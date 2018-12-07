<?php
ini_set('display_errors',1); 
error_reporting(E_ALL);

require 'settings/common.php';
require 'plugins/phpmailer/PHPMailerAutoload.php';

if( file_exists('settings/local.php') )
{
	include 'settings/local.php';
}
include 'includes/functions-lib.php';


// Emails_ON setting
define('Emails_ON', "$Emails_ON");

// Admin Details
define('Admin', "$Admin");
define('Sub_Admin', "$Sub_Admin");

 // Do not change this
 define('Webhook_Prefix', "$Webhook_Prefix"); 
 define('Latest_Webhook_Name', "$Latest_Webhook_Name");
 define('Webhook_URL', "$Webhook_URL");
 define('Webhook_Key', "$Webhook_Key");
 
 
 // Database Settings
 
 // MySQL
 define('MySQL_Host', "$MySQL_Host");
 define('MySQL_User', "$MySQL_User");
 define('MySQL_Password', "$MySQL_Password");
 define('MySQL_Database', "$MySQL_Database");
 define('MySQL_Port', " $MySQL_Port");// not needed now
 
 // PostgreSQL
 define('PostgreSQL_Host', "$PostgreSQL_Host");
 define('PostgreSQL_User', "$PostgreSQL_User");
 define('PostgreSQL_Password', "$PostgreSQL_Password");
 define('PostgreSQL_Database', "$PostgreSQL_Database");
 define('PostgreSQL_Port', "$PostgreSQL_Port");

 

 if( $Script_Enabled !== true )
 {
 	echo "**** Script is not Enabled **** ";
 	echo "Change settings to enable script";
 	exit();
 }


echo_log( "Trying db connections");

// DB Connections

// Postgres Connect
$connection_string = "host=".PostgreSQL_Host." "
        ."port=".PostgreSQL_Port." "
        ."dbname=".PostgreSQL_Database." "
        ."user=".PostgreSQL_User." "
        ."password=".PostgreSQL_Password."";

$pg_resource = pg_connect( $connection_string ) or echo_log("Could not connect to postgres db.");
if( !empty($pg_resource) )
{
    echo_log( "Postgres DB success.");
}

// MySQL Connect
$mysql_conn = mysqli_connect(MySQL_Host, MySQL_User, MySQL_Password, MySQL_Database); 
if( !empty($mysql_conn) )
{
    echo_log( "MySQL DB success.");
}

if ( empty($pg_resource) || empty($mysql_conn) )
{
    echo_log( "DB connect failed. Either MySQL or PostgreSQL");
    echo_log( "Exiting now.");
    exit();
}
