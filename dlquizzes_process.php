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
 * mod/reader/dlquizzes_process.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/lib/xmlize.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/lib/pclzip/pclzip.lib.php');
require_once($CFG->dirroot.'/mod/reader/lib/backup/restorelib.php');
require_once($CFG->dirroot.'/mod/reader/lib/backup/backuplib.php');
require_once($CFG->dirroot.'/mod/reader/lib/backup/lib.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/restorelib.php');

/** values for $sectionchoosing */
define('READER_BOTTOM_SECTION',   1);
define('READER_SORTED_SECTION',   2);
define('READER_SPECIFIC_SECTION', 3);

$id              = optional_param('id', 0, PARAM_INT); // course module id
$a               = optional_param('a', NULL, PARAM_CLEAN);
$quiz            = reader_optional_param_array('quiz', NULL, PARAM_CLEAN);
$password        = optional_param('password', NULL, PARAM_CLEAN);
$sectionchoosing = optional_param('sectionchoosing', READER_BOTTOM_SECTION, PARAM_INT);
$section         = optional_param('section', 1, PARAM_INT);
$targetcourseid  = optional_param('courseid', 0, PARAM_INT);
$quizid          = optional_param('quizid', 0, PARAM_INT);
$end             = optional_param('end', 0, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('reader', $id)) {
        error('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }
    if (! $reader = $DB->get_record('reader', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }
} else {
    if (! $reader = $DB->get_record('reader', array('id' => $a))) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reader', $reader->id, $course->id)) {
        error('Course Module ID was incorrect');
    }
}

require_login($course->id);

require_once($CFG->dirroot.'/mod/reader/lib/question/type/multianswer/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/multichoice/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/ordering/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/truefalse/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/random/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/match/questiontype.php');
require_once($CFG->dirroot.'/mod/reader/lib/question/type/description/questiontype.php');

$QTYPES = array();
$QTYPES['multianswer'] = new back_multianswer_qtype();
$QTYPES['multichoice'] = new back_multichoice_qtype();
$QTYPES['ordering']    = new back_ordering_qtype();
$QTYPES['truefalse']   = new back_truefalse_qtype();
$QTYPES['random']      = new back_random_qtype();
$QTYPES['match']       = new back_match_qtype();
$QTYPES['description'] = new back_description_qtype();

$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);
require_capability('mod/reader:manage', $contextmodule);

$readercfg = get_config('reader');

add_to_log($course->id, 'reader', 'Download Quizzes Process', "dlquizzes.php?id=$id", "$cm->instance");

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/dlquizzes_process.php', array('id' => $cm->id));

$title = $course->shortname.': '.format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

