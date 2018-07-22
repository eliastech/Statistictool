<?php

defined('MOODLE_INTERNAL') || die();

$observers = array (
        array (
            'eventname'    => '\core\event\group_member_added',
            'callback'     => 'mod_eitcoursegrouptools_observer::group_member_added',
            'includefile'  => '/mod/eitcoursegrouptools/classes/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get groupid, userid with this handler.

        array (
            'eventname'    => 'core\event\group_member_removed',
            'callback'     => 'mod_eitcoursegrouptools_observer::group_member_removed',
            'includefile'  => '/mod/eitcoursegrouptools/classes/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),

        array (
            'eventname'    => 'core\event\group_deleted',
            'callback'     => 'mod_eitcoursegrouptools_observer::group_deleted',
            'includefile'  => '/mod/eitcoursegrouptools/classes/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.


        array (
            'eventname'    => 'core\event\group_created',
            'callback'     => 'mod_eitcoursegrouptools_observer::group_created',
            'includefile'  => '/mod/eitcoursegrouptools/classes/observer.php',
            'priority'     => 0,
            'internal'     => true,
        ),
        // We get id, courseid, name, description, timecreated, timemodified, picture with this handler.

);