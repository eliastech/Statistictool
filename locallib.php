<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/eitcoursegrouptools/definitions.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/eitcoursegrouptools/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/messagelib.php');

//require_once('classes/.php');

class mod_eitcoursegrouptools {

    /** @var object */
    protected $cm;

    /** @var object */
    protected $course;

    /** @var object */
    protected $eitcoursegrouptools;

    /** @var object instance's context record */
    protected $context;

    /**
     * filter all groups
     */
    const FILTER_ALL = 0;

    /**
     * filter active groups
     */
    const FILTER_ACTIVE = 1;

    /**
     * filter inactive groups
     */
    const FILTER_INACTIVE = 2;

    /**
     * NAME_TAGS - the tags available for eitcoursegrouptools's group naming schemes
     */
    const NAME_TAGS = ['[firstname]', '[lastname]', '[idnumber]', '[username]', '@', '#'];

    /**
     * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
     */
    var $overview;
    var $reg;
    var $gp;
    var $activity;

    public function __construct($cmid, $mod_eitcoursegrouptools = null, $cm = null, $course = null) {
        global $DB;


        if ($cmid == 'staticonly') {
            // Use static functions only!
            return;
        }

        if (!empty($cm)) {
            $this->cm = $cm;
        } else if (!$this->cm = get_coursemodule_from_id('eitcoursegrouptools', $cmid)) {
            print_error('invalidcoursemodule');
        }
        $this->context = context_module::instance($this->cm->id);

        if ($course) {
            $this->course = $course;
        } else if (!$this->course = $DB->get_record('course', array('id' => $this->cm->course))) {
            print_error('invalidid', 'eitcoursegrouptools');
        }

        if ($mod_eitcoursegrouptools) {
            $this->eitcoursegrouptools = $mod_eitcoursegrouptools;
        } else if (!$this->eitcoursegrouptools = $DB->get_record('eitcoursegrouptools', array('id' => $this->cm->instance))) {
            print_error('invalidid', 'mod_eitcoursegrouptools');
        }

        $this->eitcoursegrouptools->cmidnumber = $this->cm->idnumber;
        $this->eitcoursegrouptools->course = $this->course->id;

        /*
         * visibility handled by require_login() with $cm parameter
         * get current group only when really needed
         */

        $this->overview = new mod_eitcoursegrouptools_overview($this->eitcoursegrouptools, $this->cm, $this->course, $this->context);
        $this->reg = new mod_eitcoursegrouptools_joingroups($this->eitcoursegrouptools, $this->cm, $this->course, $this->context);
        $this->activity = new mod_eitcoursegrouptools_activity($this->course, $this->context);
    }

    /**
     * @return string the name
     */
    public function get_name() {
        return $this->eitcoursegrouptools->name;
    }

    /**
     * @return object Grouptool's DB record
     */
    public function get_settings() {
        return $this->eitcoursegrouptools;
    }

    /**
     * @return array [allow_multiple, choose_min, choose_max]
     */
    public function get_reg_settings() {
        return [$this->eitcoursegrouptools->allow_multiple, $this->eitcoursegrouptools->choose_min, $this->eitcoursegrouptools->choose_max];
    }

