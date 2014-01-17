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
 * mod/reader/tabs.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

$currenttab = $a;

if (! isset($currenttab)) {
    $currenttab = 'quizzes';
}

if (! isset($cm)) {
    if (! $cm = $DB->get_record('course_modules', array('id' => $id))) {
        throw new reader_exception('Course Module ID was incorrect');
    }
}

$context = reader_get_context(CONTEXT_MODULE, $cm->id);

if (! isset($contexts)){
    $contexts = new question_edit_contexts($context);
}

$tabs = array();
$row  = array();
$inactive = array();
$activated = array();

$row[] = new tabobject('quizzes', "view.php?a=quizzes&id=".$id, "Quizzes");
$row[] = new tabobject('admin', "admin.php?a=admin&id=".$id, "Admin Area");
$row[] = new tabobject('adminarea', 'admin/index.php?id='.$id, get_string('adminarea', 'reader'));

$tabs[] = $row;

if ($currenttab == 'admin' and isset($mode)) {
    $inactive[] = 'admin';
    $activated[] = 'admin';

    // Standard reports we want to show first.
    $reportlist = array('overview', 'regrade', 'grading', 'analysis');
    // Reports that are restricted by capability.
    $reportrestrictions = array(
        'regrade' => 'mod/quiz:grade',
        'grading' => 'mod/quiz:grade'
    );

    $allreports = get_list_of_plugins("mod/quiz/report");
    foreach ($allreports as $report) {
        if (! in_array($report, $reportlist)) {
            $reportlist[] = $report;
        }
    }

    $row  = array();
    $currenttab = '';
    foreach ($reportlist as $report) {
        if (! isset($reportrestrictions[$report]) || has_capability($reportrestrictions[$report], $context)) {
            $url = "$CFG->wwwroot/mod/reader/report.php?id={$id}&q={$q}&mode={$report}&b={$b}";
            $row[] = new tabobject($report, $url, $url, get_string($report, 'quiz_'.$report));
            if ($report == $mode) {
                $currenttab = $report;
            }
        }
    }
    $tabs[] = $row;
}

unset ($tabs[1][0]);

print_tabs($tabs, $currenttab, $inactive, $activated);

