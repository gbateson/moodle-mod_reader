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
 * mod/reader/updatecheck.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$id                = optional_param('id', 0, PARAM_INT);
$a                 = optional_param('a', NULL, PARAM_CLEAN);
$quiz              = optional_param('quiz', NULL, PARAM_CLEAN);
$newquizzes        = optional_param('newquizzes', NULL, PARAM_CLEAN);
$updatedquizzes    = optional_param('updatedquizzes', NULL, PARAM_CLEAN);
$quiz              = optional_param('updatedquizzes', NULL, PARAM_CLEAN);
$newquizzesto      = optional_param('newquizzesto', NULL, PARAM_CLEAN);
$json              = optional_param('json', NULL, PARAM_CLEAN);
$checker           = optional_param('checker', 0, PARAM_INT);

//$readercfg->last_update = $readercfg->last_update - 31 * 24 * 3600;       //Убрать потом

if ($id) {
    if (! $cm = get_coursemodule_from_id('reader', $id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $reader = $DB->get_record('reader', array('id' => $cm->instance))) {
        throw new reader_exception('Course module is incorrect');
    }
} else {
    if (! $reader = $DB->get_record('reader', array('id' => $a))) {
        throw new reader_exception('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reader', $reader->id, $course->id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
}

require_login($course->id);

$readercfg = get_config('reader');
$readercfg->last_update -= (31 * 24 * 3600);

$context = reader_get_context(CONTEXT_COURSE, $course->id);
$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);
if (! has_capability('mod/reader:manage', $contextmodule)) {
    throw new reader_exception('You should be Admin');
}

add_to_log($course->id, 'reader', 'Download Quizzes Process', "dlquizzes.php?id=$id", "$cm->instance");

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/updatecheck.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

require_once ('tabs_dl.php');

echo $OUTPUT->box_start('generalbox');

if ($checker == 1) {
    echo "<center>"; print_string('lastupdatedtime', 'reader', date("d M Y", $readercfg->last_update));
    echo ' <br /> <a href="updatecheck.php?id='.$id.'">YES</a> / <a href="admin.php?a=admin&id='.$id.'">NO</a></center> ';

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die;
}

/** Find all readers **/
$readersarr = $DB->get_records ("reader");
if (empty($readersarr)) {
    $readersarr = array();
}
$r = array();
$datareaders = array();
while (list($key,$reader) = each($readersarr)) {
    if (empty($datareaders[$reader->id])) {
        $datareaders[$reader->id] = array();
    }
    $datareaders[$reader->id]['ignoredate'] = $reader->ignoredate;
    $usersarr = $DB->get_records_sql('SELECT DISTINCT userid FROM {reader_attempts} WHERE reader= ? and timestart >= ?', array($reader->id, $reader->ignoredate));
    if (empty($usersarr)) {
        $usersarr = array();
    }
    $datareaders[$reader->id]['totalusers'] = count($usersarr);
    $attemptsarr = $DB->get_records_sql('SELECT id FROM {reader_attempts} WHERE reader= ? and timestart >= ?', array($reader->id, $reader->ignoredate));
    if (empty($attemptsarr) || empty($usersarr)) {
        $datareaders[$reader->id]['attemptsaver'] = 0;
    } else {
        $datareaders[$reader->id]['attemptsaver'] = round(count($attemptsarr) / count($usersarr), 1);
    }
    $datareaders[$reader->id]['course'] = $reader->course;
    $course = $DB->get_record('course', array('id' => $reader->course));
    $datareaders[$reader->id]['short_name'] = $course->shortname;
    $r[$reader->id]['course'] = $reader->course;
    $r[$reader->id]['short_name'] = $course->shortname;
}
/**=============**/

$publishers = $DB->get_records_sql('SELECT * FROM {reader_books} WHERE hidden != 1');
if (empty($publishers)) {
    $publishers = array();
}
if (empty($data)) {
    $data = array();
}

while (list($key,$book) = each($publishers)) {
    //echo "SELECT passed,bookrating FROM {$CFG->prefix}reader_attempts WHERE quizid = {$book->id}"."<br />";
    if ($book->time < 10) {
        $book->time = $readercfg->last_update;
    }

    $attempts = $DB->get_records_sql('SELECT id,passed,bookrating,reader FROM {reader_attempts} WHERE quizid = ?', array($book->id));

    unset($rate,$c);
    $c = array();
    $data = array();
    $rate = array();
    if (is_array($attempts)) {
        while(list($key2,$attempt) = each($attempts)) {
            if (empty($c[$attempt->reader])) {
                $c[$attempt->reader] = 0;
            }
            if (empty($data[$attempt->reader])) {
                $data[$attempt->reader] = array();
            }
            if (empty($data[$attempt->reader][$book->image])) {
                $data[$attempt->reader][$book->image] = array(
                    'true' => 0, 'credit' => 0, 'false' => 0,
                    'rate' => 0, 'course' => 0, 'time' => 0,
                    'short_name' => ''
                );
            }
            if (empty($rate[$attempt->reader])) {
                $rate[$attempt->reader] = 0;
            }
            @$c[$attempt->reader]++;
            if ($attempt->passed == 'TRUE' || $attempt->passed == 'true') {
                $data[$attempt->reader][$book->image]['true']++;
            } else if ($attempt->passed == 'credit') {
                $data[$attempt->reader][$book->image]['credit']++;
            } else {
                @$data[$attempt->reader][$book->image]['false']++;
            }
            @$rate[$attempt->reader] = $attempt->bookrating + $rate[$attempt->reader];
        }
    } else {
        if (empty($data[0])) {
            $data[0] = array();
        }
        if (empty($data[0][$book->image])) {
            $data[0][$book->image] = array();
        }
        $data[0][$book->image]['true']       = 0;
        $data[0][$book->image]['false']      = 0;
        $data[0][$book->image]['credit']     = 0;
        $data[0][$book->image]['rate']       = 0;
        $data[0][$book->image]['course']     = 1;
        $data[0][$book->image]['time']       = $book->time;
        $data[0][$book->image]['short_name'] = 'NOTUSED';
    }

    reset($readersarr);
    while (list($key,$reader) = each($readersarr)) {
        //echo "{$rate[$reader->id]}  / {$c[$reader->id]} <br />";
        if (isset($data[$reader->id][$book->image]['true']) || isset($data[$reader->id][$book->image]['credit']) || isset($data[$reader->id][$book->image]['false'])) {
            if (empty($data[$reader->id])) {
                $data[$reader->id] = array();
            }
            if (empty($data[$reader->id][$book->image])) {
                $data[$reader->id][$book->image] = array();
            }
            $data[$reader->id][$book->image]['rate'] = round($rate[$reader->id] / $c[$reader->id],1);
            $data[$reader->id][$book->image]['course'] = $r[$reader->id]['course'];
            $data[$reader->id][$book->image]['time'] = $book->time;
            $data[$reader->id][$book->image]['short_name'] = $r[$reader->id]['short_name'];
        }
    }
}

$jdata['userlogin']  = $readercfg->serverlogin;
$jdata['lastupdate'] = $readercfg->last_update;
$jdata['books']      = $data;
$jdata['readers']    = $datareaders;

$json = json_encode($jdata);

$postdata = http_build_query(
    array(
        'json' => $json
    )
);

$opts = array('http' =>
    array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    )
);

$context  = stream_context_create($opts);

$url = new moodle_url($readercfg->serverlink.'/update_quizzes.php');
$result = file_get_contents($url, false, $context);

//echo stripslashes($result);

$needqudate = json_decode(stripslashes($result));

unset($data);

$cp = 0;

if (is_object($needqudate)) {
    echo $OUTPUT->box_start('generalbox');

    echo '<script type="text/javascript">'."\n";
    echo 'function setChecked(obj, from,to) {'."\n";
    echo '    for (var i=from; i<=to; i++) {'."\n";
    echo '        if (document.getElementById("quiz_" + i)) {'."\n";
    echo '            document.getElementById("quiz_" + i).checked = obj.checked;'."\n";
    echo '        }'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '</script>';

    echo '<form action="dlquizzes.php?id='.$id.'" method="post" id="mform1">';
    echo '<div style="width:600px"><a href="#" onclick="expandall();">Show All</a> / <a href="#" onclick="collapseall();">Hide All</a><br />';

    //vivod
    $allquestionscount = 0;
    $newcheckboxes = '';
    $updatedcheckboxes = '';

    while(list($key, $value) = each($needqudate)) {
        while(list($key2, $value2) = each($value)) {
            while(list($key3, $value3) = each($value2)) {
                $allquestionscount++;
                $checkboxdatapublishersreal[$key][] = $key3;
                $checkboxdatalevelsreal[$key][$key2][] = $key3;

                $checkboxdatapublishers[$key][] = $allquestionscount;
                $checkboxdatalevels[$key][$key2][] = $allquestionscount;
                $quizzescountid[$key3] = $allquestionscount;

                if (strstr($value3, 'UPDATE::')) {
                    $updatedcheckboxes .= $allquestionscount . ',';
                }
                if (strstr($value3, 'NEW::')) {
                    $newcheckboxes .= $allquestionscount . ',';
                }
            }
        }
    }

    $updatedcheckboxes = substr($updatedcheckboxes,0,-1);
    $newcheckboxes = substr($newcheckboxes,0,-1);

    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'button', 'name' => 'selectnew', 'value' => 'Select all new', 'onclick' => 'expandall2();setcheckedbyid("'.$newcheckboxes.'");'));
    echo html_writer::empty_tag('input', array('type' => 'button', 'name' => 'selectupdated', 'value' => 'Select all updated', 'onclick' => 'expandall2();setcheckedbyid("'.$updatedcheckboxes.'");'));
    echo html_writer::empty_tag('input', array('type' => 'button', 'name' => 'selectupdated', 'value' => 'Clear all selections', 'onclick' => 'uncheckall();'));
    echo html_writer::end_tag('div');

    echo '<script type="text/javascript" defer="defer">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function uncheckall() {'."\n";
    echo '    void(d=document);'."\n";
    echo '    void(el=d.getElementsByTagName("INPUT"));'."\n";
    echo '    for(i=0;i<el.length;i++) {'."\n";
    echo '        void(el[i].checked=0);'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo 'function checkall() {'."\n";
    echo '    void(d=document);'."\n";
    echo '    void(el=d.getElementsByTagName("INPUT"));'."\n";
    echo '    for(i=0;i<el.length;i++) {'."\n";
    echo '        void(el[i].checked=1);'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '//]]>'."\n";
    echo '</script>'."\n";

    reset($needqudate);
    while(list($publiher, $datas) = each($needqudate)) {
        $cp++;
        echo '<br /><a href="#" onclick="toggle(\'comments_'.$cp.'\');return false">
        <span id="comments_'.$cp.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';
        echo ' <b>'.$publiher.'</b>';

        echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatapublishers[$publiher][0].','.end($checkboxdatapublishers[$publiher]).')" value="" /><span id="seltext_'.$cp.'">Install All</span>';
        //print_r ($datas);
        reset($datas);
        while(list($level, $quizzesdata) = each($datas)) {
            $cp++;
            echo '<div style="padding-left:40px;padding-top:10px;padding-bottom:10px;"><a href="#" onclick="toggle(\'comments_'.$cp.'\');return false">
            <span id="comments_'.$cp.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';

            //if ($needpassword[$publiher][$level] == "true") {
            //echo ' <img src="'.$CFG->wwwroot.'/mod/reader/pix/pw.png" width="23" height="15" alt="Need password" /> ';
            //}

            echo '<b>'.$level.'</b>';
            echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatalevels[$publiher][$level][0].','.end($checkboxdatalevels[$publiher][$level]).')" value="" /><span id="seltext_'.$cp.'">Install All</span>';
            reset($quizzesdata);
            while(list($quizid, $quiztitle) = each($quizzesdata)) {
                if (strstr($quiztitle, "NEW::")) {$quiztitle = substr($quiztitle, 5); $mark = 'New';}
                if (strstr($quiztitle, "UPDATE::")) {$quiztitle = substr($quiztitle, 8); $mark = 'Updated';}
                echo '<div style="padding-left:20px;"><span style="color:blue;">'.$mark.'</span><input type="checkbox" name="quiz[]" id="quiz_'.$quizzescountid[$quizid].'" value="'.$quizid.'" />'.$quiztitle.'</div>';
            }
            echo '</span></div>';
        }
        echo '</span>';
    }

    echo '<div style="margin-top:40px;margin-left:200px;"><input type="submit" name="downloadquizzes" value="Install Quizzes" /></div>';

    echo '</div>';
    echo '</form>';

    echo $OUTPUT->box_end();
} else {
    echo $OUTPUT->box_start('generalbox');

    print_string('therehavebeennonewquizzesorupdates', 'reader');

    echo $OUTPUT->box_end();
}

