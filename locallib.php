<?php


require_once($CFG->dirroot.'/report/participation/locallib.php');
require_once($CFG->libdir.'/gradelib.php');

defined('MOODLE_INTERNAL') || die();

Class ucicactivity_LocalLib{

	public function ucicactivity_LocalLib(){
		global $DB, $CFG, $USER;
		$this->db = $DB;
		$this->cfg = $CFG;
		$this->user = $USER;
	}

  public function state_module($course){
    $modinfo = get_fast_modinfo($course);
    $modules = $this->db->get_records_select('modules', "visible = 1", null, 'name ASC');

    $instanceoptions = array();
    foreach ($modules as $module) {
        if (empty($modinfo->instances[$module->name])) {
            continue;
        }
        $instances = array();
        foreach ($modinfo->instances[$module->name] as $cm) {
            if (!$cm->has_view()) {
                continue;
            }
            if($this->db->record_exists('grade_items', array('courseid' => $course->id , 'itemname' => $cm->name, 'gradetype' => 1, 'hidden' => 0))){
              $instanceoptions[$cm->id] = $cm->name;
            }
            if (empty($modinfo->instances[$module->name])) {
            continue;
            }
        }
        if (count($instances) == 0) {
            continue;
        }
        $instanceoptions = array_merge($instances,$instanceoptions);
    }

    return $instanceoptions;
  }

  public function print_section($sectionid,$courseid,$userid){
      $modules_section = $this->db->get_record('course_sections',  array('id' => $sectionid));

      if( empty($modules_section->sequence) ){
          return 0;
      }
      $mods = explode(',', $modules_section->sequence);

      foreach ($mods as $key => $value) {

        $tm_instance = $this->db->get_record('course_modules',  array('id' => $value));

        $name_mod = $this->db->get_record('modules', array('id' => $tm_instance->module));
        /*echo "<pre>";
            print_r($name_mod);
            echo "</pre>";*/
        if($name_mod->name == 'label' 
          || $name_mod->name == 'book' 
          || $name_mod->name == 'chat' 
          || $name_mod->name == 'choice'
          || $name_mod->name == 'data'
          || $name_mod->name == 'folder'
          || $name_mod->name == 'glossary'
          || $name_mod->name == 'ucicbootstrap'
          || $name_mod->name == 'imscp'
          || $name_mod->name == 'lesson'
          || $name_mod->name == 'lti'
          || $name_mod->name == 'page'
          || $name_mod->name == 'resource'
          || $name_mod->name == 'survey'
          || $name_mod->name == 'url'
          || $name_mod->name == 'workshop'
          || $name_mod->name == 'attendance'){
          unset($mods[$key]);
        }
      }
/*
      echo "<pre>";
            print_r($mods);
            echo "</pre>";*/

      $sect_glob = 0;

      foreach ($mods as $value) {

        $tm_instance = $this->db->get_record('course_modules',  array('id' => $value));

        $name_mod = $this->db->get_record('modules', array('id' => $tm_instance->module));


            $actio = 'view';
            if($name_mod->name == 'forum' || $name_mod->name == 'assign'){
                $actio = 'post';
            }

            if($name_mod->name == 'feedback'){
                $actio = '';
            }

            $groupname = null;
           


            $context = context_course::instance($courseid);

            $actionoptions = report_participation_get_action_options();

            $logtable = report_participation_get_log_table_name();

            list($relatedctxsql, $params) = $this->db->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
            $params['roleid'] = 5;
            $params['instanceid'] = $tm_instance->id;
            $params['timefrom'] = 0;

            list($crudsql, $crudparams) = report_participation_get_crud_sql($actio);
            $params = array_merge($params, $crudparams);

            $ex_groups = ($groupname != null) ? $this->db->get_record( "groups" , array( 'courseid' => $courseid , 'id' =>  $idg->id ) ) : null;
            if(is_object($ex_groups)){
                $sqlgroups = " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = " . $ex_groups->id . ") ";
            }else{
                $sqlgroups = "";
            }

            $users = array();


             $sql = "SELECT ra.userid, COUNT(DISTINCT l.timecreated) AS count
                        FROM {user} u
                        JOIN {role_assignments} ra ON u.id = ra.userid AND ra.contextid $relatedctxsql AND ra.roleid = :roleid
                        $sqlgroups
                        LEFT JOIN {" . $logtable . "} l
                           ON l.contextinstanceid = :instanceid
                             AND l.timecreated > :timefrom" . $crudsql ."
                             AND l.edulevel = :edulevel
                             AND l.anonymous = 0
                             AND l.contextlevel = :contextlevel
                             AND (l.origin = 'web' OR l.origin = 'ws')
                             AND l.userid = ra.userid 
                             WHERE ra.userid =" . $userid;
              $groupbysql = " GROUP BY ra.userid";

              $params['edulevel'] = core\event\base::LEVEL_PARTICIPATING;
              $params['contextlevel'] = CONTEXT_MODULE;

            $sql .= $groupbysql;

            $users = $this->db->get_record_sql($sql, $params);

            switch ($name_mod->name) {
                case 'scorm':
                    // timeclose
                    $mod_close = $this->db->get_record('scorm',  array('id' => $tm_instance->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        //$sect_glob = 0;
                    } 
                    break;
                case 'quiz':
                    // timeclose
                    $mod_close = $this->db->get_record('quiz',  array('id' => $tm_instance->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        //$sect_glob = 0;
                    } 
                    break;
                case 'assign':
                    // duedate
                    $mod_close = $this->db->get_record('assign',  array('id' => $tm_instance->instance));
                    if( time() - $mod_close->duedate >= 0){
                        //$sect_glob = 0;
                    } 
                    break;
                case 'feedback':
                    // timeclose
                    $mod_close = $this->db->get_record('feedback',  array('id' => $tm_instance->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        //$sect_glob = 0;
                    } 
                    break;
            }

            $paramMod = array(
                'courseid' => $courseid,
                //'itemname' => $mod->name,
                'iteminstance' => $tm_instance->instance,
                'itemmodule' => $name_mod->name,
                'hidden' => 0
                );

            $grade_item_mod = $this->db->get_record('grade_items',  $paramMod);
            
            if( is_object($grade_item_mod) ){
                $grade_mod = $this->db->get_record('grade_grades',  array('itemid' => $grade_item_mod->id, 'userid' => $userid));
            }

            if(is_object($users) && $users->count >0){
                $sect_glob++;
                if($actio == 'post'){
                    $sect_glob++;
                }
            }

            if( is_object($grade_item_mod) && is_object($grade_mod) && $grade_mod->finalgrade != null && $actio == 'view'){
                $sect_glob++;   
            }


            $tm_mo = get_coursemodule_from_id($name_mod->name, $tm_instance->instance);

            /*echo "<pre>";
            print_r($name_mod->name);
            echo "<br>";
            print_r($tm_instance->instance);
            echo "<br>";
            print_r($tm_mo);
            echo "</pre>";*/

            if($name_mod->name == 'feedback'){
                $tmp_feedback = $this->db->get_record('feedback_completed',  array('feedback' => $tm_instance->instance, 'userid' => $userid));
                if(is_object($tmp_feedback)){
                    $sect_glob++;   
                }
            }


            /*$paramMod = array(
                'courseid' => $courseid,
                'iteminstance' => $mod->instance,
                'itemmodule' => $name_mod->modname,
                'hidden' => 0
                );

            $grade_item_mod = $DB->get_record('grade_items',  $paramMod);*/

            


            
        
      }/*
            echo "GLOBAL<pre>";
            print_r($sect_glob . ' ----- ' . count($mods));
            echo "</pre>";*/

            if($sect_glob == count($mods) * 2){
              return 2;
            }else if($sect_glob == 0){
              return 0;
            }else{
              return 1;
            }
  }

	public function bar_values($courseid){

		global $CFG,$DB, $USER;
        

        $total = 0;
        $score = 0;

        $userid = $this->user->id;
        $course = $this->db->get_record("course",array( 'id' => $courseid));

        $roleid     = 5;
        $instanceid = optional_param('instanceid', 0, PARAM_INT);
        $groupname  = (isset($_GET['grupo'])) ? $_GET['grupo'] : null;
        $timefrom   = 0;
        $action     = 'post';



        $id = $course->id;


        $context = context_course::instance($course->id);
        $roles = get_roles_used_in_context($context);
        $guestrole = get_guest_role();
        $roles[$guestrole->id] = $guestrole;
        $roleoptions = role_fix_names($roles, $context, ROLENAME_ALIAS, true);

        $modinfo = get_fast_modinfo($course);

        $modules = $this->db->get_records_select('modules', "visible = 1", null, 'name ASC');
        //print_r($modules);
        $instanceoptions = array();
        foreach ($modules as $module) {
            if (empty($modinfo->instances[$module->name])) {
                continue;
            }

            $instances = array();
            foreach ($modinfo->instances[$module->name] as $cm) {

                if (!$cm->has_view()) {
                    continue;
                }

                if($this->db->record_exists('grade_items', array('courseid' => $id , 'itemname' => $cm->name, 'gradetype' => 1, 'hidden' => 0))){
                  $instanceoptions[$cm->id] = $cm->name;
                }
                if (empty($modinfo->instances[$module->name])) {
                continue;
                }
            }


            if (count($instances) == 0) {
                continue;
            }

            $instanceoptions = array_merge($instances,$instanceoptions);

        }
        $instance_grader = array();

        $sqlGroup = $this->db->get_records('groups', array('courseid' => $id));

        $idg = 0;
        foreach ($sqlGroup as $llavee => $valoor) {
            $gru = $this->db->get_record('groups_members',array('userid' => $userid, 'groupid' => $valoor->id));
                if($gru != null) $idg = $gru;
        }

        foreach ($instanceoptions as $key => $value) {
          $mysquiz = get_coursemodule_from_id('quiz', $key);
          if(is_object($mysquiz)){
            if($mysquiz->visible == 0){
              unset($instanceoptions[$key]);
            }else{
              if(!empty($mysquiz->availability)){
                  $temmp = json_decode($mysquiz->availability);
                  if($idg != 0){
                      if($temmp->op == '&'){
                        if($temmp->c[0]->type == 'group' && $temmp->c[0]->id != $idg->id){
                            unset($instanceoptions[$key]);
                        }else if($temmp->c[0]->type == 'grouping'){
                            if(!$this->db->record_exists('groupings_groups',array('groupingid' => $temmp->c[0]->id, 'groupid' => $idg->id))){
                              unset($instanceoptions[$key]);
                            }
                        }
                      }else{
                        if($temmp->c[0]->type == 'group' && $temmp->c[0]->id == $idg->id){
                            unset($instanceoptions[$key]);
                        }else if($temmp->c[0]->type == 'grouping'){
                            if($this->db->record_exists('groupings_groups',array('groupingid' => $temmp->c[0]->id, 'groupid' => $idg->id))){
                              unset($instanceoptions[$key]);
                            }
                        }
                      }
                  }

                  
              }
            }
          }
        }

        foreach ($instanceoptions as $key => $value) {
          $mysquiz = get_coursemodule_from_id('quiz', $key);
          if(is_object($mysquiz)){
            $instance_grader[$key] = $mysquiz->instance;
          }else{
            $myscorm = get_coursemodule_from_id('scorm',  $key);
            if(is_object($myscorm)){
              $instance_grader[$key] = $myscorm->instance;
            }else{
              $mygra = get_coursemodule_from_id('assign',  $key);
              if(is_object($mygra)){
                $instance_grader[$key] = $mygra->instance;
              }
            }
          }
        }

        foreach ($instanceoptions as $lave => $alor) {
          if(!isset($instance_grader[$lave])){
            unset($instanceoptions[$lave]);
          }
        }

        if(count($instanceoptions) <=0){
            return array( 'good' => -1);
        }

        $context = context_course::instance($course->id);

        $actionoptions = report_participation_get_action_options();

        $logtable = report_participation_get_log_table_name();

        if (!empty($roleid)) {

            list($relatedctxsql, $params) = $this->db->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
            $params['roleid'] = $roleid;
            $params['instanceid'] = (array_keys($instanceoptions) != null) ? array_keys($instanceoptions)[0] : 0;
            $params['timefrom'] = $timefrom;

                list($crudsql, $crudparams) = report_participation_get_crud_sql($action);
                $params = array_merge($params, $crudparams);

                $ex_groups = ($groupname != null) ? $this->db->get_record( "groups" , array( 'courseid' => $course->id , 'id' =>  $idg->id ) ) : null;
                if(is_object($ex_groups)){
                  $sqlgroups = " JOIN {groups_members} gm ON (gm.userid = u.id AND gm.groupid = " . $ex_groups->id . ") ";
                }else{
                  $sqlgroups = "";
                }

            $users = array();

                $sql = "SELECT ra.userid, u.username, u.firstname, u.lastname, u.email, u.department as departament
                          FROM {user} u
                          JOIN {role_assignments} ra ON u.id = ra.userid AND ra.contextid $relatedctxsql AND ra.roleid = :roleid
                          $sqlgroups
                          LEFT JOIN {" . $logtable . "} l
                             ON l.contextinstanceid = :instanceid
                               AND l.timecreated > :timefrom" . $crudsql ."
                               AND l.edulevel = :edulevel
                               AND l.anonymous = 0
                               AND l.contextlevel = :contextlevel
                               AND (l.origin = 'web' OR l.origin = 'ws')
                               AND l.userid = ra.userid";
                //$groupbysql = " GROUP BY ra.userid";
                $groupbysql = " WHERE ra.userid = " . $userid;

                $params['edulevel'] = core\event\base::LEVEL_PARTICIPATING;
                $params['contextlevel'] = CONTEXT_MODULE;

                $sql .= $groupbysql;

                $users = $this->db->get_records_sql($sql, $params);
                $tmpo = array();
                foreach ($users as $key => $value) {
                  $tmpo[] = $value;
                }
                $users = $tmpo;


                $temp = 0;
                foreach ($instanceoptions as $key => $value) {
                  $params['instanceid'] = $key;
                  $sql = "SELECT ra.userid, COUNT(DISTINCT l.timecreated) AS count
                            FROM {user} u
                            JOIN {role_assignments} ra ON u.id = ra.userid AND ra.contextid $relatedctxsql AND ra.roleid = :roleid
                            $sqlgroups
                            LEFT JOIN {" . $logtable . "} l
                               ON l.contextinstanceid = :instanceid
                                 AND l.timecreated > :timefrom" . $crudsql ."
                                 AND l.edulevel = :edulevel
                                 AND l.anonymous = 0
                                 AND l.contextlevel = :contextlevel
                                 AND (l.origin = 'web' OR l.origin = 'ws')
                                 AND l.userid = ra.userid";
                  //$groupbysql = " GROUP BY ra.userid";
                  $groupbysql = " WHERE ra.userid = " . $userid;

                  $params['edulevel'] = core\event\base::LEVEL_PARTICIPATING;
                  $params['contextlevel'] = CONTEXT_MODULE;

                  $sql .= $groupbysql;

                  $notes = $this->db->get_records_sql($sql, $params);
                  $tmpo = array();
                  foreach ($notes as $y => $ue) {
                    $tmpo[] = $ue;
                  }
                  $notes = $tmpo;


                  $count = array();
                  foreach ($notes as $k => $v) {
                    $total++;
                    $users[$k]->count[$temp]['ingreso_actividad'] = ($notes[$k]->count>0) ? 'Si': 'No';
                    if($notes[$k]->count>0){
                        $score++;
                    }
                    $users[$k]->count[$temp]['nombre_actividad'] = $value;

                  }
                  $temp++;
                }

              /*
              echo "<pre>";
              print_r($users);
              echo "</pre>";*/


        }

        $good = round(($score != 0) ? (($score / $total) * 100) : 0);
        $bad = 100 - $good;

        return array( 'good' => $good , 'bad' => $bad);

	}


}

