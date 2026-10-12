<?php
// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * Lite Scholar: a lightweight child theme of the OJS 3.3 Default Theme.
 *
 * It adds one small stylesheet and shows the author's country below the
 * affiliation on article pages. No core or parent-theme template is copied,
 * so OJS 3.3.x updates of the Default Theme keep working.
 */
import('lib.pkp.classes.plugins.ThemePlugin');

class LiteScholarThemePlugin extends ThemePlugin {

	/** @var array|null ISO 3166-1 alpha-2 => localized country name */
	protected $countryNames = null;

	public function init() {
		$this->setParent('defaultthemeplugin');
		$this->addStyle('liteScholar', 'styles/liteScholar.css', array('priority' => STYLE_SEQUENCE_LAST));

		$this->addOption('showAuthorCountry', 'FieldOptions', array(
			'label' => __('plugins.themes.liteScholar.option.showAuthorCountry.label'),
			'description' => __('plugins.themes.liteScholar.option.showAuthorCountry.description'),
			'options' => array(
				array(
					'value' => true,
					'label' => __('plugins.themes.liteScholar.option.showAuthorCountry.option'),
				),
			),
			'default' => true,
		));

		HookRegistry::register('TemplateManager::display', array($this, 'addAuthorCountries'));
	}

	public function getDisplayName() {
		return __('plugins.themes.liteScholar.name');
	}

	public function getDescription() {
		return __('plugins.themes.liteScholar.description');
	}

	/**
	 * Append the country to each author's affiliation on the article page.
	 *
	 * The Default Theme already prints the affiliation under the author name;
	 * adding the country to that in-memory value avoids copying its template.
	 * Nothing is saved to the database.
	 */
	public function addAuthorCountries($hookName, $args) {
		$templateMgr = $args[0];
		$template = $args[1];
		if ($template !== 'frontend/pages/article.tpl' || !$this->isCountryEnabled()) {
			return false;
		}

		$publication = $templateMgr->getTemplateVars('publication');
		if (!$publication) {
			return false;
		}
		$authors = $publication->getData('authors');
		if (!is_array($authors) && !($authors instanceof Traversable)) {
			return false;
		}

		$locale = AppLocale::getLocale();
		foreach ($authors as $author) {
			$country = $this->getCountryName($author->getData('country'));
			if ($country === '') {
				continue;
			}
			$affiliation = (string) $author->getLocalizedData('affiliation');
			$author->setData('affiliation', self::joinAffiliationAndCountry($affiliation, $country), $locale);
		}
		return false;
	}

	/**
	 * Combine affiliation and country on two lines (CSS renders the line
	 * break). Returns the affiliation unchanged if it already ends with the
	 * country name.
	 */
	public static function joinAffiliationAndCountry($affiliation, $country) {
		$affiliation = trim((string) $affiliation);
		$country = trim((string) $country);
		if ($country === '') {
			return $affiliation;
		}
		if ($affiliation === '') {
			return $country;
		}
		$tail = mb_strtolower(mb_substr($affiliation, -mb_strlen($country)));
		if ($tail === mb_strtolower($country)) {
			return $affiliation;
		}
		return $affiliation . "\n" . $country;
	}

	protected function isCountryEnabled() {
		$value = $this->getOption('showAuthorCountry');
		return $value === null || !empty($value);
	}

	/**
	 * Localized country name for an ISO alpha-2 code, or '' when unknown.
	 */
	protected function getCountryName($code) {
		$code = strtoupper(trim((string) $code));
		if (!preg_match('/^[A-Z]{2}$/', $code)) {
			return '';
		}
		if ($this->countryNames === null) {
			$this->countryNames = array();
			try {
				$factory = new \Sokil\IsoCodes\IsoCodesFactory();
				foreach ($factory->getCountries() as $country) {
					$this->countryNames[strtoupper($country->getAlpha2())] = $country->getLocalName();
				}
			} catch (\Throwable $e) {
				error_log('liteScholar: unable to load country names: ' . $e->getMessage());
			}
		}
		return isset($this->countryNames[$code]) ? $this->countryNames[$code] : '';
	}
}