if (empty($quizid)) {
    echo $OUTPUT->header();
    echo $OUTPUT->box_start('generalbox');
    reader_show_quizlist($quiz, $id, $targetcourseid, $sectionchoosing, $section);
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

$quizitems = reader_download_quizitems($quizid, $password);

if ($targetcourseid==0) {
    // create a new course to hold the quizzes - unusual ?!
    $targetcourse = reader_create_targetcourse();
    $targetcourseid = $targetcourse->id;
    print_string('process_courseadded', 'reader');
} else {
    $targetcourse = $DB->get_record('course', array('id' => $targetcourseid));
}

$_SESSION['SESSION']->reader_downloadprocesscourseid = $targetcourseid;

$quizmodule = $DB->get_record('modules', array('name' => 'quiz'));

// Add quizzes
$quizids = array();
foreach ($quizitems as $sectionname => $items) {

    // get section number for these items
    $sectionnum = reader_download_sectionnum($targetcourse, $sectionname, $sectionchoosing);

    // add all items in this section
    foreach ($items as $i => $item) {

        $itemid = $item['id'];
        $itemname = $item['title'];

        // if the $item is already a quiz in this section of the course, skip it
        if (reader_download_instance_exists($targetcourseid, $sectionnum, 'quiz', $itemname)) {
            $a = (object)array('coursename'  => format_text($targetcourse->shortname),
                               'sectionnum'  => $sectionnum,
                               'sectionname' => $sectionname,
                               'quizname'    => $itemname);
            echo html_writer::tag('p', get_string('skipquizdownload', 'reader', $a));
            unset($quizitems[$sectionname][$i]);
        } else {
            // add a new quiz course module for this $item
            $cm = reader_create_new_quiz($targetcourseid, $sectionnum, $quizmodule, $itemname);
            $quizids[$itemid] = $cm->instance;
        }
    }
}

if (count($quizids)) {
    rebuild_course_cache($targetcourseid);
}

foreach ($quizitems as $sectionname => $items) {

    foreach ($items as $i => $item) {

        $publisher = $item['publisher'];
        $level     = $item['level'];
        $itemid    = $item['id'];
        $quizid    = $quizids[$itemid];

        if (isset($password[$publisher][$level])) {
            $pass = $password[$publisher][$level];
        } else {
            $pass = '';
        }

        $restore = reader_create_restore_object($targetcourse);
        $tempdir = reader_download_tempdir($restore);

        $xmlcontent = reader_download_getfile($readercfg, $itemid, $pass);
        $xml = xmlize($xmlcontent);

        $xml_file = $tempdir.'/moodle.xml';
        $restore->file = $tempdir.'/moodle.zip';

        $fp = fopen($xml_file, 'w+');
        fwrite($fp, $xmlcontent);
        fclose($fp);

        backup_zip($restore);
        //$filelist = list_directories_and_files($tempdir);

        // get any images that are used in the  quiz questions
        reader_download_quiz_images($readercfg, $xml, $targetcourseid);

        if (! $oldcmid = reader_download_old_cmid($xml)) {
            echo html_writer::tag('p', 'Oops, could not add book/quiz: '.$item['title']);
            continue;
        }

        $newcmid = $DB->get_field('course_modules', 'id', array('course' => $targetcourseid, 'module' => $quizmodule->id, 'instance' => $quizid));
        reader_store_backup_ids($restore, 'course_modules', $oldcmid, $newcmid, 's:0:"",');

        $restore->course_startdateoffset = -1900800;
        $restore->restore_restorecatto   = 3;
        $restore->rolesmapping           = array(3 => 3, 4 => 4);
        $restore->mods['quiz']->restore  = 1;
        $restore->mods['quiz']->userinfo = 0;
        $restore->mods['quiz']->granular = 1;
        $restore->mods['quiz']->instances[$oldcmid] = new stdClass();
        $restore->mods['quiz']->instances[$oldcmid]->restore = 1;
        $restore->mods['quiz']->instances[$oldcmid]->userinfo = 0;
        $restore->mods['quiz']->instances[$oldcmid]->restored_as_course_module = $newcmid;

        print_string('process_addquestion', 'reader', $item['title']);

        echo '<center><table width="400px"><tr><td>';
        restore_create_questions($restore, $xml_file);
        echo '</td></tr></table></center>';

        // now we can delete the temporary directory
        reader_remove_directory($tempdir);

        // add the questions for this quiz
        reader_restore_questions($restore, $xml, $quizid);

        // add the book cover for this item
        $imagepath = (empty($item['image']) ? '' : $item['image']);
        reader_download_bookcover($readercfg, $imagepath, $itemid, $targetcourseid);

        // and finally we can add the book
        reader_add_book($item, $quizid);
    }
}

$DB->set_field('reader', 'usecourse', $targetcourseid, array('id' => $reader->id));

if ($readercfg->reader_last_update == 1) {
    $DB->set_field('config_plugins', 'value', time(), array('name' => 'reader_last_update'));
}

if (! empty($end)) {
    echo '<center>';
    echo $OUTPUT->single_button(new moodle_url('/mod/reader/dlquizzes.php', array('id' => $id)), 'Return to Quiz Selection Screen', 'get');
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $targetcourseid)));
    echo '</center>';
}

// ======================
// functions
// ======================

/**
 * reader_show_quizlist
 *
 * @uses $CFG
 * @param xxx $quiz
 * @param xxx $id
 * @param xxx $targetcourseid
 * @param xxx $sectionchoosing
 * @param xxx $section
 * @todo Finish documenting this function
 */
