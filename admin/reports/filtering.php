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
require_once($CFG->dirroot.'/user/filters/lib.php');

// get child classes
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/date.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/select.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/simpleselect.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/text.php');

require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/duration.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/group.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filters/number.php');

/**
 * reader_admin_reports_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_filtering extends user_filtering {

    /** @var moodleform used for display options */
    var $_optionsform = null;

    /**
     * Contructor
     * @param array  $filterfields  names of visible fields
     * @param string $baseurl       url for submission/return, null if the same of current page
     * @param array  $params        extra page parameters
     * @param array  $optionfields  names of display option fields
     */
    public function __construct($filterfields=null, $baseurl=null, $params=null, $optionfields=null) {
        if ($optionfields) {
            $classname = str_replace('filtering', 'options', get_class($this));
            $this->_optionsform = new $classname($optionfields, $baseurl, $filterfields);
        }
        parent::user_filtering($filterfields, $baseurl, $params);
    }

    /**
     * get_field
     * reader version of standard function
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    public function get_field($fieldname, $advanced)  {
        global $DB;

        $default = $this->get_default_value($fieldname);
        switch ($fieldname) {

            case 'realname':
                $label = get_string('fullname');
                return new reader_admin_reports_filter_text($fieldname, $label, $advanced, $DB->sql_fullname(), $default, 'where');
                break;

            case 'lastname':
            case 'firstname':
            case 'username':
                $label = get_string($fieldname);
                return new reader_admin_reports_filter_text($fieldname, $label, $advanced, $fieldname, $default, 'where');
                break;

            case 'duration':
                $label = get_string($fieldname, 'mod_reader');
                return new reader_admin_reports_filter_duration($fieldname, $label, $advanced, $fieldname, $default, 'having');
                break;

            case 'grade':
                $label = get_string($fieldname);
                return new reader_admin_reports_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having');

            default:
                // other fields (e.g. from user record)
                die("Unknown filter field: $fieldname");
                return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * get_default_value
     *
     * @param string $fieldname
     * @return array sql string and $params
     */
    public function get_default_value($fieldname) {
        $default = get_user_preferences('reader_'.$fieldname, '');
        $rawdata = data_submitted();
        if ($rawdata && isset($rawdata->$fieldname) && ! is_array($rawdata->$fieldname)) {
            $default = optional_param($fieldname, $default, PARAM_ALPHANUM);
        }
        return $default;
    }

    /**
     * Returns sql statement based on active filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @param string $type of sql (optional, default = "filter") "filter", "where" or "having"
     * @return array sql string and $params
     */
    public function get_sql($extra='', array $params=null, $type='filter') {
        global $SESSION;

        $sqls = array();
        if ($extra) {
            $sqls[] = $extra;
        }
        if ($params===null) {
            $params = array();
        }

        // get_sql_filter
        // get_sql_where
        // get_sql_having
        $method = 'get_sql_'.$type;

        if (! empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fieldname => $conditions) {
                if (! array_key_exists($fieldname, $this->_fields)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fieldname];
                if (! method_exists($field, $method)) {
                    continue; // no $type sql for this $field
                }
                foreach ($conditions as $condition) {
                    list($s, $p) = $field->$method($condition);
                    if ($s) {
                        $sqls[] = $s;
                        $params += $p;
                    }
                }
            }
        }

        $sqls = implode(' AND ', $sqls);
        return array($sqls, $params);
    }

    /**
     * Returns sql WHERE and HAVING statements based on active user filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @return array ($wherefilter, $havingfilter, $params)
     */
    public function get_sql_filter($extra='', array $params=null) {
        list($wherefilter, $whereparams) = $this->get_sql_where($extra, $params);
        list($havingfilter, $havingparams) = $this->get_sql_having($extra, $params);

        if ($this->_optionsform) {
            list($optionsfilter, $optionsparams) = $this->_optionsform->get_sql();
            $wherefilter .= $optionsfilter;
            $whereparams += $optionsparams;
        }

        // remove empty " AND " conditions at start, middle and end of filter
        $search = array('/^(?: AND )+/', '/(<= AND )(?: AND )+/', '/(?: AND )+$/');

        $wherefilter = preg_replace($search, '', $wherefilter);
        $havingfilter = preg_replace($search, '', $havingfilter);

        if ($whereparams || $havingparams) {
            if ($params===null) {
                $params = array();
            }
            if ($whereparams) {
                $params += $whereparams;
            }
            if ($havingparams) {
                $params += $havingparams;
            }
        }

        return array($wherefilter, $havingfilter, $params);
    }

    /**
     * Returns sql WHERE statement based on active user filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @return array ($sql, $params)
     */
    public function get_sql_where($extra='', array $params=null) {
        return $this->get_sql($extra, $params, 'where');
    }

    /**
     * Returns sql HAVING statement based on active filters
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array ($sql, $params)
     */
    public function get_sql_having($extra='', array $params=null) {
        return $this->get_sql($extra, $params, 'having');
    }

    /**
     * display options form
     */
    public function display_options() {
        if ($this->_optionsform) {
            $this->_optionsform->display();
        }
    }

    /**
     * get a single option value
     */
    public function get_optionvalue($name, $default=null) {
        if ($this->_optionsform) {
            return $this->_optionsform->get_value($name, $default);
        } else {
            return $default;
        }
    }

    /*
     * uniqueid
     *
     * @param string $type
     * @return string $uniqueid
     */
    static function uniqueid($type) {
        static $types = array();
        if (isset($types[$type])) {
            $types[$type] ++;
        } else {
            $types[$type] = 0;
        }
        return $types[$type];
    }
}

