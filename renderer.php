<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot.'/course/format/ucicactivity/lib.php');
require_once($CFG->dirroot.'/course/format/ucicactivity/locallib.php');



class format_ucicactivity_course_renderer extends core_course_renderer{

	
	public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $USER;

        $output = '';
        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $movingpix = new pix_icon('movehere', get_string('movehere'), 'moodle', array('class' => 'movetarget'));
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of modules visible to user (excluding the module being moved if there is one)
        $moduleshtml = array();
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                if ($modulehtml = $this->course_section_cm_list_item($course,
                        $completioninfo, $mod, $sectionreturn, $displayoptions)) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($moduleshtml) || $ismoving) {
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', array('moveto' => $modnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag('li',
                            html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                            array('class' => 'movehere'));
                }

                $sectionoutput .= $modulehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag('li',
                        html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                        array('class' => 'movehere'));
            }
        }

        // Always output the section module list.
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        $output .= html_writer::end_tag('div'); //end collapse

        return $output;
    }
    

    public function course_section_cm_list_item($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        global $COURSE,$USER,$DB;
        $output = '';
        if ($modulehtml = $this->course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {

            $state_mod = 0;

            $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;

            //INSTANCE ID
            $actio = 'view';
            if($mod->modname == 'forum' || $mod->modname == 'assign'){
                $actio = 'post';
            }

            if($mod->modname == 'feedback'){
                $actio = '';
            }

            $groupname = null;



            $context = context_course::instance($COURSE->id);

            $actionoptions = report_participation_get_action_options();

            $logtable = report_participation_get_log_table_name();

            list($relatedctxsql, $params) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED, 'relatedctx');
            $params['roleid'] = 5;
            $params['instanceid'] = $mod->id;
            $params['timefrom'] = 0;

            list($crudsql, $crudparams) = report_participation_get_crud_sql($actio);
            $params = array_merge($params, $crudparams);

            $ex_groups = ($groupname != null) ? $DB->get_record( "groups" , array( 'courseid' => $course->id , 'id' =>  $idg->id ) ) : null;
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
                             WHERE ra.userid =" . $USER->id;
              $groupbysql = " GROUP BY ra.userid";

              $params['edulevel'] = core\event\base::LEVEL_PARTICIPATING;
              $params['contextlevel'] = CONTEXT_MODULE;

            $sql .= $groupbysql;

            $users = $DB->get_record_sql($sql, $params);

            $paramMod = array(
                'courseid' => $COURSE->id,
                'itemname' => $mod->name,
                'iteminstance' => $mod->instance,
                'itemmodule' => $mod->modname,
                'hidden' => 0
                );

            $grade_item_mod = $DB->get_record('grade_items',  $paramMod);

            if( is_object($grade_item_mod) ){
                $grade_mod = $DB->get_record('grade_grades',  array('itemid' => $grade_item_mod->id, 'userid' => $USER->id));
            }


            if(is_object($users) && $users->count >0){
                $state_mod++;
                if($actio == 'post'){
                    $state_mod++;
                }
            }

            if( is_object($grade_item_mod) && is_object($grade_mod) && $grade_mod->finalgrade != null && $actio == 'view'){
                $state_mod++;
            }


            $tm_mo = get_coursemodule_from_id($mod->modname, $mod->id);

            if($mod->modname == 'feedback'){
                $tmp_feedback = $DB->get_record('feedback_completed',  array('feedback' => $tm_mo->instance, 'userid' => $USER->id));
                if(is_object($tmp_feedback)){
                    $state_mod++;
                /*echo "<pre>";
                print_r($users);
                print_r($state_mod);
                echo "</pre>";*/
                }
            }

            /*echo "<pre>";
            print_r($tm_mo);
            echo "</pre>";*/

            switch ($mod->modname) {
                case 'scorm':
                    // timeclose
                    $mod_close = $DB->get_record('scorm',  array('id' => $tm_mo->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        $state_mod = 3;
                    }
                    break;
                case 'quiz':
                    // timeclose
                    $mod_close = $DB->get_record('quiz',  array('id' => $tm_mo->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        $state_mod = 3;
                    }
                    break;
                case 'assign':
                    // duedate
                    $mod_close = $DB->get_record('assign',  array('id' => $tm_mo->instance));
                    if( time() - $mod_close->duedate >= 0){
                        $state_mod = 3;
                    }
                    break;
                case 'feedback':
                    // timeclose
                    $mod_close = $DB->get_record('feedback',  array('id' => $tm_mo->instance));
                    if( time() - $mod_close->timeclose >= 0){
                        $state_mod = 3;
                    }
                    break;
                case 'resource':
                    // timeclose
                        $state_mod = 3;
                    break;
            }



            switch ($state_mod) {
                case 1:
                    $modclasses .= ' activity-progress ';
                    break;

                case 2:
                    $modclasses .= ' activity-finished ';
                    break;

                case 3:
                    $modclasses .= ' activity-blocked ';
                    break;
            }


            $output .= html_writer::tag('li', $modulehtml, array('class' => $modclasses, 'id' => 'module-' . $mod->id));
        }
        return $output;
    }

    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        $output = '';
        if (!$mod->uservisible && empty($mod->availableinfo)) {
            return $output;
        }


        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        if ($this->page->user_is_editing()) {
            $output .= course_get_cm_move($mod, $sectionreturn);
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer'));

        $output .= html_writer::div('', $indentclasses);

        $output .= html_writer::start_tag('div');

        $cmname = $this->course_section_cm_name($mod, $displayoptions);


        if (!empty($cmname)) {
            $output .= html_writer::start_tag('div', array('class' => 'activityinstancess'));
            $output .= $cmname;

            $output .= $mod->afterlink;

            $output .= html_writer::end_tag('div');
        }

        $contentpart = $this->course_section_cm_text($mod, $displayoptions);
        $url = $mod->url;
        if (empty($url)) {
            $output .= $contentpart;
        }

        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $modicons .= ' '. $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $modicons .= $mod->afterediticons;
        }

        $modicons .= $this->course_section_cm_completion($course, $completioninfo, $mod, $displayoptions);

        if (!empty($modicons)) {
            $output .= html_writer::span($modicons, 'actions');
        }


        if (!empty($url)) {
            $output .= $contentpart;
        }

        $output .= $this->course_section_cm_availability($mod, $displayoptions);

        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }


}

