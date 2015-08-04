<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
<title>GIT/SVN Web Based Management</title>
    <!--<link rel="stylesheet" type="text/css" href="CSS/styles.css" media="screen">-->
    <script type="text/javascript" src="scripts/simpletreemenu.js">
    /***********************************************
    * Simple Tree Menu- © Dynamic Drive DHTML code library (www.dynamicdrive.com)
    * This notice MUST stay intact for legal use
    * Visit Dynamic Drive at http://www.dynamicdrive.com/ for full source code
    ***********************************************/
    </script>
    
    <link rel="stylesheet" type="text/css" href="CSS/simpletree.css">
    <script type="text/javascript">
    <!--
    function confirmation(ID,Name) 
    {
        var answer = confirm( "Delete repository\n" + Name + " ?" );
        if ( answer )
        {
            alert( "Selected repository will be deleted" );
            parent.location= "delete.php?repo=" + ID;
        }
        else
        {
            alert( "No action taken" );
        }
    }
    //-->
    </script>
</head>
<body>
<?php
# this file contains the whole configuration of the application
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$user_id = strtolower($_SERVER['PHP_AUTH_USER']);

if ($user_id == null) die("ERROR: user_id is null");
if ($user_id == "") die("ERROR: user_id is empty");

$path = $user_id;

// If the user clicked on "Create", we get the "repository" field from the form
// and create the repository
$repo = $_POST['repository'];
$type = $_POST['repo_type'];

