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
require_once($CFG->dirroot.'/mod/reader/admin/reports/filtering.php');

/**
 * reader_admin_reports_groupsummary_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_groupsummary_filtering extends reader_admin_reports_filtering {

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
            case 'groupname':
                return new reader_admin_filter_group($fieldname, $advanced, $default, 'where');

            case 'countactive':
            case 'countinactive':
                $label = get_string($fieldname, 'mod_reader');
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having');

            case 'percentactive':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * countactive / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'percentinactive':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * countinactive / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagetaken':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * (countpassed + countfailed) / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagepassed':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * countpassed / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagefailed':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * countfailed / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagepercentgrade':
                $label = get_string('averagegrade', 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * sumaveragegrade / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagewordsthisterm':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * totalwordsthisterm / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagewordsallterms':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * totalwordsallterms / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagepointsthisterm':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * totalpointsthisterm / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            case 'averagepointsallterms':
                $label = get_string($fieldname, 'mod_reader');
                $fieldsql = '(CASE WHEN countusers=0 THEN 0 ELSE (100 * totalpointsallterms / countusers) END)';
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having', $fieldsql);

            default:
                // other fields (e.g. from user record)
                return parent::get_field($fieldname, $advanced);
        }
    }
}

/**
 * reader_admin_reports_groupsummary_options
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_groupsummary_options extends reader_admin_reports_options {
}
