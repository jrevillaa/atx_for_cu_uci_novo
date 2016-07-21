<?php


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/course/format/ucicactivity/locallib.php');

$locallib = new ucicactivity_LocalLib();


if ($ucicactivity = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated ucicactivity param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}


$course = course_get_format($course)->get_course();
course_create_sections_if_missing($course, range(0, $course->numsections));

$renderer = $PAGE->get_renderer('format_ucicactivity');

if(!$USER->editing){
	//echo "El usuario esta editandoooo";
  $data_bar = $locallib->bar_values($course->id);

  if($data_bar['good'] == -1){

  }else{

    if($data_bar['bad'] == 100){
            $bad = 0;
        }else{
            $bad = $data_bar['bad'];
        }


    echo '<div class="progress">
              <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: ' . $data_bar['good']. '%;">
                ' . $data_bar['good'] . '% Completado
              </div>
              <div class="progress-bar progress-bar-danger" role="progressbar" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100" style="width: ' . $data_bar['bad'] . '%;">
                ' . $bad . '%
              </div>
          </div>
          <div class="legend">
            <ul>
              <li class="finished">
                <span class="color"></span>
                <span>Completado</span>
              </li>
              <li class="in-progress">
                <span class="color"></span>
                <span>En Progreso</span>
              </li>
              <li class="not-entered">
                <span class="color"></span>
                <span>No Revisado</span>
              </li>
            </ul>
          </div>';
  }


}
if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}


$PAGE->requires->js('/course/format/ucicactivity/format.js');
