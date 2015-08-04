<?php
/*
 *   This file is part of svn2rss.
 *
 *   svn2rss is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   svn2rss is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Lesser General Public License for more details.
 *
 *   You should have received a copy of the GNU Lesser General Public License
 *   along with svn2rss.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 *   (c) Stefan Idler, MulchProductions, sidler@mulchprod.de, http://www.mulchprod.de
 *
 */

/**
 * The Log2HtmlConverter tries to load either the first or the specified revision-commit message
 * and converts the entries into a html-view using the template located in /svn2rss/
 *
 * @author Stefan Idler, sidler@mulchprod.de
 */
class Log2HtmlConverter {

    /**
     *
     * @var ConfigReader
     */
    private $objConfig;

    public function __construct(ConfigReader $objConfig) {
        $this->objConfig = $objConfig;
    }

    /**
     * Generates a html-view of either the first or the specified svn-commit message.
     * Uses the template to render the 
     * @param string $objLogRootNode
     * @return SimpleXMLElement
     */
    public function generateHtmlFromLogContent($strLogRootNode, $strRevisionNumber = "") {

        $objOneLogEntry= $this->getLogNodeToProcess($strLogRootNode, $strRevisionNumber);
        $arrObjAttributes = $objOneLogEntry->attributes();

        //prepare log-message
        $strChangedFiles = "";
        foreach($objOneLogEntry->paths->path as $objOnePath) {
            $objPathAttributes = $objOnePath->attributes();
            $strChangedFiles .= "<li>".$objPathAttributes->action." ".$objOnePath."</li>";
        }


        $arrTemplate = array();
        $arrTemplate["revision"] = $arrObjAttributes->revision."";
        $arrTemplate["author"] = $objOneLogEntry->author."";
        $arrTemplate["date"] = date("r", strtotime($objOneLogEntry->date));
        $arrTemplate["description"] = nl2br(htmlentities($objOneLogEntry->msg.""));
        $arrTemplate["changedfiles"] = nl2br($strChangedFiles);

        //read the template
        if(!file_exists(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".$this->objConfig->getStrHtmlViewTemplate()))
            throw new Svn2RssException("Template could not be loaded: ".SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".$this->objConfig->getStrHtmlViewTemplate());
        
        $strTemplateContent = file_get_contents(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".$this->objConfig->getStrHtmlViewTemplate());

        foreach($arrTemplate as $strKey => $strValue)
            $strTemplateContent = str_replace("%%".$strKey."%%", $strValue, $strTemplateContent);

        return $strTemplateContent;

    }

    /**
     * Tries to load a single commit-entry out of the svn-log loaded before
     * 
     * @param SimpleXMLElement $strLogRootNode
     * @param string $strRevisionNumber
     * @return SimpleXMLElement
     */
    private function getLogNodeToProcess($strLogRootNode, $strRevisionNumber) {
        //build a xml-tree out of the passed svn-log-content
        libxml_use_internal_errors();
        $objSimpleXmlElement = simplexml_load_string($strLogRootNode);
        $arrParseErrors = libxml_get_errors();
        libxml_clear_errors();

        if(count($arrParseErrors) > 0)
            throw new Svn2RssException("Error parsing xml-based svn log content.\nErrors:\n".implode("\n", $arrParseErrors));


        foreach($objSimpleXmlElement->logentry as $objOneLogEntry) {

            //if no revision was set, pass the first entry so the head rev
            if($strRevisionNumber == "")
                return $objOneLogEntry;
            

            $arrObjAttributes = $objOneLogEntry->attributes();

            if($arrObjAttributes->revision."" == $strRevisionNumber)
                return $objOneLogEntry;

        }

        throw new Svn2RssException("Specified Revision ".$strRevisionNumber." not available");
    }
}
?>