if ( $repo ) {
    # clean up the repository name
    $repo = preg_replace('/\s+/', '', $repo);
    // Save the information about the repository in the database
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'ERROR: Could not connect: ' . mysql_error() );

    mysql_select_db($DATABASE_USER, $con);
    $repository_name = strtolower( $type ) . "/" . $path . "/" . $repo;

    $rssid = sprintf("%s", uniqid());
    
    $sqlstr = "INSERT INTO repositories(repository_name, repository_type, username, rssid) values('" . $repository_name . "', '". $type ."', '" . mysql_real_escape_string( $user_id ) . "', '" . $rssid . "')";
    $result = mysql_query($sqlstr);

    print "<font color='red'>" . mysql_error() . "</font>";

    mysql_close($con); 

    if ( $type == "SVN" )
    {
        // If the user directory does not exist, create it on the fly
        $output = shell_exec( "mkdir " . $SVN_REPOSITORY_PATH . "/" . $path);
        echo $output;
        $output = shell_exec( "chown -R www-data.www-data " . $SVN_REPOSITORY_PATH . "/" . $path);
        echo $output;
        
        // Create the actual SVN repository
        
        // Create the repository in the user directory
        $svnpath = $SVN_REPOSITORY_PATH . "/" . $path . "/" . $repo ;

        $output = shell_exec( "svnadmin create " . $svnpath );
        echo $output;

        $output = shell_exec( "chown -R www-data.www-data " . $svnpath );
        echo $output;
      
        // Create the Apache2 conf file for the repository so that it becomes accessible through web
        $output = shell_exec ( "touch " . $SVN_APACHE_CONF_PATH . "/gitsvn_" . $path . "_"  . $repo . ".conf" );
        echo $output;

        $myFile = $SVN_APACHE_CONF_PATH . "/gitsvn_" . $path . "_" . $repo . ".conf";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile );

        $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        $stringData = $stringData . "<Location /repositories/svn/" . $path . "/" . $repo . ">\n";
        $stringData = $stringData . "   DAV svn\n";
        $stringData = $stringData . "   SVNPath " . $SVN_REPOSITORY_PATH . "/" . $path . "/" . $repo . "\n";
        $stringData = $stringData . "   SVNAutoVersioning On\n";
        $stringData = $stringData . "   AuthzSVNAccessFile " . $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $path . "_" . $repo . "_authz.conf\n";
        $stringData = $stringData . "   AuthType Basic\n";
        $stringData = $stringData . "   AuthBasicProvider ldap\n";
        $stringData = $stringData . "   AuthName \"" . $LDAP_AUTH_NAME . "\"\n";
        $stringData = $stringData . "   AuthLDAPURL \"" . $LDAP_CONNECTION_STRING . "\"\n";
        $stringData = $stringData . "   AuthLDAPGroupAttributeIsDN off\n";
        $stringData = $stringData . "   AuthLDAPGroupAttribute member memberUid uniqueMember\n";
        $stringData = $stringData . "   Require valid-user\n";
        $stringData = $stringData . "</Location>\n";
        
        fwrite($fh, $stringData);

        fclose($fh);
                
        // Create the gitsvn_username_reponame_authz.conf file containing the authorizations for the repository
        $output = shell_exec ( "touch " . $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $path . "_" . $repo . "_authz.conf" );
        echo $output;

        $myFile = $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $path . "_" . $repo . "_authz.conf";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile );

        $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        
        // the group $ADMIN_GROUP is ALWAYS given read/write rights
        // so there is always at least one group ($ADMIN_GROUP)
        $stringData = $stringData . "[groups]\n";
        $stringData = $stringData . $ADMIN_GROUP . "=";
        
        // Expand $ADMIN_GROUP LDAP Group
        $textSearch = "(| (cn=" . $ADMIN_GROUP . ") (memberUid=" . $ADMIN_GROUP . "))";
        $ldapClass="posixGroup";
        $filter="(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";
        
        $ds=ldap_connect( "ldaps://bender.inf.unibz.it:636" );
        if ( $ds )
        {
            $r = ldap_bind( $ds );
            $sr = ldap_search( $ds, "dc=inf,dc=unibz,dc=it", $filter );
            $entries = ldap_get_entries( $ds, $sr );

            for ($i=0; $i<$entries["count"]; $i++) {
                $cn = $entries[$i]["cn"][0];
                $members = $entries[$i]["memberuid"];

                for ($j=0; $j < $members["count"]; $j++) { 
                    $stringData = $stringData . $members[$j];
                    if ( $j < $members["count"]-1 )
                        $stringData = $stringData . ", ";
                }
            }
            $stringData = $stringData . "\n";
        }
        ldap_close($ds);
                
        $stringData = $stringData . "[" . $repo . ":/]\n";
        $stringData = $stringData . $user_id . " = rw\n";
        $stringData = $stringData . "@" . $ADMIN_GROUP . " = rw\n";
        $stringData = $stringData . "* =\n";
        
        fwrite($fh, $stringData);

        fclose($fh);
        
        // add the owner and group $ADMIN_GROUP with read/write rights to the database
        //
        
        // build the repository name
        $repository_name = strtolower( $type ) . "/" . $path . "/" . $repo;
                
        // get the repository_id of the newly created repository
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con ) die( 'Could not connect: ' . mysql_error() );

        mysql_select_db($DATABASE_USER, $con);
                
        $result = mysql_query( "SELECT * FROM `repositories` WHERE `repository_name` = '" . $repository_name . "'" );
        while( $row = mysql_fetch_array( $result ) )
        {
            $repository_id = $row['repository_ID'];
        }
        
        // insert the rights for the user 
        $sqlstr = "INSERT INTO rights(`repository_ID`, `type`, `username`, `path`, `read`, `write`) values('" . $repository_id . "', 'user', '" . mysql_real_escape_string( $user_id ) . "', '/', '1', '1')";
        $result = mysql_query($sqlstr);

        if ( !$result ) print $result;
       
        // insert the rights for the group $ADMIN_GROUP 
        $sqlstr = "INSERT INTO rights(`repository_ID`, `type`, `username`, `path`, `read`, `write`) values('" . $repository_id . "', 'group', '" . $ADMIN_GROUP . "', '/', '1', '1')";
        $result = mysql_query($sqlstr);
        if ( !$result ) print $result;

        
        mysql_close($con); 
        
        
        // recreate from scratch the svn2rss XML configuration file
        $output = shell_exec ( "rm /var/www/html/svn2rss/svn2rss.xml" );
        echo $output;

        $myFile = "/var/www/html/svn2rss/svn2rss.xml";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile );

        $stringData = "";
        $stringData = $stringData . "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $stringData = $stringData . "<!DOCTYPE config SYSTEM \"./svn2rss.dtd\">\n";
        $stringData = $stringData . "<config version=\"1.4\">\n";
        $stringData = $stringData . "    <globalConfig>\n";
        $stringData = $stringData . "        <svnBinaryPath>svn</svnBinaryPath>\n";
        $stringData = $stringData . "        <cachingEnabled>true</cachingEnabled>\n";
        $stringData = $stringData . "        <defaultConfigSet>none</defaultConfigSet>\n";
        $stringData = $stringData . "    </globalConfig>\n";
        $stringData = $stringData . "    <configSets>\n";
        
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con ) die( 'Could not connect: ' . mysql_error() );

        mysql_select_db( $DATABASE_USER, $con );

        $result1 = mysql_query( "SELECT * FROM repositories WHERE repository_type='SVN'" );

        $count1 = mysql_num_rows( $result1 );

        while( $row = mysql_fetch_array( $result1 ) )
        {
            $pos = strrpos( $row['repository_name'], '/' );
            $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
            $username = $row['username'];
            $rssid = $row['rssid'];
            
            $path = $username;
 
            $stringData = $stringData . "        <configSet id=\"" . $rssid . "\">\n";
            $stringData = $stringData . "            <svnUrl>file://" .$SVN_REPOSITORY_PATH . "/" . $path . "/" . $reponame . "</svnUrl>\n";
            $stringData = $stringData . "            <svnUsername></svnUsername>\n";
            $stringData = $stringData . "            <svnPassword></svnPassword>\n";
            $stringData = $stringData . "            <logAmount>25</logAmount>\n";
            $stringData = $stringData . "            <refreshInterval>60</refreshInterval>\n";
            $stringData = $stringData . "            <feedTitle>" . $reponame . " SVN History</feedTitle>\n";
            $stringData = $stringData . "            <feedDescription>Browse the SVN-history of " . $reponame . "</feedDescription>\n";
            $stringData = $stringData . "            <feedWithChangedFiles>true</feedWithChangedFiles>\n";
            $stringData = $stringData . "            <htmlViewTemplate>svn2rss.xhtml</htmlViewTemplate>\n";
            $stringData = $stringData . "        </configSet>\n";
        }

        $stringData = $stringData . "    </configSets>\n";        
        $stringData = $stringData . "</config>\n";

        fwrite($fh, $stringData);

        fclose($fh);
   
        
    }
    else if ( $type == "GIT" )
    {
        if ($path == null) die("ERROR: Userpath is null");
        if ($path == "") die("ERROR: Userpath is empty");
               
        // If the user directory does not exist, create it on the fly
        $output = shell_exec( "mkdir " . $GIT_REPOSITORY_PATH . "/" . $path);
        echo $output;
        $output = shell_exec( "chown -R www-data.www-data " . $GIT_REPOSITORY_PATH . "/" . $path);
        echo $output;
        
        // Create the actual SVN repository                
                
        // Initialize the bare GIT repository
        $gitpath = $GIT_REPOSITORY_PATH . "/". $path . "/" . $repo . ".git";
 
        $output = shell_exec( "mkdir " . $gitpath );
        echo $output;
 
        $output = shell_exec( "git init --bare " . $gitpath );
        //echo $output;
      
        $output = shell_exec( "cd " . $GIT_REPOSITORY_PATH . "/" . $gitpath . " && git update-server-info" );
        echo $output;

        $output = shell_exec( "touch " . $gitpath . "/objects/info/packs" );
        echo $output;

        $output = shell_exec( "touch " . $gitpath . "/info/refs" );
        echo $output;
        
        $output = shell_exec( "chown -R www-data.www-data " . $gitpath );
        echo $output;
      
        // Create the acl.conf file in the root of the user
        $output = shell_exec ( "touch " . $GIT_REPOSITORY_PATH . "/" . $path . "/acl.conf" );
        echo $output;
        
        // Update /data/git/repositories/username/acl.conf file
        // The file has to be completely rewritten every time the authorizations for the repository are modified
        $myFile = $GIT_REPOSITORY_PATH . "/" . $path . "/acl.conf";
        $fh = fopen( $myFile, 'w' ) or die( "###can't open file: " . $myFile );
        
        $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        $stringData = $stringData . "#Format\n";
        $stringData = $stringData . "#path username permissions\n";
        $stringData = $stringData . "#/              ausername       2\n";
        $stringData = $stringData . "#/test/aRepo1o  anotherusername 1\n";
        $stringData = $stringData . "#/test2/myRepo  anotherusername 2\n";
        $stringData = $stringData . "#/test2/myRepo  @myfriends      1\n";
        $stringData = $stringData . "#/test3/public  @user           1\n";
        $stringData = $stringData . "#\n";
        $stringData = $stringData . "# Permissions:\n";
        $stringData = $stringData . "# none   0\n";
        $stringData = $stringData . "# read   1\n";
        $stringData = $stringData . "# edit   2\n";
        $stringData = $stringData . "#\n";
        $stringData = $stringData . "# Special users:\n";        
        $stringData = $stringData . "# @user: any authenticaed user\n";
        $stringData = $stringData . "# @group: a group\n";   
        $stringData = $stringData . "\n";
        $stringData = $stringData . "/       @" . $ADMIN_GROUP. "       2\n";  
        $stringData = $stringData . "/       " . $user_id . "        2\n";
        $stringData = $stringData . "/".$repo."       " . $user_id . "        2\n";
        $stringData = $stringData . "/      @" . $ADMIN_GROUP . "       2\n";
        
        // Cycle through all repositories of current user
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con )
            die( 'Could not connect: ' . mysql_error() );

        mysql_select_db( $DATABASE_USER, $con );
        $result = mysql_query( "SELECT *, rights.username AS name FROM rights INNER JOIN repositories ON rights.repository_ID = repositories.repository_ID WHERE repositories.username = '" . $user_id . "' AND repository_type = 'GIT'" );
        
        while( $row = mysql_fetch_array( $result ) )
        {
            $pos = strrpos( $row['repository_name'], '/' );
            $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
        
            $stringData = $stringData . "/" . $reponame . "\t" . $row['name'] . "\t";
        
            if ( $row['read'] == "1" )
            {
                if ( $row['write'] == "1" )
                    $stringData = $stringData . "2\n";
                else
                    $stringData = $stringData . "1\n";
            }
        }
              
        fwrite( $fh, $stringData );
        fclose( $fh );
     
        $output = shell_exec( "chown -R www-data.www-data " . $gitpath );
        echo $output;
 
        // Create the Apache2 configuration file for the repository so that it becomes accessible through web
        // IF IT ALREADY EXISTS, IT IS REWRITTEN
        $output = shell_exec ( "touch " . $GIT_APACHE_CONF_PATH . "/gitsvn_" . $path . ".conf" );
        echo $output;

        $myFile = $GIT_APACHE_CONF_PATH . "/gitsvn_" . $path . ".conf";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile );

        $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        
        $stringData = $stringData . "ScriptAlias /repositories/git/" . $path . " /usr/lib/git-core/git-http-backend/\n";
        $stringData = $stringData . "<Location /repositories/git/" . $path  . ">\n";
        $stringData = $stringData . "   SetEnv GIT_PROJECT_ROOT " . $GIT_REPOSITORY_PATH . "/" . $path . "\n";
        $stringData = $stringData . "   SetEnv GIT_HTTP_EXPORT_ALL\n";
        $stringData = $stringData . "   AuthType Basic\n";
        $stringData = $stringData . "   AuthBasicProvider ldap\n";
        $stringData = $stringData . "   AuthName \"" . $LDAP_AUTH_NAME ."\"\n";
        $stringData = $stringData . "   AuthLDAPURL \"" . $LDAP_CONNECTION_STRING. "\"\n";
        $stringData = $stringData . "   AuthLDAPGroupAttributeIsDN off\n";
        $stringData = $stringData . "   AuthLDAPGroupAttribute member memberUid uniqueMember\n";
        $stringData = $stringData . "   Require valid-user\n";
        $stringData = $stringData . "</Location>\n\n";
   
        fwrite($fh, $stringData);

        fclose($fh);
        
        // add the owner and group $ADMIN_GROUP with read/write rights to the database
        //
        
        // build the repository name
        $repository_name = strtolower( $type ) . "/" . $path . "/" . $repo;
                
        // get the repository_id of the newly created repository
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con )
            die( 'Could not connect: ' . mysql_error() );

        mysql_select_db($DATABASE_USER, $con);
                
        $result = mysql_query( "SELECT * FROM `repositories` WHERE `repository_name` = '" . $repository_name . "'" );
        while( $row = mysql_fetch_array( $result ) )
        {
            $repository_id = $row['repository_ID'];
        }
        
        // insert the rights for the user 
        $sqlstr = "INSERT INTO rights(`repository_ID`, `type`, `username`, `path`, `read`, `write`) values('" . $repository_id . "', 'user', '" . mysql_real_escape_string ( $user_id ) . "', '/', '1', '1')";
        $result = mysql_query($sqlstr);
        if ( !$result ) print $result;

        // insert the rights for the group $ADMIN_GROUP
        $sqlstr = "INSERT INTO rights(`repository_ID`, `type`, `username`, `path`, `read`, `write`) values('" . $repository_id . "', 'group', '" . $ADMIN_GROUP . "', '/', '1', '1')";
        $result = mysql_query($sqlstr);
        if ( !$result ) print $result;

        mysql_close($con); 
    }

    // Set the flag for the Crontab job resetting Apache2
    // If the Crontab job finds the flag.txt file in the commands directory, it resets Apache2
    // The job checks the directory every minute
    $output = shell_exec ( "touch " . "/var/www/html/commands/flag.txt" );
    echo $output;
    
    // Send an email to the creator of the repository and also to us

    // first of all, retrieve the user's email address from LDAP
    $ldapClass = "person";
    $textSearch = "(cn=" . $user_id . ")";
    $filter = "(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";

    $email = "";
    
    $ds=ldap_connect( $LDAP_SERVER_AND_PORT );
    if( $ds )
    {
        $r = ldap_bind( $ds );
        $sr = ldap_search( $ds, $LDAP_SEARCH_BASE, $filter );
        $entries = ldap_get_entries( $ds, $sr );
        
        for ( $i=0; $i<$entries["count"]; $i++ ) 
        {
            $email = $entries[$i]["mail"][0];
        }

        ldap_close($ds);
    }

    
    // send the email
    $to = $email . ";" . $ADMIN_EMAIL;
    $subject = $type . " repository " . $repo . " created by " . $user_id;
    $body    = "This message has been generated by the GIT/SVN Web Based Management.\nThe " . $type . " repository " . $repo . " has been created by " . $user_id . ".\n";
    $headers = "From: " .$ADMIN_EMAIL . "\r\n" . "X-Mailer: php";
    
    if ( mail( $to, $subject, $body, $headers ) ) 
    {
        // echo ( "<p>Message sent!</p>" );
    } 
    else
    {
        echo( "<p>WARNING: Message delivery failed...</p>" );
    }


}

