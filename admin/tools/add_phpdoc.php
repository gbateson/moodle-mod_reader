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
 * mod/reader/admin/tools/add_phpdoc.php
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

// set title
$title = get_string('add_phpdoc', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header()."\n";
echo $OUTPUT->box_start()."\n";
echo html_writer::start_tag('ul')."\n";

$paths = array(
    str_replace('\\', '/', $CFG->dirroot).'/mod/reader'
);

$skip_dirs = array('.svn', 'CVS', 'img', 'js', 'lang', 'lib', 'pix', 'quiz', 'tools');

$path = current($paths);
while ($path) {
    // get items within this directory
    $items = new DirectoryIterator($path);
    foreach ($items as $item) {
        if ($item->isDot() || substr($item, 0, 1)=='.' || substr($item, -4)=='.old') {
            continue;
        }
        if ($item->isDir() && in_array($item, $skip_dirs)) {
            continue;
        }
        if ($item->isDir()) {
            $paths[] = $path.'/'.$item;
        } else if ($item->isFile()) {
            reader_fix_file($path.'/'.$item);
        }
    }
    $path = next($paths);
}

echo html_writer::end_tag('ul')."\n";
echo html_writer::tag('p', 'All done')."\n";
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end()."\n";
echo $OUTPUT->footer();

/**
 * reader_fix_file
 *
 * @uses $CFG
 * @param xxx $path
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fix_file($path) {
    global $CFG;

    if (! file_exists($path)) {
        die('oops, path is not a file: '.$path);
        return false;
    }

    if (! preg_match('/\w+\.(\w+)$/', $path, $matches)) {
        return false; // no file extension
    }
    $filetype = $matches[1];
    $filepath = substr($path, strlen($CFG->dirroot.'/'));

    if (! $contents = @file_get_contents($path)) {
        die('oops, could not read file: '.$path);
        return false;
    }

    $update = false;

    $remove_old_phpdocs = false;
    if ($remove_old_phpdocs && ($filetype=='php')) { // $filetype=='js'
        reader_remove_old_phpdocs($contents, $update);
    }

    switch ($filetype) {
        case 'js':
            // fix js functions and methods
            //reader_fix_copyright($contents, $update, $filepath, $filetype);
            //reader_fix_file_contents($contents, $update, 1);
            //reader_fix_file_contents($contents, $update, 2);
            break;

        case 'php':
            // fix php functions/methods and classes
            reader_fix_copyright($contents, $update, $filepath, $filetype);
            reader_fix_file_contents($contents, $update, 3);
            reader_fix_file_contents($contents, $update, 4);
            break;
    }

    if ($update) {
        if (! @file_put_contents($path, $contents)) {
            // print '<pre>'.htmlspecialchars($contents).'</pre>';
            die('Oops, could not write to file: '.$path);
        }
        $text = "updated $path";
    } else {
        $text = html_writer::tag('span', "skipped $path", array('style' => 'color: #999999;'));
    }
    echo html_writer::tag('li', $text)."\n";
}

/**
 * reader_remove_old_phpdocs
 *
 * @param xxx $contents (passed by reference)
 * @param xxx $update (passed by reference)
 * @todo Finish documenting this function
 */
function reader_remove_old_phpdocs(&$contents, &$update) {
    $count = 0;

    // remove all previous phpDoc commeents
    // maybe we should limit this to functions?
    $search = '/\s*\/\*\*(.*?)\*\//s';
    $contents = preg_replace($search, '', $contents, -1, $count);

    if ($count) {
        $update = true;
    }

    $search = '/\s*'.'\/\/ get( the)? parent class[^\n\r]*'.'/s';
    $contents = preg_replace($search, '', $contents, -1, $count);

    if ($count) {
        $update = true;
    }

    // remove old GNU notice (a.k.a. boilerplate)
    $search = '/\s*'.preg_quote('// This file '.'is part of Moodle'.' - http://moodle.org/', '/').'(.*?)\n+(?=[^\/])/s';
    $contents = preg_replace($search, "\n", $contents, 1, $count);

    if ($count) {
        $update = true;
    }
}

/**
 * reader_fix_file_contents
 *
 * @param string $contents (passed by reference) the php/js file contents
 * @param boolean $update (passed by reference) true if $contents was modified, false otherwise
 * @param integer $type 1=js functions, 2=js methods, 3=php functions/methods, 4=php classes
 * @return void but $contents and $update may be modified
 * @todo Finish documenting this function
 */
function reader_fix_file_contents(&$contents, &$update, $type) {
    $lastline   = '((?:^|\{|\}|;|-|,)[ \t]*(?:\/\/[^\n\r]*)?[\n\r]*)';
    $comments   = '((?:[ \t]*\/\/[^\n\r]*[\n\r]+)*)';
    $indent     = '([ \t]*)';
    $parameters = '([^\n\r{]*)';

    switch ($type) {
        case 1:
            // javascript functions
            // e.g. function FooBar(x, y, z) {
            $keywords = '(function[ \t]+)';
            $blockname = '(\w+)[ \t]*';
            $search = '/'.$lastline.$comments.$indent.$keywords.$blockname.$parameters.'\{/s';
            break;

        case 2:
            // javascript methods
            // e.g. this.FooBar = function (x, y, z) {
            // e.g. FooBar: function(x, y, z) {
            $blockname = '(\w+(?:\.\w+)*[ \t]*)';
            $keywords = '([=:][ \t]*function[ \t]*)';
            $search = '/'.$lastline.$comments.$indent.$blockname.$keywords.$parameters.'\{/s';
            break;

        case 3:
            // php functions/methods
            // e.g. static public function FooBar($x, $y=0, $z="z") {
            $keywords = '((?:(?:public|private|protected|static)[ \t]+)*function[ \t]+)';
            $blockname = '(\w+)[ \t]*';
            $search = '/'.$lastline.$comments.$indent.$keywords.$blockname.$parameters.'\{/s';
            break;

        case 4:
            // php classes
            // e.g. abstract class FooBar extends Foo {
            $keywords = '((?:abstract[ \t]+)?(?:class|interface)[ \t]+)';
            $blockname = '(\w+[ \t]*)';
            $search = '/'.$lastline.$comments.$indent.$keywords.$blockname.$parameters.'\{/s';
            break;

        case 5:
            // PHP class constants
            //
            // e.g const CONST_NAME = CONST_VALUE (e.g. 99 'string' "string" true false null);
            break;

        case 6:
            // PHP class variables
            //
            // e.g. (protected|public|private|static|var) $VAR_NAME = DEFAULT_VALUE (e.g. 99 'string' "string" array() true false null);
            break;

        default: return; // shouldn't happen !!
    }
    unset($lastline, $comments, $indent, $keywords, $blockname, $parameters);

    // [0][$i][0] : the whole match (i.e. all of the following)
    // [1][$i][0] : last line of previous code block, if any
    // [2][$i][0] : single line comments, if any
    // [3][$i][0] : indent (excluding newlines)
    // [4][$i][0] : PHP/javascript keywords
    // [5][$i][0] : code block name
    // [6][$i][0] : code block parameters (including parentheses)
    // Note: if $type is 2, then [4] and [5] switch position

    // locate all occurrences of this block $type
    if (! preg_match_all($search, $contents, $matches, PREG_OFFSET_CAPTURE)) {
        return false;
    }

    $i_max = count($matches[0]) - 1;
    for ($i=$i_max; $i>=0; $i--) {

        $length = strlen($matches[0][$i][0]);
        $start = $matches[0][$i][1];

        $spacer = '';
        if ($lastline = trim($matches[1][$i][0])) {
            $lastline .= "\n";
            $spacer = "\n";
        }
        if ($comments = trim($matches[2][$i][0])) {
            $comments .= "\n";
            $spacer = "\n";
        }
        $indent = $matches[3][$i][0];
        switch ($type) {
            case 1: $blockname = $matches[5][$i][0]; break; // js functions
            case 2: $blockname = $matches[4][$i][0]; break; // js methods
            case 3: $blockname = $matches[5][$i][0]; break; // php functions/methods
            case 4: $blockname = $matches[5][$i][0]; break; // php classes
            default: return false; // shouldn't happen !!
        }

        $parameters = trim($matches[6][$i][0]);
        if (substr($parameters, 0 ,1)=='(' && substr($parameters, -1)==')') {
            $phpdoc = reader_get_parameters_phpdoc($contents, $start, $indent, $blockname, $parameters);
        } else {
            $phpdoc = reader_get_generic_phpdoc($contents, $start, $indent, $blockname);
        }

        $match = ''
            .$lastline
            .$comments
            .$spacer
            .$phpdoc
            .$indent.$matches[4][$i][0].$matches[5][$i][0].$matches[6][$i][0].'{'
        ;

        if ($match != $matches[0][$i][0]) {
            $contents = substr_replace($contents, $match, $start, $length);
            $update = true;
        }
    }
}

/**
 * reader_get_phpdoc
 *
 * @param xxx $indent
 * @param xxx $blockname
 * @param xxx $details
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_phpdoc($indent, $blockname, $details) {
    return ''
        .$indent.'/'.'**'."\n"
        .reader_get_name_phpdoc($blockname, $indent)
        .reader_get_details_phpdoc($details, $indent)
        .$indent.' *'.'/'."\n"
    ;
}

/**
 * reader_get_name_phpdoc
 *
 * @param xxx $blockname
 * @param xxx $indent
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_name_phpdoc($blockname, $indent) {
    $name = trim($blockname);
    if ($pos = strrpos($name, '.')) {
        $name = substr($name, $pos + 1);
    }
    return "$indent * $name\n";
}

/**
 * reader_get_details_phpdoc
 *
 * @param xxx $details
 * @param xxx $indent
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_details_phpdoc($details, $indent) {
    if ($details) {
        $details = "$indent *\n".$details;
    }
    return $details;
}

/**
 * reader_get_generic_phpdoc
 *
 * @param xxx $contents
 * @param xxx $start
 * @param xxx $indent
 * @param xxx $blockname
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_generic_phpdoc($contents, $start, $indent, $blockname) {
    $details = ''
        ."$indent * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)\n"
        ."$indent * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later\n"
        ."$indent * @since      Moodle 2.0\n"
        ."$indent * @package    mod\n"
        ."$indent * @subpackage reader\n"
    ;
    return reader_get_phpdoc($indent, $blockname, $details);
}

/**
 * reader_get_parameters_phpdoc
 *
 * @param xxx $contents
 * @param xxx $start
 * @param xxx $indent
 * @param xxx $blockname
 * @param xxx $parameters
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_parameters_phpdoc($contents, $start, $indent, $blockname, $parameters) {

    $details = '';
    $search = '/'.'(\&?)(\$?\w+)(\s*=\s*([^,]*))?'.'/';
    // [0][$i] : reference + name + default value
    // [1][$i] : reference (i.e. "&")
    // [2][$i] : parameter name (optional leading "$")
    // [3][$i] : default value expression (i.e. "=" + default $value)
    // [4][$i] : default value
    if (preg_match_all($search, trim($parameters, ' ()'), $matches)) {
        $i_max = count($matches[0]);
        for ($i=0; $i<$i_max; $i++) {
            $name = $matches[2][$i];
            $details .= "$indent * @param xxx $name";
            if ($matches[1][$i]) {
                $details .= " (passed by reference)";
            }
            if ($matches[3][$i]) {
                $default = $matches[4][$i];
                $details .= " (optional, default=$default)";
            }
            $details .= "\n";
        }
    }

    // get $pos(ition) of end of function
    if ($pos = strpos($contents, "\n$indent}", $start)) {
        $substr = substr($contents, $start, $pos - $start);
        if (preg_match_all('/(?<=global )\$[^;]*(?=;)/', $substr, $matches)) {
            $globals = array();
            foreach ($matches[0] as $match) {
                $match = explode(',', $match);
                $match = array_map('trim', $match);
                $match = array_filter($match);
                $globals = array_merge($globals, $match);
            }
            $globals = array_unique($globals);
            rsort($globals);
            foreach ($globals as $global) {
                $details = "$indent * @uses $global\n".$details;
            }
        }
        if (preg_match('/\s'.'return'.'\s/s', $substr)) {
            $details .= "$indent * @return xxx\n";
        }
    }

    $details .= "$indent * @todo Finish documenting this function\n";

    return reader_get_phpdoc($indent, $blockname, $details);
}

/**
 * reader_fix_copyright
 *
 * @param xxx $contents (passed by reference)
 * @param xxx $update (passed by reference)
 * @param xxx $filepath
 * @param xxx $filetype
 * @todo Finish documenting this function
 */
function reader_fix_copyright(&$contents, &$update, $filepath, $filetype) {

    // these files are accessed directly from the browser
    // so we don't add the check for defined('MOODLE_INTERNAL')
    static $main_filepaths = array(
        'mod/reader/admin.php',
        'mod/reader/admin/attempts.php',
        'mod/reader/admin/books.php',
        'mod/reader/admin/books/download/ajax.js.php',
        'mod/reader/admin/quizzes.php',
        'mod/reader/admin/report.php',
        'mod/reader/admin/users.php',
        'mod/reader/dlquizzes.php',
        'mod/reader/dlquizzes_process.php',
        'mod/reader/dlquizzesnoq.php',
        'mod/reader/images.php',
        'mod/reader/index.php',
        'mod/reader/loadsectionoption.php',
        'mod/reader/quiz/attempt.php',
        'mod/reader/quiz/processattempt.php',
        'mod/reader/quiz/startattempt.php',
        'mod/reader/quiz/summary.php',
        'mod/reader/report.php',
        'mod/reader/showincorrectquizzes.php',
        'mod/reader/styles.php',
        'mod/reader/updatecheck.php',
        'mod/reader/view_books.php',
        'mod/reader/view_users.php',
        'mod/reader/view.php',
    );

    // set default position to insert copyright
    $count = 0;
    $insert_at = 0;
    $fix_copyright = false;

    switch ($filetype) {
        case 'php':
            // remove all file and class phpdocs
            $remove_previous_phpdocs = true;
            if ($remove_previous_phpdocs) {
                $basename = substr(basename($filepath), 0, -4);
                $search = '/\/\*\*[\n\r]+'.' \* (mod_|xmldb_)?reader_'.$basename.'[\n\r]+'.'.*?\*\/[\n\r]+/s';
                $contents = preg_replace($search, '', $contents, -1, $count);
                if ($count) {
                    $update = true;
                }
            }

            // standardize opening php tag
            $search = '/^\<\?'.'php[^\n\r]*[\n\r]\s*/is';
            $replace ='<'.'?'.'php'."\n\n";
            if (preg_match($search, $contents, $matches)) {
                if (strcmp($matches[0], $replace)) {
                    $contents = substr_replace($contents, $replace, 0, strlen($matches[0]));
                    $update = true;
                }
            }

            // set position to insert copyright
            $insert_at = strlen($replace);

            // prevent direct access from browser, if necessary
            $dir = basename(dirname($filepath));
            if ($dir=='en') {
                $fix_copyright = false;
                $fix_directaccess = false;
            } else if ($dir=='tools') {
                $fix_copyright = true;
                $fix_directaccess = false;
            } else if (in_array($filepath, $main_filepaths)) {
                // no need to prevent direct access to these files
                // but we do need to fix the copyright notice
                $fix_copyright = true;
                $fix_directaccess = false;
            } else {
                $fix_copyright = true;
                $fix_directaccess = true;
            }

            // remove any previous comment about blocking direct access
            $comment = preg_quote('', '/');
            $contents = preg_replace('/\s*'.$comment.'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            // remove any previous PHP code to block direct access
            $phpcode = preg_quote('if (empty($CFG)) {').'\s*die;\s*'.preg_quote('}');
            $contents = preg_replace('/\s*'.$phpcode.'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            $comment = '/'.'** Prevent direct access to this script *'.'/';
            $phpcode = "defined('MOODLE".'_'."INTERNAL') || die;";

            // remove any previous code to prevent direct access
            $contents = preg_replace('/\s*'.preg_quote($comment, '/').'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            // remove any previous PHP code to block direct access
            $contents = preg_replace('/\s*'.preg_quote($phpcode, '/').'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            if ($fix_directaccess) {
                // insert code to prevent direct access
                $search = '/[\n\r]+\w/s';
                $replace = "\n\n".$comment."\n".$phpcode."\n\n".'$0';
                $contents = preg_replace($search, $replace, $contents, 1, $count);
                if ($count) {
                    $update = true;
                }
            }

            // remove previous comment
            $comment = '';
            $contents = preg_replace('/\s*'.preg_quote($comment, '/').'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            $comment = '/'.'** Include required files *'.'/';
            $contents = preg_replace('/\s*'.preg_quote($comment, '/').'/s', '', $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            // insert phpDoc before first require_once()
            $search = '/[\n\r]+((?:require|include)_once)/s';
            $replace = "\n\n".$comment."\n".'$1';
            $contents = preg_replace($search, $replace, $contents, 1, $count);
            if ($count) {
                $update = true;
            }

            // remove closing php tag
            $search = '/\s*\?\>\s*$/s';
            if (preg_match($search, $contents)) {
                $contents = preg_replace($search, "\n", $contents, 1);
                $update = true;
            }

            break;

        case 'js':
            // remove opening comment tag
            $search = '/^\<\!\-\-[^\n\r]*[\n\r]+/is';
            if (preg_match($search, $contents)) {
                $contents = preg_replace($search, '', $contents, 1);
                $update = true;
            }

            // remove closing comment tag
            $search = '/\s*(:?\/\/\s*)?\-\-\>\s*$/s';
            if (preg_match($search, $contents)) {
                $contents = preg_replace($search, "\n", $contents, 1);
                $update = true;
            }

            break;
    }

    $copyright = ''
        .'// This file '.'is part of Moodle'.' - http://moodle.org/'."\n"
        .'//'."\n"
        .'// Moodle is free software: you can redistribute it and/or modify'."\n"
        .'// it under the terms of the GNU General Public License as published by'."\n"
        .'// the Free Software Foundation, either version 3 of the License, or'."\n"
        .'// (at your option) any later version.'."\n"
        .'//'."\n"
        .'// Moodle is distributed in the hope that it will be useful,'."\n"
        .'// but WITHOUT ANY WARRANTY; without even the implied warranty of'."\n"
        .'// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the'."\n"
        .'// GNU General Public License for more details.'."\n"
        .'//'."\n"
        .'// You should have received a copy of the GNU General Public License'."\n"
        .'// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.'."\n"
    ;
    $phpdoc = ''
        .'/'.'**'."\n"
        ." * $filepath\n"
        .' *'."\n"
        .' * @package    mod'."\n"
        .' * @subpackage reader'."\n"
        .' * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)'."\n"
        .' * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later'."\n"
        .' * @since      Moodle 2.0'."\n"
        .' *'.'/'."\n"
    ;

    // remove previous copyright
    $search = preg_quote($copyright, '/');
    $search = str_replace("\n", '[\n\r]+', $search);
    $contents = preg_replace('/'.$search.'/s', '', $contents, 1, $count);
    if ($count) {
        $fix_copyright = true;
    }

    // remove previous phpdoc
    $search = preg_quote($phpdoc, '/');
    $search = preg_replace('/ +/', ' +', $search);
    //$search = preg_replace('/('.preg_quote(' +\*', '/').'(?!\*)[^\n\r]*)[\n\r]+/s', '(?:$1[\n\r]+)?', $search);
    $search = str_replace("\n", '[\n\r]+', $search);
    $search = str_replace(preg_quote($filepath, '/'), '.*?', $search);

    $contents = preg_replace('/'.$search.'/s', '', $contents, -1, $count);
    if ($count) {
        $fix_copyright = true;
    }

    if ($fix_copyright) {
        $insert = $copyright."\n".$phpdoc."\n";
        $contents = substr_replace($contents, $insert, $insert_at, 0);
        $update = true;
    }
}
