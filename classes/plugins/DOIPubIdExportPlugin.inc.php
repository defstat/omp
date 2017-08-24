<?php

/**
 * @file classes/plugins/DOIPubIdExportPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DOIPubIdExportPlugin
 * @ingroup plugins
 *
 * @brief Basis class for DOI XML metadata export plugins
 */

import('classes.plugins.PubObjectsExportPlugin');

// Configuration errors.
define('DOI_EXPORT_CONFIG_ERROR_DOIPREFIX', 0x01);

// The name of the setting used to save the registered DOI.
define('DOI_EXPORT_REGISTERED_DOI', 'registeredDoi');

abstract class DOIPubIdExportPlugin extends PubObjectsExportPlugin {


	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		parent::display($args, $request);
		$context = $request->getContext();
		switch (array_shift($args)) {
			case 'index':
			case '':
				parent::display($args, $request);
				$templateMgr = TemplateManager::getManager($request);
				// Check for configuration errors:
				$configurationErrors = $templateMgr->get_template_vars('configurationErrors');
				// missing DOI prefix
				$doiPrefix = $exportArticles = $exportRepresentations = null;
				$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
				if (isset($pubIdPlugins['doipubidplugin'])) {
					$doiPlugin = $pubIdPlugins['doipubidplugin'];
					$doiPrefix = $doiPlugin->getSetting($context->getId(), $doiPlugin->getPrefixFieldName());
					$exportArticles = $doiPlugin->getSetting($context->getId(), 'enableSubmissionDoi');
					// $exportIssues = $doiPlugin->getSetting($context->getId(), 'enableSubmissionFileDoi');
					$exportRepresentations = $doiPlugin->getSetting($context->getId(), 'enableRepresentationDoi');
				}
				if (empty($doiPrefix)) {
					$configurationErrors[] = DOI_EXPORT_CONFIG_ERROR_DOIPREFIX;
				}
				$templateMgr->assign(array(
					'exportArticles' => $exportArticles,
					'exportRepresentations' => $exportRepresentations,
				));
				$templateMgr->display($this->getTemplatePath() . 'index.tpl');
				break;
		}
	}

	/**
	 * Get pub ID type
	 * @return string
	 */
	function getPubIdType() {
		return 'doi';
	}

	/**
	 * Get pub ID display type
	 * @return string
	 */
	function getPubIdDisplayType() {
		return 'DOI';
	}

	/**
	 * Mark selected submissions or issues as registered.
	 * @param $context Context
	 * @param $objects array Array of published articles, issues or galleys
	 */
	function markRegistered($context, $objects) {
		foreach ($objects as $object) {
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
			$this->saveRegisteredDoi($context, $object);
		}
	}

	/**
	 * Saving object's DOI to the object's
	 * "registeredDoi" setting.
	 * We prefix the setting with the plugin's
	 * id so that we do not get name clashes
	 * when several DOI registration plug-ins
	 * are active at the same time.
	 * @param $context Context
	 * @param $object Issue|PublishedArticle|ArticleGalley
	 * @param $testPrefix string
	 */
	function saveRegisteredDoi($context, $object, $testPrefix = '10.1234') {
		$registeredDoi = $object->getStoredPubId('doi');
		assert(!empty($registeredDoi));
		if ($this->isTestMode($context)) {
			$registeredDoi = PKPString::regexp_replace('#^[^/]+/#', $testPrefix . '/', $registeredDoi);
		}
		$object->setData($this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI, $registeredDoi);
		$this->updateObject($object);
	}

	/**
	 * Hook callback that returns the
	 * "registeredDoi" setting's name prefixed with
	 * the plug-in's id to avoid name collisions.
	 * @see DAO::getAdditionalFieldNames()
	 * @param $hookName string
	 * @param $args array
	 */
	function getAdditionalFieldNames($hookName, $args) {
		parent::getAdditionalFieldNames($hookName, $args);
		$additionalFields =& $args[1];
		assert(is_array($additionalFields));
		$additionalFields[] = $this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI;
	}

	/**
	 * Retrieve all unregistered submissions.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredSubmissions($context) {
		// Retrieve all published articles that have not yet been registered.
		$publishedMonographDao = DAORegistry::getDAO('PublishedMonographDAO'); /* @var $publishedMonographDao PublishedMonographDAO */
		$articles = $publishedMonographDao->getExportable(
			$context->getId(),
			$this->getPubIdType(),
			null,
			null,
			null,
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		return $articles->toArray();
	}

	/**
	 * Get published articles with a DOI asigned from submission IDs.
	 * @param $submissionIds array
	 * @param $context Context
	 * @return array
	 */
	function getPublishedSubmissions($submissionIds, $context) {
		$publishedArticles = array();
		$publishedMonographDao = DAORegistry::getDAO('PublishedMonographDAO');
		foreach ($submissionIds as $submissionId) {
			$publishedArticle = $publishedMonographDao->getById($submissionId, $context->getId());
			if ($publishedArticle && $publishedArticle->getStoredPubId('doi')) $publishedArticles[] = $publishedArticle;
		}
		return $publishedArticles;
	}

}

?>
