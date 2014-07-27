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
 * mod/reader/admin/tools/fix_bookcovers.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/lib/xmlize.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $cm = null;
    $course = null;
    $reader = null;
}
$reader = mod_reader::create($reader, $cm, $course);

require_login(SITEID);
if (class_exists('context_system')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('moodle/site:config', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

// set title
$title = get_string('fix_bookcovers', 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('mod_reader', 'admin_tools');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start();

$action = optional_param('action', '', PARAM_ALPHA);
reader_print_images_form($readercfg, $action);

$readercfg = get_config('mod_reader');
$courseid  = $readercfg->usecourse;
make_upload_directory('reader/images');

switch ($action) {
    case 'all'       : reader_fetch_all_book_images($readercfg); break;
    case 'my'        : reader_fetch_my_book_images($readercfg);  break;
    case 'attempted' : reader_fetch_attempted_book_images($readercfg); break;
}

echo $output->box_end();
echo $output->footer();

/**
 * reader_fetch_all_book_images
 *
 * @param xxx $readercfg
 * @todo Finish documenting this function
 */
function reader_fetch_all_book_images($readercfg) {

    $server    = $readercfg->serverlink;
    $login     = $readercfg->serverlogin;
    $password  = $readercfg->serverpassword;
    $courseid  = $readercfg->usecourse;

    if (! $itemids = reader_get_itemids($readercfg)) {
        return false;
    }
    $remotenames = reader_get_remotenames($readercfg);

    $ids = array();
    foreach ($itemids as $publisher => $levels) {
        foreach ($levels as $level => $itemnames) {
            foreach ($itemnames as $itemname => $itemid) {
                $ids[] = $itemid;
            }
        }
    }

    $images = array();
    while (($quizids = array_splice($ids, 0, 100)) && count($quizids)) {
        $params = array('a' => 'quizzes', 'login' => $login, 'password' => $password);
        $xml_file = new moodle_url($server.'/index.php', $params);

        $params = array('password' => $password, 'quiz' => $quizids, 'upload' => 'true');
        $xml = reader_file($xml_file, $params);

        $xml = xmlize($xml);
        if (isset($xml['myxml']['#']['item'])) {
            $item = &$xml['myxml']['#']['item'];
            $i = 0;
            while (isset($item["$i"])) {
                if (isset($item["$i"]['@']['id']) && isset($item["$i"]['@']['image'])) {
                    $itemid = $item["$i"]['@']['id'];
                    $images[$itemid] = $item["$i"]['@']['image'];
                }
                $i++;
            }
            unset($item, $itemid);
        }
        unset($xml_file, $xml, $params);
    }

    echo '<ul>';
    foreach ($itemids as $publisher => $levels) {
        foreach ($levels as $level => $itemnames) {
            foreach ($itemnames as $itemname => $itemid) {
                if (isset($images[$itemid])) {
                    reader_fetch_book_image($readercfg, $itemids, $remotenames, $publisher, $level, $itemname, $images[$itemid]);
                }
            }
        }
    }
    echo '</ul>';

    reader_print_all_done();
}

/**
 * reader_fetch_my_book_images
 *
 * @uses $DB
 * @param xxx $readercfg
 * @todo Finish documenting this function
 */
function reader_fetch_my_book_images($readercfg) {
    global $DB;

    $select = 'publisher <> ? AND publisher <> ? AND publisher <> ? AND publisher <> ? AND level <> ?';
    $params = array('Extra points', 'Extra_Points', 'testing', '_testing_only', '99');
    if (! $books = $DB->get_records_select('reader_books', $select, $params, 'publisher,level,name')) {
        echo 'Oops - no books have been installed on this site';
        return false;
    }

    if (! $itemids = reader_get_itemids($readercfg)) {
        return false;
    }
    $remotenames = reader_get_remotenames($readercfg);

    echo '<ul>';
    foreach ($books as $book) {
        if (empty($book->image)) {
            continue; // no image - unexpected !!
        }
        reader_fetch_book_image($readercfg, $itemids, $remotenames, $book->publisher, $book->level, $book->name, $book->image);
    }
    echo '</ul>';

    reader_print_all_done();
}

/**
 * reader_fetch_attempted_book_images
 *
 * @uses $DB
 * @param xxx $readercfg
 * @todo Finish documenting this function
 */
function reader_fetch_attempted_book_images($readercfg) {
    global $DB;

    // get all books that have been attempted
    $select = 'rb.id AS bookid, COUNT(*) AS countattempts';
    $from   = '{reader_attempts} ra JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'publisher <> ?';
    $params = array('Extra Points');
    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY rb.id", $params)) {
        list($select, $params) = $DB->get_in_or_equal(array_keys($books));
        $books = $DB->get_records_select('reader_books', "id $select", $params, 'publisher,level,name');
    }

    if (empty($books)) {
        echo 'Oops - no books have been attempted on this site';
        return false;
    }

    if (! $itemids = reader_get_itemids($readercfg)) {
        return false;
    }
    $remotenames = reader_get_remotenames($readercfg);

    echo '<ul>';
    foreach ($books as $book) {
        if (empty($book->image)) {
            continue; // no image - unexpected !!
        }
        reader_fetch_book_image($readercfg, $itemids, $remotenames, $book->publisher, $book->level, $book->name, $book->image);
    }
    echo '</ul>';

    reader_print_all_done();
}

/**
 * reader_fetch_book_image
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $readercfg
 * @param xxx $itemids
 * @param xxx $remotenames
 * @param xxx $publisher
 * @param xxx $level
 * @param xxx $itemname
 * @param xxx $imagefile
 * @todo Finish documenting this function
 */
function reader_fetch_book_image($readercfg, $itemids, $remotenames, $publisher, $level, $itemname, $imagefile) {
    global $CFG, $DB;

    $server    = $readercfg->serverlink;
    $login     = $readercfg->serverlogin;
    $password  = $readercfg->serverpassword;
    $courseid  = $readercfg->usecourse;

    if (empty($remotenames[$publisher][$level][$itemname])) {
        $remotename = $itemname; // local name == remote name
        $displayname = $itemname;
    } else {
        $remotename = $remotenames[$publisher][$level][$itemname];
        $displayname = "$itemname (remote name: $remotename)";
    }
    $fullname = $publisher.' ('.$level.'): '.$itemname;

    if (empty($itemids[$publisher][$level][$remotename])) {
        echo '<li><span style="color: red;">OOPS:</span> Image ID not specified: '.$fullname.'</li>';
        return;
    }
    $itemid = $itemids[$publisher][$level][$remotename];

    $remote_image_file = new moodle_url($server.'/getfile.php', array('imageid' => $itemid));
    $local_image_file = $CFG->dataroot."/reader/images/$imagefile";

    $local_file_exists = file_exists($local_image_file);
    $is_image_file = reader_is_image_file($local_image_file);

    if ($local_file_exists && ! $is_image_file) {
        echo "<li>Removing old book cover: $displayname</li>";
        if (! unlink($local_image_file)) {
            echo '<li><span style="color: red;">OOPS:</span> Could not delete non-image file ('.$local_image_file.')</li>';
            return;
        }
        echo '<li><span style="color: red;">DELETE:</span> Non-image file ('.$local_image_file.')</li>';
        $local_file_exists = false;
    }

    if ($local_file_exists) {
        return; // book cover already exists
    }

    if (! $contents = file_get_contents($remote_image_file)) {
        echo '<li><span style="color: red;">OOPS:</span> remote image missing ('.$imagefile.'): ';
        echo '<a target="_blank" href="'.$remote_image_file.'">'.$fullname.'</a></li>';
        return;
    }

    if (! file_put_contents($local_image_file, $contents)) {
        echo '<li><span style="color: red;">OOPS:</span> Could not save image ('.$local_image_file.')</li>';
        return;
    }

    if (! reader_is_image_file($local_image_file)) {
        //print '<pre>'.htmlspecialchars($contents).'</pre>';
        if (! unlink($local_image_file)) {
            echo '<li><span style="color: red;">OOPS:</span> Could not delete non-image file ('.$local_image_file.')</li>';
            continue;
        }
        echo '<li><span style="color: red;">DELETE:</span> Non-image file: ';
        echo '<a target="_blank" href="'.$remote_image_file.'">'.$fullname.'</a></li>';
        return;
    }

    echo "<li>Downloaded $publisher: $displayname ($imagefile)</li>";
}

/**
 * reader_is_image_file
 *
 * @param xxx $file
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_is_image_file($file) {
    if (! file_exists($file)) {
        return false; // file does not exist
    }
    if (! is_file($file)) {
        return false; // file is not a file
    }
    if (! filesize($file)) {
        return false; // file is zero size
    }
    $is_image_file = true;
    if (function_exists('finfo_open')) {
        if ($finfo = finfo_open(FILEINFO_MIME)) {
            if ($finfo_file = finfo_file($finfo, $file)) {
                if (strpos($finfo_file, 'image/jpeg')===false && strpos($finfo_file, 'image/gif')===false) {
                    $is_image_file = false;
                }
            }
            finfo_close($finfo);
        }
    }
    return $is_image_file;
}

/**
 * reader_get_itemids
 *
 * @param xxx $readercfg
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_itemids($readercfg) {

    static $done = false;
    if ($done) {
        echo 'Oops, reader_get_itemids has been called twice !!';
        die;
    }
    $done = true;

    $itemids = array();

    $server    = $readercfg->serverlink;
    $login     = $readercfg->serverlogin;
    $password  = $readercfg->serverpassword;

    $params = array('a' => 'publishers', 'login' => $login, 'password' => $password);
    $index_files  = array('index.php', 'index-noq.php');

    foreach ($index_files as $index_file) {
        $xml_file = new moodle_url($server.'/'.$index_file, $params);

        if (! $xml = reader_curlfile($xml_file)) {
            echo 'Oops - no images found ('.$index_file.')';
            return false;
        }

        if (! $xml = xmlize(implode('', $xml))) {
            echo 'Oops - could not create xml for images ('.$index_file.')';
            return false;
        }

        if (! isset($xml['myxml']['#']['item'])) {
            echo 'Oops - no item tag in images xml ('.$index_file.')';
            return false;
        }

        $i = 0;
        while (isset($xml['myxml']['#']['item'][$i])) {
            $publisher = $xml['myxml']['#']['item'][$i]['@']['publisher'];
            $level     = $xml['myxml']['#']['item'][$i]['@']['level'];
            $itemid    = $xml['myxml']['#']['item'][$i]['@']['id'];
            $needpass  = $xml['myxml']['#']['item'][$i]['@']['needpass'];
            $itemname  = $xml['myxml']['#']['item'][$i]['#'];
            $i++;

            if ($publisher=='Extra points' || $publisher=='Extra_Points' || $publisher=='testing' || $publisher=='_testing_only') {
                continue;
            }

            if (empty($itemids[$publisher])) {
                $itemids[$publisher] = array();
            }
            if (empty($itemids[$publisher][$level])) {
                $itemids[$publisher][$level] = array();
            }
            $itemids[$publisher][$level][$itemname] = $itemid;
        }
    }

    if (! count($itemids)) {
        echo 'Oops - no images found in images xml';
        return false;
    }

    foreach (array_keys($itemids) as $publisher) {
        foreach (array_keys($itemids[$publisher]) as $level) {
            asort($itemids[$publisher][$level]);
        }
        asort($itemids[$publisher]);
    }
    asort($itemids);

    return $itemids; // this is what we expect
}

/**
 * reader_get_remotenames
 *
 * @param xxx $readercfg
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_remotenames($readercfg) {
    return array(
        // $publisher => $level => $itemnames
        'Macmillan' => array(
            'Elementary' => array(
                // local book name => remote book name
                'Dawson Creek--Long Hot Summer' => "Dawson's Creek 2: Long Hot Summer",
                'Dawson Creek--Major Meltdown' => "Dawson's Creek 3: Major Meltdown",
                'Dawson Creek -- Shifting into Overdrive' => "Dawson's Creek 4: Shifting Into Overdrive"
            )
        )
    );
}

/**
 * reader_print_images_form
 *
 * @uses $CFG
 * @param xxx $readercfg
 * @param xxx $action
 * @todo Finish documenting this function
 */
function reader_print_images_form($readercfg, $action) {
    global $CFG;

    // start form
    $params = array('method' => 'post', 'action' => $CFG->wwwroot.'/mod/reader/admin/tools/fix_bookcovers.php');
    echo html_writer::start_tag('form', $params);
    echo html_writer::start_tag('div');

    // default $action
    if (empty($action)) {
        $action = 'my';
    }

    // prompt
    echo get_string('whichbooks', 'mod_reader').' ';
    echo html_writer::empty_tag('br');

    // actions
    $actions = array('all', 'my', 'attempted');
    foreach ($actions as $a) {
        $params = array('type' => 'radio', 'name' => 'action', 'value' => $a);
        if ($action==$a) {
            $params['checked'] = 'checked';
        }
        echo html_writer::empty_tag('input', $params).' ';
        echo get_string($a.'books', 'mod_reader');
        echo html_writer::empty_tag('br');
    }

    // submit button
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));

    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

/**
 * reader_print_all_done
 *
 * @todo Finish documenting this function
 */
function reader_print_all_done() {
    echo html_writer::tag('p', get_string('alldone', 'mod_reader'));
}

/**
 * reader_reset_timeout
 *
 * @param xxx $moretime (optional, default=300)
 * @todo Finish documenting this function
 */
function reader_reset_timeout($moretime=300) {
    static $timeout = 0;
    $time = time();
    if ($timeout < $time) {
        $timeout = ($time + $moretime);
        set_time_limit($moretime);
    }
}
