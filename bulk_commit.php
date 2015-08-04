<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$repo_id = $_POST['repo_id'];
$repository_type = $_POST['type'];
$path = $_POST['path'];
$rights = $_POST['rights'];
$bulk = stripslashes($_POST['bulk']);

$logged_user = strtolower($_SERVER['PHP_AUTH_USER']);


$users = explode("\n", $bulk);

$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );

$result = mysql_query( "SELECT * FROM repositories WHERE repository_ID=" . $repo_id );
while( $row = mysql_fetch_array( $result ) )
{
    $repository_owner = $row['username'];
}

if (strtolower($repository_owner) != strtolower($_SERVER['PHP_AUTH_USER']))
{
    echo "ERROR: Security breach";
    die("ERROR: Security breach");
}

$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

foreach ($users as &$value) 
{
    $user_id = rtrim($value);

    $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . mysql_real_escape_string($repository_owner) . "', '" . "user" . "', '" .$path . "', '" . "1" . "', '" . "1" . "');";
    $result = mysql_query( $sqlstr );
    
    if ( $rights == "0" )
        $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . mysql_real_escape_string($user_id) . "', '" . "user" . "', '" .$path . "', '" . "1" . "', '" . "0" . "');";
    else if ($rights == "1" )
        $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . mysql_real_escape_string($user_id) . "', '" . "user" . "', '" .$path . "', '" . "1" . "', '" . "1" . "');";
    
    $result = mysql_query( $sqlstr );

    if ( mysql_error() <> "" )
    {
        print "<font size='3' color='red'>[1] " . mysql_error() . "</font>";
        exit;
    }      

    if ( $path <> "/" )
    {    
        // if the path is not "/", add Read rights to the root "/" of the repository
        // if we don't do that, the user will not have the right to access the subfolder
        
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con ) die( 'Could not connect: ' . mysql_error() );

        mysql_select_db( $DATABASE_USER, $con );

        $sqlstr = "INSERT INTO `rights` (`repository_ID`, `username`, `type`, `path`, `read`, `write`) VALUES ('" . $repo_id . "', '" . mysql_real_escape_string($user_id) . "', '" . "user" . "', '/', '" . "1" . "', '" . "0" . "');";
        
        $result = mysql_query( $sqlstr );

        if ( mysql_error() <> "" )
        {
            ### if it's a duplicate entry, please proceed with the execution###
            $errormy =  mysql_error();
            if ( strpos($errormy,'Duplicate entry') !==false )
            {
            } 
            else 
            {
                print "<font size='3' color='red'>[2] " . mysql_error() . "</font>";
                exit;
            }
        }    
    }
     
}

// Get the name and type of the repository
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

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
        $path = $row['path'];
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
}


$headerstr = "location: http://" . $server . "/authorize.php?repo=" . $repo_id;

header( $headerstr );

?>
