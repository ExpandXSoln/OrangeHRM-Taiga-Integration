<?php
/*********************************************************************************************************************
* Library functions
*
*
*********************************************************************************************************************/
const New_Line_Start = '<br/> ';//PHP_EOL;
const New_Line_End = ' ';//PHP_EOL;

/**
  * Function to outoput log
  * 
  */
 function echo_log($log_entry)
 {
    echo New_Line_Start . date('d-m-Y h:i a') . " # " . $log_entry .New_Line_End;
    return true;
 }
 // End of function echo_log


/**
  * Function to add cols to mysql table, if cols not exists. 
  *
  * Function also works in upsert mode, in this case it will overwrite the definations of earlier cols.
  *
  * @param $cols_array associative array of column name => defination string pairs
  * @param $db_name string database name
  * @param $table_name string table name whoose cols going to be alerted
  * @param $mysql_conn object connection resource of mysql connection to make queries
  * @param $upsert_mode bool when true drops cols and then adds new ones, if false only add missing cols, does not 
  *         update the old ones.
  * 
  * @return bool true on success, false on failure.
  */
  function mysql_add_cols_if_not_exists($cols_array, $db_name, $table_name, $mysql_conn, $upsert_mode = false)
  {
      
      if( empty($cols_array) )
      {

        // Nothing to alter
        return true;
      }
      $cols_not_exists = $cols_array;
      $only_cols = array_keys($cols_array);

      if($upsert_mode)
      {

        $drop_cols = "ALTER TABLE ".$table_name." DROP " 
          ."".implode(", DROP ", $only_cols).";";

        $mysql_conn->query($drop_cols);
      }

      // Check cols exists
      $check_sync_cols_sql = "SELECT * "
      ."FROM information_schema.COLUMNS "
      ."WHERE  "
      ."TABLE_SCHEMA = '".$db_name."'  "
      ."AND TABLE_NAME = '".$table_name."'  "
      ."AND COLUMN_NAME IN ('".implode("','", $only_cols)."')";

       $check_sync_cols_result = $mysql_conn->query($check_sync_cols_sql);

       if( !empty($check_sync_cols_result) )
       {
           while ( $check_sync_cols_row = $check_sync_cols_result->fetch_array(MYSQLI_ASSOC) ) 
           {
              if(isset($cols_not_exists[$check_sync_cols_row['COLUMN_NAME']])) 
                {
                    unset($cols_not_exists[$check_sync_cols_row['COLUMN_NAME']]);
                } 
           }
       }
       
       
       $col_not_exists_definations = array_values($cols_not_exists);

       if( empty($col_not_exists_definations) )
       {
          // there are no cols left for alerting
          return true;
       }

       $add_col_query = "ALTER TABLE ".$table_name." "
          . implode(', ', $col_not_exists_definations);

       $add_col_result = $mysql_conn->query($add_col_query);


       if( empty($add_col_result) )
       {
             echo_log("Cols could not be aletered.");
             return false;
       }
       return true;
  }
  // End of function mysql_add_cols_if_not_exists

  /**
    * Function to copy project membership table as is
    * 
    * @param $pg_resource object postgres connection object
    * @param $mysql_conn object mysql connection object
    * 
    * @return bool true on success , false on failure
    */
    function copy_projects_membership_as_is($pg_resource, $mysql_conn)
    {
        echo_log("Create project memembership table in OrangeHRM....");

        $mysql_prj_membership_tbl = "taiga_projects_membership";
        $create_prj_member_tbl_sql = "CREATE TABLE IF NOT EXISTS `".$mysql_prj_membership_tbl."` ("
          ."`id` int(11) NOT NULL,"
          ."`is_owner` varchar(1) DEFAULT NULL,"
          ."`email` varchar(255) DEFAULT NULL,"
          ."`created_at` varchar(50) DEFAULT NULL,"
          ."`token` varchar(60) DEFAULT NULL,"
          ."`user_id` int(11) DEFAULT NULL,"
          ."`project_id` int(11) DEFAULT NULL,"
          ."`role_id` int(11) DEFAULT NULL,"
          ."`invited_by_id` int(11) DEFAULT NULL,"
          ."`invitation_extra_text` text,"
          ."`user_order` int(11) DEFAULT NULL,"
          ."PRIMARY KEY (`id`)"
          .") ENGINE=InnoDB DEFAULT CHARSET=latin1;";
    
       $create_prj_member_tbl_sql_result =  $mysql_conn->query($create_prj_member_tbl_sql);  

        $check_create_prj_tbl_sql = "SELECT * FROM ".$mysql_prj_membership_tbl." LIMIT 1;";
        $check_create_prj_tbl_result = $mysql_conn->query($check_create_prj_tbl_sql);
        if( $check_create_prj_tbl_result === FALSE )
        {
            // Table does not exists
            echo_log("Could not create projects membership table.");
            return false;
        }
        
        echo_log("Ohrm project membership table exists, processing further...");

        // Fetch all recrods from postgres
        echo_log("Fetch all memembership table from TAIGA....");
        $get_all_memberships_sql = "SELECT member.*,urole.slug as slug,u.email as user_email "
        ." FROM projects_membership as member "
        ." INNER JOIN users_role as urole ON (member.role_id = urole.id)" 
        ." INNER JOIN users_user as u ON (member.user_id = u.id)";
        

        $get_all_memberships_result = pg_query($pg_resource, $get_all_memberships_sql);

        if( empty($get_all_memberships_sql) )
        {
            
            // Could not get taiga projects data
            echo_log("Could not get taiga projects data from taiga");
            return false;
        }
        

        $ohrm_membership_records = array();
        $ohrm_project_admin_table = 'ohrm_project_admin';
        $ohrm_project_admin_records = array();

        while($get_all_memberships_record = pg_fetch_array($get_all_memberships_result))
        {
          
          $nullintegervalue = -999;
          if( empty($get_all_memberships_record['invited_by_id']) )
          {
            $get_all_memberships_record['invited_by_id'] = $nullintegervalue;
          }
          if( empty($get_all_memberships_record['user_id']) )
          {
            $get_all_memberships_record['user_id'] = $nullintegervalue;
          }
          if( empty($get_all_memberships_record['project_id']) )
          {
            $get_all_memberships_record['project_id'] = $nullintegervalue;
          }
          if( empty($get_all_memberships_record['role_id']) )
          {
            $get_all_memberships_record['role_id'] = $nullintegervalue;
          }
          if( empty($get_all_memberships_record['invited_by_id']) )
          {
            $get_all_memberships_record['invited_by_id'] = $nullintegervalue;
          }
          if( empty($get_all_memberships_record['user_order']) )
          {
            $get_all_memberships_record['user_order'] = $nullintegervalue;
          }
if(empty($get_all_memberships_record['is_owner'])){
          $ohrm_membership_records[] = '('.$get_all_memberships_record['id']
          . ',' . "0"
          . ',' . "'" . addslashes($get_all_memberships_record['user_email']) . "'"
          . ',' . "'" . addslashes($get_all_memberships_record['created_at']) . "'"
          . ',' . "'" . addslashes($get_all_memberships_record['token']) . "'"
          . ',' . $get_all_memberships_record['user_id']
          . ',' . $get_all_memberships_record['project_id']
          . ',' . $get_all_memberships_record['role_id']
          . ',' . $get_all_memberships_record['invited_by_id']
          . ',' . "'" . addslashes($get_all_memberships_record['invitation_extra_text']) ."'"
          . ',' . $get_all_memberships_record['user_order']
          . ') ';
}else{
          $ohrm_membership_records[] = '('.$get_all_memberships_record['id']
          . ',' . "'" . $get_all_memberships_record['is_owner'] . "'"
          . ',' . "'" . addslashes($get_all_memberships_record['user_email']) . "'"
          . ',' . "'" . addslashes($get_all_memberships_record['created_at']) . "'"
          . ',' . "'" . addslashes($get_all_memberships_record['token']) . "'"
          . ',' . $get_all_memberships_record['user_id']
          . ',' . $get_all_memberships_record['project_id']
          . ',' . $get_all_memberships_record['role_id']
          . ',' . $get_all_memberships_record['invited_by_id']
          . ',' . "'" . addslashes($get_all_memberships_record['invitation_extra_text']) ."'"
          . ',' . $get_all_memberships_record['user_order']
          . ') ';
        }
          // trim($get_all_memberships_record['is_owner']) === 't' && 
          if( strtolower(trim($get_all_memberships_record['slug'])) !== 'client' )
          {
            if( !isset($ohrm_project_admin_records[$get_all_memberships_record['project_id']]) )
            {
              $ohrm_project_admin_records[$get_all_memberships_record['project_id']] = array();
            }
            $ohrm_project_admin_records[$get_all_memberships_record['project_id']][] 
            = $get_all_memberships_record['user_id'];
          }
          
            
        }
        
        $turncate_ohrm_prj_mem_table = "TRUNCATE ".$mysql_prj_membership_tbl;
        $mysql_conn->query($turncate_ohrm_prj_mem_table);

        echo_log("Ohrm  project membership table truncated, processing further...");



        if( count($ohrm_membership_records) <=0 )
        {
          echo_log("No taiga membership found, this case is not possible when taiga is running good.");
          return false;
        }

        echo_log("Taiga project membership records fetched, processing further...");

        $insert_ohrm_prj_mem_table_sql = "INSERT INTO ".$mysql_prj_membership_tbl." ("
          . "id,"
          ."is_owner,"
          ."email,"
          ."created_at,"
          ."token,"
          ."user_id,"
          ."project_id,"
          ."role_id,"
          ."invited_by_id,"
          ."invitation_extra_text,"
          ."user_order)"
          ." VALUES "
          .implode(", ", $ohrm_membership_records);
        
        
        $insert_ohrm_prj_mem_table_result = $mysql_conn->query($insert_ohrm_prj_mem_table_sql);  

        if( empty($mysql_conn->affected_rows) )
        {
          echo_log("There were some records in taiga project membership, but system failed to insert those in ohrm.");
          return false;
        }
        echo_log("Inserting project admin records, ....");
        foreach($ohrm_project_admin_records as $prj_id => $admin_array)
        {
            $clean_earlier_admins_sql = "DELETE FROM ".$ohrm_project_admin_table." " 
            . "WHERE project_id IN(SELECT project_id FROM ohrm_project WHERE taiga_project_id = ".$prj_id.")";
            $clean_earlier_admins_result = $mysql_conn->query($clean_earlier_admins_sql);

            if( !empty($admin_array) )
            {
              foreach($admin_array as $admin_id)
              {
                if( empty($admin_id) ) continue;
                $insert_sql = "INSERT INTO ".$ohrm_project_admin_table." (project_id, emp_number) "
                ."SELECT ( SELECT ohrm_project.project_id FROM ohrm_project WHERE taiga_project_id=".$prj_id." LIMIT 1) as project_id, (SELECT hs_hr_employee.emp_number FROM hs_hr_employee WHERE hs_hr_employee.sync_user_id = '".$admin_id."' LIMIT 1) as emp_number FROM DUAL";
                $mysql_conn->query($insert_sql);
              }
            }
        }

        echo_log("Done.");
        return true;
    }
    // End of function copy_projects_membership_as_is

    /**
    * Function to assign customers to project.
    *
    * @param $pg_resource object postgres connection object
    * @param $mysql_conn object mysql connection object
    * 
    * @return bool true on success , false on failure
    */
    function assign_customers_to_project($pg_resource, $mysql_conn)
    {
      echo_log("Retrieving list of customers from taiga...");
      $get_customers_frm_taiga_sql = "SELECT u.id as user_id, member.project_id as taiga_project_id   "
      ."FROM users_user as u, users_role as urole, projects_membership as member "
      ."WHERE "
      ."u.id = member.user_id " 
      ."AND " 
      ."member.role_id = urole.id "
      ."AND ( urole.slug = 'client') AND u.is_system = 'f'";

      $get_customers_frm_taiga_result = pg_query($pg_resource, $get_customers_frm_taiga_sql);
      if( empty($get_customers_frm_taiga_result) )
      {
        echo_log("No customers yet.");
        return true;
      }

      while($get_customers_frm_taiga_record = pg_fetch_array($get_customers_frm_taiga_result))
      {

          $update_customer_sql = "UPDATE ohrm_project "
          ."SET customer_id = ( "
            ."SELECT customer_id "
            ."FROM ohrm_customer "
            ."WHERE sync_user_id = '".$get_customers_frm_taiga_record['user_id']."' LIMIT 1) "
          ."WHERE taiga_project_id = ".$get_customers_frm_taiga_record['taiga_project_id']." ";
          $mysql_conn->query($update_customer_sql);
      }
      echo_log("Done");
      return true;
    }
    // End of function assign_customers_to_project

  /**
  * Function to copy user stories from taiga to ohrm project activities
  *
  */
    function copy_all_tasks_to_project($pg_resource, $mysql_conn){

      echo_log("Checking schema changes...");  

      
        $all_taiga_tasks_from_taiga = "SELECT us_t.id as taiga_task_pk_id, us_t.ref as taiga_task_ref_id, us_t.subject as task_name, us_t.user_story_id as taiga_us_id, us_t.project_id as project_id, usr.email as task_assigned_to, st_t.is_closed as is_closed, st_t.name as status, cat.attributes_values "
                      . "FROM tasks_task as us_t "
                      . "LEFT JOIN projects_taskstatus as st_t ON us_t.status_id = st_t.id "
                      . "LEFT JOIN users_user as usr ON us_t.assigned_to_id = usr.id "
                      . "LEFT JOIN custom_attributes_taskcustomattributesvalues as cat ON us_t.id = cat.task_id "
                      . "WHERE us_t.modified_date > current_timestamp - interval '24 hours'";
    
        $all_taiga_tasks_from_taiga_result = pg_query($pg_resource, $all_taiga_tasks_from_taiga);
//       $all_taiga_tasks_from_taiga_result_temp = pg_query($pg_resource, $all_taiga_tasks_from_taiga);

//       if(!pg_fetch_array($all_taiga_tasks_from_taiga_result_temp))
//       {
//          echo_log("no tasks found.");
//          return true;
//       }

       $updatedtaskcount = 0;
       $newtaskCount = 0;
       while($all_taiga_tasks_from_taiga_record = pg_fetch_array($all_taiga_tasks_from_taiga_result)) 
       {
           $estimatedTimeTaiga = 0;
           $actualTimeTaiga = 0;
           
           $_tasksTimming = json_decode($all_taiga_tasks_from_taiga_record['attributes_values']);
           foreach ($_tasksTimming as $key=>$_tasksTime){
               if(empty($key)||empty($_tasksTime))                   continue;
               
               $taiga_tasks_attribute_details_query = "SELECT name FROM custom_attributes_taskcustomattribute WHERE id=".$key;
               $taiga_tasks_attribute_details_result = pg_query($pg_resource, $taiga_tasks_attribute_details_query);
               $taiga_tasks_attribute_details_result_array = pg_fetch_array($taiga_tasks_attribute_details_result);
               
               if(!empty($taiga_tasks_attribute_details_result_array['name']) && (strpos(strtolower($taiga_tasks_attribute_details_result_array['name']), 'time allocated') !== FALSE || strpos(strtolower($taiga_tasks_attribute_details_result_array['name']), 'estimated time') !== FALSE)){
                   $estimatedTimeTaiga = (float)$_tasksTime;
                   echo $all_taiga_tasks_from_taiga_record['taiga_task_pk_id'];
               }
               
               if(!empty($taiga_tasks_attribute_details_result_array['name']) && strpos(strtolower($taiga_tasks_attribute_details_result_array['name']), 'actual time') !== FALSE){
                   $actualTimeTaiga = (float)$_tasksTime;
                   echo $all_taiga_tasks_from_taiga_record['taiga_task_pk_id'];
               }
               
               if($estimatedTimeTaiga && $actualTimeTaiga)                   break;
            
               
           }
           
//           echo_log("All Tasks One by One");
          $get_emp_number_subsql = "SELECT `emp_number` "
          ."FROM `hs_hr_employee` WHERE `emp_work_email`='".$all_taiga_tasks_from_taiga_record['task_assigned_to']."' OR `emp_oth_email`='".$all_taiga_tasks_from_taiga_record['task_assigned_to']."' "
          ."LIMIT 1";
          $get_emp_number_subsql_result = $mysql_conn->query($get_emp_number_subsql);
          $get_emp_number_subsql_result_fetch = mysqli_fetch_array($get_emp_number_subsql_result);
          
          $get_project_id_subsql = "SELECT project_id "
            ."FROM ohrm_project WHERE taiga_project_id=".$all_taiga_tasks_from_taiga_record['project_id']." "
            ."LIMIT 1";
          $get_us_activity_id_subsql = "SELECT `activity_id` "
            ."FROM `ohrm_project_activity` WHERE `taiga_us_id`=".$all_taiga_tasks_from_taiga_record['taiga_us_id']." "
            ."LIMIT 1";
          
          if(!$get_emp_number_subsql_result_fetch){
              
              echo_log("Employee not found in Upasthiti =>".$all_taiga_tasks_from_taiga_record['task_assigned_to']);
              
          }
          
          $get_task_upasthiti = "SELECT * FROM ohrm_taiga_us_tasks WHERE taiga_task_pk_id = ".$all_taiga_tasks_from_taiga_record['taiga_task_pk_id'];
          $get_task_upasthiti_result = $mysql_conn->query($get_task_upasthiti);
          $get_task_upasthiti_result_fetch = mysqli_fetch_array($get_task_upasthiti_result);
          if($get_task_upasthiti_result_fetch){
              /**
               * if task is exist
               * check if status is changed
               * if yes then update
               */
//              if($get_task_upasthiti_result_fetch['status'] != $all_taiga_tasks_from_taiga_record['status'] || $get_emp_number_subsql_result_fetch['emp_number'] != $get_task_upasthiti_result_fetch['task_assigned_to'] || $get_task_upasthiti_result_fetch['is_closed'] != $all_taiga_tasks_from_taiga_record['is_closed'] ){
                  $update_us_task_as_activity_sql = "UPDATE `ohrm_taiga_us_tasks`"
                          . "SET "
                          . "`activity_id`=(".$get_us_activity_id_subsql."),"
                          . "`project_id`= (".$get_project_id_subsql."),"
                          . "`task_assigned_to`= (".$get_emp_number_subsql."),"
                          . "`task_name`='".addslashes($all_taiga_tasks_from_taiga_record["task_name"])."',"
                          . "`status`='".$all_taiga_tasks_from_taiga_record['status']."',"
                          . "`is_closed`='".$all_taiga_tasks_from_taiga_record['is_closed']."'"
                          . "`estimated_time_taiga`=".$estimatedTimeTaiga.""
                          . "`actual_time_taiga`=".$actualTimeTaiga.""
                          . " WHERE `id`=".$get_task_upasthiti_result_fetch['id']." ";
                  
                  $mysql_conn->query($update_us_task_as_activity_sql);
                  $updatedtaskcount++;
//              }
          }else{
            /**
             * if task is not exit
             * create new task
             */
            
            $insert_new_task_as_activity_sql = "INSERT INTO `ohrm_taiga_us_tasks`(`activity_id`, `taiga_task_pk_id`, `taiga_task_ref_id`, `project_id`, `task_assigned_to`, `task_name`, `status`, `estimated_time_taiga`, `actual_time_taiga`, `is_closed`) "
                    . "VALUES ((".$get_us_activity_id_subsql."),'".$all_taiga_tasks_from_taiga_record['taiga_task_pk_id']."','".$all_taiga_tasks_from_taiga_record['taiga_task_ref_id']."',(".$get_project_id_subsql."),(".$get_emp_number_subsql."),'".addslashes($all_taiga_tasks_from_taiga_record["task_name"])."','".$all_taiga_tasks_from_taiga_record['status']."',".$estimatedTimeTaiga.",".$actualTimeTaiga.",'".$all_taiga_tasks_from_taiga_record['is_closed']."')";
            $mysql_conn->query($insert_new_task_as_activity_sql);
            $newtaskCount++;
          }
          echo_log("Finish");

          
       }
       
       // Delete Task which are deleted on taiga
//      $all_taiga_task_ids = array();
//      $all_taiga_tasks_from_taiga_delete = "SELECT us_t.id as task_id, us_t.subject as task_name, us_t.user_story_id as taiga_us_id, us_t.project_id as project_id, usr.email as task_assigned_to, st_t.is_closed as is_closed, st_t.name as status "
//                    . "FROM tasks_task as us_t "
//                    . "LEFT JOIN projects_taskstatus as st_t ON us_t.status_id = st_t.id "
//                    . "LEFT JOIN users_user as usr ON us_t.assigned_to_id = usr.id ";
//      
//      $all_taiga_tasks_from_taiga_delete_result = pg_query($pg_resource, $all_taiga_tasks_from_taiga_delete);
//      
//        while($all_taiga_tasks_from_taiga_delete_record = pg_fetch_array($all_taiga_tasks_from_taiga_delete_result)) 
//        {
//          $all_taiga_task_ids[] = $all_taiga_tasks_from_taiga_delete_record['task_id'];
//        }
//        //Count of Task which are deleted
//        $all_taiga_task_deleted_count =     "SELECT count(*) FROM `ohrm_taiga_us_tasks` "
//            . "WHERE `task_id` NOT IN (".implode(',', $all_taiga_task_ids).")";
//        
//        $all_taiga_task_deleted_count_result = $mysql_conn->query($all_taiga_task_deleted_count);
//        if($all_taiga_task_deleted_count_result){
//            $all_taiga_task_deleted_count_fetch = mysqli_fetch_array($all_taiga_task_deleted_count_result);
//        }
//        
////        echo "<pre>";        print_r($all_taiga_task_deleted_count_fetch);die;
//        $deletedtaskCount = 0;
//        // Delete all Upasthiti tasks which are not in taiga
//        $all_taiga_task_deleted = "DELETE FROM `ohrm_taiga_us_tasks` "
//            . "WHERE `task_id` NOT IN (".implode(',', $all_taiga_task_ids).")";
//        if($mysql_conn->query($all_taiga_task_deleted)){
//            $deletedtaskCount = $all_taiga_task_deleted_count_fetch[0];
//        }

       echo_log("Total ".$newtaskCount." new Task added");
       echo_log("Total ".$updatedtaskcount." Tasks updated");
//       echo_log("Total ".$deletedtaskCount." Task deleted");
       echo_log("Done.");

        return true;
    }
        // End of function copy_all_Tasks_to_ohrm_taiga_us_tasks

    
  /**
  * Function to copy user stories from taiga to ohrm project activities
  *
  */
    function copy_user_stories_to_project($pg_resource, $mysql_conn){

      echo_log("Checking schema changes...");  

      $ohrm_activities_table = 'ohrm_project_activity';

        // Add missing columns 
      $activity_sync_cols = array(
        'taiga_us_id' => 'ADD `taiga_us_id` VARCHAR( 10 ) NULL ',
        'is_closed' => 'ADD `is_closed` VARCHAR( 10 ) NULL ',
      );   

      $activity_cols = mysql_add_cols_if_not_exists($activity_sync_cols, 
        MySQL_Database, $ohrm_activities_table, $mysql_conn);

      if($activity_cols)
      {
        echo_log("User stories id col exists... ");

      }else
      {
        echo_log("Could not add user storiy column.");
        return false;
      }

      // Get all existing user story id 
      $all_taiga_us_ids = array();
      $get_all_taiga_us_ids_sql = "SELECT taiga_us_id FROM ".$ohrm_activities_table." WHERE taiga_us_id IS NOT NULL ";
      $get_all_taiga_us_ids_result = $mysql_conn->query($get_all_taiga_us_ids_sql);

      if ( !empty($get_all_taiga_us_ids_result) )
      {
          while($get_all_taiga_us_ids_record = mysqli_fetch_array($get_all_taiga_us_ids_result))
          {
            $all_taiga_us_ids[] = $get_all_taiga_us_ids_record['taiga_us_id'];
          }
      }

      echo_log("Total  ".count($all_taiga_us_ids)." user stories exists");


      // Get all taiga user stories from taiga
      $all_taiga_us_from_taiga_sql = "SELECT u_us.id, u_us.ref, u_us.project_id, "
      ." u_us.subject, u_us.description,  us_status.is_closed "
      ." FROM userstories_userstory as u_us "
      ." LEFT JOIN projects_userstorystatus as us_status ON u_us.status_id = us_status.id "
      ."";
      
      if( !empty($all_taiga_us_ids) )
      {
        $all_taiga_us_from_taiga_sql .= " WHERE u_us.id NOT IN (".implode(',', $all_taiga_us_ids).")";
      }

       $all_taiga_us_from_taiga_result = pg_query($pg_resource, $all_taiga_us_from_taiga_sql);

       if( empty($all_taiga_us_from_taiga_result) )
       {
          echo_log("Userstories in sync.");
          return true;
       }

       $newuscount = 0;
       while($all_taiga_us_from_taiga_record = pg_fetch_array($all_taiga_us_from_taiga_result)) 
       {
          $get_project_id_subsql = "SELECT project_id "
          ."FROM ohrm_project WHERE taiga_project_id=".$all_taiga_us_from_taiga_record['project_id']." "
          ."LIMIT 1";

          $insert_us_as_activity_sql = "INSERT INTO ".$ohrm_activities_table." " 
          . "(activity_id, project_id, name, is_deleted, taiga_us_id, is_closed)  "
          ."SELECT "

          // Values
          ."NULL, "
          ."(".$get_project_id_subsql."),"
          ."'".$all_taiga_us_from_taiga_record['ref']."-".addslashes($all_taiga_us_from_taiga_record["subject"])."',"
          ."0,"
          ."'".$all_taiga_us_from_taiga_record['id']."',"
          ."'".$all_taiga_us_from_taiga_record['is_closed']."'";


          $mysql_conn->query($insert_us_as_activity_sql);
          $newuscount++;
       }

       echo_log("Total ".$newuscount." new userstores added");
       echo_log("Done.");

        return true;
    }
    // End of function copy_user_stories_to_project
 /**
  * Function to process userstory webhook
  *
  * @param $json_array array of userstory webhook data
  * @param $mysql_conn object mysql connection object
  * 
  * @return bool true on success , false on failure
  */
  function process_userstory_hook($json_array, $mysql_conn)
  {
    if( isset($json_array['type']) && $json_array['type'] === 'userstory' )
    {
      $userstory_data = $json_array['data']; 

      // Process userstory hook
      if( $json_array['action'] == 'create' || $json_array['action'] == 'change' )
      {
          upsert_userstory_as_prj_activity($userstory_data, $mysql_conn);
      }

      if( $json_array['action'] == 'delete' )
      {
        echo_log("Deleting...");
        $delete_ohrm_project_activity_sql = "UPDATE ohrm_project_activity SET is_deleted='1' WHERE taiga_us_id='".$userstory_data["id"]."'";
        $mysql_conn->query($delete_ohrm_project_activity_sql);
      }
      echo_log($mysql_conn->affected_rows ." Affected rows.");
      echo_log("Userstory hook processed.");
      return true;
    }
    return false;
  }
  // End of function process_userstory_hook
  /**
  * Function to upsert userstory as project acrivity when userstory array is given.
  *
  */
  function upsert_userstory_as_prj_activity($userstory_data, $mysql_conn)
  {
      $userstory_name = $userstory_data["ref"].'-'.$userstory_data["subject"];
      $get_project_id_subsql = "SELECT project_id "
              ."FROM ohrm_project WHERE taiga_project_id=".$userstory_data['project']." LIMIT 1";

      $activity_exists_sql = "SELECT * FROM ohrm_project_activity WHERE taiga_us_id='".$userstory_data["id"]."' ";
      $activity_exists_result = $mysql_conn->query($activity_exists_sql);
      $activity_exists_record = (!empty($activity_exists_result))?mysqli_fetch_array($activity_exists_result):0;

      $userstory_data['is_closed'] = (isset($userstory_data['is_closed']) && $userstory_data['is_closed'] == true)?'t':'f';

      if( empty($activity_exists_result) || empty( $activity_exists_record ) ) 
      {
          // Insert new activity for this project
          echo_log("Inserting ...");
          $insert_ohrm_project_activity_sql = "INSERT INTO ohrm_project_activity "
          ."(activity_id, project_id, name, is_deleted, taiga_us_id, is_closed) SELECT "
          ."NULL, (".$get_project_id_subsql."), '".addslashes($userstory_name)."',0,'".$userstory_data['id']."','".$userstory_data['is_closed']."'";
          $mysql_conn->query($insert_ohrm_project_activity_sql);

      }else
      {
        echo_log("Updating...");
        $update_ohrm_project_activity_sql = "UPDATE ohrm_project_activity SET "
        ." name='".addslashes($userstory_name)."', "
        ." is_closed='".$userstory_data['is_closed']."' "
        ." WHERE taiga_us_id='".$userstory_data["id"]."'";
        $mysql_conn->query($update_ohrm_project_activity_sql);
      }
  }


 /**
  * Update webhooks for given project
  * 
  */
  function upsert_webhooks_for_all_projects($pg_resource){
      
      echo_log("Started Updating Webhooks for all projects.");
      echo_log("Webhooks Data");
      echo_log("Webhooks Name : " . Webhook_Prefix.Latest_Webhook_Name );
      echo_log("Webhooks URL : " . Webhook_URL );
      echo_log("Webhooks Key : " . Webhook_Key );
      
      
      // Get all Projects
      $get_all_proj_sql = "SELECT id, name "
              ."FROM projects_project ";
      
      $result = pg_query( $pg_resource, $get_all_proj_sql);
      
      $count = 0;
      $upsert_result_count = 0;
      while( $project = pg_fetch_array($result) )
      {
          
        // Update all webhooks with new url and new key
        $update_exi_webhook_sql = "UPDATE webhooks_webhook "
          ."SET "
          ."url='".Webhook_URL."', "
          ."key='".Webhook_Key."' "
          ."WHERE name='".Webhook_Prefix.Latest_Webhook_Name."' "
          ."AND project_id=".$project['id']."";

        $insert_exi_webhook_sql = "INSERT INTO webhooks_webhook "
          ."( url, key, project_id, name) "      
          ."SELECT "
          .""
          //."NULL,"      
          ."'".Webhook_URL."',"      
          ."'".Webhook_Key."',"      
          ."".$project['id'].","      
          ."'".Webhook_Prefix.Latest_Webhook_Name."'"      
          ."";
        
        $upsert_query = "WITH upsert AS "
            ."(".$update_exi_webhook_sql." RETURNING *) "
            .$insert_exi_webhook_sql." WHERE NOT EXISTS (SELECT * FROM upsert);";
        
        
        
        $upsert_result = pg_query($pg_resource, $upsert_query);
        
        $affected_rows = pg_affected_rows($upsert_result);
        
        //echo_log("Affected rows -> ".$affected_rows);
        if( $affected_rows )
        {
            $upsert_result_count++;
        }
        
        $count++;
      }
      
      echo_log( "Total ".$count." projects." );
      echo_log( "Updated for ".$upsert_result_count." projects." );
      echo_log("End Of Updating Webhooks for all projects.");
      
      // Remove old webhooks
      echo_log("Removing old webhooks");
      $delete_webhooks_log = "DELETE FROM webhooks_webhooklog WHERE webhook_id IN (SELECT id FROM webhooks_webhook WHERE name LIKE '".Webhook_Prefix."%' AND name NOT LIKE '".Webhook_Prefix.Latest_Webhook_Name."' )";
      pg_query($pg_resource, $delete_webhooks_log);
      $delete_old_webhooks_query = "DELETE FROM webhooks_webhook WHERE name LIKE '".Webhook_Prefix."%' AND name NOT LIKE '".Webhook_Prefix.Latest_Webhook_Name."'; ";
      $delete_old_webhooks_result = pg_query($pg_resource, $delete_old_webhooks_query);
      $delete_old_webhooks_affected_rows = pg_affected_rows($delete_old_webhooks_result);
      echo_log("Rows affected -> ".$delete_old_webhooks_affected_rows);

      return true;
  };
  
  /**
   * Function to sycn all taiga projects with orangehrm projects
   *
   * @param $pg_resource object postgres connection object
   * @param $mysql_conn object mysql connection object
   * 
   * @return bool true on success , false on failure
   * 
   */
  function sync_orangehrm_projects_with_taiga($pg_resource, $mysql_conn)
  {
      echo_log("Adding sync cols to project table...");
      // Check if taiga project id column added to ohrm projects table
      // Add missing columns 
      $project_sync_cols = array(
        'taiga_project_id' => 'ADD COLUMN taiga_project_id int(11) DEFAULT NULL ',
        'is_closed' => 'ADD COLUMN is_closed tinyint(1) DEFAULT \'0\' ',
      );   

      $project_sync_cols_result = mysql_add_cols_if_not_exists($project_sync_cols, 
        MySQL_Database, 'ohrm_project', $mysql_conn);

      if( empty($project_sync_cols_result) )
      {
        echo_log("Project Sync col does not exist.");
        return false;
      }


      
      echo_log("Fetching all synced projects from OrangeHRM ...");
       // Get all taiga project ids from orange hrm
       $exist_taiga_prj_result = $mysql_conn
               ->query("SELECT taiga_project_id "
                       ."FROM ohrm_project "
                       ."WHERE taiga_project_id IS NOT NULL");
   
   
       // Get all projects from taiga not including these ids
       $taiga_project_ids = array();
        if( !empty($exist_taiga_prj_result) )
        {
             while($row = mysqli_fetch_array($exist_taiga_prj_result)) 
             {
                 $taiga_project_ids[] = $row["taiga_project_id"];
             }; 
        }
        echo_log("Total ".count($taiga_project_ids)." Projects found in sync.");


        echo_log("Fetching all projects from TAIGA...");
        $taiga_fetch_prj_query = "SELECT id, name, slug, description "
                ."FROM projects_project";
        
        if( count($taiga_project_ids) > 0 ) 
        {
            $taiga_fetch_prj_query .= " WHERE id not in (".implode(',',$taiga_project_ids).");";
        }
        $result = pg_query($pg_resource, $taiga_fetch_prj_query);
        if ( empty($result) ) 
        {
          echo_log("Everything is in sync.");
          return true;
        }
    
        echo_log("Preparing insert query for OrangeHRM...");
        echo_log("Postponing customer assignment...");
        // Add projects records in hrm, update taiga for orangehrm hooks
        $insert_into_orangehrm_query = "INSERT INTO `".MySQL_Database."`.`ohrm_project` (
              `project_id` ,
              `customer_id` ,
              `name` ,
              `description` ,
              `taiga_project_id` ,
              `is_deleted`
              )
              VALUES ";
        $project_values = [];
        while ($row = pg_fetch_array($result))
        {

            $project_values[] ="(
              NULL , -999, '".addslashes($row["name"])."', '".addslashes($row["description"])."' , ".$row["id"]." , '0'
              )";
            
            echo_log("Sync in progress... ".$row["id"]." ".$row["name"]);
        }
        
    
    if( !empty($project_values) )
    {
        echo_log("Total ".count($project_values)." Projects will get added.");
        $insert_projects_sql = $insert_into_orangehrm_query.implode(', ',$project_values);
        $sync_result = $mysql_conn->query($insert_projects_sql);
        if( !empty($mysql_conn->affected_rows) )
        {
            echo_log($mysql_conn->affected_rows." Projects Imported.");
        }else
        {
          echo_log("Project insert failed");
          return false;
        }
        echo_log("Rows Affected " . $mysql_conn->affected_rows);
    }
    echo_log("End of sync");
    return true;
  }


    /**
    * Function to import customers
    *
    */
    function import_customers_data($pg_resource, $mysql_conn)
    {
        $customers_table = 'ohrm_customer';

        // Add missing columns to ohrm customers
    $customers_sync_cols = array(
      'sync_username' => 'ADD `sync_username` VARCHAR( 300 ) NULL ',
      'sync_email' => 'ADD `sync_email` VARCHAR( 300 ) NULL ',
      'sync_is_active' => 'ADD `sync_is_active` VARCHAR( 300 ) NULL ',
      'sync_full_name' => 'ADD `sync_full_name` VARCHAR( 300 ) NULL ',
      'sync_bio' => 'ADD `sync_bio` TEXT NULL',
      'sync_date_joined' => 'ADD `sync_date_joined` VARCHAR( 300 ) NULL ',
      'sync_is_system' => 'ADD `sync_is_system` VARCHAR( 300 ) NULL ',
      'sync_user_id' => 'ADD COLUMN sync_user_id VARCHAR( 300 ) NULL  ',
      'sync_last_login' => 'ADD `sync_last_login` VARCHAR( 300 ) NULL '
  );    
  $customers_sync_cols_result 
    = mysql_add_cols_if_not_exists($customers_sync_cols, MySQL_Database, $customers_table, $mysql_conn);

  if( $customers_sync_cols_result )
  {
    echo_log((empty($customers_sync_cols))?'Customers cols were empty.':'');
    echo_log('Customers cols added successfully..');
  }else
  {
    echo_log('Customers Cols add failed, exiting now.');
    return false;
  }

    // Customers list
    $get_customers_frm_taiga_sql = "SELECT u.id as user_id, u.username,   "
    . "u.email, u.is_active, u.full_name, u.bio, u.date_joined, u.is_system, u.last_login "
    ."FROM users_user as u, users_role as urole, projects_membership as member "
    ."WHERE "
    ."u.id = member.user_id " 
    ."AND " 
    ."member.role_id = urole.id "
    ."AND ( urole.slug = 'client') AND u.is_system = 'f' GROUP BY u.id;";

    $get_customers_frm_taiga_result = pg_query( $pg_resource, $get_customers_frm_taiga_sql);

    if( empty($get_customers_frm_taiga_result) )
      {
        echo_log("Could not fetch taiga users.");
        echo_log("Exiting now");
        return false;
      }

      $count = 0;
      
      $customer_cols = array_keys($customers_sync_cols);
      $customer_rows = array();


      while( $customer = pg_fetch_array($get_customers_frm_taiga_result) )
      {
          $customer_row = array('name'=>'');
          foreach($customer_cols as $customer_col)
          {
            $taiga_col_name = ltrim($customer_col, 'sync_');

            if( isset($customer[$taiga_col_name]) )
            {
              $customer_row[$customer_col] = $customer[$taiga_col_name];
            }
          }
          $customer_row['name'] = $customer['full_name'];
          $customer_rows[] = $customer_row;
      }


      if( empty($customer_rows) )
      {
        echo_log("No customers found to be synced.");
        echo_log("Exiting now");
        return false;
      }

      foreach ($customer_rows as $customer_row) 
      {

        

        $update_values = array();
        $insert_cols = array();
        $insert_values = array();
        foreach ($customer_row as $col => $val) 
        {
          $update_values[] = ' '.$col.' = \''.addslashes($val).'\' ';
          $insert_cols[] = $col;
          $insert_values[] = addslashes($val);
        }

          
          $udpate_cust_query = "Update `".$customers_table."` SET "
          ." ".implode(", ", $update_values)." "
          ."WHERE sync_user_id is NOT NULL "
          ."AND sync_user_id='".$customer_row['sync_user_id']."'";
          $mysql_conn->query($udpate_cust_query);


          
            // insert sql
            $insert_sql = "INSERT INTO `".$customers_table."` "
                ."(".implode(', ', $insert_cols).") "
                . " SELECT "
                ."'".implode("', '", $insert_values)."' FROM DUAL "
                ."WHERE NOT EXISTS(SELECT customer_id FROM ".$customers_table." "
                  ."WHERE sync_user_id IS NOT NULL AND sync_user_id='".$customer_row['sync_user_id']."')";
          //$mysql_conn->query($insert_sql);
        echo_log("customer insert skipped");
      }

      return true;
    }
    // End of function import_customers_data

    /**
    * Function to import employee
    *
    */
    function import_employee_data($pg_resource, $mysql_conn)
    {

      $customers_table = 'hs_hr_employee';

        // Add missing columns to ohrm customers
        $customers_sync_cols = array(
          'sync_username' => 'ADD `sync_username` VARCHAR( 300 ) NULL ',
          'sync_email' => 'ADD `sync_email` VARCHAR( 300 ) NULL ',
          'sync_is_active' => 'ADD `sync_is_active` VARCHAR( 300 ) NULL ',
          'sync_full_name' => 'ADD `sync_full_name` VARCHAR( 300 ) NULL ',
          'sync_bio' => 'ADD `sync_bio` TEXT NULL',
          'sync_date_joined' => 'ADD `sync_date_joined` VARCHAR( 300 ) NULL ',
          'sync_is_system' => 'ADD `sync_is_system` VARCHAR( 300 ) NULL ',
          'sync_user_id' => 'ADD COLUMN sync_user_id VARCHAR( 300 ) NULL  ',
          'sync_last_login' => 'ADD `sync_last_login` VARCHAR( 300 ) NULL '
      );

  $customers_sync_cols_result 
    = mysql_add_cols_if_not_exists($customers_sync_cols, MySQL_Database, $customers_table, $mysql_conn);

  if( $customers_sync_cols_result )
  {
    echo_log((empty($customers_sync_cols))?'Customers cols were empty.':'');
    echo_log('Employee schema updated successfully..');
  }else
  {
    echo_log('Employee  Schema updation failed, exiting now.');
    return false;
  }

    // Customers list
    $get_customers_frm_taiga_sql = "SELECT u.id as user_id, u.username,  "
    . "u.email, u.is_active, u.full_name, u.bio, u.date_joined, u.is_system, u.last_login "
    ."FROM users_user as u, users_role as urole, projects_membership as member "
    ."WHERE "
    ."u.id = member.user_id " 
    ."AND " 
    ."member.role_id = urole.id "
    ."AND ( urole.slug <> 'client') AND u.is_system = 'f' GROUP BY u.id;";

    $get_customers_frm_taiga_result = pg_query( $pg_resource, $get_customers_frm_taiga_sql);

    if( empty($get_customers_frm_taiga_result) )
      {
        echo_log("Could not fetch taiga users.");
        echo_log("Exiting now");
        return false;
      }

      $count = 0;
      
      $customer_cols = array_keys($customers_sync_cols);
      $customer_rows = array();


      while( $customer = pg_fetch_array($get_customers_frm_taiga_result) )
      {
          $customer_row = array();// array('emp_firstname'=>'','emp_number'=>'','employee_id'=>'','emp_lastname'=>'');
          foreach($customer_cols as $customer_col)
          {
            $taiga_col_name = ltrim($customer_col, 'sync_');

            if( isset($customer[$taiga_col_name]) )
            {
              $customer_row[$customer_col] = $customer[$taiga_col_name];
            }
          }
          //$cust_name = explode(' ', $customer['full_name']);
          //$customer_row['emp_firstname'] = isset($cust_name[0])?$cust_name[0]:'';
          //$customer_row['emp_lastname'] = isset($cust_name[1])?$cust_name[1]:'';
          
          $customer_rows[] = $customer_row;
      }


      if( empty($customer_rows) )
      {
        echo_log("No Employee found to sync.");
        echo_log("Exiting now");
        return false;
      }

      $emp_not_in_ohrm = array();

      foreach ($customer_rows as $customer_row) 
      {

        // Prepare update and insert values
        // This helps in preparing update and insert queries
        $update_values = array();
        $insert_cols = array();
        $insert_values = array();
        foreach ($customer_row as $col => $val) 
        {
          $update_values[] = ' '.$col.' = \''.addslashes($val).'\' ';
          $insert_cols[] = $col;
          $insert_values[] = addslashes($val);
        }

        $update_values[] = 'emp_work_email = \''.addslashes($customer_row['sync_email']).'\' ';

          
          // Update Sync data when email matches
          $udpate_cust_query = "Update `".$customers_table."` SET "
          ." ".implode(", ", $update_values)." "
          ." WHERE emp_work_email='".$customer_row['sync_email']."'";
          $mysql_conn->query($udpate_cust_query);
          // 



          // Check if users exist in ohrm 
          $get_emp_in_ohrm_sql = "SELECT emp_work_email "
          ." FROM `".$customers_table."` "
          ." WHERE (emp_work_email='".$customer_row['sync_email']."' OR emp_oth_email='".$customer_row['sync_email']."') LIMIT 1";
          $get_emp_in_ohrm_result = $mysql_conn->query($get_emp_in_ohrm_sql);
          if( !empty($get_emp_in_ohrm_result) )
          {
            $get_emp_in_ohrm_row = mysqli_fetch_array($get_emp_in_ohrm_result);
            if( empty($get_emp_in_ohrm_row) )
            {
              // Employee does not exist in ohrm database
              /*$email_domain = explode('@', $customer_row['sync_email']);
              $email_domain = array_pop($email_domain);
              if( in_array(trim($email_domain), array('splendornet.com')) )
              {
                  $emp_not_in_ohrm[] = $customer_row;
              }*/
              $emp_not_in_ohrm[] = $customer_row;
              
            }
          }
      }
      // End of foreach of taiga non client users i.e. taiga employees

      if( !empty($emp_not_in_ohrm) )
      {
        echo_log("Following employees need to get added in ohrm manually.");
        $subject = "Employee missing";
        $message = "";
        $message .= "Following employees need to get added in ohrm manually. "."<br/>";
        foreach ($emp_not_in_ohrm as $emp) {
          echo_log(" Name : " . $emp['sync_full_name']." Email : ".$emp['sync_email']
          . " Date Joined : " . $emp['sync_date_joined']);
          $message .= "<br/>" . " Name : " . $emp['sync_full_name'].", Email : ".$emp['sync_email']
          . ", Date Joined : " . $emp['sync_date_joined'] ;
        }

        
        $indian_current_time = new DateTime(null, new DateTimeZone('Asia/Kolkata'));
        $current_hr = $indian_current_time->format('H');

        if( intval($current_hr) > 8 && intval($current_hr) < 11 )
        {
            // Send Emails
            send_email_to_admin($subject, $message);
            
        }else
        {
            echo_log("Time is not yet to send email.");
        }
        

      }else
      {
        echo_log("Employees are in sync.");
      }

      return true;
    }
    // End of function import_employee_data

