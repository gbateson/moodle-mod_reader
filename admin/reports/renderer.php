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
 * Render an attempt at a Reader quiz
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filtering.php');

/**
 * mod_reader_admin_reports_renderer
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class mod_reader_admin_reports_renderer extends mod_reader_admin_renderer {

    public $tab = 'reports';

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_REPORTS_USERSUMMARY  = 21;
    const TAB_REPORTS_USERDETAILED = 22;
    const TAB_REPORTS_GROUPSUMMARY = 23;
    const TAB_REPORTS_BOOKSUMMARY  = 24;
    const TAB_REPORTS_BOOKDETAILED = 25;
    /**#@-*/

    protected $users = null;

    /**
     * get_my_tab
     *
     * @return integer tab id
     */
    public function get_my_tab() {
        return self::TAB_REPORTS;
    }

    /**
     * get_default_tab
     *
     * @return integer tab id
     */
    public function get_default_tab() {
        return self::TAB_REPORTS_USERSUMMARY;
    }

    /**
     * get_tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function get_tabs() {
        $tabs = array();
        $cmid = $this->reader->cm->id;
        if ($this->reader->can_viewreports()) {
            $modes = mod_reader::get_modes('admin/reports');
            foreach ($modes as $mode) {
                $tab = constant('self::TAB_REPORTS_'.strtoupper($mode));
                $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
                $url = new moodle_url('/mod/reader/admin/reports.php', $params);
                $tabs[] = new tabobject($tab, $url, get_string('report'.$mode, 'mod_reader'));
            }
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_REPORTS, $tabs);
    }

    /**
     * render_page
     */
    public function render_page() {
        if ($this->reader->can_viewreports()) {
            echo $this->page_report();
        } else if (mod_reader::is_loggedinas()) {
            echo $this->render_logout();
        } else {
            $this->reader->req('viewreports');
        }
    }

    /**
     * render_logout
     *
     * a simple page to warn a teacher who is logged in as a student
     * that they must logout and then login as themselves to continue
     */
    public function render_logout()  {
        global $USER;

        $msg = get_string('logoutrequired', 'mod_reader', fullname($USER));
        $msg = format_text($msg, FORMAT_MARKDOWN, array('context' => $this->reader->context));

        $tab = optional_param('tab', 0, PARAM_INT);
        $mode = optional_param('mode', '', PARAM_ALPHA);
        $params = array('id' => $this->reader->cm->id, 'tab' => $tab, 'mode' => $mode);

        $button = new moodle_url('/mod/reader/view_loginas.php', $params);
        $button = new single_button($button, get_string('logout'));
        $button->class = 'continuebutton';

        $output = '';
        $output .= $this->notification($msg, 'notifyproblem');
        $output .= $this->render($button);
        return $output;
    }

    /**
     * get_standard_modes
     *
     * @param object $reader (optional, default=null)
     * @return string HTML output to display navigation tabs
     */
    static public function get_standard_modes($reader=null) {
        return array('usersummary', 'userdetailed', 'groupsummary', 'booksummary', 'bookdetailed');
    }
}
