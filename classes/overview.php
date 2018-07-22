<?php

defined('MOODLE_INTERNAL') || die;

class mod_eitcoursegrouptools_overview {

    protected $cm;

    /** @var object */
    protected $course;

    /** @var object */
    protected $eitcoursegrouptools;

    /** @var object instance's context record */
    protected $context;

    public function __construct($mod_eitcoursegrouptools, $cm, $course, $context) {
     
        $this->eitcoursegrouptools = $mod_eitcoursegrouptools;
        $this->cm = $cm;
        $this->course = $course;
        $this->context = $context;
    }

//
//    public function get_grouping_select($url, $groupingid) {
//        $groupings = groups_get_all_groupings($this->course->id);
//        $options = array(0 => get_string('all'));
//        if (count($groupings)) {
//            foreach ($groupings as $grouping) {
//                $options[$grouping->id] = $grouping->name;
//            }
//        }
//        return new single_select($url, 'groupingid', $options, $groupingid, false);
//    }

    public function get_groups_select($url, $groupingid, $groupid) {
        global $OUTPUT;
        
  

        $groups = $this->get_active_groups(false, false, 0, 0, $groupingid);
        $options = array(0 => get_string('all'));
        if (count($groups)) {
            foreach ($groups as $group) {
                $options[$group->id] = $group->name;
            }
        }
        if (!key_exists($groupid, $options)) {
            $groupid = 0;
            $url->param('groupid', 0);
            echo $OUTPUT->box($OUTPUT->notification(get_string('group_not_in_grouping', 'eitcoursegrouptools') .
                            html_writer::empty_tag('br') .
                            get_string('switched_to_all_groups', 'eitcoursegrouptools'), 'error'), 'generalbox centered');
        }
        return new single_select($url, 'groupid', $options, $groupid, false);
    }

    public function get_orientation_select($url, $orientation) {
        static $options = null;

        if (!$options) {
            $options = array(0 => get_string('portrait', 'eitcoursegrouptools'),
                1 => get_string('landscape', 'eitcoursegrouptools'));
        }

        return new single_select($url, 'orientation', $options, $orientation, false);
    }