function manage_taiga_roles($pg_resource) {
  $new_roles_json = "[{\"order\": 51, \"slug\": \"project-manager\", \"permissions\": [\"add_issue\", \"modify_issue\", \"delete_issue\", \"view_issues\", \"add_milestone\", \"modify_milestone\", \"delete_milestone\", \"view_milestones\", \"view_project\", \"add_task\", \"modify_task\", \"delete_task\", \"view_tasks\", \"add_us\", \"modify_us\", \"delete_us\", \"view_us\", \"add_wiki_page\", \"modify_wiki_page\", \"delete_wiki_page\", \"view_wiki_pages\", \"add_wiki_link\", \"delete_wiki_link\", \"view_wiki_links\"], \"name\": \"Project Manager\", \"computable\": true},{\"order\": 52, \"slug\": \"team-lead\", \"permissions\": [\"add_issue\", \"modify_issue\", \"delete_issue\", \"view_issues\", \"add_milestone\", \"modify_milestone\", \"delete_milestone\", \"view_milestones\", \"view_project\", \"add_task\", \"modify_task\", \"delete_task\", \"view_tasks\", \"add_us\", \"modify_us\", \"delete_us\", \"view_us\", \"add_wiki_page\", \"modify_wiki_page\", \"delete_wiki_page\", \"view_wiki_pages\", \"add_wiki_link\", \"delete_wiki_link\", \"view_wiki_links\"], \"name\": \"Team Leader\", \"computable\": true},{\"order\": 53, \"slug\": \"full-stack\", \"permissions\": [\"add_issue\", \"modify_issue\", \"delete_issue\", \"view_issues\", \"add_milestone\", \"modify_milestone\", \"delete_milestone\", \"view_milestones\", \"view_project\", \"add_task\", \"modify_task\", \"delete_task\", \"view_tasks\", \"add_us\", \"modify_us\", \"delete_us\", \"view_us\", \"add_wiki_page\", \"modify_wiki_page\", \"delete_wiki_page\", \"view_wiki_pages\", \"add_wiki_link\", \"delete_wiki_link\", \"view_wiki_links\"], \"name\": \"Full Stack\", \"computable\": true},{\"order\": 54, \"slug\": \"client\", \"permissions\": [\"add_issue\", \"modify_issue\", \"delete_issue\", \"view_issues\", \"add_milestone\", \"modify_milestone\", \"delete_milestone\", \"view_milestones\", \"view_project\", \"add_task\", \"modify_task\", \"delete_task\", \"view_tasks\", \"add_us\", \"modify_us\", \"delete_us\", \"view_us\", \"add_wiki_page\", \"modify_wiki_page\", \"delete_wiki_page\", \"view_wiki_pages\", \"add_wiki_link\", \"delete_wiki_link\", \"view_wiki_links\"], \"name\": \"Client\", \"computable\": false},{\"order\": 55, \"slug\": \"qa\", \"permissions\": [\"add_issue\", \"modify_issue\", \"delete_issue\", \"view_issues\",  \"view_milestones\", \"view_project\", \"add_task\", \"modify_task\", \"view_tasks\", \"add_us\", \"modify_us\", \"view_us\", \"add_wiki_page\",  \"view_wiki_pages\", \"add_wiki_link\", \"view_wiki_links\"], \"name\": \"QA\", \"computable\": true}]";
  $new_task_statuses = json_decode("[{\"color\": \"#ffcc00\", \"order\": 3, \"is_closed\": false, \"name\": \"Ready for test\", \"slug\": \"ready-for-test\"}, {\"color\": \"#669900\", \"order\": 4, \"is_closed\": true, \"name\": \"Closed\", \"slug\": \"closed\"}, {\"color\": \"#999999\", \"order\": 5, \"is_closed\": false, \"name\": \"Needs Info\", \"slug\": \"needs-info\"}, {\"color\": \"#99AA99\", \"order\": 6, \"is_closed\": false, \"name\": \"Ready For UAT\", \"slug\": \"ready-for-uat\"}, {\"color\": \"#99BB99\", \"order\": 7, \"is_closed\": true, \"name\": \"Rejected\", \"slug\": \"rejected\"}]", true);
  $new_us_statuses = json_decode("[ {\"color\": \"#fcc000\", \"order\": 4, \"is_closed\": false, \"is_archived\": false, \"wip_limit\": null, \"name\": \"Ready for test\", \"slug\": \"ready-for-test\"}, {\"color\": \"#669900\", \"order\": 5, \"is_closed\": true, \"is_archived\": false, \"wip_limit\": null, \"name\": \"Done\", \"slug\": \"done\"}, {\"color\": \"#5c3566\", \"order\": 6, \"is_closed\": true, \"is_archived\": true, \"wip_limit\": null, \"name\": \"Archived\", \"slug\": \"archived\"}, {\"color\": \"#5c3577\", \"order\": 7, \"is_closed\": false, \"is_archived\": false, \"wip_limit\": null, \"name\": \"Ready For UAT\", \"slug\": \"ready-for-uat\"}, {\"color\": \"#5c3588\", \"order\": 8, \"is_closed\": true, \"is_archived\": false, \"wip_limit\": null, \"name\": \"Rejected\", \"slug\": \"rejected\"}]", true);  
  $new_issue_statuses = json_decode("[{\"color\": \"#6666AA\", \"order\": 8, \"is_closed\": false, \"name\": \"Ready for UAT\", \"slug\": \"ready-for-uat\"}, {\"color\": \"#6666BB\", \"order\": 9, \"is_closed\": false, \"name\": \"Can Not Reproduce\", \"slug\": \"can-not-reproduce\"}]", true);

  $us_custom_attrs = array(
      array('name'=>'Actual Time','description'=>'Time Taken to complete the user story.'),
      array('name'=>'Time Allocated','description'=>'Allocated time to complete the user story.'),
      array('name'=>'Raised Issues','description'=>'Raised issues against this User Story.')
  );
  $task_custom_attrs = array(
      array('name'=>'Actual Time','description'=>'Time Taken to complete the task.'),
      array('name'=>'Time Allocated','description'=>'Allocated time to complete the task.')
  );
  $issue_custom_attrs = array(
      array('name'=>'Actual Time','description'=>'Time Taken to fix the issue.')
  );

  $new_roles_array = json_decode($new_roles_json, true);

  $get_all_projects_sql = "SELECT * FROM projects_project;";
  $get_all_projects_result = pg_query($pg_resource, $get_all_projects_sql);
  if( !empty($get_all_projects_result) )
  {
    while($get_all_projects_record = pg_fetch_assoc($get_all_projects_result) )
    {
        foreach($new_roles_array as $new_role)
        {
           // Do upsert for role
          $new_role['computable'] = ($new_role['computable'])?'t':'f';

           $upsert_role_sql = ""
           ."INSERT INTO users_role(name, slug, permissions, \"order\", computable, project_id) "
           ."SELECT '".addslashes($new_role['name'])."', '".addslashes($new_role['slug'])."', '{".implode( "," ,$new_role['permissions'])."}','".$new_role['order']."','".$new_role['computable']."',".$get_all_projects_record['id']." "
           ." WHERE NOT EXISTS (SELECT * FROM users_role WHERE project_id=".$get_all_projects_record['id']." AND slug='".trim(strtolower($new_role['slug']))."');"; 
           $upsert_role_result = pg_query($pg_resource, $upsert_role_sql);
           //echo_log("Affected rows ".pg_affected_rows($upsert_role_result));

        }

        foreach($new_task_statuses as $new_item)
        {
          $new_item['is_closed'] = ($new_item['is_closed'])?'t':'f';
           $new_item_sql = ""
           ."INSERT INTO projects_taskstatus(name, \"order\", is_closed, color ,project_id, slug) "
           ."SELECT '".addslashes($new_item['name'])."',".addslashes($new_item['order']).",'".addslashes($new_item['is_closed'])."','".addslashes($new_item['color'])."',".($get_all_projects_record['id']).", '".addslashes($new_item['slug'])."' "
           ." WHERE NOT EXISTS (SELECT * FROM projects_taskstatus WHERE project_id=".$get_all_projects_record['id']." AND slug='".trim(strtolower($new_item['slug']))."');"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);
        }

        foreach($new_us_statuses as $new_item)
        {
          $new_item['is_closed'] = ($new_item['is_closed'])?'t':'f';
          $new_item['is_archived'] = ($new_item['is_archived'])?'t':'f';
          $new_item['wip_limit'] = empty($new_item['wip_limit'])?"NULL":$new_item['wip_limit'];

          $new_item_sql = ""
           ."INSERT INTO projects_userstorystatus(name, \"order\", is_closed, color ,project_id, slug, is_archived, wip_limit) "
           ."SELECT '".addslashes($new_item['name'])."',".addslashes($new_item['order']).",'".addslashes($new_item['is_closed'])."','".addslashes($new_item['color'])."',".($get_all_projects_record['id']).", '".addslashes($new_item['slug'])."','".$new_item['is_archived']."',".$new_item['wip_limit']." "
           ." WHERE NOT EXISTS (SELECT * FROM projects_userstorystatus WHERE project_id=".$get_all_projects_record['id']." AND slug='".trim(strtolower($new_item['slug']))."');"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);
        }

        foreach($new_issue_statuses as $new_item)
        {
          $new_item['is_closed'] = ($new_item['is_closed'])?'t':'f';
           $new_item_sql = ""
           ."INSERT INTO projects_issuestatus(name, \"order\", is_closed, color ,project_id, slug) "
           ."SELECT '".addslashes($new_item['name'])."',".addslashes($new_item['order']).",'".addslashes($new_item['is_closed'])."','".addslashes($new_item['color'])."',".($get_all_projects_record['id']).", '".addslashes($new_item['slug'])."' "
           ." WHERE NOT EXISTS (SELECT * FROM projects_issuestatus WHERE project_id=".$get_all_projects_record['id']." AND slug='".trim(strtolower($new_item['slug']))."'  );"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);
        }


        // CUSTOM ATTRIBUTES
        $created_date = date('Y-m-d H:i:s');


        $attr_count = 0;
        foreach($us_custom_attrs as $new_item)
        {
          $attr_count++;
           $new_item_sql = ""
           ."INSERT INTO custom_attributes_userstorycustomattribute(name, description ,\"order\", project_id, created_date, modified_date, type) "//field_type,
           ."SELECT '".addslashes($new_item['name'])."','".addslashes($new_item['description'])."',".$attr_count.",".($get_all_projects_record['id']).", '".$created_date."','".$created_date."', 'text' "
           ." WHERE NOT EXISTS (SELECT * FROM custom_attributes_userstorycustomattribute WHERE project_id=".$get_all_projects_record['id']." AND name='".trim(($new_item['name']))."'  );"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);

        }

      $attr_count = 0;
        foreach($task_custom_attrs as $new_item)
        {
          $attr_count++;

           $new_item_sql = ""
           ."INSERT INTO custom_attributes_taskcustomattribute(name, description ,\"order\", project_id, created_date, modified_date, type) "//field_type, 
           ."SELECT '".addslashes($new_item['name'])."','".addslashes($new_item['description'])."',".$attr_count.",".($get_all_projects_record['id']).", '".$created_date."','".$created_date."', 'text' "//'TEXT' ,
           ." WHERE NOT EXISTS (SELECT * FROM custom_attributes_taskcustomattribute WHERE project_id=".$get_all_projects_record['id']." AND name='".trim(($new_item['name']))."'  );"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);

        }

        $attr_count = 0;
        foreach($issue_custom_attrs as $new_item)
        {
          $attr_count++;

           $new_item_sql = ""
           ."INSERT INTO custom_attributes_issuecustomattribute(name, description ,\"order\", project_id, created_date, modified_date, type) "//field_type, 
           ."SELECT '".addslashes($new_item['name'])."','".addslashes($new_item['description'])."',".$attr_count.",".($get_all_projects_record['id']).", '".$created_date."','".$created_date."', 'text' "//'TEXT' ,
           ." WHERE NOT EXISTS (SELECT * FROM custom_attributes_issuecustomattribute WHERE project_id=".$get_all_projects_record['id']." AND name='".trim(($new_item['name']))."'  );"; 
           $new_item_result = pg_query($pg_resource, $new_item_sql);

        }


    }      
  }
};

