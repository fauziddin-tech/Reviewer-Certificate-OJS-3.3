<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Standalone check of the author-country hook; no OJS installation needed.
function import($class) {}
class ThemePlugin { public function getOption($name) { return null; } }
class HookRegistry { public static function register() {} }
class AppLocale { public static function getLocale() { return 'en_US'; } }
define('STYLE_SEQUENCE_LAST', 9);
require __DIR__ . '/../LiteScholarThemePlugin.inc.php';

class FakeAuthor {
	public $data;
	public function __construct($data) { $this->data = $data; }
	public function getData($k) { return $this->data[$k] ?? null; }
	public function setData($k, $v, $locale = null) { $this->data[$k] = $v; }
	public function getLocalizedData($k) { return $this->data[$k] ?? ''; }
}
class FakePublication {
	public $authors;
	public function __construct($a) { $this->authors = $a; }
	public function getData($k) { return $this->authors; }
}
class FakeTemplateMgr {
	public $pub;
	public function __construct($p) { $this->pub = $p; }
	public function getTemplateVars($k) { return $this->pub; }
}
class TestTheme extends LiteScholarThemePlugin {
	public $enabled = true;
	protected function isCountryEnabled() { return $this->enabled; }
	protected function getCountryName($code) {
		$names = array('ID' => 'Indonesia', 'MY' => 'Malaysia');
		return $names[strtoupper(trim((string) $code))] ?? '';
	}
}

$fail = 0;
function check($label, $actual, $expected) {
	global $fail;
	if ($actual !== $expected) {
		$fail++;
		echo "FAIL $label: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
	}
}

check('join', LiteScholarThemePlugin::joinAffiliationAndCountry('Univ A', 'Indonesia'), "Univ A\nIndonesia");
check('join empty affiliation', LiteScholarThemePlugin::joinAffiliationAndCountry('', 'Indonesia'), 'Indonesia');
check('join empty country', LiteScholarThemePlugin::joinAffiliationAndCountry('Univ A', ''), 'Univ A');
check('join duplicate', LiteScholarThemePlugin::joinAffiliationAndCountry('Univ A, indonesia', 'Indonesia'), 'Univ A, indonesia');

$authors = array(
	new FakeAuthor(array('affiliation' => 'Univ A', 'country' => 'ID')),
	new FakeAuthor(array('affiliation' => '', 'country' => 'MY')),
	new FakeAuthor(array('affiliation' => 'Univ C', 'country' => '')),
	new FakeAuthor(array('affiliation' => 'Univ D', 'country' => 'XX')),
);
$theme = new TestTheme();
$mgr = new FakeTemplateMgr(new FakePublication($authors));
$theme->addAuthorCountries('TemplateManager::display', array($mgr, 'frontend/pages/article.tpl'));
check('author 1', $authors[0]->data['affiliation'], "Univ A\nIndonesia");
check('author 2', $authors[1]->data['affiliation'], 'Malaysia');
check('author 3 no country', $authors[2]->data['affiliation'], 'Univ C');
check('author 4 unknown code', $authors[3]->data['affiliation'], 'Univ D');

$authors = array(new FakeAuthor(array('affiliation' => 'Univ A', 'country' => 'ID')));
$theme->addAuthorCountries('TemplateManager::display', array(new FakeTemplateMgr(new FakePublication($authors)), 'frontend/pages/issue.tpl'));
check('other template untouched', $authors[0]->data['affiliation'], 'Univ A');

$theme->enabled = false;
$theme->addAuthorCountries('TemplateManager::display', array(new FakeTemplateMgr(new FakePublication($authors)), 'frontend/pages/article.tpl'));
check('disabled option', $authors[0]->data['affiliation'], 'Univ A');

echo $fail === 0 ? "OK\n" : "$fail failure(s)\n";
exit($fail === 0 ? 0 : 1);
