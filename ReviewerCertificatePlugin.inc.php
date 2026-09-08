<?php
// SPDX-License-Identifier: GPL-3.0-or-later
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');
import('lib.pkp.classes.security.Validation');
import('classes.template.TemplateManager');

class ReviewerCertificatePlugin extends GenericPlugin {

function register($category, $path, $mainContextId = null) {
if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
$success = parent::register($category, $path, $mainContextId);
if ($success && $this->getEnabled()) {
HookRegistry::register('LoadHandler', array($this, 'setupPageHandler'));
HookRegistry::register('Templates::User::userHome', array($this, 'addReviewerLink'));
HookRegistry::register('Templates::Index::journal', array($this, 'addPublicInsights'));
}
return $success;
}

function getDisplayName() {
return __('plugins.generic.reviewerCertificate.displayName');
}

function getDescription() {
return __('plugins.generic.reviewerCertificate.description');
}

function getActions($request, $actionArgs) {
	$actions = parent::getActions($request, $actionArgs);
	if (!$this->getEnabled()) return $actions;

	$settingsUrl = $request->getDispatcher()->url(
		$request,
		ROUTE_PAGE,
		null,
		'reviewercertificate',
		'settingsModal'
	);
	array_unshift($actions, new LinkAction(
		'settings',
		new AjaxModal(
			$settingsUrl,
			$this->getDisplayName(),
			'modal_settings'
		),
		__('manager.plugins.settings'),
		'settings'
	));

	// OJS exposes its native upload/upgrade action only to Site Administrators.
	// Journal Managers receive a safe in-OJS upgrade information modal instead
	// of filesystem write access.
	if (!Validation::isSiteAdmin()) {
		$upgradeUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'reviewercertificate',
			'upgradeInfo'
		);
		array_splice($actions, 1, 0, array(new LinkAction(
			'upgradeInfo',
			new AjaxModal(
				$upgradeUrl,
				__('plugins.generic.reviewerCertificate.upgrade.title'),
				'modal_upgrade'
			),
			__('grid.action.upgrade'),
			'upgrade'
		)));
	}
	return $actions;
}

function getCertificateUploadDirectory($contextId) {
	$publicDir = (string) Config::getVar('files', 'public_files_dir');
	if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $publicDir)) {
		$publicDir = Core::getBaseDir() . DIRECTORY_SEPARATOR . $publicDir;
	}
	return rtrim($publicDir, '/\\') . DIRECTORY_SEPARATOR . 'journals' .
		DIRECTORY_SEPARATOR . (int) $contextId . DIRECTORY_SEPARATOR .
		'reviewerCertificate';
}

function getCertificateAsset($request, $contextId, $settingName, $fallbackName) {
	$fileName = basename((string) $this->getSetting($contextId, $settingName));
	if ($fileName) {
		$filePath = $this->getCertificateUploadDirectory($contextId) .
			DIRECTORY_SEPARATOR . $fileName;
		if (is_file($filePath)) {
			return array(
				'exists' => true,
				'uploaded' => true,
				'path' => $filePath,
				'url' => rtrim($request->getBaseUrl(), '/') . '/public/journals/' .
					(int) $contextId . '/reviewerCertificate/' . rawurlencode($fileName),
			);
		}
	}

	$pluginPath = $this->getPluginPath();
	if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#', $pluginPath)) {
		$pluginPath = Core::getBaseDir() . DIRECTORY_SEPARATOR . $pluginPath;
	}
	$fallbackPath = rtrim($pluginPath, '/\\') . DIRECTORY_SEPARATOR .
		'assets' . DIRECTORY_SEPARATOR . basename($fallbackName);
	return array(
		'exists' => is_file($fallbackPath),
		'uploaded' => false,
		'path' => $fallbackPath,
		'url' => rtrim($request->getBaseUrl(), '/') .
			'/plugins/generic/reviewerCertificate/assets/' . rawurlencode(basename($fallbackName)),
	);
}

/**
 * Return aggregate reviewer/editor country data for one journal.
 *
 * OJS stores an ISO 3166-1 alpha-2 value in users.country. Values that are
 * missing or no longer valid are reported separately and never guessed.
 */
