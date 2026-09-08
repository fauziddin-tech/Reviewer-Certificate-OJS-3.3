<?php
// SPDX-License-Identifier: GPL-3.0-or-later
import('classes.handler.Handler');

class ReviewerCertificateHandler extends Handler {
	var $_plugin;

	function __construct() { parent::__construct(); }
	function setPlugin($plugin) { $this->_plugin = $plugin; }
	function authorize($request, &$args, $roleAssignments) { return true; }

	function _getPlugin() {
		if ($this->_plugin) return $this->_plugin;
		return PluginRegistry::getPlugin('generic', 'reviewercertificateplugin');
	}

	function _getPdo() {
		return \Illuminate\Database\Capsule\Manager::connection()->getPdo();
	}

	function _e($value) {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	function _sendPrivacyHeaders($publicVerification = false) {
		if (headers_sent()) return;
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
		header('Referrer-Policy: no-referrer');
		header('X-Content-Type-Options: nosniff');
		if ($publicVerification) {
			header('X-Robots-Tag: noindex, nofollow, noarchive');
		}
	}

	function _settingNumber($plugin, $contextId, $name, $default, $minimum, $maximum) {
		$value = $plugin ? $plugin->getSetting($contextId, $name) : null;
		$value = is_numeric($value) ? (float) $value : (float) $default;
		return max((float) $minimum, min((float) $maximum, $value));
	}

	function _getFaviconHtml($journal, $baseUrl) {
		if (!$journal) return '';

		$favicon = method_exists($journal, 'getLocalizedData')
			? $journal->getLocalizedData('favicon')
			: $journal->getData('favicon');
		$uploadName = '';

		if (is_array($favicon) && !empty($favicon['uploadName'])) {
			$uploadName = $favicon['uploadName'];
		} elseif (is_object($favicon) && method_exists($favicon, 'getData')) {
			$uploadName = $favicon->getData('uploadName');
		} elseif (is_string($favicon)) {
			$uploadName = $favicon;
		}

		$uploadName = basename((string) $uploadName);
		if (!$uploadName) return '';

		$extension = strtolower(pathinfo($uploadName, PATHINFO_EXTENSION));
		$mimeTypes = array(
			'ico' => 'image/x-icon',
			'png' => 'image/png',
			'svg' => 'image/svg+xml',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
		);
		$mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'image/png';
		$faviconUrl = rtrim($baseUrl, '/') . '/public/journals/' .
			(int) $journal->getId() . '/' . rawurlencode($uploadName);

		return '<link rel="icon" type="' . $this->_e($mimeType) .
			'" href="' . $this->_e($faviconUrl) . '">';
	}

	function _isJournalManager($request, $journal) {
		$user = $request->getUser();
		if (!$user || !$journal) return false;
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userGroups = $userGroupDao->getByUserId($user->getId(), $journal->getId());
		while ($group = $userGroups->next()) {
			if ($group->getRoleId() == ROLE_ID_MANAGER) return true;
		}
		if (defined('ROLE_ID_SITE_ADMIN')) {
			$allUserGroups = $userGroupDao->getByUserId($user->getId());
			while ($group = $allUserGroups->next()) {
				if ($group->getRoleId() == ROLE_ID_SITE_ADMIN) return true;
			}
		}
		return false;
	}

	function _validateSettingsImage($field, $label, &$errors) {
		if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
		$file = $_FILES[$field];
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$errors[] = $label . ' upload failed (error code ' . (int) $file['error'] . ').';
			return null;
		}
		if ((int) $file['size'] <= 0 || (int) $file['size'] > 2 * 1024 * 1024) {
			$errors[] = $label . ' must be no larger than 2 MB.';
			return null;
		}
		if (!is_uploaded_file($file['tmp_name'])) {
			$errors[] = $label . ' was not received as a valid uploaded file.';
			return null;
		}
		$imageInfo = @getimagesize($file['tmp_name']);
		if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
			$errors[] = $label . ' must be a valid raster image.';
			return null;
		}
		if ($imageInfo[0] < 40 || $imageInfo[1] < 40 || $imageInfo[0] > 3000 || $imageInfo[1] > 3000) {
			$errors[] = $label . ' dimensions must be between 40×40 and 3000×3000 pixels.';
			return null;
		}
		$extensions = array(IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg');
		if (defined('IMAGETYPE_WEBP')) $extensions[IMAGETYPE_WEBP] = 'webp';
		if (!isset($extensions[$imageInfo[2]])) {
			$errors[] = $label . ' must be PNG, JPG/JPEG, or WebP.';
			return null;
		}
		return array(
			'tmpName' => $file['tmp_name'],
			'extension' => $extensions[$imageInfo[2]],
			'label' => $label,
		);
	}

	function _settingsRandomSuffix() {
		try {
			return bin2hex(random_bytes(4));
		} catch (Exception $e) {
			return substr(md5(uniqid('', true)), 0, 8);
		}
	}

	function _randomHex($bytes) {
		try {
			return bin2hex(random_bytes((int) $bytes));
		} catch (Exception $e) {
			if (function_exists('openssl_random_pseudo_bytes')) {
				$random = openssl_random_pseudo_bytes((int) $bytes);
				if ($random !== false) return bin2hex($random);
			}
			return hash('sha256', uniqid('', true) . microtime(true) . mt_rand());
		}
	}

	function _getVerificationSecret($plugin, $journalId, $createIfMissing = false) {
		if (!$plugin) return '';
		$secret = strtolower(trim((string) $plugin->getSetting($journalId, 'verificationSecret')));
		if (preg_match('/^[a-f0-9]{64}$/', $secret)) return $secret;
		if (!$createIfMissing) return '';

		$secret = $this->_randomHex(32);
		if (!preg_match('/^[a-f0-9]{64}$/', $secret)) return '';
		$plugin->updateSetting($journalId, 'verificationSecret', $secret, 'string');
		return $secret;
	}

	function _getVerificationToken($plugin, $journalId, $reviewId, $createSecret = false) {
		$secret = $this->_getVerificationSecret($plugin, $journalId, $createSecret);
		if (!$secret) return '';
		$payload = (int) $journalId . ':' . (int) $reviewId;
		return substr(hash_hmac('sha256', $payload, $secret), 0, 40);
	}

	function _isValidVerificationToken($plugin, $journalId, $reviewId, $token) {
		$token = strtolower(trim((string) $token));
		if (!preg_match('/^[a-f0-9]{40}$/', $token)) return false;
		$expected = $this->_getVerificationToken($plugin, $journalId, $reviewId, false);
		return $expected && hash_equals($expected, $token);
	}

	function _deleteSettingsAsset($uploadDir, $fileName, $keepName = '') {
		$fileName = basename((string) $fileName);
		if (!$fileName || $fileName === $keepName) return;
		$path = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
		if (is_file($path)) @unlink($path);
	}

