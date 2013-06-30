<?php  // $Id:,v 2.0 2012/05/20 16:10:00 Serafim Panov

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$id = optional_param('id', 0, PARAM_INT);
$a  = optional_param('a',  0, PARAM_INT);

$selectedpublishers = reader_optional_param_array('publishers', array(), PARAM_CLEAN);
$selectedlevels     = reader_optional_param_array('levels',     array(), PARAM_CLEAN);
$selecteditemids    = reader_optional_param_array('itemids',    array(), PARAM_CLEAN);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id'=>$cm->instance), '*', MUST_EXIST);
    $a = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id'=>$a), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_login($course, true, $cm);

add_to_log($course->id, 'reader', 'Download Quizzes', "dlquizzesnoq.php?id=$id", "$cm->instance");

$readercfg = get_config('reader');

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/dlquizzesnoq.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploaddatanoquizzes', 'reader'));

if (!function_exists('file')) {
   print_error('FILE function unavailable. ');
}
if (! is_dir($CFG->dirroot.'/question/type/ordering')){
  print_error('Ordering question type is missign. Please install it the first.');
}

$exist = (object)array('items' => array());
if ($records = $DB->get_records('reader_noquiz')) {
    foreach ($records as $record) {

        $publisher = $record->publisher;
        $level     = $record->level;
        $itemname  = $record->name;

        if (! isset($exist->items[$publisher])) {
            $exist->items[$publisher] = (object)array('items' => array());
        }
        if (! isset($exist->items[$publisher]->items[$level])) {
            $exist->items[$publisher]->items[$level] = (object)array('items' => array());
        }
        $exist->items[$publisher]->items[$level]->items[$itemname] = true;
    }
}

$images = (object)array(
    'open'       => new moodle_url('/mod/reader/pix/open.gif'),
    'closed'     => new moodle_url('/mod/reader/pix/closed.gif'),
    'pw'         => new moodle_url('/mod/reader/pix/pw.png'),
    'zoomloader' => new moodle_url('/mod/reader/pix/zoomloader.gif')
);

// define url to access publisher list on remote server
$params = array('a' => 'publishers',
                'login' => $readercfg->serverlogin,
                'password' => $readercfg->serverpassword);
$url = new moodle_url($readercfg->serverlink.'/index-noq.php', $params);

// get list of publishers from remote server
$items = reader_curlfile($url);
$items = xmlize(reader_makexml($items));

$available = (object)array('count' => 0, 'newcount' => 0, 'items' => array());
foreach ($items['myxml']['#']['item'] as $item) {

    $publisher = $item['@']['publisher'];
    $needpass  = $item['@']['needpass'];
    $level     = $item['@']['level'];
    $itemid    = $item['@']['id'];
    $itemname  = $item['#'];

    if ($publisher=='Extra_Points' || $publisher=='testing') {
        continue; // ignore these publisher categories
    }

    if (! isset($available->items[$publisher])) {
        $available->items[$publisher] = (object)array('count' => 0, 'newcount' => 0, 'items' => array());
    }
    if (! isset($available->items[$publisher]->items[$level])) {
        $available->items[$publisher]->items[$level] = (object)array('count' => 0, 'newcount' => 0, 'items' => array(), 'needpassword' => false);
    }

    if ($needpass=='true') {
        $available->items[$publisher]->items[$level]->needpassword = true;
    }

    $available->count++;
    $available->items[$publisher]->count++;
    $available->items[$publisher]->items[$level]->count++;

    if (empty($exist->items[$publisher]->items[$level]->items[$itemname])) {
        $available->newcount++;
        $available->items[$publisher]->newcount++;
        $available->items[$publisher]->items[$level]->newcount++;
    }

    $available->items[$publisher]->items[$level]->items[$itemname] = $itemid;
}
unset($items, $item);

if (count($selectedpublishers) || count($selectedlevels)) {
    $i = 0;
    foreach ($available->items as $publishername => $levels) {
        $i++;

        $ii = 0;
        foreach ($levels->items as $levelname => $items) {
            $ii++;

            if (in_array($i, $selectedpublishers) || in_array($i.'_'.$ii, $selectedlevels)) {
                foreach ($items->items as $itemname => $itemid) {
                    if (! in_array($itemid, $selecteditemids)) {
                        $selecteditemids[] = $itemid;
                    }
                }
            }
        }
    }
}
unset($selectedpublishers, $selectedlevels);

