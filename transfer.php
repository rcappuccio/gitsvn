<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
    <title>GIT/SVN management</title>
    <link rel="stylesheet" type="text/css" href="CSS/simpletree.css">
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
    echo "Security breach";
    die("Security breach");
}

$pos = strpos( $repository_name, '/' );
$repotype = substr( $svnrepo, 0, $pos );

print "<a href=\"http://" . $server . "/index.php\"><h1>GIT/SVN Web Based Management</h1></a>";
print "<p>";

?>
    
<div id="queryForm">
    Transfer repository <b><?php echo $repository_name ?></b> to another user.<br>
    You will lose ownership of the repository and it will be moved into the directory of the new owner.
    <br><br> 
    <form action="<?php echo $me; ?>" method="get" accept-charset="utf-8">	
        <input type="hidden" name="repo" value="<?php echo $repo_id ?>">Text to search: <input type="text" name="query" value="">
        <input type="checkbox" name="exact" value="exact"> Exact match (cn only)<br/>
        Search among: <select name="typez" size="1" disabled>
            <option value="user">users</option>
        </select>
        <input type="hidden" name="type" value="user">
    <input type="submit" value="Search">
    </form>
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

function PrintUsers($entries,$repo_id)
{
    echo "<table id=\"users\">";
    echo "<tr><th>Username</th><th>Name</th></tr>";
    for ($i=0; $i<$entries["count"]; $i++) 
    {
        $cn = $entries[$i]["cn"][0];
        $name = cleanField($entries[$i]["givenname"][0]);
        $name .= " " . cleanField($entries[$i]["sn"][0]);
        echo "<tr>";
        echo "<td>" . $cn . "</td>";
        echo "<td>" . $name . "</td>";
        echo "<td><button type=\"button\" value=\"Add\" onclick=\"document.location='commit_transfer.php?action=add&type=user&repo=" . $repo_id . "&username=" . urlencode($cn) . "'\">Transfer repository</button></td>";
        echo "</tr>";
    }
    echo "</table>";
}

if(isset($_GET['query'])) 
{
    $searchString = $_GET['query'];

    if (preg_match("/^[-[:alnum:]_\\\]+$/",$searchString) > 0) {

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

        $ds=ldap_connect( $LDAP_SERVER_AND_PORT );
        if($ds)
        {
            $r=ldap_bind($ds);
            $sr=ldap_search($ds, $LDAP_SEARCH_BASE, $filter);
            $info = ldap_get_entries($ds, $sr);

            PrintUsers($info,$repo_id);

            ldap_close($ds);
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
    }
}
?>

</div>

</body>
</html>