function reader_show_quizlist($quiz, $id, $targetcourseid, $sectionchoosing, $section) {
    global $CFG;

    echo '<script type="text/javascript" src="js/jquery-1.4.2.min.js"></script>'."\n";
    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";

    if (empty($quiz)) {
        $quiz = array();
    }
    $lastkey = 0;

    echo 'var quizzes = new Array();'."\n";
    foreach ($quiz as $key => $value) {
        echo " quizzes[$key] = [$value];\n";
        $lastkey = $key;
    }
    echo 'lastkey = '.$lastkey.";\n";

    echo '$(document).ready(function() {'."\n";
    echo '    loadquiz(0);'."\n";
    echo '});'."\n";

    echo 'function loadquiz(key) {'."\n";
    echo '    if (key == window.lastkey) {'."\n";
    echo '        var end = 1;'."\n";
    echo '    } else {'."\n";
    echo '        var end = 0;'."\n";
    echo '    }'."\n";
    echo '    $.post("'.$CFG->wwwroot.'/mod/reader/dlquizzes_process.php?id='.$id.'&quizid="+window.quizzes[key]+"&courseid='.$targetcourseid.'&sectionchoosing='.$sectionchoosing.'&section='.$section.'&end="+end, function(data) {';
    echo '        $("#installationlog").append(data);'."\n";
    echo '        if (key != window.lastkey) {'."\n";
    echo '            loadquiz(key + 1);'."\n";
    echo '        }'."\n";
    echo '    });'."\n";
    echo '}'."\n";

    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo '<div id="installationlog">Installation in process...</div>';
}

