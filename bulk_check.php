<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
    <title>GIT/SVN Web Based Management</title>
    <link rel="stylesheet" type="text/css" href="CSS/simpletree.css">
</head>

<body>


<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$repo_id = $_POST['repo_id'];
$type = $_POST['type'];
$path = $_POST['path'];
$rights = $_POST['rights'];
$bulk = $_POST['bulk'];

$logged_user = strtolower($_SERVER['PHP_AUTH_USER']);

print "<a href=\"http://" . $server . "/index.php\"><h1>GIT/SVN Web Based Management</h1></a>";
print "<p>";

print "<b>Users to authorize: </b><br><br>";

$users = explode("\n", strtolower($bulk));

// check all specified users against LDAP

$allUsersExist = true;

foreach ($users as &$value) 
{
    $value2 = rtrim($value);

    $searchString = $value2;

    if (preg_match("/^[-[:alnum:]_\\\]+$/",$searchString) > 0) {

        $ldapClass = "person";
        #$ldapClass = "sambaSamAccount";
        $textSearch = "(cn=" . $searchString . ")";
        
        $filter = "(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";

        $ds = ldap_connect( $LDAP_SERVER_AND_PORT ); 
        if( $ds )
        {
           $r = ldap_bind( $ds );
           $sr = ldap_search( $ds, $LDAP_SEARCH_BASE, $filter );
           $info = ldap_get_entries( $ds, $sr );
        
            $status = $info[0]["status"][0];

            $result = $info["count"];
        
            if ( $result < 1)
            {
                print "<b>" . stripslashes($value2) . "</b><font color=red> does not exist</font><br>";
                $allUsersExist = false;
            }
            else
            {
                if ( $status == "active" )
                {
                    print "<b>" . stripslashes($value2) . "</b> exists<br>";
                }
                else
                {
                    print "<b>" . stripslashes($value2) . "</b> <font color=red>exists but is not active</font><br>";
                    $allUsersExist = false;
                }
            }

                    ldap_close( $ds );
           } 
           else 
           {
                    echo "<h4>Unable to connect to LDAP server</h4>";
           }
    } 
    else 
    {
            echo "<h4>Invalid search pattern</h4>";
            echo "<p>The search string &lt;" . $searchString . "&gt; is not valid. Only alphanumeric characters or - and _, without spaces, are accepted.</p>";
    
        $allUsersExist = false;
    }
}

print "<br>";

if ($allUsersExist)
{
    print "All usernames have been checked against LDAP. Press <b>Commit</b> to authorize them or <b>Back</b> to return to the previous page.<br>";
    
    //print "<form></form>";
    print "<form action=\"bulk_commit.php\" method=\"post\" accept-charset=\"utf-8\">";	
    
    print" <input type=\"button\" value=\"Back\" onClick=\"history.go(-1);return true;\">&nbsp;";
    
    print "    <input type=\"hidden\" name=\"repo_id\" value=\"" . $repo_id . "\">";
    print "    <input type=\"hidden\" name=\"path\" value=\"" . $path . "\">";
    print "    <input type=\"hidden\" name=\"rights\" value=\"" . $rights . "\">";
    print "    <input type=\"hidden\" name=\"bulk\" value=\"" . stripslashes($bulk) . "\">";
    print "    <input type=\"hidden\" name=\"type\" value=\"SVN\">";
    print "    <input type=\"submit\" value=\"Submit\">";
    print "</form>";
}
else
{
    print "Some of the specified usernames have not been validated by LDAP (either because they do not exist or they are not active).<br>Please check them and resubmit.<br>";
    print "<FORM><INPUT TYPE=\"button\" VALUE=\"Back\" onClick=\"history.go(-1);return true;\"></FORM>";
}

print "<br>";


// functions

function cleanField($value='')
{
    return (preg_match("/^none/",$value) < 1 ? $value : "");
}

function queryUrl($query,$type='user',$exact=FALSE, $repo)
{
    if ($exact) 
    {
        return $me . "?" . http_build_query(Array('query'=>$query, 'exact'=>'exact', 'type'=>$type, 'repo'=>$repo));
    } 
    else 
    {
        return $me . "?" . http_build_query(Array('query'=>$query, 'type'=>$type, 'repo'=>$repo));
    }
}

function PrintUsers($entries,$repo_id)
{
    echo "<table id=\"users\">";
    echo "<tr><th>Username</th><th>Name</th></tr>";
    for ($i=0; $i<$entries["count"]; $i++) {
        $cn = $entries[$i]["cn"][0];
        $name = cleanField($entries[$i]["givenname"][0]);
        $name .= " " . cleanField($entries[$i]["sn"][0]);
        echo "<tr>";
        echo "<td>" . $cn . "</td>";
        echo "<td>" . $name . "</td>";
        echo "<td><button type=\"button\" value=\"Add\" onclick=\"document.location='edit_authorization.php?action=add&type=user&repo=" . $repo_id . "&username=" . $cn . "'\">Authorize User</button></td>";
        echo "<td><a href=\"" . queryUrl($cn,'group',TRUE,$repo_id)  . "\">Groups containing this user</a> </td>";
            echo "</tr>";
    }
    echo "</table>";
}


?>



</body>
</html>
