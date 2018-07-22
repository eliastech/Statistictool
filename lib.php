<?php


// Convention - add instance

function eitcoursegrouptools_add_instance(stdClass $eitcoursegrouptools) {
    global $DB;

    $eitcoursegrouptools->timecreated = time();

    if (!isset($eitcoursegrouptools->use_size)) {
        $eitcoursegrouptools->use_size = 0;
    }
    if (!isset($eitcoursegrouptools->use_individual)) {
        $eitcoursegrouptools->use_individual = 0;
    }
    if (!isset($eitcoursegrouptools->use_queue)) {
        $eitcoursegrouptools->use_queue = 0;
    }
    if (!isset($eitcoursegrouptools->users_queues_limit)) {
        $eitcoursegrouptools->users_queues_limit = 0;
    }
    if (!isset($eitcoursegrouptools->groups_queues_limit)) {
        $eitcoursegrouptools->groups_queues_limit = 0;
    }
    if (!isset($eitcoursegrouptools->allow_multiple)) {
        $eitcoursegrouptools->allow_multiple = 0;
        $eitcoursegrouptools->choose_min = 0;
        $eitcoursegrouptools->choose_max = 1;
    } else {
        $eitcoursegrouptools->choose_min = clean_param($eitcoursegrouptools->choose_min, PARAM_INT);
        $eitcoursegrouptools->choose_max = clean_param($eitcoursegrouptools->choose_max, PARAM_INT);
    }

    //$eitcoursegrouptools->grpsize = clean_param($eitcoursegrouptools->grpsize, PARAM_INT);

    $return = $DB->insert_record('eitcoursegrouptools', $eitcoursegrouptools);

    //eitcoursegrouptools_refresh_events($eitcoursegrouptools->course, $return);

    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($eitcoursegrouptools->course));
    foreach ($coursegroups as $groupid) {
        if (!$DB->record_exists('ecgt_activegroups', array('grouptoolid' => $return,
                                                         'groupid'     => $groupid))) {
            $record = new stdClass();
            $record->grouptoolid = $return;
            $record->groupid = $groupid;
            $record->sort_order = 9999999;
            $record->grpsize = 5;
            $record->active = 0;
            $DB->insert_record('ecgt_activegroups', $record);
        }
    }

    return $return;
}

// Refresh



// Convention - update instance

function eitcoursegrouptools_update_instance(stdClass $eitcoursegrouptools) {
    global $DB, $CFG;

    $eitcoursegrouptools->timemodified = time();
    $eitcoursegrouptools->id = $eitcoursegrouptools->instance;

    if (!isset($eitcoursegrouptools->use_size)) {
        $eitcoursegrouptools->use_size = 0;
    }
    if (!isset($eitcoursegrouptools->use_individual)) {
        $eitcoursegrouptools->use_individual = 0;
    }
    if (!isset($eitcoursegrouptools->use_queue)) {
        $queues = $DB->count_records_sql("SELECT COUNT(DISTINCT queues.id) AS count
                                            FROM {ecgt_activegroups} agrps
                                       LEFT JOIN {ecgt_queued} queues ON queues.agrpid = agrps.id
                                           WHERE agrps.grouptoolid = ? AND agrps.active = 1", array($eitcoursegrouptools->instance));
        if (!empty($queues)) {
            $eitcoursegrouptools->use_queue = 1;
        } else {
            $eitcoursegrouptools->use_queue = 0;
            $eitcoursegrouptools->users_queues_limit = 0;
            $eitcoursegrouptools->groups_queues_limit = 0;
        }
    }
    if (!isset($eitcoursegrouptools->allow_multiple)) {
        $eitcoursegrouptools->allow_multiple = 0;
    }

    $eitcoursegrouptools->grpsize = clean_param($eitcoursegrouptools->grpsize, PARAM_INT);
    $eitcoursegrouptools->choose_min = clean_param($eitcoursegrouptools->choose_min, PARAM_INT);
    $eitcoursegrouptools->choose_max = clean_param($eitcoursegrouptools->choose_max, PARAM_INT);

    // Register students if immediate registration has been turned on!
    if ($eitcoursegrouptools->immediate_reg) {
        require_once($CFG->dirroot.'/mod/eitcoursegrouptools/locallib.php');
        $instance = new mod_grouptool($eitcoursegrouptools->coursemodule, $eitcoursegrouptools);
        $instance->push_registrations();
    }

    eitcoursegrouptools_refresh_events($eitcoursegrouptools->course, $eitcoursegrouptools->instance);

    $coursegroups = $DB->get_fieldset_select('groups', 'id', 'courseid = ?', array($eitcoursegrouptools->course));
    foreach ($coursegroups as $groupid) {
        if (!$DB->record_exists('ecgt_activegroups', array('grouptoolid' => $eitcoursegrouptools->instance,
                                                         'groupid'     => $groupid))) {
            $record = new stdClass();
            $record->grouptoolid = $eitcoursegrouptools->instance;
            $record->groupid = $groupid;
            $record->sort_order = 9999999;
            $record->grpsize = $eitcoursegrouptools->grpsize;
            $record->active = 0;
            $DB->insert_record('ecgt_activegroups', $record);
        }
    }

    // We have to override the functions fetching of data, because it's not updated yet!
    eitcoursegrouptools_update_queues($eitcoursegrouptools);

    return $DB->update_record('grouptool', $eitcoursegrouptools);
}

function eitcoursegrouptools_delete_instance($id) {
    global $DB;

    if (! $eitcoursegrouptools = $DB->get_record('eitcoursegrouptools', array('id' => $id))) {
        return false;
    }

    // Get all agrp-ids for this grouptool-instance!
    if ($DB->record_exists('ecgt_activegroups', array('grouptoolid' => $id))) {
        $ids = $DB->get_fieldset_select('ecgt_activegroups', 'id', "grouptoolid = ?", array($id));

        /*
         * delete all entries in grouptool_agrps, grouptool_queued, grouptool_registered
         * with correct grouptoolid or agrps_id
         */
        if (is_array($ids)) {
            list($sql, $params) = $DB->get_in_or_equal($ids);
            $DB->delete_records_select('ecgt_queued', "agrpid ".$sql, $params);
            $DB->delete_records_select('ecgt_registered', "agrpid ".$sql, $params);
            $DB->delete_records_select('ecgt_activegroups', "id ".$sql, $params);
        }
    }

    $DB->delete_records('event', array('modulename' => 'eitcoursegrouptools', 'instance' => $eitcoursegrouptools->id));

    $DB->delete_records('eitcoursegrouptools', array('id' => $id));

    return true;
}

