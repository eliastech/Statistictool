<?php

defined('MOODLE_INTERNAL') || die();

/**
 * ECGT_N_M_GROUPS - group creation mode where N groups with a groupsize of M members are created
 */
define('ECGT_N_M_GROUPS', 4);

/**
 * ECGT_FROMTO_GROUPS - group creation mode where just groups with a starting and
 * ending number are created - no user allocation
 */
define('ECGT_FROMTO_GROUPS', 3);

/**
 * ECGT_GROUPS_AMOUNT - group creation mode where amount of groups is defined
 */
define('ECGT_GROUPS_AMOUNT', 1);

/**
 * ECGT_MEMBERS_AMOUNT - group creation mode where amount of groupmembers is defined
 */
define('ECGT_MEMBERS_AMOUNT', 2);

/**
 * ECGT_1_PERSON_GROUPS - group creation mode where a single group is created for each user
 */
define('ECGT_1_PERSON_GROUPS', 0);

/**
 * ECGT_AUTOGROUP_MIN_RATIO - means minimum member count is 70% in the smallest group
 */
define('ECGT_AUTOGROUP_MIN_RATIO', 0.7);

/**
 * ECGT_BEP - use new implementation of parsing groupnames with @ if current groups
 * number is larger than ECGT_BEP
 * new implementation is faster for large numbers
 * old style = linear - new style = estimated 15 instructions per stage --> 15 * log(x,25)
 * break even point estimated < 12 --> @30 we are on the secure side...
 */
define('ECGT_BEP', 30);

/**
 * IE_7_IS_DEAD - disable workarounds for IE7-problems?
 * still quite alive, so we need some hacks :(
 */
define('ECGT_IE7_IS_DEAD', 0);

/**
 * ECGT_FILTER_ALL - no filter at all...
 */
define('ECGT_FILTER_ALL', 0);

/**
 * ECGT_FILTER_NONCONFLICTING - Show just those groups, which have just 1 graded member
 * for this activity
 */
define('ECGT_FILTER_NONCONFLICTING', -1);

/**
 * ECGT_PDF - get PDF-File
 */
define('ECGT_PDF', 0);

/**
 * ECGT_TXT - get TXT-File
 */
define('ECGT_TXT', 1);

/**
 * ECGT_ODS - get ODS-File
 */
define('ECGT_ODS', 3);

/**
 * ECGT_XLSX - get XLSX-File
 */
define('ECGT_XLSX', 2);

/**
 * ECGT_RAW - get raw data - just for development
 */
define('ECGT_RAW', -1);

/**
 * ECGT_NL - Windows style newlines
 * otherwise we get problems with windows users and txt-files (UNIX \n, MAC \r)
 */
define('ECGT_NL', "\r\n");

/**
 * ECGT_OUTDATED - active group's registrations are not consistent with moodle-group's
 */
define('ECGT_OUTDATED', 0);

/**
 * ECGT_UPTODATE - active group's registrations are consistent with moodle-group's registrations
 */
define('ECGT_UPTODATE', 1);

/**
 * ECGT_FOLLOW - follow changes via eventhandler
 */
define('ECGT_FOLLOW', 1);

/**
 * ECGT_IGNORE - ignore changes
 */
define('ECGT_IGNORE', 1);

/**
 * ECGT_RECREATE_GROUP - recreate group just for use in grouptool
 */
define('ECGT_RECREATE_GROUP', 1);

/**
 * ECGT_DELETE_REF - delete all references in grouptool-instance
 */
define('ECGT_DELETE_REF', 1);

/**
 * HIDE_GROUPMEMBERS - never show groupmembers no matter what...
 */
define('ECGT_HIDE_GROUPMEMBERS', 0);
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show groupmembers after due date
 */
define('ECGT_SHOW_GROUPMEMBERS_AFTER_DUE', 2);
/**
 * SHOW_GROUPMEMBERS_AFTER_DUE - show members of own group(s) after due date
 */
define('ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_DUE', 3);
/**
 * SHOW_GROUPMEMBERS_AFTER_REG - show members of own group(s) immediately after registration
 */
define('ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_REG', 4);
/**
 * SHOW_GROUPMEMBERS - show groupmembers no matter what...
 */
define('ECGT_SHOW_GROUPMEMBERS', 1);

/**
 * ECGT_EVENT_TYPE_DUE - event type for due date events
 */
define('ECGT_EVENT_TYPE_DUE', 'deadline');
/**
 * ECGT_EVENT_TYPE_AVAILABLEFROM - event type for availalbe from events (not used anymore!)
 */
define('ECGT_EVENT_TYPE_AVAILABLEFROM', 'availablefrom');
