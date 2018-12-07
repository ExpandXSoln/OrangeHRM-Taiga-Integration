<?php
echo "********** ON LIVE SERVER ************";
// Script_Enabled 
$Script_Enabled = true;

// Webhook settings
$Webhook_Prefix = 'sntwebhook_';
$Latest_Webhook_Name = 'v7';
$Webhook_URL ='http://localhost/snt-taiga-hooks/orangehrmhooks.php';
 
 
 // Database Settings
 
 // MySQL - ohrm database
 $MySQL_Host = '127.0.0.1';
 $MySQL_User = 'ORANGEHRM_DB_USERNAMR';
 $MySQL_Password = 'ORANGEHRM_DB_PASSWORD';
 $MySQL_Database = 'ORANGEHRM_DB_NAME';
 
 

 // PostgreSQL - taiga database
 $PostgreSQL_Host = '127.0.0.1';
 $PostgreSQL_User = 'TAIGA_DB_USERNAME';
 $PostgreSQL_Password = 'TAIGA_DB_PASSWORD';
 $PostgreSQL_Database = 'TAIGA_DB_NAME';
 