print "<a href=\"http://" . $server . "/index.php\"><h1>GIT/SVN Web Based Management</h1></a>";
print "<p>";

// List the SVN and GIT repositories for which the user has rights

// We begin with the repositories created by the user
print "<p>SVN and GIT repositories owned by user ";
print "<b>" . $user_id . "</b>:</p>\n";
print "<br>\n";

// SVN
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );

$result1 = mysql_query( "SELECT * FROM repositories WHERE username='" . mysql_real_escape_string ($user_id) . "' AND repository_type='SVN' ORDER BY repository_name ASC" );
$result2 = mysql_query( "SELECT * FROM repositories WHERE username='" . mysql_real_escape_string ($user_id) . "' AND repository_type='GIT' ORDER BY repository_name ASC" );

$count1 = mysql_num_rows( $result1 );
$count2 = mysql_num_rows( $result2 );

print "<a href=\"javascript:ddtreemenu.flatten('treemenu1', 'expand')\"><img title=\"Expand all\" alt=\"Eypand all\"src=\"images/expand.png\"></a><a href=\"javascript:ddtreemenu.flatten('treemenu1', 'contact')\"><img title=\"Contract all\" alt=\"Contract all\" src=\"images/collapse.png\"></a></a><br><br>\n";

print "<ul id='treemenu1' class='treeview'>\n";

