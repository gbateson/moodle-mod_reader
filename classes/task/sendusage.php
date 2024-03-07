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
 * Scheduled task for sending usage data on Reader books.
 *
 * @package   mod_reader
 * @author    Gordon Bateson 2023
 * @copyright Gordon Bateson 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reader\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for collecting sendusage on Reader books.
 *
 * @package   mod_reader
 * @author    Gordon Bateson 2023
 * @copyright Gordon Bateson 2023
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sendusage extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('sendusagetask', 'mod_reader');
    }

    /**
     * Remove old entries from table mod_reader
     */
    public function execute() {
        global $CFG, $DB;

        mtrace( 'Collecting Reader usage data');
        $mtrace = ''; // Message to be displayed via mtrace.

        // delete expired messages
        $select = 'timefinish > ? AND timefinish < ?';
        $params = array(0, time());
        $DB->delete_records_select('reader_messages', $select, $params);

        // check time that Reader usage stats were last updated
        $time = time();
        $name = 'last_update';
        if ($update = get_config('mod_reader', $name)) {
            $update += (4 * WEEKSECS); // next update
            if (($update <= $time)) {
                $send_usage_stats = true;
            } else {
                $send_usage_stats = false;
                $mtrace = '... oops, too soon since previous send';
            }
        } else {
            $send_usage_stats = true; // first time
        }

        // prevent sending of Reader usage stats from developer/test sites
        if (preg_match('/^https?:\/\/localhost/', $CFG->dirroot) && debugging('', DEBUG_DEVELOPER)) {
            $send_usage_stats = false;
            $mtrace = '... oops, results are not sent from developer/test sites.';
        }

        // send usage stats, if necessary
        if ($send_usage_stats) {
            set_config($name, $time, 'mod_reader');

            // get remotesite classes
            require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite.php');
            require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/moodlereadernet.php');

            // create an object to represent main download site (moodlereader.net)
            $remotesite = new reader_remotesite_moodlereadernet(get_config('mod_reader', 'serverurl'),
                                                                get_config('mod_reader', 'serverusername'),
                                                                get_config('mod_reader', 'serverpassword'));
            if ($results = $remotesite->send_usage_stats()) {
                $mtrace = '... OK, usage data was sent successfully';
            } else {
                $mtrace = '... No data found, so nothing was sent';
            }
        }

        if ($mtrace) {
            mtrace($mtrace);
        }

        // No return value is required.
    }
}
