<?php



namespace mod_eitcoursegrouptools\output;

use plugin_renderer_base;

defined('MOODLE_INTERNAL') || die();

class renderer extends plugin_renderer_base {

    public function render_main($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_eitcoursegrouptools/main', $data);
    }

    public function render_activity($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_eitcoursegrouptools/activity', $data);
    }
    
        public function render_activitybygroup($page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('mod_eitcoursegrouptools/activitybygroup', $data);
    }

}
