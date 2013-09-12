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
 * Filtering for Reader reports
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/reader/report/filtering.php');

/**
 * reader_report_booksummary_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_booksummary_filtering extends reader_report_filtering {

    /**
     * get_field
     * reader version of standard function
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    function get_field($fieldname, $advanced)  {
        $default = $this->get_default_value($fieldname);
        switch ($fieldname) {
            case 'group':
                return new reader_report_filter_group($fieldname, $advanced, $default, 'where');

            case 'publisher':
            case 'level':
                $label = get_string($fieldname, 'reader');
                return new reader_report_filter_text($fieldname, $label, $advanced, $fieldname, $default, 'where');

            case 'name':
                $label = get_string('booktitle', 'reader');
                return new reader_report_filter_text($fieldname, $label, $advanced, $fieldname, $default, 'where');

            case 'difficulty':
                $label = get_string('bookdifficulty', 'reader');
                return new reader_report_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'where');

            case 'countpassed':
            case 'countfailed':
            case 'averagegrade':
            case 'averagerating':
            case 'countrating':
                $label = get_string($fieldname, 'reader');
                return new reader_report_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having');

            case 'averageduration':
                $label = get_string($fieldname, 'reader');
                return new reader_report_filter_duration($fieldname, $label, $advanced, $fieldname, $default, 'having');

            default:
                // other fields (e.g. from user record)
                return parent::get_field($fieldname, $advanced);
        }
    }
}