    /**
     * Print a message along with button choices for Continue/Cancel
     *
     * If a string or moodle_url is given instead of a single_button, method defaults to post.
     * If cancel=null only continue button is displayed!
     *
     * @param string $message The question to ask the user
     * @param single_button|moodle_url|string $continue The single_button component representing the
     *                                                  Continue answer. Can also be a moodle_url
     *                                                  or string URL
     * @param single_button|moodle_url|string $cancel   The single_button component representing the
     *                                                  Cancel answer. Can also be a moodle_url or
     *                                                  string URL
     * @return string HTML fragment
     */
    public function confirm($message, $continue, $cancel = null) {
        global $OUTPUT;
        if (!($continue instanceof single_button)) {
            if (is_string($continue)) {
                $url = new moodle_url($continue);
                $continue = new single_button($url, get_string('continue'), 'post', true);
            } else if ($continue instanceof moodle_url) {
                $continue = new single_button($continue, get_string('continue'), 'post', true);
            } else {
                throw new coding_exception('The continue param to eitcoursegrouptools::confirm() must be either a' .
                ' URL (string/moodle_url) or a single_button instance.');
            }
        }

        if (!($cancel instanceof single_button)) {
            if (is_string($cancel)) {
                $cancel = new single_button(new moodle_url($cancel), get_string('cancel'), 'get');
            } else if ($cancel instanceof moodle_url) {
                $cancel = new single_button($cancel, get_string('cancel'), 'get');
            } else if ($cancel == null) {
                $cancel = null;
            } else {
                throw new coding_exception('The cancel param to eitcoursegrouptools::confirm() must be either a' .
                ' URL (string/moodle_url), single_button instance or null.');
            }
        }

        $output = $OUTPUT->box_start('generalbox modal modal-dialog modal-in-page show', 'notice');
        $output .= $OUTPUT->box_start('modal-content', 'modal-content');
        $output .= $OUTPUT->box_start('modal-header', 'modal-header');
        $output .= html_writer::tag('h4', get_string('confirm'));
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_start('modal-body', 'modal-body');
        $output .= html_writer::tag('p', $message);
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_start('modal-footer', 'modal-footer');
        $cancel = ($cancel != null) ? $OUTPUT->render($cancel) : "";
        $output .= html_writer::tag('div', $OUTPUT->render($continue) . $cancel, array('class' => 'buttons'));
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_end();
        $output .= $OUTPUT->box_end();
        return $output;
    }

