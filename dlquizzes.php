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
 * mod/reader/dlquizzes.php
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
require_once($CFG->dirroot.'/lib/tablelib.php');
require_once($CFG->dirroot.'/lib/xmlize.php');
require_once($CFG->dirroot.'/mod/reader/dlquizzes_form.php');

$id         = optional_param('id', 0, PARAM_INT);
$a          = optional_param('a', NULL, PARAM_CLEAN);
$quiz       = reader_optional_param_array('quiz', NULL, PARAM_CLEAN);
$installall = reader_optional_param_array('installall', NULL, PARAM_CLEAN);
$password   = optional_param('password', NULL, PARAM_CLEAN);
$second     = optional_param('second', NULL, PARAM_CLEAN);
$step       = optional_param('step', NULL, PARAM_CLEAN);

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

add_to_log($course->id, 'reader', 'Download Quizzes', "dlquizzes.php?id=$id", "$cm->instance");

//$navigation = build_navigation('', $cm);

$readercfg = get_config('reader');

//print_header_simple(format_string($reader->name), "", $navigation, "", "", true,
//                  update_module_button($cm->id, $course->id, get_string('modulename', 'reader')), navmenu($course, $cm));

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/dlquizzes.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

require_once('js/hide.js');

if (! function_exists('file')) {
   throw new reader_exception('FILE function unavailable. ');
}

$params = array('a'        => 'publishers',
                'login'    => $readercfg->serverlogin,
                'password' => $readercfg->serverpassword);
$publishersurl = new moodle_url($readercfg->serverlink.'/', $params);

$publishersxml = reader_curlfile($publishersurl);
$publishersxml = xmlize(reader_makexml($publishersxml));

if (empty($publishersxml)) {
    $publishersxml = array();
}
if (empty($publishersxml['myxml'])) {
    $publishersxml['myxml'] = array();
}
if (empty($publishersxml['myxml']['#'])) {
    $publishersxml['myxml']['#'] = array();
}
if (empty($publishersxml['myxml']['#']['item'])) {
    $publishersxml['myxml']['#']['item'] = array();
}
$quizzes = array();
$needpassword = array();

foreach ($publishersxml['myxml']['#']['item'] as $item) {
    $publisher = $item['@']['publisher'];
    $level     = $item['@']['level'];
    $itemid    = $item['@']['id']; // don't use $id
    $needpass  = $item['@']['needpass'];

    if (empty($quizzes[$publisher])) {
        $quizzes[$publisher] = array();
    }
    if (empty($quizzes[$publisher][$level])) {
        $quizzes[$publisher][$level] = array();
    }
    $quizzes[$publisher][$level][$itemid] = $item['#'];

    if (empty($needpassword[$publisher])) {
        $needpassword[$publisher] = array();
    }
    $needpassword[$publisher][$level] = $needpass;
}

$allquestionscount = 0;
$printerrormessage = false;

foreach ($quizzes as $publisher =>$levels) {
    foreach ($levels as $level => $itemids) {
        foreach ($itemids as $itemid => $item) {
            $allquestionscount++;
            $checkboxdatapublishersreal[$publisher][] = $itemid;
            $checkboxdatalevelsreal[$publisher][$level][] = $itemid;

            $checkboxdatapublishers[$publisher][] = $allquestionscount;
            $checkboxdatalevels[$publisher][$level][] = $allquestionscount;
            $quizzescountid[$itemid] = $allquestionscount;

            /*  FOR LOGIN AND PASS CHECKING  */
            if (strstr($item, 'You should be student')) {
                $printerrormessage = true;
            }
        }
    }
}

require_once ($CFG->dirroot.'/mod/reader/tabs_dl.php');

$context = reader_get_context(CONTEXT_COURSE, $course->id);
$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);
if (! has_capability('mod/reader:addinstance', $contextmodule)) {
    throw new reader_exception("You should be an 'Editing' Teacher");
}

if (empty($quiz)) {
    $quiz = array();
}

if ($installall) {
    foreach ($installall as $installall_) {
        $installalldata = explode(',', $installall_);
        foreach ($installalldata as $installalldata_) {
            if (! empty($installalldata_)) {
                $quiz[] = $installalldata_;
            }
        }
    }

    $quiz = array_unique($quiz);
}

echo $OUTPUT->box_start('generalbox');

/*  FOR LOGIN AND PASS CHECKING  */
if ($printerrormessage) {
    echo html_writer::tag('p', $publishersurl);
    $href = new moodle_url('http://moodlereader.org/moodle/course/view.php', array('id' => 15));
    throw new reader_exception("In order to download quizzes, you need to be registered on the  'Moodle Reader Users' course on ".
          html_writer::tag('a', 'MoodleReader.org', array('href' => $href)).' '.
          'Please contact the system administrator ( admin@moodlereader.org ) to register yourself, '.
          'providing information on your school, your position, the reading grade level of your students '.
          'and the approximate number of students who will be using the system.');
    echo $OUTPUT->box_end();
    die;
}