	function _applySettingsAsset($plugin, $journalId, $uploadDir, $definition, $newFiles, $request) {
		$oldName = basename((string) $plugin->getSetting($journalId, $definition['setting']));
		if (isset($newFiles[$definition['field']])) {
			$newName = $newFiles[$definition['field']]['name'];
			$plugin->updateSetting($journalId, $definition['setting'], $newName, 'string');
			$this->_deleteSettingsAsset($uploadDir, $oldName, $newName);
		} elseif ($request->getUserVar($definition['remove'])) {
			$plugin->updateSetting($journalId, $definition['setting'], '', 'string');
			$this->_deleteSettingsAsset($uploadDir, $oldName);
		}
	}

	function _saveCertificateSettings($request, $journal, $plugin, &$errors) {
		$session = $request->getSession();
		$expectedToken = $session ? (string) $session->getCSRFToken() : '';
		$submittedToken = (string) $request->getUserVar('csrfToken');
		if (!$expectedToken || !$submittedToken || !hash_equals($expectedToken, $submittedToken)) {
			$errors[] = 'Your session has expired. Reload the page and try again.';
			return false;
		}

		$editorName = trim((string) $request->getUserVar('editorName'));
		$editorTitle = trim((string) $request->getUserVar('editorTitle'));
		$publicInsightsTitle = trim((string) $request->getUserVar('publicInsightsTitle'));
		if (strlen($editorName) > 150 || strlen($editorTitle) > 150) {
			$errors[] = 'The signatory name and title must not exceed 150 characters.';
		}
		if (strlen($publicInsightsTitle) > 120) {
			$errors[] = 'The public statistics title must not exceed 120 characters.';
		}
		$minimumCountryCount = (int) $request->getUserVar('minimumCountryCount');
		if ($minimumCountryCount < 1 || $minimumCountryCount > 10) {
			$errors[] = 'The privacy threshold must be between 1 and 10 people per country.';
		}
		$publicInsightsPosition = (string) $request->getUserVar('publicInsightsPosition');
		if (!in_array($publicInsightsPosition, array('top', 'bottom'))) {
			$errors[] = 'Choose a valid public statistics position.';
		}
		$showReviewerStats = $request->getUserVar('showReviewerStats') ? '1' : '0';
		$showEditorStats = $request->getUserVar('showEditorStats') ? '1' : '0';
		if ($showReviewerStats === '0' && $showEditorStats === '0') {
			$errors[] = 'Select reviewer statistics, editor statistics, or both.';
		}

		$numberRules = array(
			'logoHeightMm' => array(10, 25, 'Logo height'),
			'signatureWidthMm' => array(20, 55, 'Signature width'),
			'stampSizeMm' => array(15, 35, 'Stamp size'),
		);
		$numbers = array();
		foreach ($numberRules as $name => $rule) {
			$value = str_replace(',', '.', trim((string) $request->getUserVar($name)));
			if (!is_numeric($value) || (float) $value < $rule[0] || (float) $value > $rule[1]) {
				$errors[] = $rule[2] . ' must be between ' . $rule[0] . ' and ' . $rule[1] . ' mm.';
			} else {
				$numbers[$name] = (string) ((float) $value);
			}
		}

		$definitions = array(
			array('field' => 'logoUpload', 'label' => 'Logo', 'prefix' => 'logo', 'setting' => 'logoFile', 'remove' => 'removeLogo'),
			array('field' => 'signatureUpload', 'label' => 'Signature', 'prefix' => 'signature', 'setting' => 'signatureFile', 'remove' => 'removeSignature'),
			array('field' => 'stampUpload', 'label' => 'Stamp', 'prefix' => 'stamp', 'setting' => 'stampFile', 'remove' => 'removeStamp'),
		);
		$uploads = array();
		foreach ($definitions as $definition) {
			$upload = $this->_validateSettingsImage($definition['field'], $definition['label'], $errors);
			if ($upload) $uploads[$definition['field']] = $upload;
		}
		if (!empty($errors)) return false;

		$uploadDir = $plugin->getCertificateUploadDirectory($journal->getId());
		$newFiles = array();
		if (!empty($uploads)) {
			if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
				$errors[] = 'The image directory could not be created. Check public directory permissions.';
				return false;
			}
			if (!is_writable($uploadDir)) {
				$errors[] = 'The OJS public image directory is not writable.';
				return false;
			}
			foreach ($definitions as $definition) {
				if (!isset($uploads[$definition['field']])) continue;
				$upload = $uploads[$definition['field']];
				$fileName = $definition['prefix'] . '-' . date('YmdHis') . '-' .
					$this->_settingsRandomSuffix() . '.' . $upload['extension'];
				$destination = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
				if (!move_uploaded_file($upload['tmpName'], $destination)) {
					foreach ($newFiles as $newFile) {
						if (is_file($newFile['path'])) @unlink($newFile['path']);
					}
					$errors[] = $upload['label'] . ' could not be stored. Check directory permissions.';
					return false;
				}
				@chmod($destination, 0644);
				$newFiles[$definition['field']] = array('name' => $fileName, 'path' => $destination);
			}
		}

