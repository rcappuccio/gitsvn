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
 * Class to manage the access to the config-file.
 * Reads the config file and loads the configSet passed via the constructur.
 * If the param is being left empty, the default-config set is being loaded.
 *
 * @author Stefan Idler, sidler@mulchprod.de
 */
class ConfigReader {

    /**
     *
     * @var SimpleXMLElement
     */
    private $objSimpleXml;

    /**
     *
     * @var SimpleXMLElement
     */
    private $objCurrentConfigSetXml = null;

    private $strConfigSetName = "";

    public function __construct($strConfigSet = "") {

        libxml_use_internal_errors();
        $this->objSimpleXml = simplexml_load_file(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_CONFIG_FILE);
        $arrParseErrors = libxml_get_errors();
        libxml_clear_errors();

        if(count($arrParseErrors) > 0)
            throw new Svn2RssException("Error parsing xml-config-file ".SVN2RSS_CONFIG_FILE.".\nErrors:\n".implode("\n", $arrParseErrors));

        if($strConfigSet == "")
            $strConfigSet = $this->getStrDefaultConfigSet();

        if($strConfigSet == "")
            throw new Svn2RssException("No default config-set defined in ".SVN2RSS_CONFIG_FILE);

        //load the config-set requested
        $this->strConfigSetName = $strConfigSet;
        foreach($this->objSimpleXml->configSets->configSet as $objOneConfigSet) {
            $arrAttributes = $objOneConfigSet->attributes();
            if($arrAttributes->id."" == $strConfigSet) {
                $this->objCurrentConfigSetXml = $objOneConfigSet;
            }
        }

        if($this->objCurrentConfigSetXml == null)
            throw new Svn2RssException("Loading of config set ".$strConfigSet." failed.");
    }


    public function getStrSvnBinaryPath() {
        return $this->objSimpleXml->globalConfig->svnBinaryPath."";
    }

    public function getBitCachingEnabled() {
        if($this->objSimpleXml->globalConfig->cachingEnabled."" == "true")
            return true;
        else
            return false;
    }

    public function getStrDefaultConfigSet() {
        return $this->objSimpleXml->globalConfig->defaultConfigSet."";
    }

    public function getStrSvnUrl() {
        return $this->objCurrentConfigSetXml->svnUrl."";
    }

    public function getStrSvnUsername() {
        return $this->objCurrentConfigSetXml->svnUsername."";
    }

    public function getStrSvnPassword() {
        return $this->objCurrentConfigSetXml->svnPassword."";
    }

    public function getIntLogAmount() {
        return (int)$this->objCurrentConfigSetXml->logAmount."";
    }

    public function getIntRefreshInterval() {
        return (int)$this->objCurrentConfigSetXml->refreshInterval."";
    }

    public function getStrFeedTitle() {
        return $this->objCurrentConfigSetXml->feedTitle."";
    }

    public function getStrFeedDescription() {
        return $this->objCurrentConfigSetXml->feedDescription."";
    }

    public function getBitFeedWithChangedFiles() {
        return $this->objCurrentConfigSetXml->feedWithChangedFiles."" == "true";
    }

    public function getStrHtmlViewTemplate() {
        return $this->objCurrentConfigSetXml->htmlViewTemplate."";
    }

    public function getStrConfigSetName() {
        return $this->strConfigSetName;
    }


    
    
}
?>