class format_ucicactivity_renderer extends format_section_renderer_base{



    public function __construct(moodle_page $page, $target) {

        parent::__construct($page, $target);

        //this line creates a new class that can extend the 'core_course_renderer'
        $this->courserenderer = new format_ucicactivity_course_renderer($page, $target);

        // Since format_bish_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on

        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.

        $page->set_other_editing_capability('moodle/course:setcurrentsection');

    }


    protected function start_section_list() {
		return html_writer::start_tag('ul',array('class' => 'ucicactivity', 'id'=>'activity-accordion'));

    }


    protected function end_section_list() {
	        return html_writer::end_tag('ul');
    }


    protected function page_title() {
        return get_string('ucicactivitylyoutline', 'format_ucicactivity');
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false)
    {
        global $PAGE;

        if (!$PAGE->user_is_editing())
        {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage)
        {
            $url = course_get_url($course, $section->section);
        }
        else
        {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();
        if (has_capability('moodle/course:setcurrentsection', $coursecontext))
        {
            if ($course->marker == $section->section)
            {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $controls[] = html_writer::link($url, html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marked'),
                                    'class' => 'icon ', 'alt' => get_string('markedthistopic'))), array('title' => get_string('markedthistopic'), 'class' => 'editing_highlight'));
            }
            else
            {
                $url->param('marker', $section->section);
                $controls[] = html_writer::link($url, html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marker'),
                                    'class' => 'icon', 'alt' => get_string('markthistopic'))), array('title' => get_string('markthistopic'), 'class' => 'editing_highlight'));
            }
        }

        return array_merge($controls, parent::section_edit_controls($course, $section, $onsectionpage));
    }

/*
    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }


    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }*/

    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null) {
        global $PAGE,$USER;

        $locallib = new ucicactivity_LocalLib();

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section)) {
                //$sectionstyle = ' current';
            }
        }

        /*echo "<pre>";
        print_r($section);
        echo "</pre>";*/

        $modclasses  = '';
        switch ($locallib->print_section($section->id,$course->id,$USER->id)) {
                case 0:
                    $modclasses .= ' section-blocked ';
                    break;

                case 1:
                    $modclasses .= ' section-progress ';
                    break;

                case 2:
                    $modclasses .= ' section-finished ';
                    break;
            }

        $o.= html_writer::start_tag('li', array('id' => 'section-'.$section->section,
            'class' => 'section main clearfix'.$sectionstyle . $modclasses, 'role'=>'region',
            'aria-label'=> get_section_name($course, $section)));


        // Create a span that contains the section title to be used to create the keyboard section move menu.
        $o .= html_writer::tag('span', get_section_name($course, $section) .'/////', array('class' => 'hidden sectionname'));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));
		$rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $o.= html_writer::start_tag('div');
        $sectionname = html_writer::tag('span', $this->section_title($section, $course),
            array(
                'class'=> ' collapsable' . $classes,
                'target' => '#sect-'.$section->section,
            ));

        $o .= html_writer::tag('h3',$sectionname, array('class' => 'sectionname'));
        //$o.= $this->output->heading($sectionname, 3,'','esteeselid');

        //collapse init
        $o.= html_writer::start_tag('div', array('class' => 'panel-collapse', 'id'=>'sect-'.$section->section));

        $o.= html_writer::start_tag('div', array('class' => 'summary'));
        $o.= $this->format_summary_text($section);
        $o.= html_writer::end_tag('div');
        

        $context = context_course::instance($course->id);
        $o .= $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));


        return $o;
    }




}
