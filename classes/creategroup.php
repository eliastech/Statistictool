<?php

namespace mod_eitcoursegrouptools;

use \html_writer as html_writer;
use html_table;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/eitcoursegrouptools/definitions.php');
require_once($CFG->dirroot.'/mod/eitcoursegrouptools/lib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_grade.php');
require_once($CFG->libdir . '/pdflib.php');



/**
 * class representing the moodleform used in the administration tab
 *
 * @package   mod_grouptool
 * @author    Philipp Hager
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class creategroup extends \moodleform {

    /**
     * @var \mod_grouptool\output\sortlist contains reference to our sortlist, so we can alter current active entries afterwards
     */
    private $_sortlist = null;

    /**
     * Update currently active sortlist elements
     *
     * @param bool[] $curactive currently active entries
     * @return void
     */
    public function update_cur_active($curactive = null) {
        if (!empty($curactive) && is_array($curactive)) {
            $this->_sortlist->_options['curactive'] = $curactive;
        }
    }

    /**
     * Definition of group creation form
     */
    protected function definition() {
        global $DB, $PAGE;
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setDefault('id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $this->context = \context_module::instance($this->_customdata['id']);

        $cm = get_coursemodule_from_id('eitcoursegrouptools', $this->_customdata['id']);
        $course = $DB->get_record('course', array('id' => $cm->course));
        $eitcoursegrouptools = $DB->get_record('eitcoursegrouptools', array('id' => $cm->instance), '*', MUST_EXIST);
        $coursecontext = \context_course::instance($cm->course);

        $mform->addElement('hidden', 'tab');
        $mform->setDefault('tab', 'group_creation');
        $mform->setType('tab', PARAM_TEXT);

        if (has_capability('mod/eitcoursegrouptools:create_groups', $this->context)) {
            /* -------------------------------------------------------------------------------
             * Adding the "group creation" fieldset, where all the common settings are showed!
             */
            $mform->addElement('header', 'group_creation', get_string('groupcreation',
                                                                      'eitcoursegrouptools'));

            $options = array(0 => get_string('all'));
            $options += $this->_customdata['roles'];
            $mform->addElement('select', 'roleid', get_string('selectfromrole', 'group'), $options);
            $student = get_archetype_roles('student');
            $student = reset($student);

            if ($student and array_key_exists($student->id, $options)) {
                $mform->setDefault('roleid', $student->id);
            }

            $canviewcohorts = has_capability('moodle/cohort:view', $this->context);
            if ($canviewcohorts) {
                $cohorts = cohort_get_available_cohorts($coursecontext, true, 0, 0);
                if (count($cohorts) != 0) {
                    $options = array(0 => get_string('anycohort', 'cohort'));
                    foreach ($cohorts as $cohort) {
                         $options[$cohort->id] = $cohort->name;
                    }
                    $mform->addElement('select', 'cohortid', get_string('selectfromcohort',
                                                                        'eitcoursegrouptools'), $options);
                    $mform->setDefault('cohortid', '0');
                }
            } else {
                $cohorts = array();
            }

            if (!$canviewcohorts || (count($cohorts) == 0)) {
                $mform->addElement('hidden', 'cohortid');
                $mform->setType('cohortid', PARAM_INT);
                $mform->setConstant('cohortid', '0');
            }

            $mform->addElement('hidden', 'seed');
            $mform->setType('seed', PARAM_INT);

            $radioarray = array();
            $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('define_amount_groups', 'eitcoursegrouptools'),
                                                  ECGT_GROUPS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('define_amount_members', 'eitcoursegrouptools'),
                                                  ECGT_MEMBERS_AMOUNT);
            $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('create_1_person_groups', 'eitcoursegrouptools'),
                                                  ECGT_1_PERSON_GROUPS);
            $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('create_fromto_groups', 'eitcoursegrouptools'),
                                                  ECGT_FROMTO_GROUPS);
            $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('create_n_m_groups', 'eitcoursegrouptools'),
                                                  ECGT_N_M_GROUPS);
            $mform->addGroup($radioarray, 'modearray',
                             get_string('groupcreationmode', 'eitcoursegrouptools'),
                             \html_writer::empty_tag('br'), false);
            $mform->setDefault('mode', ECGT_GROUPS_AMOUNT);
            $mform->addHelpButton('modearray', 'groupcreationmode', 'eitcoursegrouptools');

            $mform->addElement('text', 'numberofgroups', get_string('number_of_groups', 'eitcoursegrouptools'), array('size' => '4'));
            $mform->disabledIf('numberofgroups', 'mode', 'eq', ECGT_MEMBERS_AMOUNT);
            $mform->disabledif ('numberofgroups', 'mode', 'eq', ECGT_1_PERSON_GROUPS);
            $mform->disabledif ('numberofgroups', 'mode', 'eq', ECGT_FROMTO_GROUPS);
            $mform->setType('numberofgroups', PARAM_INT);
            $mform->setDefault('numberofgroups', 2);

            $mform->addElement('text', 'numberofmembers', get_string('number_of_members', 'eitcoursegrouptools'), array('size' => '4'));
            $mform->disabledIf('numberofmembers', 'mode', 'eq', ECGT_GROUPS_AMOUNT);
            $mform->disabledif ('numberofmembers', 'mode', 'eq', ECGT_1_PERSON_GROUPS);
            $mform->setType('numberofmembers', PARAM_INT);
            $mform->setDefault('numberofmembers', $eitcoursegrouptools->grpsize);

            $fromto = array();
            $fromto[] = $mform->createElement('text', 'from', get_string('from'));
            $mform->setDefault('from', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('from', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'to', get_string('to'));
            $mform->setDefault('to', 0);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('to', PARAM_RAW);
            $fromto[] = $mform->createElement('text', 'digits', get_string('digits', 'eitcoursegrouptools'));
            $mform->setDefault('digits', 2);
            /*
             * We have to clean this params by ourselves afterwards otherwise we get problems
             * with texts getting mapped to 0
             */
            $mform->setType('digits', PARAM_RAW);
            $fromtoglue = array(' '.\html_writer::tag('label', '-', array('for' => 'id_from')).' ',
                                ' '.\html_writer::tag('label', get_string('digits', 'eitcoursegrouptools'), array('for' => 'id_digits')).' ');
            $mform->addGroup($fromto, 'fromto', get_string('groupfromtodigits', 'eitcoursegrouptools'), $fromtoglue, false);
            $mform->disabledif ('from', 'mode', 'noteq', ECGT_FROMTO_GROUPS);
            $mform->disabledif ('to', 'mode', 'noteq', ECGT_FROMTO_GROUPS);
            $mform->disabledif ('digits', 'mode', 'noteq', ECGT_FROMTO_GROUPS);
            $mform->setAdvanced('fromto');

            $mform->addElement('checkbox', 'nosmallgroups', get_string('nosmallgroups', 'group'));
            $mform->addHelpButton('nosmallgroups', 'nosmallgroups', 'eitcoursegrouptools');
            $mform->disabledif ('nosmallgroups', 'mode', 'noteq', ECGT_MEMBERS_AMOUNT);
            $mform->disabledif ('nosmallgroups', 'mode', 'eq', ECGT_FROMTO_GROUPS);
            $mform->disabledif ('nosmallgroups', 'mode', 'eq', ECGT_N_M_GROUPS);
            $mform->setAdvanced('nosmallgroups');

            $options = array('no'        => get_string('noallocation', 'group'),
                             'random'    => get_string('random', 'group'),
                             'firstname' => get_string('byfirstname', 'group'),
                             'lastname'  => get_string('bylastname', 'group'),
                             'idnumber'  => get_string('byidnumber', 'group'));
            $mform->addElement('select', 'allocateby', get_string('allocateby', 'group'), $options);
            if ($eitcoursegrouptools->allow_reg) {
                $mform->setDefault('allocateby', 'no');
            } else {
                $mform->setDefault('allocateby', 'random');
            }
            $mform->disabledif ('allocateby', 'mode', 'eq', ECGT_1_PERSON_GROUPS);
            $mform->disabledif ('allocateby', 'mode', 'eq', ECGT_FROMTO_GROUPS);
            $mform->disabledif ('allocateby', 'mode', 'eq', ECGT_N_M_GROUPS);

            $tags = array();
            foreach (\mod_eitcoursegrouptools::NAME_TAGS as $tag) {
                $tags[] = html_writer::tag('span', $tag, array('class' => 'nametag', 'data-nametag' => $tag));
            }

            $naminggrp = array();
            $naminggrp[] =& $mform->createElement('text', 'namingscheme', '', array('size' => '64'));
            $naminggrp[] =& $mform->createElement('static', 'tags', '', implode("", $tags));
            $namingstd = get_config('mod_eitcoursegrouptools', 'name_scheme');
            $namingstd = (!empty($namingstd) ? $namingstd : get_string('group', 'group').' #');
            $mform->setDefault('namingscheme', $namingstd);
            $mform->setType('namingscheme', PARAM_RAW);
            $mform->addGroup($naminggrp, 'naminggrp', get_string('namingscheme', 'eitcoursegrouptools'), ' ', false);
            $mform->addHelpButton('naminggrp', 'namingscheme', 'eitcoursegrouptools');
            // Init JS!
            $params = new \stdClass();
            $params->fromtomode  = ECGT_FROMTO_GROUPS;
            $PAGE->requires->js_call_amd('mod_eitcoursegrouptools/groupcreation', 'initializer', array($params));

            $selectgroups = $mform->createElement('selectgroups', 'grouping', get_string('createingrouping', 'group'));

            $options = array('0' => get_string('no'));
            if (has_capability('mod/eitcoursegrouptools:create_groupings', $this->context)) {
                $options['-1'] = get_string('onenewgrouping', 'eitcoursegrouptools');

            }
            $selectgroups->addOptGroup("", $options);
            if ($groupings = groups_get_all_groupings($course->id)) {
                $options = array();
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = strip_tags(format_string($grouping->name));
                }
                $selectgroups->addOptGroup("————————————————————————", $options);
            }
            $mform->addElement($selectgroups);
            if ($groupings) {
                $mform->setDefault('grouping', '0');
            }
            if (has_capability('mod/eitcoursegrouptools:create_groupings', $this->context)) {
                $mform->addElement('text', 'groupingname', get_string('groupingname', 'group'));
                $mform->setType('groupingname', PARAM_MULTILANG);
                $mform->disabledif ('groupingname', 'grouping', 'noteq', '-1');
            }

            $mform->addElement('submit', 'createGroups', get_string('createGroups', 'eitcoursegrouptools'));
        }
    }

    /**
     * Validation for administration-form
     * If there are errors return array of errors ("fieldname"=>"error message"),
     * otherwise true if ok.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *               or an empty array if everything is OK.
     */
    public function validation($data, $files) {
        $parenterrors = parent::validation($data, $files);
        $errors = array();
        if (!empty($data['createGroups']) && $data['grouping'] == "-1"
                && (empty($data['groupingname']) || $data['groupingname'] == "")) {
            $errors['groupingname'] = get_string('must_specify_groupingname', 'eitcoursegrouptools');
        }
        if (!empty($data['createGroups']) && in_array($data['mode'], array(ECGT_GROUPS_AMOUNT, ECGT_N_M_GROUPS))
                && ($data['numberofgroups'] <= 0)) {
            $errors['numberofgroups'] = get_string('mustbeposint', 'eitcoursegrouptools');
        }
        if (!empty($data['createGroups'])) {
            switch($data['mode']) {
                case ECGT_N_M_GROUPS:
                case ECGT_FROMTO_GROUPS:
                    if ($data['numberofmembers'] < 0) {
                        $errors['numberofmembers'] = get_string('mustbegt0', 'eitcoursegrouptools');
                    }
                    break;
                case ECGT_MEMBERS_AMOUNT:
                    if ($data['numberofmembers'] <= 0) {
                        $errors['numberofmembers'] = get_string('mustbeposint', 'eitcoursegrouptools');
                    }
                    break;
            }
        }
        if (!empty($data['createGroups']) && ($data['mode'] == ECGT_FROMTO_GROUPS)) {
            if ($data['from'] > $data['to']) {
                $errors['fromto'] = get_string('fromgttoerror', 'eitcoursegrouptools');
            }
            if ((clean_param($data['from'], PARAM_INT) < 0) || !ctype_digit($data['from'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('from').': '.
                                         get_string('mustbegt0', 'eitcoursegrouptools');
                } else {
                    $errors['fromto'] = get_string('from').': '.
                                        get_string('mustbegt0', 'eitcoursegrouptools');
                }
            }
            if ((clean_param($data['to'], PARAM_INT) < 0) || !ctype_digit($data['to'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('to').': '.
                                         get_string('mustbegt0', 'eitcoursegrouptools');
                } else {
                    $errors['fromto'] = get_string('to').': '.
                                        get_string('mustbegt0', 'eitcoursegrouptools');
                }
            }
            if ((clean_param($data['digits'], PARAM_INT) < 0) || !ctype_digit($data['digits'])) {
                if (isset($errors['fromto'])) {
                    $errors['fromto'] .= \html_writer::empty_tag('br').
                                         get_string('digits', 'eitcoursegrouptools').': '.
                                         get_string('mustbegt0', 'eitcoursegrouptools');
                } else {
                    $errors['fromto'] = get_string('digits', 'eitcoursegrouptools').': '.
                                        get_string('mustbegt0', 'eitcoursegrouptools');
                }
            }
        }

        return array_merge($parenterrors, $errors);
    }
    
     public function view_creation() {
        global $SESSION, $OUTPUT;

        $cgroup = new mod_eitcoursegrouptools\creategroup();
        
        $id = $this->cm->id;
        $context = context_course::instance($this->course->id);
        // Get applicable roles!
        $rolenames = array();
        if ($roles = get_profile_roles($context)) {
            foreach ($roles as $role) {
                $rolenames[$role->id] = strip_tags(role_get_name($role, $context));
            }
        }
        


        // Check if everything has been confirmed, so we can finally start working!
        if (optional_param('confirm', 0, PARAM_BOOL)) {
            if (isset($SESSION->eitcoursegrouptools->view_administration->createGroups)) {
                require_capability('mod/eitcoursegrouptools:create_groups', $this->context);
                // Create groups!
                $data = $SESSION->eitcoursegrouptools->view_administration;
                switch ($data->mode) {
                    case ECGT_GROUPS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                print_error('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps = $data->numberofgroups;
                        $userpergrp = floor($usercnt / $numgrps);
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case ECGT_MEMBERS_AMOUNT:
                        // Allocate members from the selected role to groups!
                        switch ($data->allocateby) {
                            case 'no':
                            case 'random':
                            case 'lastname':
                                $orderby = 'lastname, firstname';
                                break;
                            case 'firstname':
                                $orderby = 'firstname, lastname';
                                break;
                            case 'idnumber':
                                $orderby = 'idnumber';
                                break;
                            default:
                                //('unknoworder');
                        }
                        $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid, $orderby);
                        $usercnt = count($users);
                        $numgrps = ceil($usercnt / $data->numberofmembers);
                        $userpergrp = $data->numberofmembers;
                        if (!empty($data->nosmallgroups) and $usercnt % $data->numberofmembers != 0) {
                            /*
                             *  If there would be one group with a small number of member
                             *  reduce the number of groups
                             */
                            $missing = $userpergrp * $numgrps - $usercnt;
                            if ($missing > $userpergrp * (1 - ECGT_AUTOGROUP_MIN_RATIO)) {
                                // Spread the users from the last small group!
                                $numgrps--;
                                $userpergrp = floor($usercnt / $numgrps);
                            }
                        }
                        list($error, $preview) = $this->create_groups($data, $users, $userpergrp, $numgrps);
                        break;
                    case ECGT_1_PERSON_GROUPS:
                        $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid);
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $prev) = $this->create_one_person_groups($users, $data->namingscheme, $data->grouping, $data->groupingname);
                        $preview = $prev;
                        break;
                    case ECGT_N_M_GROUPS:
                        /* Shortcut here: create_fromto_groups does exactly what we want,
                         * with from = 1 and to = number of groups to create! */
                        $data->from = 1;
                        $data->to = $data->numberofgroups;
                        $data->digits = 1;
                    case ECGT_FROMTO_GROUPS:
                        if (!isset($data->groupingname)) {
                            $data->groupingname = null;
                        }
                        list($error, $preview) = $this->create_fromto_groups($data);
                        break;
                }
                $preview = $OUTPUT->notification($preview, $error ? 'error' : 'info');
                echo $OUTPUT->box(html_writer::tag('div', $preview, array('class' => 'centered')), 'generalbox');
            }
            unset($SESSION->eitcoursegrouptools->view_administration);
        }

                       // Create the form-object!
        $showgrpsize = $this->eitcoursegrouptools->use_size && $this->eitcoursegrouptools->use_individual;
        $mform = $this->create_groups(null, array('id' => $this->cm->id,
            'roles' => $rolenames,
            'show_grpsize' => $showgrpsize));
        unset($showgrpsize);


        if ($fromform = $mform->get_data()) {
            require_capability('mod/eitcoursegrouptools:create_groups', $this->context);
            // Save submitted data in session and show confirmation dialog!
            if (!isset($SESSION->eitcoursegrouptools)) {
                $SESSION->eitcoursegrouptools = new stdClass();
            }
            if (!isset($SESSION->eitcoursegrouptools->view_administration)) {
                $SESSION->eitcoursegrouptools->view_administration = new stdClass();
            }
            $SESSION->eitcoursegrouptools->view_administration = $fromform;
            $data = $SESSION->eitcoursegrouptools->view_administration;
            $preview = "";
            switch ($data->mode) {
                case ECGT_GROUPS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    switch ($data->allocateby) {
                        case 'no':
                        case 'random':
                        case 'lastname':
                            $orderby = 'lastname, firstname';
                            break;
                        case 'firstname':
                            $orderby = 'firstname, lastname';
                            break;
                        case 'idnumber':
                            $orderby = 'idnumber';
                            break;
                        default:
                            print_error('unknoworder');
                    }
                    $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid, $orderby);
                    $usercnt = count($users);
                    $numgrps = clean_param($data->numberofgroups, PARAM_INT);
                    $userpergrp = floor($usercnt / $numgrps);
                    list($error, $preview) = $mform->create_groups($data, $users, $userpergrp, $numgrps, true);
                    break;
                case ECGT_MEMBERS_AMOUNT:
                    // Allocate members from the selected role to groups!
                    switch ($data->allocateby) {
                        case 'no':
                        case 'random':
                        case 'lastname':
                            $orderby = 'lastname, firstname';
                            break;
                        case 'firstname':
                            $orderby = 'firstname, lastname';
                            break;
                        case 'idnumber':
                            $orderby = 'idnumber';
                            break;
                        default:
                            print_error('unknoworder');
                    }
                    $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid, $orderby);
                    $usercnt = count($users);
                    $numgrps = ceil($usercnt / $data->numberofmembers);
                    $userpergrp = clean_param($data->numberofmembers, PARAM_INT);
                    if (!empty($data->nosmallgroups) and $usercnt % clean_param($data->numberofmembers, PARAM_INT) != 0) {
                        /*
                         *  If there would be one group with a small number of member
                         *  reduce the number of groups
                         */
                        $missing = $userpergrp * $numgrps - $usercnt;
                        if ($missing > $userpergrp * (1 - ECGT_AUTOGROUP_MIN_RATIO)) {
                            // Spread the users from the last small group!
                            $numgrps--;
                            $userpergrp = floor($usercnt / $numgrps);
                        }
                    }
                    list($error, $preview) = $mform->create_groups($data, $users, $userpergrp, $numgrps, true);
                    break;
                case ECGT_1_PERSON_GROUPS:
                    $users = groups_get_potential_members($this->course->id, $data->roleid, $data->cohortid);
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $prev) = $this->create_one_person_groups($users, $data->namingscheme, $data->grouping, $data->groupingname, true);
                    $preview = $prev;
                    break;
                case ECGT_N_M_GROUPS:
                    /* Shortcut here: create_fromto_groups does exactly what we want,
                     * with from = 1 and to = number of groups to create! */
                    $data->from = 1;
                    $data->to = $data->numberofgroups;
                    $data->digits = 1;
                case ECGT_FROMTO_GROUPS:
                    if (!isset($data->groupingname)) {
                        $data->groupingname = null;
                    }
                    list($error, $preview) = $this->create_fromto_groups($data, true);
                    break;
            }
            $preview = html_writer::tag('div', $preview, array('class' => 'centered'));
            $tab = required_param('tab', PARAM_ALPHANUMEXT);
            if ($error) {
                $text = get_string('create_groups_confirm_problem', 'eitcoursegrouptools');
                $url = new moodle_url("view.php?id=$id&tab=" . $tab);
                $back = new single_button($url, get_string('back'), 'post');
                $confirmboxcontent = $this->confirm($text, $back);
            } else {
                $continue = "view.php?id=$id&tab=" . $tab . "&confirm=true";
                $cancel = "view.php?id=$id&tab=" . $tab;
                $text = get_string('create_groups_confirm', 'eitcoursegrouptools');
                $confirmboxcontent = $this->confirm($text, $continue, $cancel);
            }
            echo $OUTPUT->heading(get_string('preview'), 2, 'centered') .
            $OUTPUT->box($preview, 'generalbox') .
            $confirmboxcontent;
        } else {
            $mform->display();
        }
    }
    
    public function create_groups($data, $users, $userpergrp, $numgrps, $previewonly = false) {
        global $DB, $USER;

        require_capability('mod/eitcoursegrouptools:create_groups', $this->context);

        $namestouse = array();

        // Allocate members from the selected role to groups!
        $usercnt = count($users);
        switch ($data->allocateby) {
            case 'no':
            case 'random':
            case 'lastname':
                $orderby = 'lastname, firstname';
                break;
            case 'firstname':
                $orderby = 'firstname, lastname';
                break;
            case 'idnumber':
                $orderby = 'idnumber';
                break;
            default:
                print_error('unknoworder');
        }

        if ($data->allocateby == 'random') {
            srand($data->seed);
            shuffle($users);
        }

        $groups = array();

        // Allocate the users - all groups equal count first!
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i] = array();
            $groups[$i]['members'] = array();
            if ($data->allocateby == 'no') {
                continue; // Do not allocate users!
            }
            for ($j = 0; $j < $userpergrp; $j++) {
                if (empty($users)) {
                    break 2;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Now distribute the rest!
        if ($data->allocateby != 'no') {
            for ($i = 0; $i < $numgrps; $i++) {
                if (empty($users)) {
                    break 1;
                }
                $user = array_shift($users);
                $groups[$i]['members'][$user->id] = $user;
            }
        }
        // Every member is there, so we can parse the name!
        $digits = ceil(log10($numgrps));
        for ($i = 0; $i < $numgrps; $i++) {
            $groups[$i]['name'] = $this->groups_parse_name(trim($data->namingscheme), $i, $groups[$i]['members'], $digits);
        }
        if ($previewonly) {
            $error = false;
            $table = new html_table();
            if ($data->allocateby == 'no') {
                $table->head = array(get_string('groupscount', 'group', $numgrps));
                $table->size = array('100%');
                $table->align = array('left');
                $table->width = '40%';
            } else {
                $table->head = array(get_string('groupscount', 'group', $numgrps),
                    get_string('groupmembers', 'group'),
                    get_string('usercounttotal', 'group', $usercnt));
                $table->size = array('20%', '70%', '10%');
                $table->align = array('left', 'left', 'center');
                $table->width = '90%';
            }
            $table->data = array();

            foreach ($groups as $group) {
                $line = array();
                if (@groups_get_group_by_name($this->course->id, $group['name']) || in_array($group['name'], $namestouse)) {
                    $error = true;
                    if (in_array($group['name'], $namestouse)) {
                        $line[] = '<span class="notifyproblem">' .
                                get_string('nameschemenotunique', 'eitcoursegrouptools', $group['name']) . '</span>';
                    } else {
                        $line[] = '<span class="notifyproblem">' .
                                get_string('groupnameexists', 'group', $group['name']) . '</span>';
                    }
                } else {
                    $line[] = $group['name'];
                    $namestouse[] = $group['name'];
                }
                if ($data->allocateby != 'no') {
                    $unames = array();
                    foreach ($group['members'] as $user) {
                        $unames[] = fullname($user);
                    }
                    $line[] = implode(', ', $unames);
                    $line[] = count($group['members']);
                }
                $table->data[] = $line;
            }
            return array(0 => $error, 1 => html_writer::table($table));
        } else {
            $grouping = null;
            $createdgrouping = null;
            $createdgroups = array();
            $failed = false;

            // Prepare grouping!
            if (!empty($data->grouping)) {
                if ($data->grouping < 0) {
                    $grouping = new stdClass();
                    $grouping->courseid = $this->course->id;
                    $grouping->name = trim($data->groupingname);
                    $grouping->id = groups_create_grouping($grouping);
                    $createdgrouping = $grouping->id;
                } else {
                    $grouping = groups_get_grouping($data->grouping);
                }
            }

            // Trigger group_creation_started event.
            $groupingid = !empty($grouping) ? $grouping->id : 0;
            switch ($data->mode) {
                case ECGT_GROUPS_AMOUNT:
                    ////\mod_eitcoursegrouptools\event\group_creation_started::create_groupamount($this->cm, $data->namingscheme, $data->numberofgroups, $groupingid)->trigger();
                    break;
                case ECGT_MEMBERS_AMOUNT:
                    ////\mod_eitcoursegrouptools\event\group_creation_started::create_memberamount($this->cm, $data->namingscheme, $data->numberofmembers, $groupingid)->trigger();
                    break;
            }

            // Save the groups data!
            foreach ($groups as $group) {
                if (@groups_get_group_by_name($this->course->id, $group['name'])) {
                    $error = get_string('groupnameexists', 'group', $group['name']);
                    $failed = true;
                    continue;
                }
                $newgroup = new stdClass();
                $newgroup->courseid = $this->course->id;
                $newgroup->name = $group['name'];
                $groupid = groups_create_group($newgroup);
                $this->add_agrp_entry($groupid);
                $createdgroups[] = $groupid;
                foreach ($group['members'] as $user) {
                    groups_add_member($groupid, $user->id);
                    $usrreg = new stdClass();
                    $usrreg->userid = $user->id;
                    $usrreg->agrpid = $newagrp->id;
                    $usrreg->timestamp = time();
                    $usrreg->modified_by = $USER->id;
                    $attr = array('userid' => $user->id,
                        'agrpid' => $newagrp->id);
                    if (!$DB->record_exists('ecgt_registered', $attr)) {
                        $DB->insert_record('ecgt_registered', $usrreg);
                    } else {
                        $DB->set_field('ecgt_registered', 'modified_by', $USER->id, $attr);
                    }
                }
                if ($grouping) {
                    groups_assign_grouping($grouping->id, $groupid);
                }
            }

            if ($failed) {
                foreach ($createdgroups as $groupid) {
                    groups_delete_group($groupid);
                }
                if ($createdgrouping) {
                    groups_delete_grouping($createdgrouping);
                }
            } else {
                // Trigger agrps updated via groupcreation event.
                $groupingid = !empty($grouping) ? $grouping->id : 0;
                ////\mod_eitcoursegrouptools\event\agrps_updated::create_groupcreation($this->cm, $data->namingscheme, $numgrps, $groupingid)->trigger();
            }
        }
        if (empty($failed)) {
            $preview = get_string('groups_created', 'eitcoursegrouptools');
        } else if (empty($preview)) {
            if (!empty($error)) {
                $preview = $error;
            } else {
                $preview = get_string('group_creation_failed', 'eitcoursegrouptools');
            }
        }

        return array($failed, $preview);
    }
    
     private function groups_parse_name($namescheme, $groupnumber, $members = null, $digits = 0) {

        $tags = array('firstname', 'lastname', 'idnumber', 'username');
        $pregsearch = "#\[(" . implode("|", $tags) . ")\]#";
        if (preg_match($pregsearch, $namescheme) > 0) {
            if ($members != null) {
                $data = array();
                if (is_array($members)) {
                    foreach ($tags as $key => $tag) {
                        foreach ($members as $member) {
                            if (!empty($member->$tag)) {
                                if (isset($data[$key]) && $data[$key] != "") {
                                    $data[$key] .= "-";
                                } else if (!isset($data[$key])) {
                                    $data[$key] = "";
                                }

                                $data[$key] .= substr($member->$tag, 0, 3);
                            }
                        }
                        if (empty($data[$key])) {
                            $data[$key] = "no" . $tag . "#";
                        }
                    }
                } else {
                    foreach ($tags as $key => $tag) {

                        if (!empty($members->$tag)) {
                            $data[$key] = $members->$tag;
                        } else {
                            $data[$key] = "no" . $tag . "#";
                        }
                    }
                }
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[" . $tag . "]";
                }
                $namescheme = str_replace($tags, $data, $namescheme);
            } else {
                foreach ($tags as $key => $tag) {
                    $tags[$key] = "[" . $tag . "]";
                }
                $namescheme = str_replace($tags, "", $namescheme);
            }
        }

        if (strstr($namescheme, '@') !== false) { // Convert $groupnumber to a character series!
            if ($groupnumber > ECGT_BEP) {
                $nexttempnumber = $groupnumber;
                $string = "";
                $orda = ord('A');
                $ordz = ord('Z');
                do {
                    $tempnumber = $nexttempnumber;
                    $mod = ($tempnumber) % ($ordz - $orda + 1);
                    $letter = chr($orda + $mod);
                    $string .= $letter;
                    $nexttempnumber = floor(($tempnumber) / ($ordz - $orda + 1)) - 1;
                } while ($tempnumber >= ($ordz - $orda + 1));

                $namescheme = str_replace('@', strrev($string), $namescheme);
            } else {
                $letter = 'A';
                for ($i = 0; $i < $groupnumber; $i++) {
                    $letter++;
                }
                $namescheme = str_replace('@', $letter, $namescheme);
            }
        }

        if (strstr($namescheme, '#') !== false) {
            if ($digits != 0) {
                $format = '%0' . $digits . 'd';
            } else {
                $format = '%d';
            }
            $namescheme = str_replace('#', sprintf($format, $groupnumber + 1), $namescheme);
        }
        return $namescheme;
    }
}