print "<li>SVN\n";
print "     <ul>\n";
print "         <li>" . $user_id . "\n";
print "             <!--<ul rel=\"open\">-->\n";
print "             <ul>\n";

while( $row = mysql_fetch_array( $result1 ) )
{
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'];
    
    print "<li>\n";

    print "<div class='repository'>" . $reponame . "</div>\n";
    print "<div class='repository2'>rw</div>";
    print "<a href=\"\" onClick=\"confirmation(" . $row['repository_ID'] . ", '" . $row['repository_name'] . "');\"><img title=\"Delete\" alt=\"Delete\" src=\"images/delete.png\"></a>&nbsp;\n";
    print "<a href='authorize.php?repo=" . $row['repository_ID'] . "'><img title=\"Authorize\" alt=\"Authorize\" src=\"images/authorize.png\"></a>&nbsp;\n";
    print "<a href='transfer.php?repo=" . $row['repository_ID'] . "'><img title=\"Transfer Repository to other User\" alt=\"Transfer Repository to other User\" src=\"images/transfer.png\"></a>&nbsp;\n";
    print "<a href='" . "'><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    print "<a href='http://" . $server. "/svn2rss/svn2rss.php?feed=" . $row['rssid'] . "'><img title=\"RSS Feed\" alt=\"RSS Feed\" src=\"images/rss.png\"></a>\n";
    
    print "</li>\n";
}

