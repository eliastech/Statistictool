<?php

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/eitcoursegrouptools/lib.php');
    require_once($CFG->dirroot.'/mod/eitcoursegrouptools/definitions.php');

    // Administration header!
//    $settings->add(new admin_setting_heading('mod_eitcoursegrouptools/view_administration',
//                                             get_string('cfg_admin_head', 'eitcoursegrouptools'),
//                                             get_string('cfg_admin_head_info', 'eitcoursegrouptools')));

    // Standard name scheme?
//    $settings->add(new admin_setting_configtext('mod_eitcoursegrouptools/name_scheme',
//                                                get_string('cfg_name_scheme', 'eitcoursegrouptools'),
//                                                get_string('cfg_name_scheme_desc', 'eitcoursegrouptools'),
//                                                get_string('group').' #'));

    // Instance settings header!
    $settings->add(new admin_setting_heading('mod_eitcoursegrouptools/instance',
                                             get_string('cfg_instance_head', 'eitcoursegrouptools'),
                                             get_string('cfg_instance_head_info', 'eitcoursegrouptools')));

    // Enable selfregistration?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/allow_reg',
//                                                    get_string('cfg_allow_reg', 'eitcoursegrouptools'),
//                                                    get_string('cfg_allow_reg_desc', 'eitcoursegrouptools'),
//                                                    1, $yes = '1', $no = '0'));

//    // Show groupmembers?
//    $options = array(ECGT_HIDE_GROUPMEMBERS               => get_string('yes'),
//                     ECGT_SHOW_GROUPMEMBERS_AFTER_DUE     => get_string('showafterdue', 'eitcoursegrouptools'),
//                     ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_DUE => get_string('showownafterdue', 'eitcoursegrouptools'),
//                     ECGT_SHOW_OWN_GROUPMEMBERS_AFTER_REG => get_string('showownafterreg', 'eitcoursegrouptools'),
//                     ECGT_SHOW_GROUPMEMBERS               => get_string('no'));
//    $settings->add(new admin_setting_configselect('mod_eitcoursegrouptools/show_members',
//                                                  get_string('cfg_show_members', 'eitcoursegrouptools'),
//                                                  get_string('cfg_show_members_desc', 'eitcoursegrouptools'),
//                                                  ECGT_HIDE_GROUPMEMBERS,
//                                                  $options));

    // Activate immediate registrations?
    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/immediate_reg',
                                                    get_string('cfg_immediate_reg', 'eitcoursegrouptools'),
                                                    get_string('cfg_immediate_reg_desc', 'eitcoursegrouptools'),
                                                    0, $yes = '1', $no = '0'));

    // Allow unregistration?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/allow_unreg',
//                                                    get_string('cfg_allow_unreg', 'eitcoursegrouptools'),
//                                                    get_string('cfg_allow_unreg_desc', 'eitcoursegrouptools'),
//                                                    0, $yes = '1', $no = '0'));

    // Standard groupsize?
//    $groupsize = new admin_setting_configtext('mod_eitcoursegrouptools/grpsize',
//                                              get_string('cfg_grpsize', 'eitcoursegrouptools'),
//                                              get_string('cfg_grpsize_desc', 'eitcoursegrouptools'),
//                                              '5', PARAM_INT);
//    $settings->add($groupsize);

    // Use groupsize?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/use_size',
//                                                    get_string('cfg_use_size', 'eitcoursegrouptools'),
//                                                    get_string('cfg_use_size_desc', 'eitcoursegrouptools'),
//                                                    0, $yes = '1', $no = '0'));

    // Use individual size per group?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/use_individual',
//                                                    get_string('cfg_use_individual', 'eitcoursegrouptools'),
//                                                    get_string('cfg_use_individual_desc', 'eitcoursegrouptools'),
//                                                    0, $yes = '1', $no = '0'));