//print_r ($needqudate);

//echo 'done';

echo $OUTPUT->box_end();

echo '<script type="text/javascript">'."\n";
echo '//<![CDATA['."\n";

echo 'var spanmark = 1;'."\n";
echo 'var vh_content = new Array();'."\n";

echo 'function getspan(spanid) {'."\n";
echo '    if (document.getElementById) {'."\n";
echo '        return document.getElementById(spanid);'."\n";
echo '    } else if (window[spanid]) {'."\n";
echo '        return window[spanid];'."\n";
echo '    }'."\n";
echo '    return null;'."\n";
echo '}'."\n";

echo 'function toggle(spanid) {'."\n";
echo '    if (getspan(spanid).innerHTML == "") {'."\n";
echo '        getspan(spanid).innerHTML = vh_content[spanid];'."\n";
echo '        getspan(spanid + "indicator").innerHTML = \'<img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" />\';'."\n";
echo '    } else {'."\n";
echo '        vh_content[spanid] = getspan(spanid).innerHTML;'."\n";
echo '        getspan(spanid).innerHTML = "";'."\n";
echo '        getspan(spanid + "indicator").innerHTML = \'<img src="'.$CFG->wwwroot.'/mod/reader/pix/closed.gif" alt="Closed folder" />\';'."\n";
echo '    }'."\n";
echo '}'."\n";