    public function view_overview() {
        global $PAGE, $OUTPUT;



        $groupid = optional_param('groupid', 0, PARAM_INT);
        $groupingid = optional_param('groupingid', 0, PARAM_INT);
        $orientation = optional_param('orientation', 0, PARAM_BOOL);
        $includeinactive = optional_param('inactive', 0, PARAM_BOOL);
        $url = new moodle_url($PAGE->url, array('sesskey' => sesskey(),
            'groupid' => $groupid,
            'groupingid' => $groupingid,
            'orientation' => $orientation,
            'inactive' => $includeinactive));

        // Process submitted form!
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed?!
            $hideform = 0;
            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                list($error, $message) = $this->overview->push_registrations($groupid, $groupingid);
            }
            if ($error) {
                echo $OUTPUT->notification($message, 'error');
            } else {
                echo $OUTPUT->notification($message, 'success');
            }
        } else if (data_submitted() && confirm_sesskey()) {
            // Display confirm-dialog!
            $hideform = 1;

            $pushtomdl = optional_param('pushtomdl', 0, PARAM_BOOL);
            if ($pushtomdl) {
                // Try only!
                list($error, $message) = $this->overview->push_registrations($groupid, $groupingid, true);
                $attr = array();
                $attr['confirm'] = 1;
                $attr['pushtomdl'] = 1;
                $attr['sesskey'] = sesskey();

                $continue = new moodle_url($PAGE->url, $attr);
                $cancel = new moodle_url($PAGE->url);

                if ($error) {
                    $continue->remove_params('confirm', 'group');
                    $continue = new single_button($continue, get_string('continue'), 'get');
                    $cancel = null;
                }
                echo $this->confirm($message, $continue, $cancel);
            } else {
                $hideform = 0;
            }
        } else {
            $hideform = 0;
        }

        if (!$hideform) {
            //$groupingselect = $overview->get_grouping_select($url, $groupingid);
            //$groupselect = $this->overview->get_groups_select($url, $groupingid, $groupid);
            //$orientationselect = $this->overview->get_orientation_select($url, $orientation);

            if ($includeinactive) {
                $inactivetext = get_string('inactivegroups_hide', 'eitcoursegrouptools');
                $inactiveurl = new moodle_url($url, array('inactive' => 0));
            } else {
                $inactivetext = get_string('inactivegroups_show', 'eitcoursegrouptools');
                $inactiveurl = new moodle_url($url, array('inactive' => 1));
            }

            $syncstatus = $this->overview->get_sync_status();

            if ($syncstatus[0]) {
                /*
                 * Out of sync? --> show button to get registrations from eitcoursegrouptools to moodle
                 * (just register not already registered persons and let the others be)
                 */
                $url = new moodle_url($PAGE->url, array('pushtomdl' => 1, 'sesskey' => sesskey()));
                $button = new single_button($url, get_string('updatemdlgrps', 'eitcoursegrouptools'), 'post', true);
                echo $OUTPUT->box(html_writer::empty_tag('br') . $OUTPUT->render($button) . html_writer::empty_tag('br'), 'generalbox centered');
            }

            //  echo html_writer::tag('div', get_string('grouping', 'group') . '&nbsp;' .$OUTPUT->render($groupingselect), array('class' => 'centered grouptool_overview_filter')) .
//            html_writer::tag('div', get_string('group', 'group') . '&nbsp;' .
//                    $OUTPUT->render($groupselect), array('class' => 'centered grouptool_overview_filter')) .
//            html_writer::tag('div', get_string('orientation', 'eitcoursegrouptools') . '&nbsp;' .
//                    $OUTPUT->render($orientationselect), array('class' => 'centered grouptool_overview_filter')) .
//            html_writer::tag('div', html_writer::link($inactiveurl, $inactivetext), array('class' => 'centered grouptool_overview_filter'));
            // If we don't only get the data, the output happens directly per group!
            $this->overview->group_overview_table($groupingid, $groupid, false, $includeinactive);
        }
    }

    public function view_selfregistration() {
        global $OUTPUT, $DB, $USER, $PAGE;

        $userid = $USER->id;

        $regopen = $this->reg->is_registration_open();
       
        echo "REG OPEN ". print_r($regopen);

  
       
        
        // Process submitted form!
        $error = false;
        if (data_submitted() && confirm_sesskey() && optional_param('confirm', 0, PARAM_BOOL)) {
            // Execution has been confirmed!
                 echo 'HELLO WE ARE HERE -PROCESS';
            $hideform = 0;
            $action = optional_param('action', 'reg', PARAM_ALPHA);
            if ($action == 'unreg') {
                require_capability('mod/eitcoursegrouptools:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Unregister user and get feedback!
                try {
                    $confirmmessage = $this->reg->unregister_from_agrp($agrpid, $USER->id);
                } catch (\mod_eitcoursegrouptools\local\exception\registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'reg') {
                require_capability('mod/eitcoursegrouptools:register', $this->context);
                $agrpid = required_param('group', PARAM_INT);
                // Register user and get feedback!
                try {
                    $confirmmessage = $this->reg->register_in_agrp($agrpid, $USER->id);
                } catch (\mod_eitcoursegrouptools\local\exception\registration $e) {
                    $error = true;
                    $confirmmessage = $e->getMessage();
                }
            } else if ($action == 'resolvequeues') {
                require_capability('mod/eitcoursegrouptools:register_students', $this->context);
                list($error, $confirmmessage) = $this->resolve_queues(true); // Try only!
                if ($error == -1) {
                    $error = true;
                }
            }
            if ($error === true) {
                echo $OUTPUT->notification($confirmmessage, 'error');
            } else {
                echo $OUTPUT->notification($confirmmessage, 'success');
            }
        } else if (data_submitted() && confirm_sesskey()) {
            echo 'HELLO WE ARE HERE 2- PROCESS ELSE';
            // Display confirm-dialog!
            $hideform = 1;
            $reg = optional_param_array('reg', null, PARAM_INT);
            if ($reg != null) {
                $agrpid = array_keys($reg);
                $agrpid = reset($agrpid);
                $action = 'reg';
            }
            $unreg = optional_param_array('unreg', null, PARAM_INT);
            if ($unreg != null) {
                $agrpid = array_keys($unreg);
                $agrpid = reset($agrpid);
                $action = 'unreg';
            }
            $resolvequeues = optional_param('resolve_queues', 0, PARAM_BOOL);
            if (!empty($resolvequeues)) {
                $action = 'resolvequeues';
            }

            $attr = array();
            if ($action == 'resolvequeues') {
                require_capability('mod/eitcoursegrouptools:register_students', $this->context);
                list($error, $confirmmessage) = $this->resolve_queues(true); // Try only!
            } else if ($action == 'unreg') {
                require_capability('mod/eitcoursegrouptools:register', $this->context);
                $attr['group'] = $agrpid;
                // Try only!
                try {
                    $confirmmessage = $this->reg->unregister_from_agrp($agrpid, $USER->id, true);
                } catch (\mod_eitcoursegrouptools\local\exception\registration $e) {
                    $error = 1;
                    $confirmmessage = $e->getMessage();
                }
            } else {
                require_capability('mod/eitcoursegrouptools:register', $this->context);
                $action = 'reg';
                $attr['group'] = $agrpid;
                // Try only!
                try {
                    $confirmmessage = $this->reg->register_in_agrp($agrpid, $USER->id, true);
                } catch (\mod_eitcoursegrouptools\local\exception\registration $e) {
                    $error = 1;
                    $confirmmessage = $e->getMessage();
                }
            }
            $attr['confirm'] = '1';
            $attr['action'] = $action;
            $attr['sesskey'] = sesskey();

            $continue = new moodle_url($PAGE->url, $attr);
            $cancel = new moodle_url($PAGE->url);

            if (($error === true) && ($action != 'resolvequeues')) {
                $continue->remove_params('confirm', 'group');
                $continue = new single_button($continue, get_string('continue'), 'get');
                $cancel = null;
            }
            echo $this->confirm($confirmmessage, $continue, $cancel);
        } else {
            $hideform = 0;
        }

        if (empty($hideform)) {
            /*
             * we need a new moodle_url-Object because
             * $PAGE->url->param('sesskey', sesskey());
             * won't set sesskey param in $PAGE->url?!?
             */
            
            $url = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
            $mform = new MoodleQuickForm('registration_form', 'post', $url, '', array('id' => 'registration_form'));

            $regstat = $this->reg->get_registration_stats($USER->id);

            if (!empty($this->eitcoursegrouptools->timedue) && (time() >= $this->eitcoursegrouptools->timedue) &&
                    has_capability('mod/eitcoursegrouptools:register_students', $this->context)) {
                if ($regstat->queued_users > 0) {
                    // Insert queue-resolving button!
                    $mform->addElement('header', 'resolveheader', get_string('resolve_queue_legend', 'eitcoursegrouptools'));
                    $mform->addElement('submit', 'resolve_queues', get_string('resolve_queue', 'eitcoursegrouptools'));
                }
            }
            if (has_capability('mod/eitcoursegrouptools:view_description', $this->context)) {

                $mform->addElement('header', 'generalinfo', get_string('general_information', 'eitcoursegrouptools'));
                $mform->setExpanded('generalinfo');

                if (!empty($this->eitcoursegrouptools->use_size)) {
                    $placestats = $regstat->group_places . '&nbsp;' . get_string('total', 'eitcoursegrouptools');
                } else {
                    $placestats = get_string('total', 'eitcoursegrouptools') . ' no limit &nbsp;';
                }
                if (($regstat->free_places != null) && !empty($this->eitcoursegrouptools->use_size)) {
                    $placestats .= ' /' . $regstat->free_places . '&nbsp;' .
                            get_string('free', 'eitcoursegrouptools');
                } else {
                    $placestats .= "/ " . get_string('free', 'eitcoursegrouptools') . '  no limit &nbsp;';
                }
                if ($regstat->occupied_places != null) {
                    $placestats .= ' / ' . $regstat->occupied_places . '&nbsp;' .
                            get_string('occupied', 'eitcoursegrouptools');
                }
                $mform->addElement('static', 'group_places', get_string('group_places', 'eitcoursegrouptools'), $placestats);
                $mform->addHelpButton('group_places', 'group_places', 'eitcoursegrouptools');

                $mform->addElement('static', 'number_of_students', get_string('number_of_students', 'eitcoursegrouptools'), $regstat->users);

                if (($this->eitcoursegrouptools->allow_multiple &&
                        (count($regstat->registered) < $this->eitcoursegrouptools->choose_min)) || (!$this->eitcoursegrouptools->allow_multiple && !count($regstat->registered))) {
                    if ($this->eitcoursegrouptools->allow_multiple) {
                        $missing = ($this->eitcoursegrouptools->choose_min - count($regstat->registered));
                        $stringlabel = ($missing > 1) ? 'registrations_missing' : 'registration_missing';
                    } else {
                        $missing = 1;
                        $stringlabel = 'registration_missing';
                    }
                    $missingtext = get_string($stringlabel, 'eitcoursegrouptools', $missing);
                } else {
                    $missingtext = "";
                }

                if (!empty($regstat->registered)) {
                    foreach ($regstat->registered as $registration) {
                        if (empty($regscumulative)) {
                            $regscumulative = $registration->grpname .
                                    ' (' . $registration->rank . ')<---000';
                        } else {
                            $regscumulative .= ', ' . $registration->grpname .
                                    ' (' . $registration->rank . ')';
                        }
                    }
                    $mform->addElement('static', 'registrations', get_string('registrations', 'eitcoursegrouptools'), html_writer::tag('div', $missingtext) . $regscumulative);
                } else {
                    $mform->addElement('static', 'registrations', get_string('registrations', 'eitcoursegrouptools'), html_writer::tag('div', $missingtext) . get_string('not_registered', 'eitcoursegrouptools'));
                }

                if (!empty($regstat->queued)) {
                    foreach ($regstat->queued as $queue) {
                        if (empty($queuescumulative)) {
                            $queuescumulative = $queue->grpname . ' (' . $queue->rank . ')';
                        } else {
                            $queuescumulative .= ', ' . $queue->grpname . ' (' . $queue->rank . ')';
                        }
                    }
                    $mform->addElement('static', 'queues', get_string('queues', 'eitcoursegrouptools'), $queuescumulative);
                }

                if (!empty($this->eitcoursegrouptools->timeavailable)) {
                    $mform->addElement('static', 'availabledate', get_string('availabledate', 'eitcoursegrouptools'), userdate($this->eitcoursegrouptools->timeavailable, get_string('strftimedatetime')));
                }

                if (!empty($this->eitcoursegrouptools->timedue)) {
                    $textdue = userdate($this->eitcoursegrouptools->timedue, get_string('strftimedatetime'));
                } else {
                    $textdue = get_string('noregistrationdue', 'eitcoursegrouptools');
                }
                $mform->addElement('static', 'registrationdue', get_string('registrationdue', 'eitcoursegrouptools'), $textdue);

//                if (!empty($this->eitcoursegrouptools->allow_unreg)) {
//                    $unregtext = get_string('allowed', 'eitcoursegrouptools');
//                } else {
//                    $unregtext = get_string('not_permitted', 'eitcoursegrouptools');
//                }
//                $mform->addElement('static', 'unreg', get_string('unreg_is', 'eitcoursegrouptools'), $unregtext);

                if (!empty($this->eitcoursegrouptools->allow_multiple)) {
                    if ($this->eitcoursegrouptools->choose_min && $this->eitcoursegrouptools->choose_max) {
                        $data = array('min' => $this->eitcoursegrouptools->choose_min,
                            'max' => $this->eitcoursegrouptools->choose_max);
                        $minmaxtext = get_string('choose_min_max_text', 'eitcoursegrouptools', $data);
                    } else if ($this->eitcoursegrouptools->choose_min) {
                        $minmaxtext = get_string('choose_min_text', 'eitcoursegrouptools', $this->eitcoursegrouptools->choose_min);
                    } else if ($this->eitcoursegrouptools->choose_max) {
                        $minmaxtext = get_string('choose_max_text', 'eitcoursegrouptools', $this->eitcoursegrouptools->choose_max);
                    }
                    $mform->addElement('static', 'minmax', get_string('choose_minmax_title', 'eitcoursegrouptools'), $minmaxtext);
                }

//                if (!empty($this->eitcoursegrouptools->use_queue)) {
//                    $mform->addElement('static', 'queueing', get_string('queueing_is', 'eitcoursegrouptools'), get_string('active', 'eitcoursegrouptools'));
//                }
                // Intro-text if set!
                if (($this->eitcoursegrouptools->alwaysshowdescription || (time() > $this->eitcoursegrouptools->timeavailable)) && $this->eitcoursegrouptools->intro) {
                    $intro = format_module_intro('eitcoursegrouptools', $this->eitcoursegrouptools, $this->cm->id);
                    $mform->addElement('header', 'intro', get_string('intro', 'eitcoursegrouptools'));
                    $mform->addElement('html', $OUTPUT->box($intro, 'generalbox'));
                }
            }
            $groups = $this->overview->get_active_groups();

            $mform->addElement('header', 'groups', get_string('groups'));

            // Student view!
            if (has_capability("mod/eitcoursegrouptools:view_groups", $this->context)) {

                // Prepare formular-content for registration-action!
                foreach ($groups as $key => &$group) {
                    $group = $this->overview->get_active_groups(true, true, 0, $key);
                    $group = current($group);

                    $registered = count($group->registered);
                    $grpsize = ($this->eitcoursegrouptools->use_size) ? $group->grpsize : "";
                    $grouphtml = html_writer::tag('span', get_string('registered', 'eitcoursegrouptools') .
                                    " " . $registered . " " . $grpsize, array('class' => 'fillratio'));
                    if ($this->eitcoursegrouptools->use_queue) {
                        $queued = count($group->queued);
                        $grouphtml .= html_writer::tag('span', get_string('queued', 'eitcoursegrouptools') .
                                        " " . $queued, array('class' => 'queued')) . " - ";
                    }

                    if (!empty($group->registered)) {
                        $regrank = $this->reg->get_rank_in_queue($group->registered, $USER->id);
                    } else {
                        $regrank = false;
                    }
                    if (!empty($group->queued)) {
                        $queuerank = $this->reg->get_rank_in_queue($group->queued, $USER->id);
                    } else {
                        $queuerank = false;
                    }

                    // We have to determine if we can show the members link!
                    $showmembers = $this->reg->canshowmembers($group->agrpid, $regrank, $queuerank);
                    if ($showmembers) {
                        
                        echo "Hello WORLD MEMBER";
                        //$grouphtml .= $this->render_members_link($group);
                    }

                    /* If we include inactive groups and there's someone registered in one of these,
                     * the label gets displayed incorrectly.
                     */
                    $agrpids = $DB->get_fieldset_select('ecgt_activegroups', 'id', "grouptoolid = ? AND active = 1", array($this->eitcoursegrouptools->id));
                    list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
                    array_unshift($params, $userid);
                    $userregs = $DB->count_records_select('ecgt_registered', "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql, $params);
                    $userqueues = $DB->count_records_select('ecgt_queued', "userid = ? AND agrpid " . $agrpsql, $params);
                    $min = $this->eitcoursegrouptools->allow_multiple ? $this->eitcoursegrouptools->choose_min : 0;
                    if (!empty($group->registered) && $this->reg->get_rank_in_queue($group->registered, $userid) != false) {
                        // User is allready registered --> unreg button!
                        if ($this->eitcoursegrouptools->allow_unreg) {
                            $label = get_string('unreg', 'eitcoursegrouptools');
                            $buttonattr = array('type' => 'submit',
                                'name' => 'unreg[' . $group->agrpid . ']',
                                'value' => $group->agrpid,
                                'class' => 'unregbutton btn btn-secondary');
                            if ($regopen) {

                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span', get_string('registered_on_rank', 'eitcoursegrouptools', $regrank), array('class' => 'rank'));
                    } else if (!empty($group->queued) && $this->reg->get_rank_in_queue($group->queued, $userid) != false) {
                        // We're sorry, but user's already queued in this group!
                        if ($this->eitcoursegrouptools->allow_unreg) {
                            $label = get_string('unqueue', 'eitcoursegrouptools');
                            $buttonattr = array('type' => 'submit',
                                'name' => 'unreg[' . $group->agrpid . ']',
                                'value' => $group->agrpid,
                                'class' => 'unregbutton btn btn-secondary');
                            if ($regopen) {
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            }
                        }
                        $grouphtml .= html_writer::tag('span', get_string('queued_on_rank', 'eitcoursegrouptools', $queuerank), array('class' => 'rank'));
                    } else if ($this->reg->grpmarked($group->agrpid)) {
                        $grouphtml .= html_writer::tag('span', get_string('grp_marked', 'eitcoursegrouptools'), array('class' => 'rank'));
                    } else if ($this->reg->qualifies_for_groupchange($group->agrpid, $USER->id)) {


                        // Groupchange!
                        $label = get_string('change_group', 'eitcoursegrouptools');
                        if ($this->eitcoursegrouptools->use_size && count($group->registered) >= $group->grpsize) {

                            $label .= ' (' . get_string('queue', 'eitcoursegrouptools') . ')';
                            $class = "btn-secondary";
                        } else {
                            $class = "btn-primary";
                        }
                        $buttonattr = array('type' => 'submit',
                            'name' => 'reg[' . $group->agrpid . ']',
                            'value' => $group->agrpid,
                            'class' => 'regbutton btn ' . $class);
                        $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                    } else {
                        $message = new stdClass();
                        $message->username = fullname($USER);
                        $message->groupname = $group->name;
                        $message->userid = $USER->id;

                        try {
                            try {
                                // Can be registered?
                                $this->reg->can_be_registered($group->agrpid, $USER->id, $message);

                                // Register button!
                                $label = get_string('register', 'eitcoursegrouptools');
                                $buttonattr = array('type' => 'submit',
                                    'name' => 'reg[' . $group->agrpid . ']',
                                    'value' => $group->agrpid,
                                    'class' => 'regbutton btn btn-primary');
                                $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                            } catch (\mod_eitcoursegrouptools\local\exception\exceedgroupsize $e) {
                                if (!$this->eitcoursegrouptools->use_queue) {
                                    throw new \mod_eitcoursegrouptools\local\exception\exceedgroupsize();
                                } else {
                                    // There's no place left in the group, so we try to queue the user!
                                    $this->reg->can_be_queued($group->agrpid, $USER->id, $message);

                                    // Queue button!
                                    $label = get_string('queue', 'eitcoursegrouptools');
                                    $buttonattr = array('type' => 'submit',
                                        'name' => 'reg[' . $group->agrpid . ']',
                                        'value' => $group->agrpid,
                                        'class' => 'queuebutton btn btn-secondary');
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                }
                            } catch (\mod_eitcoursegrouptools\local\exception\notenoughregs $e) {
                                /* The user has not enough registrations, queue entries or marks,
                                 * so we try to mark the user! (Exceptions get handled above!) */
                                list($queued, ) = $this->reg->can_be_marked($group->agrpid, $USER->id, $message);
                                if (!$queued) {
                                    // Register button!
                                    $label = get_string('register', 'eitcoursegrouptools');
                                    $buttonattr = array('type' => 'submit',
                                        'name' => 'reg[' . $group->agrpid . ']',
                                        'value' => $group->agrpid,
                                        'class' => 'regbutton btn btn-primary');
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                } else {
                                    // Queue button!
                                    $label = get_string('queue', 'eitcoursegrouptools');
                                    $buttonattr = array('type' => 'submit',
                                        'name' => 'reg[' . $group->agrpid . ']',
                                        'value' => $group->agrpid,
                                        'class' => 'queuebutton btn btn-secondary');
                                    $grouphtml .= html_writer::tag('button', $label, $buttonattr);
                                }
                            }
                        } catch (\mod_eitcoursegrouptools\local\exception\exceedgroupqueuelimit $e) {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div', get_string('fullgroup', 'eitcoursegrouptools'), array('class' => 'rank'));
                        } catch (\mod_eitcoursegrouptools\local\exception\exceedgroupsize $e) {
                            // Group is full!
                            $grouphtml .= html_writer::tag('div', get_string('fullgroup', 'eitcoursegrouptools'), array('class' => 'rank'));
                        } catch (\mod_eitcoursegrouptools\local\exception\exceeduserqueuelimit $e) {
                            // Too many queues!
                            $grouphtml .= html_writer::tag('div', get_string('max_queues_reached', 'eitcoursegrouptools'), array('class' => 'rank'));
                        } catch (\mod_eitcoursegrouptools\local\exception\exceeduserreglimit $e) {
                            $grouphtml .= html_writer::tag('div', get_string('max_regs_reached', 'eitcoursegrouptools'), array('class' => 'rank'));
                        } catch (\mod_eitcoursegrouptools\local\exception\registration $e) {
                            // No registration possible!
                            $grouphtml .= html_writer::tag('div', '', array('class' => 'rank'));
                        }
                    }

                    if ($regrank !== false) {
                        $grouphtml = $OUTPUT->box(html_writer::tag('h2', $group->name, array('class' => 'panel-title')) .
                                html_writer::tag('div', $grouphtml, array('class' => 'panel-body')), 'generalbox group alert-success');
                    } else if ($queuerank !== false) {
                        $grouphtml = $OUTPUT->box(html_writer::tag('h2', $group->name, array('class' => 'panel-title')) .
                                html_writer::tag('div', $grouphtml, array('class' => 'panel-body')), 'generalbox group alert-warning');
                    } else if (($this->eitcoursegrouptools->use_size) && ($registered >= $group->grpsize) && $regopen) {
                        $grouphtml = $OUTPUT->box(html_writer::tag('h2', $group->name, array('class' => 'panel-title')) .
                                html_writer::tag('div', $grouphtml, array('class' => 'panel-body')), 'generalbox group alert-error');
                    } else {
                        $grouphtml = $OUTPUT->box(html_writer::tag('h2', $group->name, array('class' => 'panel-title')) .
                                html_writer::tag('div', $grouphtml, array('class' => 'panel-body')), 'generalbox group empty');
                    }
                    $mform->addElement('html', $grouphtml);
                }
            }

            if ($this->eitcoursegrouptools->show_members) {
                $params = new stdClass();
                $params->courseid = $this->eitcoursegrouptools->course;
                $params->showidnumber = has_capability('mod/eitcoursegrouptools:register', $this->context) || has_capability('mod/eitcoursegrouptools:register', $this->context);
                $helpicon = new help_icon('status', 'mod_eitcoursegrouptools');
                // Add the help-icon-data to the form element as data-attribute so we use less params for the JS-call!
                $mform->updateAttributes(array('data-statushelp' => json_encode($helpicon->export_for_template($OUTPUT))));
                // Require the JS to show group members (just once)!
                $PAGE->requires->js_call_amd('mod_eitcoursegrouptools/memberspopup', 'initializer', array($params));
            }

            $mform->display();
        }
    }

    public function view_creation() {
        Global $PAGE, $SESSION, $DB;


        $SESSION->instance = $this->cm->instance;
        // echo "CID: ". $this->cm->instance;

        $url = new moodle_url($PAGE->url, array('sesskey' => sesskey()));
        //$mform = new mod_eitcoursegrouptools_groupadminform('view.php?id='.$this->cm->id.'&', 'post', $url, '');
        $mform = new mod_eitcoursegrouptools_groupadminform();
        $SESSION->id = $this->cm->id;

        //'view', 'post', $url, '', array('id' => $this->cm->id)
        //Form processing and displaying is done here
        if ($mform->is_cancelled()) {
            redirect(new moodle_url('/mod/eitcoursegrouptools/view.php', array('id' => $this->cm->id)));
        } else if ($data = $mform->get_data()) {

            foreach ($SESSION->ecgt_activegroups as $itr2) {
                $arr = (array) $data;
                $DB->execute("UPDATE mdl_ecgt_activegroups SET active = " . $arr[$itr2->groupid] . " WHERE mdl_ecgt_activegroups.id = " . $itr2->id . "");
                //echo "<br> UPDATE mdl_ecgt_activegroups SET active = " . $arr[$itr2->groupid] . " WHERE mdl_ecgt_activegroups.id = " . $itr2->id . "";
            }

            echo 'Your changes was successfull saved. <a href="' . new moodle_url('/mod/eitcoursegrouptools/view.php', array('id' => $this->cm->id)) . '&tab=group_admin">Click here</a> to go overview.';
        } else {

            //$toform = new stdClass();
            // $toform->grpname24 = true;
            // $mform->set_data($toform);
            //displays the form  
            $mform->display();
            //$mform->render();
        }
    }

    public function view_forumactivity() {
        global $OUTPUT, $PAGE, $DB, $CFG, $SESSION;

        $OUTPUT = $PAGE->get_renderer('mod_eitcoursegrouptools');
        $SESSION->course = $this->cm->course;
        $SESSION->id = $this->cm->id;
        $SESSION->sesskey = $_SESSION['USER']->sesskey;

        $activity = $this->activity->getSortedListCombination($this->cm->course);
        //$activity[] = array("Counts" => '88', "UID" => '3', "User" => 'Maga Din', "Groups" => '0', "Unique" => '0');
        //echo print_r($activity);
        if (empty(rsort($activity))) {
            $activity[] = array("Counts" => '', "UID" => 'Empty', "User" => '', "Groups" => '', "Unique" => '');
        }



        $renderable = new \mod_eitcoursegrouptools\output\activity("None", $activity);
        echo $OUTPUT->render($renderable);

        $SESSION->emailto = array();
    }

    public function view_forumactivitybygroup() {
        global $OUTPUT, $PAGE, $DB, $CFG, $SESSION;

        $OUTPUT = $PAGE->get_renderer('mod_eitcoursegrouptools');
        $SESSION->course = $this->cm->course;
        $SESSION->id = $this->cm->id;
        $SESSION->sesskey = $_SESSION['USER']->sesskey;

        $activity = $this->activity->getSortListbyGroups($this->cm->course);
       // echo print_r($activity);
        
        if (empty(rsort($activity))) {
            $activity[] = array("groupname" => 'Empty');
        }

        $renderable = new \mod_eitcoursegrouptools\output\activitybygroup("None", $activity);

        echo $OUTPUT->render($renderable);
    }



}
