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
 * mod/reader/admin/tools/sort_strings.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

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

$title = get_string('sort_strings', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$mainlang = 'en';
if ($dh = opendir($CFG->dirroot.'/mod/reader/lang')) {

    $used = false;
    $task_keys = array();
    $reader_keys = array();

    $helpdir = $CFG->dirroot.'/mod/reader/lang/en/help/reader';
    $helpdir = ''; // str_replace('20', '19', $helpdir);

    sort_used($mainlang, $used, $task_keys, $reader_keys, $helpdir);
    sort_unused($mainlang, $used);

    while ($lang = readdir($dh)) {
        if ($lang=='.' || $lang=='..' || $lang=='CVS' || $lang==$mainlang) {
            continue;
        }
        if (! is_dir($CFG->dirroot.'/mod/reader/lang/'.$lang)) {
            continue;
        }
        sort_used($lang, $used, $task_keys, $reader_keys);
        sort_unused($lang, $used);
    }
}
closedir($dh);

echo html_writer::tag('p', 'All done');
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

//////////////////////////////////////////////////////////
// Functions only below this line
//////////////////////////////////////////////////////////

/**
 * escape_string
 *
 * @param xxx $str
 * @return xxx
 * @todo Finish documenting this function
 */
function escape_string($str)  {
    // unescape
    $str = strtr($str, array('\\\\' => "\\", "\\'"=>"'", '\\"'=>'"'));
    // escape
    $str = strtr($str, array('\\' => "\\\\", "'"=>"\\'")); // , '"'=>'\\"'
    return $str;
}

/**
 * sort_used
 *
 * @uses $CFG
 * @param xxx $lang
 * @param xxx $used (passed by reference)
 * @param xxx $task_keys (passed by reference)
 * @param xxx $reader_keys (passed by reference)
 * @param xxx $helpdir (optional, default='')
 * @return xxx
 * @todo Finish documenting this function
 */
function sort_used($lang, &$used, &$task_keys, &$reader_keys, $helpdir='')  {
    global $CFG;
    $langfile = $CFG->dirroot.'/mod/reader/lang/'.$lang.'/reader.php';
    $backupfile = $CFG->dirroot.'/mod/reader/lang/'.$lang.'/reader.backup.php';
    $helpfilesdir = $CFG->dirroot.'/mod/reader/lang/'.$lang.'/reader.backup.php';

    if (! file_exists($langfile)) {
        return false;
    }

    // get strings
    $string = array();
    if ($used && file_exists($backupfile)) {
        include $backupfile; // non-english lang pack
    }
    include $langfile;

    if ($helpdir) {
        append_help_strings($helpdir, $string);
    }

    if (! isset($string['modulename'])) {
        $string['modulename'] = 'Reader';
    }
    if (! isset($string['modulenameplural'])) {
        $string['modulenameplural'] = 'Readers';
    }
    if (count($task_keys)) {
        append_standard_strings($lang, 'task', $string, $task_keys);
    }
    if (count($reader_keys)) {
        append_standard_strings($lang, 'reader', $string, $reader_keys);
    }

    // get keys
    ksort($string);
    $keys = array_keys($string);

    if ($used) {
        $keys_used = array_keys($used);
        $keys = array_intersect($keys_used, $keys);
        $keys_missing = array_diff($keys_used, $keys);
    } else {
        $used = $string;
        $keys_missing = array();
        locate_standard_strings($lang, 'task', $used, $task_keys);
        //locate_standard_strings($lang, 'reader', $used, $reader_keys);
    }

    // move modulename and modulenameplural to the top of the list
    $keys = array_merge(
        preg_grep('/^(module|plugin)(\w+)$/', $keys), // essential
        preg_grep('/^subplugin(\w+)$/', $keys), // subplugin
        preg_grep('/^reader:(\w+)$/', $keys), // roles
        preg_grep('/^(module|subplugin|plugin|(reader:))(\w+)$/', $keys, PREG_GREP_INVERT)
    );

    $essential = false;
    $subplugin = false;
    $roles = false;
    $more = false;

    if (! $fh = @fopen($langfile, 'w')) {
        echo html_writer::start_tag('p');
        echo 'Could not write to file: '.$langfile.html_writer::empty_tag('br');
        echo 'Please file check permissions.';
        echo html_writer::end_tag('p');
        return false;
    }

    echo html_writer::start_tag('p');
    echo 'processing file: '.$langfile;

    fwrite($fh, '<'.'?'.'php'."\n");
    foreach ($keys as $key) {

        // output comments, if required
        switch (true) {
            case substr($key, 0, 6)=='module':
            case substr($key, 0, 6)=='plugin':
                if (! $essential) {
                    fwrite($fh, '// essential strings'."\n");
                    $essential = true;
                }
                break;

            case substr($key, 0, 9)=='subplugin':
                if (! $subplugin) {
                    fwrite($fh, "\n".'// subplugin strings'."\n");
                    $subplugin = true;
                }
                break;

            case substr($key, 0, 7)=='reader:':
                if (! $roles) {
                    fwrite($fh, "\n".'// roles strings'."\n");
                    $roles = true;
                }
                break;

            default:
                if (! $more) {
                    fwrite($fh, "\n".'// more strings'."\n");
                    $more = true;
                }
        } // end switch

        // output this $string
        fwrite($fh, '$'."string['$key'] = '".escape_string($string[$key])."';\n");
    }

    if (count($keys_missing)) {
        $repeat = 50;
        fwrite($fh, "\n".'/'.'* '.str_repeat('=', $repeat)."\n");
        fwrite($fh, '** '.'these strings are also used by the Reader module'."\n");
        fwrite($fh, '** '.str_repeat('=', $repeat)."\n");
        foreach ($keys_missing as $key) {
            // output this $string
            fwrite($fh, '$'."string['$key'] = '".escape_string($used[$key])."';\n");
        }
        fwrite($fh, '** '.str_repeat('=', $repeat).' *'.'/'."\n");
    }

    // skip closing PHP tag
    // fwrite($fh, '?'.'>');

    fclose($fh);

    echo ' - '.count($keys).' strings written to file';
    echo html_writer::end_tag('p');
}

/**
 * sort_unused
 *
 * @uses $CFG
 * @param xxx $lang
 * @param xxx $used (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function sort_unused($lang, &$used)  {
    global $CFG;
    $backupfile = $CFG->dirroot.'/mod/reader/lang/'.$lang.'/reader.backup.php';
    $unusedfile = $CFG->dirroot.'/mod/reader/lang/'.$lang.'/reader.unused.php';

    if (! file_exists($backupfile)) {
        return false;
    }

    // get strings
    $string = array();
    include $backupfile;

    // get keys
    ksort($string);
    $keys = array_keys($string);

    if ($used) {
        $keys_used = array_keys($used);
        $keys = array_diff($keys, $keys_used);
    }

    if (! $fh = @fopen($unusedfile, 'w')) {
        echo html_writer::start_tag('p');
        echo 'Could not write to file: '.$unusedfile.html_writer::empty_tag('br');
        echo 'Please file check permissions.';
        echo html_writer::end_tag('p');
        return false;
    }

    echo html_writer::start_tag('p');
    echo 'processing file: '.$unusedfile;

    fwrite($fh, '<'.'?'.'php'."\n");
    fwrite($fh, '// these strings are not used by the Reader module'."\n");
    foreach ($keys as $key) {
        // output this $string
        fwrite($fh, '$'."string['$key'] = '".escape_string($string[$key])."';\n");
    }
    // skip closing PHP tag
    // fwrite($fh, '?'.'>');
    fclose($fh);

    echo ' - '.count($keys).' strings written to file';
    echo html_writer::end_tag('p');
}

/**
 * locate_standard_strings
 *
 * @uses $CFG
 * @param xxx $lang
 * @param xxx $mod
 * @param xxx $array(passed by reference)
 * @param xxx $keys (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function locate_standard_strings($lang, $mod, &$array, &$keys)  {
    global $CFG;
    $langfile = $CFG->dirroot.'/lang/'.$lang.'/'.$mod.'.php';

    if (! file_exists($langfile)) {
        return false;
    }

    $string = array();
    include $langfile;

    foreach (array_keys($array) as $key) {
        if ($pos = strpos($key, ':')) {
            // roles e.g. reader:attempt
            $keys[] = $key;
        } else if (isset($string[$key]) && $string[$key]==$array[$key]) {
            $keys[] = $key;
        }
    }
}

/**
 * append_standard_strings
 *
 * @uses $CFG
 * @param xxx $lang
 * @param xxx $mod
 * @param xxx $array(passed by reference)
 * @param xxx $keys (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function append_standard_strings($lang, $mod, &$array, &$keys)  {
    global $CFG;
    $langfile = $CFG->dataroot.'/lang/'.$lang.'/'.$mod.'.php';

    if (! file_exists($langfile)) {
        return false;
    }

    $string = array();
    include $langfile;

    foreach ($keys as $key) {
        if ($pos = strpos($key, ':')) {
            // e.g. $key = reader:attempt
            $modkey = $mod.substr($key, $pos);
        } else {
            $modkey = $key;
        }
        if (isset($string[$modkey]) && empty($array[$key])) {
            $array[$key] = $string[$modkey];
        }
    }
}

/**
 * append_help_strings
 *
 * @param xxx $helpdir
 * @param xxx $string (passed by reference)
 * @todo Finish documenting this function
 */
function append_help_strings($helpdir, &$string)  {
    $search = array(
        '/\s*<h1>.*?<\/h1>/is', // remove h1 tag and content
        '/\s*<\/?div[^>]*>/is', // remove div tags
        '/(?<=\w)\s+(?=\w)/is', // remove line breaks within text
        '/^\s+/m',              // remove indent
        '/<br \/>\s*/is',       // convert <br> to newline
        '/\s*<\/?p>/is',        // convert <p> tags to newline
        '/<\/?i>/i',            // convert <i> tags to Moodle format
        '/<\/?b>/i',            // convert <b> tags to Moodle format
    );
    $replace = array(
        '', '', ' ', '', "\n", "\n", '*', '**'
    );
    if (file_exists($helpdir)) {
        if ($dh = opendir($helpdir)) {
            while ($item = readdir($dh)) {
                if (substr($item, 0, 1)=='.') {
                    continue;
                }
                $pos = strrpos($item, '.');

                $ext = substr($item, $pos + 1);
                if ($ext=='htm' || $ext=='html') {

                    $stringname = substr($item, 0, $pos).'_help';
                    if (empty($string[$stringname])) {
                        $contents = file_get_contents($helpdir.'/'.$item);
                        $contents = preg_replace($search, $replace, $contents);
                        $string[$stringname] = trim($contents);
                    }
                }
            }
            closedir($dh);
        }
    } else {
        print '<p><span style="color: red;">Oops</span>, $helpdir does not exist: '.$helpdir.'</p>';
    }
}
