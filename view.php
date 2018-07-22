<?php

require_once('../../config.php');
require_once('locallib.php');

Global $SESSION;

$id = optional_param('id', 0, PARAM_INT);
$sesskey = optional_param('sesskey', 0, PARAM_NOTAGS);



if ($id) {
    $cm = get_coursemodule_from_id('eitcoursegrouptools', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $coursetool = $DB->get_record('eitcoursegrouptools', array('id' => $cm->instance), '*', MUST_EXIST);
} 
elseif ($sesskey)
    {
     $cm = get_coursemodule_from_id('eitcoursegrouptools', $SESSION->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $coursetool = $DB->get_record('eitcoursegrouptools', array('id' => $cm->instance), '*', MUST_EXIST);
    
 
    }
else {



//    $parts = parse_url($_GET['sesskey']);
//    parse_str($parts['query'], $query);
//    echo $query['sesskey'];

    print_error('invalidcoursemodule');
}






require_login($course, true, $cm);
// initiate context
$context = context_module::instance($cm->id);

// define page
$PAGE->set_url('/mod/eitcoursegrouptools/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($coursetool->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($coursetool);
$PAGE->add_body_class('course-content');


//locallib
$instance = new mod_eitcoursegrouptools($cm->id, $coursetool, $cm, $course);

// output
$output = $PAGE->get_renderer('mod_eitcoursegrouptools');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($course->fullname));

$renderable = new \mod_eitcoursegrouptools\output\main('Course&Group-tools enhances functionality of e-learning and management. ');
echo $output->render($renderable);


$inactive = array();
$tabs = array();
$row = array();

$admingrps = has_capability('mod/eitcoursegrouptools:administrate_groups', $context);

if ($admingrps) {


    $row['administration'] = new tabobject('administration', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=administration', get_string('administration', 'eitcoursegrouptools'), get_string('administration_alt', 'eitcoursegrouptools'), false);
    $row['administration']->subtree['group_admin'] = new tabobject('group_admin', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=group_admin', 'Overview groups', get_string('group_administration_alt', 'eitcoursegrouptools'), false);
    $row['administration']->subtree['group_creation'] = new tabobject('group_creation', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id .'&amp;tab=group_creation', 'Administrate shell groups', get_string('group_creation_alt', 'eitcoursegrouptools'), false);
}
//Forum activity
if (has_capability('mod/eitcoursegrouptools:administrate_groups', $context)) {
    $row['activity'] = new tabobject('activity', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=activity', get_string('forumactivity', 'eitcoursegrouptools'), get_string('grading_alt', 'eitcoursegrouptools'), false);
    $row['activity']->subtree['activity'] = new tabobject('activity', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=activity', 'Activity by Users', "", false);
    $row['activity']->subtree['activitybygroup'] = new tabobject('activitybygroup', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id .'&amp;tab=activitybygroup', 'Activity by Groups', "", false);
    
}
//Student registration
if (has_capability('mod/eitcoursegrouptools:register_students', $context) || has_capability('mod/eitcoursegrouptools:register', $context)) {
    $row['selfregistration'] = new tabobject('selfregistration', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id .
            '&amp;tab=selfregistration', get_string('selfregistration', 'eitcoursegrouptools'), get_string('selfregistration_alt', 'eitcoursegrouptools'), false);
}

//if (has_capability('mod/eitcoursegrouptools:register_students', $context)) {
//    $row['import'] = new tabobject('import', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=import', get_string('import', 'eitcoursegrouptools'), get_string('import_desc', 'eitcoursegrouptools'), false);
//}
//if (has_capability('mod/eitcoursegrouptools:view_regs_group_view', $context) && has_capability('mod/eitcoursegrouptools:view_regs_course_view', $context)) {
//    $row['users'] = new tabobject('users', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=overview', get_string('users_tab', 'eitcoursegrouptools'), get_string('users_tab_alt', 'eitcoursegrouptools'), false);
//    $row['users']->subtree['overview'] = new tabobject('overview', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=overview', get_string('overview_tab', 'eitcoursegrouptools'), get_string('overview_tab_alt', 'eitcoursegrouptools'), false);
//    $row['users']->subtree['overview']->level = 2;
//    $row['users']->subtree['userlist'] = new tabobject('userlist', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=userlist', get_string('userlist_tab', 'eitcoursegrouptools'), get_string('userlist_tab_alt', 'eitcoursegrouptools'), false);
//    $row['users']->subtree['userlist']->level = 2;
//} else if (has_capability('mod/eitcoursegrouptools:view_regs_group_view', $context)) {
//    $row['users'] = new tabobject('users', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=overview', get_string('users_tab', 'eitcoursegrouptools'), get_string('users_tab_alt', 'eitcoursegrouptools'), false);
//} else if (has_capability('mod/eitcoursegrouptools:view_regs_course_view', $context)) {
//    $row['users'] = new tabobject('users', $CFG->wwwroot . '/mod/eitcoursegrouptools/view.php?id=' . $id . '&amp;tab=userlist', get_string('users_tab', 'eitcoursegrouptools'), get_string('users_tab_alt', 'eitcoursegrouptools'), false);
//}

if (!isset($SESSION->mod_eitcoursegrouptools)) {
    $SESSION->mod_eitcoursegrouptools = new stdClass();
}
$availabletabs = array_keys($row);

$modinfo = get_fast_modinfo($course);
$cm = $modinfo->get_cm($cm->id);
if (empty($cm->uservisible)) {
    $SESSION->mod_eitcoursegrouptools->currenttab = 'conditions_prevent_access';
    $tab = 'conditions_prevent_access';
} else if (count($row) > 1) {
    $tab = optional_param('tab', null, PARAM_ALPHAEXT);
    if ($tab) {
        $SESSION->mod_eitcoursegrouptools->currenttab = $tab;
    }

    if (!isset($SESSION->mod_eitcoursegrouptools->currenttab) || ($SESSION->mod_eitcoursegrouptools->currenttab == 'noaccess') || ($SESSION->mod_eitcoursegrouptools->currenttab == 'conditions_prevent_access')) {
        // Set standard-tab according to users capabilities!
        if (has_capability('mod/eitcoursegrouptools:administrate_groups', $context) || has_capability('mod/eitcoursegrouptools:administrate_groups', $context)) {
            $SESSION->mod_eitcoursegrouptools->currenttab = 'administrate_groups';
        } else if (has_capability('mod/eitcoursegrouptools:create_groups', $context)) {
            $SESSION->mod_eitcoursegrouptools->currenttab = 'administrate_groups';
        } else if (has_capability('mod/eitcoursegrouptools:register_students', $context) || has_capability('mod/eitcoursegrouptools:register', $context)) {
            $SESSION->mod_eitcoursegrouptools->currenttab = 'selfregistration';
        } else {
            $SESSION->mod_eitcoursegrouptools->currenttab = current($availabletabs);
        }
    }

    echo $OUTPUT->tabtree($row, $SESSION->mod_eitcoursegrouptools->currenttab, $inactive);
} else if (count($row) == 1) {
    $SESSION->mod_eitcoursegrouptools->currenttab = current($availabletabs);
    $tab = current($availabletabs);
} else {
    $SESSION->mod_eitcoursegrouptools->currenttab = 'noaccess';
    $tab = 'noaccess';
}

$context = context_course::instance($course->id);

if (has_capability('moodle/course:managegroups', $context)) {
    // Print link to moodle groups!
    $url = new moodle_url('/group/index.php', array('id' => $course->id));
    $grpslnk = html_writer::link($url, get_string('viewmoodlegroups', 'eitcoursegrouptools'));

    echo html_writer::tag('div', $grpslnk, array('class' => 'moodlegrpslnk'));
    echo html_writer::tag('div', '', array('class' => 'clearer'));
}

$PAGE->url->param('tab', $SESSION->mod_eitcoursegrouptools->currenttab);

$tab = $SESSION->mod_eitcoursegrouptools->currenttab; // Shortcut!

/* TRIGGER THE VIEW EVENT */
//$event = \mod_eitcoursegrouptools\event\course_module_viewed::create(array(
//            'objectid' => $cm->instance,
//            'context' => context_module::instance($cm->id),
//            'other' => array(
//                'tab' => $tab,
//                'name' => $instance->get_name(),
//            ),
//        ));
//$event->add_record_snapshot('course', $course);
//// In the next line you can use $PAGE->activityrecord if you have set it, or skip this line if you don't have a record.
//$event->add_record_snapshot($PAGE->cm->modname, $eitcoursegrouptools);
//$event->trigger();
/* END OF VIEW EVENT */

switch ($tab) {
    case 'administration':
    case 'group_admin':
        $instance->view_overview();
        break;
    case 'group_creation':
        $instance->view_creation();
        break;
    case 'activity':
        $instance->view_forumactivity();
        break;
    case 'selfregistration':
        $instance->view_selfregistration();
        break;
    case 'activitybygroup':
        $instance->view_forumactivitybygroup();
        break;
    case 'overview':
        $instance->view_overview();
        break;
    case 'userlist':
        $instance->view_userlist();
        break;
    case 'noaccess':
        $notification = $OUTPUT->notification(get_string('noaccess', 'eitcoursegrouptools'), 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break; 
    case 'conditions_prevent_access':
        if ($cm->availableinfo) {
            // User cannot access the activity, but on the course page they will
            // see a link to it, greyed-out, with information (HTML format) from
            // $cm->availableinfo about why they can't access it.
            $text = "<br />" . format_text($cm->availableinfo, FORMAT_HTML);
        } else {
            // User cannot access the activity and they will not see it at all.
            $text = '';
        }
        $notification = $OUTPUT->notification(get_string('conditions_prevent_access', 'eitcoursegrouptools') . $text, 'error');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
    default:
        $notification = $OUTPUT->notification('Choose a task above.', 'warning');
        echo $OUTPUT->box($notification, 'generalbox centered');
        break;
}

// Finish the page!



echo $output->footer();