print "             </ul>\n";
print "         </li>\n";
print "     </ul>\n";
print "</li>\n";

// GIT

print "<li>GIT\n";
print "     <ul>\n";
print "         <li>" . $user_id . "\n";
print "             <!--<ul rel=\"open\">-->\n";
print "             <ul>\n";

while( $row = mysql_fetch_array( $result2 ) )
{
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'] . ".git";
    
    print "<li>";

    print "<div class='repository'>" . $reponame . "</div>\n";
    print "<div class='repository2'>rw</div>";
    print "<a href=\"\" onClick=\"confirmation(" . $row['repository_ID'] . ", '" . $row['repository_name'] . "');\"><img title=\"Delete\" alt=\"Delete\" src=\"images/delete.png\"></a>&nbsp;\n";
    print "<a href='authorize.php?repo=" . $row['repository_ID'] . "'><img title=\"Authorize\" alt=\"Authorize\" src=\"images/authorize.png\"></a>&nbsp;\n";
    print "<a href=''><img title=\"Transfer Repository to other User\" alt=\"Transfer Repository to other User\" src=\"images/transfer.png\" onClick=\"alert('Transfer is not possible for GIT repositories yet.')\"></a>&nbsp;&nbsp;";
    print "<a href=''><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    #print "<a href='http://" . $server. "/svn2rss/svn2rss.php?feed=" . $row['rssid'] . "'><img title=\"RSS Feed\" alt=\"RSS Feed\" src=\"images/rss.png\"></a>\n";
    
    print "</li>\n";

}