/**
 * reader_download_quizitems
 *
 * @param xxx $quizids
 * @param xxx $password
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_quizitems($quizids, $password) {

    // convert quizids to array if necessary
    if (! is_array($quizids)) {
        $quizids = array($quizids);
    }

    // get reader config data
    $readercfg = get_config('reader');

    // set download url
    $params = array('a'        => 'quizzes',
                    'login'    => $readercfg->reader_serverlogin,
                    'password' => $readercfg->reader_serverpassword);
    $url = new moodle_url($readercfg->reader_serverlink.'/', $params);

    // download quiz data and convert to array
    $params = array('password' => $password,
                    'quiz'     => $quizids,
                    'upload'   =>'true');
    $xml = reader_file($url, $params);
    $xml = xmlize($xml);

    $quizitems = array();
    foreach ($xml['myxml']['#']['item'] as $item) {

        // sanity check on expected fields
        if (! isset($item['@']['publisher'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['@']['level'])) {
            continue; // shouldn't happen !!
        }

        // set section name
        $publisher = $item['@']['publisher'];
        if ($level = $item['@']['level']) {
            $name = $publisher.' - '.$level;
        } else {
            $name = $publisher; // no level
        }

        // initialize items in this section
        if (empty($quizitems[$name])) {
            $quizitems[$name] = array();
        }
        $i = count($quizitems[$name]);

        // add $fields for this quiz item
        $quizitems[$name][$i] = array();
        foreach ($item['@'] as $field => $value) {
            $quizitems[$name][$i][$field] = $value;
        }
    }

    return $quizitems;
}

/**
 * reader_create_targetcourse
 *
 * @param xxx $numsections (optional, default=1)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_targetcourse($numsections=1) {
    // get the first valid $category_id
    $category_list = array();
    $category_parents = array();
    make_categories_list($category_list, $category_parents);
    list($category_id, $category_name) = each($category_list);

    $targetcourse = (object)array(
        'category'      => $category_id, // crucial !!
        'fullname'      => 'Reader Quizzes',
        'shortname'     => 'Reader Quizzes',
        'name'          => 'Reader Quizzes',
        'summary'       => '',
        'summaryformat' => FORMAT_PLAIN, // plain text
        'format'        => 'topics',
        'newsitems'     => 0,
        'startdate'     => time(),
        'visible'       => 0, // hidden
        'numsections'   => $numsections,

        // these don't seem to be necessary in Moodle 2.x
        'enrollable'    =>  1,
        'password'      => 'readeradmin',
        'guest'         =>  0,
    );

    if ($targetcourse = create_course($targetcourse)) {
        return $targetcourse;
    } else {
        return false;
    }
}

/**
 * reader_download_sectionnum
 *
 * @uses $DB
 * @param xxx $targetcourse (passed by reference)
 * @param xxx $sectionname
 * @param xxx $sectionchoosing
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_sectionnum(&$targetcourse, $sectionname, $sectionchoosing) {
    global $DB;

    $sectionnum = 0;
    switch ($sectionchoosing) {

        case READER_BOTTOM_SECTION:
            $params = array('course' => $targetcourse->id);
            if ($coursesections = $DB->get_records('course_sections', $params, 'section DESC', '*', 0, 1)) {
                $coursesection = reset($coursesections);
                if ($coursesection->name == $sectionname) {
                    $sectionnum = $coursesection->section;
                }
            }
            break;

        case READER_SORTED_SECTION:
            $params = array('course' => $targetcourse->id, 'name' => $sectionname);
            if ($coursesections = $DB->get_records('course_sections', $params, 'section', '*', 0, 1)) {
                $coursesection = reset($coursesections);
                $sectionnum = $coursesection->section;
            }
            break;

        case READER_SPECIFIC_SECTION:
        default: // should happen !!
            $params = array('course' => $targetcourse->id, 'section' => $section);
            if ($coursesection = $DB->get_record('course_sections', $params)) {
                $sectionnum = $coursesection->section;
            }
            break;
    }

    // create a new section, if necessary
    if ($sectionnum==0) {
        $numsections = reader_get_numsections($targetcourse);
        $sectionnum = $numsections + 1; // = last section
        $numsections = $numsections + 1;
        reader_set_numsections($targetcourse, $numsections);

        $newsection = (object)array(
            'course'        => $targetcourse->id,
            'section'       => $sectionnum,
            'name'          => $sectionname,
            'summary'       => '',
            'summaryformat' => FORMAT_HTML
        );
        $newsection->id = $DB->insert_record('course_sections', $newsection);
    }

    return $sectionnum;
}

/**
 * reader_download_instance_exists
 *
 * @uses $DB
 * @param xxx $targetcourseid
 * @param xxx $sectionnum
 * @param xxx $modname
 * @param xxx $instancename
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_instance_exists($targetcourseid, $sectionnum, $modname, $instancename) {
    global $DB;
    $select = 'cm.*, cs.section AS sectionnum, m.name AS modname, x.name';
    $from   = '{course_modules} cm '.
              'LEFT JOIN {course_sections} cs ON cm.section = cs.id '.
              'LEFT JOIN {modules} m ON cm.module = m.id '.
              'LEFT JOIN {'.$modname.'} x ON cm.instance = x.id';
    $where  = 'cm.course = ? AND cs.section = ? AND m.name = ? AND x.name = ?';
    $params = array($targetcourseid, $sectionnum, $modname, $instancename);
    return $DB->record_exists_sql("SELECT $select FROM $from WHERE $where", $params);
}

/**
 * reader_create_new_quiz
 *
 * @uses $DB
 * @uses $USER
 * @param xxx $targetcourseid
 * @param xxx $sectionnum
 * @param xxx $quizmodule
 * @param xxx $quizname
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_new_quiz($targetcourseid, $sectionnum, $quizmodule, $quizname) {
    global $DB, $USER;
    $sumgrades = 0;
    $newquiz = (object)array(
        // standard Quiz fields
        'name'          => $quizname,
        'intro'         => ' ',
        'visible'       => 1,
        'introformat'   => FORMAT_HTML, // =1
        'timeopen'      => 0,
        'timeclose'     => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'attempts'      => 0,
        'attemptonlast' => 1,
        'grademethod'   => 1,
        'decimalpoints' => 2,
        'questionsperpage' => 0,
        'shufflequestions' => 0,
        'shuffleanswers' => 1,
        'timemodified'  => time(),
        'timelimit'     => 0,
        'subnet'        => '',
        'quizpassword'  => '',
        'delay1'        => 0,
        'delay2'        => 0,
        'questions'     => '0,',

        // feedback fields
        'feedbacktext'          => array_fill(0, 5, array('text' => '', 'format' => 0)),
        'feedbackboundarycount' => 0,
        'feedbackboundaries'    => array(0 => 0, -1 => 11),

        // these fields may not be necessary in Moodle 2.x
        'sumgrades'     => 0, // reset after adding questions
        'grade'         => 100,
        'adaptive'      => 1,
        'penaltyscheme' => 1,
        'popup'         => 0,

        // standard fields for adding a new cm
        'course'        => $targetcourseid,
        'section'       => $sectionnum,
        'module'        => $quizmodule->id,
        'modulename'    => 'quiz',
        'add'           => 'quiz',
        'update'        => 0,
        'return'        => 0,
        'cmidnumber'    => '',
        'groupmode'     => 0,
        'MAX_FILE_SIZE' => 10485760,
    );

    //$newquiz->instance = quiz_add_instance($newquiz);
    if (! $newquiz->instance = $DB->insert_record('quiz', $newquiz)) {
        return false;
    }
    if (! $newquiz->coursemodule = add_course_module($newquiz) ) {
        error('Could not add a new course module');
    }
    $newquiz->id = $newquiz->coursemodule;
    if (! $sectionid = add_mod_to_section($newquiz) ) {
        error('Could not add the new course module to that section');
    }
    if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newquiz->coursemodule))) {
        error('Could not update the course module with the correct section');
    }

    // if the section is hidden, we should also hide the new quiz activity
    if (! isset($newquiz->visible)) {   // We get the section's visible field status
        $newquiz->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
    }
    set_coursemodule_visible($newquiz->coursemodule, $newquiz->visible);

    // Trigger mod_updated event with information about this module.
    $event = (object)array(
        'courseid'   => $newquiz->course,
        'cmid'       => $newquiz->coursemodule,
        'modulename' => $newquiz->modulename,
        'name'       => $newquiz->name,
        'userid'     => $USER->id
    );
    events_trigger('mod_updated', $event);

    return $newquiz;
}

/**
 * reader_create_restore_object
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $targetcourse
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_restore_object($targetcourse) {
    global $CFG, $DB;

    $restore = (object)array(
        'backup_unique_code'   => time(),
        'backup_name'          => 'moodle.zip',
        'restoreto'            => 1,
        'metacourse'           => 0,
        'users'                => 0,
        'groups'               => 0,
        'logs'                 => 0,
        'user_files'           => 0,
        'course_files'         => 0,
        'site_files'           => 0,
        'messages'             => 0,
        'blogs'                => 0,
        'restore_gradebook_history' => 0,
        'course_id'            => $targetcourse->id,
        'course_shortname'     => $targetcourse->shortname,
        'restore_restorecatto' => 0,
        'deleting'             => '',
        'original_wwwroot'     => $CFG->wwwroot,
        'backup_version'       => 2008030300,
        'mods'                 => array('quiz' => (object)array('instances' => array())),
    );

    if ($modules = $DB->get_records('modules')) {
        foreach ($modules as $module) {
            $restore->mods[$module->name] = (object)array('restore' => 0, 'userinfo' => 0);
        }
    }

    return $restore;
}

/**
 * reader_download_tempdir
 *
 * @uses $CFG
 * @param xxx $restore
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_tempdir($restore) {
    global $CFG;
    $tempdir = '/temp/backup/'.$restore->backup_unique_code;
    make_upload_directory($tempdir);
    return $CFG->dataroot.$tempdir;
}

/**
 * reader_download_getfile
 *
 * @param xxx $readercfg
 * @param xxx $itemid
 * @param xxx $pass
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_getfile($readercfg, $itemid, $pass) {

    $params     = array('getid' => $itemid, 'pass' => $pass);
    $getfileurl = new moodle_url($readercfg->reader_serverlink.'/getfile.php', $params);

    if ($xmlcontent = reader_curlfile($getfileurl)) {
        $xmlcontent = implode('', $xmlcontent);
    } else {
        $xmlcontent = ''; // shouldn't happen !!
    }

    // remove entire category of "Test101" questions (if any)
    $search = '/<QUESTION_CATEGORY>(.*?)<NAME>Default for Test101<\/NAME>(.*?)<\/QUESTION_CATEGORY>\s*/s';
    $xmlcontent = preg_replace($search, '', $xmlcontent);
    //if (preg_match_all($search, $xmlcontent, $matches, PREG_OFFSET_CAPTURE)) {
    //    $i_max = count($matches[0]);
    //    for ($i=0; $i<=$i_max; $i++) {
    //        list($match, $start) = $matches[0][$i];
    //        if (strpos($match, '<NAME>Default for Test101<\/NAME>')) {
    //            $xmlcontent = substr_replace($xmlcontent, '', $start, strlen($match));
    //        }
    //    }
    //}

    return $xmlcontent;
}

