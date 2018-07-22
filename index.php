<?php

require_once('../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
 

$id = required_param('id', PARAM_INT);           // Course ID 
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

/* TRIGGER THE VIEW ALL EVENT */
$event = \mod_eitcoursegrouptools\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->trigger();

$coursecontext = context_course::instance($course->id);
//$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/mod/eitcoursegrouptools/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();
// Between header

if (! $grouptools = get_all_instances_in_course('grouptool', $course)) {
    notice(get_string('nogrouptools', 'eitcoursegrouptools'), new moodle_url('/course/view.php',
                                                                   array('id' => $course->id)));
}

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head  = array(get_string('week'), get_string('name'), get_string('info'),
                          get_string('moduleintro'));
    $table->align = array('center', 'left', 'left', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array(get_string('topic'), get_string('name'), get_string('info'),
                          get_string('moduleintro'));
    $table->align = array('center', 'left', 'left', 'left', 'left');
} else {
    $table->head  = array(get_string('name'), get_string('info'), get_string('moduleintro'));
    $table->align = array('left', 'left', 'left', 'left');
}

foreach ($grouptools as $grouptool) {

    // Just some info.
    $context = context_module::instance($grouptool->coursemodule, MUST_EXIST);

    $strgrouptool = get_string('grouptool', 'eitcoursegrouptools');
    $strduedate = get_string('duedate', 'eitcoursegrouptools');
    $strduedateno = get_string('duedateno', 'eitcoursegrouptools');

    $str = "";
    if (has_capability('mod/eitcoursegrouptools:register', $context)
        || has_capability('mod/eitcoursegrouptools:view_regs_course_view', $context)
        || has_capability('mod/eitcoursegrouptools:view_regs_group_view', $context)) {
        $attrib = array('title' => $strgrouptool,
                        'href'  => $CFG->wwwroot.'/mod/eitcoursegrouptools/view.php?id='.$grouptool->coursemodule);
        if ($grouptool->visible) {
            $attrib['class'] = 'dimmed';
        }
        list($colorclass, $unused) = grouptool_display_lateness(time(), $grouptool->timedue);

        $attr = array('class' => 'info');
        if ($grouptool->timeavailable > time()) {
            $str .= html_writer::tag('div', get_string('availabledate', 'eitcoursegrouptools').': '.
                    html_writer::tag('span', userdate($grouptool->timeavailable)),
                    $attr);
        }
        if ($grouptool->timedue) {
            $str .= html_writer::tag('div', $strduedate.': '.
                                            html_writer::tag('span', userdate($grouptool->timedue),
                                                             array('class' => (($colorclass == 'late') ? ' late' : ''))),
                                     $attr);
        } else {
            $str .= html_writer::tag('div', $strduedateno, $attr);
        }
    }

    $details = grouptool_get_user_reg_details($grouptool, $context);

    if (($grouptool->allow_reg
            && (has_capability('mod/eitcoursegrouptools:view_regs_group_view', $context)
            || has_capability('mod/eitcoursegrouptools:view_regs_course_view', $context)))
        || has_capability('mod/eitcoursegrouptools:register', $context)) {
        $str = html_writer::tag('div', $str.$details, array('class' => 'grouptool overview'));
    }

    $info = $str;

    if (!$grouptool->visible) {
        $link = html_writer::link(
                new moodle_url('/mod/eitcoursegrouptools/view.php', array('id' => $grouptool->coursemodule)),
                format_string($grouptool->name, true),
                array('class' => 'dimmed'));
    } else {
        $link = html_writer::link(
                new moodle_url('/mod/eitcoursegrouptools/view.php', array('id' => $grouptool->coursemodule)),
                format_string($grouptool->name, true));
    }

    if ($grouptool->alwaysshowdescription || (time() > $grouptool->timeavailable)) {
        $intro = $grouptool->intro ? $grouptool->intro : "";
    } else {
        $intro = '';
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = array($grouptool->section, $link, $info, $intro);
    } else {
        $table->data[] = array($link, $info, $intro);
    }
}

echo $OUTPUT->heading(get_string('modulenameplural', 'eitcoursegrouptools'), 2);
echo html_writer::table($table);


// Finish between header
echo $OUTPUT->footer();