		$plugin->updateSetting($journal->getId(), 'editorName', $editorName, 'string');
		$plugin->updateSetting($journal->getId(), 'editorTitle', $editorTitle, 'string');
		$plugin->updateSetting($journal->getId(), 'publicInsightsEnabled', $request->getUserVar('publicInsightsEnabled') ? '1' : '0', 'string');
		$plugin->updateSetting($journal->getId(), 'publicInsightsTitle', $publicInsightsTitle, 'string');
		$plugin->updateSetting($journal->getId(), 'publicInsightsPosition', $publicInsightsPosition, 'string');
		$plugin->updateSetting($journal->getId(), 'minimumCountryCount', (string) $minimumCountryCount, 'string');
		$plugin->updateSetting($journal->getId(), 'showReviewerStats', $showReviewerStats, 'string');
		$plugin->updateSetting($journal->getId(), 'showEditorStats', $showEditorStats, 'string');
		foreach ($numbers as $name => $value) {
			$plugin->updateSetting($journal->getId(), $name, $value, 'string');
		}
		foreach ($definitions as $definition) {
			$this->_applySettingsAsset($plugin, $journal->getId(), $uploadDir, $definition, $newFiles, $request);
		}
		return true;
	}

	function settings($args, $request) {
		$this->_sendPrivacyHeaders(true);
		$user = $request->getUser();
		$journal = $request->getJournal();
		$plugin = $this->_getPlugin();
		if (!$user) { $request->redirect(null, 'login'); return; }
		if (!$journal || !$plugin || !$this->_isJournalManager($request, $journal)) {
			http_response_code(403);
			echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access denied</title></head>';
			echo '<body style="font-family:Arial,sans-serif;padding:40px"><h2>Access denied</h2>';
			echo '<p>Only a Journal Manager can change reviewer certificate settings.</p></body></html>';
			return;
		}

		$errors = array();
		$saved = false;
		if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
			$saved = $this->_saveCertificateSettings($request, $journal, $plugin, $errors);
		}

		$isPostWithErrors = !$saved && !empty($errors);
		$editorName = $isPostWithErrors ? (string) $request->getUserVar('editorName') : (string) $plugin->getSetting($journal->getId(), 'editorName');
		$editorTitle = $isPostWithErrors ? (string) $request->getUserVar('editorTitle') : (string) $plugin->getSetting($journal->getId(), 'editorTitle');
		if (!$editorTitle) $editorTitle = 'Editor in Chief';
		$logoHeight = $isPostWithErrors ? (string) $request->getUserVar('logoHeightMm') : $this->_settingNumber($plugin, $journal->getId(), 'logoHeightMm', 19, 10, 25);
		$signatureWidth = $isPostWithErrors ? (string) $request->getUserVar('signatureWidthMm') : $this->_settingNumber($plugin, $journal->getId(), 'signatureWidthMm', 38, 20, 55);
		$stampSize = $isPostWithErrors ? (string) $request->getUserVar('stampSizeMm') : $this->_settingNumber($plugin, $journal->getId(), 'stampSizeMm', 22, 15, 35);
		$publicInsightsEnabled = $isPostWithErrors ? (bool) $request->getUserVar('publicInsightsEnabled') : (bool) $plugin->getSetting($journal->getId(), 'publicInsightsEnabled');
		$publicInsightsTitle = $isPostWithErrors ? (string) $request->getUserVar('publicInsightsTitle') : (string) $plugin->getSetting($journal->getId(), 'publicInsightsTitle');
		$publicInsightsPosition = $isPostWithErrors ? (string) $request->getUserVar('publicInsightsPosition') : (string) $plugin->getSetting($journal->getId(), 'publicInsightsPosition');
		if (!in_array($publicInsightsPosition, array('top', 'bottom'))) $publicInsightsPosition = 'bottom';
		$minimumCountryCount = $isPostWithErrors ? (int) $request->getUserVar('minimumCountryCount') : (int) $plugin->getSetting($journal->getId(), 'minimumCountryCount');
		if ($minimumCountryCount < 1 || $minimumCountryCount > 10) $minimumCountryCount = 2;
		$showReviewerStats = $isPostWithErrors ? (bool) $request->getUserVar('showReviewerStats') : $plugin->getSetting($journal->getId(), 'showReviewerStats') !== '0';
		$showEditorStats = $isPostWithErrors ? (bool) $request->getUserVar('showEditorStats') : $plugin->getSetting($journal->getId(), 'showEditorStats') !== '0';
		$countryStatistics = method_exists($plugin, 'getCountryStatistics') ? $plugin->getCountryStatistics($journal->getId()) : null;
		$logoAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'logoFile', 'logo.png');
		$signatureAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'signatureFile', 'signature.png');
		$stampAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'stampFile', 'stempel.png');
		$csrfToken = $request->getSession() ? $request->getSession()->getCSRFToken() : '';
		$baseUrl = rtrim($request->getBaseUrl(), '/');
		$backUrl = $baseUrl . '/index.php/' . rawurlencode($journal->getPath()) . '/management/settings/website#plugins';
		$actionUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'reviewercertificate', 'settings');

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow,noarchive">';
		echo $this->_getFaviconHtml($journal, $baseUrl);
		echo '<title>Reviewer Certificate Settings</title><style>';
		echo '*{box-sizing:border-box}body{margin:0;background:#eef1f4;color:#252a32;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}.top{background:#003b4d;color:#fff;padding:18px 28px;font-size:20px;font-weight:700}.page{max-width:1080px;margin:32px auto;padding:0 18px}.panel{background:#fff;border:1px solid #d8dde3;padding:28px;box-shadow:0 2px 8px rgba(0,0,0,.04)}.head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;border-bottom:1px solid #e3e6ea;padding-bottom:18px;margin-bottom:22px}.head h1{font-size:24px;margin:0 0 7px}.head p{margin:0;color:#667085;line-height:1.5}.back{color:#0071a1;font-weight:600;text-decoration:none;white-space:nowrap}.notice{padding:13px 15px;margin-bottom:18px;border-left:4px solid}.success{background:#ecfdf3;border-color:#12b76a;color:#067647}.error{background:#fef3f2;border-color:#f04438;color:#b42318}.warning{background:#fffaeb;border-color:#f79009;color:#7a2e0e}.error ul{margin:0;padding-left:20px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.card{border:1px solid #d8dde3;padding:16px}.card h2{font-size:17px;margin:0 0 13px}.preview{height:120px;background:#f7f8fa;border:1px dashed #c6ccd4;display:flex;align-items:center;justify-content:center;padding:10px}.preview img{max-width:100%;max-height:98px;object-fit:contain}.field{margin-top:14px}.field>label,.public-grid label{display:block;font-weight:650;font-size:13px;margin-bottom:6px}.field input[type=file]{max-width:100%;font-size:12px}.field input[type=number],.signatory input,.public-grid input[type=text],.public-grid input[type=number],.public-grid select{width:100%;height:40px;border:1px solid #aeb5bf;padding:8px 10px;background:#fff}.remove{display:block;font-size:12px;color:#555;line-height:1.4;margin-top:12px}.signatory,.public-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px}.signatory label{display:block;font-size:13px;font-weight:650;margin-bottom:6px}.section{margin-top:28px;padding-top:22px;border-top:1px solid #e3e6ea}.section h2{margin:0 0 6px;font-size:19px}.check{display:flex!important;gap:8px;align-items:flex-start;font-weight:500!important;line-height:1.45}.quality{display:flex;gap:12px;margin-top:14px}.quality span{padding:8px 11px;border-radius:6px;background:#f2f4f7;font-size:12px}.actions{display:flex;justify-content:flex-end;align-items:center;gap:14px;border-top:1px solid #e3e6ea;margin-top:24px;padding-top:20px}.save{border:0;background:#0071a1;color:#fff;font-weight:700;padding:11px 20px;cursor:pointer}.help{font-size:13px;color:#667085;margin:0 0 20px}@media(max-width:760px){.grid,.signatory,.public-grid{grid-template-columns:1fr}.quality{flex-direction:column}.head{display:block}.back{display:inline-block;margin-top:12px}}</style></head><body>';
		echo '<div class="top">Reviewer Certificate</div><main class="page"><div class="panel">';
		echo '<div class="head"><div><h1>Certificate image settings</h1><p>Upload and size the journal logo, editor signature, and official stamp.</p></div><a class="back" href="' . $this->_e($backUrl) . '">&larr; Back to Plugins</a></div>';
		if ($saved) echo '<div class="notice success">Settings and images were saved successfully.</div>';
		if (!empty($errors)) {
			echo '<div class="notice error"><ul>';
			foreach ($errors as $error) echo '<li>' . $this->_e($error) . '</li>';
			echo '</ul></div>';
		}
		echo '<p class="help">Accepted: PNG, JPG/JPEG, or WebP. Maximum 2 MB and 3000×3000 px per image. Transparent PNG is recommended for signatures and stamps.</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . $this->_e($actionUrl) . '">';
		echo '<input type="hidden" name="csrfToken" value="' . $this->_e($csrfToken) . '"><div class="grid">';
		$this->_renderSettingsCard('Journal logo', 'logoUpload', 'removeLogo', 'logoHeightMm', 'Display height (10–25 mm)', $logoHeight, $logoAsset['url'], 'rcLogoPreview');
		$this->_renderSettingsCard('Editor signature', 'signatureUpload', 'removeSignature', 'signatureWidthMm', 'Maximum width (20–55 mm)', $signatureWidth, $signatureAsset['url'], 'rcSignaturePreview');
		$this->_renderSettingsCard('Official stamp', 'stampUpload', 'removeStamp', 'stampSizeMm', 'Display size (15–35 mm)', $stampSize, $stampAsset['url'], 'rcStampPreview');
		echo '</div><div class="signatory"><div><label for="editorName">Signatory name</label><input id="editorName" name="editorName" type="text" maxlength="150" value="' . $this->_e($editorName) . '" placeholder="Dr. Editor Name"></div>';
		echo '<div><label for="editorTitle">Signatory title</label><input id="editorTitle" name="editorTitle" type="text" maxlength="150" value="' . $this->_e($editorTitle) . '" placeholder="Editor in Chief"></div></div>';
		echo '<section class="section"><h2>Public reviewer and editor map</h2><p class="help">Show aggregate country statistics on the journal homepage. Country names come from the ISO country selected in each OJS user profile; names and email addresses are never published.</p>';
		echo '<label class="check"><input type="checkbox" name="publicInsightsEnabled" value="1"' . ($publicInsightsEnabled ? ' checked' : '') . '> Enable the public statistics panel</label>';
		echo '<div class="public-grid"><div><label for="publicInsightsTitle">Panel title</label><input id="publicInsightsTitle" name="publicInsightsTitle" type="text" maxlength="120" value="' . $this->_e($publicInsightsTitle) . '" placeholder="Our Reviewer and Editorial Community"></div>';
		echo '<div><label for="publicInsightsPosition">Homepage position</label><select id="publicInsightsPosition" name="publicInsightsPosition"><option value="bottom"' . ($publicInsightsPosition === 'bottom' ? ' selected' : '') . '>After homepage content</option><option value="top"' . ($publicInsightsPosition === 'top' ? ' selected' : '') . '>Before homepage content</option></select></div>';
		echo '<div><label for="minimumCountryCount">Privacy threshold (people per role and country)</label><input id="minimumCountryCount" name="minimumCountryCount" type="number" min="1" max="10" value="' . $this->_e($minimumCountryCount) . '"><p class="help">Every non-zero displayed role count must meet this threshold. Recommended: 2.</p></div>';
		echo '<div><label>Included roles</label><label class="check"><input type="checkbox" name="showReviewerStats" value="1"' . ($showReviewerStats ? ' checked' : '') . '> Completed reviewers</label><label class="check"><input type="checkbox" name="showEditorStats" value="1"' . ($showEditorStats ? ' checked' : '') . '> Journal managers and editors</label></div></div>';
		if ($countryStatistics && empty($countryStatistics['error'])) {
			echo '<div class="quality"><span>Reviewer profiles without a valid country: <strong>' . (int) $countryStatistics['missing']['reviewers'] . '</strong></span><span>Editor profiles without a valid country: <strong>' . (int) $countryStatistics['missing']['editors'] . '</strong></span></div>';
			if ($countryStatistics['missing']['reviewers'] || $countryStatistics['missing']['editors']) echo '<div class="notice warning" style="margin-top:12px">Ask these users to choose their country in <strong>Profile → Contact</strong>. Free-text country guessing is intentionally avoided so the map remains accurate.</div>';
		}
		echo '</section>';
		echo '<div class="actions"><a class="back" href="' . $this->_e($backUrl) . '">Cancel</a><button class="save" type="submit">Save settings</button></div></form>';
		echo '</div></main><script>(function(){function p(i,o){var e=document.getElementById(i);if(!e)return;e.addEventListener("change",function(){var f=this.files&&this.files[0];if(!f)return;var r=new FileReader();r.onload=function(x){document.getElementById(o).src=x.target.result};r.readAsDataURL(f)})}p("logoUpload","rcLogoPreview");p("signatureUpload","rcSignaturePreview");p("stampUpload","rcStampPreview")})();</script></body></html>';
	}

	function _renderSettingsCard($title, $uploadField, $removeField, $sizeField, $sizeLabel, $sizeValue, $previewUrl, $previewId) {
		$limits = array(
			'logoHeightMm' => array(10, 25),
			'signatureWidthMm' => array(20, 55),
			'stampSizeMm' => array(15, 35),
		);
		$minimum = isset($limits[$sizeField]) ? $limits[$sizeField][0] : 1;
		$maximum = isset($limits[$sizeField]) ? $limits[$sizeField][1] : 100;
		echo '<section class="card"><h2>' . $this->_e($title) . '</h2><div class="preview"><img id="' . $this->_e($previewId) . '" src="' . $this->_e($previewUrl) . '" alt="' . $this->_e($title) . '"></div>';
		echo '<div class="field"><label for="' . $this->_e($uploadField) . '">Replace image</label><input id="' . $this->_e($uploadField) . '" name="' . $this->_e($uploadField) . '" type="file" accept="image/png,image/jpeg,image/webp"></div>';
		echo '<label class="remove"><input type="checkbox" name="' . $this->_e($removeField) . '" value="1"> Remove uploaded image and use bundled fallback</label>';
		echo '<div class="field"><label for="' . $this->_e($sizeField) . '">' . $this->_e($sizeLabel) . '</label><input id="' . $this->_e($sizeField) . '" name="' . $this->_e($sizeField) . '" type="number" min="' . $minimum . '" max="' . $maximum . '" step="0.5" value="' . $this->_e($sizeValue) . '"></div></section>';
	}

	function _reviewBelongsToJournal($reviewId, $journalId) {
		try {
			$stmt = $this->_getPdo()->prepare(
				'SELECT 1 FROM review_assignments ra
				JOIN submissions s ON s.submission_id = ra.submission_id
				WHERE ra.review_id = ? AND s.context_id = ? LIMIT 1'
			);
			$stmt->execute(array((int) $reviewId, (int) $journalId));
			return (bool) $stmt->fetchColumn();
		} catch (Exception $e) {
			error_log('ReviewerCertificate journal validation error: ' . $e->getMessage());
			return false;
		}
	}

	function index($args, $request) {
		$this->_sendPrivacyHeaders(true);
		$user = $request->getUser();
		if (!$user) { $request->redirect(null, 'login'); return; }
		$journal = $request->getJournal();
		if (!$journal) { http_response_code(404); echo 'Journal not found.'; return; }
		$reviews = $this->_getReviews($user->getId(), $journal->getId());
		$journalPath = $journal->getPath();
		$baseUrl = $request->getBaseUrl();
		$faviconHtml = $this->_getFaviconHtml($journal, $baseUrl);

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<meta name="robots" content="noindex,nofollow,noarchive">';
		echo $faviconHtml;
		echo '<title>My Review Certificates</title>';
		echo '<style>:root{--primary:#173f7a;--dark:#102e5a;--line:#dce5f0;--muted:#667085}*{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#f3f6fa;margin:0;padding:32px 20px;color:#172033}.wrap{max-width:880px;margin:0 auto;background:#fff;border:1px solid #e7ecf2;border-radius:14px;box-shadow:0 8px 28px rgba(16,46,90,.08);padding:34px}h1{font-size:1.65rem;margin:0 0 8px;color:var(--dark);border-bottom:3px solid var(--primary);padding-bottom:14px}.sub{font-size:.9rem;line-height:1.6;color:var(--muted);margin:0 0 26px}.item{border:1px solid var(--line);border-radius:10px;padding:18px 20px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:20px;transition:.15s ease}.item:hover{border-color:#b8cbe2;background:#fbfdff}.info{flex:1;min-width:0}.title{font-weight:650;font-size:.98rem;line-height:1.45;margin-bottom:6px}.meta{font-size:.82rem;color:var(--muted)}.btn{background:var(--primary);color:#fff;padding:10px 17px;border-radius:7px;text-decoration:none;font-size:.84rem;font-weight:600;white-space:nowrap;display:inline-block}.btn:hover{background:var(--dark)}.empty{text-align:center;padding:46px 20px;color:var(--muted);border:1px dashed var(--line);border-radius:10px}.back{display:inline-block;margin-bottom:20px;color:var(--primary);text-decoration:none;font-size:.9rem;font-weight:600}@media(max-width:640px){body{padding:16px 10px}.wrap{padding:22px 16px}.item{align-items:flex-start;flex-direction:column}.btn{width:100%;text-align:center}}</style></head><body><div class="wrap">';
		echo '<a class="back" href="' . $this->_e($baseUrl . '/index.php/' . $journalPath . '/user/profile') . '">&larr; Back to Profile</a>';
		echo '<h1>My Review Certificates</h1>';
		echo '<p class="sub">Click the button to open your certificate, then use browser Print (Ctrl+P) and select "Save as PDF".</p>';
		$country = strtoupper(trim((string) $user->getCountry()));
		$validCountry = false;
		if (preg_match('/^[A-Z]{2}$/', $country)) {
			try {
				$factory = new \Sokil\IsoCodes\IsoCodesFactory();
				foreach ($factory->getCountries() as $countryItem) {
					if (strtoupper($countryItem->getAlpha2()) === $country) { $validCountry = true; break; }
				}
			} catch (Exception $e) {}
		}
		if (!$validCountry) {
			$profileUrl = $baseUrl . '/index.php/' . $journalPath . '/user/profile';
			echo '<div style="margin:0 0 18px;padding:13px 15px;background:#fffaeb;border-left:4px solid #f79009;color:#7a2e0e;font-size:.88rem;line-height:1.5">Your country has not been selected correctly. Please update <a href="' . $this->_e($profileUrl) . '">Profile → Contact</a> so your contribution is represented accurately in the public reviewer map.</div>';
		}
		if (empty($reviews)) {
			echo '<div class="empty">No completed reviews found.</div>';
		} else {
			foreach ($reviews as $r) {
				$viewUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'reviewercertificate', 'view', array($r['reviewId']));
				echo '<div class="item" data-submission-id="' . $this->_e($r['submissionId']) .
					'" data-authors="' . $this->_e($r['authors']) . '"><div class="info"><div class="title">' . htmlspecialchars($r['title']) . '</div>';
				echo '<div class="meta">Completed: ' . htmlspecialchars($r['date']) . '</div></div>';
				echo '<a class="btn" href="' . $this->_e($viewUrl) . '" target="_blank" rel="noopener">View Certificate</a></div>';
			}
		}
		echo '</div></body></html>';
	}

	function view($args, $request) {
		$this->_sendPrivacyHeaders(true);
		$user = $request->getUser();
		if (!$user) { $request->redirect(null, 'login'); return; }
		$reviewId = isset($args[0]) ? (int)$args[0] : 0;
		if (!$reviewId) { $request->redirect(null, 'index'); return; }

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$ra = $reviewAssignmentDao->getById($reviewId);
		$journal = $request->getJournal();
		if (!$journal) { http_response_code(404); echo 'Journal not found.'; return; }
		if (!$ra || $ra->getReviewerId() != $user->getId() || !$ra->getDateCompleted() ||
			!$this->_reviewBelongsToJournal($reviewId, $journal->getId())) {
			$request->redirect(null, 'index'); return;
		}

		$plugin    = $this->_getPlugin();
		$baseUrl   = $request->getBaseUrl();

		// Ambil judul artikel via PDO
		$articleTitle = '(No title)';
		try {
			$pdo = $this->_getPdo();
			$stmt = $pdo->prepare('SELECT ps.setting_value FROM publications p JOIN publication_settings ps ON ps.publication_id = p.publication_id WHERE p.submission_id = ? AND ps.setting_name = ? ORDER BY CASE WHEN ps.locale = ? THEN 0 ELSE 1 END LIMIT 1');
			$stmt->execute(array($ra->getSubmissionId(), 'title', 'en_US'));
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			if ($row) $articleTitle = $row['setting_value'];
		} catch (Exception $e) {
			error_log('RC view title error: ' . $e->getMessage());
		}

		$reviewerName  = $user->getFullName();
		$journalName   = $journal->getLocalizedName();
		$printIssn     = $journal->getData('printIssn');
		$onlineIssn    = $journal->getData('onlineIssn');
		$dateFormatted = strtoupper(date('F Y', strtotime($ra->getDateCompleted())));
		$editorName    = $plugin ? $plugin->getSetting($journal->getId(), 'editorName') : '';
		$editorTitle   = $plugin ? $plugin->getSetting($journal->getId(), 'editorTitle') : 'Editor in Chief';
		if (!$editorTitle) $editorTitle = 'Editor in Chief';
		$journalUrl    = $baseUrl . '/index.php/' . $journal->getPath();
		$siteHost      = parse_url($baseUrl, PHP_URL_HOST);
		$headerMeta    = array();
		if ($printIssn) $headerMeta[] = 'P-ISSN ' . $printIssn;
		if ($onlineIssn) $headerMeta[] = 'E-ISSN ' . $onlineIssn;
		if ($siteHost) $headerMeta[] = $siteHost;

		// Assets uploaded through plugin settings, with bundled files as fallback.
		if ($plugin) {
			$logoAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'logoFile', 'logo.png');
			$signatureAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'signatureFile', 'signature.png');
			$stampAsset = $plugin->getCertificateAsset($request, $journal->getId(), 'stampFile', 'stempel.png');
		} else {
			$assetsUrl = $baseUrl . '/plugins/generic/reviewerCertificate/assets';
			$assetsDir = dirname(dirname(__FILE__)) . '/assets';
			$logoAsset = array('exists' => is_file($assetsDir . '/logo.png'), 'url' => $assetsUrl . '/logo.png');
			$signatureAsset = array('exists' => is_file($assetsDir . '/signature.png'), 'url' => $assetsUrl . '/signature.png');
			$stampAsset = array('exists' => is_file($assetsDir . '/stempel.png'), 'url' => $assetsUrl . '/stempel.png');
		}
		$logoHeightMm = $this->_settingNumber($plugin, $journal->getId(), 'logoHeightMm', 19, 10, 25);
		$signatureWidthMm = $this->_settingNumber($plugin, $journal->getId(), 'signatureWidthMm', 38, 20, 55);
		$stampSizeMm = $this->_settingNumber($plugin, $journal->getId(), 'stampSizeMm', 22, 15, 35);

		// Non-guessable, journal-specific verification URL. The QR is rendered locally.
		$verificationToken = $this->_getVerificationToken(
			$plugin,
			$journal->getId(),
			$reviewId,
			true
		);
		if (!$verificationToken) {
			http_response_code(500);
			echo 'Certificate verification token could not be created.';
			return;
		}
		$verifyUrl = $journalUrl . '/reviewercertificate/verify/' .
			$reviewId . '/' . $verificationToken;
		$qrScriptUrl = $baseUrl .
			'/plugins/generic/reviewerCertificate/assets/reviewerCertificateQr.js?v=1.9.0';

		echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		echo '<meta name="robots" content="noindex,nofollow,noarchive">';
		echo $this->_getFaviconHtml($journal, $baseUrl);
		echo '<title>Certificate - ' . htmlspecialchars($reviewerName) . '</title>';
		echo '<style>';
		echo '*{margin:0;padding:0;box-sizing:border-box}';
		echo 'body{font-family:"Helvetica Neue",Arial,sans-serif;background:#eef2f7;color:#172033}';
		echo '.cert{width:297mm;height:210mm;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:20mm 24mm;background:radial-gradient(circle at 7% 10%,rgba(201,162,83,.09) 0,rgba(201,162,83,0) 24%),radial-gradient(circle at 93% 90%,rgba(23,63,122,.07) 0,rgba(23,63,122,0) 25%),#fff;overflow:hidden}';
		echo '.border-outer{position:absolute;z-index:3;inset:8mm;border:2.4px solid #173f7a;box-shadow:inset 0 0 0 1px rgba(201,162,83,.42);pointer-events:none}';
		echo '.border-inner{position:absolute;z-index:3;inset:11mm;border:.7px solid #c9a253;pointer-events:none}';
		echo '.header{z-index:2;background:linear-gradient(90deg,#102e5a 0%,#173f7a 50%,#102e5a 100%);position:absolute;top:8mm;left:8mm;right:8mm;height:28mm;border-bottom:.9mm solid #c9a253}';
		echo '.header-title{position:absolute;top:10mm;left:0;right:0;text-align:center;color:#fff;font-family:Georgia,"Times New Roman",serif;font-size:17pt;line-height:1;font-weight:700;letter-spacing:.8px;text-transform:uppercase}';
		echo '.header-rule{position:absolute;top:17.2mm;left:50%;transform:translateX(-50%);width:38mm;height:.35px;background:rgba(201,162,83,.9)}';
		echo '.header-meta{position:absolute;top:19.2mm;left:0;right:0;text-align:center;color:rgba(255,255,255,.82);font-size:7pt;font-weight:500;letter-spacing:.7px;text-transform:uppercase}';
		echo '.body{margin-top:16mm;text-align:center;width:100%;position:relative;z-index:1}';
		echo '.cert-logo-wrap{height:' . $this->_e($logoHeightMm) . 'mm;display:flex;align-items:center;justify-content:center;margin-bottom:2mm}';
		echo '.cert-logo{display:block;max-height:' . $this->_e($logoHeightMm) . 'mm;max-width:58mm;width:auto;height:auto;object-fit:contain}';
		echo '.cert-title{font-family:Georgia,"Times New Roman",serif;font-size:32pt;line-height:1.1;font-weight:700;color:#173f7a;letter-spacing:.6px;text-shadow:0 1px 0 rgba(255,255,255,.8);margin-bottom:4mm}';
		echo '.divider{position:relative;width:108mm;height:1.2px;background:linear-gradient(90deg,rgba(201,162,83,0),#c9a253 22%,#c9a253 78%,rgba(201,162,83,0));margin:0 auto 5mm}';
		echo '.divider:after{content:"";position:absolute;left:50%;top:50%;width:3mm;height:3mm;background:#c9a253;border:1mm solid #fff;transform:translate(-50%,-50%) rotate(45deg)}';
		echo '.reviewer-name{font-family:Georgia,"Times New Roman",serif;font-size:30pt;line-height:1.15;font-style:italic;color:#111;margin-bottom:4mm}';
		echo '.body-text{font-size:9.5pt;color:#4b5563;line-height:1.6;margin-bottom:2mm}';
		echo '.journal-name{font-size:11pt;font-weight:700;color:#173f7a;margin-bottom:2mm}';
		echo '.article-label{font-size:7.8pt;letter-spacing:.3px;color:#7a8492;margin-bottom:1mm}';
		echo '.article-title{font-family:Georgia,"Times New Roman",serif;font-size:9.7pt;line-height:1.4;font-style:italic;color:#222;margin-bottom:4mm;max-width:195mm;margin-left:auto;margin-right:auto}';
		echo '.awarded{display:inline-block;font-size:9pt;font-weight:600;letter-spacing:.7px;color:#4b5563;border-top:.4px solid #d7dde5;padding-top:2mm;margin-bottom:3.5mm}';
		echo '.sign-row{position:relative;width:100%;height:38mm}';
		echo '.sign-block{position:absolute;left:50%;bottom:0;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;width:70mm}';
		echo '.sig-wrap{height:20mm;display:flex;align-items:flex-end;justify-content:center;position:relative;width:60mm}';
		echo '.sig-img{position:relative;z-index:2;bottom:1mm;max-height:24mm;max-width:' . $this->_e($signatureWidthMm) . 'mm;object-fit:contain;transform:translateX(2mm)}';
		echo '.stamp-img{position:absolute;z-index:1;left:8mm;bottom:0;height:' . $this->_e($stampSizeMm) . 'mm;width:' . $this->_e($stampSizeMm) . 'mm;object-fit:contain;opacity:.88}';
		echo '.sign-line{width:55mm;border-top:1px solid #173f7a;margin-top:1mm;margin-bottom:2mm}';
		echo '.sign-name{font-size:9.2pt;font-weight:700;color:#111;text-align:center}';
		echo '.sign-title{font-size:8.5pt;color:#555;text-align:center}';
		echo '.qr-block{position:absolute;left:calc(50% - 72mm);bottom:0;display:flex;flex-direction:column;align-items:center;gap:1mm}';
		echo '.qr-code{width:80px;height:80px;padding:1.2mm;background:#fff;border:.5px solid #d7dde5}';
		echo '.qr-code svg{display:block;width:100%;height:100%}';
		echo '.qr-label{font-size:6pt;letter-spacing:.3px;color:#8b96a6}';
		echo '.footer{position:absolute;bottom:10mm;left:20mm;right:20mm;text-align:center;font-size:7pt;color:#8b96a6}';
		echo '.print-btn{position:fixed;z-index:10;bottom:20px;right:20px;background:#173f7a;color:#fff;border:none;padding:12px 22px;border-radius:7px;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 3px 12px rgba(0,0,0,.22)}';
		echo '@media screen{.cert{margin:20px auto;box-shadow:0 10px 35px rgba(0,0,0,.14);transform-origin:top center}}@media print{.print-btn{display:none!important}@page{size:A4 landscape;margin:0}html,body{width:297mm;height:210mm;background:#fff}body{margin:0}.cert{margin:0;box-shadow:none;page-break-after:avoid}}';
		echo '</style><script src="' . $this->_e($qrScriptUrl) . '"></script></head><body>';
		echo '<button class="print-btn" onclick="window.print()">&#128424; Print / Save as PDF</button>';
		echo '<div class="cert">';
		echo '<div class="border-outer"></div><div class="border-inner"></div>';
		echo '<div class="header"><div class="header-title">' . htmlspecialchars($journalName) . '</div>';
		if (!empty($headerMeta)) echo '<div class="header-rule"></div><div class="header-meta">' . htmlspecialchars(implode(' · ', $headerMeta)) . '</div>';
		echo '</div>';
		echo '<div class="body">';
		if ($logoAsset['exists']) echo '<div class="cert-logo-wrap"><img class="cert-logo" src="' . $this->_e($logoAsset['url']) . '" alt="Journal logo"></div>';
		echo '<div class="cert-title">Certificate of Reviewing</div>';
		echo '<div class="divider"></div>';
		echo '<div class="reviewer-name">' . htmlspecialchars($reviewerName) . '</div>';
		echo '<div class="body-text">is hereby recognized for the valuable contribution as a peer reviewer for</div>';
		echo '<div class="journal-name">' . htmlspecialchars($journalName) . '</div>';
		echo '<div class="article-label">For the manuscript:</div>';
		echo '<div class="article-title">' . htmlspecialchars($articleTitle) . '</div>';
		echo '<div class="awarded">Awarded on ' . htmlspecialchars($dateFormatted) . '</div>';

		// Sign row: tanda tangan + QR
		echo '<div class="sign-row">';

		// Blok tanda tangan
		echo '<div class="sign-block">';
		echo '<div class="sig-wrap">';
		if ($signatureAsset['exists']) echo '<img class="sig-img" src="' . $this->_e($signatureAsset['url']) . '" alt="signature">';
		if ($stampAsset['exists']) echo '<img class="stamp-img" src="' . $this->_e($stampAsset['url']) . '" alt="stamp">';
		echo '</div>';
		echo '<div class="sign-line"></div>';
		echo '<div class="sign-name">' . htmlspecialchars($editorName ? $editorName : '___________________') . '</div>';
		echo '<div class="sign-title">' . htmlspecialchars($editorTitle) . '</div>';
		echo '</div>';

		// QR code
		echo '<div class="qr-block">';
		echo '<div id="rc-verification-qr" class="qr-code" data-value="' . $this->_e($verifyUrl) . '"></div>';
		echo '<div class="qr-label">Scan to verify</div>';
		echo '</div>';

		echo '</div>'; // sign-row
		echo '</div>'; // body
		echo '<div class="footer">' . htmlspecialchars($journalUrl) . '</div>';
		echo '</div><script>(function(){var target=document.getElementById("rc-verification-qr");if(target&&window.ReviewerCertificateQr){window.ReviewerCertificateQr.render(target,target.getAttribute("data-value"),{quietZone:4,label:"Certificate verification QR code"});}else if(target){target.textContent="QR unavailable";}})();</script></body></html>';
	}

	function verify($args, $request) {
		$this->_sendPrivacyHeaders(true);
		$reviewId = isset($args[0]) ? (int)$args[0] : 0;
		$token = isset($args[1]) ? (string) $args[1] : '';
		$journal  = $request->getJournal();
		$baseUrl  = $request->getBaseUrl();
		if (!$journal) { http_response_code(404); echo 'Journal not found.'; return; }
		$faviconHtml = $this->_getFaviconHtml($journal, $baseUrl);
		$plugin = $this->_getPlugin();

		if (!$reviewId || !$this->_isValidVerificationToken(
			$plugin,
			$journal->getId(),
			$reviewId,
			$token
		)) {
			http_response_code(404);
			echo '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $faviconHtml;
			echo '<meta name="robots" content="noindex,nofollow,noarchive"><title>Invalid Certificate</title></head>';
			echo '<body style="font-family:Arial,sans-serif;text-align:center;padding:40px;">';
			echo '<h2 style="color:#cc0000;">&#10007; Certificate Not Found</h2>';
			echo '<p>This verification link is invalid or incomplete.</p></body></html>';
			return;
		}

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$ra = $reviewAssignmentDao->getById($reviewId);

		if (!$ra || !$ra->getDateCompleted() ||
			!$this->_reviewBelongsToJournal($reviewId, $journal->getId())) {
			http_response_code(404);
			echo '<!DOCTYPE html><html><head><meta charset="UTF-8">' . $faviconHtml . '<meta name="robots" content="noindex,nofollow,noarchive"><title>Invalid Certificate</title></head>';
			echo '<body style="font-family:Arial,sans-serif;text-align:center;padding:40px;">';
			echo '<h2 style="color:#cc0000;">&#10007; Certificate Not Found</h2>';
			echo '<p>This certificate could not be verified.</p></body></html>';
			return;
		}

		// Ambil data reviewer
		$userDao = DAORegistry::getDAO('UserDAO');
		$reviewer = $userDao->getById($ra->getReviewerId());

		// Ambil judul artikel
		$articleTitle = '(No title)';
		try {
			$pdo = $this->_getPdo();
			$stmt = $pdo->prepare('SELECT ps.setting_value FROM publications p JOIN publication_settings ps ON ps.publication_id = p.publication_id WHERE p.submission_id = ? AND ps.setting_name = ? LIMIT 1');
			$stmt->execute(array($ra->getSubmissionId(), 'title'));
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			if ($row) $articleTitle = $row['setting_value'];
		} catch (Exception $e) {}

		$journalName   = $journal->getLocalizedName();
		$reviewerName  = $reviewer ? $reviewer->getFullName() : 'Unknown';
		$dateCompleted = strtoupper(date('F Y', strtotime($ra->getDateCompleted())));

		echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		echo '<meta name="robots" content="noindex,nofollow,noarchive">';
		echo $faviconHtml;
		echo '<title>Certificate Verification</title>';
		echo '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px}';
		echo '.box{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.1)}';
		echo '.valid{color:#00aa44;font-size:1.3rem;font-weight:700;margin-bottom:16px}';
		echo '.row{margin-bottom:12px}.label{font-size:.85rem;color:#888}.value{font-size:1rem;color:#111;font-weight:600}';
		echo '</style></head><body><div class="box">';
		echo '<div class="valid">&#10003; Certificate Verified</div>';
		echo '<div class="row"><div class="label">Reviewer</div><div class="value">' . htmlspecialchars($reviewerName) . '</div></div>';
		echo '<div class="row"><div class="label">Journal</div><div class="value">' . htmlspecialchars($journalName) . '</div></div>';
		echo '<div class="row"><div class="label">Manuscript</div><div class="value">' . htmlspecialchars($articleTitle) . '</div></div>';
		echo '<div class="row"><div class="label">Review Completed</div><div class="value">' . htmlspecialchars($dateCompleted) . '</div></div>';
		echo '</div></body></html>';
	}

	function _getAuthorNamesBySubmission($pdo, $submissionIds) {
		$namesBySubmission = array();
		if (empty($submissionIds)) return $namesBySubmission;

		try {
			$submissionIds = array_values(array_unique(array_map('intval', $submissionIds)));
			$placeholders = implode(',', array_fill(0, count($submissionIds), '?'));
			$sql = 'SELECT s.submission_id, a.author_id, a.seq, aus.setting_name, aus.setting_value
				FROM submissions s
				JOIN authors a ON a.publication_id = s.current_publication_id
				JOIN author_settings aus ON aus.author_id = a.author_id
				WHERE s.submission_id IN (' . $placeholders . ')
				AND aus.setting_name IN (?, ?, ?)
				ORDER BY s.submission_id, a.seq,
				CASE WHEN aus.locale = ? THEN 0 ELSE 1 END';
			$params = array_merge($submissionIds, array(
				'preferredPublicName', 'givenName', 'familyName', 'en_US'
			));
			$stmt = $pdo->prepare($sql);
			$stmt->execute($params);
			$authors = array();
			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$submissionId = (int) $row['submission_id'];
				$authorId = (int) $row['author_id'];
				$settingName = $row['setting_name'];
				if (!isset($authors[$submissionId])) $authors[$submissionId] = array();
				if (!isset($authors[$submissionId][$authorId])) {
					$authors[$submissionId][$authorId] = array();
				}
				if (!isset($authors[$submissionId][$authorId][$settingName]) ||
					!$authors[$submissionId][$authorId][$settingName]) {
					$authors[$submissionId][$authorId][$settingName] = trim((string) $row['setting_value']);
				}
			}

			foreach ($authors as $submissionId => $submissionAuthors) {
				$authorNames = array();
				foreach ($submissionAuthors as $author) {
					$name = isset($author['preferredPublicName']) ? $author['preferredPublicName'] : '';
					if (!$name) {
						$name = trim(
							(isset($author['givenName']) ? $author['givenName'] : '') . ' ' .
							(isset($author['familyName']) ? $author['familyName'] : '')
						);
					}
					if ($name) $authorNames[] = $name;
				}
				$namesBySubmission[$submissionId] = implode(', ', $authorNames);
			}
		} catch (Exception $e) {
			error_log('RC author lookup error: ' . $e->getMessage());
		}
		return $namesBySubmission;
	}

	function _getReviews($userId, $journalId) {
		$result = array();
		try {
			$pdo = $this->_getPdo();
			$stmt = $pdo->prepare(
				'SELECT ra.review_id, ra.submission_id, ra.date_completed,
				COALESCE((
					SELECT ps.setting_value
					FROM publications p
					JOIN publication_settings ps ON ps.publication_id = p.publication_id
					WHERE p.submission_id = ra.submission_id
					AND ps.setting_name = \'title\'
					ORDER BY CASE WHEN ps.locale = \'en_US\' THEN 0 ELSE 1 END
					LIMIT 1
				), \'(No title)\') AS title
				FROM review_assignments ra
				JOIN submissions s ON s.submission_id = ra.submission_id AND s.context_id = ?
				WHERE ra.reviewer_id = ?
				AND ra.date_completed IS NOT NULL AND ra.declined = 0
				ORDER BY ra.date_completed DESC'
			);
			$stmt->execute(array((int)$journalId, (int)$userId));
			$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			$submissionIds = array();
			foreach ($rows as $row) $submissionIds[] = (int) $row['submission_id'];
			$authorNames = $this->_getAuthorNamesBySubmission($pdo, $submissionIds);
			foreach ($rows as $row) {
				$submissionId = (int) $row['submission_id'];
				$result[] = array(
					'reviewId' => (int)$row['review_id'],
					'submissionId' => $submissionId,
					'title'    => $row['title'] ? $row['title'] : '(No title)',
					'authors'  => isset($authorNames[$submissionId]) ? $authorNames[$submissionId] : '',
					'date'     => date('d M Y', strtotime($row['date_completed'])),
				);
			}
		} catch (Exception $e) {
			error_log('RC_getReviews error: ' . $e->getMessage());
		}
		return $result;
	}
}
