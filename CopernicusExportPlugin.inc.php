<?php

/**
 * @file plugins/importexport/copernicus/CopernicusExportPlugin.inc.php
 *
 * Copyright (c) 2018 Oleksii Vodka
 * Maintenance 2026 By origrata@ioscloud.co.id , RRZ SCIENTIFIC PUBLISHING
 * Telegram @origrata
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CopernicusExportPlugin
 * @ingroup plugins_importexport_copernicus
 *
 * @brief Copernicus import/export plugin
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

use PKP\facades\Locale;
use APP\facades\Repo;

class CopernicusExportPlugin extends ImportExportPlugin
{
    function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    function getName()
    {
        return 'CopernicusExportPlugin';
    }

    function getDisplayName()
    {
        return __('plugins.importexport.copernicus.displayName');
    }

    function displayName()
    {
        return 'Copernicus export plugin';
    }

    function getDescription()
    {
        return __('plugins.importexport.copernicus.description');
    }

    function formatDate($date)
    {
        if ($date == '') return null;
        return date('Y-m-d', strtotime($date));
    }

    function multiexplode($delimiters, $string)
    {
        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return $launch;
    }

    function formatXml($simpleXMLElement)
    {
        $xmlDocument = new DOMDocument('1.0');
        $xmlDocument->preserveWhiteSpace = false;
        $xmlDocument->formatOutput = true;
        $xmlDocument->loadXML($simpleXMLElement->saveXML());
        return $xmlDocument->saveXML();
    }

    function generateIssueDom($doc, $journal, $issue)
    {
        $issn = $journal->getSetting('printIssn');
        $issn = $issn ? $issn : $journal->getSetting('onlineIssn');

        $root = $doc->createElement('ici-import');
        $root->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
        $root->setAttribute("xsi:noNamespaceSchemaLocation", "https://journals.indexcopernicus.com/ic-import.xsd");

        $journal_elem = self::createChildWithText($doc, $root, 'journal', '', true);
        $journal_elem->setAttribute('issn', $issn);

        $issue_elem = self::createChildWithText($doc, $root, 'issue', '', true);

        $pub_issue_date = $issue->getDatePublished() ? strtok($issue->getDatePublished(), ' ') : '';

        $issue_elem->setAttribute('number', $issue->getNumber());
        $issue_elem->setAttribute('volume', $issue->getVolume());
        $issue_elem->setAttribute('year', $issue->getYear());
        $issue_elem->setAttribute('publicationDate', $pub_issue_date);

        $num_articles = 0;

        $issueSubmissions = iterator_to_array(Repo::submission()->getCollector()
            ->filterByContextIds([$journal->getId()])
            ->filterByIssueIds([$issue->getId()])
            ->filterByStatus([STATUS_PUBLISHED])
            ->getMany());

        $sections = Repo::section()->getCollector()
            ->filterByContextIds([$journal->getId()])
            ->getMany();

        $issueSubmissionsInSection = [];
        foreach ($sections as $section) {
            $issueSubmissionsInSection[$section->getId()] = [
                'title' => $section->getLocalizedTitle(),
                'articles' => [],
            ];
        }
        foreach ($issueSubmissions as $submission) {
            if (!$sectionId = $submission->getCurrentPublication()->getData('sectionId')) {
                continue;
            }
            $issueSubmissionsInSection[$sectionId]['articles'][] = $submission;
        }

        foreach ($issueSubmissionsInSection as $sections) {
            foreach ($sections['articles'] as $_article) {
                $article = $_article->getCurrentPublication();
                $title = $article->getData('title');
                if (!$title)
                    continue;

                $locales = array_keys($title);
                $article_elem = self::createChildWithText($doc, $issue_elem, 'article', '', true);
                self::createChildWithText($doc, $article_elem, 'type', 'ORIGINAL_ARTICLE');

                foreach ($locales as $loc) {
                    $lc = explode('_', $loc);
                    $lang_version = self::createChildWithText($doc, $article_elem, 'languageVersion', '', true);
                    $lang_version->setAttribute('language', $lc[0]);
                    self::createChildWithText($doc, $lang_version, 'title', $article->getLocalizedTitle($loc), true);
                    self::createChildWithText($doc, $lang_version, 'abstract', strip_tags($article->getLocalizedData('abstract', $loc)), true);

                    foreach ($_article->getGalleys() as $galley) {
                        self::createChildWithText($doc, $lang_version, 'pdfFileUrl', $url = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '/' . $journal->getPath() . '/article/download/' . $_article->getBestArticleId() . '/' . $galley->getBestGalleyId(), true);
                    }

                    $publicationDate = $_article->getDatePublished();
                    self::createChildWithText($doc, $lang_version, 'publicationDate', $publicationDate, false);
                    self::createChildWithText($doc, $lang_version, 'pageFrom', $article->getStartingPage(), true);
                    self::createChildWithText($doc, $lang_version, 'pageTo', $article->getEndingPage(), true);
                    self::createChildWithText($doc, $lang_version, 'doi', $article->getStoredPubId('doi'), true);

                    $keywords = self::createChildWithText($doc, $lang_version, 'keywords', '', true);
                    $kwds = $article->getData('keywords');
                    $j = 0;
                    if (isset($kwds[$loc])) {
                        foreach ($kwds[$loc] as $k) {
                            self::createChildWithText($doc, $keywords, 'keyword', $k, true);
                            $j++;
                        }
                    }
                    if ($j == 0) {
                        self::createChildWithText($doc, $keywords, 'keyword', " ", true);
                    }
                }

                $authors_elem = self::createChildWithText($doc, $article_elem, 'authors', '', true);
                $index = 1;
                foreach ($article->getData('authors') as $author) {
                    $author_elem = self::createChildWithText($doc, $authors_elem, 'author', '', true);

                    $author_FirstName = '';
                    $author_MiddleName = '';
                    $author_LastName = '';

                    if (method_exists($author, "getLocalizedFirstName")) {
                        $author_FirstName = $author->getLocalizedFirstName();
                        $author_MiddleName = $author->getLocalizedMiddleName();
                        $author_LastName = $author->getLocalizedLastName();
                    } elseif (method_exists($author, "getLocalizedGivenName")) {
                        $author_FirstName = $author->getLocalizedGivenName();
                        $author_MiddleName = '';
                        $author_LastName = $author->getLocalizedFamilyName();
                    } else {
                        $author_FirstName = $author->getFirstName();
                        $author_MiddleName = $author->getMiddleName();
                        $author_LastName = $author->getLastName();
                    }

                    self::createChildWithText($doc, $author_elem, 'name', $author_FirstName, true);
                    self::createChildWithText($doc, $author_elem, 'name2', $author_MiddleName, false);
                    self::createChildWithText($doc, $author_elem, 'surname', $author_LastName, true);
                    self::createChildWithText($doc, $author_elem, 'email', $author->getEmail(), false);
                    self::createChildWithText($doc, $author_elem, 'order', $index, true);
                    self::createChildWithText($doc, $author_elem, 'instituteAffiliation', substr($author->getLocalizedAffiliation(), 0, 1000), false);
                    self::createChildWithText($doc, $author_elem, 'country', $author->getCountry(), true);
                    self::createChildWithText($doc, $author_elem, 'role', 'AUTHOR', true);
                    self::createChildWithText($doc, $author_elem, 'ORCID', $author->getData('orcid'), false);

                    $index++;
                }

                if (method_exists($_article, "getLocalizedCitations"))
                    $citation_text = $_article->getLocalizedCitations();
                else
                    $citation_text = $_article->getCitations();

                if ($citation_text) {
                    $citation_arr = explode("\n", $citation_text);
                    $references_elem = self::createChildWithText($doc, $article_elem, 'references', '', true);
                    $index = 1;
                    foreach ($citation_arr as $citation) {
                        if ($citation == "") continue;
                        $reference_elem = self::createChildWithText($doc, $references_elem, 'reference', '', true);
                        self::createChildWithText($doc, $reference_elem, 'unparsedContent', $citation, true);
                        self::createChildWithText($doc, $reference_elem, 'order', $index, true);
                        self::createChildWithText($doc, $reference_elem, 'doi', '', true);
                        $index++;
                    }
                }
                $num_articles++;
            }
        }
        $issue_elem->setAttribute('numberOfArticles', $num_articles);
        return $root;
    }

    function exportIssue($journal, $issue, $outputFile = null)
    {
        $impl = new DOMImplementation();
        $doc = $impl->createDocument('1.0', '');
        $doc->encoding = 'UTF-8';

        $issueNode = $this->generateIssueDom($doc, $journal, $issue);
        $doc->appendChild($issueNode);

        if (!empty($outputFile)) {
            if (($h = fopen($outputFile, 'wb')) === false) return false;
            fwrite($h, $doc->saveXML());
            fclose($h);
        } else {
            header("Content-Type: application/xml");
            header("Cache-Control: private");
            header("Content-Disposition: attachment; filename=\"copernicus-issue-" . $journal->getLocalizedAcronym() . '-' . $issue->getYear() . '-' . $issue->getNumber() . ".xml\"");
            echo $this->formatXml($doc);
        }
        return true;
    }

    function display($args, $request)
    {
        parent::display($args, $request);
        $journal = $request->getJournal();

        switch (array_shift($args)) {
            case 'exportIssue':
                $issueId = array_shift($args);
                $issue = Repo::issue()->get($issueId);
                if (!$issue || $issue->getData('journalId') !== $journal->getId()) $request->redirect();
                $this->exportIssue($journal, $issue);
                break;

            case 'validateIssue':
                $issueId = array_shift($args);
                $issue = Repo::issue()->get($issueId);
                if (!$issue || $issue->getData('journalId') !== $journal->getId()) $request->redirect();

                $impl = new DOMImplementation();
                $doc = $impl->createDocument('1.0', '');
                $doc->encoding = 'UTF-8';

                $issueNode = $this->generateIssueDom($doc, $journal, $issue);
                $doc->appendChild($issueNode);

                $xmlDocument = new DOMDocument('1.0', 'UTF-8');
                $xmlDocument->preserveWhiteSpace = false;
                $xmlDocument->formatOutput = true;

                $xml = utf8_encode($doc->saveXML());
                $xmlDocument->loadXML($xml);
                $xmlDocument->loadXML($xmlDocument->saveXML());

                libxml_use_internal_errors(true);
                $xmlDocument->schemaValidate($this->getPluginPath() . '/ic-import.xsd');
                $xml_lines = explode("\n", htmlentities($xmlDocument->saveXML()));
                $xml_errors = libxml_get_errors();
                libxml_clear_errors();

                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assignByRef('xml_lines', $xml_lines);
                $templateMgr->assignByRef('xml_errors', $xml_errors);
                $templateMgr->display($this->getTemplateResource('validate.tpl'));
                break;

            default:
                $issues = iterator_to_array(Repo::issue()->getCollector()
                    ->filterByContextIds([$journal->getId()])
                    ->getMany());
                $templateMgr = TemplateManager::getManager($request);
                $templateMgr->assignByRef('issues', $issues);
                $templateMgr->display($this->getTemplateResource('issues.tpl'));
        }
    }

    function executeCLI($scriptName, &$args)
    {
        $this->usage($scriptName);
    }

    function usage($scriptName)
    {
        echo "USAGE NOT AVAILABLE.\n"
            . "This is a sample plugin and does not actually perform a function.\n";
    }

    private static function createChildWithText($doc, $node, $name, $value, $appendIfEmpty = true) {
        $childNode = null;
        if($appendIfEmpty || ($value != '' && $value !== null)) {
            $childNode = $doc->createElement($name);
            $textNode = $doc->createTextNode((string)$value);
            $childNode->appendChild($textNode);
            $node->appendChild($childNode);
        }
        return $childNode;
    }
}
