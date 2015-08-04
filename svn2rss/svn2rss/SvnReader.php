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
 * This class handles all svn-related stuff such as connecting to the svn-server, fetching the logs, ....
 * In addition, it implements a caching in order to avoid uneccessary network-requests
 *
 * @author Stefan Idler, sidler@mulchprod.de
 */
class SvnReader {
    
    /**
     *
     * @var ConfigReader
     */
    private $objConfig;

    public function __construct(ConfigReader $objConfig) {
        $this->objConfig = $objConfig;
    }

    /**
     * Gets the complete commit-log.
     * Tries to load it from cache, otherwise an external request is being made.
     *
     * @param string make sure this revision is included
     * @return string, xmlbased log-content
     */
    public function getSvnLogContent($strRevisionToCheck = false) {
       $strLogContent = false;
       
       //anything to load via the cache?
       if($this->objConfig->getBitCachingEnabled()) {
           $strLogContent = $this->getContentFromCache();
       }

       if($strLogContent === false) {
           //load the logfile via a system-call
           $strLogContent = $this->loadLoghistoryViaSvn();
       }

       //if a single revision was passed, make sure it is included
       if($strRevisionToCheck !== false) {
           if(!$this->isSingleRevisionInLogContent($strLogContent, $strRevisionToCheck)) {
               $strSingleRevision = $this->loadLoghistoryViaSvn($strRevisionToCheck);

               //validate the single revision
               $strSingleRevision = $this->extractSingleRevision($strSingleRevision);

               $strLogContent = $this->mergeSingleRevisionToLog($strLogContent, $strSingleRevision);
           }
           
       }

       //write back to the cache
       if($this->objConfig->getBitCachingEnabled()) {
           $this->setContentToCache($strLogContent);
       }

       return $strLogContent;
    }
    

    /**
     * Loads the svn-log-content via a system-call
     * @param string $strSingleRevision use this param if you want so make sure a given id is included
     * @return string
     */
    private function loadLoghistoryViaSvn($strSingleRevision = false) {
        $strSvnLogLines = "";
        $strErrors = "";

        //build the command
        $arrCommand = array();
        $arrCommand[] = escapeshellcmd($this->objConfig->getStrSvnBinaryPath());
        $arrCommand[] = "log -v --xml --no-auth-cache";
        $arrCommand[] = escapeshellarg($this->objConfig->getStrSvnUrl());
        if($strSingleRevision !== false && $strSingleRevision != "")
            $arrCommand[] = " -r ".escapeshellarg(intval($strSingleRevision));
        else
            $arrCommand[] = " -l ".escapeshellarg($this->objConfig->getIntLogAmount());
        
        if($this->objConfig->getStrSvnUsername() != "" )
            $arrCommand[] = "--username ".escapeshellarg($this->objConfig->getStrSvnUsername());

        if($this->objConfig->getStrSvnPassword() != "" )
            $arrCommand[] = "--password ".escapeshellarg($this->objConfig->getStrSvnPassword());


        //create a new process
        $arrProcessDescSpec = array(
               0 => array("pipe", "r"),  // stdin
               1 => array("pipe", "w"),  // stdout
               2 => array("pipe", "w")   // stderr
        );

        $arrPipes = array();

        $objProcess = proc_open(implode(" ", $arrCommand), $arrProcessDescSpec, $arrPipes);

        if(is_resource($objProcess)) {
            //accept certificate temporarily
            fwrite($arrPipes[0], "t\r\n");

            //read logfile
            while(!feof($arrPipes[1]))
                $strSvnLogLines .= fread($arrPipes[1], 4096);

            //read errors
            while(!feof($arrPipes[2]))
                $strErrors .= fread($arrPipes[2], 4096);

            fclose($arrPipes[0]);
            fclose($arrPipes[1]);
            fclose($arrPipes[2]);
            proc_close($objProcess);

            
        }


        if($strSvnLogLines == 0) {
            return $strSvnLogLines;
        }

        throw new Svn2RssException("Error loading svn-log content, errors: ".$strErrors);
    }