/**
 * reader_download_quiz_images
 *
 * @uses $CFG
 * @param xxx $readercfg
 * @param xxx $xml
 * @param xxx $targetcourseid
 * @todo Finish documenting this function
 */
function reader_download_quiz_images($readercfg, $xml, $targetcourseid) {
    global $CFG;

    $images = array();

    // extract $images from $xml
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'])) {
        $categories = &$xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'];
        $c = 0;
        while (isset($categories["$c"]['#']['QUESTIONS']['0']['#'])) {
            $questions = &$categories["$c"]['#']['QUESTIONS']['0']['#'];
            $q = 0;
            while (isset($questions['QUESTION']["$q"]['#'])) {
                $question = &$questions['QUESTION']["$q"]['#'];
                if (isset($question['IMAGE']['0']['#'])) {
                    if ($image = $question['IMAGE']['0']['#']) {
                        $images[] = $image;
                    }
                }
                unset($question);
                $q++;
            }
            unset($questions);
            $c++;
        }
        unset($categories);
    }

    // download $images, if any
    foreach ($images as $image) {
        $basename = basename($image);
        $dirname = dirname($image);
        $dirname = trim($dirname, '/');
        $dirname = ltrim($dirname, './');
        if ($dirname) {
            $dirname = '/'.$dirname;
            make_upload_directory($targetcourseid.$dirname);
        }

        $params = array('imagelink' => urlencode($image));
        $image_file_url = new moodle_url($readercfg->reader_serverlink.'/getfile_quiz_image.php', $params);
        $image_contents = file_get_contents($image_file_url);

        if ($fp = @fopen($CFG->dataroot.'/'.$targetcourseid.$dirname.'/'.$basename, 'w+')) {
            @fwrite($fp, $image_contents);
            @fclose($fp);
        }
    }
}

