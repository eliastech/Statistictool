<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace mod_eitcoursegrouptools\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;
use ArrayIterator;

/**
 * Description of activity
 *
 * @author rapco
 */
class activity implements renderable, templatable {

    var $sometext = null;
    var $mylist;

    public function __construct($string, $data) {
        $this->sometext = $string;
        $this->mylist = $data;
    }

    public function export_for_template(renderer_base $output) {
        $data = new stdClass();
        $data->sometext = $this->sometext;
        $data->mylist = new ArrayIterator($this->mylist);
        return $data;
    }

}