//    // Use queues?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/use_queue',
//                                                    get_string('cfg_use_queue', 'eitcoursegrouptools'),
//                                                    get_string('cfg_use_queue_desc', 'eitcoursegrouptools'),
//                                                    1, $yes = '1', $no = '0'));
//
//    // Max simultaneous queue-places?
//    $maxqueues = new admin_setting_configtext('mod_eitcoursegrouptools/max_queues',
//                                              get_string('cfg_max_queues', 'eitcoursegrouptools'),
//                                              get_string('cfg_max_queues_desc', 'eitcoursegrouptools'),
//                                              '5', PARAM_INT);
//    $settings->add($maxqueues);

    // Multiple registrations?
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/allow_multiple',
//                                                    get_string('cfg_allow_multiple', 'eitcoursegrouptools'),
//                                                    get_string('cfg_allow_multiple_desc', 'eitcoursegrouptools'),
//                                                    0, $yes = '1', $no = '0'));
//
//    // Min groups to choose?
//    $mingroups = new admin_setting_configtext('mod_eitcoursegrouptools/choose_min',
//                                              get_string('cfg_choose_min', 'eitcoursegrouptools'),
//                                              get_string('cfg_choose_min_desc', 'eitcoursegrouptools'),
//                                              '1', PARAM_INT);
//    $settings->add($mingroups);
//
//    // Max groups to choose?
//    $maxgroups = new admin_setting_configtext('mod_eitcoursegrouptools/choose_max',
//                                              get_string('cfg_choose_max', 'eitcoursegrouptools'),
//                                              get_string('cfg_choose_max_desc', 'eitcoursegrouptools'),
//                                              '1', PARAM_INT);
//    $settings->add($maxgroups);

//    $settings->add(new admin_setting_heading('mod_eitcoursegrouptools/moodlesync',
//                                             get_string('cfg_moodlesync_head', 'eitcoursegrouptools'),
//                                             get_string('cfg_moodlesync_head_info', 'eitcoursegrouptools')));
//
//    $options = array( ECGT_IGNORE => get_string('ignorechanges', 'eitcoursegrouptools'),
//                      ECGT_FOLLOW => get_string('followchanges', 'eitcoursegrouptools'));
//
//    $settings->add(new admin_setting_configselect('mod_eitcoursegrouptools/ifmemberadded',
//                                                  get_string('cfg_ifmemberadded', 'eitcoursegrouptools'),
//                                                  get_string('cfg_ifmemberadded_desc', 'eitcoursegrouptools'),
//                                                  ECGT_IGNORE,
//                                                  $options));
//
//    $settings->add(new admin_setting_configselect('mod_eitcoursegrouptools/ifmemberremoved',
//                                                  get_string('cfg_ifmemberremoved', 'eitcoursegrouptools'),
//                                                  get_string('cfg_ifmemberremoved_desc', 'eitcoursegrouptools'),
//                                                  ECGT_IGNORE,
//                                                  $options));
//
//    $options = array( ECGT_RECREATE_GROUP => get_string('recreate_group', 'eitcoursegrouptools'),
//                      ECGT_DELETE_REF     => get_string('delete_reference', 'eitcoursegrouptools'));
//
//    $settings->add(new admin_setting_configselect('mod_eitcoursegrouptools/ifgroupdeleted',
//                                                  get_string('cfg_ifgroupdeleted', 'eitcoursegrouptools'),
//                                                  get_string('cfg_ifgroupdeleted_desc', 'eitcoursegrouptools'),
//                                                  ECGT_RECREATE_GROUP,
//                                                  $options));
//
//    $settings->add(new admin_setting_heading('mod_eitcoursegrouptools/addinstanceset',
//                                             get_string('cfg_addinstanceset_head', 'eitcoursegrouptools'),
//                                             get_string('cfg_addinstanceset_head_info', 'eitcoursegrouptools')));
//
//    $settings->add(new admin_setting_configcheckbox('mod_eitcoursegrouptools/force_importreg',
//                                                    get_string('cfg_force_importreg', 'eitcoursegrouptools'),
//                                                    get_string('cfg_force_importreg_desc', 'eitcoursegrouptools'),
//                                                    0, $yes = '1', $no = '0'));
//
//    $settings->add(new admin_setting_configtext('mod_eitcoursegrouptools/importfields',
//                                                get_string('cfg_importfields', 'eitcoursegrouptools'),
//                                                get_string('cfg_importfields_desc', 'eitcoursegrouptools'),
//                                                'username,idnumber', "/^((?![^a-zA-Z,]).)*$/"));
}