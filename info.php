<?php if(0==0){ ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="en-GB">
<head>
<title>GIT/SVN Web Based Management</title>
    <link rel="stylesheet" type="text/css" href="CSS/simpletree.css">
</head>
<body>

<a href="index.php"><h1>GIT/SVN Web Based Management</h1></a>
<p>

<p>
<p>In order to give everyone the possibility to autonomously create and administer SVN/GIT repositories, we have created a web based interface.<br>
You can login using your LDAP credentials and you are allowed to create and administer an unlimited amount of repositories.<br>
The interface allows the administration of access permissions for every repository you create.<br>
You can also see a list of repositories, created by others, for which you have been granted access.<br>
Finally, you can give access rights also to LDAP groups.</p>
<h2>The web interface</h2>
<p>
<p>After login, you will see three lists:</p>
<p>
<p>     * The list of repositories you created</p>
<p>     * The list of repositories created by others, for which you have been granted access (at user level)</p>
<p>     * The list of repositories created by others, for which you have been granted access (at group level)</p>
<p>
<p>Every user can only administer the repositories she/he has created.<br>
Every repository resides in the directory of the user who is responsible for it, that is, its creator/owner.<br>
The general structure of the repositories is:</p>
<p>
<p>     * /repositories/svn/username/repositoryname</p>
<p>     * /repositories/git/username/repositoryname</p>
<p>
<p>Therefore, the addresses for checking out (SVN) or cloning (GIT) are:</p>
<p>
<p>     * http://<?php print $_SERVER['SERVER_NAME']?>/repositories/svn/username/repositoryname (for SVN)</p>
<p>     * http://<?php print $_SERVER['SERVER_NAME']?>/repositories/git/username/repositoryname.git (for GIT)</p>
<p>
<h2>Creation of a repository</h2>
<p>
<p>You can create an SVN/GIT repository by filling the <b>New repository</b> field, selecting the type of repository you want to create (SVN or GIT), and clicking on <b>Create</b>.<br>
<p>The repository is created immediately but you have to wait a minute for the Apache2 server to restart in order to have it working.</p>
<p>
<h2>Authorizations</h2>
<p>
<p>After the creation of the repository you can authorize users or groups to access it in read only mode or in read/write mode.<br>
This is done by clicking the <b>Authorize</b> button corresponding to the repository for which you want to give authorizations.<br>
The authorization page allows you to search for LDAP users/groups.<br>
If you search for a user, the result page will show the users matching your search criteria and also useful information about them, such as the groups of which they are part.<br>
If you search for a group, the result page will show the groups matching your search criteria and also the users belonging to each group.<br>
You can navigate through users and groups by clicking the links.<br>
When you have found the user/group you were searching for, you can edit the authorizations for that user/group.<br>
<p>For SVN, the authorization can be given for directories.<br>
If you want to grant access to the entire SVN repository, you give authorizations for the "/" directory.<br>
You can give many authorizations to each user, one for each directory of the repository. <br>
The authorizations may differ on a directory base (read only or read and write).</p>
<p>
<p>Two users/groups are AUTOMATICALLY given read/write access to each newly created repository:</p>
<p>     * the creator/owner of the repository</p>
<p>     * the $ADMIN_GROUP group</p>
<p>You cannot delete or otherwise modify those 2 authorizations, and there is no need to add authorization for the creator/owner.</p>
<p>
<h2>Transfer of ownership</h2>
<p>
<p>After creating a repository, you can transfer it to another user. <br>
The repository will be moved into the directory of the new owner. <br>
Please use this funtion carefully. You will lose ownership of the repository and it will no longer be shown in the list of your repositories.</br>
<p>The URL of the repository, to be used for checking it out, will also reflect the change.</br>
<p>For example, if user original_owner creates a repository called my_repository, it will have this URL:</p>
<p>
<p>     * https://<?php print $_SERVER['SERVER_NAME']?>/repositories/svn/original_owner/my_repository</a></p>
<p>
<p>After transferring it to user new_owner, it will have this URL:</p>
<p>
<p>     * https://<?php print $_SERVER['SERVER_NAME']?>/repositories/svn/new_owner/my_repository</a></p>
<p>
<p>In order to transfer one of your repositories, click on the <b>Transfer</b> button corresponding to it, select the user who will become the new owner and click <b>Transfer</b>.<br>
You will notice that the repository no longer appears in the list of the repositories owned by you.</p>
</BODY>
</HTML>

<?php } ?>