<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot . '/course/renderer.php');
require_once($CFG->dirroot.'/course/format/ucicactivity/lib.php');


class format_ucicactivity_course_renderer extends core_course_renderer{
    

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

            if($users->count >0){
                $state_mod++;
                if($actio == 'post'){
                    $state_mod++;
                }
            }

            if( is_object($grade_item_mod) && $grade_mod->finalgrade != null && $actio == 'view'){
                $state_mod++;   
            }

           /* echo "<pre>";
            print_r($actio);
            print_r($mod->modname . '-----------');
            print_r($users);
            print_r($state_mod);
            echo "</pre>";*/

            switch ($state_mod) {
                case 1:
                    $modclasses .= ' activity-progress ';
                    break;
                
                case 2:
                    $modclasses .= ' activity-finished ';
                    break;

                case 'Lorem ipsum dolor sit amet':
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
        return html_writer::start_tag('ul', array('class' => 'ucicactivity'));
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


    public function section_title($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section));
    }

    
    public function section_title_without_link($section, $course) {
        return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, false));
    }


    

}
