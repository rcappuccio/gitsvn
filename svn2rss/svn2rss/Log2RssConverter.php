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
 * The Log2RssConverter-Manager creates a rss-node structure out of the passed svn-node structure.
 *
 * @author Stefan Idler, sidler@mulchprod.de
 */
class Log2RssConverter {

    /**
     *
     * @var ConfigReader
     */
    private $objConfig;

    public function __construct(ConfigReader $objConfig) {
        $this->objConfig = $objConfig;
    }

    /**
     * Generates a rss-node-structure from the passed log-node-structure
     * @param string $objLogRootNode
     * @return SimpleXMLElement
     */
    public function generateRssNodesFromLogContent($strLogRootNode) {


        $objFeedRootNode = new SimpleXMLElement("<rss version=\"2.0\"></rss>");
        $objChannel = $objFeedRootNode->addChild("channel");
        $objChannel->addChild("title", $this->objConfig->getStrFeedTitle());
        $objChannel->addChild("description", $this->objConfig->getStrFeedDescription());
        $objChannel->addChild("link", $this->objConfig->getStrSvnUrl());
        $objChannel->addChild("pubDate", date("r", time()));


        //build a xml-tree out of the passed svn-log-content
        libxml_use_internal_errors();
        $objSimpleXmlElement = simplexml_load_string($strLogRootNode);
        $arrParseErrors = libxml_get_errors();
        libxml_clear_errors();

        if(count($arrParseErrors) > 0)
            throw new Svn2RssException("Error parsing xml-based svn log content.\nErrors:\n".implode("\n", $arrParseErrors));

        foreach($objSimpleXmlElement->logentry as $objOneLogEntry) {

            $arrObjAttributes = $objOneLogEntry->attributes();
            $objRssItemNode = $objChannel->addChild("item");

            //prepare log-message
            $strDescription = $objOneLogEntry->msg."";

            //include changed files?
            if($this->objConfig->getBitFeedWithChangedFiles()) {
                $strDescription .= "\n\n";

                foreach($objOneLogEntry->paths->path as $objOnePath) {
                    $objPathAttributes = $objOnePath->attributes();
                    $strDescription .= $objPathAttributes->action." ".$objOnePath."\n";
                }
            }

            $strDescription = html_entity_decode($strDescription, ENT_COMPAT, "UTF-8");
            $strDetailsLink = SVN2RSS_WEB_ROOT."?feed=".$this->objConfig->getStrConfigSetName()."&revision=".$arrObjAttributes->revision;
            //but: encode &, <, >
            $strDescription = nl2br($this->xmlSafeString($strDescription));
            $strDetailsLink = $this->xmlSafeString($strDetailsLink);


            //title, description, logdate
            $objRssItemNode->addChild("title", $arrObjAttributes->revision." by ".$objOneLogEntry->author);
            $objDescNode = $objRssItemNode->addChild("description", $strDescription);
            //$objRssItemNode->addChild("pubDate", $objOneLogEntry->date."");
            $objRssItemNode->addChild("pubDate", date("r", strtotime($objOneLogEntry->date))."");

            $objGuidNode = $objRssItemNode->addChild("guid", $arrObjAttributes->revision."");
            $objGuidNode->addAttribute("isPermaLink", "false");

            $objRssItemNode->addChild("link", $strDetailsLink);
            
        }

        return $objFeedRootNode;

    }

    /**
     * Replaces special chars to make strings xml-safe
     * 
     * @param string $strString
     * @return string
     */
    private function xmlSafeString($strString) {
        $strString = str_replace(array("<", ">"), array("&lt;", "&gt;"), $strString);
        $strString = str_replace(array("&" ), array("&amp;"), $strString);
        return $strString;
    }

    
}
?>
