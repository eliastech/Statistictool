<?php

require_once($CFG->dirroot . '/mod/eitcoursegrouptools/definitions.php');

class mod_eitcoursegrouptools_joingroups {

    const HIDE_GROUPMEMBERS = ECGT_HIDE_GROUPMEMBERS;
    const SHOW_GROUPMEMBERS_AFTER_DUE = ECGT_SHOW_GROUPMEMBERS_AFTER_DUE;
    const SHOW_OWN_GROUPMEMBERS_AFTER_DUE = ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_DUE;
    const SHOW_OWN_GROUPMEMBERS_AFTER_REG = ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_REG;
    const SHOW_GROUPMEMBERS = ECGT_SHOW_GROUPMEMBERS;

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

    public function is_registration_open() {
        
        //echo "Mod ". print_r($this->eitcoursegrouptools);
        
        return $this->eitcoursegrouptools->allow_reg;//($this->eitcoursegrouptools->allow_reg && (($this->eitcoursegrouptools->timedue == 0) || (time() < $this->eitcoursegrouptools->timedue)) && (time() > $this->eitcoursegrouptools->timeavailable));
    }

    public function get_registration_stats($userid = null) {
        global $USER, $DB;
        $return = new stdClass();
        $return->group_places = 0;
        $return->free_places = 0;
        $return->occupied_places = 0;
        $return->users = 0;
        $return->registered = array();
        $return->queued = array();
        $return->queued_users = 0;
        $return->reg_users = 0;

        switch ($userid) {
            case null:
                $userid = $USER->id;
            default:
                $groups = $this->get_active_groups(false, false);
                echo "GRP ". print_r($groups);
                break;
            case 0:
                $groups = $this->get_active_groups();
        }

        foreach ($groups as $group) {
            $group = $this->get_active_groups(true, true, $group->agrpid, $group->id);
            $group = current($group);
            if ($this->eitcoursegrouptools->use_size) {
                $return->group_places += $group->grpsize;
            }
            $return->occupied_places += count($group->registered);
            if ($userid != 0) {
                $regrank = $this->get_rank_in_queue($group->registered, $userid);
                if (!empty($regrank)) {
                    $regdata = new stdClass();
                    $regdata->rank = $regrank;
                    $regdata->grpname = $group->name;
                    $regdata->agrpid = $group->agrpid;
                    reset($group->registered);
                    do {
                        $current = current($group->registered);
                        $regdata->timestamp = $current->timestamp;
                        next($group->registered);
                    } while ($current->userid != $userid);
                    $regdata->id = $group->id;
                    $return->registered[] = $regdata;
                }

                $queuerank = $this->get_rank_in_queue($group->queued, $userid);
                if (!empty($queuerank)) {
                    $queuedata = new stdClass();
                    $queuedata->rank = $queuerank;
                    $queuedata->grpname = $group->name;
                    $queuedata->agrpid = $group->agrpid;
                    reset($group->queued);
                    do {
                        $current = current($group->queued);
                        $queuedata->timestamp = $current->timestamp;
                        next($group->queued);
                    } while ($current->userid != $userid);
                    $queuedata->id = $group->id;
                    $return->queued[] = $queuedata;
                }
            }
        }
        $return->free_places = ($this->eitcoursegrouptools->use_size) ? ($return->group_places - $return->occupied_places) : null;
        $return->users = count_enrolled_users($this->context, 'mod/eitcoursegrouptools:register');

        $agrps = $DB->get_records('ecgt_activegroups', array('grouptoolid' => $this->cm->instance, 'active' => 1));
        if (is_array($agrps) && count($agrps) >= 1) {
            $agrpids = array_keys($agrps);
            list($inorequal, $params) = $DB->get_in_or_equal($agrpids);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {ecgt_registered}
                     WHERE modified_by >= 0 AND agrpid " . $inorequal;
            $return->reg_users = $DB->count_records_sql($sql, $params);
            $sql = "SELECT count(DISTINCT userid)
                      FROM {ecgt_queued}
                     WHERE agrpid " . $inorequal;
            $return->queued_users = $DB->count_records_sql($sql, $params);
        } else {
            $return->reg_users = 0;
        }
        $return->notreg_users = $return->users - $return->reg_users;

        return $return;
    }

