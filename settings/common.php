<?php

// Script_Enabled 
$Script_Enabled = true;
$Emails_ON = true;

// Admin
$Admin = "ADMIN_EMAIL_ADDRESS";
$Sub_Admin = "SUB_ADMIN_EMAIL";

// Webhook settings
$Webhook_Prefix = 'sntwebhook_';
$Latest_Webhook_Name = 'v2';
$Webhook_URL ='http://localhost/snttaiga/taiga-hooks/orangehrmhooks.php';
$Webhook_Key ='WEBHOOK_KEY_FROM_TAIGA';
 
 
 // Database Settings
 
 // MySQL - ohrm database
 $MySQL_Host = 'localhost';
 $MySQL_User = 'ORANGEHRM_DB_USERNAME';
 $MySQL_Password = 'ORANGEHRM_DB_PASSWORD';
 $MySQL_Database = 'ORANGEHRM_DB_';
 $MySQL_Port = '';// not needed now
 

 // PostgreSQL - taiga database
 $PostgreSQL_Host = 'localhost';
 $PostgreSQL_User = 'TAIGA_DB_USERNAME';
 $PostgreSQL_Password = 'TAIGA_DB_PASSWORD';
 $PostgreSQL_Database = 'TAIGA_DB_NAME';
 $PostgreSQL_Port = 5432;