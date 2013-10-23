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
 * mod/reader/admin/download/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');

/**
 * mod_reader_admin_download_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_download_renderer extends mod_reader_admin_renderer {

    /**
     * form_js_start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_js_start() {
        $js = '';
        $js .= $this->check_boxes_js();
        $js .= $this->showhide_js_start();
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
            $js .= "    var obj = reader_node(img.nextSibling);\n";
            $js .= "    var myClassName = obj.getAttribute(css_class_attribute());\n";
            $js .= "    if (obj && (targetClassName=='' || (myClassName && myClassName.match(new RegExp(targetClassName))))) {\n";
            $js .= "        if (showhide==1 || (showhide==0 && obj.style.display=='none')) {\n";
            $js .= "            obj.style.display = '';\n";
            $js .= "            var pix = 'minus';\n";
            $js .= "        } else {\n";
            $js .= "            obj.style.display = 'none';\n";
            $js .= "            var pix = 'plus';\n";
            $js .= "        }\n";
            $js .= "        img.alt = 'switch_' + pix;\n";
            $js .= "        img.src = img.src.replace(new RegExp('switch_[a-z]+'), 'switch_' + pix);\n";
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
            $js .= "        return false;";
            $js .= "    }\n";

            $js .= "    var searchtext = btn.form.searchtext.value.toLowerCase();\n";
            $js .= "    if (searchtext=='') {\n";
            $js .= "        return false;";
            $js .= "    }\n";

            $js .= "    var obj = document.getElementsByTagName('SPAN');\n";
            $js .= "    if (obj==null) {\n";
            $js .= "        return false;";
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
    public function form_start() {
        $output = '';
        $output .= $this->form_js_start();
        $output .= html_writer::start_tag('form', array('action' => $this->page->url, 'method' => 'post'));
        $output .= html_writer::start_tag('div');
        return $output;
    }

    /**
     * search_box
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function search_box() {
        $output = '';
        $output .= html_writer::start_tag('p');
        $output .= html_writer::tag('span', get_string('search').': ');
        $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchtext')).' ';
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go'), 'onclick' => 'search_itemnames(this); return false;'));
        $output .= html_writer::end_tag('p');
        return $output;
    }

    /**
     * showhide_menu
     *
     * @param integer $count
     * @param boolean $updatecount
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_menu($count, $updatecount) {
        $menu = array();

        if ($count) {
            // Publishers
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); return false;';
            $menu[] = html_writer::tag('a', get_string('publishers', 'reader'), array('onclick' => $onclick));

            // Levels
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); return false;';
            $menu[] = html_writer::tag('a', get_string('levels', 'reader'), array('onclick' => $onclick));

            // Books
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); showhide_lists(1, "items"); return false;';
            $menu[] = html_writer::tag('a', get_string('books', 'reader'), array('onclick' => $onclick));

            // Downloads
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 1); showhide_lists(1, "levels", 1); showhide_lists(1, "items", 1); return false;';
            $menu[] = html_writer::tag('a', get_string('downloads', 'reader'), array('onclick' => $onclick));
        }

        if ($updatecount) {
            // Updates
            $onclick = 'clear_search_results(); showhide_lists(1, "publishers", 2); showhide_lists(1, "levels", 2); showhide_lists(1, "items", 2); return false;';
            $menu[] = html_writer::tag('a', get_string('updates', 'reader')." ($updatecount)", array('onclick' => $onclick));
        }

        if ($menu = implode(' / ', $menu)) {
            return html_writer::tag('p', html_writer::tag('span', get_string('show').': ').$menu);
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
            $menu[] = html_writer::tag('a', get_string('updates', 'reader')." ($updatecount)", array('onclick' => $onclick));
        }

        if ($menu = implode(' / ', $menu)) {
            return html_writer::tag('p', html_writer::tag('span', get_string('select').': ').$menu);
        } else {
            return ''; // there are currently no downloadable or updatable items
        }
    }

    /**
     * form_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_end() {
        $output = '';
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('download')));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('form');
        $output .= $this->form_js_end();
        return $output;
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
     * @uses $OUTPUT
     * @param xxx $remotesite
     * @param xxx $available
     * @param xxx $downloaded
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list($remotesite, $available, $downloaded) {
        global $OUTPUT;
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
                            $img = ' '.html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/tick_green_big'), 'class' => 'icon'));
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
            $img = ' '.html_writer::empty_tag('img', array('src' => $this->pix_url('i/tick_green_big'), 'class' => 'icon'));
            $output .= html_writer::tag('span', $img, array('class' => 'downloadeditems'));
        }
        $output .= html_writer::tag('span', s($text), array('class' => $cssclass));
        if ($count) {
            if ($newcount==$count) {
                $msg = get_string('dataallavailable', 'reader', number_format($count));
            } else if ($newcount==0) {
                $msg = get_string('dataalldownloaded', 'reader', number_format($count));
            } else {
                $a = (object)array('new' => number_format($newcount), 'all' => number_format($count));
                $msg = get_string('datasomeavailable', 'reader', $a);
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
     * available_list_img
     *
     * @uses $OUTPUT
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list_img() {
        global $OUTPUT;
        $src = $OUTPUT->pix_url('t/switch_minus');
        $img = html_writer::empty_tag('img', array('src' => $src, 'onclick' => 'showhide_list(this)', 'alt' => 'switch_minus'));
        return ' '.$img;
    }

    /**
     * available_list_img
     *
     * @uses $OUTPUT
     * @param integer $updatecount (optional, default=0)
     * @param integer $updatetime (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_new_img($updatecount=0, $updatetime=0) {
        global $OUTPUT;
        $src = $OUTPUT->pix_url('i/new');
        if ($updatecount) {
            $str = get_string('updatesavailable', 'reader', $updatecount);
            $onclick = '';
        } else if ($updatetime) {
            $str = get_string('updatedon', 'reader', userdate($updatetime));
            $onclick = '';
        } else {
            $str = get_string('update'); // shouldn't happen !!
            $onclick = '';
        }
        $img = html_writer::empty_tag('img', array('src' => $src, 'alt' => $str, 'title' => $str, 'onclick' => $onclick, 'class' => 'update'));
        return ' '.$img;
    }
}
