<?php
    // DATABASE
    $DATABASE_URL = "localhost";
    $DATABASE_USER = "username";
    $DATABASE_PASSWORD = "password";
    
    // ADMINISTRATIVE ACCOUNT, GROUP AND EMAIL
    $ADMIN_EMAIL = "admin.user@mydomain.net";
    $ADMIN_USER = "admin";
    $ADMIN_GROUP = "systemadmins"; // don't leave it blank !!!
    
    // LDAP
    $LDAP_SERVER_AND_PORT = "ldap://ldap.mydomain.net:389";
    $LDAP_SEARCH_BASE = "dc=mydomain,dc=net";
    $LDAP_AUTH_NAME = "Welcome to mydomain's GITSVN. Use your LDAP account to login.";
    $LDAP_CONNECTION_STRING = "ldap://ldap.mydomain.net:389/dc=mydomain,dc=net?uid?sub?(objectClass=sambaSamAccount)";

    // The following paths have to be created by root and given www-data.www-data as owner:
    $SVN_REPOSITORY_PATH = "/data/svn/repositories";
    $SVN_AUTHZ_CONF_PATH = "/var/lib/svn/repositories";
    $SVN_APACHE_CONF_PATH = "/etc/apache2/svnrepos";
    $GIT_REPOSITORY_PATH = "/data/git/repositories";
    $GIT_APACHE_CONF_PATH = "/etc/apache2/gitrepos";
?> 
