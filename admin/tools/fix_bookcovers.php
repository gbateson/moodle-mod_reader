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
require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/moodlereadernet.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/mreaderorg.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/lib/xmlize.php');

require_login(SITEID);

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
$reader->req('managetools');

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url('/mod/reader/admin/tools/fix_bookcovers.php', $params));

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

// get config settings for the Reader module
$readercfg = get_config('mod_reader');

$action = optional_param('action', '', PARAM_ALPHA);
reader_print_images_form($readercfg, $action);

$courseid  = $readercfg->usecourse;
make_upload_directory('reader/images');

if ($action) {
    if ($enable_mreader = $readercfg->mreaderenable) {
        $remotesite = new reader_remotesite_mreaderorg();
    } else {
        $server   = $readercfg->serverurl;
        $login    = $readercfg->serverusername;
        $password = $readercfg->serverpassword;
        $remotesite = new reader_remotesite_moodlereadernet($server, $login, $password);
    }
    switch ($action) {
        case 'all'       : reader_fetch_all_book_images($readercfg, $remotesite); break;
        case 'attempted' : reader_fetch_attempted_book_images($readercfg, $remotesite); break;
        case 'installed' : reader_fetch_installed_book_images($readercfg, $remotesite); break;
    }
}

reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();

/**
 * reader_fetch_all_book_images
 *
 * @param xxx $readercfg
 * @todo Finish documenting this function
 */
function reader_fetch_all_book_images($readercfg, $remotesite) {

    if (! $itemids = reader_get_itemids($readercfg, $remotesite)) {
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
    while (($quizids = array_splice($ids, 0, 10)) && count($quizids)) {
        $types = array(reader_downloader::BOOKS_WITH_QUIZZES,
                       reader_downloader::BOOKS_WITHOUT_QUIZZES);
        foreach ($types as $type) {
            $items = $remotesite->download_bookcovers($quizids);
            foreach ($items as $item) {
                $images[$item->id] = $item->image;
            }
        }
    }

    echo '<ul>';
    foreach ($itemids as $publisher => $levels) {
        foreach ($levels as $level => $itemnames) {
            foreach ($itemnames as $itemname => $itemid) {
                if (isset($images[$itemid])) {
                    reader_fetch_book_image($readercfg, $remotesite, $itemids, $remotenames, $publisher, $level, $itemname, $images[$itemid]);
                }
            }
        }
    }
    echo '</ul>';

    reader_print_all_done();
}

/**
 * reader_fetch_installed_book_images
 *
 * @uses $DB
 * @param xxx $readercfg
 * @todo Finish documenting this function
 */
function reader_fetch_installed_book_images($readercfg, $remotesite) {
    global $DB;

    $select = 'publisher <> ? AND publisher <> ? AND publisher <> ? AND publisher <> ? AND level <> ?';
    $params = array('Extra points', 'Extra_Points', 'testing', '_testing_only', '99');
    if (! $books = $DB->get_records_select('reader_books', $select, $params, 'publisher,level,name')) {
        echo 'Oops - no books have been installed on this site';
        return false;
    }

    if (! $itemids = reader_get_itemids($readercfg, $remotesite)) {
        return false;
    }
    $remotenames = reader_get_remotenames($readercfg);

    echo '<ul>';
    foreach ($books as $book) {
        if (empty($book->image)) {
            continue; // no image - unexpected !!
        }
        reader_fetch_book_image($readercfg, $remotesite, $itemids, $remotenames, $book->publisher, $book->level, $book->name, $book->image);
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
function reader_fetch_attempted_book_images($readercfg, $remotesite) {
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

    if (! $itemids = reader_get_itemids($readercfg, $remotesite)) {
        return false;
    }
    $remotenames = reader_get_remotenames($readercfg);

    echo '<ul>';
    foreach ($books as $book) {
        if (empty($book->image)) {
            continue; // no image - unexpected !!
        }
        reader_fetch_book_image($readercfg, $remotesite, $itemids, $remotenames, $book->publisher, $book->level, $book->name, $book->image);
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
function reader_fetch_book_image($readercfg, $remotesite, $itemids, $remotenames, $publisher, $level, $itemname, $imagefile) {
    global $CFG, $DB;

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

    $select = 'publisher = ? AND level = ? AND name = ? AND quizid <> ?';
    $params = array($publisher, $level, $itemname, 0);
    if ($DB->record_exists_select('reader_books', $select, $params)) {
        $type = reader_downloader::BOOKS_WITH_QUIZZES;
    } else {
        $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
    }
    $remote_image_url = $remotesite->get_image_url($type, $itemid);
    $remote_image_post = $remotesite->get_image_post($type, $itemid);
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

    if (! $contents = download_file_content($remote_image_url, null, $remote_image_post)) {
        echo '<li><span style="color: red;">OOPS:</span> remote image missing ('.$imagefile.'): ';
        echo '<a target="_blank" href="'.$remote_image_url.'">'.$fullname.'</a></li>';
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
            return;
        }
        echo '<li><span style="color: red;">DELETE:</span> Non-image file: ';
        echo '<a target="_blank" href="'.$remote_image_url.'">'.$fullname.'</a></li>';
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
function reader_get_itemids($readercfg, $remotesite) {
    static $done = false;
    if ($done) {
        echo 'Oops, reader_get_itemids has been called twice !!';
        die;
    }
    $done = true;

    $itemids = array();

    $types = array(reader_downloader::BOOKS_WITH_QUIZZES,
                   reader_downloader::BOOKS_WITHOUT_QUIZZES);
    foreach ($types as $type) {
        $items = $remotesite->download_items($type, false);
        foreach ($items as $item) {
            $publisher = $item->publisher;
            $level     = $item->level;
            $itemid    = $item->id;
            $itemname  = $item->title;
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
    global $CFG, $PAGE;

    // start form
    $params = array('method' => 'post', 'action' => $PAGE->url);
    echo html_writer::start_tag('form', $params);
    echo html_writer::start_tag('div');

    // default $action
    if (empty($action)) {
        if (strpos($CFG->wwwroot, 'localhost')) {
            $action = 'attempted'; // development site
        } else {
            $action = 'installed'; // production site
        }
    }

    // prompt
    echo get_string('chooseaction', 'mod_reader').' ';
    echo html_writer::empty_tag('br');

    // actions
    $actions = array('all', 'installed', 'attempted');
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