print "             </ul>\n";
print "         </li>\n";
print "     </ul>\n";
print "</li>\n";

print "</ul>\n";

mysql_close($con); 

print "<script type=\"text/javascript\">";
print "     ddtreemenu.createTree( \"treemenu1\", true );";
print "</script>";


// Then we continue with the repositories created by other users, for which (repositories) the user has rights
print "<br><br>\n";

print "SVN and GIT repositories for which user ";
print "<b>" . $user_id . "</b> has access rights:";
print "<br><br>\n";

$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );

$result1 = mysql_query( "SELECT *, repositories.username AS owner, rights.path AS path FROM rights INNER JOIN repositories ON rights.repository_ID = repositories.repository_ID WHERE repository_type = 'SVN' and rights.username = '" . mysql_real_escape_string ($user_id) . "' ORDER BY repositories.username, repository_name ASC" );
$result2 = mysql_query( "SELECT *, repositories.username AS owner FROM rights INNER JOIN repositories ON rights.repository_ID = repositories.repository_ID WHERE repository_type = 'GIT' and rights.username = '" . mysql_real_escape_string ($user_id) . "' ORDER BY repositories.username, repository_name ASC" );

$count1 = mysql_num_rows($result1);
$count2 = mysql_num_rows($result2);

print "<a href=\"javascript:ddtreemenu.flatten('treemenu2', 'expand')\"><img title=\"Expand all\" alt=\"Eypand all\"src=\"images/expand.png\"></a><a href=\"javascript:ddtreemenu.flatten('treemenu2', 'contact')\"><img title=\"Contract all\" alt=\"Collapse all\" src=\"images/collapse.png\"></a></a><br><br>\n";

