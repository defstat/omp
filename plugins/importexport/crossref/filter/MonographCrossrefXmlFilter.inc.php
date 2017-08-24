<?php

/**
 * @file plugins/importexport/crossref/filter/MonographCrossrefXmlFilter.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MonographCrossrefXmlFilter
 * @ingroup plugins_importexport_crossref
 *
 * @brief Class that converts a Monograph to a Crossref XML document.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeExportFilter');

class MonographCrossrefXmlFilter extends NativeExportFilter {
	/**
	 * Constructor
	 * @param $filterGroup FilterGroup
	 */
	function __construct($filterGroup) {
		$this->setDisplayName('Crossref XML Monograph export');
		parent::__construct($filterGroup);
	}

	//
	// Implement template methods from PersistableFilter
	//
	/**
	 * @copydoc PersistableFilter::getClassName()
	 */
	function getClassName() {
		return 'plugins.importexport.crossref.filter.MonographCrossrefXmlFilter';
	}

	//
	// Implement template methods from Filter
	//
	/**
	 * @see Filter::process()
	 * @param $pubObjects array Array of PublishedMonographs
	 * @return DOMDocument
	 */
	function &process(&$pubObjects) {
		// Create the XML document
		$doc = new DOMDocument('1.0', 'utf-8');
		$doc->preserveWhiteSpace = false;
		$doc->formatOutput = true;
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();

		// Create the root node
		$rootNode = $this->createRootNode($doc);
		$doc->appendChild($rootNode);

		// Create and appet the 'head' node and all parts inside it
		$rootNode->appendChild($this->createHeadNode($doc));

		// Create and append the 'body' node, that contains everything
		$bodyNode = $doc->createElementNS($deployment->getNamespace(), 'body');
		$rootNode->appendChild($bodyNode);

		foreach($pubObjects as $pubObject) {
			// pubObject is PublishedMonograph
			$monographNode = $this->createMonographNode($doc, $pubObject);
			$bodyNode->appendChild($monographNode);
		}
		return $doc;
	}

	//
	// Issue conversion functions
	//
	/**
	 * Create and return the root node 'doi_batch'.
	 * @param $doc DOMDocument
	 * @return DOMElement
	 */
	function createRootNode($doc) {
		$deployment = $this->getDeployment();
		$rootNode = $doc->createElementNS($deployment->getNamespace(), $deployment->getRootElementName());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', $deployment->getXmlSchemaInstance());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:jats', $deployment->getJATSNamespace());
		$rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ai', $deployment->getAINamespace());
		$rootNode->setAttribute('version', $deployment->getXmlSchemaVersion());
		$rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());
		return $rootNode;
	}

	/**
	 * Create and return the head node 'head'.
	 * @param $doc DOMDocument
	 * @return DOMElement
	 */
	function createHeadNode($doc) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$plugin = $deployment->getPlugin();
		$headNode = $doc->createElementNS($deployment->getNamespace(), 'head');
		$headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi_batch_id', htmlspecialchars($context->getSetting('initials', $context->getPrimaryLocale()) . '_' . time(), ENT_COMPAT, 'UTF-8')));
		$headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'timestamp', time()));
		$depositorNode = $doc->createElementNS($deployment->getNamespace(), 'depositor');
		$depositorName = $plugin->getSetting($context->getId(), 'depositorName');
		if (empty($depositorName)) {
			$depositorName = $context->getSetting('supportName');
		}
		$depositorEmail = $plugin->getSetting($context->getId(), 'depositorEmail');
		if (empty($depositorEmail)) {
			$depositorEmail = $context->getSetting('supportEmail');
		}
		$depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'depositor_name', htmlspecialchars($depositorName, ENT_COMPAT, 'UTF-8')));
		$depositorNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'email_address', htmlspecialchars($depositorEmail, ENT_COMPAT, 'UTF-8')));
		$headNode->appendChild($depositorNode);
		$publisherInstitution = $context->getSetting('publisherInstitution');
		if ($publisherInstitution == "") {
			$publisherInstitution = "PKP";
		}
		$headNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'registrant', htmlspecialchars($publisherInstitution, ENT_COMPAT, 'UTF-8')));
		return $headNode;
	}

	/**
	 * Create and return the monograph node
	 * @param $doc DOMDocument
	 * @param $pubObject object Issue or PublishedMonograph
	 * @return DOMElement
	 */
	function createMonographNode($doc, $pubObject) {
		$deployment = $this->getDeployment();
		$journalNode = $doc->createElementNS($deployment->getNamespace(), 'book');
		$journalNode->setAttribute('book_type', 'monograph');
		$journalNode->appendChild($this->createMonographMetadataNode($doc, $pubObject));
		return $journalNode;
	}

	/**
	 * Create and return the monograph metadata node 'book-metadata'.
	 * @param $doc DOMDocument
	 * @return DOMElement
	 */
	function createMonographMetadataNode($doc, $pubObject) {
		$deployment = $this->getDeployment();
		$context = $deployment->getContext();
		$request = Application::getRequest();

		$journalMetadataNode = $doc->createElementNS($deployment->getNamespace(), 'book_metadata');

		// Contributors
		$contributorsNode = $this->createContributorsNode($doc, $pubObject);
		$journalMetadataNode->appendChild($contributorsNode);

		// Full title
		$journalMetadataTitlesNode = $doc->createElementNS($deployment->getNamespace(), 'titles');
		$journalTitle = $pubObject->getTitle($context->getPrimaryLocale());

		$journalMetadataNode->appendChild($journalMetadataTitlesNode);
		$journalMetadataTitlesNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'title', htmlspecialchars($journalTitle, ENT_COMPAT, 'UTF-8')));

		// abstract
		if ($abstract = $pubObject->getAbstract($context->getPrimaryLocale())) {
			$abstractNode = $doc->createElementNS($deployment->getJATSNamespace(), 'jats:abstract');
			$abstractNode->appendChild($node = $doc->createElementNS($deployment->getJATSNamespace(), 'jats:p', htmlspecialchars(html_entity_decode(strip_tags($abstract), ENT_COMPAT, 'UTF-8'), ENT_COMPAT, 'UTF-8')));
			$journalMetadataNode->appendChild($abstractNode);
		}

		// publication date
		$datePublished = $pubObject->getDatePublished();
		if ($datePublished) {
			$journalMetadataNode->appendChild($this->createPublicationDateNode($doc, $datePublished));
		}

		// Get all ISBN-13
		$publicationFormats = $pubObject->getPublicationFormats(true);
		if ($publicationFormats) {
			$identificationCodes = $publicationFormats[0]->getIdentificationCodes();
			$node = $doc->createElementNS($deployment->getNamespace(), 'noisbn', '');
			$node->setAttribute('reason', 'monograph');
			$journalMetadataNode->appendChild($node);
			//if ($identificationCodes) {
			//    while ($code = $identificationCodes->next()) {
			//        if ($code->getCode() == 24) { //ISBN-13
			//            $journalMetadataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'isbn', $code->getValue()));
			//            $node->setAttribute('media_type', 'electronic');
			//        }

			//        unset($productIdentifierNode);
			//        unset($code);
			//    }
			//}
			//if ($identificationCodes) {
			//    while ($code = $identificationCodes->next()) {
			//        if ($code->getCode() == 24) { //ISBN-13
			//            $journalMetadataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'isbn', $code->getValue()));
			//            $node->setAttribute('media_type', 'electronic');
			//        }

			//        unset($productIdentifierNode);
			//        unset($code);
			//    }
			//}
		}

		// publisher
		$journalMetadataPublisherNode = $doc->createElementNS($deployment->getNamespace(), 'publisher');
		$monographPublisherName = $context->getName($context->getPrimaryLocale());

		$journalMetadataNode->appendChild($journalMetadataPublisherNode);
		$journalMetadataPublisherNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'publisher_name', htmlspecialchars($monographPublisherName, ENT_COMPAT, 'UTF-8')));

		// DOI data
		$doiDataNode = $this->createDOIDataNode($doc, $pubObject->getStoredPubId('doi'), $request->url($context->getPath(), 'catalog', 'book', $pubObject->getBestId(), null, null, true));
		$journalMetadataNode->appendChild($doiDataNode);

		return $journalMetadataNode;
	}

	/**
	 * Create and return the publication date node 'publication_date'.
	 * @param $doc DOMDocument
	 * @param $objectPublicationDate string
	 * @return DOMElement
	 */
	function createPublicationDateNode($doc, $objectPublicationDate) {
		$deployment = $this->getDeployment();
		$publicationDate = strtotime($objectPublicationDate);
		$publicationDateNode = $doc->createElementNS($deployment->getNamespace(), 'publication_date');
		$publicationDateNode->setAttribute('media_type', 'online');
		if (date('m', $publicationDate)) {
			$publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'month', date('m', $publicationDate)));
		}
		if (date('d', $publicationDate)) {
			$publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'day', date('d', $publicationDate)));
		}
		$publicationDateNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'year', date('Y', $publicationDate)));
		return $publicationDateNode;
	}

	/**
	 * Create and return the Contributors node 'contributors'.
	 * @param $doc DOMDocument
	 * @param $objectPublicationDate string
	 * @return DOMElement
	 */
	function createContributorsNode($doc, $pubObject) {
		$deployment = $this->getDeployment();

		// contributors
		$contributorsNode = $doc->createElementNS($deployment->getNamespace(), 'contributors');
		$authors = $pubObject->getAuthors();
		$isFirst = true;
		foreach ($authors as $author) {
			$personNameNode = $doc->createElementNS($deployment->getNamespace(), 'person_name');
			$personNameNode->setAttribute('contributor_role', 'author');
			if ($isFirst) {
				$personNameNode->setAttribute('sequence', 'first');
			} else {
				$personNameNode->setAttribute('sequence', 'additional');
			}
			$personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'given_name', htmlspecialchars(ucfirst($author->getFirstName()).(($author->getMiddleName())?' '.ucfirst($author->getMiddleName()):''), ENT_COMPAT, 'UTF-8')));
			$personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'surname', htmlspecialchars(ucfirst($author->getLastName()), ENT_COMPAT, 'UTF-8')));
			if ($author->getData('orcid')) {
				$personNameNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'ORCID', $author->getData('orcid')));
			}
			$contributorsNode->appendChild($personNameNode);
		}

		return $contributorsNode;
	}


	/**
	 * Create and return the DOI date node 'doi_data'.
	 * @param $doc DOMDocument
	 * @param $doi string
	 * @param $url string
	 * @return DOMElement
	 */
	function createDOIDataNode($doc, $doi, $url) {
		$deployment = $this->getDeployment();
		$doiDataNode = $doc->createElementNS($deployment->getNamespace(), 'doi_data');
		$doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'doi', htmlspecialchars($doi, ENT_COMPAT, 'UTF-8')));
		$doiDataNode->appendChild($node = $doc->createElementNS($deployment->getNamespace(), 'resource', $url));
		return $doiDataNode;
	}

}

?>