if (! $quiz) {
    echo $OUTPUT->box_start('generalbox');

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function setChecked(obj,from,to) {'."\n";
    echo '    for (var i=from; i<=to; i++) {'."\n";
    echo '        if (document.getElementById(\'quiz_\' + i)) {'."\n";
    echo '            document.getElementById(\'quiz_\' + i).checked = obj.checked;'."\n";
    echo '        }'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '//]]>'."\n";
    echo '</script>'."\n";

    //echo '<div style="width:600px"><a href="#" onclick="expandall();">Show All</a> / <a href="#" onclick="collapseall();">Hide All</a><br /><br />';
    //$form = new reader_selectuploadbooks_form('dlquizzes.php?id='.$id);
    //$form->display();

    echo '<form action="dlquizzes.php?id='.$id.'" method="post" id="mform1">';
    echo '<div style="width:600px"><a href="#" onclick="expandall();">Show All</a> / <a href="#" onclick="collapseall();">Hide All</a><br />';

    //vivod
    $cp = 0;

    if ($quizzes) {
        foreach ($quizzes as $publiher => $datas) {
            $cp++;
            echo '<br /><a href="#" onclick="toggle(\'comments_'.$cp.'\');return false">
                  <span id="comments_'.$cp.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';
            echo ' <b>'.$publiher.'</b>';

            //echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatapublishers[$publiher][0].','.end($checkboxdatapublishers[$publiher]).')" value="'.implode(',', $checkboxdatapublishersreal[$publiher]).'" /><span id="seltext_'.$cp.'">Install All</span>';
            echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatapublishers[$publiher][0].','.end($checkboxdatapublishers[$publiher]).')" value="" /><span id="seltext_'.$cp.'">Install All</span>';
            foreach ($datas as $level => $quizzesdata) {
                $cp++;

                echo '<div style="padding-left:40px;padding-top:10px;padding-bottom:10px;"><a href="#" onclick="toggle(\'comments_'.$cp.'\');return false">
                      <span id="comments_'.$cp.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';

                if ($needpassword[$publiher][$level] == "true") {
                    echo ' <img src="'.$CFG->wwwroot.'/mod/reader/pix/pw.png" width="23" height="15" alt="Need password" /> ';
                }

                echo '<b>'.$level.'</b>';
                //echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatalevels[$publiher][$level][0].','.end($checkboxdatalevels[$publiher][$level]).')" value="'.implode(',', $checkboxdatalevelsreal[$publiher][$level]).'" /><span id="seltext_'.$cp.'">Install All</span>';
                echo '<span id="comments_'.$cp.'"><input type="checkbox" name="installall['.$cp.']" onclick="setChecked(this,'.$checkboxdatalevels[$publiher][$level][0].','.end($checkboxdatalevels[$publiher][$level]).')" value="" /><span id="seltext_'.$cp.'">Install All</span>';
                foreach ($quizzesdata as $quizid => $quiztitle) {
                    echo '<div style="padding-left:20px;"><input type="checkbox" name="quiz[]" id="quiz_'.$quizzescountid[$quizid].'" value="'.$quizid.'" />'.$quiztitle.'</div>';
                }
                echo '</span></div>';
            }
            echo '</span>';
        }

        echo '<div style="margin-top:40px;margin-left:200px;"><input type="submit" name="downloadquizzes" value="Install Quizzes" /></div>';
    }

    echo '<input type="hidden" name="step" value="1" />';  //”¡–¿“‹ ≈—À» Õ”∆Õ€ œ¿–ŒÀ»

    echo '</div>';
    echo '</form>';

    echo $OUTPUT->box_end();

} else {

    // $quizzes has already been set up

    $params = array('a'        => 'quizzes',
                    'login'    => $readercfg->serverlogin,
                    'password' => $readercfg->serverpassword);
    $quizzessurl = new moodle_url($readercfg->serverlink.'/', $params);

    if ($step == 1) {
        $postparams = array('quiz'=>$quiz);
    } else {
        $postparams = array('password'=>$password, 'quiz'=>$quiz);
    }
    $quizzesxml = xmlize(reader_file($quizzessurl, $postparams));

    if (empty($quizzesxml)) {
        $quizzesxml = array();
    }
    if (empty($quizzesxml['myxml'])) {
        $quizzesxml['myxml'] = array();
    }
    if (empty($quizzesxml['myxml']['#'])) {
        $quizzesxml['myxml']['#'] = array();
    }
    if (empty($quizzesxml['myxml']['#']['item'])) {
        $quizzesxml['myxml']['#']['item'] = array();
    }

    $publishers = array();
    foreach ($quizzesxml['myxml']['#']['item'] as $item) {
        $publisher = $item['@']['publisher'];
        $level     = $item['@']['level'];
        if (empty($quizzes[$publisher])) {
            $quizzes[$publisher] = array();
        }
        if (empty($quizzes[$publisher][$level])) {
            $quizzes[$publisher][$level] = array();
        }
        $publishers[$publisher][$level]['pass'] = $item['#'];
        if (isset($item['@']['status'])) {
            $publishers[$publisher][$level]['status'] = $item['@']['status'];
        }
    }

    //Passwords form
    $passprefix = "";

    $mform = new reader_uploadbooks_form('dlquizzes_process.php?id='.$id);

    $mform->display();
}

echo $OUTPUT->box_end();

if (! $quiz) {
    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'var vh_numspans = '.$cp.';'."\n";
    echo 'collapseall();'."\n";
    echo '//]]>'."\n";
    echo '</script>'; //
}

echo '<script type="application/x-javascript" src="js/jquery-1.4.2.min.js"></script>'."\n";
echo '<script type="text/javascript">'."\n";
echo '$(document).ready(function(){'."\n";
echo '    $("#id_courseid").change( function(){'."\n";
echo '        $("#loadersection").toggle();'."\n";
echo '        $.post("loadsectionoption.php?id=" + $(this).val(), function(data){'."\n";
echo '            $("#id_section").html(data);'."\n";
echo '            $("#loadersection").toggle();'."\n";
echo '        });'."\n";
echo '    });'."\n";
echo '    $("#id_section").click( function(){'."\n";
echo '        $("input[name=sectionchoosing]").attr("checked", true);'."\n";
echo '    });'."\n";
echo '});'."\n";
echo '</script>'."\n";

echo $OUTPUT->footer();
