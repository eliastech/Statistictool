<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of action_redir
 *
 * @author rapco
 */
require_once("../../config.php");


global $DB, $SESSION, $PAGE;



if (!empty($_POST['users'])) {
    foreach ($_POST['users'] as $value) {

     
        //$SESSION->sent2users = array($value);

        $touser = new stdClass();
        $user = $DB->get_records_sql('SELECT * FROM mdl_user WHERE id = ?', array($value));
        //echo print_r($user);

        foreach ($user as $itr) {

            $touser->id = $itr->id;
            $touser->firstname = $itr->firstname;
            $touser->lastname = $itr->lastname;
            $touser->mailformat = $itr->mailformat;
            $touser->maildisplay = $itr->maildisplay;
            $touser->username = $itr->username;
            $touser->email = $itr->email;
            $touser->firstnamephonetic = '';
            $touser->lang = $itr->lang;
            $touser->lastnamephonetic = '';
            $touser->middlename = $itr->middlename;
            $touser->alternatename = '';
            $touser->lastaccess = $itr->lastaccess;
            $touser->idnumber = $itr->idnumber;
            $touser->auth = $itr->auth;
            $touser->suspended = $itr->suspended;
            $touser->deleted = $itr->deleted;
            $touser->emailstop = $itr->emailstop;
            
            $SESSION->emailto[$SESSION->course][$itr->id] = $touser;
        }
       
        
    }
   

    //echo print_r($SESSION->emailto);
    redirect(new moodle_url('/user/messageselect.php?sesskey=' . sesskey(). '&id='.$SESSION->course));//, '', null, \core\output\notification::NOTIFY_ERROR);
}
else{ 
     redirect(new moodle_url('view.php?id='.$SESSION->id), 'No users were selected', null, \core\output\notification::NOTIFY_ERROR);
}
