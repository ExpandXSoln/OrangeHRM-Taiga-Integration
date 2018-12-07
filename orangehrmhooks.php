<?php
/***
* Orangehrm hook process script
*
*
*
*
*
*
*/
try{

	include 'init.php';

	$json_input = file_get_contents('php://input');
	$json_array = json_decode($json_input, true);

	process_userstory_hook($json_array, $mysql_conn);

}catch(Exception $e)
{
    $subject = "Unable to execute ohrm webhook, Check Error.";
    $message = "I was trying to execute the SYNC script(WEBHOOK). Please Check Following Error, "."<br/>";
    $message .= "".$e->getMessage()." "."<br/>"."<br/>";
    $message .= "Kindly do the needful."."<br/>";
    send_email_to_admin($subject, $message);
}