echo 'function collapse(spanid) {'."\n";
echo '    if (getspan(spanid).innerHTML !== "") {'."\n";
echo '        vh_content[spanid] = getspan(spanid).innerHTML;'."\n";
echo '        getspan(spanid).innerHTML = "";'."\n";
echo '        getspan(spanid + "indicator").innerHTML = \'<img src="'.$CFG->wwwroot.'/mod/reader/pix/closed.gif" alt="Closed folder" />\';'."\n";
echo '    }'."\n";
echo '}'."\n";

echo 'function expand(spanid) {'."\n";
echo '    getspan(spanid).innerHTML = vh_content[spanid];'."\n";
echo '    getspan(spanid + "indicator").innerHTML = \'<img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" />\';'."\n";
echo '}'."\n";

echo 'function expandall() {'."\n";
echo '    for (i = 1; i <= vh_numspans; i++) {'."\n";
echo '        expand("comments_" + String(i));'."\n";
echo '    }'."\n";
echo '}'."\n";

echo 'function collapseall() {'."\n";
echo '    for (i = vh_numspans; i > 0; i--) {'."\n";
echo '        collapse("comments_" + String(i));'."\n";
echo '    }'."\n";
echo '}'."\n";

echo 'function expandall2() {'."\n";
echo '    if (window.spanmark == 1) {'."\n";
echo '        for (i = 1; i <= vh_numspans; i++) {'."\n";
echo '            expand("comments_" + String(i));'."\n";
echo '        }'."\n";
echo '        window.spanmark = 2;'."\n";
echo '    }'."\n";
echo '}'."\n";

echo 'function setcheckedbyid(ids) {'."\n";
echo '    var pos=ids.indexOf(",");'."\n";
echo '    if (pos>=0) {'."\n";
echo '        var myArray = ids.split(",");'."\n";
echo '        for (i = 0; i < myArray.length; i++) {'."\n";
echo '            document.getElementById("quiz_" + myArray[i]).checked = true;'."\n";
echo '        }'."\n";
echo '    } else {'."\n";
echo '        document.getElementById("quiz_" + ids).checked = true;'."\n";
echo '    }'."\n";
echo '}'."\n";

echo "var vh_numspans = $cp;\n";
echo 'collapseall();'."\n";

echo '//]]>'."\n";
echo '</script>'."\n";

//}

echo $OUTPUT->footer();

$DB->set_field('config_plugins', 'value', time(), array('name' => 'last_update'));