print "<ul id='treemenu2' class='treeview'>\n";

// SVN
print "<li>SVN\n";
print "     <ul>\n";

$previous_username = "x";
$al_least_one = False;

while( $row = mysql_fetch_array( $result1 ) )
{
    $at_least_one = True;

    if ( $previous_username != $row['owner'] && $previous_username != "x" ) 
    {
        print "             </ul>\n";
        print "         </li>\n";
    }

    if ( $previous_username != $row['owner'] )
    {
        print "         <li>" . $row['owner'] . "\n";
        print "             <ul>\n";
    }
    
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'];
    
    print "                 <li>";
    print "<div class='repository2'>" . $reponame . "</div>\n";
    print "<div class='repository2'>";
    if ( $row['read'] == 1 )
        print "r";
    if ( $row['write'] == 1 )
        print "w";
    print "</div>";

    print "<a href='" . "'><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    print "<a href='http://" . $server. "/svn2rss/svn2rss.php?feed=" . $row['rssid'] . "'><img title=\"RSS Feed\" alt=\"RSS Feed\" src=\"images/rss.png\"></a>\n";

    print "                 </li>\n";
    
    $previous_username = $row['owner'];
}

if ( $at_least_one == True )
{
    print "             </ul>\n";
    print "         </li>\n";
}

print "</ul>\n";
print "</li>\n";

// GIT
print "<li>GIT\n";
print "     <ul>\n";


$previous_username = "x";
$al_least_one = False;

while( $row = mysql_fetch_array( $result2 ) )
{
    $at_least_one = True;

    if ( $previous_username != $row['owner'] && $previous_username != "x" ) 
    {
        print "             </ul>\n";
        print "         </li>\n";
    }

    if ( $previous_username != $row['owner'] )
    {
        print "         <li>" . $row['owner'] . "\n";
        print "             <ul>\n";
    }
    
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'] . ".git";
    
    print "                 <li>";
    print "<div class='repository2'>" . $reponame . "</div>\n";
    print "<div class='repository2'>";
    if ( $row['read'] == 1 )
        print "r";
    if ( $row['write'] == 1 )
        print "w";
    print "</div>";

    print "<a href='" . "'><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    print "                 </li>\n";
    
    $previous_username = $row['owner'];
}

if ( $at_least_one == True )
{
    print "             </ul>\n";
    print "         </li>\n";
}

print "</ul>\n";
print "</li>\n";


print "</ul>\n";

print "<script type=\"text/javascript\">";
print "     ddtreemenu.createTree( \"treemenu2\", true );";
print "</script>";

mysql_close($con); 

print "<br><br>\n";


// Then we continue with the repositories created by other users, for which (repositories) the user has rights due to belonging to a group
print "SVN and GIT repositories for which groups containing user ";
print "<b>" . $user_id . "</b> have access rights:";
print "<br><br>\n";

$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con )
    die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );

$result1 = mysql_query( "SELECT *, repositories.username AS owner, rights.path AS path FROM rights INNER JOIN groups ON rights.username = groups.group JOIN repositories ON rights.repository_id = repositories.repository_id WHERE repository_type = 'SVN' and groups.users LIKE '%" . mysql_real_escape_string ( $user_id ) . "%' ORDER BY owner, repository_name ASC;" );
$result2 = mysql_query( "SELECT *, repositories.username AS owner FROM rights INNER JOIN groups ON rights.username = groups.group JOIN repositories ON rights.repository_id = repositories.repository_id WHERE repository_type = 'GIT' and groups.users LIKE '%" . mysql_real_escape_string ( $user_id ) . "%' ORDER BY owner, repository_name ASC;" );

$count1 = mysql_num_rows($result1);
$count2 = mysql_num_rows($result2);


print "<a href=\"javascript:ddtreemenu.flatten('treemenu3', 'expand')\"><img title=\"Expand all\" alt=\"Eypand all\"src=\"images/expand.png\"></a><a href=\"javascript:ddtreemenu.flatten('treemenu3', 'contact')\"><img title=\"Contract all\" alt=\"Contract all\" src=\"images/collapse.png\"></a></a><br><br>\n";
    
print "<ul id='treemenu3' class='treeview'>\n";

// SVN
print "<li>SVN\n";
print "     <ul>\n";

