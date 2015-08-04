<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];

$user_id = strtolower($_SERVER['PHP_AUTH_USER']);

if ($user_id == null) die("ERROR: user_id is null");
if ($user_id == "") die("ERROR: user_id is empty");

$path = $user_id;

$repo_id = $_GET['repo'];
$new_user_id = $_GET['username'];

// Get the name and type of the repository
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );
$result = mysql_query( "SELECT * FROM repositories WHERE repository_ID=" . $repo_id );
while( $row = mysql_fetch_array( $result ) )
{
    $repository_name = $row['repository_name'];
    $repository_type = $row['repository_type'];
    $owner = $row['username'];
}
mysql_close( $con );

if (strtolower($owner) != strtolower($_SERVER['PHP_AUTH_USER']))
{
    echo "Security breach";
    die("Security breach");
}

if ( $repository_type == "SVN" )
{
    $pos = strrpos( $repository_name, '/' );
    $reponame = substr( $repository_name, -( strlen( $repository_name ) - $pos ) + 1 );

    // delete the old authz file
    $output = shell_exec ( "rm " . $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $path . "_" . $reponame . "_authz.conf" );
    echo $output;
    
    // update the repository information in the database
    // changing the owner of the repository
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "UPDATE repositories SET `username`='" . mysql_real_escape_string($new_user_id) . "' WHERE `repository_ID`=" . $repo_id . ";";
    $result = mysql_query( $sqlstr );

    if ( !$result ) print $result;
        
    // update the repository information in the database
    // changing the path of the repository
    
    $newname = strtolower( $repository_type . "/" . $new_user_id . "/" . $reponame );
    
    $sqlstr = "UPDATE repositories SET `repository_name`='" . $newname . "' WHERE `repository_ID`=" . $repo_id . ";";
    $result = mysql_query( $sqlstr );

    if ( !$result ) print $result;
        
    mysql_close( $con );
    
    // update the rights information in the database
    // substituting the old owner with the new one in the rights table
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "UPDATE rights SET `username`='" . mysql_real_escape_string($new_user_id) . "' WHERE `repository_ID`=" . $repo_id . " AND `path`='/' AND `username`='" . $owner . "';";
    $result = mysql_query( $sqlstr );

    if ( !$result ) print $result;

    mysql_close( $con );
    
    // Write a new /data/svn/repositories/gitsvn_username_repository_authz.conf file
    $myFile = $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $new_user_id . "_" . $reponame . "_authz.conf";
    $fh = fopen( $myFile, 'w' ) or die( "can't open file" );

    $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
    
    // look if there are groups among the authorizations for the repository
    // the answer is always yes since the group $ADMIN_GROUP is ALWAYS given read/write rights to EVERY repository
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND type='group'" );

    $num_rows = mysql_num_rows($result);
    
    if ( $num_rows > 0 )
        $stringData = $stringData . "[groups]\n";
    
    $old_groupname = "";
    
    while( $row = mysql_fetch_array( $result ) )
    {
        $groupname = $row['username'];
        $pathz = $row['path'];
        $read = $row['read'];
        $write = $row['write'];

        if ( $groupname == $old_groupname )
            continue;
            
        $stringData = $stringData . $groupname . "=";
        
        // Expand LDAP Group
        $textSearch = "(| (cn=" . $groupname . ") (memberUid=" . $groupname . "))";
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
                    $stringData = $stringData . $members[$j];
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


    // create the new configuration file  
    $svnpath = $SVN_REPOSITORY_PATH . "/" . $new_user_id . "/" . $reponame;
    $oldpath = $SVN_REPOSITORY_PATH . "/" . $path . "/" . $reponame;
    
    // If the user directory does not exist, create it on the fly
    $output = shell_exec( $SVN_REPOSITORY_PATH . "/" . $new_user_id );
    echo $output;
    
    $output = shell_exec( "chown -R www-data.www-data " . $SVN_REPOSITORY_PATH . "/" . $new_user_id );
    echo $output;
    
    // Move the repository in the user directory
    $output = shell_exec( "mv " . $oldpath . " " . $svnpath );
    echo $output;

    $output = shell_exec( "chown -R www-data.www-data " . $svnpath );
    echo $output;
        
    // Remove old apache configuration file
    $output = shell_exec ( "rm " . $SVN_APACHE_CONF_PATH . "/gitsvn_" . $path . "_"  . $reponame . ".conf" );
    echo $output;
        
    // Create the Apache2 conf file for the repository so that it becomes accessible through web
    $output = shell_exec ( "touch " . $SVN_APACHE_CONF_PATH . "/gitsvn_" . $new_user_id  . "_"  . $reponame . ".conf" );
    echo $output;

    $myFile = $SVN_APACHE_CONF_PATH . "/gitsvn_" . $new_user_id . "_" . $reponame . ".conf";
    $fh = fopen( $myFile, 'w' ) or die( "can't open file" );

    $stringData = "### Created by GIT/SVN Web Based Interface on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
    $stringData = $stringData . "<Location /repositories/svn/" . $new_user_id. "/" . $reponame . ">\n";
    $stringData = $stringData . "   DAV svn\n";
    $stringData = $stringData . "   SVNPath " . $SVN_REPOSITORY_PATH . "/" . $new_user_id . "/" . $reponame . "\n";
    $stringData = $stringData . "   SVNAutoVersioning On\n";
    $stringData = $stringData . "   AuthzSVNAccessFile " . $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $new_user_id . "_" . $reponame . "_authz.conf\n";
    $stringData = $stringData . "   AuthType Basic\n";
    $stringData = $stringData . "   AuthBasicProvider ldap\n";
    $stringData = $stringData . "   AuthName \"GITSVN Auth\"\n";
    $stringData = $stringData . "   AuthLDAPURL " . $LDAP_CONNECTION_STRING . "\n";
    $stringData = $stringData . "   AuthLDAPGroupAttributeIsDN off\n";
    $stringData = $stringData . "   AuthLDAPGroupAttribute member memberUid uniqueMember\n";
    $stringData = $stringData . "   Require valid-user\n";
    $stringData = $stringData . "</Location>\n";

    fwrite($fh, $stringData);

    fclose($fh);
}

// Set the flag for the Crontab job resetting Apache2
// If the Crontab job finds the flag.txt file in the commands directory, it resets Apache2
// The job checks the directory every minute
$output = shell_exec ( "touch " . "/var/www/gitsvn/commands/flag.txt" );
echo $output;

$headerstr = "location: http://" . $server . "/index.php";

header( $headerstr );
?>
