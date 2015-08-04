<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
    <title>GIT/SVN management</title>
    <script type="text/javascript" src="scripts/simpletreemenu.js"></script>
    
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
    
   
    <script type="text/javascript">
    <!--
    function confirmation( username, repo_id, path ) 
    {
            var answer = confirm( "Delete the authorization of user " + username + " for " + path + "directory?" )
            if ( answer )
            {
                    alert( "Specified authorization will be deleted from selected repository and directory." );
                    parent.location = "delete_authorization.php?username=" + username + "&repo_id=" + repo_id + "&path=" + path;
            }
            else{
                    alert( "No action taken" );
            }
    }
    //-->
    </script>
</head>

<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$repo_id = $_GET['repo'];
$user_id = $_GET['username'];
$action = $_GET['action'];
$path = $_GET['path'];
$type = $_GET['type'];

$user_id = stripslashes($user_id);

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

$pos = strpos( $repository_name, '/' );
$repotype = substr( $svnrepo, 0, $pos );

print "<a href=\"http://" . $server . "/index.php\"><h1>GIT/SVN Web Based Management</h1></a>";
print "<p>";

if ( $action == "add" )
{
    if ( $type == "user" )
        print  "Add authorization for user <b>" . $user_id . "</b> to access repository <b>" . $repository_name . "</b>";
    else if ( $type == "group" )
        print  "Add authorization for group <b>" . $user_id . "</b> to access repository <b>" . $repository_name . "</b>";
}
else
{
    if ( $type == "user" )
        print  "Edit authorization for user <b>" . $user_id . "</b> to access for repository <b>" . $repository_name . "</b>";
    else if ( $type == "group" )
        print  "Edit authorization for group <b>" . $user_id . "</b> to access for repository <b>" . $repository_name . "</b>";
}
print "  <button type='button' onClick=\"parent.location='index.php'\">Back</button>";
print "<br><br>";    

print "<form action='commit_authorization.php' method='post' name='selection'>";

print "<input type='hidden' name='repo_id' value='" . $repo_id . "'>";
print "<input type='hidden' name='user_id' value='" . $user_id . "'>";
print "<input type='hidden' name='type' value='" . $type . "'>";
print "<input type='hidden' name='action' value='" . $action . "'>";

if ( $action == "edit" )
{
    print "<input type='hidden' name='path' value='" . $path . "'>";
}

if ( $type == "user" )
    print "Username <input name='username' size=30 value='" . $user_id . "' disabled> ";
else if ( $type == "group" )
    print "Group <input name='username' size=30 value='" . $user_id . "' disabled> ";

if ( $repository_type == "SVN" )
    if ( $action == "add" ) // add
        print "Directory <input name='path' value='/' size=30> ";
    else // edit
        print "Directory <input name='fixedpath' value='" . $path . "' size=30 disabled> ";
    
print "<td><select name=\"rights\">";

if ( $action == "edit" )
{
    // read the previously saved authorization values
    $con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
    if ( !$con ) die( 'Could not connect: ' . mysql_error() );

    mysql_select_db( $DATABASE_USER, $con );
    $result = mysql_query( "SELECT * FROM rights WHERE `repository_ID` = " . $repo_id . " AND `username` = '" . mysql_real_escape_string ( $user_id ) . "' AND `path` = '" . $path . "'" );
    
    while( $row = mysql_fetch_array( $result ) )
    {
        if ( $row['read'] == "1" and $row['write'] == "1" )
        {
            print "<option value=\"0\">Read</option>\n";
            print "<option value=\"1\" selected>Read/Write</option>\n";
        }
        else
        {
            print "<option value=\"0\" selected>Read</option>\n";
            print "<option value=\"1\">Read/Write</option>\n";
        } 
    }
}
else if ( $action == "add" )
{
    print "<option value=\"0\" selected>Read</option>\n";
    print "<option value=\"1\">Read/Write</option>\n";
}

print "</select>";
print "<td></td>\n";    

print "<button type='submit'>Commit</button>";
print "</form>";

if ( $repository_type == "SVN" )
{
    print "<p>&nbsp;</p>\n";
    print "<p>By default, the root of the repository (directory /) is not accessible to anyone other than the owner/creator of the repository.</p>\n";
    print "<p>If you want to give read or write access to the root of the repository to <b>everyone</b>, please enter username '*' and directory '/' and assign this special user the rights you consider appropriate for this repository.</p>\n";
}
?>
</BODY>
</HTML>