$previous_username = "x";
$at_least_one = False;

while( $row = mysql_fetch_array( $result1 ) )
{
    $at_least_one = True;

    if ( $previous_username != $row['owner'] && $previous_username != "x" ) 
    {
        print "             </ul>\n";
        print "         </li>\n";
    }

    if ( $previous_username != $row['owner'] )
    {
        print "         <li>" . $row['owner'] . "\n";
        print "             <ul>\n";
    }
    
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'];

    print "                 <li>";
    print "<div class='repository2'>" . $reponame . "</div>\n";
    print "<div class='repository2'>";
    if ( $row['read'] == 1 )
        print "r";
    if ( $row['write'] == 1 )
        print "w";
    print "</div>";
    
    #print "  <a href='http://" . $server . "/repositories/" . $row['repository_name'] . $row['path'] . "'>URL for checking out</a>&nbsp;&nbsp;";
    print "<a href='" . "'><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    #print "<a href='http://" . $server. "/svn2rss/svn2rss.php?feed=" . $row['rssid'] . "'>RSS Feed</a>\n";
    print "<a href='http://" . $server. "/svn2rss/svn2rss.php?feed=" . $row['rssid'] . "'><img title=\"RSS Feed\" alt=\"RSS Feed\" src=\"images/rss.png\"></a>\n";
    print "   (access rights inherited from group: <b>" . $row['group'] . "</b>)";
    print "                 </li>\n";
    
    $previous_username = $row['owner'];
}

if ( $at_least_one == True )
{
    print "             </ul>\n";
    print "         </li>\n";
}

print "</ul>\n";
print "</li>\n";


// GIT
print "<li>GIT\n";
print "     <ul>\n";

$previous_username = "x";
$at_least_one = False;

while( $row = mysql_fetch_array( $result2 ) )
{
    $at_least_one = True;

    if ( $previous_username != $row['owner'] && $previous_username != "x" ) 
    {
        print "             </ul>\n";
        print "         </li>\n";
    }

    if ( $previous_username != $row['owner'] )
    {
        print "         <li>" . $row['owner'] . "\n";
        print "             <ul>\n";
    }
    
    $pos = strrpos( $row['repository_name'], '/' );
    $reponame = substr( $row['repository_name'], -( strlen( $row['repository_name'] ) - $pos ) + 1 );
    $reponamez = "http://" . $server. "/repositories/" . $row['repository_name'] . ".git";

    print "                 <li>";
    print "<div class='repository2'>" . $reponame . "</div>\n";
    print "<div class='repository2'>";
    if ( $row['read'] == 1 )
        print "r";
    if ( $row['write'] == 1 )
        print "w";
    print "</div>";
    
    print "<a href='" . "'><img title=\"URL for Check Out\" alt=\"URL for Check Out\" src=\"images/checkout.png\" onClick=\"alert('" . $reponamez .  "')\"></a>&nbsp;&nbsp;";
    print "                 </li>\n";
    
    $previous_username = $row['owner'];
}

if ( $at_least_one == True )
{
    print "             </ul>\n";
    print "         </li>\n";
}

print "</ul>\n";
print "</li>\n";


print "</ul>\n";

print "<script type=\"text/javascript\">";
print "     ddtreemenu.createTree( \"treemenu3\", true );";
print "</script>";

mysql_close($con); 

print "<br><br>\n";


// Form to add repositories

print "<hr>\n";

print "<form action='index.php' method='post'>\n";
print "New repository ";

print "<select name='repo_type'>\n";
print "<option value = 'SVN' >SVN</option>\n";
print "<option value = 'GIT' >GIT</option>\n";
print "</select> ";

print "<input name='repository' size=30> ";
print "<button type='submit' onClick=\"confirm('Please wait 1 minute before checking the newly created repository.')\">Create</button>\n";
print "</form>\n";

print "<br><br>\n";


print "<p>Useful information can be found here: <a href=\"http://" . $server . "/info.php\">Help</a>.</p>";

print "<p>Powered by: <a href=\"https://github.com/rcappuccio/gitsvn\">GITSVN - Web Based Management of GIT and SVN repositories with LDAP, email and RSS support.</a></p>";
?>

</BODY>
</HTML>