/**
* Function to get current saturday number 
*
*/
function getCurrentSaturdayNumber($date = false) {

    if( $date !== false )
    {
      $indian_current_time = new DateTime(strtotime($date), new DateTimeZone('Asia/Kolkata'));
    }else
    {
      echo_log("setting indian current time, no date given.");
      $indian_current_time = new DateTime(null, new DateTimeZone('Asia/Kolkata'));
    }

    $current_day_number =  $indian_current_time->format('N');
    $current_day =  $indian_current_time->format('d');

    if( intval($current_day_number) !== 6 )
    {
      echo_log("Today is not Saturday!!");
      return false;
    }


    if( $current_day - 7 < 0 )
    {
      // It is first saturday
      return 1;
    }

    if( $current_day_number == 6 &&   $current_day - 14 < 0 )
    {
      // It is second saturday
      return 2;
    }

    if( $current_day_number == 6 &&  $current_day - 21 < 0 )
    {
      // It is third saturday
      return 3;
    }

    if( $current_day_number == 6 &&   $current_day - 28 < 0 )
    {
      // It is fourth saturday
      return 4;
    }

    if( $current_day_number == 6 &&  $current_day - 35 < 0 )
    {
      // It is fifth saturday
      return 5;
    }

    // There can be max 5 saturdays in any month
    return false;
}
/***
* Function to update punchout time if not given.
*
*/
function update_punchout_times($mysql_conn)
{
    // Check if time is 11 PM then procced
    $hour_to_update_punchouts = 23;
    $mins_to_update_punchouts = 39;
    $indian_current_time = new DateTime(null, new DateTimeZone('Asia/Kolkata'));
    $utc_current_time = new DateTime(null, new DateTimeZone('UTC'));
    $utc_current_time->format('Y-m-d H:i:s');


    
    

    
    // Get all punchin records when punch out not set
    $current_hr = $indian_current_time->format('H');
    $current_min = $indian_current_time->format('i');
    
    if( intval($current_hr) < $hour_to_update_punchouts )
    {
        echo_log("Time is not yet to update the punch outs.");
        return false;
    }
    if( intval($current_min) < $mins_to_update_punchouts )
    {
        echo_log("Time is not yet to update the punch outs.");
        return false;
    }
    echo_log("Time is correct to update the punch outs.");

    // Update all records set punch out time 8:00 PM
    $punch_out_utc_time = new DateTime(null, new DateTimeZone('UTC'));
    $punch_out_utc_time = $utc_current_time->format('Y-m-d 13:30:00');

    $punch_out_note = "System Auto Punch Out.";
    $punch_out_time_offset = $indian_current_time->format('P');
    $punch_out_user_time = $indian_current_time->format('Y-m-d 19:00:00');
    $new_state = 'PUNCHED OUT';

    $sql_get_incomplete_punch_ins = "UPDATE ohrm_attendance_record "
    ." SET "
    ." punch_out_utc_time = '$punch_out_utc_time', "
    ." punch_out_note = '$punch_out_note', "
    ." punch_out_time_offset = '$punch_out_time_offset', "
    ." punch_out_user_time = '$punch_out_user_time', "
    ." state = '$new_state' "
    ." WHERE "
    ." state = 'PUNCHED IN' AND punch_in_utc_time "
    ." BETWEEN '".$utc_current_time->format('Y-m-d 00:00:00')."' "
    ." AND '".$utc_current_time->format('Y-m-d 23:59:59')."'";

    $mysql_conn->query($sql_get_incomplete_punch_ins);
    echo_log("Affected Rows " . $mysql_conn->affected_rows);


    ////
    // Check if today is sunday or not working satur day

    $current_day_number =  $indian_current_time->format('N');
    $current_day =  $indian_current_time->format('d');

    if( empty($current_day_number) ||  $current_day_number > 6 )
    {
      echo_log("INVALID DAY NUMBER OR Today is sunday, hence NOT processing the auto leave code.");
      return false;
    }

    $saturday_number = getCurrentSaturdayNumber();

    if( $saturday_number !== false && in_array($saturday_number, array(1,3,5)) )
    {
      echo_log("Today is Saturday and its number ".$saturday_number." in this month.");
      echo_log("Non working saturday, hence not processing auto leave code.");
      return false;
    }

    /////////////////////////////////////////////////////////////////////////////////////
    // Check if today is holiday
    $check_if_today_is_holiday_sql = "SELECT id, description FROM ohrm_holiday WHERE "
    ." date = '".$indian_current_time->format('Y-m-d')."' "
    ." OR (recurring = '1' AND date LIKE '%-".$indian_current_time->format('m-d')."' ) LIMIT 1" ;

    $check_if_today_is_holiday_result = $mysql_conn->query($check_if_today_is_holiday_sql);

    if( empty($check_if_today_is_holiday_result) )
    {
      echo_log("Error occurred in fetching holidays. NOT processing auto leave code.");
      return false;
    }
    $check_if_today_is_holiday_record = mysqli_fetch_array($check_if_today_is_holiday_result);

    if( !empty($check_if_today_is_holiday_record) )
    {
      echo_log("Today is Holiday!! ".$check_if_today_is_holiday_record['description']);
      return false;
    }

    echo_log("NO holiday today!!");

    

    // Get employees having no punch in and punch out today
    $get_employees_on_leave_today_sql 
    = "SELECT emp.emp_number,emp.emp_firstname,emp.emp_lastname,emp.emp_work_email,ouserrole.name as role_name, ouserrole.display_name as role_display_name  "
    ." FROM `hs_hr_employee` as emp "
    ." INNER JOIN ohrm_user as ousers ON ousers.emp_number=emp.employee_id "
    ." INNER JOIN ohrm_user_role as ouserrole ON ousers.user_role_id=ouserrole.id "
    ." INNER JOIN ohrm_employment_status as oes ON (emp.emp_status=oes.id AND (oes.name='Active' OR oes.name='active')) "
    ." LEFT JOIN ohrm_leave_request as olr ON emp.emp_number=olr.emp_number AND olr.date_applied='".$utc_current_time->format('Y-m-d')."' "
    ." WHERE ouserrole.name <> 'Admin' AND "
    ." olr.emp_number IS NULL AND emp.termination_id IS NULL AND emp.emp_number NOT IN "
    ." ( SELECT employee_id FROM ohrm_attendance_record as oar "
    ." WHERE oar.punch_in_utc_time "
    ." BETWEEN '".$utc_current_time->format('Y-m-d 00:00:00')."' "
    ." AND '".$utc_current_time->format('Y-m-d 23:59:59')."') ";

    $get_employees_on_leave_today_result = $mysql_conn->query($get_employees_on_leave_today_sql);

    if( !empty($get_employees_on_leave_today_result) )
    {

      // Fetch leave type id
      $leave_type_id = null;
      $fetch_leave_type_sql = "SELECT id "
      ." FROM ohrm_leave_type "
      ." WHERE name='Casual Leave' ";

      $fetch_leave_type_result = $mysql_conn->query($fetch_leave_type_sql);
      if( !empty($fetch_leave_type_result) )
      {
        //
        $fetch_leave_type_record = mysqli_fetch_array($fetch_leave_type_result);
        $leave_type_id = $fetch_leave_type_record['id'];
      }

      if( empty($leave_type_id) )
      {
        echo_log("Leave Type 'Casual Leave' not found in database, can  not proceed.");
        return false;
      }



      echo_log("Processing employee on leave records....");
      $employes_to_send_email = array();
      while( $get_employees_on_leave_today_record = mysqli_fetch_array($get_employees_on_leave_today_result) )
      {
        
        $date_applied = $indian_current_time->format('Y-m-d');
        $emp_number = $get_employees_on_leave_today_record['emp_number'];
        $comments = "Attendance Record Not Found.";


        /////////////////////////////////////////////////////////////////
        // Insert leave request
        echo_log("Inserting leave request");
        $insert_leave_request_sql = "INSERT INTO ohrm_leave_request "
        ."(id, leave_type_id, date_applied, emp_number, comments) VALUES "
        ."(NULL,".$leave_type_id.",'".$date_applied."',".$emp_number.",'".$comments."')";

        if( $mysql_conn->query($insert_leave_request_sql) )
        {

            echo_log("Inserting leave");
            $leave_request_id = $mysql_conn->insert_id;
            $length_hours = '8';
            $length_days = '1';
            $leave_status = '1'; // Pending for Approval
            $insert_leave_sql = "INSERT INTO ohrm_leave "
            ."(id, leave_request_id, date, leave_type_id, emp_number, comments, length_hours, length_days, status) VALUES "
            ."(NULL,".$leave_request_id.",'".$date_applied."',".$leave_type_id.",".$emp_number.",'".$comments."',".$length_hours.",".$length_days.",".$leave_status." )";


            if( $mysql_conn->query($insert_leave_sql) )
            {

               $email_message = '';
               $email_message .= 'You were not available for work on date '.$utc_current_time->format('Y-m-d')
               .' Hence leave as been applied for the date.';
               $applied_leave_id = $mysql_conn->insert_id;

               // 
               $update_leave_entitlement_sql = "UPDATE ohrm_leave_entitlement SET "
               ."days_used = days_used+1 WHERE emp_number=".$emp_number." LIMIT 1";
               $mysql_conn->query($update_leave_entitlement_sql);

               // Check if leave balance is negative
               $get_leave_balance_sql = "SELECT id,emp_number FROM  ohrm_leave_entitlement "
               . " WHERE emp_number=".$emp_number." AND days_used > no_of_days LIMIT 1 ";
               $get_leave_balance_result = $mysql_conn->query($get_leave_balance_sql);
               if( !empty($get_leave_balance_result) ) 
               {
                  $get_leave_balance_record = mysqli_fetch_array($get_leave_balance_result);
                  if( !empty($get_leave_balance_record) 
                    && isset($get_leave_balance_record['emp_number']) 
                    && !empty($applied_leave_id)
                    )
                  {
                      // Leave balance exceeded for this user.
                      // Mark the leave as LWP
                      $update_applied_leave_sql = "UPDATE ohrm_leave SET leave_without_pay='1' "
                      ." WHERE id=".$applied_leave_id;

                      if( $mysql_conn->query($update_applied_leave_sql) )
                      {
                          $email_message .= ' As there was no leave balance for Casual Leaves, system is setting this leave as LOS OF PAY.';
                      }

                  }

               }

               // Collect user to send email
               $employes_to_send_email[] = array(
                  'to_email' => $get_employees_on_leave_today_record['emp_work_email'],
                  'to_name' => ucfirst($get_employees_on_leave_today_record['emp_firstname'])
                    . ucfirst($get_employees_on_leave_today_record['emp_lastname']),
                  'message' => $email_message
                );

            }else
            {
              echo_log("Leave insertion failed deleting leave request.");
              $mysql_conn->query("DELETE FROM ohrm_leave_request WHERE id = ".$leave_request_id);
              $mysql_conn->query("DELETE FROM ohrm_leave WHERE leave_request_id = ".$leave_request_id);
            }

        }
        /////////////////////////////////////////////////////////////////

      }// End of while loop checking each employee

      // Send email to  employees
      send_auto_leave_email($employes_to_send_email);

    }
    

    /////////////////////////////////////////////////////////////////////////////////////
  

    return true;

};

