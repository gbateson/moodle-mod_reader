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
 * mod/reader/admin/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/renderer.php');

/**
 * mod_reader_download_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_download_renderer extends mod_reader_renderer {

    /**
     * form_js_start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_js_start() {
        $output = '';
        $output .= $this->check_boxes_js();
        $output .= $this->showhide_js_start();
        return $output;
    }

    /**
     * check_boxes_js
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_boxes_js() {
        $output = '';

        static $done = false;
        if ($done==false) {
            $done = true;

            $output .= '<script type="text/javascript">'."\n";
            $output .= "//<![CDATA[\n";
            $output .= "function reader_check_boxes(checkbox) {\n";
            $output .= "    var obj = checkbox.parentNode.getElementsByTagName('input');\n";
            $output .= "    if (obj) {\n";
            $output .= "        var i_max = obj.length;\n";
            $output .= "        for (var i=0; i<i_max; i++) {\n";
            $output .= "            if (obj[i].type=='checkbox') {\n";
            $output .= "                obj[i].checked = checkbox.checked;\n";
            $output .= "            }\n";
            $output .= "        }\n";
            $output .= "    }\n";
            $output .= "    var obj = null;\n";
            $output .= "}\n";
            $output .= "//]]>\n";
            $output .= '</script>'."\n";
        }
        return $output;
    }

    /**
     * showhide_js_start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_js_start() {
        $output = '';

        static $done = false;
        if ($done==false) {
            $done = true;

            $output .= '<script type="text/javascript">'."\n";
            $output .= "//<![CDATA[\n";

            $output .= "function css_class_attribute() {\n";
            $output .= "    if (window.cssClassAttribute==null) {\n";
            $output .= "        var m = navigator.userAgent.match(new RegExp('MSIE (\\d+)'));\n";
            $output .= "        if (m && m[1]<=7) {\n";
            $output .= "            // IE7 and earlier\n";
            $output .= "            window.cssClassAttribute = 'className';\n";
            $output .= "        } else {\n";
            $output .= "            window.cssClassAttribute = 'class';\n";
            $output .= "        }\n";
            $output .= "    }\n";
            $output .= "    return window.cssClassAttribute;\n";
            $output .= "}\n";

            $output .= "function remove_child_nodes(obj) {\n";
            $output .= "    while (obj.firstChild) {\n";
            $output .= "        obj.removeChild(obj.firstChild);\n";
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "function showhide_parent_lists(obj, display) {\n";
            $output .= "    var p = obj.parentNode;\n";
            $output .= "    while (p) {\n";
            $output .= "        if (p.nodeType==1 && p.nodeName.toUpperCase()=='UL') {\n";
            $output .= "            p.style.display = display;\n";
            $output .= "        }\n";
            $output .= "        p = p.parentNode;\n";
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "function match_classname(obj, targetClassNames) {\n";
            $output .= "    if (obj==null || obj.getAttribute==null) {\n";
            $output .= "        return false;\n";
            $output .= "    }\n";
            $output .= "    var myClassName = obj.getAttribute(css_class_attribute());\n";
            $output .= "    if (myClassName) {\n";
            $output .= "        if (typeof(targetClassNames)=='string') {\n";
            $output .= "           targetClassNames = new Array(targetClassNames);\n";
            $output .= "        }\n";
            $output .= "        var i_max = targetClassNames.length;\n";
            $output .= "        for (var i=0; i<i_max; i++) {\n";
            $output .= "            if (myClassName.indexOf(targetClassNames[i]) >= 0) {\n";
            $output .= "                return true;\n";
            $output .= "            }\n";
            $output .= "        }\n";
            $output .= "    }\n";
            $output .= "    return false;\n";
            $output .= "}\n";

            $output .= "function showhide_list(img, force, targetClassName) {\n";
            $output .= "    if (typeof(force)=='undefined') {\n";
            $output .= "       force = 0;\n"; // -1=hide, 0=toggle, 1=show
            $output .= "    }\n";
            $output .= "    if (typeof(targetClassName)=='undefined') {\n";
            $output .= "       targetClassName = '';\n";
            $output .= "    }\n";
            $output .= "    var obj = img.nextSibling;\n";
            $output .= "    var myClassName = obj.getAttribute(css_class_attribute());\n";
            $output .= "    if (obj && (targetClassName=='' || (myClassName && myClassName.match(new RegExp(targetClassName))))) {\n";
            $output .= "        if (force==1 || (force==0 && obj.style.display=='none')) {\n";
            $output .= "            obj.style.display = '';\n";
            $output .= "            var pix = 'minus';\n";
            $output .= "        } else {\n";
            $output .= "            obj.style.display = 'none';\n";
            $output .= "            var pix = 'plus';\n";
            $output .= "        }\n";
            $output .= "        img.alt = 'switch_' + pix;\n";
            $output .= "        img.src = img.src.replace(new RegExp('switch_[a-z]+'), 'switch_' + pix);\n";
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "function showhide_lists(force, targetClassName, requireCheckbox) {\n";

            $output .= "    switch (force) {\n";
            $output .= "        case -1: var targetImgName = 'minus';        break;\n"; // hide
            $output .= "        case  0: var targetImgName = '(minus|plus)'; break;\n"; // toggle
            $output .= "        case  1: var targetImgName = 'plus';         break;\n"; // show
            $output .= "        default: return false;\n";
            $output .= "    }\n";

            $output .= "    var targetImgName = new RegExp('switch_'+targetImgName);\n";
            $output .= "    var img = document.getElementsByTagName('img');\n";
            $output .= "    if (img) {\n";

            $output .= "        var i_max = img.length;\n";
            $output .= "        for (var i=0; i<i_max; i++) {\n";

            $output .= "            if (typeof(requireCheckbox)=='undefined') {\n";
            $output .= "                var ok = true;\n";
            $output .= "            } else {\n";
            $output .= "                var obj = img[i].parentNode.firstChild;\n";
            $output .= "                while (obj && obj.nodeType != 1) {\n";
            $output .= "                    obj = obj.nextSibling;\n";
            $output .= "                }\n";
            $output .= "                requireCheckbox = (requireCheckbox ? true : false);\n"; // convert to boolean
            $output .= "                var hascheckbox = (obj && obj.nodeType==1 && obj.nodeName.toUpperCase()=='INPUT');\n";
            $output .= "                var ok = (requireCheckbox==hascheckbox);\n";
            $output .= "                obj = null;\n";
            $output .= "            }\n";

            $output .= "            if (ok && img[i].src && img[i].src.match(targetImgName)) {\n";
            $output .= "                showhide_list(img[i], force, targetClassName);\n";
            $output .= "            }\n";
            $output .= "        }\n";
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "function clear_search_results() {\n";
            $output .= "    showhide_lists(-1);\n";

            $output .= "    var whiteSpace = new RegExp('  +', 'g');\n";
            $output .= "    var htmlTags = new RegExp('<[^>]*>', 'g');\n";

            $output .= "    var obj = document.getElementsByTagName('SPAN');\n";
            $output .= "    if (obj) {\n";
            $output .= "        var i_max = obj.length;\n";
            $output .= "        for (var i=0; i<i_max; i++) {\n";
            $output .= "            if (match_classname(obj[i], 'itemname')) {\n";
            $output .= "                var txt = obj[i].innerHTML.replace(htmlTags, '').replace(whiteSpace, ' ');\n";
            $output .= "                if (txt != obj[i].innerHTML) {\n";
            $output .= "                    remove_child_nodes(obj[i]);\n";
            $output .= "                    obj[i].appendChild(document.createTextNode(txt));\n";
            $output .= "                }\n";
            $output .= "            }\n";
            $output .= "        }\n";
            $output .= "    }\n";

            $output .= "    var obj = document.getElementsByTagName('UL');\n";
            $output .= "    if (obj) {\n";
            $output .= "        var i_max = obj.length;\n";
            $output .= "        for (var i=0; i<i_max; i++) {\n";
            $output .= "            if (match_classname(obj[i], new Array('publishers', 'levels', 'items'))) {\n";
            $output .= "                obj[i].style.display = 'none';\n";
            $output .= "            }\n";
            $output .= "        }\n";
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "function search_itemnames(btn) {\n";
            $output .= "    clear_search_results();\n";

            $output .= "    if (btn==null || btn.form==null || btn.form.searchtext==null) {\n";
            $output .= "        return false;";
            $output .= "    }\n";

            $output .= "    var searchtext = btn.form.searchtext.value.toLowerCase();\n";
            $output .= "    if (searchtext=='') {\n";
            $output .= "        return false;";
            $output .= "    }\n";

            $output .= "    var obj = document.getElementsByTagName('SPAN');\n";
            $output .= "    if (obj==null) {\n";
            $output .= "        return false;";
            $output .= "    }\n";

            $output .= "    var i_max = obj.length;\n";
            $output .= "    for (var i=0; i<i_max; i++) {\n";

            $output .= "        if (match_classname(obj[i], 'itemname')==false) {\n";
            $output .= "            continue;\n";
            $output .= "        }\n";

            $output .= "        var txt = obj[i].innerHTML.toLowerCase();\n";
            $output .= "        var pos = txt.indexOf(searchtext);\n";
            $output .= "        if (pos < 0) {\n";
            $output .= "            continue;\n";
            $output .= "        }\n";

            $output .= "        var string1 = obj[i].innerHTML.substr(0, pos);\n";
            $output .= "        var string2 = obj[i].innerHTML.substr(pos, searchtext.length);\n";
            $output .= "        var string3 = obj[i].innerHTML.substr(pos + searchtext.length);\n";

            $output .= "        var span = document.createElement('SPAN');\n";
            $output .= "        span.appendChild(document.createTextNode(string2));\n";
            $output .= "        span.setAttribute(css_class_attribute(), 'matchedtext');\n";

            $output .= "        remove_child_nodes(obj[i]);\n";

            $output .= "        obj[i].appendChild(document.createTextNode(string1));\n";
            $output .= "        obj[i].appendChild(span);\n";
            $output .= "        obj[i].appendChild(document.createTextNode(string3));\n";

            $output .= "        showhide_parent_lists(obj[i], '');\n"; // show
            $output .= "    }\n";
            $output .= "}\n";

            $output .= "//]]>\n";
            $output .= '</script>'."\n";
        }
        return $output;
    }

    /**
     * form_js_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function form_js_end() {
        $output = '';
        $output .= $this->showhide_js_end();
        return $output;
    }

    /**
     * showhide_js_end
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_js_end() {
        $output = '';
        $output .= '<script type="text/javascript">'."\n";
        $output .= "//<![CDATA[\n";
        $output .= "showhide_lists(-1);\n"; // hide all
        $output .= "showhide_lists(1, 'publishers');\n";
        $output .= "//]]>\n";
        $output .= '</script>'."\n";
        return $output;
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
     * @return xxx
     * @todo Finish documenting this function
     */
    public function showhide_menu() {
        $output = '';
        $output .= html_writer::start_tag('p');
        $output .= html_writer::tag('span', get_string('show').': ');

        // Publishers
        $onclick = 'clear_search_results(); showhide_lists(-1); showhide_lists(1, "publishers"); return false;';
        $output .= html_writer::tag('a', get_string('publishers', 'reader'), array('onclick' => $onclick));
        $output .= ' / ';

        // Levels
        $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); return false;';
        $output .= html_writer::tag('a', get_string('levels', 'reader'), array('onclick' => $onclick));
        $output .= ' / ';

        // Books
        $onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); showhide_lists(1, "items"); return false;';
        $output .= html_writer::tag('a', get_string('books', 'reader'), array('onclick' => $onclick));
        $output .= ' / ';

        // Downloadable
        $onclick = 'clear_search_results(); showhide_lists(1, "publishers", true); showhide_lists(1, "levels", true); showhide_lists(1, "items", true); return false;';
        $output .= html_writer::tag('a', get_string('downloadable', 'reader'), array('onclick' => $onclick));

        $output .= html_writer::end_tag('p');
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
                foreach ($items->items as $itemname => $itemid) {
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
                                $output .= $this->available_list_name('remotesites[]', 0, $remotesitename, 'remotesites', $available->count, $available->newcount);
                            }
                            if ($showpublishers) {
                                $output .= html_writer::start_tag('ul', array('class' => 'publishers'));
                            }
                        }

                        if ($started_levels==false) {
                            $started_levels = true;
                            if ($showpublishers) {
                                $output .= html_writer::start_tag('li', array('class' => 'publisher'));
                                $output .= $this->available_list_name('publishers[]', $i, $publishername, 'publishername', $levels->count, $levels->newcount);
                            }
                            if ($showlevels) {
                                $output .= html_writer::start_tag('ul', array('class' => 'levels'));
                            }
                        }

                        if ($started_items==false) {
                            $started_items = true;
                            if ($showlevels) {
                                $output .= html_writer::start_tag('li', array('class' => 'level'));
                                $output .= $this->available_list_name('levels[]', $i.'_'.$ii, $levelname, 'levelname', $items->count, $items->newcount);
                            }
                            $output .= html_writer::start_tag('ul', array('class' => 'items'));
                        }

                        $output .= html_writer::start_tag('li', array('class' => 'item'));
                        if (empty($downloaded->items[$publishername]->items[$levelname]->items[$itemname])) {
                            $output .= $this->available_list_name('itemids[]', $itemid, $itemname, 'itemname', 0, 1);
                        } else {
                            $img = ' '.html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/tick_green_big'), 'class' => 'icon'));
                            $output .= html_writer::tag('span', $img, array('class' => 'downloadeditem'));
                            $output .= html_writer::tag('span', $itemname, array('class' => 'itemname'));
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
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_list_name($name, $value, $text, $cssclass, $count=0, $newcount=0) {
        $output = '';
        if ($newcount) {
            $id = str_replace('[]', '_'.$value, 'id_'.$name);
            $output .= html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => $id, 'name' => $name, 'value' => $value, 'onchange' => 'reader_check_boxes(this)'));
            $output .= html_writer::start_tag('label', array('for' => $id));
        } else {
            $img = ' '.html_writer::empty_tag('img', array('src' => $this->pix_url('i/tick_green_big'), 'class' => 'icon'));
            $output .= html_writer::tag('span', $img, array('class' => 'downloadeditems'));
        }
        $output .= html_writer::tag('span', $text, array('class' => $cssclass));
        if ($count) {
            if ($newcount==$count) {
                $msg = " - data for all $count book(s) is available";
            } else if ($newcount==0) {
                $msg = " - data for all $count book(s) has been downloaded";
            } else {
                $msg = " - data for $newcount out of $count book(s) is available";
            }
            $output .= html_writer::tag('span', $msg, array('class' => 'itemcount'));
        }
        if ($newcount) {
            $output .= html_writer::end_tag('label');
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
}