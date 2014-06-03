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
 * mod/reader/admin/tools/fix_installxml.php
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
$title = get_string('fix_installxml', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

// extract and format versions from Reader upgrade script
$install_xml = $CFG->dirroot.'/mod/reader/db/install.xml';
if (is_file($install_xml)) {
    $contents = file_get_contents($install_xml);

    $UPDATE = false;

    $search = '/<TABLE\b([^>]*)>(.*?)<\/TABLE>/s';
    $callback = 'reader_fix_indexes';
    $contents = preg_replace_callback($search, $callback, $contents);

    $search = '/(?<=<)(FIELD)\b([^>]*)(?=\/>)/s';
    $callback = 'reader_fix_field';
    $contents = preg_replace_callback($search, $callback, $contents);

    $tags = array('TABLES', 'FIELDS', 'KEYS', 'INDEXES');
    foreach ($tags as $tag) {
        $search = '/<('.$tag.')\b([^>]*)>(.*?)<\/'.$tag.'>/s';
        $callback = 'reader_fix_tags';
        $contents = preg_replace_callback($search, $callback, $contents);
    }

    if ($UPDATE) {
        if (is_writeable($install_xml)) {
            file_put_contents($install_xml, $contents);
            echo 'install.xml was successfully updated:<br />'.$install_xml;
        } else {
            echo 'Sorry, could not write to install.xml:<br />'.$install_xml;
        }
    } else {
        echo 'No update required at this time:<br />'.$install_xml;
    }
} else {
    echo 'Sorry, could not find install.xml:<br />'.$install_xml;
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

/**
 * reader_require_update
 */
function reader_require_update() {
    global $UPDATE;
    $UPDATE = true;
}

/**
 * reader_fix_field
 */
function reader_fix_field($matches) {
    list($match, $tag, $attributes) = $matches;
    $strpos = strpos($attributes, 'TYPE="int"');
    if ($strpos===false) {
        // string field
        $sequence = '/ SEQUENCE="[^"]*"/';
        if (preg_match($sequence, $attributes)) {
            $attributes = preg_replace($sequence, '', $attributes);
            reader_require_update();
        }
    } else {
        // integer field
        if (strpos($attributes, 'NAME="id"')) {
            $sequence = ' SEQUENCE="true"';
            $notnull  = ' NOTNULL="true"';
        } else {
            $sequence = ' SEQUENCE="false"';
            $notnull  = '';
        }
        if ($sequence && strpos($attributes, $sequence)===false) {
            $attributes = preg_replace('/ SEQUENCE="[^"]*"/', '', $attributes);
            $attributes = substr_replace($attributes, $sequence, $strpos + 10, 0);
            reader_require_update();
        }
        if ($notnull && strpos($attributes, $notnull)===false) {
            $attributes = preg_replace('/ NOTNULL="[^"]*"/', '', $attributes);
            $attributes = substr_replace($attributes, $notnull, $strpos + 10, 0);
            reader_require_update();
        }
    }
    return $tag.$attributes;
}

/**
 * reader_fix_tags
 */
function reader_fix_tags($matches) {
    list($match, $tag, $attributes, $childtags) = $matches;

    // set appropriate $childtag for this $tag
    switch ($tag) {
        case 'FIELDS' : $childtag = 'FIELD'; break;
        case 'INDEXES': $childtag = 'INDEX'; break;
        case 'KEYS'   : $childtag = 'KEY'  ; break;
        case 'TABLES' : $childtag = 'TABLE'; break;
        default : return $match; // shouldn't happen !!
    }

    // fix all child tags
    $search = '/(<)('.$childtag.')\b([^\/>]*?)([ *\/]*>)/s';
    if (preg_match_all($search, $childtags, $matches, PREG_OFFSET_CAPTURE)) {

        // get number of $childtags
        $i_max = count($matches[0]) - 1;
        for ($i=$i_max; $i>=0; $i--) {

            // extract childtag match, start and length
            list($match, $start) = $matches[0][$i];
            $length = strlen($match);

            $update = '';

            // add NEXT="...", if necessary
            if ($i<$i_max) {
                $search = '/ NAME="[^"]*"/';
                $next = $matches[3][$i+1][0];
                if (preg_match($search, $next, $next)) {
                    $next = ' NEXT'.substr($next[0], 5);
                    if (strpos($match, $next)===false) {
                        $matches[3][$i][0] = preg_replace('/ NEXT="[^"]*"/', '', $matches[3][$i][0]);
                        $update .= $next;
                    }
                }
            }

            // add PREVIOUS="...", if necessary
            if ($i>0) {
                $search = '/ NAME="[^"]*"/';
                $prev = $matches[3][$i-1][0];
                if (preg_match($search, $prev, $prev)) {
                    $prev = ' PREVIOUS'.substr($prev[0], 5);
                    if (strpos($match, $prev)===false) {
                        $matches[3][$i][0] = preg_replace('/ PREVIOUS="[^"]*"/', '', $matches[3][$i][0]);
                        $update .= $prev;
                    }
                }
            }

            if ($update) {
                $match = $matches[1][$i][0].
                         $matches[2][$i][0].
                         $matches[3][$i][0].$update.
                         $matches[4][$i][0];
                $childtags = substr_replace($childtags, $match, $start, $length);
                reader_require_update();
            }
        }
    }

    return "<$tag$attributes>$childtags</$tag>";
}

function reader_fix_indexes($matches) {
    list($match, $attributes, $childtags) = $matches;

    $prefix = '';
    if (preg_match('/(?<=NAME=")[^"]*(?=")/', $attributes, $names)) {
        $names = explode('_', $names[0]);
        foreach ($names as $name) {
            $prefix .= substr($name, 0, 4);
        }
        $prefix .= '_';
    }

    $search = '/<(INDEX|KEY) NAME="([^"]*)".*?FIELDS="([^"]*)".*?>/s';
    if (preg_match_all($search, $match, $matches, PREG_OFFSET_CAPTURE)) {
        $i_max = count($matches[0]) - 1;
        for ($i=$i_max; $i>=0; $i--) {

            $type   = $matches[1][$i][0];
            $name   = $matches[2][$i][0];
            $fields = $matches[3][$i][0];

            if ($type=='KEY' && $fields=='id') {
                $newname = 'primary';
            } else {
                $newname = $prefix;
                $fields = explode(',', $fields);
                $fields = array_map('trim', $fields);
                $fields = array_filter($fields);
                foreach ($fields as $field) {
                    $newname .= substr($field, 0, 3);
                }
                if (strpos($matches[0][$i][0], 'UNIQUE="true"')==false) {
                    $newname .= '_ix';
                } else {
                    $newname .= '_uix';
                }
            }

            if ($name==$newname) {
                // do nothing
            } else {
                reader_require_update();
                $pos = $matches[2][$i][1];
                $match = substr_replace($match, $newname, $pos, strlen($name));
            }
        }
    }

    return $match;
}

