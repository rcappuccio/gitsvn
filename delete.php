<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$logged_user = strtolower($_SERVER['PHP_AUTH_USER']);

$repo_id = $_GET['repo'];

if ($repo_id) {
    // Get the repository name from the database
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if (!$con) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $result = mysql_query( "SELECT repository_name,username FROM repositories WHERE repository_ID=" . $repo_id );

    while( $row = mysql_fetch_array( $result ) )
    {
        $svnrepo = $row['repository_name'];
        $repository_owner = $row['username'];
    }
    mysql_close( $con );


    // Delete the information from the database
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );

    $sqlstr = "DELETE FROM repositories WHERE repository_ID=" . $repo_id;
    $result = mysql_query( $sqlstr );
    if ( !$result ) print $result;

    $sqlstr = "DELETE FROM rights WHERE repository_ID=" . $repo_id;
    $result = mysql_query( $sqlstr );
    if ( !$result ) print $result;
        
    mysql_close( $con );

    // Delete the repository from disk

    // the name of the repo in $svnrepo contains also svn/username so we have to get the last part only
    $pos = strrpos( $svnrepo, '/' );
    $reponame = substr( $svnrepo, -( strlen( $svnrepo ) - $pos ) + 1 );

    $pos = strpos( $svnrepo, '/' );
    $repotype = substr( $svnrepo, 0, $pos );
  
    if ( $repotype == "svn" ) 
    {
        // Remove the actual repository in /data/svn/repositories/svn/username
        $output = shell_exec ( "rm -fr " . $SVN_REPOSITORY_PATH . "/" . $path . "/" . $reponame );
        echo $output;

        // The Apache2 configuration file in /etc/apache2/svnrepos
        $output = shell_exec ( "rm " . $SVN_APACHE_CONF_PATH . "/gitsvn_" . $path  . "_" . $reponame . ".conf" );
        echo $output;

        // The SVN authz file in /data/svn/repositories
        $output = shell_exec ( "rm " . $SVN_REPOSITORY_PATH . "/gitsvn_" . $path . "_" . $reponame . "_authz.conf" );
        echo $output;       
    }
    else if ( $repotype == "git" )
    {
        // Remove the actual repository in /data/git/repositories/svn/username
        $output = shell_exec ( "rm -fr " . $GIT_REPOSITORY_PATH. "/" . $path . "/" .$reponame . ".git" );
        echo $output;
        
        // the rows relative to this repository in the acl.conf file in /data/git/repositories/username
        
    }
    
	// Reset Apache2
    $output = shell_exec ( "touch /var/www/gitsvn/commands/flag.txt" );
    echo $output;
}

// Redirec to the index page
header( "location: http://" . $server . "/index.php" ); 

?>