function getCountryStatistics($contextId) {
	$result = array(
		'countries' => array(),
		'totals' => array('reviewers' => 0, 'reviews' => 0, 'editors' => 0),
		'missing' => array('reviewers' => 0, 'editors' => 0),
	);

	try {
		$factory = new \Sokil\IsoCodes\IsoCodesFactory();
		$countryNames = array();
		foreach ($factory->getCountries() as $country) {
			$countryNames[strtoupper($country->getAlpha2())] = $country->getLocalName();
		}

		$pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
		$reviewerStatement = $pdo->prepare(
			'SELECT u.country, COUNT(DISTINCT u.user_id) AS reviewer_count, COUNT(ra.review_id) AS review_count
			FROM review_assignments ra
			JOIN submissions s ON s.submission_id = ra.submission_id
			JOIN users u ON u.user_id = ra.reviewer_id
			WHERE s.context_id = ? AND ra.date_completed IS NOT NULL AND ra.declined = 0
			GROUP BY u.country'
		);
		$reviewerStatement->execute(array((int) $contextId));
		while ($row = $reviewerStatement->fetch(\PDO::FETCH_ASSOC)) {
			$code = strtoupper(trim((string) $row['country']));
			$reviewerCount = (int) $row['reviewer_count'];
			$result['totals']['reviewers'] += $reviewerCount;
			$result['totals']['reviews'] += (int) $row['review_count'];
			if (!preg_match('/^[A-Z]{2}$/', $code) || !isset($countryNames[$code])) {
				$result['missing']['reviewers'] += $reviewerCount;
				continue;
			}
			if (!isset($result['countries'][$code])) {
				$result['countries'][$code] = array(
					'code' => $code,
					'name' => $countryNames[$code],
					'reviewers' => 0,
					'reviews' => 0,
					'editors' => 0,
				);
			}
			$result['countries'][$code]['reviewers'] += $reviewerCount;
			$result['countries'][$code]['reviews'] += (int) $row['review_count'];
		}

		$editorStatement = $pdo->prepare(
			'SELECT u.country, COUNT(DISTINCT u.user_id) AS editor_count
			FROM users u
			JOIN user_user_groups uug ON uug.user_id = u.user_id
			JOIN user_groups ug ON ug.user_group_id = uug.user_group_id
			WHERE ug.context_id = ? AND ug.role_id IN (?, ?) AND u.disabled = 0
			GROUP BY u.country'
		);
		$editorStatement->execute(array((int) $contextId, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR));
		while ($row = $editorStatement->fetch(\PDO::FETCH_ASSOC)) {
			$code = strtoupper(trim((string) $row['country']));
			$editorCount = (int) $row['editor_count'];
			$result['totals']['editors'] += $editorCount;
			if (!preg_match('/^[A-Z]{2}$/', $code) || !isset($countryNames[$code])) {
				$result['missing']['editors'] += $editorCount;
				continue;
			}
			if (!isset($result['countries'][$code])) {
				$result['countries'][$code] = array(
					'code' => $code,
					'name' => $countryNames[$code],
					'reviewers' => 0,
					'reviews' => 0,
					'editors' => 0,
				);
			}
			$result['countries'][$code]['editors'] += $editorCount;
		}
	} catch (Exception $e) {
		error_log('ReviewerCertificate country statistics error: ' . $e->getMessage());
		$result['error'] = true;
	}

	$result['countries'] = array_values($result['countries']);
	usort($result['countries'], function ($a, $b) {
		$aTotal = $a['reviewers'] + $a['editors'];
		$bTotal = $b['reviewers'] + $b['editors'];
		if ($aTotal === $bTotal) return strcasecmp($a['name'], $b['name']);
		return $bTotal - $aTotal;
	});
	return $result;
}