    /**
     * Tries to load the logfile from a cached request.
     *
     * @return string cachecontent, false if cache is invalid or no cached log was found
     */
    private function getContentFromCache() {
        //search for a cached file
        $strFilename = $this->generateCachename();
        if(is_file(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".SVN2RSS_CACHE_FOLDER."/".$strFilename)) {
            //file exists, read content
            $strFileContent = file_get_contents(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".SVN2RSS_CACHE_FOLDER."/".$strFilename);

            //validate cache-lease-time, so get first row
            $strFirstRow = trim( substr($strFileContent, 0, strpos($strFileContent, "\r\n")) );

            //format: projectname | version | generation time hr | gentime in secs
            $arrFirstRowParts = explode("|", $strFirstRow);
            $intTimestamp = trim($arrFirstRowParts[3]);

            //validate the timestamp against the refreh interval
            if($intTimestamp >= (time() - $this->objConfig->getIntRefreshInterval()) ) {
                $strLogContent = substr($strFileContent, strpos($strFileContent, "\r\n")+2 );
                return $strLogContent;
            }
        }
        return false;
    }

    /**
     * Saves the passed string to a cache-file
     *
     * @param string $strContent
     * @return boolean
     */
    private function setContentToCache($strContent) {

        //update the log-content
        $strHeaderRow = "svn2rss | ".SVN2RSS_VERSION." | ".strftime("%a, %d %b %Y %H:%M:%S GMT", time())." | ".time()."\r\n";
        $strContent = $strHeaderRow.$strContent;

        if(is_writable(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".SVN2RSS_CACHE_FOLDER."/") && file_put_contents(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".SVN2RSS_CACHE_FOLDER."/".$this->generateCachename(), $strContent) !== false )
            return true;


        return false;
    }


    private function generateCachename() {
        return md5($this->objConfig->getStrSvnUrl().$this->objConfig->getStrConfigSetName()).".log";
    }


    /**
     * Searches the passed log for a given revision.
     * If found true is returned, otherwise false
     * 
     * @param string $strContent
     * @param string $strRevision
     * @return bool
     */
    private function isSingleRevisionInLogContent($strContent, $strRevision) {
        //build a xml-tree out of the passed svn-log-content
        libxml_use_internal_errors();
        $objSimpleXmlElement = simplexml_load_string($strContent);
        $arrParseErrors = libxml_get_errors();
        libxml_clear_errors();

        if(count($arrParseErrors) > 0)
            throw new Svn2RssException("Error parsing xml-based svn log content.\nErrors:\n".implode("\n", $arrParseErrors));


        foreach($objSimpleXmlElement->logentry as $objOneLogEntry) {


            $arrObjAttributes = $objOneLogEntry->attributes();

            if($arrObjAttributes->revision."" == $strRevision)
                return true;

        }


        //nothing was found, return false
        return false;

    }


    /**
     * Exracts the revision out of the whole xml-document
     *
     * @param string $strLog
     * @return string
     */
    private function extractSingleRevision($strLog) {
        //build a xml-tree out of the passed svn-log-content
        libxml_use_internal_errors();
        $objSimpleXmlElement = simplexml_load_string($strLog);
        $arrParseErrors = libxml_get_errors();
        libxml_clear_errors();

        if(count($arrParseErrors) > 0)
            throw new Svn2RssException("Error parsing xml-based svn log content.\nErrors:\n".implode("\n", $arrParseErrors));


        if($objSimpleXmlElement->logentry != "")
            return $objSimpleXmlElement->logentry->asXML();

        

        //nothing was found
        return "";
    }


    /**
     * Appends the passed log-entry to the full log-file
     * @param string $strLog
     * @param string $strRevision
     * @return string 
     */
    private function mergeSingleRevisionToLog($strLog, $strRevision) {
        $strLog = trim($strLog);
        return substr($strLog, 0,  (strlen("</log>")*-1)).$strRevision."</log>";
    }

}
?>
