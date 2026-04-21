{**
 * plugins/importexport/sample/issues.tpl
 * Copyright (c) 2024-2026 origrata@ioscloud.co.id , RRZ SCIENTIFIC PUBLISHING
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * List of issues to potentially export
 *
 *}
{strip}
{assign var="pageTitle" value="plugins.importexport.copernicus.selectIssue.long"}
{assign var="pageCrumbTitle" value="plugins.importexport.copernicus.selectIssue.short"}
{/strip}

{extends file="layouts/backend.tpl"}
{block name="page"}
<br/>

<div id="issues">
<table class="listing">
    <tr>
        <td colspan="5" class="headseparator">&nbsp;</td>
    </tr>
    <tr class="heading" valign="bottom">
        <td width="65%">{translate key="issue.issue"}</td>
        <td width="15%">{translate key="issue.published"}</td>
        <td width="20%">{translate key="common.action"}</td>
    </tr>
    <tr>
        <td colspan="5" class="headseparator">&nbsp;</td>
    </tr>
    
    {foreach from=$issues item=issue name=issueLoop}
    <tr valign="top">
        <td>
            <a href="{url page="issue" op="view" path=$issue->getId()}" class="action">{$issue->getIssueIdentification()|strip_tags}</a>
        </td>
        <td>
            {$issue->getDatePublished()|date_format:"%Y-%m-%d"}
        </td>
        <td>
            {assign var="issueId" value=$issue->getId()}
            <a href="{url page="management" op="importexport" path="plugin/CopernicusExportPlugin/exportIssue"|to_array:$issueId}" class="action">{translate key="plugins.importexport.copernicus.exportIssue"}</a>
            <a href="{url page="management" op="importexport" path="plugin/CopernicusExportPlugin/validateIssue"|to_array:$issueId}" class="action">{translate key="plugins.importexport.copernicus.validateIssue"}</a>
        </td>
    </tr>
    <tr>
        <td colspan="5" class="{if $smarty.foreach.issueLoop.last}end{/if}separator">&nbsp;</td>
    </tr>
    {foreachelse}
    <tr>
        <td colspan="5" class="nodata">{translate key="issue.noIssues"}</td>
    </tr>
    <tr>
        <td colspan="5" class="endseparator">&nbsp;</td>
    </tr>
    {/foreach}
</table>
</div>
{/block}
