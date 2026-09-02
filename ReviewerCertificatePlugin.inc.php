<?php
// SPDX-License-Identifier: GPL-3.0-or-later
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.RedirectAction');
import('classes.template.TemplateManager');

class ReviewerCertificatePlugin extends GenericPlugin {

function register($category, $path, $mainContextId = null) {
if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
$success = parent::register($category, $path, $mainContextId);
if ($success && $this->getEnabled()) {
HookRegistry::register('LoadHandler', array($this, 'setupPageHandler'));
HookRegistry::register('Templates::User::userHome', array($this, 'addReviewerLink'));
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
		'settings'
	);
	array_unshift($actions, new LinkAction(
		'settings',
		new RedirectAction($settingsUrl),
		__('manager.plugins.settings'),
		null
	));
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
		'/plugins/generic/reviewerCertificate/assets/reviewerCertificateTab.js?v=1.8.0';
	$templateMgr = TemplateManager::getManager($request);
	$templateMgr->addJavaScript(
		'reviewerCertificateTab',
		$scriptUrl,
		array('contexts' => 'backend')
	);
}
}