    public function get_active_groups($includeregs = false, $includequeues = false, $agrpid = 0, $groupid = 0, $groupingid = 0, $indexbygroup = true, $includeinactive = false) {
        global $DB;

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

    public function get_rank_in_queue($data = 0, $userid = 0) {
        global $DB, $USER;

        if (is_array($data)) { // It's the queue itself!
            //uasort($data, array(&$this, $this->cmptimestamp()));
            $i = 1;
            foreach ($data as $entry) {
                if ($entry->userid == $userid) {
                    return $i;
                } else {
                    $i++;
                }
            }
            return false;
        } else if (!empty($data)) { // It's an active-group-id, so we gotta get the queue data!
            $params = array('agrpid' => $data,
                'userid' => !empty($userid) ? $userid : $USER->id);
            $sql = "SELECT count(b.id) AS rank
                      FROM {ecgt_queued} a
                INNER JOIN {ecgt_queued} b ON b.timestamp <= a.timestamp
                     WHERE a.agrpid = :agrpid AND a.userid = :userid";
        } else {
            return null;
        }

        return $DB->count_records_sql($sql, $params);
    }

    public function canshowmembers($agrp = null, $regrank = null, $queuerank = null) {
        global $DB, $USER;

        $showmembers = false;

        if ($regrank === null || $queuerank === null) {
            if (is_numeric($agrp)) {
                $agrpid = $agrp;
            } else if (is_object($agrp) && isset($agrp->id)) {
                $agrpid = $agrp->id;
            } else {
                throw new coding_exception('$agrp has to be the active group ID or an object containing $agrp->id');
            }

            if ($regrank === null) {
                $regrank = $DB->record_exists('ecgt_registered', array('userid' => $USER->id, 'agrpid' => $agrpid));
            }

            if ($queuerank === null) {
                $queuerank = $DB->record_exists('ecgt_queued', array('userid' => $USER->id, 'agrpid' => $agrpid));
            }
        }

        switch ($this->eitcoursegrouptools->show_members) {
            case self::SHOW_GROUPMEMBERS:
                $showmembers = true;
                break;
            case self::SHOW_GROUPMEMBERS_AFTER_DUE:
                $showmembers = (time() > $this->eitcoursegrouptools->timedue);
                break;
            case self::SHOW_OWN_GROUPMEMBERS_AFTER_REG:
                $showmembers = ($regrank !== false) || ($queuerank !== false);
                break;
            case self::SHOW_OWN_GROUPMEMBERS_AFTER_DUE:
                $showmembers = (time() > $this->eitcoursegrouptools->timedue) && (($regrank !== false) || ($queuerank !== false));
                break;
            default:
            case self::HIDE_GROUPMEMBERS:
                $showmembers = false;
                break;
        }

        return $showmembers;
    }

    public function grpmarked($agrpid, $userid = 0) {
        global $DB, $USER;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        return $DB->record_exists('ecgt_registered', array('agrpid' => $agrpid,
                    'userid' => $userid,
                    'modified_by' => -1));
    }

    public function qualifies_for_groupchange($agrpid, $userid) {
        global $DB, $USER;

        // Not really used here, but at least empty values needed by can_change_group()!
        $message = new stdClass();
        $message->username = '';
        $message->groupname = '';

        try {
            $this->can_change_group($agrpid, $userid, $message);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

     protected function can_change_group($agrpid, $userid = null, $message = null, $oldagrpid = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            ////throw new \mod_eitcoursegrouptools\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        if ($message === null) {
            $message = new stdClass();
            if ($userid != $USER->id) {
                $userdata = $DB->get_record('user', array('id' => $userid));
                $message->username = fullname($userdata);
            } else {
                $message->username = fullname($USER);
            }
            $message->groupname = $groupdata->name;
        }

        if (empty($this->eitcoursegrouptools->allow_unreg)) {
            //throw new \mod_eitcoursegrouptools\local\exception\registration('unreg_not_allowed');
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        list($userregs, $userqueues,,, $max) = $this->check_users_regs_limits($userid, true);

        if (($oldagrpid === null) && !(($userqueues == 1 && $userregs == $max - 1) || ($userqueues + $userregs == 1 && $max == 1))) {
            // We can't determine a unique group to unreg the user from! He has to do it by manually!
            //throw new \mod_eitcoursegrouptools\local\exception\registration('groupchange_from_non_unique_reg');
        }

        if ($this->eitcoursegrouptools->use_size && !empty($groupdata->registered) && (count($groupdata->registered) > $groupdata->grpsize)) {
            if (!$this->eitcoursegrouptools->use_queue) {
                // We can't register the user nor queue the user!
                throw new \mod_eitcoursegrouptools\local\exception\exceedgroupsize();
            } else if (count($groupdata->queues) >= $this->eitcoursegrouptools->groups_queues_limit) {
                throw new \mod_eitcoursegrouptools\local\exception\exceedgroupqueuelimit();
            }

            if ($this->eitcoursegrouptools->users_queues_limit && ($userqueues >= $this->eitcoursegrouptools->users_queues_limit) && ($userqueues != 1)) {
                // We can't queue him, due to exceeding his queue limit or not being able to determine which queue entry to unreg!
                throw new \mod_eitcoursegrouptools\local\exception\exceeduserqueuelimit();
            }
        }

        // We have no 'you'-version of the string here!
        return get_string('change_group_to', 'eitcoursegrouptools', $message);
    }
    
     protected function check_reg_present($agrpid, $userid, $groupdata, $message) {
        global $USER;

        if ($this->grpmarked($agrpid, $userid)) {
            // Allready marked for registration!?!
            if ($userid != $USER->id) {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('already_marked', $message);
            } else {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('you_are_already_marked', $message);
            }
        }

        if (!empty($groupdata->registered) && $this->get_rank_in_queue($groupdata->registered, $userid) != false) {
            // We're sorry, but user's already registered in this group!
            if ($userid != $USER->id) {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('already_registered', $message);
            } else {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('you_are_already_registered', $message);
            }
        }

        if (!empty($groupdata->queued) && $this->get_rank_in_queue($groupdata->queued, $userid) != false) {
            // We're sorry, but user's already queued in this group!
            if ($userid != $USER->id) {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('already_queued', $message);
            } else {
                throw new \mod_eitcoursegrouptools\local\exception\regpresent('you_are_aleady_queued', $message);
            }
        }
    }
    
    protected function check_users_regs_limits($userid, $change = false) {
        global $DB;

        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select('ecgt_activegroups', 'id', "grouptoolid = ? AND active = 1", array($this->eitcoursegrouptools->id));
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('ecgt_registered', "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql, $params);
        $userqueues = $DB->count_records_select('ecgt_queued', "userid = ? AND agrpid " . $agrpsql, $params);
        $marks = $this->count_user_marks($userid);
        $max = $this->eitcoursegrouptools->allow_multiple ? $this->eitcoursegrouptools->choose_max : 1;
        $min = $this->eitcoursegrouptools->allow_multiple ? $this->eitcoursegrouptools->choose_min : 0;

        if ($change) {
            if ($min > ($marks + $userregs + $userqueues)) {
                //throw new \mod_eitcoursegrouptools\local\exception\registration('too_many_registrations');
                echo 'Too many registrations';
            }
            if ($max < ($marks + $userregs + $userqueues)) {
                //throw new \mod_eitcoursegrouptools\local\exception\exceeduserreglimit();
                echo 'User registration limit exceded';
            }
        } else {
            if ($min <= ($marks + $userregs + $userqueues)) {
                //throw new \mod_eitcoursegrouptools\local\exception\registration('too_many_registrations');
                echo 'Too many registrations';
            }
            if ($max <= ($marks + $userregs + $userqueues)) {
                //throw new \mod_eitcoursegrouptools\local\exception\exceeduserreglimit();
                echo 'User registration limit exceded';
            }
        }

        return array($userregs, $userqueues, $marks, $min, $max);
    }
    
    public function count_user_marks($userid = 0) {
        $marks = $this->get_user_marks($userid);

        return count($marks);
    }
    
    public function get_user_marks($userid = 0) {
        global $DB, $USER, $OUTPUT;

        if (empty($userid)) {
            $userid = $USER->id;
        }

        $agrps = $DB->get_fieldset_select('ecgt_activegroups', 'id', 'grouptoolid = ?', array($this->cm->instance));

        list($agrpssql, $params) = $DB->get_in_or_equal($agrps);
        $params[] = $userid;

        $sql = 'SELECT reg.id, reg.agrpid, reg.userid, reg.timestamp,
                       agrp.groupid
                  FROM {ecgt_registered} reg
                  JOIN {ecgt_activegroups} agrp ON reg.agrpid = agrp.id
                 WHERE reg.agrpid ' . $agrpssql . '
                   AND modified_by = -1
                   AND userid = ?';

        $marks = $DB->get_records_sql($sql, $params);
        foreach ($marks as $id => $cur) {
            $groupdata = $this->get_active_groups(true, true, $cur->agrpid);
            $groupdata = current($groupdata);

            if ($this->eitcoursegrouptools->use_size) {
                $notfull = empty($this->eitcoursegrouptools->groups_queues_limit) || (count($groupdata->queued) < $this->eitcoursegrouptools->groups_queues_limit);
                if (count($groupdata->registered) < $groupdata->grpsize) {
                    $marks[$id]->type = 'reg';
                } else if ($this->eitcoursegrouptools->use_queue && $notfull) {
                    $marks[$id]->type = 'queue';
                } else {
                    // Place occupied in the meanwhile, must look for another group!
                    $info = new stdClass();
                    $info->grpname = groups_get_group_name($cur->groupid);
                    $info->userid = $userid;
                    echo $OUTPUT->notification(get_string('already_occupied', 'eitcoursegrouptools', $info), 'error');
                    $DB->delete_records('ecgt_registered', array('id' => $id));
                    unset($marks[$id]);
                }
            } else {
                $marks[$id]->type = 'reg';
            }
        }

        return $marks;
    }
    
    public function register_in_agrp($agrpid, $userid = 0, $previewonly = false) {
        global $USER, $DB;

        $eitcoursegrouptools = $this->eitcoursegrouptools;

        if (empty($userid)) {
            $userid = $USER->id;
            require_capability('mod/eitcoursegrouptools:register', $this->context);
        }

        $regopen = ($this->eitcoursegrouptools->allow_reg && (($this->eitcoursegrouptools->timedue == 0) || (time() < $this->eitcoursegrouptools->timedue)) && ($this->eitcoursegrouptools->timeavailable < time()));

        if (!$regopen && !has_capability('mod/eitcoursegrouptools:register_students', $this->context)) {
            //throw new \mod_eitcoursegrouptools\local\exception\registration('reg_not_open');
        }

        $message = new stdClass();
        if ($userid != $USER->id) {
            $userdata = $DB->get_record('user', array('id' => $userid));
            $message->username = fullname($userdata);
        }
        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            //throw new \mod_eitcoursegrouptools\local\exception\registration('error_getting_data');
        }
        $groupdata = current($groupdata);

        $message->groupname = $groupdata->name;
        $message->userid = $userid;

        if ($this->qualifies_for_groupchange($agrpid, $userid)) {
            if ($previewonly) {
                $return = $this->can_change_group($agrpid, $userid, $message);
            } else {
                $return = $this->change_group($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $this->convert_marks_to_regs($userid);
            }

            return $return;
        }

        try {
            // First we try to register the user!
            if ($previewonly) {
                $return = $this->can_be_registered($agrpid, $userid, $message);
            } else {
                $return = $this->add_registration($agrpid, $userid, $message);
                // If we can register, we have to convert the other marks to registrations & queue entries!
                $this->convert_marks_to_regs($userid);
            }

            return $return;
        } catch (\mod_eitcoursegrouptools\local\exception\exceedgroupsize $e) {
            if (!$this->eitcoursegrouptools->use_queue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            try {
                if ($previewonly) {
                    $return = $this->can_be_queued($agrpid, $userid, $message);
                } else {
                    $return = $this->add_queue_entry($agrpid, $userid, $message);
                    // If we can queue, we have to convert the other marks to registrations & queue entries!
                    $this->convert_marks_to_regs($userid);
                }

                return $return;
            } catch (\mod_eitcoursegrouptools\local\exception\notenoughregs $e) {
                // Pass it on!
                throw $e;
            }
        } catch (\mod_eitcoursegrouptools\local\exception\notenoughregs $e) {
            /* The user has not enough registrations, queue entries or marks,
             * so we try to mark the user! (Exceptions get handled above!) */
            if ($previewonly) {
                list(, $return) = $this->can_be_marked($agrpid, $userid, $message);
            } else {
                $return = $this->mark_for_reg($agrpid, $userid, $message);
            }

            return $return;
        }
    }

     public function change_group($agrpid, $userid = null, $message = null, $oldagrpid = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if ($message === null) {
            $message = new stdClass();
            if ($userid != $USER->id) {
                $userdata = $DB->get_record('user', array('id' => $userid));
                $message->username = fullname($userdata);
            } else {
                $message->username = fullname($USER);
            }
            $groupdata = $this->get_active_groups(false, false, $agrpid);
            if (count($groupdata) != 1) {
                //throw new \mod_eitcoursegrouptools\local\exception\registration('error_getting_data');
            }
            $groupdata = reset($groupdata);
            $message->groupname = $groupdata->name;
        }

        // Check if the user can be registered or queued with respect to max registrations being incremented by 1.
        $this->can_change_group($agrpid, $userid, $message, $oldagrpid);

        // Determine from which group to change and unregister from it!
        // We have to filter only active groups to ensure no problems counting userregs and -queues.
        $agrpids = $DB->get_fieldset_select('ecgt_activegroups', 'id', "grouptoolid = ? AND active = 1", array($this->eitcoursegrouptools->id));
        list($agrpsql, $params) = $DB->get_in_or_equal($agrpids);
        array_unshift($params, $userid);
        $userregs = $DB->count_records_select('ecgt_registered', "modified_by >= 0 AND userid = ? AND agrpid " . $agrpsql, $params);
        $userqueues = $DB->count_records_select('ecgt_queued', "userid = ? AND agrpid " . $agrpsql, $params);
        if ($oldagrpid !== null) {
            $sql = "SELECT queued.*, agrp.groupid
                      FROM {ecgt_queued} queued
                      JOIN {ecgt_activegroups} agrp ON agrp.id = queued.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if ($queue = $DB->get_record_sql($sql, array('userid' => $userid,
                'agrpid' => $oldagrpid), IGNORE_MISSING)) {

                $DB->delete_records('ecgt_queued', array('id' => $queue->id));
                // Trigger the event!
                //\mod_eitcoursegrouptools\event\queue_entry_deleted::create_direct($this->cm, $queue);
            }
            $sql = "SELECT reg.*, agrp.groupid
                      FROM {ecgt_registered} reg
                      JOIN {ecgt_activegroups} agrp ON agrp.id = reg.agrpid
                     WHERE userid = ? AND agrpid = ?";
            if ($reg = $DB->get_record_sql($sql, array('userid' => $userid,
                'agrpid' => $oldagrpid), IGNORE_MISSING)) {

                $DB->delete_records('ecgt_registered', array('id' => $reg->id));
                if (!empty($this->eitcoursegrouptools->immediate_reg)) {
                    groups_remove_member($reg->groupid, $userid);
                }
                // Trigger the event!
                //\mod_eitcoursegrouptools\event\registration_deleted::create_direct($this->cm, $reg);
            }
        } else if ($userqueues == 1) {
            // Delete his queue!
            $queues = $DB->get_records_sql("SELECT queued.*, agrp.groupid
                                              FROM {ecgt_queued} queued
                                              JOIN {ecgt_activegroups} agrp ON agrp.id = queued.agrpid
                                              WHERE userid = ? AND agrpid " . $agrpsql, $params);
            $DB->delete_records_select('ecgt_queued', "userid = ? AND agrpid " . $agrpsql, $params);
            foreach ($queues as $cur) {
                // Trigger the event!
                //\mod_eitcoursegrouptools\event\queue_entry_deleted::create_direct($this->cm, $cur);
            }
        } else if ($userregs == 1) {
            $oldgrp = $DB->get_field_sql("SELECT agrp.groupid
                                            FROM {ecgt_registered} reg
                                            JOIN {ecgt_activegroups} agrp ON agrp.id = reg.agrpid
                                           WHERE reg.userid = ? AND reg.agrpid " . $agrpsql, $params, MUST_EXIST);
            $reg = $DB->get_record_select('ecgt_registered', "userid = ? AND agrpid " . $agrpsql, $params, '*', MUST_EXIST);
            $DB->delete_records_select('ecgt_registered', "userid = ? AND agrpid " . $agrpsql, $params);
            if (!empty($oldgrp) && !empty($this->eitcoursegrouptools->immediate_reg)) {
                groups_remove_member($oldgrp, $userid);
            }

            // Trigger the event!
            $reg->groupid = $oldgrp;
            //\mod_eitcoursegrouptools\event\registration_deleted::create_direct($this->cm, $reg);
        } else {
            //throw new \mod_eitcoursegrouptools\exception\registration(get_string('groupchange_from_non_unique_reg', 'eitcoursegrouptools'));
        }

        // Register him in the new group!
        try {
            // First we try to register the user!
            $return = $this->add_registration($agrpid, $userid, $message);
            // If we can register, we have to convert the other marks to registrations & queue entries!
            $this->convert_marks_to_regs($userid);

            return $return;
        } catch (\mod_eitcoursegrouptools\local\exception\exceedgroupsize $e) {
            if (!$this->eitcoursegrouptools->use_queue) {
                // Shortcut: throw the exception again, if we don't use queues!
                throw $e;
            }

            // There's no place left in the group, so we try to queue the user!
            $return = $this->add_queue_entry($agrpid, $userid, $message);
            // If we can queue, we have to convert the other marks to registrations & queue entries!
            $this->convert_marks_to_regs($userid);

            return $return;
        }
    }
    
    
    public function add_registration($agrpid, $userid, $message) {
        global $DB, $USER;

        $groupdata = $this->get_active_groups(false, false, $agrpid);
        if (count($groupdata) != 1) {
            //throw new \mod_eitcoursegrouptools\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        /* This method throws exceptions if there is a problem */
        $this->can_be_registered($agrpid, $userid, $message);

        $record = new stdClass();
        $record->agrpid = $agrpid;
        $record->userid = $userid;
        $record->timestamp = time();
        $record->modified_by = $USER->id;
        $record->id = $DB->insert_record('ecgt_registered', $record);
        if ($this->eitcoursegrouptools->immediate_reg) {
            groups_add_member($groupdata->id, $userid);
        }
        // Trigger the event!
        $record->groupid = $groupdata->id;
        //\mod_eitcoursegrouptools\event\registration_created::create_direct($this->cm, $record)->trigger();
        if ($userid != $USER->id) {
            return get_string('register_in_group_success', 'eitcoursegrouptools', $message);
        } else {
            return get_string('register_you_in_group_success', 'eitcoursegrouptools', $message);
        }
    }
    
    protected function can_be_registered($agrpid, $userid = null, $message = null) {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        $groupdata = $this->get_active_groups(true, true, $agrpid);
        if (count($groupdata) != 1) {
            //throw new \mod_eitcoursegrouptools\local\exception\registration('error_getting_data');
        }
        $groupdata = reset($groupdata);

        if ($message === null) {
            $message = new stdClass();
            if ($userid != $USER->id) {
                $userdata = $DB->get_record('user', array('id' => $userid));
                $message->username = fullname($userdata);
            } else {
                $message->username = fullname($USER);
            }
            $message->groupname = $groupdata->name;
        }

        $this->check_reg_present($agrpid, $userid, $groupdata, $message);

        // Check if enough (queue) places are available, otherwise display an info and remove marked entry.
        $userregs = $this->get_user_reg_count($this->eitcoursegrouptools->id, $userid);
        $queues = $this->get_user_queues_count($this->eitcoursegrouptools->id, $userid);
        $marks = $this->count_user_marks($userid);
        $max = $this->eitcoursegrouptools->allow_multiple ? $this->eitcoursegrouptools->choose_max : 1;
        $min = $this->eitcoursegrouptools->allow_multiple ? $this->eitcoursegrouptools->choose_min : 0;
        if ($max <= ($marks + $userregs + $queues)) {
            //throw new \mod_eitcoursegrouptools\local\exception\exceeduserreglimit();
            echo 'User reg limit exceeded.';
        }
        if ($min > ($marks + $userregs + $queues + 1)) {
            // Not enough registrations/queues/marks!
            //throw new \mod_eitcoursegrouptools\local\exception\notenoughregs();
             echo 'Not enough registrations';
        }

        if ($this->eitcoursegrouptools->use_size && (count($groupdata->registered) >= $groupdata->grpsize)) {
            //throw new \mod_eitcoursegrouptools\local\exception\exceedgroupsize();
            echo 'Group size exceeded';
        }

        if ($userid != $USER->id) {
            return get_string('register_in_group', 'eitcoursegrouptools', $message);
        } else {
            return get_string('register_you_in_group', 'eitcoursegrouptools', $message);
        }
    }
    
    public function get_user_reg_count($grouptoolid = 0, $userid = 0) {
        global $DB, $USER;

        if (empty($grouptoolid)) {
            $grouptoolid = $this->eitcoursegrouptools->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {ecgt_registered}
                                       WHERE modified_by >= 0 AND userid = ? AND agrpid ' . $sql, $params);
    }
     public function get_user_queues_count($grouptoolid = 0, $userid = 0) {
        global $DB, $USER;
        if (empty($grouptoolid)) {
            $grouptoolid = $this->eitcoursegrouptools->id;
        }
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $agrps = $this->get_active_groups();
        $keys = array();
        foreach ($agrps as $current) {
            $keys[] = $current->agrpid;
        }
        if (count($keys) == 0) {
            return 0;
        }
        list($sql, $params) = $DB->get_in_or_equal($keys);
        $params = array_merge(array($userid), $params);
        return $DB->count_records_sql('SELECT count(id)
                                       FROM {ecgt_queued}
                                       WHERE userid = ? AND agrpid ' . $sql, $params);
    }
    
    public function convert_marks_to_regs($userid) {
        global $DB, $USER;

        // Get user's marks!
        $usermarks = $this->get_user_marks($userid);

        $queues = 0;
        foreach ($usermarks as $cur) {
            if ($cur->type != 'reg') {
                $queues++;
            }
        }
        if (!empty($this->eitcoursegrouptools->users_queues_limit) && ($queues > $this->eitcoursegrouptools->users_queues_limit)) {
            throw new \mod_eitcoursegrouptools\local\exception\exceeduserqueuelimit();
        }

        foreach ($usermarks as $cur) {
            if ($cur->type == 'reg') {
                unset($cur->type);
                $cur->modified_by = $USER->id;
                $DB->update_record('ecgt_registered', $cur);
                if ($this->eitcoursegrouptools->immediate_reg) {
                    groups_add_member($cur->groupid, $userid);
                }
            } else {
                unset($cur->type);
                $DB->insert_record('ecgt_queued', $cur);
                $DB->delete_records('ecgt_registered', array('id' => $cur->id));
            }
        }
        $this->delete_user_marks($userid);
    }
    
    public function delete_user_marks($userid = 0) {
        global $DB;

        $marks = $this->get_user_marks($userid);
        if (is_array($marks) && count($marks) > 0) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($marks));
            $select = 'id ' . $select;
            $DB->delete_records_select('ecgt_registered', $select, $params);
        }
    }
}
