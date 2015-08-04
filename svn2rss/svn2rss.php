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
 * PLEASE NOTE:
 * All configuration can be made in svn2rss.xml.
 * There is no need to change anything in this file.
 */







//set up base-constants
define("SVN2RSS_PROJECT_ROOT",  dirname(__FILE__));
define("SVN2RSS_WEB_ROOT",     (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? "https://" : "http://").$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']);
define("SVN2RSS_SYSTEM_FOLDER", "svn2rss");
define("SVN2RSS_CACHE_FOLDER",  "cache");
define("SVN2RSS_CONFIG_FILE",   "svn2rss.xml");
define("SVN2RSS_VERSION",       "1.4");


/**
 * Autoloader. Handles the loading of class-definitions not known to the compiler.
 * Called by PHP, so no need to call on your own
 * @param string $strClassName
 * @return void
 */
function __autoload($strClassName) {
    
    if(require(SVN2RSS_PROJECT_ROOT."/".SVN2RSS_SYSTEM_FOLDER."/".$strClassName.".php"))
        return;
}

//different processing of xml-requests and web-requests
if(isset($_GET["revision"]) && $_GET["revision"] != "") {
    $strFeedParam = isset($_GET["feed"]) ? $_GET["feed"] : "";
    $strRevisionParam = isset($_GET["revision"]) ? $_GET["revision"] : "";

    $objSvn2Rss = new Svn2Rss();
    $objSvn2Rss->processSvn2WebRequest($strFeedParam, $strRevisionParam);

    $strReturnCode = $objSvn2Rss->getStrOutput();

    echo $strReturnCode;
}
else {

    //start rss2svn and invoke the request-processing
    $strFeedParam = isset($_GET["feed"]) ? $_GET["feed"] : "";
    $objSvn2Rss = new Svn2Rss();
    $objSvn2Rss->processSvn2RssRequest($strFeedParam);

    //set up response to browser
    header("Content-Type: text/xml; charset=utf-8");

    $strReturnCode = $objSvn2Rss->getStrOutput();

    echo $strReturnCode;
}



?>
