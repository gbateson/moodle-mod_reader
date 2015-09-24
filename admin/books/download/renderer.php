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
 * mod/reader/admin/books/download/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/books/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');

/**
 * mod_reader_admin_books_download_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_books_download_renderer extends mod_reader_admin_books_renderer {

    /**
     * get_standard_types
     *
     * @return string HTML output to display navigation tabs
     */
    static public function get_standard_types() {
        return array(reader_downloader::BOOKS_WITH_QUIZZES,
                     reader_downloader::BOOKS_WITHOUT_QUIZZES);
    }

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/lib.php');

        $selectedpublishers = reader_optional_param_array('publishers', array(), PARAM_CLEAN);
        $selectedlevels     = reader_optional_param_array('levels',     array(), PARAM_CLEAN);
        $selecteditemids    = reader_optional_param_array('itemids',    array(), PARAM_CLEAN);

        $downloadmode       = reader_downloader::NORMAL_MODE; // default download mode
        $downloadmode       = reader_optional_param_array('downloadmode', $downloadmode, PARAM_INT);

        $tab = $this->get_tab();
        $mode = mod_reader::get_mode('admin/books');
        $type = mod_reader::get_type('admin/books/download');

        switch ($type) {
            case reader_downloader::BOOKS_WITH_QUIZZES: $str = 'uploadquiztoreader'; break;
            case reader_downloader::BOOKS_WITHOUT_QUIZZES: $str = 'uploaddatanoquizzes'; break;
        }
        echo $this->heading(get_string($str, 'mod_reader'));

        // create an object to represent main download site (moodlereader.net)
        $remotesite = new reader_remotesite_moodlereadernet(get_config('mod_reader', 'serverurl'),
                                                            get_config('mod_reader', 'serverusername'),
                                                            get_config('mod_reader', 'serverpassword'));

        // create an object to handle the downloading of data from remote sites
        $downloader = new reader_downloader($this);

        // register the known remote sites with the downloader
        $downloader->add_remotesite($remotesite);

        // get a list of items that have already been downloaded
        // from the remote site and are stored in the Moodle DB
        $downloader->get_downloaded_items($type, $downloadmode);

        // download the list(s) of available items from each remote site
        $downloader->add_available_items($type, $selecteditemids);

        // if any itemids have been selected for download, check that
        // they are valid, and expand selected publishers and levels
        $downloader->check_selected_itemids($selectedpublishers,
                                            $selectedlevels,
                                            $selecteditemids);

        // download selected any itemids from the remote site(s)
        // and add them to this Moodle site
        $downloader->add_selected_itemids($type, $selecteditemids);

        // if any downloadable items were found on the remote sites,
        // show them in a selectable, hierarchical menu
        $output = '';
        if ($count = $downloader->has_available_items()) {
            $newcount = $downloader->has_new_items();
            $updatecount = $downloader->has_updated_items();

            $output .= $this->form_start($tab, $mode, $type);

            //$output .= $this->formheader(get_string('pagesettings', 'mod_reader'));
            $output .= $this->downloadmode_menu($downloadmode); // "normal" or "repair"
            $output .= $this->downloadtype_menu($type); // "with" or "without" quizzes
            $output .= $this->search_box();
            $output .= $this->showhide_menu($count, $updatecount);
            $output .= $this->select_menu($newcount, $updatecount);

            //$output .= $this->formheader(get_string('availableitems', 'mod_reader'));
            $output .= $this->available_lists($downloader);

            if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
                //$output .= $this->formheader(get_string('downloadsettings', 'mod_reader'));
                $output .= $this->category_list($downloader);
                $output .= $this->course_list($downloader);
                $output .= $this->section_list($downloader);
            }

            $output .= $this->form_end();

        } else if (get_config('mod_reader', 'serverurl') && get_config('mod_reader', 'serverusername')) {

            // no items to download - probably internet connection has been lost
            $output .= $this->heading(get_string('nodownloaditems', 'mod_reader'), '3');

        } else {

            // no items to download - probably because remote settings have not been set up
            $link = new moodle_url('/admin/settings.php', array('section' => 'modsettingreader'));
            $link = html_writer::link($link, get_string('settings'));
            $output .= html_writer::tag('p', get_string('remotesitenotaccessible', 'mod_reader'));
            $output .= html_writer::tag('p', get_string('definelogindetails', 'mod_reader', $link));
        }

        return $output;
    }

    /**
     * form_js_start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_js_start() {
        $js = '';
        $js .= $this->ajax_request_js();
        $js .= $this->check_boxes_js();
        $js .= $this->showhide_js_start();
        return $js;
    }

    /**
     * ajax_request_js
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function ajax_request_js() {
        global $CFG;
        $js = '';

        static $done = false;
        if ($done==false) {
            $done = true;

            $js .= '<script type="text/javascript">'."\n";
            $js .= "//<![CDATA[\n";


            $js .= "function RDR_request(url, targetids, callback) {\n";
            $js .= "    url = url.replace(new RegExp('&amp;', 'g'), '&');\n";

            $js .= "    if (targetids) {\n";
            $js .= "        if (typeof(targetids)=='string') {\n";
            $js .= "            targetids = targetids.split(',');\n";
            $js .= "        }\n";
            $js .= "        var i_max = targetids.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            var obj = document.getElementById(targetids[i]);\n";
            $js .= "            if (obj) {\n";
            $js .= "                obj.innerHTML = (i ? '' : '".'<img src="'.$CFG->wwwroot.'/pix/i/ajaxloader.gif" alt="loading ..." />'."');\n";
            $js .= "                obj = null;\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";

            $js .= "    window.RDR_xmlhttp = false;\n";
            $js .= "    if (window.XMLHttpRequest) {\n"; // modern browser (incl. IE7+)
            $js .= "        RDR_xmlhttp = new XMLHttpRequest();\n";
            $js .= "    } else if (window.ActiveXObject) {\n"; // IE6, IE5
            $js .= "        RDR_xmlhttp = new ActiveXObject('Microsoft.XMLHTTP');\n";
            $js .= "    }\n";

            $js .= "    if (RDR_xmlhttp) {\n";
            $js .= "        if (callback) {\n";
            $js .= "            RDR_xmlhttp.onreadystatechange = eval(callback);\n";
            $js .= "        } else {\n";
            $js .= "            RDR_xmlhttp.onreadystatechange = function() {\n";
            $js .= "                RDR_response(url, targetids);\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "        RDR_xmlhttp.open('GET', url, true);\n";
            $js .= "        RDR_xmlhttp.send(null);\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function RDR_response(url, targetids) {\n";
            $js .= "    if (RDR_xmlhttp.readyState==4) {\n";

            $js .= "        if (typeof(targetids)=='string') {\n";
            $js .= "            targetids = targetids.split(',');\n";
            $js .= "        }\n";


            $js .= "        if (RDR_xmlhttp.status==200) {\n";
            $js .= "            var response = RDR_parseJSON(RDR_xmlhttp.responseText);\n";
            $js .= "        } else {\n";
            $js .= "            var response = 'Error: ' + RDR_xmlhttp.status;\n";
            $js .= "        }\n";

            $js .= "        if (typeof(response)=='string') {\n";
            $js .= "            if (targetids) {\n";
            $js .= "                var i_max = targetids.length;\n";
            $js .= "                for (var i=0; i<i_max; i++) {\n";
            $js .= "                    var obj = document.getElementById(targetids[i]);\n";
            $js .= "                    if (obj) {\n";
            $js .= "                        obj.innerHTML = response;\n";
            $js .= "                    }\n";
            $js .= "                    obj = null;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "        } else {\n";
            $js .= "            var id = '';\n";
            $js .= "            for (id in response) {\n";
            $js .= "                var obj = document.getElementById(id);\n";
            $js .= "                if (obj) {\n";
            $js .= "                    var tmp = document.createDocumentFragment();\n";
            $js .= "                    var div = document.createElement('DIV');\n";
            $js .= "                    div.innerHTML = response[id];\n";
            $js .= "                    var child = null;\n";
            $js .= "                    while (child = div.firstChild) {\n";
            $js .= "                        tmp.appendChild(child)\n";
            $js .= "                    }\n";
            $js .= "                    obj.parentNode.replaceChild(tmp, obj)\n";
            $js .= "                    div= null;\n";
            $js .= "                    tmp= null;\n";
            $js .= "                }\n";
            $js .= "                obj = null;\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "        RDR_showhide_textboxes();\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function RDR_showhide_textboxes(buttonid) {\n";
            $js .= "    if (buttonid==null) {\n";
            $js .= "        var items= new Array('category', 'course', 'section');\n";
            $js .= "    } else {\n";
            $js .= "        var items= new Array(buttonid.replace('button', '').replace('target', ''));\n";
            $js .= "        var obj = document.getElementById(buttonid);\n";
            $js .= "        if (obj) {\n";
            $js .= "            obj.style.display = 'none';\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    for (var i in items) {\n";
            $js .= "        var obj = document.getElementById('menutarget' + items[i] + 'type');\n";
            $js .= "        if (obj) {\n";
            $js .= "            var is_default = (obj.options[obj.selectedIndex].value==0);\n";
            $js .= "            var is_new = (obj.options[obj.selectedIndex].value==4);\n";
            $js .= "        } else {\n";
            $js .= "            var is_default = false;\n";
            $js .= "            var is_new = false;\n";
            $js .= "        }\n";
            $js .= "        var obj = document.getElementById('menutarget' + items[i] + 'id');\n";
            $js .= "        if (obj) {\n";
            $js .= "            obj.style.display = (is_new ? 'none' : '');\n";
            $js .= "        }\n";
            $js .= "        var obj = document.getElementById('menutarget' + items[i] + 'num');\n";
            $js .= "        if (obj) {\n";
            $js .= "            obj.style.display = ((is_new || is_default) ? 'none' : '');\n";
            $js .= "        }\n";
            $js .= "        var obj = document.getElementById('texttarget' + items[i] + 'text');\n";
            $js .= "        if (obj) {\n";
            $js .= "            obj.style.display = (is_new ? '' : 'none');\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function RDR_parseJSON(str) {\n";
            $js .= "    if (typeof str !== 'string' || ! str) {\n";
            $js .= "        return null;\n";
            $js .= "    }\n";
            $js .= "    str = str.replace(/^\s+/, '').replace(/\s+\$/, '');\n"; // for IE
            $js .= "    if (window.JSON && window.JSON.parse) {\n";
            $js .= "        return window.JSON.parse(str);\n";
            $js .= "    }\n";
            //$js .= "    var validchars  = new RegExp('^[\],:{}\s]*\$');\n";
            //$js .= "    var validescape = new RegExp('\\(?:[\"\\\/bfnrt]|u[0-9a-fA-F]{4})', 'g');\n";
            //$js .= "    var validtokens = new RegExp('\"[^\"\\\n\r]*\"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?', 'g');\n";
            //$js .= "    var validbraces = new RegExp('(?:^|:|,)(?:\s*\[)+', 'g');\n";
            //$js .= "    if (validchars.test(str.replace(validescape, '@').replace(validtokens, ']').replace(validbraces, ''))) {\n";
            //$js .= "        return (new Function('return ' + str))();\n"; // =eval(str)
            //$js .= "    }\n";
            $js .= "    return 'Invalid JSON: ' + str;\n"; // shouldn't happen !!
            $js .= "}\n";

            $js .= "function RDR_set_location_from_select(obj) {\n";
            $js .= "    var name = obj.name;\n";
            $js .= "    var href = location.href;\n";
            $js .= "    href = href.replace(new RegExp('&' + name + '=[0-9]+'), '');\n";
            $js .= "    href = href + '&' + name + '=' + obj.options[obj.selectedIndex].value;\n";
            $js .= "    location.assign(href);\n"; // simulate GET form submit
            $js .= "}\n";

            $js .= "function RDR_get_id() {\n";
            $js .= "    return location.href.replace(new RegExp('^.*?id=([0-9]+).*\$'), '\$1');\n";
            $js .= "}\n";

            $js .= "function RDR_get_wwwroot() {\n";
            $js .= "    return location.href.replace(new RegExp('^(.*)/mod/reader/.*\$'), '\$1');\n";
            $js .= "}\n";

            $js .= "function RDR_get_value(id, is_integer) {\n";
            $js .= "    var obj = document.getElementById(id);\n";
            $js .= "    if (obj==null || obj.type==null) {\n";
            $js .= "        return ;\n";
            $js .= "    }\n";
            $js .= "    var v = '';\n";
            $js .= "    switch (obj.type) {\n";
            $js .= "        case 'text':\n";
            $js .= "        case 'textarea':\n";
            $js .= "        case 'password':\n";
            $js .= "        case 'hidden':\n";
            $js .= "            v = obj.value;\n";
            $js .= "            break;\n";
            $js .= "        case 'radio':\n";
            $js .= "        case 'checkbox':\n";
            $js .= "            if (obj.checked) {\n";
            $js .= "                v = obj.value;\n";
            $js .= "            }\n";
            $js .= "            break;\n";
            $js .= "        case 'select-one':\n";
            $js .= "        case 'select-multiple':\n";
            $js .= "            var i_max = obj.options.length;\n";
            $js .= "            for (var i=0; i<i_max; i++) {\n";
            $js .= "                if (obj.options[i].selected) {\n";
            $js .= "                    v += (v=='' ? '' : ',') + obj.options[i].value;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "            break;\n";
            $js .= "        case 'button':\n";
            $js .= "        case 'reset':\n";
            $js .= "        case 'submit':\n";
            $js .= "            break;\n";
            $js .= "        default:\n";
            $js .= "            // radio or checkbox groups\n";
            $js .= "            var i_max = obj.length || 0;\n";
            $js .= "            for (var i=0; i<i_max; i++) {\n";
            $js .= "                if (obj[i].checked) {\n";
            $js .= "                    v += (v=='' ? '' : ',') + obj[i].value;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "    }\n";
            $js .= "    if (is_integer) {\n";
            $js .= "        return parseInt(v);\n";
            $js .= "    } else {\n";
            $js .= "        return encodeURIComponent(v);\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function RDR_set_menuitems(obj, action) {\n";
            $js .= "    var items = new Array();\n";

            $js .= "    items['id'] = RDR_get_id();\n";
            $js .= "    items['action'] = action;\n";

            $js .= "    items['mode'] = RDR_get_value('menumode', true);\n";
            $js .= "    items['type'] = RDR_get_value('menutype', true);\n";

            $js .= "    items['targetcategorytype'] = RDR_get_value('menutargetcategorytype', true);\n";
            $js .= "    items['targetcategoryid']   = RDR_get_value('menutargetcategoryid',   true);\n";
            $js .= "    items['targetcategorytext'] = RDR_get_value('texttargetcategorytext', false);\n";

            $js .= "    items['targetcoursetype']   = RDR_get_value('menutargetcoursetype',   true);\n";
            $js .= "    items['targetcourseid']     = RDR_get_value('menutargetcourseid',     true);\n";
            $js .= "    items['targetcoursetext']   = RDR_get_value('texttargetcoursetext',   false);\n";

            $js .= "    items['targetsectiontype']  = RDR_get_value('menutargetsectiontype',  true);\n";
            $js .= "    items['targetsectionnum']   = RDR_get_value('menutargetsectionnum',   true);\n";
            $js .= "    items['targetsectiontext']  = RDR_get_value('texttargetsectiontext',  false);\n";

            $js .= "    var url = RDR_get_wwwroot() + '/mod/reader/admin/books/download/ajax.js.php';\n";
            $js .= "    var amp = '?';\n";
            $js .= "    for (var i in items) {\n";
            $js .= "        if (items[i]) {\n";
            $js .= "            url += amp + i + '=' + items[i];\n";
            $js .= "            amp = '&';\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    RDR_request(url);\n"; // if (confirm(url))
            $js .= "}\n";

            $js .= "//]]>\n";
            $js .= '</script>'."\n";
        }
        return $js;
    }

    /**
     * check_boxes_js
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_boxes_js() {
        $js = '';

        static $done = false;
        if ($done==false) {
            $done = true;

            $js .= '<script type="text/javascript">'."\n";
            $js .= "//<![CDATA[\n";

            // detect shift-click (and set a global variable)
            $js .= "function reader_checkbox_onmousedown(evt) {\n";
            $js .= "    if (! evt) {\n";
            $js .= "        evt = window.event;\n"; // IE
            $js .= "    }\n";
            $js .= "    var target = null;\n";
            $js .= "    if (evt) {\n";
            $js .= "        if (evt.target) {\n";
            $js .= "            target = event.target;\n";
            $js .= "        } else if (evt.srcElement) {\n";
            $js .= "            target = event.srcElement;\n"; // IE
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    if (target && target.nodeType==3) {\n";
            $js .= "        target = target.parentNode;\n"; // Safari
            $js .= "    }\n";
            $js .= "    if (target && target.tagName.toUpperCase()=='INPUT' && target.type=='checkbox') {\n";
            $js .= "        if (window.reader_checkbox_id1 && evt.shiftKey) {\n";
            $js .= "            window.reader_checkbox_id2 = target.id;\n";
            $js .= "        } else {\n";
            $js .= "            window.reader_checkbox_id1 = target.id;\n";
            $js .= "            window.reader_checkbox_id2 = '';\n";
            $js .= "        }\n";
            $js .= "    } else {\n";
            $js .= "        window.reader_checkbox_id1 = '';\n";
            $js .= "        window.reader_checkbox_id2 = '';\n";
            $js .= "    }\n";
            $js .= "}\n";

            // handle the onchange event for checkboxes
            // if a second checkbox is selected with a shift-click, then
            // all the checkboxes in between will be toggled on or off
            // a normal mouse click will select the checkbox and any checkboxes in sublists
            $js .= "function reader_checkbox_onchange(checkbox) {\n";
            $js .= "    if (window.reader_checkbox_id1 && window.reader_checkbox_id2) {\n";
            $js .= "        var id1 = reader_checkbox_id1;\n";
            $js .= "        var id2 = reader_checkbox_id2;\n";
            $js .= "        window.reader_checkbox_id1 = id2;\n";
            $js .= "        window.reader_checkbox_id2 = '';\n";
            $js .= "        reader_checkbox_toggle_range(id1, id2, checkbox.checked);\n";
            $js .= "    } else {\n";
            $js .= "        var obj = checkbox.parentNode.getElementsByTagName('input');\n";
            $js .= "        if (obj) {\n";
            $js .= "            var i_max = obj.length;\n";
            $js .= "            for (var i=0; i<i_max; i++) {\n";
            $js .= "                if (obj[i].type=='checkbox') {\n";
            $js .= "                    obj[i].checked = checkbox.checked;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "            reader_checkbox_onchange_parent(checkbox.parentNode.parentNode);\n";
            $js .= "        }\n";
            $js .= "        obj = null;\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function reader_checkbox_onchange_parent(ul) {\n";
            $js .= "    if (ul && ul.childNodes) {\n";
            $js .= "        var count = 0;\n";
            $js .= "        var count_checked = 0;\n";
            $js .= "        var i_max = ul.childNodes.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            var checkbox = reader_node(ul.childNodes[i].firstChild, 'INPUT', 'checkbox');\n";
            $js .= "            if (checkbox) {\n";
            $js .= "                count++;\n";
            $js .= "                if (checkbox.checked) {\n";
            $js .= "                    count_checked++;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "        var checkbox = reader_node(ul.parentNode.firstChild, 'INPUT', 'checkbox');\n";
            $js .= "        if (checkbox) {\n";
            $js .= "            var checked = (count > 0 && count==count_checked);\n";
            $js .= "            if (checked != checkbox.checked) {\n";
            $js .= "                checkbox.checked = checked;\n";
            $js .= "                reader_checkbox_onchange_parent(ul.parentNode.parentNode)\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            // check this DOM node obj(ject) and all its next siblings to find a node
            // of the required tagName, tagType, tagClassName, and DOM nodeType
            $js .= "function reader_node(obj, tagName, tagType, tagClassName, nodeType) {\n";
            $js .= "    if (obj==null) {\n";
            $js .= "        return null;\n";
            $js .= "    }\n";
            $js .= "    if (tagName) {\n";
            $js .= "        tagName = tagName.toLowerCase();\n";
            $js .= "    }\n";
            $js .= "    if (tagType) {\n";
            $js .= "        tagType = tagType.toLowerCase();\n";
            $js .= "    }\n";
            $js .= "    if (nodeType==null) {\n";
            $js .= "        nodeType = 1;\n"; // 1=elementNode 3=textNode
            $js .= "    } else if (nodeType==3) {\n";
            $js .= "        tagName = null;\n";
            $js .= "        tagType = null;\n";
            $js .= "        tagClassName = null;\n";
            $js .= "    }\n";
            $js .= "    while (obj) {\n";
            $js .= "        if (obj.nodeType==nodeType) {\n";
            $js .= "            if (tagName==null || tagName==obj.tagName.toLowerCase()) {\n";
            $js .= "                if (tagType==null || tagType==obj.type.toLowerCase()) {\n";
            $js .= "                    if (tagClassName==null || tagClassName==obj.getAttribute(css_class_attribute())) {\n";
            $js .= "                       return obj;\n";
            $js .= "                    }\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "        obj = obj.nextSibling;\n";
            $js .= "    }\n";
            $js .= "    return null;\n";
            $js .= "}\n";

            $js .= "function reader_checkbox_toggle_range(id1, id2, checked) {\n";
            $js .= "    if (id1==null || id2==null) {\n";
            $js .= "        return;\n";
            $js .= "    }\n";
            $js .= "    switch (true) {\n";
            $js .= "        case (id1.indexOf('id_itemids_')==0):\n";
            $js .= "        case (id2.indexOf('id_itemids_')==0): var targetid = 'id_itemids_'; break;\n";
            $js .= "        case (id1.indexOf('id_levels_')==0):\n";
            $js .= "        case (id2.indexOf('id_levels_')==0): var targetid = 'id_levels_'; break;\n";
            $js .= "        case (id1.indexOf('id_publishers_')==0):\n";
            $js .= "        case (id2.indexOf('id_publishers_')==0): var targetid = 'id_publishers_'; break;\n";
            $js .= "        case (id1.indexOf('id_remotesites_')==0):\n";
            $js .= "        case (id2.indexOf('id_remotesites_')==0): var targetid = 'id_remotesites_'; break;\n";
            $js .= "        default: return;\n"; // shouldn't happen !!
            $js .= "    }\n";
            $js .= "    var targetid = new RegExp('^' + targetid);\n";
            $js .= "    var obj = document.getElementsByTagName('input');\n";
            $js .= "    if (obj) {\n";
            $js .= "        var i_max = obj.length;\n";
            $js .= "        for (var i=0, m=0; (i<i_max && m<2); i++) {\n";
            $js .= "            if (obj[i].type=='checkbox') {\n";
            $js .= "                var match = (obj[i].id==id1 || obj[i].id==id2);\n";
            $js .= "                if (match || m) {\n";
            $js .= "                    if (obj[i].id.match(targetid)) {\n";
            $js .= "                        obj[i].checked = checked;\n";
            $js .= "                        if (obj[i].onchange) {\n";
            $js .= "                            obj[i].onchange(obj[i]);\n";
            $js .= "                        }\n";
            $js .= "                    }\n";
            $js .= "                }\n";
            $js .= "                if (match) {\n";
            $js .= "                    m += 1;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    obj = null;\n";
            $js .= "}\n";

            $js .= "//]]>\n";
            $js .= '</script>'."\n";
        }
        return $js;
    }

    /**
     * showhide_js_start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_js_start() {
        $js = '';

        static $done = false;
        if ($done==false) {
            $done = true;

            $js .= '<script type="text/javascript">'."\n";
            $js .= "//<![CDATA[\n";

            $js .= "function css_class_attribute() {\n";
            $js .= "    if (window.cssClassAttribute==null) {\n";
            $js .= "        var m = navigator.userAgent.match(new RegExp('MSIE (\\d+)'));\n";
            $js .= "        if (m && m[1]<=7) {\n";
            $js .= "            // IE7 and earlier\n";
            $js .= "            window.cssClassAttribute = 'className';\n";
            $js .= "        } else {\n";
            $js .= "            window.cssClassAttribute = 'class';\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    return window.cssClassAttribute;\n";
            $js .= "}\n";

            $js .= "function remove_child_nodes(obj) {\n";
            $js .= "    while (obj.firstChild) {\n";
            $js .= "        obj.removeChild(obj.firstChild);\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function showhide_parent_lists(obj, display) {\n";
            $js .= "    var p = obj.parentNode;\n";
            $js .= "    while (p) {\n";
            $js .= "        if (p.nodeType==1 && (p.nodeName.toUpperCase()=='UL' || p.nodeName.toUpperCase()=='OL')) {\n";
            $js .= "            p.style.display = display;\n";
            $js .= "        }\n";
            $js .= "        p = p.parentNode;\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function match_classname(obj, targetClassNames) {\n";
            $js .= "    if (obj==null || obj.getAttribute==null) {\n";
            $js .= "        return false;\n";
            $js .= "    }\n";
            $js .= "    var myClassName = obj.getAttribute(css_class_attribute());\n";
            $js .= "    if (myClassName) {\n";
            $js .= "        if (typeof(targetClassNames)=='string') {\n";
            $js .= "           targetClassNames = new Array(targetClassNames);\n";
            $js .= "        }\n";
            $js .= "        var i_max = targetClassNames.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            if (myClassName.indexOf(targetClassNames[i]) >= 0) {\n";
            $js .= "                return true;\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    return false;\n";
            $js .= "}\n";

            $js .= "function showhide_list(img, showhide, targetClassName) {\n";
            $js .= "    if (typeof(showhide)=='undefined') {\n";
            $js .= "       showhide = 0;\n"; // -1=hide, 0=toggle, 1=show
            $js .= "    }\n";
            $js .= "    if (typeof(targetClassName)=='undefined') {\n";
            $js .= "       targetClassName = '';\n";
            $js .= "    }\n";
            $js .= "    var obj = reader_node(img.nextSibling, 'OL');\n";
            $js .= "    if (obj) {\n";
            $js .= "        var myClassName = obj.getAttribute(css_class_attribute());\n";
            $js .= "        if (targetClassName=='' || (myClassName && myClassName.match(new RegExp(targetClassName)))) {\n";
            $js .= "            if (showhide==1 || (showhide==0 && obj.style.display=='none')) {\n";
            $js .= "                obj.style.display = '';\n";
            $js .= "                var pix = 'minus';\n";
            $js .= "            } else {\n";
            $js .= "                obj.style.display = 'none';\n";
            $js .= "                var pix = 'plus';\n";
            $js .= "            }\n";
            $js .= "            img.alt = 'switch_' + pix;\n";
            $js .= "            img.src = img.src.replace(new RegExp('switch_[a-z]+'), 'switch_' + pix);\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function showhide_lists(showhide, targetClassName, requireElement, checked) {\n";

            $js .= "    var requireCheckbox = (requireElement && (requireElement & 1));\n";
            $js .= "    var requireNewImg   = (requireElement && (requireElement & 2));\n";

            $js .= "    switch (showhide) {\n";
            $js .= "        case -1: var targetImgName = 'minus';        break;\n"; // hide
            $js .= "        case  0: var targetImgName = '(minus|plus)'; break;\n"; // toggle
            $js .= "        case  1: var targetImgName = 'plus';         break;\n"; // show
            $js .= "        default: return false;\n";
            $js .= "    }\n";

            $js .= "    var targetImgName = new RegExp('switch_'+targetImgName);\n";
            $js .= "    var img = document.getElementsByTagName('img');\n";
            $js .= "    if (img) {\n";

            $js .= "        var i_max = img.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";

            $js .= "            var ok = true;\n";
            $js .= "            if (requireNewImg) {\n";
            $js .= "                var obj = reader_node(img[i].parentNode.firstChild, 'IMG', null, 'update');\n";
            $js .= "                if (obj==null) {\n";
            $js .= "                    ok = false;\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "            if (requireCheckbox) {\n";
            $js .= "                var obj = reader_node(img[i].parentNode.firstChild, 'INPUT', 'checkbox');\n";
            $js .= "                if (obj==null) {\n";
            $js .= "                    ok = false;\n";
            $js .= "                }\n";
            $js .= "            }\n";

            $js .= "            if (ok && img[i].src && img[i].src.match(targetImgName)) {\n";
            $js .= "                if (requireCheckbox && typeof(checked)=='number') {\n";
            $js .= "                    obj.checked = checked;\n";
            $js .= "                }\n";
            $js .= "                showhide_list(img[i], showhide, targetClassName);\n";
            $js .= "            }\n";
            $js .= "            obj = null;\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function clear_search_results() {\n";
            $js .= "    showhide_lists(-1);\n";

            $js .= "    var obj = document.getElementsByTagName('SPAN');\n";
            $js .= "    if (obj) {\n";
            $js .= "        var i_max = obj.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            if (match_classname(obj[i], 'itemname')) {\n";
            $js .= "                if (obj[i].textContent) {\n";
            $js .= "                    var txt = obj[i].textContent;\n";
            $js .= "                } else {\n";
            $js .= "                    var txt = obj[i].innerText;\n"; // IE
            $js .= "                }\n";
            $js .= "                if (txt != obj[i].innerHTML) {\n";
            $js .= "                    remove_child_nodes(obj[i]);\n";
            $js .= "                    obj[i].appendChild(document.createTextNode(txt));\n";
            $js .= "                }\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";

            $js .= "    var obj = document.getElementsByTagName('UL');\n";
            $js .= "    if (obj) {\n";
            $js .= "        var names = new Array('publishers', 'levels', 'items');\n";
            $js .= "        var i_max = obj.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            if (match_classname(obj[i], names)) {\n";
            $js .= "                obj[i].style.display = 'none';\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "function select_updated(imgClassName, parentClassName) {\n";
            $js .= "    clear_search_results();\n";

            $js .= "    var img = document.getElementsByTagName('IMG');\n";
            $js .= "    if (img) {\n";
            $js .= "        var i_max = img.length;\n";
            $js .= "        for (var i=0; i<i_max; i++) {\n";
            $js .= "            if (match_classname(img[i], imgClassName) && match_classname(img[i].parentNode, parentClassName)) {\n";
            $js .= "                var obj = reader_node(img[i].parentNode.firstChild, 'INPUT', 'checkbox');\n";
            $js .= "                if (obj) {\n";
            $js .= "                    obj.checked = 1;\n";
            $js .= "                    if (obj.onchange) {\n";
            $js .= "                        obj.onchange();\n";
            $js .= "                    }\n";
            $js .= "                    showhide_parent_lists(obj, '');\n"; // show
            $js .= "                }\n";
            $js .= "                obj = null;\n";
            $js .= "            }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "    img = null;\n";
            $js .= "}\n";

            $js .= "function search_itemnames(btn) {\n";
            $js .= "    clear_search_results();\n";

            $js .= "    if (btn==null || btn.form==null || btn.form.searchtext==null) {\n";
            $js .= "        return false;\n";
            $js .= "    }\n";

            $js .= "    var searchtext = btn.form.searchtext.value.toLowerCase();\n";
            $js .= "    if (searchtext=='') {\n";
            $js .= "        return false;\n";
            $js .= "    }\n";

            $js .= "    var obj = document.getElementsByTagName('SPAN');\n";
            $js .= "    if (obj==null) {\n";
            $js .= "        return false;\n";
            $js .= "    }\n";

            $js .= "    var firstmatch = true;\n";
            $js .= "    var i_max = obj.length;\n";
            $js .= "    for (var i=0; i<i_max; i++) {\n";

            $js .= "        if (match_classname(obj[i], 'itemname')==false) {\n";
            $js .= "            continue;\n";
            $js .= "        }\n";

            $js .= "        if (obj[i].textContent) {\n";
            $js .= "            var txt = obj[i].textContent;\n";
            $js .= "        } else {\n";
            $js .= "            var txt = obj[i].innerText;\n"; // IE
            $js .= "        }\n";
            $js .= "        var pos = txt.toLowerCase().indexOf(searchtext);\n";
            $js .= "        if (pos < 0) {\n";
            $js .= "            continue;\n";
            $js .= "        }\n";

            $js .= "        var string1 = txt.substr(0, pos);\n";
            $js .= "        var string2 = txt.substr(pos, searchtext.length);\n";
            $js .= "        var string3 = txt.substr(pos + searchtext.length);\n";

            $js .= "        var span = document.createElement('SPAN');\n";
            $js .= "        span.appendChild(document.createTextNode(string2));\n";
            $js .= "        span.setAttribute(css_class_attribute(), 'matchedtext');\n";

            $js .= "        remove_child_nodes(obj[i]);\n";

            $js .= "        obj[i].appendChild(document.createTextNode(string1));\n";
            $js .= "        obj[i].appendChild(span);\n";
            $js .= "        obj[i].appendChild(document.createTextNode(string3));\n";

            $js .= "        showhide_parent_lists(obj[i], '');\n"; // show

            $js .= "        if (firstmatch) {\n";
            $js .= "            try {\n";
            $js .= "                obj[i].parentNode.parentNode.firstChild.focus();\n";
            $js .= "                firstmatch = false;\n";
            $js .= "            } catch (err) { }\n";
            $js .= "        }\n";
            $js .= "    }\n";
            $js .= "}\n";

            $js .= "//]]>\n";
            $js .= '</script>'."\n";
        }
        return $js;
    }

    /**
     * form_js_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_js_end() {
        $js = '';
        $js .= $this->showhide_js_end();
        return $js;
    }

    /**
     * showhide_js_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_js_end() {
        $js = '';
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "showhide_lists(-1);\n"; // hide all
        $js .= "showhide_lists(1, 'publishers');\n";
        $js .= "//]]>\n";
        $js .= '</script>'."\n";
        return $js;
    }

    /**
     * form_start
     *
     * @param xxx $id
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_start($tab, $mode, $type) {
        $url = $this->page->url;
        $params = $url->params();
        $params['tab'] = $tab;
        $params['mode'] = $mode;
        $params['type'] = $type;
        $url->params($params);

        $output = '';
        $output .= $this->form_js_start();
        $output .= html_writer::start_tag('form', array('action' => $url, 'method' => 'post'));
        $output .= html_writer::start_tag('div');
        return $output;
    }

    /**
     * form_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_end() {
        $output = '';
        $params = array('type'  => 'submit',
                        'value' => get_string('download'),
                        'class' => 'downloadbutton');
        $output .= html_writer::empty_tag('input', $params);
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');
        $output .= $this->form_js_end();
        return $output;
    }

    /**
     * downloadmode_menu
     *
     * @param string $downloadmode "normal" or "repair"
     * @return xxx
     * @todo Finish documenting this function
     */
    public function downloadmode_menu($downloadmode) {
        $name = 'downloadmode';
        $label = get_string($name, 'mod_reader');
        $onchange = 'RDR_set_location_from_select(this)';
        $downloadmodes = array(reader_downloader::NORMAL_MODE => get_string('normalmode', 'mod_reader'),
                               reader_downloader::REPAIR_MODE => get_string('repairmode', 'mod_reader'));
        $downloadmodes = html_writer::select($downloadmodes, $name, $downloadmode, null, array('onchange' => $onchange));
        return $this->formitem($name, $label, $downloadmodes, $name);
    }

    /**
     * dowloadtype_menu
     *
     * @param integer $type reader_downloader::BOOKS_xxx_QUIZZES
     * @return xxx
     * @todo Finish documenting this function
     */
    public function downloadtype_menu($type) {
        $name = 'type';
        $label = get_string($name, 'mod_reader');
        $onchange = 'RDR_set_location_from_select(this)';
        $types = $this->available_booktypes();
        $types = html_writer::select($types, 'type', $type, null, array('onchange' => $onchange));
        return $this->formitem($name, $label, $types, $name);
    }

    /**
     * search_box
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function search_box() {
        $label = get_string('search');
        $input = html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchtext'));
        $onclick = 'search_itemnames(this); return false;';
        return $this->formitem('search', $label, $input, 'search', $onclick);
    }

    /**
     * showhide_menu
     *
     * @param integer $count
     * @param integer $updatecount
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_menu($count, $updatecount) {
        $menu = array();

        if ($count) {
            // Publishers
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); return false;';
            $menu[] = html_writer::tag('a', get_string('publishers', 'mod_reader'), array('onclick' => $onclick));

            // Levels
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); return false;';
            $menu[] = html_writer::tag('a', get_string('levels', 'mod_reader'), array('onclick' => $onclick));

            // Books
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); showhide_lists(1, "items"); return false;';
            $menu[] = html_writer::tag('a', get_string('books', 'mod_reader'), array('onclick' => $onclick));

            // Downloads
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 1); showhide_lists(1, "levels", 1); showhide_lists(1, "items", 1); return false;';
            $menu[] = html_writer::tag('a', get_string('downloads', 'mod_reader'), array('onclick' => $onclick));
        }

        if ($updatecount) {
            // Updates
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 2); showhide_lists(1, "levels", 2); showhide_lists(1, "items", 2); return false;';
            $menu[] = html_writer::tag('a', get_string('updates', 'mod_reader')." ($updatecount)", array('onclick' => $onclick));
        }

        if ($menu = implode(' / ', $menu)) {
            return $this->formitem('show', get_string('show'), $menu, '', 'show');
        } else {
            return ''; // there are currently no downloadable or updatable items
        }
    }

    /**
     * select_menu
     *
     * @param integer $newcount
     * @param integer $updatecount
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_menu($newcount, $updatecount) {
        $menu = array();

        if ($newcount) {
            // All
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 1, 1); return false;';
            $menu[] = html_writer::tag('a', get_string('all'), array('onclick' => $onclick));

            // None
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 1, 0); return false;';
            $menu[] = html_writer::tag('a', get_string('none'), array('onclick' => $onclick));
        }

        if ($updatecount) {
            // Updates
            $onclick = 'select_updated("update", "item"); return false;';
            $menu[] = html_writer::tag('a', get_string('updates', 'mod_reader')." ($updatecount)", array('onclick' => $onclick));
        }

        if ($menu = implode(' / ', $menu)) {
            return $this->formitem('select', get_string('select'), $menu, '', 'select');
        } else {
            return ''; // there are currently no downloadable or updatable items
        }
    }

    /**
     * available_lists
     *
     * @param xxx $downloader
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_lists($downloader) {
        $output = '';
        foreach ($downloader->remotesites as $r => $remotesite) {
            $output .= $this->available_list($remotesite, $downloader->available[$r], $downloader->downloaded[$r]);
        }
        return $output;
    }

    /**
     * available_list
     *
     * @param xxx $remotesite
     * @param xxx $available
     * @param xxx $downloaded
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list($remotesite, $available, $downloaded) {
        $output = '';

        $started_remotesites = false;

        $remotesitename = $remotesite->sitename;
        if ($remotesitename=='') {
            $showremotesites = false;
        } else {
            $showremotesites = true;
        }

        // loop through available items to create selectable list
        $i = 0;
        $started_publishers = false;
        foreach ($available->items as $publishername => $levels) {
            $i++;

            if (count($available->items)==1 && $publishername=='') {
                $showpublishers = false;
            } else {
                $showpublishers = true;
            }

            $ii = 0;
            $started_levels = false;
            foreach ($levels->items as $levelname => $items) {
                $ii++;

                if (count($levels->items)==1 && ($levelname=='' || $levelname=='-' || $levelname=='--' || $levelname=='No Level')) {
                    $showlevels = false;
                } else {
                    $showlevels = true;
                }

                if (empty($items->items)) {
                    $items->items = array();
                }

                $iii = 0;
                $started_items = false;
                foreach ($items->items as $itemname => $item) {
                    $iii++;

                    if ($itemname) {

                        if ($started_remotesites==false) {
                            $started_remotesites = true;
                            if ($showremotesites) {
                                $output .= html_writer::start_tag('ul', array('class' => 'remotesites'));
                            }
                        }

                        if ($started_publishers==false) {
                            $started_publishers = true;
                            if ($showremotesites) {
                                $output .= html_writer::start_tag('li', array('class' => 'remotesite'));
                                $output .= $this->available_list_name('remotesites[]', 0, $remotesitename, 'remotesites', $available->count, $available->newcount, $available->updatecount);
                            }
                            if ($showpublishers) {
                                $output .= html_writer::start_tag('ul', array('class' => 'publishers'));
                            }
                        }

                        if ($started_levels==false) {
                            $started_levels = true;
                            if ($showpublishers) {
                                $output .= html_writer::start_tag('li', array('class' => 'publisher'));
                                $output .= $this->available_list_name('publishers[]', $i, $publishername, 'publishername', $levels->count, $levels->newcount, $levels->updatecount);
                            }
                            if ($showlevels) {
                                $output .= html_writer::start_tag('ul', array('class' => 'levels'));
                            }
                        }

                        if ($started_items==false) {
                            $started_items = true;
                            if ($showlevels) {
                                $output .= html_writer::start_tag('li', array('class' => 'level'));
                                $output .= $this->available_list_name('levels[]', $i.'_'.$ii, $levelname, 'levelname', $items->count, $items->newcount, $items->updatecount);
                            }
                            $output .= html_writer::start_tag('ul', array('class' => 'items'));
                        }

                        $output .= html_writer::start_tag('li', array('class' => 'item'));
                        if (! isset($downloaded->items[$publishername]->items[$levelname]->items[$itemname])) {
                            $output .= $this->available_list_name('itemids[]', $item->id, $itemname, 'itemname', 0, 1);
                        } else if ($downloaded->items[$publishername]->items[$levelname]->items[$itemname]->time < $item->time) {
                            $output .= $this->available_list_name('itemids[]', $item->id, $itemname, 'itemname', 0, 0, 0, $item->time);
                        } else {
                            $img = ' '.$this->downloaded_img();
                            $output .= html_writer::tag('span', $img, array('class' => 'downloadeditem'));
                            $output .= html_writer::tag('span', s($itemname), array('class' => 'itemname'));
                        }
                        $output .= html_writer::end_tag('li'); // finish item
                    }
                }
                if ($started_items) {
                    $output .= html_writer::end_tag('ul'); // finish items
                    if ($showlevels) {
                        $output .= html_writer::end_tag('li'); // finish level
                    }
                }
            }
            if ($started_levels) {
                if ($showlevels) {
                    $output .= html_writer::end_tag('ul'); // finish levels
                }
                if ($showpublishers) {
                    $output .= html_writer::end_tag('li'); // finish publisher
                }
            }
        }
        if ($started_publishers) {
            if ($showpublishers) {
                $output .= html_writer::end_tag('ul'); // finish publishers
            }
            if ($showremotesites) {
                $output .= html_writer::end_tag('li'); // finish remotesite
            }
        }
        if ($started_remotesites) {
            if ($showremotesites) {
                $output .= html_writer::end_tag('ul'); // finish remotesites
            }
        }

        return $output;
    }

    /**
     * available_list_name
     *
     * @param xxx $forcecheckbox
     * @param xxx $name
     * @param xxx $value
     * @param xxx $text
     * @param xxx $cssclass
     * @param xxx $count (optional, default=0)
     * @param xxx $newcount (optional, default=0)
     * @param xxx $updatecount (optional, default=0)
     * @param xxx $updatetime (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list_name($name, $value, $text, $cssclass, $count=0, $newcount=0, $updatecount=0, $updatetime=0) {
        $output = '';
        if ($newcount || $updatecount || $updatetime) {
            $id = str_replace('[]', '_'.$value, 'id_'.$name);
            $output .= html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => $id, 'name' => $name, 'value' => $value, 'onchange' => 'reader_checkbox_onchange(this)', 'onmousedown' => 'reader_checkbox_onmousedown()'));
            $output .= html_writer::start_tag('label', array('for' => $id));
        } else {
            $img = $this->downloaded_img();
            $output .= html_writer::tag('span', $img, array('class' => 'downloadeditems'));
        }
        $output .= html_writer::tag('span', s($text), array('class' => $cssclass));
        if ($count) {
            if ($newcount==$count) {
                $msg = get_string('dataallavailable', 'mod_reader', number_format($count));
            } else if ($newcount==0) {
                $msg = get_string('dataalldownloaded', 'mod_reader', number_format($count));
            } else {
                $a = (object)array('new' => number_format($newcount), 'all' => number_format($count));
                $msg = get_string('datasomeavailable', 'mod_reader', $a);
            }
            $output .= html_writer::tag('span', s(' - '.$msg), array('class' => 'itemcount'));
        }
        if ($newcount || $updatecount || $updatetime) {
            $output .= html_writer::end_tag('label');
        }
        if ($updatecount || $updatetime) {
            $output .= $this->available_new_img($updatecount, $updatetime);
        }
        if ($count) {
            $output .= $this->available_list_img();
        }
        return $output;
    }

    /**
     * downloaded_img
     *
     * @uses $CFG
     * @return xxx
     * @todo Finish documenting this function
     */
    public function downloaded_img() {
        global $CFG;
        static $img = null;
        if ($img==null) {
            switch (true) {
                case file_exists($CFG->dirroot.'/pix/i/grade_correct.png'):
                    // Moodle >= 2.4
                    $img = 'i/grade_correct';
                    break;
                case file_exists($CFG->dirroot.'/pix/i/tick_green_big.png'):
                    // Moodle 2.0 - 2.5
                    $img = 'i/tick_green_big';
                    break;
                default:
                    $img = ''; // shouldn't happen !!
            }
            if ($img=='') {
                $img = mod_reader::textlib('entities_to_utf8', '&#x2714;'); // Unicode tick ✔
                $img = html_writer::tag('span', $img, array('style' => 'color: #00FF00;')).' '; // green
            } else {
                $img = html_writer::empty_tag('img', array('src' => $this->pix_url($img), 'class' => 'icon', 'alt' => $img));
            }
        }
        return $img;
    }

    /**
     * available_list_img
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list_img() {
        $src = $this->pix_url('t/switch_minus');
        $img = html_writer::empty_tag('img', array('src' => $src, 'onclick' => 'showhide_list(this)', 'alt' => 'switch_minus'));
        return ' '.$img;
    }

    /**
     * available_new_img
     *
     * @param integer $updatecount (optional, default=0)
     * @param integer $updatetime (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_new_img($updatecount=0, $updatetime=0) {
        $src = $this->pix_url('i/new');
        if ($updatecount) {
            $str = get_string('updatesavailable', 'mod_reader', $updatecount);
            $onclick = '';
        } else if ($updatetime) {
            $str = get_string('updatedon', 'mod_reader', userdate($updatetime));
            $onclick = '';
        } else {
            $str = get_string('update'); // shouldn't happen !!
            $onclick = '';
        }
        $img = html_writer::empty_tag('img', array('src' => $src, 'alt' => $str, 'title' => $str, 'onclick' => $onclick, 'class' => 'update'));
        return ' '.$img;
    }

    /**
     * get_mycourses
     *
     * @param object $downloader
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_mycourses() {
        global $USER;

        $capability = 'moodle/course:manageactivities';
        if (has_capability($capability, reader_get_context(CONTEXT_SYSTEM))) {
            $courses = get_courses(); // system admin
        } else if (function_exists('enrol_get_users_courses')) {
            $courses = enrol_get_users_courses($USER->id);
        } else {
            // this is probably not necessary, because even Moodle 2.0 has "enrol_get_users_courses()"
            $access = (isset($USER->access) ? $USER->access : get_user_access_sitewide($USER->id));
            $courses = get_user_courses_bycap($USER->id, $capability, $access, true);
        }
        return $courses;
    }

    /**
     * category_list
     *
     * @param object $downloader
     * @return xxx
     * @todo Finish documenting this function
     */
    public function category_list($downloader) {
        global $DB;

        $categorytype = $downloader->get_course_categorytype();
        $categoryid = $downloader->get_course_categoryid();

        // to get all categories we could use:
        //    $categories = get_categories();
        // but it is better select only categories
        // containing courses relevant to this user,
        // so we derive categories from mycourses

        $categoryids = array();
        if ($is_admin = $downloader->can_manage_category()) {
            // system admin can see all categories
            $categoryids = $DB->get_records('course_categories', null, 'sortorder', 'id,sortorder');
            $categoryids = array_keys($categoryids);

        } else if ($courses = $this->get_mycourses()) {
            foreach ($courses as $course) {
                if ($course->category) {
                    $categoryids[$course->category] = true;
                }
            }
            unset($courses);

            $categoryids = array_keys($categoryids);

            // add ids of parent categories
            list($select, $params) = $DB->get_in_or_equal($categoryids);
            if ($paths = $DB->get_records_select('course_categories', "id $select", $params, '', 'id,path')) {
                $categoryids = '';
                foreach ($paths as $path) {
                    $categoryids .= $path->path;
                }
                $categoryids = explode('/', $categoryids);
                $categoryids = array_filter($categoryids); // remove blanks
                $categoryids = array_unique($categoryids); // remove duplicates
            }
        }

        $count_hidden = 0;
        $count_visible = 0;
        if (count($categoryids)) {
            list($select, $params) = $DB->get_in_or_equal($categoryids);
            $categoryids = array();
            if ($categories = $DB->get_records_select('course_categories', "id $select", $params, 'sortorder')) {
                foreach ($categories as $id => $category) {
                    if ($visible = intval($category->visible)) {
                        $count_visible++;
                    } else {
                        $count_hidden++;
                    }
                    switch ($categorytype) {
                        case reader_downloader::CATEGORYTYPE_DEFAULT: // 0
                            $keep = ($id == $downloader->defaultcategoryid);
                            break;
                        case reader_downloader::CATEGORYTYPE_HIDDEN:  // 1
                            $keep = ($visible == 0);
                            break;
                        case reader_downloader::CATEGORYTYPE_VISIBLE: // 2
                            $keep = ($visible == 1);
                            break;
                        case reader_downloader::CATEGORYTYPE_CURRENT: // 3
                            $keep = ($id == $downloader->course->category);
                            break;
                        default:
                            $keep = false; // shouldn't happen !!
                    }
                    if ($keep) {
                        if ($category->parent) {
                            $categories[$id]->name = $categories[$category->parent]->name.' / '.$categories[$id]->name;
                            $category->name = $categories[$id]->name;
                        }
                        $categoryids[$id] = $category->name;
                    }
                }
                unset($categories);
            }
        }

        $action = reader_downloader::ACTION_CATEGORYTEXT;
        $params = array('type' => 'text',
                        'name' => 'targetcategorytext',
                        'id'   => 'texttargetcategorytext',
                        'value' => $downloader->targetcategorytext,
                        'onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $categorytext = html_writer::empty_tag('input', $params);

        $action = reader_downloader::ACTION_CATEGORYTYPE;
        $params = array('onchange' => 'RDR_set_menuitems(this, '.$action.')');

        $categorytypes = array();
        if ($downloader->defaultcategoryid) {
            $categorytypes[reader_downloader::CATEGORYTYPE_DEFAULT] = get_string('default');
        }
        if ($downloader->course->category) {
            $categorytypes[reader_downloader::CATEGORYTYPE_CURRENT] = get_string('current', 'mod_reader');
        }
        if ($count_hidden) {
            $categorytypes[reader_downloader::CATEGORYTYPE_HIDDEN] = get_string('hidden', 'mod_reader');
        }
        if ($count_visible) {
            $categorytypes[reader_downloader::CATEGORYTYPE_VISIBLE] = get_string('visible');
        }
        if ($is_admin) {
            $categorytypes[reader_downloader::CATEGORYTYPE_NEW] = get_string('new');
        }
        $categorytypes = html_writer::select($categorytypes, 'targetcategorytype', $categorytype, null, $params);

        $action = reader_downloader::ACTION_CATEGORYID;
        $params = array('onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $categoryids = html_writer::select($categoryids, 'targetcategoryid', $categoryid, null, $params);

        $label = get_string('category');
        return $this->formitem('targetcategory', $label, $categorytypes.' '.$categoryids.' '.$categorytext, 'targetcategory');
    }

    /**
     * course_list
     *
     * @param object $downloader
     * @return xxx
     * @todo Finish documenting this function
     */
    public function course_list($downloader) {
        global $DB;

        $categorytype = $downloader->get_course_categorytype();
        $categoryid = $downloader->get_course_categoryid();
        $coursetype = $downloader->get_quiz_coursetype();
        $courseid = $downloader->get_quiz_courseid();

        if ($is_admin = $downloader->can_create_course($categoryid)) {
            // admin can see all courses
            $courses = $DB->get_records('course', array('category' => $categoryid), 'sortorder');
        } else {
            // teacher can only access certain categories
            $courses = $this->get_mycourses();
        }

        $count_hidden = 0;
        $count_visible = 0;
        $courseids = array();

        if ($courses) {
            foreach ($courses as $course) {
                if ($course->id==SITEID) {
                    continue;
                }
                if ($course->category==$categoryid) {
                    if ($visible = intval($course->visible)) {
                        $count_visible++;
                    } else {
                        $count_hidden++;
                    }
                    switch ($coursetype) {
                        case reader_downloader::COURSETYPE_DEFAULT: // 0
                            $keep = ($course->id == $downloader->defaultcourseid);
                            break;
                        case reader_downloader::COURSETYPE_HIDDEN:  // 1
                            $keep = ($visible == 0);
                            break;
                        case reader_downloader::COURSETYPE_VISIBLE: // 2
                            $keep = ($visible == 1);
                            break;
                        case reader_downloader::COURSETYPE_CURRENT: // 3
                            $keep = ($course->id == $downloader->reader->course);
                            break;
                        default:
                            $keep = false; // shouldn't happen !!
                    }
                    if ($keep) {
                        $courseids[$course->id] = reader_textlib('substr', $course->fullname, 0, 50);
                    } else {
                        unset($courseids[$course->id]);
                    }
                }
            }
        }
        unset($courses);

        $action = reader_downloader::ACTION_COURSEID;
        $params = array('onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $courseids = html_writer::select($courseids, 'targetcourseid', $courseid, null, $params);

        $action = reader_downloader::ACTION_COURSETEXT;
        $params = array('type' => 'text',
                        'name' => 'targetcoursetext',
                        'id'   => 'texttargetcoursetext',
                        'value' => $downloader->targetcoursetext,
                        'onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $coursetext = html_writer::empty_tag('input', $params);

        $action = reader_downloader::ACTION_COURSETYPE;
        $params = array('onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $coursetypes = array();
        if ($categoryid == $downloader->defaultcategoryid) {
            $coursetypes[reader_downloader::COURSETYPE_DEFAULT] = get_string('default');
        }
        if ($categoryid == $downloader->course->category) {
            $coursetypes[reader_downloader::COURSETYPE_CURRENT] = get_string('current', 'mod_reader');
        }
        if ($count_hidden) {
            $coursetypes[reader_downloader::COURSETYPE_HIDDEN] = get_string('hidden', 'mod_reader');
        }
        if ($count_visible) {
            $coursetypes[reader_downloader::COURSETYPE_VISIBLE] = get_string('visible');
        }
        if ($is_admin) {
            $coursetypes[reader_downloader::COURSETYPE_NEW] = get_string('new');
        }
        $coursetypes = html_writer::select($coursetypes, 'targetcoursetype', $coursetype, null, $params);

        $label = get_string('course'); // get_string('targetcourse', 'mod_reader');
        return $this->formitem('targetcourse', $label, $coursetypes.' '.$courseids.' '.$coursetext, 'targetcourse');
    }

    /**
     * section_list
     *
     * @param object $downloader
     * @return xxx
     * @todo Finish documenting this function
     */
    public function section_list($downloader) {
        global $DB;

        $categoryid  = $downloader->get_course_categoryid();
        $courseid    = $downloader->get_quiz_courseid();
        $sectionnum  = $downloader->get_quiz_sectionnum($courseid);
        $sectiontype = $downloader->get_quiz_sectiontype($courseid, $sectionnum);

        $select = "course = ? AND section > ? AND name NOT IN (?, ?)";
        $params = array($courseid, 0, 'Extra Points', '');

        if ($sectiontype==reader_downloader::SECTIONTYPE_LAST) {
            $sql = "SELECT MAX(section) FROM {course_sections} WHERE $select";
            $lastsectionnum = $DB->get_field_sql($sql, $params);
        } else {
            $lastsectionnum = 0;
        }

        $count_hidden = 0;
        $count_visible = 0;
        $sectionnums = array();
        if ($courseid) {
            if ($sections = $DB->get_records_select('course_sections', $select, $params, 'section')) {
                foreach ($sections as $section) {
                    if ($visible = intval($section->visible)) {
                        $count_visible++;
                    } else {
                        $count_hidden++;
                    }
                    switch ($sectiontype) {
                        case reader_downloader::SECTIONTYPE_DEFAULT:
                            $keep = false;
                            break;
                        case reader_downloader::SECTIONTYPE_HIDDEN:
                            $keep = ($visible == 1);
                            break;
                        case reader_downloader::SECTIONTYPE_VISIBLE:
                            $keep = ($visible == 1);
                            break;
                        case reader_downloader::SECTIONTYPE_LAST:
                            $keep = ($section->section == $lastsectionnum);
                            break;
                        case reader_downloader::SECTIONTYPE_NEW:
                            $keep = false;
                            break;
                    }
                    if ($keep) {
                        $sectionnums[$section->section] = reader_textlib('substr', $section->name, 0, 40);
                    } else {
                        unset($sectionnums[$section->section]);
                    }
                }
                unset($sections);
            }
        }

        $action = reader_downloader::ACTION_SECTIONNUM;
        $params = array('class' => 'targetsectionnum', 'onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $sectionnums = html_writer::select($sectionnums, 'targetsectionnum', $sectionnum, null, $params);

        $action = reader_downloader::ACTION_SECTIONTEXT;
        $params = array('type' => 'text',
                        'name' => 'targetsectiontext',
                        'id'   => 'texttargetsectiontext',
                        'value' => $downloader->targetsectiontext,
                        'onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $sectiontext = html_writer::empty_tag('input', $params);

        $sectiontypes = array();
        if ($downloader->can_manage_course($courseid)) {
            $sectiontypes[reader_downloader::SECTIONTYPE_DEFAULT] = get_string('sectiontypedefault', 'mod_reader');
        }
        if ($count_visible) {
            $sectiontypes[reader_downloader::SECTIONTYPE_VISIBLE] = get_string('sectiontypevisible', 'mod_reader');
        }
        if ($count_hidden) {
            $sectiontypes[reader_downloader::SECTIONTYPE_HIDDEN] = get_string('sectiontypehidden', 'mod_reader');
        }
        if ($count_visible || $count_hidden) {
            $sectiontypes[reader_downloader::SECTIONTYPE_LAST] = get_string('sectiontypelast', 'mod_reader');
        }
        if ($downloader->can_manage_course($courseid)) {
            $sectiontypes[reader_downloader::SECTIONTYPE_NEW] = get_string('sectiontypenew', 'mod_reader');
        }

        $action = reader_downloader::ACTION_SECTIONTYPE;
        $params = array('onchange' => 'RDR_set_menuitems(this, '.$action.')');
        $sectiontypes = html_writer::select($sectiontypes, 'targetsectiontype', $sectiontype, null, $params);

        //$label = get_string('targetsection', 'mod_reader');
        $label = get_string('section');
        return $this->formitem('targetsection', $label, $sectiontypes.' '.$sectionnums.' '.$sectiontext, 'targetsection');
    }

    /**
     * formheader
     *
     * @param string $header
     * @return string
     * @todo Finish documenting this function
     **/
    public function formheader($header) {
        return html_writer::tag('h3', $header, array('class' => 'formheader'));
    }

    /**
     * formitem
     *
     * @param string $label
     * @param string $element
     * @param string $action  (optional, default="")
     * @param string $onclick (optional, default="")
     * @return string
     * @todo Finish documenting this function
     **/
    public function formitem($id, $label, $element, $action='', $onclick='') {
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return html_writer::tag('div', $element, array('id' => $id.'element', 'class' => 'element'));
        }
        $output = '';
        if ($action) {
            $label .= $this->help_icon($action, 'mod_reader');
        } else if ($onclick) {
            $label .= $this->help_icon($onclick, 'mod_reader');
        }
        $output .= html_writer::tag('div', $label, array('id' => $id.'label', 'class' => 'label'));
        $output .= html_writer::tag('div', $element, array('id' => $id.'element', 'class' => 'element'));
        if ($action) {
            $buttonid = 'button'.$id;
            $params = array('type' => 'submit', 'name' => 'action'.$action, 'value' => get_string('go'), 'class' => 'button');
            if ($onclick=='') {
                $hidebutton = true;
            } else {
                $hidebutton = false;
                $params['onclick'] = $onclick; // e.g. search button
            }
            $button = html_writer::empty_tag('input', $params);
            $output .= html_writer::tag('div', $button, array('class' => 'button', 'id' => $buttonid));
            if ($hidebutton) {
                $output .= '<script type="text/javascript">'."\n";
                $output .= "//<![CDATA[\n";
                $output .= "RDR_showhide_textboxes('$buttonid');\n";
                $output .= "//]]>\n";
                $output .= '</script>'."\n";
            }
        }
        $output = html_writer::tag('div', $output, array('id' => $id.'formitem', 'class' => 'formitem'));
        return $output.html_writer::tag('div', '', array('style' => 'clear: both;'));
    }
}
