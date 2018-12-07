<?php
/**
 * Sync manager script
 * 
 * Syncs taiga data with other applications 
 * currently it is being used for orange hrm only.
 *
 */

try{    
 
    include 'init.php';
    
    echo_log("--------------------------------------------------------------------");
    echo_log("Update incomplete punch outs - Start");
    echo_log("--------------------------------------------------------------------"); 
    update_punchout_times($mysql_conn);
    echo_log("--------------------------------------------------------------------");
    echo_log("Update incomplete punch outs - End");
    echo_log("--------------------------------------------------------------------");


    // Sync all taiga projects with orangehrm
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Copy Start");
    echo_log("--------------------------------------------------------------------");
    $project_copy = sync_orangehrm_projects_with_taiga($pg_resource, $mysql_conn);  
    if( $project_copy === true )
    {
        echo_log("Project Copy Completed successfully.");
    }else
    {
        echo_log("Project Copy Failed.");
    }
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Copy End");
    echo_log("--------------------------------------------------------------------");


    // Import customers
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Customers Copy Start");
    echo_log("--------------------------------------------------------------------");
    import_customers_data($pg_resource, $mysql_conn);
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Customers End");
    echo_log("--------------------------------------------------------------------");


    // Import employee
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Employee Copy Start");
    echo_log("--------------------------------------------------------------------");
    import_employee_data($pg_resource, $mysql_conn);
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Employee Copy End");
    echo_log("--------------------------------------------------------------------");


    // Copy projects membership table as it is
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Teams Copy Start");
    echo_log("--------------------------------------------------------------------");
    $project_teams_copy = copy_projects_membership_as_is($pg_resource, $mysql_conn);  
    if( $project_teams_copy === true )
    {
        echo_log("Project Teams Copy Completed successfully.");
    }else
    {
        echo_log("Project Teams Copy Failed.");
    }
    echo_log("--------------------------------------------------------------------");
    echo_log("Project Teams Copy End");
    echo_log("--------------------------------------------------------------------");


    // Assign customers to projects
    //echo_log("--------------------------------------------------------------------");
    //echo_log("Assign Customers to Project Start");
    //echo_log("--------------------------------------------------------------------");
    //$customers_copy = assign_customers_to_project($pg_resource, $mysql_conn);
    //if( $customers_copy === true )
    //{
      //echo_log("Assign Customers to Project Completed successfully.");
    //}else
    //{
    //      echo_log("Assign Customers to Project Failed.");
    //}
    //echo_log("--------------------------------------------------------------------");
    //echo_log("Assign Customers to Project End");
    //echo_log("--------------------------------------------------------------------");


    // Copy userstories
    echo_log("--------------------------------------------------------------------");
    echo_log("User Stories Copy Start");
    echo_log("--------------------------------------------------------------------");
    $user_stories_copy = copy_user_stories_to_project($pg_resource, $mysql_conn);
    if( $user_stories_copy === true )
    {
      echo_log("User Stories Copy Completed successfully.");
    }else
    {
      echo_log("User Stories Copy Failed.");
    }
    echo_log("--------------------------------------------------------------------");
    echo_log("User Stories Copy End");
    echo_log("--------------------------------------------------------------------");

        // Copy Tasks
    echo_log("--------------------------------------------------------------------");
    echo_log("Taiga Tasks Copy Start");
    echo_log("--------------------------------------------------------------------");
    $user_task_copy = copy_all_tasks_to_project($pg_resource, $mysql_conn);
    if( $user_task_copy === true )
    {
      echo_log("Tasks Copy Completed successfully.");
    }else
    {
      echo_log("Tasks Copy Failed.");
    }
    echo_log("--------------------------------------------------------------------");
    echo_log("Tasks Copy End");
    echo_log("--------------------------------------------------------------------");

    // Upsert all webhooks for all taiga projects
    echo_log("--------------------------------------------------------------------");
    echo_log("UPSERT Webhooks Start");
    echo_log("--------------------------------------------------------------------");
    upsert_webhooks_for_all_projects($pg_resource);
    echo_log("--------------------------------------------------------------------");
    echo_log("UPSERT Webhooks End");
    echo_log("--------------------------------------------------------------------");
    

    echo_log("--------------------------------------------------------------------");
    echo_log("TAIGA Misc Data Manage Start");
    echo_log("--------------------------------------------------------------------");
    manage_taiga_roles($pg_resource);
    echo_log("--------------------------------------------------------------------");
    echo_log("TAIGA  Misc Data Manage End");
    echo_log("--------------------------------------------------------------------");

}catch(Exception $e)
{
    $subject = "Unable to execute, Check Error.";
    $message = "I was trying to execute the SYNC script(CRON JOB). Please Check Following Error, "."<br/>";
    $message .= "".$e->getMessage()." "."<br/>"."<br/>";
    $message .= "Kindly do the needful."."<br/>";
    send_email_to_admin($subject, $message);
}    