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
 * mod/reader/admin/tools/fix_coursesections.php
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

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);
$tool = substr(basename($SCRIPT), 0, -4);

require_login(SITEID);
require_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM));

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

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url("/mod/reader/admin/tools/$tool.php", $params));

// set page title
$title = get_string($tool, 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('mod_reader', 'admin_tools');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start();

// get ids of courses storing Reader Quizzes
$courseids = array();
if ($courseid = get_config('mod_reader', 'usecourse')) {
    $courseids[] = $courseid;
}
$select = 'SELECT DISTINCT usecourse FROM {reader} WHERE usecourse IS NOT NULL AND usecourse > ?';
$select = "id IN ($select) AND visible = ?";
$params = array(0, 0);
if ($courses = $DB->get_records_select('course', $select, $params, 'id', 'id,visible')) {
    $courseids = array_merge($courseids, array_keys($courses));
    $courseids = array_unique($courseids);
    sort($courseids);
}
if (empty($courseids)) {
    echo html_writer::tag('p', 'Oops, could not find any hidden Reader Quizzes courses');
} else {
    foreach ($courseids as $courseid) {
        reader_fixcoursesections($courseid);
    }
}

reader_print_all_done();
reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();

///////////////////////////////////////////////////////////////////
// functions only below this line
///////////////////////////////////////////////////////////////////

/**
 * reader_fixcoursesections
 *
 * @param string $tablename
 * @param string $columnname
 * @param boolean $unsigned XMLDB_UNSIGNED or null
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_change_field_type_unsigned($tablename, $columnname, $unsigned=null) {
    global $DB;

    $columns = $DB->get_columns($tablename);
    $column = $columns[$columnname];

    if ($column->unsigned==$unsigned) {
        // do nothing
    } else {
        $dbman = $DB->get_manager();

        $table = new xmldb_table($tablename);
        $indexes = $DB->get_indexes($tablename);

        $drop = array();
        foreach ($indexes as $indexname => $index) {
            if (in_array($columnname, $index['columns'])) {
                $drop[$indexname] = new xmldb_index($indexname, $index['unique'], $index['columns']);
            }
        }

        foreach ($drop as $index) {
            $dbman->drop_index($table, $index);
        }

        $field = new xmldb_field($column->name, XMLDB_TYPE_INTEGER, $column->max_length, $unsigned, $column->not_null, null, $column->default_value);
        $dbman->change_field_type($table, $field);

        foreach ($drop as $index) {
            $dbman->add_index($table, $index);
        }
    }

    // return current value
    return $column->unsigned;
}

/**
 * reader_fixcoursesections
 *
 * @param xxx $courseid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fixcoursesections($courseid) {
    global $DB;

    // make sure 'section' field is unsigned (Moodle <= 2.2)
    $unsigned = reader_change_field_type_unsigned('course_sections', 'section');

    if (! $course = $DB->get_record('course', array('id' => $courseid))) {
        echo html_writer::tag('p', 'Oops, courseid is not a valid course id !');
        return;
    }
    if (! $sections = $DB->get_records('course_sections', array('course' => $courseid))) {
        echo html_writer::tag('p', 'Oops, course '.$courseid.' has no sections !');
        return;
    }

    $rebuild_course_cache = false;

    $link = html_writer::tag('a', $course->fullname, array('target' => '_blank', 'href' => new moodle_url('/course/view.php', array('id' => $course->id))));
    echo html_writer::tag('h3', "Fix course sections: $link (id=$course->id)");

    // sort the array by name (maintain keys)
    uasort($sections, 'reader_section_sort_by_name');

    // fix section summaries
    $sectionnames = array();
    foreach ($sections as $sectionid => $section) {
        if ($section->section==0) {

            $cmids = explode(',', $section->sequence);
            $cmids = array_filter($cmids); // remove blanks
            foreach ($cmids as $cmid) {

                $cm = get_coursemodule_from_id('', $cmid);
                if ($cm->modname=='quiz') {
                    if ($book = $DB->get_record('reader_books', array('quizid' => $cm->instance))) {

                        $sectionname = reader_sectionname_from_book($book);
                        $newsectionid = reader_locate_section_by_name($sectionname, $sections);
                        if ($newsectionid==0) {
                            if ($newsection = reader_create_new_section($course, $sectionname)) {
                                $newsectionid = $newsection->id;
                                $sections[$newsectionid] = $newsection;
                            }
                        }
                        if ($newsectionid) {
                            moveto_module($cm, $sections[$newsectionid]);
                            $sections[$newsectionid]->sequence = $DB->get_field('course_sections', 'sequence', array('id' => $newsectionid));
                            $rebuild_course_cache = true;
                        }
                    }
                }
            }
            unset($sections[$section->id]); // ignore intro
        } else {
            $sectionname = '';
            if ($sectionname=='' && isset($section->name)) {
                $sectionname = trim(strip_tags($section->name));
            }
            if ($sectionname=='' && isset($section->summary)) {
                $sectionname = trim(strip_tags($section->summary));
            }
            if ($section->sequence=='') {
                $sectionname = '';
            }
            if ($sectionname != $section->name) {
                $section->name = $sectionname;
                $sections[$section->id]->name = $sectionname;
                $DB->set_field('course_sections', 'name', $sectionname, array('id' => $sectionid));
                $rebuild_course_cache = true;
            }
            if ($sectionname) {
                if (empty($sectionname[$sectionname])) {
                    $sectionnames[$sectionname] = array();
                }
                $sectionnames[$sectionname][] = $sectionid;
                if ($section->summary && strip_tags($section->summary)==$sectionname) {
                    // clear summary as it only duplicates $section->name
                    $section->summary = '';
                    $sections[$sectionid]->summary = '';
                    $DB->set_field('course_sections', 'summary', '', array('id' => $sectionid));
                    $rebuild_course_cache = true;
                }
            }
        }
    }

    // merge sections with identical publisher + level
    $endlist = '';
    foreach ($sectionnames as $sectionname => $sectionids) {
        if (count($sectionids) > 1) {
            // merge multiple sections with the same name

            if ($endlist=='') {
                echo '<ul>';
                $endlist = '</ul>';
            }
            echo '<li>Merging sections for '.$sectionname.'<ul>';

            $mainsectionid = 0;
            foreach ($sectionids as $sectionid) {
                if ($mainsectionid==0) {
                    $mainsectionid = $sectionid;
                } else {
                    $cmids = $sections[$sectionid]->sequence;
                    $cmids = explode(',', $cmids);
                    $cmids = array_filter($cmids); // remove blanks

                    foreach ($cmids as $cmid) {
                        $cm = get_coursemodule_from_id('', $cmid);
                        echo "<li>move $cm->name from section id=$sectionid =&gt; $mainsectionid</li>";
                        moveto_module($cm, $sections[$mainsectionid]);
                    }

                    // force removal of this section later on
                    $sections[$sectionid]->name = '';
                    $sections[$sectionid]->summary = '';
                    $sections[$sectionid]->sequence = '';

                    // fetch updated sequence for main section
                    $sections[$mainsectionid]->sequence = $DB->get_field('course_sections', 'sequence', array('id' => $mainsectionid));
                }
            }
            $rebuild_course_cache = true;
            echo '</ul></li>';
        }
    }
    echo $endlist;
    unset($sectionnames, $sectionname, $sectionids, $sectionid, $cmids, $cmid, $cm, $mainsectionid, $endlist);

    // set all section numbers in this course to negative, so that we can
    // change them later without violating database unique index restraint
    $modinfo = get_fast_modinfo($course);
    foreach ($sections as $section) {
        if ($section->section) {

            // extract this section's course module ids and names
            $cmids = explode(',', $section->sequence);
            $cmids = array_filter($cmids); // remove blanks
            $names = array();
            foreach ($cmids as $cmid)  {
                if (empty($modinfo->cms[$cmid])) {
                    continue; // shouldn't happen !!
                }
                if (empty($modinfo->cms[$cmid]->name)) {
                    $name = ''; // shouldn't happen !!
                } else {
                    $name = $modinfo->cms[$cmid]->name;
                }
                $name = preg_replace('/[^a-zA-Z0-9 ]/', '', $name);
                if ($modinfo->cms[$cmid]->visible) {
                    $name .= ' aaa'; // "visible" comes before "hidden"
                } else {
                    $name .= ' zzz'; // "hidden" comes after "visible"
                }
                $name .= " $cmid"; // the order that cms were added ?
                $names[$cmid] = strtolower($name);
            }

            // sort course modules by name, and update if necessary
            asort($names);
            $cmids = implode(',', array_keys($names));
            if ($section->sequence != $cmids) {
                $section->sequence = $cmids;
                $DB->set_field('course_sections', 'sequence', $section->sequence, array('id' => $section->id));
            }

            // set sequence number to negative
            $DB->set_field('course_sections', 'section', -($section->section), array('id' => $section->id));
        }
    }
    unset($modinfo);

    $numsections = 0;

    echo '<ul>';
    foreach ($sections as $section) {
        if ($section->section==0) {
            // intro - do nothing
        } else if ($section->name || $section->sequence) {
            $numsections++;
            if ($section->section <> $numsections) {
                echo '<li>Renumber section: '.$section->section.' =&gt; '.$numsections.' '.$section->name.'</li>';
            }
            $DB->set_field('course_sections', 'section', $numsections, array('id' => $section->id));
        } else {
            echo '<li><span style="color:red;">DELETE</span> empty section: '.$section->section.'</li>';
            $DB->delete_records('course_sections', array('id' => $section->id));
        }
        $rebuild_course_cache = true;
    }
    echo '</ul>';

    if (isset($course->numsections)) {
        $DB->set_field('course', 'numsections', $numsections, array('id' => $course->id));
    } else {
        $params = array('courseid' => $course->id, 'format' => $course->format, 'name' => 'numsections');
        $DB->set_field('course_format_options', 'value', $numsections, $params);
    }

    // restore signed 'section' field (Moodle <= 2.2)
    reader_change_field_type_unsigned('course_sections', 'section', $unsigned);

    if ($rebuild_course_cache) {
        echo 'Re-building course cache ... ';
        rebuild_course_cache($course->id);
    }

}

/**
 * reader_sectionname_from_book
 *
 * @param xxx $book
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_sectionname_from_book($book) {
    $sectionname = $book->publisher;
    if ($book->level=='' || $book->level=='') {
        // do nothing
    } else {
        $sectionname .= ' - '.$book->level;
    }
    return $sectionname;
}

/**
 * reader_locate_section_by_name
 *
 * @param xxx $book
 * @param xxx $sections
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_locate_section_by_name($sectionname, $sections) {
    foreach ($sections as $sectionid => $section) {
        if ($section->name==$sectionname) {
            return $sectionid;
        }
    }
    return 0; // section name not found
}

/**
 * reader_create_new_section
 *
 * @param xxx $course
 * @param xxx $sectionname
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_new_section($course, $sectionname) {
    global $DB;
    $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
    if ($sectionnum = $DB->get_field_sql($sql, array($course->id))) {
        $sectionnum ++;
    } else {
        $sectionnum = 1;
    }
    $newsection = (object)array(
        'course'        => $course->id,
        'section'       => $sectionnum,
        'name'          => $sectionname,
        'summary'       => '',
        'summaryformat' => FORMAT_HTML,
    );
    if ($newsection->id = $DB->insert_record('course_sections', $newsection)) {
        return $newsection; // section was successfully added
    } else {
        return false; // oops, could not create a new section
    }
}

/**
 * reader_section_sort_by_name
 *
 * @param xxx $a
 * @param xxx $b
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_section_sort_by_name($a, $b) {

    // search and replace strings
    $search = array('/ (-|Level|Stage)/', '/ \(EasyStart\)$/', '/ Starter$/', '/ Beginner$/', '/ Elementary$/', '/ Pre-Intermediate$/', '/Booksworms/');
    $replace = array('', '', 0, 1, 2, 3, 'Bookworms');
    $split = '/^(.*?)([0-9]+)$/';

    // the intro section comes before any other section
    if ($a->section==0 || $b->section==0) {
        if ($a->section) {
            return 1;
        }
        if ($b->section) {
            return -1;
        }
        return 0; // both section zero
    }

    // get filtered section names (a)
    $aname = preg_replace($search, $replace, $a->name);
    if (preg_match($split, $aname, $matches)) {
        $aname = $matches[1];
        $anum  = $matches[2];
    } else {
        $anum = 0;
    }

    // get filtered section names (b)
    $bname = preg_replace($search, $replace, $b->name);
    if (preg_match($split, $bname, $matches)) {
        $bname = $matches[1];
        $bnum  = $matches[2];
    } else {
        $bnum = 0;
    }

    // sections with empty names always go last
    if ($aname=='' || $bname=='') {
        if ($aname) {
            return -1;
        }
        if ($bname) {
            return 1;
        }
        if ($a->section < $b->section) {
            return -1;
        }
        if ($a->section > $b->section) {
            return 1;
        }
        return 0; // both empty && same section
    }

    // compare section names
    if ($aname < $bname) {
        return -1;
    }
    if ($aname > $bname) {
        return 1;
    }

    // compare section level/stage/word numbers
    if ($anum < $bnum) {
        return -1;
    }
    if ($anum > $bnum) {
        return 1;
    }

    // same name && same level/stage/word number
    return 0;
}