/**
 * reader_download_old_cmid
 *
 * @param xxx $xml
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_old_cmid($xml) {
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'])) {
        $categories = &$xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'];
        $c = 0;
        while (isset($categories["$c"]['#'])) {
            if (empty($categories["$c"]['#']['CONTEXT']['0']['#']['INSTANCE']['0']['#'])) {
                // do nothing
            } else {
                return $categories["$c"]['#']['CONTEXT']['0']['#']['INSTANCE']['0']['#'];
            }
            $c++;
        }
        unset($categories);
    }
    return 0; // not found - shouldn't happen !!
}

/**
 * reader_store_backup_ids
 *
 * @uses $DB
 * @param xxx $restore
 * @param xxx $type
 * @param xxx $oldid
 * @param xxx $newid
 * @param xxx $info
 * @todo Finish documenting this function
 */
function reader_store_backup_ids($restore, $type, $oldid, $newid, $info) {
    global $DB;
    $backup_ids = (object)array(
        'backup_code' => $restore->backup_unique_code,
        'table_name'  => $type,
        'old_id'      => $oldid,
        'new_id'      => $newid,
        'info'        => $info,
    );
    $DB->insert_record('backup_ids', $backup_ids);
}

/**
 * reader_download_questionids
 *
 * @param xxx $xml
 * @param xxx $restore
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_questionids($xml, $restore) {
    $questionids = array();    // map old question id onto new question id
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTIONS']['0']['#'])) {
        $oldids = $xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTIONS']['0']['#'];
    } else {
        $oldids = ''; // shouldn't happen !!
    }
    $oldids = explode(',', $oldids);
    $oldids = array_filter($oldids); // remove blanks

    foreach ($oldids as $oldid) {
        if ($newid = backup_getid($restore->backup_unique_code, 'question', $oldid)) {
            $questionids[$oldid] = $newid->new_id;
        }
    }
    return $questionids;
}

/**
 * reader_download_questiongrades
 *
 * @param xxx $xml
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_download_questiongrades($xml) {
    $questiongrades = array(); // map old question id onto question grade
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTION_INSTANCES'])) {
        $instances = $xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTION_INSTANCES']['0']['#']['QUESTION_INSTANCE'];
    } else {
        $instances = array(); // shouldn't happen !!
    }
    foreach ($instances as $instance) {
        $oldid = $instance['#']['QUESTION']['0']['#'];
        $grade = $instance['#']['GRADE']['0']['#'];
        $questiongrades[$oldid] = $grade;
    }
    return $questiongrades;
}

/**
 * reader_restore_questions
 *
 * @uses $DB
 * @param xxx $restore
 * @param xxx $xml
 * @param xxx $quizid
 * @todo Finish documenting this function
 */
