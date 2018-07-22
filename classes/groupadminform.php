<?php

require_once($CFG->libdir.'/formslib.php');


class mod_eitcoursegrouptools_groupadminform extends moodleform {


    //Add elements to form
    public function definition() {
        global $CFG, $DB,$SESSION;

        $mform = $this->_form; // Don't forget the underscore! 
        // $DB->execute("UPDATE mdl_ecgt_activegroups SET active = ".$act." WHERE mdl_ecgt_activegroups.id = 13");
        $selactagrp = $DB->get_records("ecgt_activegroups", array('grouptoolid' => $SESSION->instance), "", $fields = "groupid,active,id");
        $SESSION->ecgt_activegroups = $selactagrp ;

        foreach ($selactagrp as $itr) {
            $selectmgrp = $DB->get_records("groups", array('id' => $itr->groupid), "", $fields = "id,name");
            
           
            foreach ($selectmgrp as $itr2) {
                $mform->addElement('advcheckbox', $itr2->id, $itr2->id . " - " . $itr2->name, 'Active as shell group', array('group' => 1), array(0, 1));
                $mform->setType($itr2->id, PARAM_INT);
                $mform->setDefault($itr2->id, $itr->active);
            
            }

        }
        

        //$user = $DB->get_records('ecgt_activegroups', array('id' => '2'));
        //echo print_r($user);
        //$upd = $DB->update_record('ecgt_activegroups` SET `active` = "0" WHERE `mdl_ecgt_activegroups`.`id` = 13');
        //print_r($upd);

//        $buttonarray = array();
//        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
//        $buttonarray[] = $mform->createElement('cancel');
//        $mform->addGroup($buttonarray, 'buttonar', '', '', false);
        $this->add_action_buttons(false, 'submit');
        
        //$mform::errorMessage(1);
    }

    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }

}