function send_auto_leave_email( $emp_to_send_email = array() )
{
   if( empty($emp_to_send_email) || !is_array($emp_to_send_email) )
      return true;

    foreach($emp_to_send_email as $emp)
    {
        $emp['message'] 
        = 'Hello '.$emp['to_name'].',<br/><br/>'
          . $emp['message']
          . '<br/><br/>';

        $to = array(
          'email' => $emp['to_email'],
          'name' => $emp['to_name'],
        );
        $subject = "Applying auto leave : Attendance not found.";
        $message = $emp['message'];

        send_email_from_system($to, $subject , $message);
    }

    return true;
}

function send_email_from_system($to, $subject, $message)
{
    $from['email'] = 'bugtracker@progfeel.in';
    $from['name'] = 'Upasthiti';
    $message .= '<br/><br/>Please contact administrator if you have any queries.<br/>-Upasthiti';
    return send_email($to, $from, $subject, $message);
} 

function send_email( $to, $from, $subject, $message, $cc = array(), $bcc = array(), $headers = array() )
{

  if( Emails_ON !== '1' )
  {
    echo_log("Send email setting is OFF. So not sending email.");
    return false;
  }

  $mail = new PHPMailer;

  //$mail->SMTPDebug = 3;                               // Enable verbose debug output

  $mail->isSMTP();                                      // Set mailer to use SMTP
  $mail->Host = 'smtp.mailgun.org';  // Specify main and backup SMTP servers
  $mail->SMTPAuth = true;                               // Enable SMTP authentication
  $mail->Username = 'bugtracker@progfeel.in';                 // SMTP username
  $mail->Password = 'splendornetNt87';                           // SMTP password
  $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
  $mail->Port = 587;                                    // TCP port to connect to
  $mail->From = $from['email'];
  $mail->FromName = $from['name'];
  $mail->addAddress($to['email'], $to['name']);
  $mail->addCC(Sub_Admin); 
  
  $mail->isHTML(true);                                  // Set email format to HTML

  $mail->Subject = '[Upasthiti] '.$subject;
  $mail->Body    = $message;
  $mail->AltBody = strip_tags($message);

  $email_sent =  $mail->send();
  
  echo_log('Sending message');
  echo_log($message);

  if(!$email_sent) {
      echo_log('Message could not be sent.');
      echo_log('Mailer Error: ' . $mail->ErrorInfo);
  } else {
      echo_log('Message has been sent');
  }


  return $email_sent;
}

function send_email_to_admin($subject, $message)
{

  $to = array(
     'email' =>  Admin,
     'name' => 'Admin'
  );
  return send_email_from_system($to, $subject, $message);

}