$output = '';
if ($selecteditemids) {

    $params = array('a' => 'quizzes',
                    'login' => $readercfg->serverlogin,
                    'password' => $readercfg->serverpassword);
    $url = new moodle_url($readercfg->serverlink.'/index-noq.php', $params);

    $xml = reader_file($url, array('quiz' => $selecteditemids));
    $xml = xmlize($xml);

    $started_list = false;
    foreach ($xml['myxml']['#']['item'] as $i => $item) {

        // set up default values for "reader_noquiz" record
        $noquiz = (object)array(
            'publisher'  => '',
            'series'     => '',
            'level'      => '',
            'difficulty' => 0,
            'name'       => '',
            'words'      => 0,
            'genre'      => '',
            'fiction'    => '',
            'image'      => '',
            'length'     => '',
            'private'    => 0,
            'sametitle'  => '',
            'hidden'     => 0,
            'maxtime'    => 0,
        );

        // transfer values fo this $item
        $fields = get_object_vars($noquiz);
        foreach ($fields as $field => $defaultvalue) {
            if (isset($item['@'][$field])) {
                $value = $item['@'][$field];
            } else if ($field=='name') {
                $value = $item['@']['title'];
            } else {
                $value = $defaultvalue;
            }
            $noquiz->$field = $value;
        }

        // initialize array to hold messages
        $msg = array();

        // add or update the $DB information
        $params = array('publisher' => $noquiz->publisher,
                        'level'     => $noquiz->level,
                        'name'      => $noquiz->name);
        if ($noquiz->id = $DB->get_field('reader_noquiz', 'id', $params)) {
            $DB->update_record('reader_noquiz', $noquiz);
            $msg[] = 'Book data was updated: '.$noquiz->name;
        } else {
            unset($noquiz->id);
            $noquiz->id = $DB->insert_record('reader_noquiz', $noquiz);
            $msg[] = 'Book data was added: '.$noquiz->name;
        }

        // download associated image
        reader_download_noquiz_image($readercfg, $itemid, $noquiz->image);
        //$msg[] = 'Book image was dowloaded: '.$noquiz->image;

        if (count($msg)) {
            if ($started_list==false) {
                $started_list = true;
                $output .= html_writer::start_tag('div');
                $output .= html_writer::start_tag('ul');
            }
            $output .= html_writer::tag('li', implode('</li><li>', $msg));;
        }

        if (! isset($exist->items[$noquiz->publisher])) {
            $exist->items[$publisher] = (object)array('items' => array());
        }
        if (! isset($exist->items[$noquiz->publisher]->items[$noquiz->level])) {
            $exist->items[$noquiz->publisher]->items[$noquiz->level] = (object)array('items' => array());
        }
        if (! isset($exist->items[$noquiz->publisher]->items[$noquiz->level]->items[$noquiz->name])) {
            $exist->items[$noquiz->publisher]->items[$noquiz->level]->items[$noquiz->name] = true;
            $available->items[$noquiz->publisher]->items[$noquiz->level]->newcount--;
            $available->items[$noquiz->publisher]->newcount--;
            $available->newcount--;
        }
    }

    if ($started_list==true) {
        $output .= html_writer::end_tag('ul');
        $output .= html_writer::end_tag('div');
    }
}

if ($output) {
    echo $OUTPUT->box_start('generalbox', 'notice');
    echo $output;
    echo $OUTPUT->box_end();
    $output = '';
}

// add form containing list of selectcable books
$output = '';
$output .= reader_check_boxes_js();
$output .= reader_showhide_start_js();
$output .= $OUTPUT->box_start('generalbox', 'notice');

$url = new moodle_url('/mod/reader/dlquizzesnoq.php');
$output .= html_writer::start_tag('form', array('action' => $url, 'method' => 'put'));
$output .= html_writer::start_tag('div');

$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));

// Search:
$output .= html_writer::start_tag('p');
$output .= html_writer::tag('span', get_string('search').': ');
$output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchtext'));
$output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go'), 'onclick' => 'search_itemnames(this); return false;'));
$output .= html_writer::end_tag('p');

// Show:
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

// books
$onclick = 'clear_search_results(); showhide_lists(1, "publishers"); showhide_lists(1, "levels"); showhide_lists(1, "items"); return false;';
$output .= html_writer::tag('a', get_string('books', 'reader'), array('onclick' => $onclick));
$output .= ' / ';