function reader_restore_questions($restore, $xml, $quizid) {
    global $DB;

    // map old question id onto new question id
    $questionids = reader_download_questionids($xml, $restore);

    // map old question id onto question grade
    $questiongrades = reader_download_questiongrades($xml);

    $sumgrades = 0;
    foreach ($questionids as $oldid => $newid) {
        $question_instance = (object)array(
            'quiz'     => $quizid,
            'question' => $newid,
            'grade'    => $questiongrades[$oldid],
        );
        $sumgrades += $question_instance->grade;
        $DB->insert_record ('quiz_question_instances', $question_instance);
    }
    $DB->set_field('quiz', 'sumgrades', $sumgrades, array('id' => $quizid));
    $DB->set_field('quiz', 'questions', implode(',', $questionids).',0', array('id' => $quizid));
}

/**
 * reader_download_bookcover
 *
 * @uses $CFG
 * @param xxx $readercfg
 * @param xxx $imagepath
 * @param xxx $itemid
 * @param xxx $targetcourseid
 * @todo Finish documenting this function
 */
function reader_download_bookcover($readercfg, $imagepath, $itemid, $targetcourseid) {
    global $CFG;
    if (empty($imagepath)) {
        return; // nothing to do
    }
    make_upload_directory($targetcourseid.'/images');
    $imageurl = new moodle_url($readercfg->reader_serverlink.'/getfile.php', array('imageid' => $itemid));
    $image = file_get_contents($imageurl);
    if ($fp = @fopen($CFG->dataroot.'/'.$targetcourseid.'/images/'.$imagepath, 'w+')) {
        @fwrite($fp, $image);
        @fclose($fp);
    }
}

/**
 * reader_add_book
 *
 * @uses $DB
 * @param xxx $item
 * @param xxx $quizid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_add_book($item, $quizid) {
    global $DB;

    $publisher = $item['publisher'];
    $level     = $item['level'];
    $book = (object)array(
        'publisher'  => $publisher,
        'level'      => $level,
        'difficulty' => $item['difficulty'],
        'name'       => $item['title'],
        'words'      => $item['words'],
        'sametitle'  => $item['sametitle'],
        'quizid'     => $quizid,
        'image'      => $item['image'],
        'length'     => $item['length'],
        'hidden'     => 0,
        'time'       => time(),
    );

    $fields = array('genre', 'fiction', 'maxtime');
    foreach ($fields as $field) {
        if (! empty($item[$field])) {
            $book->$field = $item[$field];
        }
    }

    if ($book->id = $DB->insert_record('reader_books', $book)) {
        return $book;
    } else {
        return false;
    }
}
