<?php

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/mod/eitcoursegrouptools/definitions.php');


class mod_eitcoursegrouptools_observer {
    /**
     * group_member_added
     *
     * @param \core\event\group_member_added $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_member_added(\core\event\group_member_added $event) {
        global $DB;

        $sql = "SELECT DISTINCT grpt.id, grpt.ifmemberadded, grpt.course,
                                agrp.id agrpid
                FROM {eitcoursegrouptools} grpt
                JOIN {ecgt_activegroups} agrp ON agrp.grouptoolid = grpt.id
                WHERE (agrp.groupid = ?) AND (agrp.active = ?) AND (grpt.ifmemberadded = ?)";
        $params = array($event->objectid, 1, ECGT_FOLLOW);
        if (! $eitcoursegrouptools = $DB->get_records_sql($sql, $params)) {
            return true;
        }

        $agrpssql = "SELECT agrps.grouptoolid AS grouptoolid, agrps.id AS id FROM {ecgt_activegroups} agrps
        WHERE agrps.groupid = :groupid";
        $agrp = $DB->get_records_sql($agrpssql, array('groupid' => $event->objectid));

        $regsql = "SELECT reg.agrpid AS id
                     FROM {ecgt_activegroups} agrps
               INNER JOIN {ecgt_registered} reg ON agrps.id = reg.agrpid
                    WHERE reg.modified_by >= 0 AND agrps.groupid = :groupid AND reg.userid = :userid";
        $regs = $DB->get_records_sql($regsql, array('groupid' => $event->objectid,
                                                    'userid'  => $event->relateduserid));
        $markssql = "SELECT reg.agrpid, reg.id, reg.userid, reg.timestamp
                       FROM {ecgt_activegroups} agrps
                 INNER JOIN {ecgt_registered} reg ON agrps.id = reg.agrpid
                      WHERE reg.modified_by = -1 AND agrps.groupid = :groupid AND reg.userid = :userid";
        $marks = $DB->get_records_sql($markssql, array('groupid' => $event->objectid,
                                                       'userid'  => $event->relateduserid));

        $queuesql = "SELECT queue.agrpid AS agrpid, queue.id AS id
                       FROM {ecgt_activegroups} agrps
                  LEFT JOIN {ecgt_queued} queue ON agrps.id = queue.agrpid
                      WHERE agrps.groupid = :groupid AND queue.userid = :userid";
        $queues = $DB->get_records_sql($queuesql, array('groupid' => $event->objectid,
                                                        'userid'  => $event->relateduserid));
        foreach ($eitcoursegrouptools as $eitcoursegrouptools) {
            if (!key_exists($eitcoursegrouptools->agrpid, $regs)) {
                $reg = new stdClass();
                $reg->agrpid = $agrp[$eitcoursegrouptools->id]->id;
                $reg->userid = $event->relateduserid;
                $reg->timestamp = time();
                $reg->modified_by = 0; // There's no way we can get the teachers id!
                if (!$DB->record_exists('ecgt_registered', array('agrpid' => $reg->agrpid,
                                                                      'userid' => $reg->userid))) {
                    $reg->id = $DB->insert_record('ecgt_registered', $reg);
                    $reg->groupid = $event->objectid;
                    $cm = get_coursemodule_from_instance('eitcoursegrouptools', $eitcoursegrouptools->id, $eitcoursegrouptools->course, false, MUST_EXIST);
                    //\mod_eitcoursegrouptools\event\registration_created::create_via_eventhandler($cm, $reg)->trigger();
                }
                if (key_exists($eitcoursegrouptools->agrpid, $queues)) {
                    $DB->delete_records('ecgt_queued', array('id' => $queues[$eitcoursegrouptools->agrpid]->id));
                }
            } else if (key_exists($eitcoursegrouptools->agrpid, $marks)) {
                $record = $marks[$eitcoursegrouptools->agrpid];
                $record->modified_by = 0;
                $DB->update_record('ecgt_registered', $record);
                if (key_exists($eitcoursegrouptools->agrpid, $queues)) {
                    $DB->delete_records('ecgt_queued', array('id' => $queues[$eitcoursegrouptools->agrpid]->id));
                }
                $reg->groupid = $event->objectid;
                $cm = get_coursemodule_from_instance('eitcoursegrouptools', $eitcoursegrouptools->id, $eitcoursegrouptools->course, false, MUST_EXIST);
                //\mod_eitcoursegrouptools\event\registration_created::create_via_eventhandler($cm, $record)->trigger();
            }
        }
        return true;
    }

    /**
     * group_remove_member_handler
     * event:       groups_member_removed
     * schedule:    instant
     *
     * @param \core\event\group_member_removed $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_member_removed(\core\event\group_member_removed $event) {
        global $DB, $CFG;

        $sql = "SELECT DISTINCT {eitcoursegrouptools}.id, {eitcoursegrouptools}.ifmemberremoved, {eitcoursegrouptools}.course,
                                {eitcoursegrouptools}.use_queue, {eitcoursegrouptools}.immediate_reg, {eitcoursegrouptools}.allow_multiple,
                                {eitcoursegrouptools}.choose_max, {eitcoursegrouptools}.choose_min, {eitcoursegrouptools}.grpsize,
                                {eitcoursegrouptools}.name, {eitcoursegrouptools}.use_size, {eitcoursegrouptools}.use_individual
                           FROM {eitcoursegrouptools}
                     RIGHT JOIN {ecgt_activegroups} agrp ON agrp.grouptoolid = {eitcoursegrouptools}.id
                          WHERE agrp.groupid = ?";
        $params = array($event->objectid);
        if (! $eitcoursegrouptools = $DB->get_records_sql($sql, $params)) {
            return true;
        }
        $sql = "SELECT agrps.grouptoolid grouptoolid, agrps.id id
                  FROM {ecgt_activegroups} agrps
                 WHERE agrps.groupid = :groupid";
        $agrp = $DB->get_records_sql($sql, array('groupid' => $event->objectid));
        foreach ($eitcoursegrouptools as $eitcoursegrouptools) {
            switch ($eitcoursegrouptools->ifmemberremoved) {
                case ECGT_FOLLOW:
                    $sql = "SELECT reg.id AS id, reg.agrpid AS agrpid, reg.userid AS userid, agrps.groupid
                              FROM {ecgt_activegroups} agrps
                        INNER JOIN {ecgt_registered} reg ON agrps.id = reg.agrpid
                             WHERE reg.userid = :userid
                                   AND agrps.grouptoolid = :grouptoolid
                                   AND agrps.groupid = :groupid";
                    if ($regs = $DB->get_records_sql($sql, array('grouptoolid' => $eitcoursegrouptools->id,
                                                                 'userid'      => $event->relateduserid,
                                                                 'groupid'     => $event->objectid))) {
                        $DB->delete_records_list('ecgt_registered', 'id', array_keys($regs));
                        foreach ($regs as $reg) {
                            // Trigger event!
                            $cm = get_coursemodule_from_instance('eitcoursegrouptools', $eitcoursegrouptools->id, $eitcoursegrouptools->course, false,
                                                                 MUST_EXIST);
                            //\mod_eitcoursegrouptools\event\registration_deleted::create_via_eventhandler($cm, $reg)->trigger();
                        }

                        // Get next queued user and put him in the group (and delete queue entry)!
                        if (!empty($eitcoursegrouptools->use_queue)) {
                            // We include it right here, because we want to have it slim!
                            require_once($CFG->dirroot.'/mod/eitcoursegrouptools/locallib.php');
                            $cm = get_coursemodule_from_instance('eitcoursegrouptools', $eitcoursegrouptools->id);
                            $instance = new \mod_eitcoursegrouptools($cm->id, $eitcoursegrouptools, $cm);

                            $instance->fill_from_queue($agrp[$eitcoursegrouptools->id]->id);
                        }
                    }
                    break;
                default:
                case ECGT_IGNORE:
                    break;
            }
        }
        return true;
    }

    /**
     * group_deleted
     *
     * @param \core\event\group_deleted $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        global $CFG, $DB;

        $data = $event->get_record_snapshot('groups', $event->objectid);
        $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);

        if (! $grouptools = get_all_instances_in_course('eitcoursegrouptools', $course)) {
            return true;
        }

        $grouprecreated = false;
        $agrpids = array();
        foreach ($grouptools as $eitcoursegrouptools) {
            $cmid = $eitcoursegrouptools->coursemodule;
            switch ($eitcoursegrouptools->ifgroupdeleted) {
                default:
                case ECGT_RECREATE_GROUP:
                    if (!$grouprecreated) {
                        $newid = $DB->insert_record('groups', $data, true);
                        if ($newid !== false) {
                            // Delete auto-inserted agrp.
                            if ($DB->record_exists('ecgt_activegroups', array('groupid' => $newid))) {
                                $DB->delete_records('ecgt_activegroups', array('groupid' => $newid));
                            }
                            // Update reference.
                            if ($DB->record_exists('ecgt_activegroups', array('groupid' => $data->id))) {
                                $DB->set_field('ecgt_activegroups', 'groupid', $newid,
                                               array('groupid' => $data->id));
                            }
                            // Trigger event!
                            $logdata = array('cmid'     => $cmid,
                                             'groupid'  => $data->id,
                                             'newid'    => $newid,
                                             'courseid' => $data->courseid);
                            \mod_eitcoursegrouptools\event\group_recreated::create_from_object($logdata)->trigger();

                            if ($eitcoursegrouptools->immediate_reg) {
                                require_once($CFG->dirroot.'/mod/eitcoursegrouptools/locallib.php');
                                $instance = new mod_eitcoursegrouptools($cmid, $eitcoursegrouptools);
                                $instance->push_registrations();
                            }
                            $grouprecreated = true;
                        } else {
                            print_error('error', 'moodle');
                            return false;
                        }
                    } else {
                        if ($eitcoursegrouptools->immediate_reg) {
                            require_once($CFG->dirroot.'/mod/eitcoursegrouptools/locallib.php');
                            $instance = new mod_eitcoursegrouptools($cmid, $eitcoursegrouptools);
                            $instance->push_registrations();
                        }
                    }
                    break;
                case ECGT_DELETE_REF:
                    if ($agrpid = $DB->get_field('ecgt_activegroups', 'id', array('groupid'     => $data->id,
                                                                                'grouptoolid' => $eitcoursegrouptools->id))) {
                        $agrpids[] = $agrpid;
                    }
                    break;
            }
        }
        if (count($agrpids) > 0) {
            $agrps = $DB->get_records_list('ecgt_activegroups', 'id', $agrpids);
            $cms = array();
            $regs = $DB->get_records_list('ecgt_registered', 'agrpid', $agrpids);
            $DB->delete_records_list('ecgt_registered', 'agrpid', $agrpids);
            foreach ($regs as $cur) {
                if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                    $cms[$agrps[$cur->agrpid]->grouptoolid] = get_coursemodule_from_instance('eitcoursegrouptools',
                                                                                             $agrps[$cur->agrpid]->grouptoolid);
                }
                $cur->groupid = $agrps[$cur->agrpid]->groupid;
                //\mod_eitcoursegrouptools\event\registration_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
            }
            $queues = $DB->get_records_list('ecgt_queued', 'agrpid', $agrpids);
            $DB->delete_records_list('ecgt_queued', 'agrpid', $agrpids);
            foreach ($queues as $cur) {
                if (empty($cms[$agrps[$cur->agrpid]->grouptoolid])) {
                    $cms[$agrps[$cur->agrpid]->grouptoolid] = get_coursemodule_from_instance('eitcoursegrouptools',
                                                                                             $agrps[$cur->agrpid]->grouptoolid);
                }
                // Trigger event!
                $cur->groupid = $agrps[$cur->agrpid]->groupid;
                \mod_eitcoursegrouptools\event\queue_entry_deleted::create_via_eventhandler($cms[$agrps[$cur->agrpid]->grouptoolid], $cur);
            }
            $DB->delete_records_list('ecgt_activegroups', 'id', $agrpids);
            foreach ($agrps as $cur) {
                if (empty($cms[$cur->grouptoolid])) {
                    $cms[$cur->grouptoolid] = get_coursemodule_from_instance('eitcoursegrouptools', $cur->grouptoolid);
                }
                // Trigger event!
                $logdata = new stdClass();
                $logdata->id = $cur->id;
                $logdata->cmid = $cms[$cur->grouptoolid]->id;
                $logdata->groupid = $cur->groupid;
                $logdata->agrpid = $cur->id;
                $logdata->courseid = $data->courseid;
                \mod_eitcoursegrouptools\event\agrp_deleted::create_from_object($logdata);
            }
        }

        return true;
    }

    /**
     * group_created
     *
     * @param  \core\event\group_created $event Event object containing useful data
     * @return bool true if success
     */
    public static function group_created(\core\event\group_created $event) {
        global $DB;

        $data = $event->get_record_snapshot('groups', $event->objectid);
        $course = $DB->get_record('course', array('id' => $data->courseid));

        if (! $grouptools = get_all_instances_in_course('eitcoursegrouptools', $course)) {
            return true;
        }
        $sortorder = $DB->get_records_sql("SELECT agrp.grouptoolid, MAX(agrp.sort_order) AS max
                                             FROM {ecgt_activegroups} agrp
                                         GROUP BY agrp.grouptoolid");
        foreach ($grouptools as $eitcoursegrouptools) {
            $newagrp = new StdClass();
            $newagrp->grouptoolid = $eitcoursegrouptools->id;
            $newagrp->groupid = $data->id;
            if (!array_key_exists($eitcoursegrouptools->id, $sortorder)) {
                $newagrp->sort_order = 1;
            } else {
                $newagrp->sort_order = $sortorder[$eitcoursegrouptools->id]->max + 1;
            }
            $newagrp->active = 0;
            if (!$DB->record_exists('ecgt_activegroups', array('grouptoolid' => $eitcoursegrouptools->id,
                                                             'groupid'     => $data->id))) {
                $newagrp->id = $DB->insert_record('ecgt_activegroups', $newagrp);
                // Trigger event!
                $cm = get_coursemodule_from_instance('eitcoursegrouptools', $eitcoursegrouptools->id);
                //\mod_eitcoursegrouptools\event\agrp_created::create_from_object($cm, $newagrp)->trigger();
            }
        }
        return true;
    }
}