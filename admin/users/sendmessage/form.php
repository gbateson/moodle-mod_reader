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
 * mod/reader/admin/users/sendmessage_form.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * mod_reader_admin_users_sendmessage_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_sendmessage_form extends moodleform {

    /**
     * constructor
     *
     * @param mixed $action the action attribute (=url) for the form
     * @todo Finish documenting this function
     */
    function mod_reader_admin_users_sendmessage_form($action=null) {
        parent::moodleform($action);
    }

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {

        // shortcut to our form
        $mform = $this->_form;

        // groupids
        $groups = $this->format_groups();
        if (empty($groups)) {
            $mform->addElement('hidden', 'groupids', 0);
        } else {
            $params = array('size' => max(5, count($groups)), 'multiple' => 'multiple');
            $mform->addElement('select', 'groupids', get_string('group'), $groups, $params);
            $mform->setDefault('groupids', 0);
        }
        $mform->setType('groupids', PARAM_INT);

        // timefinish
        $mform->addElement('date_time_selector', 'timefinish', get_string('sendmessagetime', 'reader'), array('optional' => true));
        $mform->setDefault('timefinish', 0);
        $mform->setType('timefinish', PARAM_INT);

        // messageid
        $mform->addElement('hidden', 'messageid', 0);
        $mform->setType('messageid', PARAM_INT);

        // message
        $mform->addElement('editor', 'message', get_string('sendmessagetext', 'reader'), array('size' => 40));
        $mform->setDefault('message', '');
        $mform->setType('message', PARAM_RAW);

        // active messages
        if ($messages = $this->format_messages($groups)) {
            $messages = html_writer::tag('h3', get_string('activemessages', 'reader')).
                        html_writer::tag('ol', $messages, array('class' => 'messages'));
            $mform->addElement('static', 'messages', '', $messages);
        }

        // buttons
        $this->add_action_buttons();
    }

    /**
     * format_groups
     */
    function format_groups() {
        global $PAGE;
        if ($groups = groups_get_all_groups($PAGE->course->id, 0, 0, 'id,name')) {
            foreach ($groups as $groupid => $group) {
                $groups[$groupid] = $group->name;
            }
            asort($groups);
            $groups = array(0 => get_string('all')) + $groups;
        } else {
            $groups = array();
        }
        return $groups;
    }

    /**
     * format_messages
     */
    function format_messages($groups) {
        global $DB, $OUTPUT, $PAGE, $USER;

        // set date format
        // strftimerecent: 4 Apr, 20:30
        // strftimerecentfull: Fri, 4 Apr 2014, 08:30 pm
        $dateformat = get_string('strftimerecentfull');
        $time = time(); // current date and time

        $items = '';
        $mform = $this->_form;

        if ($action = optional_param('action', '', PARAM_ALPHA)) {
            $messageid = optional_param('messageid', 0, PARAM_INT);
        } else {
            $messageid = 0;
        }

        $select = 'id <> ? AND readerid = ? AND timefinish > ?';
        $params = array($messageid, $PAGE->cm->instance, $time);
        if ($messages = $DB->get_records_select('reader_messages', $select, $params, 'timefinish')) {
            foreach ($messages as $message) {
                $item = '';
                if ($message->teacherid) {
                    if ($message->teacherid==$USER->id) {
                        $teachername = fullname($USER);
                    } else if ($teachername = $DB->get_record('user', array('id' => $message->teacherid))) {
                        $teachername = fullname($teachername);
                    } else {
                        $teachername = '';
                    }
                    if ($teachername) {
                        $teachername = html_writer::tag('b', get_string('from')).': '.$teachername;
                        $item .= html_writer::tag('li', $teachername, array('class' => 'teacher'));
                    }
                }
                if ($groupnames = $message->groupids) {
                    $groupnames = explode(',', $groupnames);
                    foreach ($groupnames as $g => $gid) {
                        if (array_key_exists($gid, $groups)) {
                            $groupnames[$g] = $groups[$gid];
                        } else {
                            $groupnames[$g] = ''; // shouldn't happen !!
                        }
                    }
                    $groupnames = array_filter($groupnames);
                    if ($groupnames = implode(', ', $groupnames)) {
                        $groupnames = html_writer::tag('b', get_string('group')).': '.$groupnames;
                        $item .= html_writer::tag('li', $groupnames, array('class' => 'groups'));
                    }
                }
                if ($timemodified = $message->timemodified) {
                    $timemodified = userdate($timemodified, $dateformat);
                    $timemodified = html_writer::tag('b', get_string('updated', 'tag')).': '.$timemodified;
                    $item .= html_writer::tag('li', $timemodified, array('class' => 'timemodified'));
                }
                if ($timefinish = $message->timefinish) {
                    if ($days = round(($timefinish - $time) / (60 * 60 * 24), 0)) {
                        $days = ' ('.$days.' days remaining)';
                    }
                    $timefinish = userdate($timefinish, $dateformat).$days;
                    $timefinish = html_writer::tag('b', get_string('sendmessagetime', 'reader')).': '.$timefinish;
                    $item .= html_writer::tag('li', $timefinish, array('class' => 'timefinish'));
                }
                if ($text = $message->messagetext) {
                    $text = strip_tags(format_text($text, $message->messageformat));
                    //$text = html_writer::tag('b', get_string('sendmessagetext', 'reader')).': '.$text;
                    $item .= html_writer::tag('li', $text, array('class' => 'messagetext'));
                }
                if ($item) {
                    $icons = array();
                    $href = new moodle_url($mform->_attributes['action']);

                    // edit url
                    $params = $href->params();
                    $params['action'] = 'editmessage';
                    $params['messageid'] = $message->id;
                    $href->params($params);

                    // edit icon
                    $icon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
                    $icons[] = html_writer::link($href, $icon, array('class' => 'editicon'));

                    // delete url
                    $params = $href->params();
                    $params['action'] = 'deletemessage';
                    $params['messageid'] = $message->id;
                    $href->params($params);

                    // delete icon
                    $icon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
                    $icons[] = html_writer::link($href, $icon, array('class' => 'deleteicon'));

                    // icons
                    if ($icons = implode(' ', $icons)) {
                        $item .= html_writer::tag('li', $icons, array('class' => 'icons'));
                    }

                    $item = html_writer::tag('ul', $item, array('class' => 'details'));
                    $items .= html_writer::tag('li', $item, array('class' => 'message'));
                }
            }
        }
        return $items;
    }

    /**
     * set_data
     *
     * @param stdClass $default_values a record from the "reader_messages" table
     */
    function set_data($data) {
        $mform = $this->_form;

        $mform->setDefault('messageid',  $data->id);
        $mform->setDefault('groupids',   $data->groupids);
        $mform->setDefault('timefinish', $data->timefinish);

        // there is no "setDefault" for editors
        // so we set default message text manually
        $element = $mform->getElement('message');
        $value = $element->getValue();
        if (is_array($value) && empty($value['text'])) {
            $value['text'] = $data->messagetext;
            $value['format'] = $data->messageformat;
            $element->setValue($value);
        }
    }

    /**
     * clear_all_values
     */
    function clear_all_values() {
        $mform = $this->_form;
        foreach ($mform->_elements as $element) {
            switch ($type = $element->getType()) {
                case 'select':
                    $element->setValue(null);
                    break;
                case 'date_time_selector':
                    $element->setValue(null);
                    break;
                case 'hidden':
                    $element->setValue('');
                    break;
                case 'editor':
                    $value = array('text' => null, 'format' => null, 'itemid' => null);
                    $element->setValue($value);
                    break;
                case 'group':
                case 'static':
                    // do nothing
                    break;
            }
        }
    }
}
