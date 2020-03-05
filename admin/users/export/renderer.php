<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * mod/reader/admin/users/export/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_export_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_export_renderer extends mod_reader_admin_users_renderer {

    /** the name of the form element that, if present, signifies content is to be downloaded */
    protected $download_param_name = 'filename';

    public $mode = 'export';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_USERS_EXPORT;
    }

    /**
     * render_page
     *
     * @return string formatted html output
     */
    public function render_page() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/reader/admin/users/export/form.php');

        $url = $this->page->url;
        $params = $url->params();
        $params['id'] = $this->reader->cm->id;
        $params['tab'] = $this->get_tab();
        $params['mode'] = mod_reader::get_mode('admin/users');
        $url->params($params);

        $mform = new mod_reader_admin_users_export_form($url->out(false));

        if ($data = $mform->get_submitted_data()) {
            $filename = $data->filename;

            $select = 'ra.*, u.username, rb.image, rl.currentlevel';
            $from   = '{reader_attempts} ra '.
                      'JOIN {user} u ON ra.userid = u.id '.
                      'JOIN {reader_books} rb ON ra.bookid = rb.id '.
                      'JOIN {reader_levels} rl ON ra.userid = rl.userid AND ra.readerid = rl.readerid';
            $where  = 'ra.readerid = ? AND ra.deleted = ?';
            $order  = 'ra.userid, ra.quizid, ra.timefinish, ra.uniqueid DESC';
            $params = array($this->reader->id, 0);

            if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {

                if (! headers_sent()) {
                    header('Content-Disposition: attachment; filename="'.$filename.'"');
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
                }

                $userid = 0;
                $quizid = 0;
                $timefinish = 0;

                $fields = null;
                foreach ($attempts as $attempt) {

                    // determine $fields to export (first time only)
                    if ($fields === null) {

                        // These fields are not exported.
                        $fields = array('id', 'readerid', 'userid', 'bookid', 'quizid', 'layout');

                        // Get names of fields to be exported (i.e. all fields except those above).
                        $fields = array_diff(array_keys(get_object_vars($attempt)), $fields);

                        // Put "username" first.
                        $i = array_search('username', $fields);
                        if (is_numeric($i)) {
                            unset($fields[$i]);
                        }
                        array_unshift($fields, 'username');

                        // $fields now holds something like the following:
                        // 'username', 'uniqueid', 'attempt',
                        // 'sumgrades', 'percentgrade', 'passed',
                        // 'credit', 'cheated', 'deleted',
                        // 'timestart', 'timefinish', 'timemodified',
                        // 'currentpage', 'state'
                        // 'bookrating', 'ip', 'image', 'currentlevel'

                        // Print list of field names (on first line of export file)
                        echo implode(',', $fields)."\n";
                    }

                    // ignore lower uniqueids with same userid/quizid/timefinish
                    if ($attempt->userid==$userid && $attempt->quizid==$quizid && $attempt->timefinish==$timefinish) {
                        continue;
                    }

                    $userid = $attempt->userid;
                    $quizid = $attempt->quizid;
                    $timefinish = $attempt->timefinish;

                    // remove trailing zeroes and periods from percent grade
                    $attempt->percentgrade = preg_replace('/(\.0)?0$/', '', $attempt->percentgrade);

                    // Print $fields from this $attempt
                    foreach ($fields as $i => $field) {
                        if ($i) {
                            echo ',';
                        }
                        if (isset($attempt->$field)) {
                            echo $attempt->$field;
                        } else {
                            echo ''; // shouldn't happen !!
                        }
                    }
                    echo "\n";

                    // Legacy field order:
                    // 'username', 'uniqueid', 'attempt',
                    // 'sumgrades', 'percentgrade', 'passed',
                    // 'timefinish', 'percentgrade'
                    // 'bookrating', 'ip', 'image', 'currentlevel'
                }
            }
        } else {
            $mform->display();
        }
    }
}