/**
 * reader_admin_reports_options
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_options extends moodleform {

    const SUBMIT_BUTTON_NAME = 'submitoptions';

    /**#@+
    * values for report $usertype
    *
    * @const integer
    */
    const USERS_ENROLLED_WITH    = 0;
    const USERS_ENROLLED_WITHOUT = 1;
    const USERS_ENROLLED_ALL     = 2;
    const USERS_ALL_WITH         = 3;
    /**#@-*/

    /** @var list of display option fields array($name => $default) */
    protected $optionfields = array();

    /** @var list of filter/sort field names */
    protected $sortfields = null;

    /**
     * constructor (see "moodleform" in lib/formslib.php)
     */
    public function __construct($optionfields, $action, $sortfields) {
        global $SESSION;

        // get and set values in $SESSION
        $uniqueid = $this->get_maintable_uniqueid();
        foreach ($optionfields as $field => $default) {
            if (! isset($SESSION->flextable[$uniqueid]->display)) {
                $SESSION->flextable[$uniqueid]->display = array();
            }
            if (isset($SESSION->flextable[$uniqueid]->display[$field])) {
                $default = $SESSION->flextable[$uniqueid]->display[$field];
            }
            if (is_array($default) || is_numeric($default)) {
                $type = PARAM_INT;
            } else {
                $type = PARAM_ALPHA;
            }
            if ($field=='sortfields') {
                $value = optional_param_array('sortfields', $default, PARAM_INT);
                foreach ($value as $sortfield => $sortdirection) {
                    if ($sortdirection==0) {
                        unset($SESSION->flextable[$uniqueid]->sortby[$sortfield]);
                        unset($value[$sortfield]);
                    } else {
                        $sortdirection = ($sortdirection==SORT_ASC ? SORT_ASC : SORT_DESC);
                        $SESSION->flextable[$uniqueid]->sortby[$sortfield] = $sortdirection;
                    }
                }
            } else {
                $value = optional_param($field, $default, $type);
            }
            $SESSION->flextable[$uniqueid]->display[$field] = $value;
        }

        $this->optionfields = $optionfields;
        $this->sortfields  = $sortfields;
        parent::moodleform($action);
    }

    /**
     * definition (see "moodleform" in lib/formslib.php)
     */
    public function definition() {
        $mform = $this->_form;

        $label = get_string('displayoptions', 'mod_reader');
        $mform->addElement('header', 'displayoptions', $label);

        // add element for each $fields
        foreach ($this->optionfields as $name => $default) {
            $add_field = 'add_field_'.$name;
            $value = $this->get_value($name, $default);
            $this->$add_field($mform, $name, $value);
        }
        if (count($this->optionfields)) {
            $this->add_field_submitbutton($mform, self::SUBMIT_BUTTON_NAME);
        }
    }

    /**
     * get_maintable_uniqueid
     * convert convert class name, e.g. reader_admin_reports_xxx_options
     * to id string of main table, e.g. mod-reader-admin-reports-xxx
     */
     protected function get_maintable_uniqueid() {
        $uniqueid = get_class($this);
        $uniqueid = substr($uniqueid, 0, -8);
        $uniqueid = str_replace('_', '-', $uniqueid);
        return 'mod-'.$uniqueid;
     }

    /**
     * add_field_sortfields
     *
     * @param object $mform
     * @param string $name of field i.e. "sortfields"
     * @param mixed  $default value for this $field
     */
    protected function add_field_sortfields($mform, $name, $default) {
        global $SESSION;

        $uniqueid = $this->get_maintable_uniqueid();
        if (isset($SESSION->flextable[$uniqueid]->sortby)) {
            $sortby = $SESSION->flextable[$uniqueid]->sortby;
        } else {
            $sortby = array();
        }

        // onchange event handler for the <select> elements
        $onchange = 'this.form.elements["'.self::SUBMIT_BUTTON_NAME.'"].click()';
        $separator = '';
        $elements = array();
        foreach ($sortby as $sortfield => $sortdirection) {
            if (array_key_exists($sortfield, $this->sortfields)) {
                switch ($sortfield) {
                    case 'firstname':
                    case 'lastname':
                    case 'username':
                        $label = get_string($sortfield);
                        break;
                    case 'groupname':
                        $label = get_string('group');
                        break;
                    case 'name':
                        $label = get_string('booktitle', 'mod_reader');
                        break;
                    default:
                        $label = get_string($sortfield, 'mod_reader');
                }
                $options = array(
                    SORT_ASC  => get_string('asc'),
                    SORT_DESC => get_string('desc'),
                    0         => get_string('remove')
                );

                if ($separator=='') {
                    $separator = html_writer::empty_tag('br'); // first time through
                } else {
                    $elements[] = $mform->createElement('static', '', '', $separator);
                }
                $elements[] = $mform->createElement('static', '', '', $label.': ');
                $elements[] = $mform->createElement('select', $sortfield, '', $options, array('onchange' => $onchange));
            }
        }

        if (count($elements)) {
            $label = get_string('sortby');
            $mform->addGroup($elements, $name, $label, '');
            foreach ($sortby as $sortfield => $sortdirection) {
                $mform->setType($name.'['.$sortfield.']', PARAM_INT);
                $mform->setDefault($name.'['.$sortfield.']', $sortdirection);
            }
            $mform->setAdvanced($name);
        }
    }

    /**
     * get_sortdirection_img
     *
     * @param string $sortdirection (SORT_ASC or SORT_DESC)
     * @return string html img tag
     */
    protected function get_sortdirection_img($sortdirection) {
        global $OUTPUT;
        $type = ($sortdirection==SORT_ASC ? 'asc' : 'desc');
        $alt = get_string($type);
        $src = $OUTPUT->pix_url('t/sort_'.$type);
        return html_writer::empty_tag('img', array('src' => $src, 'alt' => $alt, 'class' => 'iconsort'));
    }

    /**
     * get_sortremove_img
     *
     * @return string html img tag
     */
    protected function get_sortremove_img() {
        global $OUTPUT;
        $alt = get_string('remove');
        $src = $OUTPUT->pix_url('t/delete');
        return html_writer::empty_tag('img', array('src' => $src, 'alt' => $alt, 'class' => 'iconsort'));
    }

    /**
     * add_field_usertype
     *
     * @param object $mform
     * @param string $name of field i.e. "add_field_usertype"
     * @param mixed  $default value for this $field
     */
    protected function add_field_usertype($mform, $name, $default) {
        $label = get_string('usertype', 'mod_reader');
        $options = array(self::USERS_ENROLLED_WITH    => get_string('usersenrolledwith',    'mod_reader'),
                         self::USERS_ENROLLED_WITHOUT => get_string('usersenrolledwithout', 'mod_reader'),
                         self::USERS_ENROLLED_ALL     => get_string('usersenrolledall',     'mod_reader'),
                         self::USERS_ALL_WITH         => get_string('usersallwith',         'mod_reader'));
        $this->add_select_autosubmit($mform, $name, $label, $options, $default);
    }

    /**
     * add_field_rowsperpage
     *
     * @param object $mform
     * @param string $name of field i.e. "rowsperpage"
     * @param mixed  $default value for this $field
     */
    protected function add_field_rowsperpage($mform, $name, $default) {
        $label = get_string($name, 'mod_reader');
        $options = array_merge(range(1, 9, 1), range(10, 90, 10), range(100, 1000, 100));
        $options = array_combine($options, $options);
        $this->add_select_autosubmit($mform, $name, $label, $options, $default);
    }

    /**
     * add_field_showhidden
     *
     * @param object $mform
     * @param string $name of field i.e. "showhidden"
     * @param mixed  $default value for this $field
     */
    protected function add_field_showhidden($mform, $name, $default) {
        $label = get_string($name, 'mod_reader');
        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $this->add_select_autosubmit($mform, $name, $label, $options, $default, true);
    }

    /**
     * add_field_showdeleted
     *
     * @param object $mform
     * @param string $name of field i.e. "showdeleted"
     * @param mixed  $default value for this $field
     */
    protected function add_field_showdeleted($mform, $name, $default) {
        $label = get_string($name, 'mod_reader');
        $options = array('0' => get_string('no'), '1' => get_string('yes'));
        $this->add_select_autosubmit($mform, $name, $label, $options, $default, true);
    }

    /**
     * add_select_autosubmit
     *
     * @param object $mform
     * @param string $name of field
     * @param string $label
     * @param string $options
     * @param string $default
     * @param string $advanced (optional, default=false)
     */
    protected function add_select_autosubmit($mform, $name, $label, $options, $default, $advanced=false) {
        $attributes = array('onchange' => 'this.form.elements["'.self::SUBMIT_BUTTON_NAME.'"].click()');
        $mform->addElement('select', $name, $label, $options, $attributes);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, $default);
        if ($advanced && $default==0) {
            $mform->setAdvanced($name);
        }
    }

    /**
     * add_field_submitbutton
     *
     * @param object $mform
     * @param string $name of field i.e. "submitbutton"
     */
    protected function add_field_submitbutton($mform, $name) {
        $mform->addElement('submit', $name, get_string('go'));
        $js = '';
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "var obj = document.getElementById('fitem_id_$name')\n";
        $js .= "if (obj) {\n";
        $js .= "    obj.style.display = 'none';\n";
        $js .= "}\n";
        $js .= "obj = null;\n";
        $js .= "//]]>\n";
        $js .= '</script>'."\n";
        $mform->addElement('static', '', '', $js);
    }

    /**
     * get_value
     *
     * @param string $name of field e.g. "rowsperpage"
     */
    public function get_value($name, $default=null) {
        global $SESSION;
        $uniqueid = $this->get_maintable_uniqueid();
        if (isset($SESSION->flextable[$uniqueid]->display[$name])) {
            return $SESSION->flextable[$uniqueid]->display[$name];
        } else {
            return $default; // shouldn't happen !!
        }
    }

    /**
     * get_sql
     */
    public function get_sql() {
        $wherefilter = '';
        $whereparams = array();
        foreach ($this->optionfields as $name => $default) {
            $value = $this->get_value($name, $default);
            $get_sql = 'get_sql_'.$name;
            if ($sql = $this->$get_sql($name, $value)) {
                list($filter, $params) = $sql;
                $wherefilter .= ' AND '.$filter;
                $whereparams += $params;
            }
        }
        return array($wherefilter, $whereparams);
    }

    /**
     * get_sql_rowsperpage
     *
     * @param string $name of field i.e. "rowsperpage"
     * @param object $value
     */
    protected function get_sql_rowsperpage($name, $value) {
        return null;
    }

    /**
     * get_sql_usertype
     *
     * @param string $name of field i.e. "usertype"
     * @param object $value
     */
    protected function get_sql_usertype($name, $value) {
        return null;
    }

    /**
     * get_sql_sortfields
     *
     * @param string $name of field i.e. "sortfields"
     * @param object $value
     */
    protected function get_sql_sortfields($name, $value) {
        return null;
    }

    /**
     * get_sql_showdeleted
     *
     * @param string $name of field i.e. "showdeleted"
     * @param object $value
     */
    protected function get_sql_showdeleted($name, $value) {
        return array("ra.deleted = :$name", array($name => $value));
    }


    /**
     * get_sql_showhidden
     *
     * @param string $name of field i.e. "showhidden"
     * @param object $value
     */
    protected function get_sql_showhidden($name, $value) {
        return array("rb.hidden = :$name", array($name => $value));
    }

}