// downloadable
$onclick = 'clear_search_results(); showhide_lists(1, "publishers", true); showhide_lists(1, "levels", true); showhide_lists(1, "items", true); return false;';
$output .= html_writer::tag('a', get_string('downloadable', 'reader'), array('onclick' => $onclick));

$output .= html_writer::end_tag('p');

$output .= html_writer::start_tag('ul', array('class' => 'remotesites'));
$output .= html_writer::start_tag('li', array('class' => 'remotesite'));
$output .= reader_checkbox('remotesites[]', 0, $readercfg->serverlink, 'remotesites', $available->count, $available->newcount);
$output .= html_writer::start_tag('ul', array('class' => 'publishers'));

$i = 0;
foreach ($available->items as $publishername => $levels) {
    $i++;

    if ($publishername) {
        $output .= html_writer::start_tag('li', array('class' => 'publisher'));
        $output .= reader_checkbox('publishers[]', $i, $publishername, 'publisher', $levels->count, $levels->newcount);
        $output .= html_writer::start_tag('ul', array('class' => 'levels'));
    }

    $ii = 0;
    foreach ($levels->items as $levelname => $items) {
        $ii++;

        if ($levelname=='-' || $levelname=='--') {
            $displaylevel = false;
        } else {
            $displaylevel = true;
        }

        if ($displaylevel) {
            $output .= html_writer::start_tag('li', array('class' => 'level'));
            $output .= reader_checkbox('levels[]', $i.'_'.$ii, $levelname, 'publisher', $items->count, $items->newcount);
            $output .= html_writer::start_tag('ul', array('class' => 'items'));
        }

        $iii = 0;
        foreach ($items->items as $itemname => $itemid) {
            $iii++;

            if ($itemname) {
                $output .= html_writer::start_tag('li', array('class' => 'item'));
                if (empty($exist->items[$publishername]->items[$levelname]->items[$itemname])) {
                    $output .= reader_checkbox('itemids[]', $itemid, $itemname, 'itemname', 0, 1);
                } else {
                    $img = ' '.html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/tick_green_big'), 'class' => 'icon'));
                    $output .= html_writer::tag('span', $img, array('class' => 'downloadeditem'));
                    $output .= html_writer::tag('span', $itemname, array('class' => 'itemname'));
                }
                $output .= html_writer::end_tag('li'); // finish item
            }
        }

        if ($displaylevel) {
            $output .= html_writer::end_tag('ul'); // finish items
            $output .= html_writer::end_tag('li'); // finish level
        }
    }

    if ($publishername) {
        $output .= html_writer::end_tag('ul'); // finish levels
        $output .= html_writer::end_tag('li'); // finish publisher
    }
}
$output .= html_writer::end_tag('ul'); // finish publishers

$output .= html_writer::end_tag('li'); // finish remotesite
$output .= html_writer::end_tag('ul'); // finish remotesites

$output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('download')));

$output .= html_writer::end_tag('div');
$output .= html_writer::end_tag('form');
$output .= $OUTPUT->box_end();
$output .= reader_showhide_end_js(); // hide the lists

echo $output;

echo $OUTPUT->footer();

/**
 * reader_check_boxes_js
 *
 * @todo Finish documenting this function
 */
function reader_check_boxes_js() {
    static $done = false;

    $js = '';
    if ($done==false) {
        $done = true;
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "function reader_check_boxes(checkbox) {\n";
        $js .= "    var obj = checkbox.parentNode.getElementsByTagName('input');\n";
        $js .= "    if (obj) {\n";
        $js .= "        var i_max = obj.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            if (obj[i].type=='checkbox') {\n";
        $js .= "                obj[i].checked = checkbox.checked;\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "    var obj = null;\n";
        $js .= "}\n";
        $js .= "//]]>\n";
        $js .= '</script>'."\n";
    }
    return $js;
}

/**
 * reader_showhide_start_js
 *
 * @todo Finish documenting this function
 */