    public function group_overview_table($groupingid = 0, $groupid = 0, $onlydata = false, $includeinactive = false) {
        global $OUTPUT, $CFG, $DB;
        if (!$onlydata) {
            $orientation = optional_param('orientation', 0, PARAM_BOOL);
            $downloadurl = new moodle_url('/mod/eitcoursegrouptools/download.php', array('id' => $this->cm->id,
                'groupingid' => $groupingid,
                'groupid' => $groupid,
                'orientation' => $orientation,
                'sesskey' => sesskey(),
                'tab' => 'overview',
                'inactive' => $includeinactive));
        } else {
            $return = array();
        }

        // We just get an overview and fetch data later on a per group basis to save memory!
        $agrps = $this->get_active_groups(false, false, 0, $groupid, $groupingid, true, $includeinactive);
        $groupinfo = groups_get_all_groups($this->eitcoursegrouptools->course);
        $userinfo = array();
        $syncstatus = $this->get_sync_status();
        $context = context_module::instance($this->cm->id);
        if (!$onlydata && count($agrps)) {
            // Global-downloadlinks!
            //echo $this->get_download_links($downloadurl);
        }

        foreach ($agrps as $agrp) {
            // We give each group 30 seconds (minimum) and hope it doesn't time out because of no output in case of download!
            core_php_time_limit::raise(30);
            $groupdata = new stdClass();
            $groupdata->name = $groupinfo[$agrp->id]->name . ($agrp->active ? '' : ' (' . get_string('inactive') . ')');

            // Get all registered userids!
            $select = " agrpid = ? AND modified_by >= 0 ";
            $registered = $DB->get_fieldset_select('ecgt_registered', 'userid', $select, array($agrp->agrpid));
            // Get all moodle-group-member-ids!
            $select = " groupid = ? ";
            $members = $DB->get_fieldset_select('groups_members', 'userid', $select, array($agrp->id));
            // Get all registered users with moodle-group-membership!
            $absregs = array_intersect($registered, $members);
            // Get all registered users without moodle-group-membership!
            $gtregs = array_diff($registered, $members);
            // Get all moodle-group-members without registration!
            $mdlregs = array_diff($members, $registered);
            // Get all queued users!
            $select = " agrpid = ? ";
            $queued = $DB->get_fieldset_select('ecgt_queued', 'userid', $select, array($agrp->agrpid));

            // We give additional 1 second per registration/queue/moodle entry in this group!
            core_php_time_limit::raise(30 * (count($registered) + count($members) + count($queued)));

            if (!empty($this->eitcoursegrouptools->use_size)) {
                if (!empty($this->eitcoursegrouptools->use_individual) && !empty($agrp->grpsize)) {
                    $size = $agrp->grpsize;
                    $free = $agrp->grpsize - count($registered);
                } else {
                    $size = !empty($this->eitcoursegrouptools->grpsize) ? $this->eitcoursegrouptools->grpsize : get_config('mod_eitcoursegrouptools', 'grpsize');
                    $free = ($size - count($registered));
                }
            } else {
                $size = "no limit";
                $free = "no limit";
            }

            $groupdata->total = $size;
            $groupdata->registered = count($registered);
            $groupdata->queued = count($queued);
            $groupdata->free = $free;
            $groupdata->reg_data = array();
            $groupdata->queue_data = array();
            $groupdata->inactive = !$agrp->active;
            if ($agrp->active) {
                $groupdata->uptodate = $syncstatus[1][$agrp->agrpid]->status === ECGT_UPTODATE;
                $groupdata->outdated = $syncstatus[1][$agrp->agrpid]->status !== ECGT_UPTODATE;
            }
            // User-ID will be added in template!
            $groupdata->userlink = $CFG->wwwroot . '/user/view.php?course=' . $this->eitcoursegrouptools->course . '&id=';
            $groupdata->groupid = $groupinfo[$agrp->id]->id;
//            $groupdata->formattxt = GROUPTOOL_TXT;
//            $groupdata->formatpdf = GROUPTOOL_PDF;
//            $groupdata->formatxlsx = GROUPTOOL_XLSX;
//            $groupdata->formatods = GROUPTOOL_ODS;
            $statushelp = new help_icon('status', 'mod_eitcoursegrouptools');
            if (!$onlydata) {
                $groupdata->statushelp = $statushelp->export_for_template($OUTPUT);
                // Format will be added in template!
                //$groupdownloadurl = new moodle_url($downloadurl, array('groupid' => $groupinfo[$agrp->id]->id));
                //$groupdata->downloadurl = $groupdownloadurl->out(false);
            }

            // We create a dummy user-object to get the fullname-format!
            $dummy = new stdClass();
            $namefields = get_all_user_name_fields();
            foreach ($namefields as $namefield) {
                $dummy->$namefield = $namefield;
            }
            $fullnameformat = fullname($dummy);
            // Now get the ones used in fullname in the correct order!
            $namefields = order_in_string($namefields, $fullnameformat);

            if (count($registered) + count($members) >= 1) {
                if (count($absregs) >= 1) {
                    foreach ($absregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = array();
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        foreach ($namefields as $field) {
                            $row[$field] = $userinfo[$curuser]->$field;
                        }
                        // We set those in any case, because PDF and TXT export needs them anyway!
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "✔ Registered";
                        $groupdata->reg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($gtregs) >= 1) {
                    foreach ($gtregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = array();
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        foreach ($namefields as $field) {
                            $row[$field] = $userinfo[$curuser]->$field;
                        }
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "+ Waiting!";
                        $groupdata->reg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }

                if (count($mdlregs) >= 1) {
                    foreach ($mdlregs as $curuser) {
                        if (!array_key_exists($curuser, $userinfo)) {
                            $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                        }
                        $fullname = fullname($userinfo[$curuser]);

                        $row = array();
                        $row['userid'] = $curuser;
                        $row['name'] = $fullname;
                        foreach ($namefields as $field) {
                            $row[$field] = $userinfo[$curuser]->$field;
                        }
                        // We set those in any case, because PDF and TXT export needs them anyway!
                        $row['email'] = $userinfo[$curuser]->email;
                        $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        $row['status'] = "? - Not synchronised";
                        $groupdata->mreg_data[] = $row;
                        $row = null;
                        unset($row);
                    }
                    $regentry = null;
                    unset($regentry);
                }
            }

            if (count($queued) >= 1) {
                $queuedlist = $DB->get_records('ecgt_queued', array('agrpid' => $agrp->agrpid), 'timestamp ASC');
                foreach ($queued as $curuser) {
                    if (!array_key_exists($curuser, $userinfo)) {
                        $userinfo[$curuser] = $DB->get_record('user', array('id' => $curuser));
                    }
                    $fullname = fullname($userinfo[$curuser]);
                    $rank = $this->get_rank_in_queue($queuedlist, $curuser);

                    $row = array();
                    $row['userid'] = $curuser;
                    $row['rank'] = $rank;
                    $row['name'] = $fullname;
                    foreach ($namefields as $namefield) {
                        if (!empty($userinfo[$curuser]->$namefield)) {
                            $row[$namefield] = $userinfo[$curuser]->$namefield;
                        } else {
                            $row[$namefield] = '';
                        }
                    }
                    if (empty($CFG->showuseridentity)) {
                        if (!empty($userinfo[$curuser]->idnumber)) {
                            $row['idnumber'] = $userinfo[$curuser]->idnumber;
                        } else {
                            $row['idnumber'] = '-';
                        }
                        if (!empty($userinfo[$curuser]->email)) {
                            $row['email'] = $userinfo[$curuser]->email;
                        } else {
                            $row['email'] = '-';
                        }
                    } else {
                        $fields = explode(',', $CFG->showuseridentity);
                        foreach ($fields as $field) {
                            if (!empty($userinfo[$curuser]->$field)) {
                                $row[$field] = $userinfo[$curuser]->$field;
                            } else {
                                $row[$field] = '';
                            }
                        }
                    }
                    // We set those in any case, because PDF and TXT export needs them anyway!
                    $row['email'] = $userinfo[$curuser]->email;
                    $row['idnumber'] = $userinfo[$curuser]->idnumber;
                    $groupdata->queue_data[] = $row;
                }
            }
            if (!$onlydata) {
                echo $OUTPUT->render_from_template('mod_eitcoursegrouptools/overviewgroup', $groupdata);
            } else {
                $return[] = $groupdata;
            }
            $groupdata = null;
            unset($groupdata);
        }

        if (count($agrps) == 0) {
            $boxcontent = $OUTPUT->notification(get_string('no_data_to_display', 'eitcoursegrouptools'), 'error');
            $return = $OUTPUT->box($boxcontent, 'generalbox centered');
            if (!$onlydata) {
                echo $return;
            }
        }
        if ($onlydata) {
            return $return;
        } else {
            return 0;
        }
    }

    public function get_active_groups($includeregs = false, $includequeues = false, $agrpid = 0, $groupid = 0, $groupingid = 0, $indexbygroup = true, $includeinactive = false) {
        global $DB;

        //echo '<hr> g_active_groups <hr>';
        
        require_capability('mod/eitcoursegrouptools:view_groups', $this->context);

        $params = array('grouptoolid' => $this->cm->instance);

        if (!empty($agrpid)) {
            $agrpidwhere = " AND agrp.id = :agroup";
            $params['agroup'] = $agrpid;
        } else {
            $agrpidwhere = "";
        }
        if (!empty($groupid)) {
            $groupidwhere = " AND grp.id = :groupid";
            $params['groupid'] = $groupid;
        } else {
            $groupidwhere = "";
        }
        if (!empty($groupingid)) {
            $groupingidwhere = " AND grpgs.id = :groupingid";
            $params['groupingid'] = $groupingid;
        } else {
            $groupingidwhere = "";
        }

        if (!empty($this->eitcoursegrouptools->use_size)) {
            if (empty($this->eitcoursegrouptools->use_individual)) {
                $sizesql = " " . $this->eitcoursegrouptools->grpsize . " grpsize,";
            } else {
                $grouptoolgrpsize = get_config('mod_eitcoursegrouptools', 'grpsize');
                $grpsize = (!empty($this->eitcoursegrouptools->grpsize) ? $this->eitcoursegrouptools->grpsize : $grouptoolgrpsize);
                if (empty($grpsize)) {
                    $grpsize = 3;
                }
                $sizesql = " COALESCE(agrp.grpsize, " . $grpsize . ") AS grpsize,";
            }
        } else {
            $sizesql = "";
        }
        if ($indexbygroup) {
            $idstring = "grp.id AS id, agrp.id AS agrpid";
        } else {
            $idstring = "agrp.id AS agrpid, grp.id AS id";
        }

        $params['agrpgrptlid'] = $this->cm->instance;

        if (!$includeinactive) {
            $active = " AND agrp.active = 1 ";
        } else {
            $active = "";
        }

        $groupdata = $DB->get_records_sql("
                   SELECT " . $idstring . ", MAX(grp.name) AS name," . $sizesql . " MAX(agrp.sort_order) AS sort_order,
                          agrp.active AS active
                     FROM {groups} grp
                LEFT JOIN {ecgt_activegroups} agrp ON agrp.groupid = grp.id AND agrp.grouptoolid = :agrpgrptlid
                LEFT JOIN {groupings_groups} ON {groupings_groups}.groupid = grp.id
                LEFT JOIN {groupings} grpgs ON {groupings_groups}.groupingid = grpgs.id
                    WHERE agrp.grouptoolid = :grouptoolid " . $active .
                $agrpidwhere . $groupidwhere . $groupingidwhere . "
                 GROUP BY grp.id, agrp.id
                 ORDER BY sort_order ASC, name ASC", $params);
        if (!empty($groupdata)) {
            foreach ($groupdata as $key => $group) {
                $groupingids = $DB->get_fieldset_select('groupings_groups', 'groupingid', 'groupid = ?', array($group->id));
                if (!empty($groupingids)) {
                    $groupdata[$key]->classes = implode(',', $groupingids);
                } else {
                    $groupdata[$key]->classes = '';
                }
            }

            if ((!empty($this->eitcoursegrouptools->use_size) && !$this->eitcoursegrouptools->use_individual) || ($this->eitcoursegrouptools->use_queue && $includequeues) || ($includeregs)) {
                $keys = array_keys($groupdata);
                foreach ($keys as $key) {
                    $groupdata[$key]->queued = null;
                    if ($includequeues && $this->eitcoursegrouptools->use_queue) {
                        $attr = array('agrpid' => $groupdata[$key]->agrpid);
                        $groupdata[$key]->queued = (array) $DB->get_records('ecgt_queued', $attr);
                    }

                    $groupdata[$key]->registered = null;
                    if ($includeregs) {
                        $params = array('agrpid' => $groupdata[$key]->agrpid);
                        $where = "agrpid = :agrpid AND modified_by >= 0";
                        $groupdata[$key]->registered = $DB->get_records_select('ecgt_registered', $where, $params);
                        $params['modifierid'] = -1;
                        $where = "agrpid = :agrpid AND modified_by = :modifierid";
                        $groupdata[$key]->marked = $DB->get_records_select('ecgt_registered', $where, $params);
                        $groupdata[$key]->moodle_members = groups_get_members($groupdata[$key]->id);
                    }
                }
                unset($key);
            }
        } else {
            $groupdata = array();
        }
        
      

        return $groupdata;
    }

    public function get_sync_status($grouptoolid = 0) {
        global $DB;
        $outofsync = false;

        if (empty($grouptoolid)) {
            $grouptoolid = $this->eitcoursegrouptools->id;
            $this->eitcoursegrouptools->id;
        }

        // We use MAX to trick postgres into thinking this is a full group_by statement!
        $sql = "SELECT agrps.id AS agrpid, MAX(agrps.groupid) AS groupid,
                       COUNT(DISTINCT reg.userid) AS grptoolregs,
                       COUNT(DISTINCT mreg.userid) AS mdlregs
                  FROM {ecgt_activegroups} agrps
             LEFT JOIN {ecgt_registered} reg ON agrps.id = reg.agrpid AND reg.modified_by >= 0
             LEFT JOIN {groups_members} mreg ON agrps.groupid = mreg.groupid
                                             AND reg.userid = mreg.userid
                  WHERE agrps.active = 1 AND agrps.grouptoolid = ?
               GROUP BY agrps.id";
       
        $return = $DB->get_records_sql($sql, array($grouptoolid));
        
       // echo print_r($return);

        foreach ($return as $key => $group) {
            $return[$key]->status = ($group->grptoolregs > $group->mdlregs) ? ECGT_OUTDATED : ECGT_UPTODATE;
            $outofsync |= ($return[$key]->status == ECGT_OUTDATED);
        }
        return array($outofsync, $return);
    }
    
    public function push_registrations($groupid = 0, $groupingid = 0, $previewonly = false) {
        global $DB, $CFG;

        // Trigger the event!
        //\mod_eitcoursegrouptools\event\registration_push_started::create_from_object($this->cm)->trigger();

        $userinfo = get_enrolled_users($this->context);
        $return = array();
        // Get active groups filtered by groupid, grouping_id, grouptoolid!
        $agrps = $this->get_active_groups(true, false, 0, $groupid, $groupingid);
        foreach ($agrps as $groupid => $agrp) {
            foreach ($agrp->registered as $reg) {
                $info = new stdClass();
                if (!key_exists($reg->userid, $userinfo)) {
                    $userinfo[$reg->userid] = $DB->get_record('user', array('id' => $reg->userid));
                }
                $info->username = fullname($userinfo[$reg->userid]);
                $info->groupname = $agrp->name;
                if (!groups_is_member($groupid, $reg->userid)) {
                    // Add to group if is not already!
                    if (!$previewonly) {
                        if (!is_enrolled($this->context, $reg->userid)) {
                            /*
                             * if user's not enrolled already we force manual enrollment in course,
                             * so we can add the user to the group
                             */
                            try {
                                $this->force_enrol_student($reg->userid);
                            } catch (Exception $e) {
                                $row->cells[] = new html_table_cell($OUTPUT->notification($e->getMessage(), 'error'));
                            } catch (Throwable $t) {
                               // $row->cells[] = new html_table_cell($OUTPUT->notification($t->getMessage(), 'error'));
                                echo "Some error occured ". $t->getMessage();
                            }
                        }
                        if (groups_add_member($groupid, $reg->userid)) {
                            $return[] = html_writer::tag('div', get_string('added_member', 'eitcoursegrouptools', $info), array('class' => 'notifysuccess'));
                        } else {
                            $return[] = html_writer::tag('div', get_string('could_not_add', 'eitcoursegrouptools', $info), array('class' => 'notifyproblem'));
                        }
                    } else {
                        $return[] = html_writer::tag('div', get_string('add_member', 'eitcoursegrouptools', $info), array('class' => 'notifysuccess'));
                    }
                } else {
                    $return[] = html_writer::tag('div', get_string('already_member', 'eitcoursegrouptools', $info), array('class' => 'ignored'));
                }
            }
        }
        switch (count($return)) {
            default:
                return array(false, implode("<br />\n", $return));
                break;
            case 1:
                return array(false, current($return));
                break;
            case 0:
                return array(true, get_string('nothing_to_push', 'eitcoursegrouptools'));
                break;
        }
    }
    
      protected function force_enrol_student($userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/enrol/manual/locallib.php');
        require_once($CFG->libdir.'/accesslib.php');
        if (!$enrolmanual = enrol_get_plugin('manual')) {
            throw new coding_exception(get_string('cant_enrol', 'eitcoursegrouptool'));
        }
        if (!$instance = $DB->get_record('enrol', array('courseid' => $this->course->id,
                                                        'enrol'    => 'manual'), '*', IGNORE_MISSING)) {
            if ($enrolmanual->add_default_instance($this->course)) {
                $instance = $DB->get_record('enrol', array('courseid' => $this->course->id,
                                                           'enrol'    => 'manual'), '*', MUST_EXIST);
            }
        }
        if ($instance != false) {
            $archroles = get_archetype_roles('student');
            $archrole = array_shift($archroles);
            $enrolmanual->enrol_user($instance, $userid, $archrole->id, time());
        } else {
            throw new coding_exception(get_string('cant_enrol', 'eitcoursegrouptools'));
        }
    }

}
