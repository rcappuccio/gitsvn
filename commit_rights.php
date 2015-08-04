<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$user_id = strtolower($_SERVER['PHP_AUTH_USER']);
$repo_id = $_GET['repo_id'];
$username = $_GET['username'];
$path = $_GET['path'];
$rights = $_GET['rights'];

if ( $repo_id ) 
{
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
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );
    
    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "UPDATE rights SET `read`='" . $read . "', `write`='" . $write . "', `path`='" . $path . "' WHERE `repository_ID`=" . $repo_id . " AND `username`='" . $username . "' AND `path`='" . $path . "';";

    $result = mysql_query( $sqlstr );

    if ( !$result )
        print $result;

    mysql_close( $con );
    
    if ( $repository_type == "SVN" )
    {
        // Update /var/lib/svn/repositories/gitsvn_username_repository_authz.conf file
        // The file has to be completely rewritten every time the authorizations for the repository are modified
        $pos = strrpos( $repo, '/' );
        $reponame = substr( $repo, -( strlen( $repo ) - $pos ) + 1 );
        
        $myFile = $SVN_AUTHZ_CONF_PATH . "/gitsvn_" . $user_id . "_" . $reponame . "_authz.conf";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file" );

        $stringData = "### Created by GIT/SVN Web Based Management on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        
        // Special case '/' which has to be always present
        $stringData = $stringData . "[" . $reponame . ":/]\n";
        $stringData = $stringData . $user_id . " = rw\n";

        $foundStar = false;
        // Cycle through all authorization for '/' and write them in the file
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con )
            die( 'Could not connect: ' . mysql_error() );

        mysql_select_db( $DATABASE_USER, $con );
        $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND path='/' ORDER BY path" );
        while( $row = mysql_fetch_array( $result ) )
        {
            $username = $row['username'];
            
            if ( $username == "*" )
                $foundStar = true;
                    
            if ( $row['read'] == "1" )
            {
                if ( $row['write'] == "1" )
                    $stringData = $stringData . $username . " = rw\n";
                else
                    $stringData = $stringData . $username . " = r\n";
            }
        }

        if ( $foundStar == false ) 
            $stringData = $stringData . "* =\n";
        
        mysql_close( $con );

        // Cycle through all authorization for directories other than '/' and write them in the file
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con )
            die( 'Could not connect: ' . mysql_error() );

        mysql_select_db( $DATABASE_USER, $con );
        $result = mysql_query( "SELECT * FROM rights WHERE repository_ID=" . $repo_id . " AND path <> '/' ORDER BY path" );
        
        $lastpath = "/";
        while( $row = mysql_fetch_array( $result ) )
        {
            if ( $lastpath != $row['path'] )
            {
                // path header
                $stringData = $stringData . "[" . $reponame . ":" . $row['path'] . "]\n";
                $stringData = $stringData . "* =\n";
            }
        
            $username = $row['username'];
            
            if ( $row['read'] == "1" )
            {
                if ( $row['write'] == "1" )
                    $stringData = $stringData . $username . " = rw\n";
                else
                    $stringData = $stringData . $username . " = r\n";
            }
                
            $lastpath = $row['path'];
        }
        mysql_close( $con );
        
        fwrite( $fh, $stringData );
        fclose( $fh );
    }
    else if ( $repository_type == "GIT" )
    {
        // Update /data/git/repositories/username/acl.conf file
        // The file has to be completely rewritten every time the authorizations for the repository are modified
        $myFile = $GIT_REPOSITORY_PATH . "/" . $path . "/acl.conf";
        $fh = fopen( $myFile, 'w' ) or die( "can't open file" );

        $stringData = "### Created by GIT/SVN Web Based Interface on " . date('l jS \of F Y h:i:s A') . " ###\n\n";
        
        // Special case '/' which has to be always present
        $stringData = $stringData . "/\t" . $user_id . "\t2\n";
        
        // Cycle through all repositories of current user
        $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
        if ( !$con ) die( 'Could not connect: ' . mysql_error() );
        
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
        mysql_close( $con );
        fwrite( $fh, $stringData );
        fclose( $fh );
    }
}

$headerstr = "location: http://" . $server . "/auth.php?repo=" . $repo_id;

header( $headerstr );
?>