function reader_showhide_start_js() {
    static $done = false;

    $js = '';
    if ($done==false) {
        $done = true;
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";

        $js .= "function css_class_attribute() {\n";
        $js .= "    var m = navigator.userAgent.match(new RegExp('MSIE (\\d+)'));\n";
        $js .= "    if (m && m[1]<=7) {\n";
        $js .= "        // IE7 and earlier\n";
        $js .= "        return 'className';\n";
        $js .= "    } else {\n";
        $js .= "        return 'class';\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function remove_child_nodes(obj) {\n";
        $js .= "    while (obj.firstChild) {\n";
        $js .= "        obj.removeChild(obj.firstChild);\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function showhide_parent_lists(obj, display) {\n";
        $js .= "    var p = obj.parentNode;\n";
        $js .= "    while (p) {\n";
        $js .= "        if (p.nodeType==1 && p.nodeName.toUpperCase()=='UL') {\n";
        $js .= "            p.style.display = display;\n";
        $js .= "        }\n";
        $js .= "        p = p.parentNode;\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function match_classname(obj, targetClassName) {\n";
        $js .= "    if (obj==null || obj.getAttribute==null) {\n";
        $js .= "        return false;\n";
        $js .= "    }\n";
        $js .= "    var myClassName = obj.getAttribute(css_class_attribute());\n";
        $js .= "    if (myClassName) {\n";
        $js .= "        if (typeof(targetClassName)=='string') {\n";
        $js .= "           targetClassName = new Array(targetClassName);\n";
        $js .= "        }\n";
        $js .= "        var i_max = targetClassName.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            if (myClassName.indexOf(targetClassName[i]) >= 0) {\n";
        $js .= "                return true;\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "    return false;\n";
        $js .= "}\n";

        $js .= "function showhide_list(img, force, targetclass) {\n";
        $js .= "    if (typeof(force)=='undefined') {\n";
        $js .= "       force = 0;\n"; // -1=hide, 0=toggle, 1=show
        $js .= "    }\n";
        $js .= "    if (typeof(targetclass)=='undefined') {\n";
        $js .= "       targetclass = '';\n";
        $js .= "    }\n";
        $js .= "    var obj = img.nextSibling;\n";
        $js .= "    var cssclass = obj.getAttribute(css_class_attribute());\n";
        $js .= "    if (obj && (targetclass=='' || (cssclass && cssclass.match(new RegExp(targetclass))))) {\n";
        $js .= "        if (force==1 || (force==0 && obj.style.display=='none')) {\n";
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

        $js .= "function showhide_lists(force, targetclass, requirecheckbox) {\n";

        $js .= "    switch (force) {\n";
        $js .= "        case -1: var targetsrc = 'minus';        break;\n"; // hide
        $js .= "        case  0: var targetsrc = '(minus|plus)'; break;\n"; // toggle
        $js .= "        case  1: var targetsrc = 'plus';         break;\n"; // show
        $js .= "        default: return false;\n";
        $js .= "    }\n";

        $js .= "    var targetsrc = new RegExp('switch_'+targetsrc);\n";
        $js .= "    var img = document.getElementsByTagName('img');\n";
        $js .= "    if (img) {\n";

        $js .= "        var i_max = img.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";

        $js .= "            if (typeof(requirecheckbox)=='undefined') {\n";
        $js .= "                var ok = true;\n";
        $js .= "            } else {\n";
        $js .= "                var obj = img[i].parentNode.firstChild;\n";
        $js .= "                while (obj && obj.nodeType != 1) {\n";
        $js .= "                    obj = obj.nextSibling;\n";
        $js .= "                }\n";
        $js .= "                requirecheckbox = (requirecheckbox ? true : false);\n"; // convert to boolean
        $js .= "                var hascheckbox = (obj && obj.nodeType==1 && obj.tagName.toUpperCase()=='INPUT');\n";
        $js .= "                var ok = (requirecheckbox==hascheckbox);\n";
        $js .= "                obj = null;\n";
        $js .= "            }\n";

        $js .= "            if (ok && img[i].src && img[i].src.match(targetsrc)) {\n";
        $js .= "                showhide_list(img[i], force, targetclass);\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function clear_search_results() {\n";
        $js .= "    showhide_lists(-1);\n";

        $js .= "    var whitespace = new RegExp('  +', 'g');\n";
        $js .= "    var htmltags = new RegExp('<[^>]*>', 'g');\n";

        $js .= "    var obj = document.getElementsByTagName('SPAN');\n";
        $js .= "    if (obj) {\n";
        $js .= "        var i_max = obj.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            if (match_classname(obj[i], 'itemname')) {\n";
        $js .= "                var txt = obj[i].innerHTML.replace(htmltags, '').replace(whitespace, ' ');\n";
        $js .= "                if (txt != obj[i].innerHTML) {\n";
        $js .= "                    remove_child_nodes(obj[i]);\n";
        $js .= "                    obj[i].appendChild(document.createTextNode(txt));\n";
        $js .= "                }\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";

        $js .= "    var obj = document.getElementsByTagName('UL');\n";
        $js .= "    if (obj) {\n";
        $js .= "        var i_max = obj.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            if (match_classname(obj[i], new Array('publishers', 'levels', 'items'))) {\n";
        $js .= "                obj[i].style.display = 'none';\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
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

        $js .= "    var i_max = obj.length;\n";
        $js .= "    for (var i=0; i<i_max; i++) {\n";

        $js .= "        if (match_classname(obj[i], 'itemname')==false) {\n";
        $js .= "            continue;\n";
        $js .= "        }\n";

        $js .= "        var txt = obj[i].innerHTML.toLowerCase();\n";
        $js .= "        var pos = txt.indexOf(searchtext);\n";
        $js .= "        if (pos < 0) {\n";
        $js .= "            continue;\n";
        $js .= "        }\n";

        $js .= "        var string1 = obj[i].innerHTML.substr(0, pos);\n";
        $js .= "        var string2 = obj[i].innerHTML.substr(pos, searchtext.length);\n";
        $js .= "        var string3 = obj[i].innerHTML.substr(pos + searchtext.length);\n";

        $js .= "        var span = document.createElement('SPAN');\n";
        $js .= "        span.setAttribute(css_class_attribute(), 'matchedtext');\n";
        $js .= "        span.appendChild(document.createTextNode(string2));\n";

        $js .= "        remove_child_nodes(obj[i]);\n";

        $js .= "        obj[i].appendChild(document.createTextNode(string1));\n";
        $js .= "        obj[i].appendChild(span);\n";
        $js .= "        obj[i].appendChild(document.createTextNode(string3));\n";

        $js .= "        showhide_parent_lists(obj[i], '');\n"; // show
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "//]]>\n";
        $js .= '</script>'."\n";
    }
    return $js;
}

