<?php

/**
 * Description of activity
 *
 * @author rapco
 */
class mod_eitcoursegrouptools_activity {

    var $DB, $SESSION;
    var $output_arr, $out_user, $out_groups, $out_posts;
    var $varByUsers, $a, $b, $tempvar;

    public function __construct($course, $context) {
        global $DB, $SESSION;

        //echo print_r($context);
        //$this->getSortedListCombination($course);

        $this->DB = $DB;
        $this->SESSION = $SESSION;
        $this->SESSION->output_arr = array();
        //$this->temp;
    }

    public function getUsernamebyId($UserId) {

        $user = $this->DB->get_records_sql('SELECT id, firstname, lastname FROM mdl_user WHERE id = ?', array($UserId));

        foreach ($user as $itr) {
            $this->out_user = array($itr->id => array('firstname' => $itr->firstname . " " . $itr->lastname));
        }

        return $this->out_user;
    }

    public function getGroupbyUserId($userId, $course) {


        $sql2 = $this->DB->get_records_sql('SELECT id, userid, groupid FROM mdl_groups_members WHERE userid = ?', array($userId));

        foreach ($sql2 as $itr) {

            $sql1 = $this->DB->get_records_sql('SELECT id, name, courseid FROM mdl_groups WHERE id = ? AND courseid = ?', array($itr->groupid, $course));

            foreach ($sql1 as $itr2) {

                $this->tempvar .= $itr2->name . ", ";
                //echo "TVAR " . $this->tempvar;
                $this->out_groups[$itr->userid] = array('groupname' => $this->tempvar);
            }
        }

        return $this->out_groups;
    }

    public function getPostsCoursebyUserId($UserId, $course) {

        $sql1 = $this->DB->get_records_sql('SELECT id, course FROM mdl_forum_discussions WHERE course = ?', array($course));
        foreach ($sql1 as $itr) {

            $sql2 = $this->DB->get_records_sql('SELECT COUNT(id) as ctot, userid, discussion FROM mdl_forum_posts WHERE userid = ? AND discussion = ? ORDER BY ctot', array($UserId, $itr->id));

            foreach ($sql2 as $itr2) {

                @$a += count($itr2->discussion);
                @$b += $itr2->ctot;

                $this->out_posts[$itr2->userid] = array('discussions' => $a, 'counts' => $b);
            }
        }

        return $this->out_posts;
    }

    public function getGroupsMembers($course) {

        $sql = $this->DB->get_records_sql('SELECT a.id, a.userid, a.groupid, b.name FROM mdl_groups b JOIN mdl_groups_members a ON b.id = a.groupid AND b.courseid = 3', array($course));
      //$sql = $this->DB->get_records_sql('SELECT b.id, a.groupid, b.name, a.userid FROM mdl_groups b, mdl_groups_members a WHERE b.id = a.groupid AND b.courseid = ?', array($course));


        foreach ($sql as $gid => $itr2) {
        
            $grouparr[] = array('gid' => $itr2->groupid, 'groupname' => $itr2->name, "userid" => $itr2->userid);
        }

        //$this->output_arr[$itr2->id] = array('gid' => $itr2->id, 'groupname' => $itr2->name, "userid" => $itr->userid);

//       echo '<hr>';
//        echo print_r($grouparr);
//       echo '<hr>';
        return $grouparr;
    }

    public function getSortListbyGroups($course) {

        $a = $this->getSortedListCombination($course);
        $b = $this->getGroupsMembers($course);

        //echo "aa" . $a[2]['Counts'];

        foreach ($b as $itr2) {

            @$atot[$itr2['gid']] += $a[$itr2['userid']]['Counts'];
            @$uniqtot[$itr2['gid']] += $a[$itr2['userid']]['Unique'];
            @$memcount[$itr2['gid']] += count($a[$itr2['userid']]['UID']);

//            echo '<hr>';
//            echo "GID ".$gid;
//            echo '<hr>';

            $retarr[$itr2['gid']] = array("ctot" => $atot[$itr2['gid']], "groupname" => $itr2['groupname'], "memberstot" => $memcount[$itr2['gid']], "unique" => $uniqtot[$itr2['gid']]);
        }




        return $retarr;
    }

    public function getSortedListCombination($course) {

        //$this->getSortListbyGroups($course);

        $a = $this->SESSION->users = $this->DB->get_records_sql('SELECT DISTINCT u.id AS id, c.fullname,c.shortname, u.username, u.firstname, u.lastname, u.email FROM mdl_role_assignments ra, mdl_user u, mdl_course c, mdl_context cxt WHERE ra.userid = u.id AND ra.contextid = cxt.id AND cxt.contextlevel = 50 AND cxt.instanceid = c.id AND c.id = ?', array($course));
        //$a = $this->SESSION->users = $this->DB->get_records_sql('SELECT id FROM mdl_user');

        foreach ($a as $index => $userdata) {

            $user = $this->getUsernamebyId($index);
            $group = $this->getGroupbyUserId($index, $course);
            $activity = $this->getPostsCoursebyUserId($index, $course);
            $this->varByUsers[$index] = array("Counts" => @$activity[$userdata->id]['counts'], "UID" => $userdata->id, "User" => $user[$userdata->id]['firstname'], "Groups" => @$group[$userdata->id]['groupname'], "Unique" => @$activity[$userdata->id]['discussions']);

            //echo "<br>UID " . $userdata->id . " Firstname " . $user[$userdata->id]['firstname'] . " *Group* " . @$group[$userdata->id]['groupname']. "Unique ". @$activity[$userdata->id]['discussions']. " count ". @$activity[$userdata->id]['counts'];
        }
        //echo "SL Comb " . print_r($this->varByUsers);
        return @$this->varByUsers;
    }

}
