<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$repo_id = $_POST['repo_id'];
$user_id = $_POST['user_id'];
$type = $_POST['type'];
$path = $_POST['path'];
$rights = $_POST['rights'];
$action = $_POST['action'];

if ($action == "") $action = $_GET['action'];

$logged_user = strtolower($_SERVER['PHP_AUTH_USER']);

if ( $path == "\\" )
{
    // we leave it as it is
}
else
{
    // we remove the initial "/"
    $path = ltrim($path, '/');
}

if ( $action == "delete" ) 
{
    $repo_id = $_GET['repo_id'];
    $user_id = $_GET['username'];
    $path = $_GET['path'];

    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    #$user_id = mysql_real_escape_string($user_id);
    $sqlstr = "DELETE FROM rights WHERE repository_ID=" . $repo_id . " AND username='" . $user_id . "' AND path='" . $path . "'";

    $result = mysql_query( $sqlstr );

    if ( !$result ) print $result;

    mysql_close( $con );
}
else if ( $action == "add" )
{
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    if ( $rights == "0" )
        $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . $user_id . "', '" . $type . "', '" .$path . "', '" . "1" . "', '" . "0" . "');";
    else if ($rights == "1" )
        $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . $user_id . "', '" . $type . "', '" .$path . "', '" . "1" . "', '" . "1" . "');";
    
    $result = mysql_query( $sqlstr );

    if ( mysql_error() <> "" )
    {
        print "<font size='3' color='red'>" . mysql_error() . "</font>";
        exit;
    }      
    
    // if the path is not "/", add Read rights to the root "/" of the repository
    // if we don't do that, the user will not have the right to access the subfolder
    
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . $user_id . "', '" . $type . "', '/', '" . "1" . "', '" . "0" . "');";
    if ($type == 'SVN'){ 
            $result = mysql_query( $sqlstr );
    }
    if ( mysql_error() <> "" )
    {
        //print "<font size='3' color='red'>" . mysql_error() . "</font>";
        //exit;
    }      
        
} 
else if ( $action == "edit" ) 
{
    // get the repository name and type
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM repositories WHERE repository_ID=" . $repo_id );
    while( $row = mysql_fetch_array( $result ) )
    {
        $repo = $row['repository_name'];
        $repository_type = $row['repository_type'];
    }
    mysql_close( $con );

    if ( $rights == "0" )
    {
        $read = "1";
        $write = "0";
    }
    else if ( $rights == "1" )
    {
        $read = "1";
        $write = "1";
    }
    
    // Update authorization information in the database
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con )
        die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "UPDATE rights SET `read`='" . $read . "', `write`='" . $write . "', `path`='" . $path . "' WHERE `repository_ID`=" . $repo_id . " AND `username`='" . $user_id . "' AND `path`='" . $path . "';";

    $result = mysql_query( $sqlstr );

    if ( !$result )
        print $result;

    mysql_close( $con );
    
    
    // if the path is not "/", add Read rights to the root "/" of the repository
    // if we don't do that, the user will not have the right to access the subfolder
    
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . $user_id . "', '" . $type . "', '/', '" . "1" . "', '" . "0" . "');";
    if ($type == 'SVN'){ 
        $result = mysql_query( $sqlstr );
    }
    if ( mysql_error() <> "" )
    {
        //print "<font size='3' color='red'>" . mysql_error() . "</font>";
        //exit;
    }    
 }
 
 // update the information about the group in the GROUPS table
 if ( $type == "group" )
 {
    // expand the LDAP group
    $users = "";
    
    $groupname = $user_id;
    
    $textSearch = "(| (cn=" . addslashes($groupname) . ") (memberUid=" . addslashes($groupname) . "))";
    $ldapClass="posixGroup";
    $filter="(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";
    
    $ds=ldap_connect( $LDAP_SERVER_AND_PORT );
    if ( $ds )
    {
        $r = ldap_bind( $ds );
        $sr = ldap_search( $ds, $LDAP_SEARCH_BASE, $filter );
        $entries = ldap_get_entries( $ds, $sr );

        for ($i=0; $i<$entries["count"]; $i++) 
        {
            $cn = $entries[$i]["cn"][0];
            $members = $entries[$i]["memberuid"];

            for ($j=0; $j < $members["count"]; $j++) { 
                $users = $users . $members[$j] . " ";
            }
        }
    }
    ldap_close($ds);
    
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con )
        die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    
    $sqlstr = "DELETE FROM groups WHERE group=" . $user_id;
    $result = mysql_query( $sqlstr );

    if ( !$result )
        print $result;
    
    $sqlstr = "INSERT INTO `groups` (`group`, `users`) VALUES ('" . $user_id . "', '" . $users . "')";
         
    $result = mysql_query( $sqlstr );

    if ( !$result )
        print $result;
    
    mysql_close( $con );
}
 