/**
 * reader_showhide_end_js
 *
 * @todo Finish documenting this function
 */
function reader_showhide_end_js() {
    $js = '';
    $js .= '<script type="text/javascript">'."\n";
    $js .= "//<![CDATA[\n";
    $js .= "showhide_lists(-1);\n"; // force hide
    $js .= "//]]>\n";
    $js .= '</script>'."\n";
    return $js;
}

/**
 * reader_showhide_img
 *
 * @todo Finish documenting this function
 */
function reader_showhide_img() {
    global $OUTPUT;
    static $img = '';
    if ($img=='') {
        $src = $OUTPUT->pix_url('t/switch_minus');
        $img = ' '.html_writer::empty_tag('img', array('src' => $src, 'onclick' => 'showhide_list(this)', 'alt' => 'switch_minus'));
    }
    return $img;
}

/**
 * reader_checkbox
 *
 * @param string $name
 * @param string $value
 * @param string $text
 * @param string $cssclass
 * @param integer $count (optional, default=0)
 * @param integer $newcount (optional, default=0)
 *
 * @todo Finish documenting this function
 */
function reader_checkbox($name, $value, $text, $cssclass, $count=0, $newcount=0) {
    global $OUTPUT;

    $output = '';
    if ($newcount) {
        $id = str_replace('[]', '_'.$value, 'id_'.$name);
        $output .= html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => $id, 'name' => $name, 'value' => $value, 'onchange' => 'reader_check_boxes(this)'));
        $output .= html_writer::start_tag('label', array('for' => $id));
    } else {
        $img = ' '.html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/tick_green_big'), 'class' => 'icon'));
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
        $output .= reader_showhide_img();
    }
    return $output;
}

/**
 * reader_download_noquiz
 *
 * @param string $itemid
 *
 * @todo Finish documenting this function
 */
function reader_download_noquiz_image($readercfg, $itemid, $image) {
    global $CFG;
    make_upload_directory('reader/images');
    $url = new moodle_url($readercfg->reader_serverlink.'/getfilenoq.php', array('imageid' => $itemid));
    $contents = file_get_contents($url);
    $fp = @fopen($CFG->dataroot.'/reader/images/'.$image, 'w+');
    @fwrite($fp, $contents);
    @fclose($fp);
}
