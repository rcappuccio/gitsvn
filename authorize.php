<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
    <title>GIT/SVN management</title>
    <link rel="stylesheet" type="text/css" href="CSS/simpletree.css">
    
    <script type="text/javascript">
    <!--
    function confirmation( username, repo_id, path ) 
    {
            var answer = confirm( "Delete the authorization of user " + username + " for " + path + "directory?" )
            if ( answer )
            {
                    alert( "Specified authorization will be deleted from selected repository and directory." );
                    parent.location = "commit_authorization.php?action=delete&username=" + encodeURIComponent(username) + "&repo_id=" + repo_id + "&path=" + path;
            }
            else{
                    alert( "No action taken" );
            }
    }
    //-->
    </script>
</head>

<body>
	
<?php
include 'config.php';

$server=$_SERVER['SERVER_NAME'];
$me = $_SERVER['PHP_SELF']; 
    
$repo_id = $_GET['repo'];
$user_id = $_POST['username'];
$path = $_POST['path'];

// Get the name and type of the repository
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );
$result = mysql_query( "SELECT * FROM repositories WHERE repository_ID=" . $repo_id );
while( $row = mysql_fetch_array( $result ) )
{
    $repository_name = $row['repository_name'];
    $repository_type = $row['repository_type'];
    $repository_owner = $row['username'];
}
mysql_close( $con );


if (strtolower($repository_owner) != strtolower($_SERVER['PHP_AUTH_USER']))
{
    echo "ERROR: Security breach";
    die("ERROR: Security breach");
}

$pos = strpos( $repository_name, '/' );
$repotype = substr( $svnrepo, 0, $pos );

print "<a href=\"http://" . $server . "/index.php\"><h1>GIT/SVN Web Based Management</h1></a>";
print "<p>";


print "Current users/groups rights for repository ";
print "<b>" . $repository_name . "</b>";
print "  <button type='button' onClick=\"parent.location='index.php'\">Back</button>";
print "<br><br>";    

// Print the list of current authorizations for the repository
$con = mysql_connect( $DATABASE_URL, $DATABASE_USER, $DATABASE_PASSWORD );
if ( !$con ) die( 'Could not connect: ' . mysql_error() );

mysql_select_db( $DATABASE_USER, $con );

$result = mysql_query( "SELECT rights.*, repositories.username as owner FROM rights inner join repositories on rights.repository_ID=repositories.repository_ID WHERE repositories.repository_ID = " . $repo_id );

print "<table style='font-family:verdana; font-size:11'>";

print "<tr>";
print "<td><b>Name</b></td>";
print "<td><b>Type</b></td>";
if ( $repository_type == "SVN" )
    print "<td><b>Directory</b></td>";
print "<td><b>Rights</b></td>";
print "</tr>";

while( $row = mysql_fetch_array( $result ) )
{
    print "<tr>\n";

    print "<td>" . $row['username'] . "</td>\n";
    print "<td>" . $row['type'] . "</td>\n";
    
    if ( $repository_type == "SVN" )
        print "<td><input type='text' name='path' value='" . $row['path'] . "' disabled></input></td>\n";
    
    print "<td><select name=\"rights\" disabled>";

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

    print "</select>";
    print "<td></td>\n";

    print "<td>";
    
    if ( $row['username'] == $row['owner'] || $row['username'] == $ADMIN_GROUP )
    {
        print "<button disabled type='button' onClick=\"document.location='edit_authorization.php?action=edit&type=user&repo=" . $repo_id . "&username=" . urlencode($row['username']) . "&path=" . $row['path'] . "'\">Edit</button> ";
        print "<button disabled type='button' onClick=\"confirmation('" . mysql_real_escape_string( $row['username'] ) . "', '" . $repo_id . "', '" . $row['path'] . "')\">Delete</button>";	
    }
    else
    {
        print "<button type='button' onClick=\"document.location='edit_authorization.php?action=edit&type=user&repo=" . $repo_id . "&username=" . urlencode($row['username']) . "&path=" . $row['path'] . "'\">Edit</button> ";
        print "<button type='button' onClick=\"confirmation('" . mysql_real_escape_string ($row['username']) . "', '" . $repo_id . "', '" . $row['path'] . "')\">Delete</button>";	
    }
    
    
    print "</td>\n";

    //print "</form>\n";
    print "</tr>\n";
}

print "</table>";

mysql_close($con);

print "<br><br>\n";
print "The creator/owner of repository has already <b>Read/Write</b> rights attached to it, so it is not necessary to add rights for that user.\n";
print "<br><br>\n";

print "<hr>";
?>
    
<div id="queryForm">
    Single user/group authorization to access repository <b><?php echo $repository_name ?></b>:
    <br><br> 
        <form action="<?php echo $me; ?>" method="get" accept-charset="utf-8">	
            <input type="hidden" name="repo" value="<?php echo $repo_id ?>">
            Text to search: <input type="text" name="query" value="">
            <input type="checkbox" name="exact" value="exact"> Exact match (cn only)<br/>
            Search among: <select name="type" size="1">
                <option value="user">users</option>
                <option value="group">groups</option>			
            </select>
            <input type="submit" value="Search">
        </form>
    <br><br>
    Actualization of SVN's authorization files containing groups is done periodically in order to reflect the possibly modified composition of the groups.
    <br><br>
</div>

<div id="results">

<?php
function cleanField($value='')
{
    return (preg_match("/^none/",$value) < 1 ? $value : "");
}

function queryUrl($query,$type='user',$exact=FALSE, $repo)
{
    if ($exact) {
        return $me . "?" . http_build_query(Array('query'=>$query, 'exact'=>'exact', 'type'=>$type, 'repo'=>$repo));
    } else {
        return $me . "?" . http_build_query(Array('query'=>$query, 'type'=>$type, 'repo'=>$repo));
    }
}