// Get the name and type of the repository
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con )
	die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );
$result = mysql_query( "SELECT * FROM repositories WHERE repository_ID=" . $repo_id );
while( $row = mysql_fetch_array( $result ) )
{
    $repository_name = $row['repository_name'];
    $repository_type = $row['repository_type'];
}
mysql_close( $con );

if ( $repository_type == "SVN" )
{
    // Update /var/lib/svn/repositories/gitsvn_username_repository_authz.conf file
    // The file has to be completely rewritten every time the authorizations for the repository are modified
    $pos = strrpos( $repository_name, '/' );
    $reponame = substr( $repository_name, -( strlen( $repository_name ) - $pos ) + 1 );
    
    $myFile = $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $path . "_" . $reponame . "_authz.conf";
    $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile);

    $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
    
    // look if there are groups among the authorizations for the repository
    // the answer is always yes since the group $ADMIN_GROUP is ALWAYS given read/write rights to EVERY repository
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con )
        die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND type='group'" );

    $num_rows = mysql_num_rows($result);
    
    if ( $num_rows > 0 )
        $stringData = $stringData . "[groups]\n";
    
    $old_groupname = "";
    
    while( $row = mysql_fetch_array( $result ) )
    {
        $groupname = $row['username'];
        $path = $row['path'];
        $read = $row['read'];
        $write = $row['write'];

        if ( $groupname == $old_groupname )
            continue;
            
        $stringData = $stringData . $groupname . "=";
        
        // Expand LDAP Group
        $textSearch = "(| (cn=" . addslashes($groupname) . ") (memberUid=" . addslashes($groupname) . "))";
        $ldapClass="posixGroup";
        $filter="(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";
        $ds=ldap_connect( $LDAP_SERVER_AND_PORT );
        if ( $ds )
        {
            $r = ldap_bind( $ds );
            $sr = ldap_search( $ds, $LDAP_SEARCH_BASE, $filter );
            $entries = ldap_get_entries( $ds, $sr );

            for ($i=0; $i<$entries["count"]; $i++) 
            {
                $cn = $entries[$i]["cn"][0];
                $members = $entries[$i]["memberuid"];
 
                for ($j=0; $j < $members["count"]; $j++) {
                    $stringData = $stringData . strtolower($members[$j]);
                    if ( $j < $members["count"]-1 )
                        $stringData = $stringData . ", ";
                }
            }
            $stringData = $stringData . "\n";
        }
        ldap_close($ds);
     
        $old_groupname = $groupname;
    }
    mysql_close( $con );
    
    // Special case '/' which has to be always present
    $stringData = $stringData . "[" . $reponame . ":/]\n";
    //$stringData = $stringData . $logged_user . " = rw\n";
    
    $foundStar = false;
    // Cycle through all authorization for '/' and write them in the file
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND path='/' ORDER BY path" );
    while( $row = mysql_fetch_array( $result ) )
    {
        $username = $row['username'];
        $type = $row['type'];
        
        if ( $username == "*" )
            $foundStar = true;
                
        if ( $row['read'] == "1" )
        {
            if ( $row['write'] == "1" )
            {
                if ( $type == "user" )
                    $stringData = $stringData . $username . " = rw\n";
                else if ( $type == "group" )
                    $stringData = $stringData . "@" . $username . " = rw\n";
            }
            else if ( $row['write'] == "0" )
            {
                if ( $type == "user" )
                    $stringData = $stringData . $username . " = r\n";
                else if ( $type == "group" )
                    $stringData = $stringData . "@" . $username . " = r\n";
            }
        }
    }

    if ( $foundStar == false ) 
        $stringData = $stringData . "* =\n";
    
    mysql_close( $con );

    // Cycle through all authorization for directories other than '/' and write them in the file
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND path <> '/' ORDER BY path" );
    
    $lastpath = ".";
    while( $row = mysql_fetch_array( $result ) )
    {
        if ( $lastpath != $row['path'] )
        {
            // path header
            $stringData = $stringData . "[" . $reponame . ":" . $row['path'] . "]\n";
            $stringData = $stringData . "* =\n";
            $stringData = $stringData . $ADMIN_GROUP . " = rw\n";
            $stringData = $stringData . $logged_user . " = rw\n";
        }
    
        $username = $row['username'];
        $type = $row['type'];
        
        if ( $row['read'] == "1" )
        {
            if ( $row['write'] == "1" )
            {
                if ( $type == "user" )
                    $stringData = $stringData . $username . " = rw\n";
                else if ( $type == "group" )
                    $stringData = $stringData . "@" . $username . " = rw\n";
            }
            else if ( $row['write'] == "0" )
            {
                if ( $type == "user" )
                    $stringData = $stringData . $username . " = r\n";
                else if ( $type == "group" )
                    $stringData = $stringData . "@" . $username . " = r\n";
            }
        }
            
        $lastpath = $row['path'];
    }
    mysql_close( $con );
    
    fwrite( $fh, $stringData );
    fclose( $fh );
}
else if ( $repository_type == "GIT" )
{
    // Update /data/git/repositories/USERNAME/acl.conf file
    // The file has to be completely rewritten every time the authorizations for the repository are modified
    
    // this file is read by git_http_backend python script installed by the gitweb package in /usr/lib/git-core
    // each .conf file in /etc/apache2/gitrepos/ defines an alias:
    // ScriptAlias /repositories/git/USERNAME /usr/lib/git-core/git-http-backend/
    
    $path = $logged_user;
    
    $myFile = $GIT_REPOSITORY_PATH . "/" . $path . "/acl.conf";
    $fh = fopen( $myFile, 'w' ) or die( "can't open file: " . $myFile );

    $stringData = "### Created by GIT/SVN Web Based Managemente on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
    
    // Special case '/' which has to be always present
    $stringData = $stringData . "/\t" . $logged_user . "\t2\n";
    
    // Cycle through all repositories of current user
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $qs = "SELECT *, rights.username AS name FROM rights INNER JOIN repositories ON rights.repository_ID = repositories.repository_ID WHERE repositories.username = '" . mysql_real_escape_string($logged_user) . "' AND repository_type = 'GIT' AND rights.type = 'user'";
    $result = mysql_query( $qs );

    $old_repo_id=''; 
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

        $repo_id = $row['repository_ID']; 
        if ($repo_id != $old_repo_id) {
        $old_repo_id = $repo_id;
        
        $resultg = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND type='group'" );

        $num_rowsg = mysql_num_rows($resultg);

        if ( $num_rowsg > 0 )
            $stringData = $stringData . "### from groups\n";

        while( $rowg = mysql_fetch_array( $resultg ) )
        {
            $groupname = $rowg['username'];
            $path = $rowg['path'];
            $read = $rowg['read'];
            $write = $rowg['write'];
            // Expand LDAP Group
            $textSearch = "(| (cn=" . addslashes($groupname) . ") (memberUid=" . addslashes($groupname) . "))";
            $ldapClass="posixGroup";
            $filter="(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";
           
            $ds=ldap_connect( $LDAP_SERVER_AND_PORT );
            if ( $ds )
            {
                $r = ldap_bind( $ds );
                $sr = ldap_search( $ds, $LDAP_SEARCH_BASE, $filter );
                $entries = ldap_get_entries( $ds, $sr );

                for ($i=0; $i<$entries["count"]; $i++) 
                {
                    $cn = $entries[$i]["cn"][0];
                    $members = $entries[$i]["memberuid"];

                    for ($j=0; $j < $members["count"]; $j++) 
                    {
                        $stringData = $stringData . "/" . $reponame . "\t" . strtolower($members[$j]) . "\t";
                        if ( $rowg['write'] == "1" )
                        {
                            $stringData = $stringData . "2\n";
                        } 
                        else 
                        {
                            $stringData = $stringData . "1\n";
                        }
                    }
                }
                ldap_close($ds);        
            }
        }


        }
    }
    mysql_close( $con );

    ### add group handling 


    fwrite( $fh, $stringData );
    fclose( $fh );
}

$headerstr = "location: http://" . $server . "/authorize.php?repo=" . $repo_id;

header( $headerstr );
?>