function addPublicInsights($hookName, $params) {
	$request = Application::get()->getRequest();
	$journal = $request->getJournal();
	if (!$journal) return false;
	$contextId = (int) $journal->getId();
	if (!$this->getSetting($contextId, 'publicInsightsEnabled')) return false;

	$statistics = $this->getCountryStatistics($contextId);
	if (!empty($statistics['error'])) return false;
	$minimumSetting = (int) $this->getSetting($contextId, 'minimumCountryCount');
	$minimum = $minimumSetting >= 1 && $minimumSetting <= 10 ? $minimumSetting : 2;
	$showReviewers = $this->getSetting($contextId, 'showReviewerStats') !== '0';
	$showEditors = $this->getSetting($contextId, 'showEditorStats') !== '0';
	$filtered = array();
	foreach ($statistics['countries'] as $country) {
		$visibleCount = ($showReviewers ? $country['reviewers'] : 0) +
			($showEditors ? $country['editors'] : 0);
		$reviewersMeetThreshold = !$showReviewers || !$country['reviewers'] || $country['reviewers'] >= $minimum;
		$editorsMeetThreshold = !$showEditors || !$country['editors'] || $country['editors'] >= $minimum;
		if ($visibleCount && $reviewersMeetThreshold && $editorsMeetThreshold) $filtered[] = $country;
	}

	$title = trim((string) $this->getSetting($contextId, 'publicInsightsTitle'));
	if (!$title) $title = __('plugins.generic.reviewerCertificate.insights.title');
	$position = $this->getSetting($contextId, 'publicInsightsPosition') === 'top' ? 'top' : 'bottom';
	$assetBase = rtrim($request->getBaseUrl(), '/') . '/plugins/generic/reviewerCertificate/assets';
	$payload = array(
		'countries' => $filtered,
		'totals' => $statistics['totals'],
		'showReviewers' => $showReviewers,
		'showEditors' => $showEditors,
		'labels' => array(
			'reviewers' => __('plugins.generic.reviewerCertificate.insights.reviewers'),
			'reviews' => __('plugins.generic.reviewerCertificate.insights.reviews'),
			'editors' => __('plugins.generic.reviewerCertificate.insights.editors'),
			'countries' => __('plugins.generic.reviewerCertificate.insights.countries'),
			'noData' => __('plugins.generic.reviewerCertificate.insights.noData'),
		),
	);
	$json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

	$output =& $params[2];
	$output .= '<section class="rc-insights" data-position="' . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') .
		'" data-map-url="' . htmlspecialchars($assetBase . '/world-110m.json', ENT_QUOTES, 'UTF-8') . '">';
	$output .= '<div class="rc-insights__heading"><div><span class="rc-insights__eyebrow">' .
		htmlspecialchars(__('plugins.generic.reviewerCertificate.insights.eyebrow'), ENT_QUOTES, 'UTF-8') .
		'</span><h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2></div></div>';
	$output .= '<div class="rc-insights__app" data-insights="' . htmlspecialchars($json, ENT_QUOTES, 'UTF-8') . '"></div>';
	$output .= '<noscript><p>' . htmlspecialchars(__('plugins.generic.reviewerCertificate.insights.javascript'), ENT_QUOTES, 'UTF-8') . '</p></noscript>';
	$output .= '</section>';
	$output .= '<script src="' . htmlspecialchars($assetBase . '/reviewerInsights.js?v=1.9.0', ENT_QUOTES, 'UTF-8') . '" defer></script>';
	return false;
}

function setupPageHandler($hookName, $params) {
$request = Application::get()->getRequest();
$this->_addReviewerAssets($request);
$page = $params[0];
if ($page === 'reviewercertificate') {
$this->import('classes.ReviewerCertificateHandler');
define('HANDLER_CLASS', 'ReviewerCertificateHandler');
$handler = new ReviewerCertificateHandler();
$handler->setPlugin($this);
return true;
}
return false;
}

function addReviewerLink($hookName, $params) {
$smarty =& $params[1];
$output =& $params[2];
$request = Application::get()->getRequest();
$user = $request->getUser();
if (!$user) return false;
$journal = $request->getJournal();
if (!$journal) return false;
$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
$userGroups = $userGroupDao->getByUserId($user->getId(), $journal->getId());
$isReviewer = false;
while ($group = $userGroups->next()) {
if ($group->getRoleId() == ROLE_ID_REVIEWER) { $isReviewer = true; break; }
}
if (!$isReviewer) return false;
$url = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'reviewercertificate', 'index');
$output .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="pkp_button" style="margin-top:8px;display:inline-block;">📜 My Review Certificates</a>';
return false;
}

function _addReviewerAssets($request) {
	$user = $request->getUser();
	$journal = $request->getJournal();
	if (!$user || !$journal) return;

	$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
	$userGroups = $userGroupDao->getByUserId($user->getId(), $journal->getId());
	$isReviewer = false;
	while ($group = $userGroups->next()) {
		if ($group->getRoleId() == ROLE_ID_REVIEWER) {
			$isReviewer = true;
			break;
		}
	}
	if (!$isReviewer) return;

	$scriptUrl = $request->getBaseUrl() .
		'/plugins/generic/reviewerCertificate/assets/reviewerCertificateTab.js?v=1.9.0';
	$templateMgr = TemplateManager::getManager($request);
	$templateMgr->addJavaScript(
		'reviewerCertificateTab',
		$scriptUrl,
		array('contexts' => 'backend')
	);
}
}