function PrintGroups($entries,$repo_id)
{
    echo "<table id=\"groups\">";
    echo "<tr><th>Groupname</th><th>&nbsp;</th><th>Members</th></tr>";
    for ($i=0; $i<$entries["count"]; $i++) {
        $cn = $entries[$i]["cn"][0];
        $members = $entries[$i]["memberuid"];
        echo "<tr valign=\"top\">";
        echo "<td>" . strtolower($cn) . "</td>";
        echo "<td><button type=\"button\" value=\"Add\" onclick=\"document.location='edit_authorization.php?action=add&type=group&repo=" . $repo_id . "&username=" . strtolower(urlencode($cn)) . "'\">Authorize Group</button></td>";;
        echo "<td>";
        for ($j=0; $j < $members["count"]; $j++) { 
                echo "<a href=\"" . queryUrl($members[$j],'user',TRUE,$repo_id) . "\">" . strtolower($members[$j]) .  "</a> ";
        }
        echo "</td>";
        // print_r($entries[$i]);
        echo "</tr>";
    }
    echo "</table>";
}

function PrintUsers($entries,$repo_id)
{
    echo "<table id=\"users\">";
    echo "<tr><th>Username</th><th>Name</th></tr>";
    for ($i=0; $i<$entries["count"]; $i++) 
    {
        $cn = $entries[$i]["cn"][0];
        //$status = $entries[$i]["status"][0];
        $name = cleanField($entries[$i]["givenname"][0]);
        $name .= " " . cleanField($entries[$i]["sn"][0]);
        echo "<tr>";
        echo "<td>" .strtolower( $cn ) . "</td>";
        echo "<td>" . $name . "</td>";
        echo "<td>" . $status . "</td>";
        //if ( $status == "active" )
            echo "<td><button type=\"button\" value=\"Add\" onclick=\"document.location='edit_authorization.php?action=add&type=user&repo=" . $repo_id . "&username=" . strtolower(urlencode($cn)) . "'\">Authorize User</button></td>";
        //else
        //    echo "<td><button type=\"button\" value=\"Add\" onclick=\"document.location='edit_authorization.php?action=add&type=user&repo=" . $repo_id . "&username=" . strtolower(urlencode($cn)) . "'\" disabled=\"disabled\">Authorize User</button></td>";
            
        echo "<td><a href=\"" . queryUrl($cn,'group',TRUE,$repo_id)  . "\">Groups containing this user</a> </td>";
        echo "</tr>";
    }
    echo "</table>";
}




if(isset($_GET['query'])) {

    $searchString = $_GET['query'];

    if (preg_match("/^[-[:alnum:]_\\\]+$/",$searchString) > 0) 
    {
        if($_GET['type']=="user") {
                $ldapClass="person";
                $textSearch = ($_GET['exact']) ?
                        "(cn=" . $searchString . ")" :
                        "(| (cn=*" . $searchString . "*) (sn=*" . $searchString . "*) (givenName=*" . $searchString . "*))";
        } else {
                $ldapClass="posixGroup";
                $textSearch = ($_GET['exact']) ?
                        "(| (cn=" . $searchString . ") (memberUid=" . $searchString . "))" :
                        "(| (cn=*" . $searchString . "*) (memberUid=*" . $searchString . "*))";
        }

        $filter="(& (objectClass=" . $ldapClass . ") " . $textSearch . ")";
        
        $ds = ldap_connect( $LDAP_SERVER_AND_PORT );
        if( $ds )
        {
            $r=ldap_bind($ds);
            $sr=ldap_search($ds, $LDAP_SEARCH_BASE, $filter);
            $info = ldap_get_entries($ds, $sr);

            if($_GET['type']=="user") {
                PrintUsers($info,$repo_id);
            } else {
                PrintGroups($info,$repo_id);
            }

            ldap_close($ds);
        } else {      
            echo "<h4>Unable to connect to LDAP server</h4>";
        }
    } else {
        echo "<h4>Invalid search pattern</h4>";
        echo "<p>The search string &lt;" . $searchString . "&gt; is not valid. Only alphanumeric characters or - and _, without spaces, are accepted.</p>";
        
        die ("invalid search pattern");
    }
}
?>

</div>

<?php        
if ( $repository_type == "SVN" )
{
?>
    <hr>

    <div id="bulkForm">
        Multiple user authorization for repository <b><?php echo $repository_name ?></b>:
        <br>
        <form action="bulk_check.php" method="post" accept-charset="utf-8">	

            <input type="hidden" name="repo_id" value="<?php echo $repo_id ?>">
            <input type="hidden" name="type" value="SVN">
            
            <br><b>Users to be authorized:</b><br>(one username per row, press Enter after each username)<br>
            <textarea name="bulk" value="" cols="40" rows="10"></textarea>
        
            <br><b>Directory</b><br>
            <input name="path" value="/" size=30>
            <br><b>Rights</b><br>
            <td><select name="rights">
                <option value="0" selected>Read</option>
                <option value="1">Read/Write</option>
            </select>

            <p>&nbsp;</p>
            <p>By default, the root of the repository (directory /) is not accessible to anyone other than the owner/creator of the repository.</p>
            <p>If you want to give read or write access to the root of the repository to <b>everyone</b>, please enter username '*' and directory '/' and assign this special user the rights you consider appropriate for this repository.</p>  
        
            <input type="submit" value="Authorize specified users">
        </form>
    <br><br>
    
    </div>
<?php
}
?>

</body>
</html>
