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
 * mod/reader/admin/tools/redo_upgrade.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');

require_login(SITEID);

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

$tool = substr(basename($SCRIPT), 0, -4);
$plugin = 'mod_reader';

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
$reader->req('manageattempts');

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url("/mod/reader/admin/tools/$tool.php", $params));

// set page title
$title = get_string($tool, $plugin);
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('mod_reader', 'admin_tools');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start();

reader_print_search_form($plugin, $reader, $id, $tab);

if ($action = optional_param('action', '', PARAM_ALPHA)) {
    reader_execute_action($plugin, $action);
} else if (optional_param('detect_cheating', '', PARAM_ALPHA)) {
    reader_print_info($plugin, $reader, $id, $tab);
}

reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();

///////////////////////////////////////////////////////////////////
// functions
///////////////////////////////////////////////////////////////////

/*
 * reader_print_search_form
 *
 * @param string $plugin
 * @param object $reader
 * @return void
 */
function reader_print_search_form($plugin, $reader, $id, $tab) {

    // start form
    $action = new moodle_url('/mod/reader/admin/tools/detect_cheating.php');
    $params = array('method' => 'post', 'action' => $action);
    echo html_writer::start_tag('form', $params);
    echo html_writer::start_tag('div');

    $params = array('type' => 'hidden', 'name' => 'id', 'value' => $id);
    echo html_writer::empty_tag('input', $params);

    $params = array('type' => 'hidden', 'name' => 'tab', 'value' => $tab);
    echo html_writer::empty_tag('input', $params);

    // Output type
    $name = 'outputformat';
    $label = get_string($name, $plugin);
    $value = optional_param($name, 0, PARAM_INT);
    $options = array(0 => get_string('atttemptsgroupedbyuser', $plugin),
                     1 => get_string('atttemptsgroupedbybook', $plugin));
    echo html_writer::tag('div', $label.': ', array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name, $value, null);
    echo html_writer::empty_tag('br');

    // Target course
    $name = 'targetcourse';
    $label = get_string($name, $plugin);
    $value = $reader->course->id; // default value
    $value = optional_param($name, $value, PARAM_INT);
    $options = array(0 => get_string('allcourses', $plugin),
                     $reader->course->id => get_string('currentcourse',$plugin));
    echo html_writer::tag('div', $label.': ', array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name, $value, null);
    echo html_writer::empty_tag('br');

    // Start date
    $name = 'startdate';
    $label = get_string($name, $plugin);
    // default start date is the start of this term
    $value = (time() - $reader->course->startdate);
    $options = array(0 => get_string('allterms', $plugin),
                     $value => get_string('thisterm', $plugin),
                     DAYSECS        => '1 day',
                    (DAYSECS * 2)   => '2 days',
                    (DAYSECS * 3)   => '3 days',
                    (DAYSECS * 4)   => '4 days',
                    (WEEKSECS)      => '1 week',
                    (WEEKSECS * 2)  => '2 weeks',
                    (WEEKSECS * 4)  => '1 month',
                    (WEEKSECS * 8)  => '2 months',
                    (WEEKSECS * 12) => '3 months',
                    (WEEKSECS * 24) => '6 months',
                    (YEARSECS)      => '1 year',
                    (YEARSECS * 2)  => '2 years',
                    (YEARSECS * 3)  => '3 years',
                    (YEARSECS * 5)  => '5 years',
                    (YEARSECS * 10) => '10 years');
    $value = optional_param($name, $value, PARAM_INT);
    echo html_writer::tag('div', $label.': ', array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name, $value, null);
    echo html_writer::empty_tag('br');

    // IP mask
    $name = 'subnetlength';
    $label = get_string($name, $plugin);
    $value = optional_param($name, 2, PARAM_INT);
    $options = array(0 => '',
                     1 => 'xxx.',
                     2 => 'xxx.xxx.',
                     3 => 'xxx.xxx.xxx.',
                     4 => 'xxx.xxx.xxx.xxx');
    echo html_writer::tag('div', $label.': ', array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name, $value, null);
    echo html_writer::empty_tag('br');

    // Minimum delay
    $name = 'minimumdelay';
    $label = get_string($name, $plugin);
    $value = (MINSECS * 15); // default value
    $value = optional_param($name.'year', $value, PARAM_INT);
    $options = array((MINSECS * 5)   => '5 minutes',
                     (MINSECS * 10)  => '10 minutes',
                     (MINSECS * 15)  => '15 minutes',
                     (MINSECS * 30)  => '30 minutes',
                     (MINSECS * 45)  => '45 minutes',
                     (HOURSECS)      => '1 hour',
                     (HOURSECS * 3)  => '3 hours',
                     (HOURSECS * 6)  => '6 hours',
                     (HOURSECS * 12) => '12 hours',
                     (DAYSECS)       => '24 hours');
    echo html_writer::tag('div', $label.': ', array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name, $value, null);
    echo html_writer::empty_tag('br');

    // Status
    $name = 'status';
    $onclick = 'var x=document.getElementById("menu'.$name.'");for(var i=0;i<x.length;i++)x.options[i].selected=';
    $label = get_string($name).':'.html_writer::empty_tag('br').
             html_writer::start_tag('small').
             html_writer::tag('a', get_string('all'), array('onclick' => $onclick.'true;return false;')).' / '.
             html_writer::tag('a', get_string('none'), array('onclick' => $onclick.'false;return false;')).
             html_writer::end_tag('small');
    $value = reader_optional_param_array('status', array(), PARAM_INT);;
    $options = reader_status_menu($plugin);
    $params = array('multiple' => 'multiple', 'size' => count($options));
    echo html_writer::tag('div', $label, array('class' => 'fitemtitle'));
    echo html_writer::select($options, $name.'[]', $value, null, $params);
    echo html_writer::empty_tag('br');

    // submit button
    $name = 'detect_cheating';
    echo html_writer::tag('div', '', array('class' => 'fitemtitle'));
    echo html_writer::empty_tag('input', array('name' => $name,
                                               'type' => 'submit',
                                               'value' => get_string('startscan', $plugin)));
    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

/*
 * reader_print_info
 *
 * @param string $plugin
 * @return void
 */
function reader_print_info($plugin, $reader, $id, $tab) {
    global $DB;

    if (data_submitted()) {

        $targetcourse = optional_param('targetcourse', 0, PARAM_INT);
        $startdate    = optional_param('startdate',    0, PARAM_INT);
        $outputformat = optional_param('outputformat', 0, PARAM_INT);
        $status       = reader_optional_param_array('status', array(), PARAM_INT);

        $select = '*';
        $from   = '{reader_attempts}';
        $where  = array();
        $order  = 'quizid, timefinish';
        $params = array();

        if ($targetcourse) {
            $targetcourse = $reader->course->id; // make sure the course id is valid ;-)
            if ($readerids = $DB->get_records_menu('reader', array('course' => $targetcourse), null, 'id,course')) {
                list($where, $params) = $DB->get_in_or_equal(array_keys($readerids));
                $where = array("readerid $where");
            }
        }

        if ($startdate) {
            $startdate = (time() - $startdate);
            $where[] = 'timestart >= ?';
            $params[] = $startdate;
        }

        array_push($where, 'quizid > ?',
                           'layout <> ?',
                           'ip IS NOT NULL',
                           'ip <> ?');
        array_push($params, 0, 0, '');

        $statuswhere = array();
        $statusparams = array();
        foreach ($status as $s) {
            switch ($s) {
                case -9: // deleted
                    $statuswhere[] = 'deleted = ?';
                    array_push($statusparams, 1);
                    break;
                case -2: // cheated
                    $statuswhere[] = 'deleted = ? && cheated = ?';
                    array_push($statusparams, 0, 1);
                    break;
                case 2: // credit
                    $statuswhere[] = 'deleted = ? && cheated = ? && credit = ?';
                    array_push($statusparams, 0, 0, 1);
                    break;
                case 0: // in progress
                    $statuswhere[] = 'deleted = ? && cheated = ? && credit = ? && timefinish = ?';
                    array_push($statusparams, 0, 0, 0, 0);
                    break;
                case 1: // passed
                    $statuswhere[] = 'deleted = ? && cheated = ? && credit = ? && timefinish > ? && passed = ?';
                    array_push($statusparams, 0, 0, 0, 0, 1);
                    break;
                case -1: // failed
                    $statuswhere[] = 'deleted = ? && cheated = ? && credit = ? && timefinish > ? && passed = ?';
                    array_push($statusparams, 0, 0, 0, 0, 0);
                    break;
            }
        }

        if ($statuswhere = implode(' OR ', $statuswhere)) {
            $where[] = "($statuswhere)";
            foreach ($statusparams as $param) {
                $params[] = $param;
            }
        }

        $where = implode(' AND ', $where);

        if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params)) {
            $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params);
        } else {
            $rs = false;
        }

        $userids = array();
        $usersprinted = false;
        $attemptsprinted = false;

        if ($rs) {

            // disable interactivity on CLI
            // because one day this functionality may be available to cron
            if (defined('STDIN') && defined('CLI_SCRIPT') && CLI_SCRIPT) {
                $bar = false;
            } else {
                $strupdating = get_string('scanningattempts', 'mod_reader');
                $bar = new progress_bar('scanningattempts', 500, true);
            }
            $i = 0; // record counter

            // loop through attempts
            $quizid = 0;
            foreach ($rs as $record) {
                $i++; // increment record count

                if ($quizid && $quizid==$record->quizid) {
                    // same quiz as previous attempt
                } else {
                    // new quiz
                    if ($quizid) {
                        reader_get_info($plugin, $attempts, $userids);
                        if ($outputformat==1) {
                            reader_print_info_books($plugin, $id, $tab, $quizid, $attempts, $attemptsprinted);
                        }
                    }
                    $attempts = array();
                }

                $quizid = $record->quizid;
                $attempts[$record->id] = $record;
                $attempts[$record->id]->suspects = array();

                // update progress bar
                if ($bar) {
                    $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
                }
            }
            $rs->close();
        }
        if ($attemptsprinted) {
            echo "</tbody></table>\n";
        }

        if ($outputformat==0) {
            reader_print_info_users($plugin, $id, $tab, $userids, $usersprinted);
        }
        if ($usersprinted) {
            echo "</tbody></table>\n";
        }

        if ($attemptsprinted || $usersprinted) {
            $actions = array('deleteattempts', 'updatepassed', 'updatecheated');
            reader_print_actions($plugin, $actions, $id, $tab);
            reader_print_action_form_end($plugin);
        }
    }
}

/*
 * reader_get_info
 *
 * @param string $plugin
 * @param array  $attempts (passed by reference)
 * @param array  $userids (passed by reference)
 * @return void
 */
function reader_get_info($plugin, &$attempts, &$userids) {

    $subnetlength = optional_param('subnetlength', 0, PARAM_INT);
    $minimumdelay = optional_param('minimumdelay', 0, PARAM_INT);

    // $minimumdelay is number of seconds e.g. 900 (=15 mins)
    // if the finish time for two attempts at the same quiz
    // is separated by less than this $minimumdelay,
    // then the two attempts will be regarded as suspects

    $attemptids1 = array_keys($attempts);
    foreach ($attemptids1 as $attemptid1) {

        if (empty($attempts[$attemptid1]->ip)) {
            continue; // shouldn't happen !!
        }

        list($ip1, $ip2, $ip3, $ip4) = explode('.', $attempts[$attemptid1]->ip);
        switch ($subnetlength) {
            case 4: $subnet = "$ip1.$ip2.$ip3.$ip4"; break;
            case 3: $subnet = "$ip1.$ip2.$ip3"; break;
            case 2: $subnet = "$ip1.$ip2"; break;
            case 1: $subnet = "$ip1"; break;
            default: $subnet = '';
        }

        $mintime = $attempts[$attemptid1]->timefinish;
        if ($minimumdelay==0) {
            $maxtime = 0;
        } else {
            $maxtime = ($mintime + $minimumdelay);
        }

        $start = false;
        $stop = false;

        $attemptids2 = array_keys($attempts);
        foreach ($attemptids2 as $attemptid2) {
            switch (true) {
                case ($stop==true):
                    // do nothing
                    break;

                case ($start==false):
                    $start = ($attemptid1==$attemptid2);
                    break;

                case ($maxtime && ($maxtime < $attempts[$attemptid2]->timefinish)):
                    $stop = true;
                    break;

                case ($subnet=='' || address_in_subnet($attempts[$attemptid2]->ip, $subnet)):
                    $attempts[$attemptid1]->suspects[$attemptid2] = $attemptid2;
                    $attempts[$attemptid2]->suspects[$attemptid1] = $attemptid1;

                    $userid1 = $attempts[$attemptid1]->userid;
                    $userid2 = $attempts[$attemptid2]->userid;

                    if (empty($userids[$userid1])) {
                        $userids[$userid1] = array($userid2 => array());
                    } else if (empty($userids[$userid1][$userid2])) {
                        $userids[$userid1][$userid2] = array();
                    }
                    $userids[$userid1][$userid2][$attemptid1] = $attemptid2;

                    if (empty($userids[$userid2])) {
                        $userids[$userid2] = array($userid1 => array());
                    } else if (empty($userids[$userid2][$userid1])) {
                        $userids[$userid2][$userid1] = array();
                    }
                    $userids[$userid2][$userid1][$attemptid2] = $attemptid1;
                    break;
            }
        }

        if (empty($attempts[$attemptid1]->suspects)) {
            unset($attempts[$attemptid1]);
        }
    }
}

/*
 * reader_print_info_books
 *
 * @param string  $plugin
 * @param integer $id
 * @param integer $tab
 * @param integer $quizid
 * @param array   $attempts (passed by reference)
 * @param boolean $attemptsprinted (passed by reference)
 * @return void, but may update $attempts and $attemptsprinted
 */
function reader_print_info_books($plugin, $id, $tab, $quizid, &$attempts, &$attemptsprinted) {
    global $DB;

    static $readers = array(),
           $courses = array(),
           $backgroundclass = 'even';

    $user = null;
    $reader = null;
    $course = null;

    $userlink = '';
    $readerlink = '';
    $courselink = '';

    $newwindow = "this.target='_blank'";
    $datefmt = get_string('strfdateshort', $plugin);
    $timefmt = get_string('strftimeshort', $plugin);

    if (count($attempts)) {
        $rowstarted = false;
        $prevattemptid = 0;

        foreach ($attempts as $attemptid => $attempt) {

            // fetch $reader and $course records if necessary
            if ($reader && $reader->id==$attempt->readerid) {
                // same reader activity - do nothing
            } else {
                // fetch $reader and format $readerlink
                if (empty($readers[$attempt->readerid])) {
                    $reader = $DB->get_record('reader', array('id' => $attempt->readerid));
                    $readers[$attempt->readerid] = $reader;
                } else {
                    $reader = $readers[$attempt->readerid];
                }
                $readerlink = new moodle_url('/mod/reader/view.php', array('r' => $reader->id));
                $readerlink = html_writer::link($readerlink, $reader->id, array('title' => $reader->name,
                                                                                'onclick' => $newwindow));
                // fetch $course and format $courselink
                if (empty($courses[$reader->course])) {
                    $course = $DB->get_record('course', array('id' => $reader->course));
                    $course->context = mod_reader::context(CONTEXT_COURSE, $course->id);
                    $courses[$reader->course] = $course;
                } else {
                    $course = $courses[$reader->course];
                }
                $courselink = new moodle_url('/course/view.php', array('id' => $course->id));
                $courselink = html_writer::link($courselink, $course->id, array('title' => $course->shortname,
                                                                                'onclick' => $newwindow));
            }

            // start html table, if necessary
            if ($attemptsprinted==false) {
                $attemptsprinted = true;

                reader_print_action_form_start($plugin, $id, $tab);

                $selectall = get_string('selectall', 'quiz');
                $selectnone = get_string('selectnone', 'quiz');
                $onclick = "if (this.checked) {".
                               "select_all_in('TABLE',null,'attempts');".
                               "this.title = '".addslashes_js($selectnone)."';".
                           "} else {".
                               "deselect_all_in('TABLE',null,'attempts');".
                               "this.title = '".addslashes_js($selectall)."';".
                           "}";
                $checkbox = array('type' => 'checkbox',
                                  'name' => 'selected[0]',
                                  'value' => '1',
                                  'title' => $selectall,
                                  'onclick' => $onclick);
                $checkbox = html_writer::empty_tag('input', $checkbox).'</th>';

                echo '<table cellspacing="4" cellpadding="4" border="1" id="attempts"><tbody>'."\n";
                echo '<tr>';
                echo '<th>'.get_string('book',       $plugin).'<br />('.
                            get_string('publisher',  $plugin).' - '.
                            get_string('level',      $plugin).')</th>';
                echo '<th style="text-align: center;">'.get_string('difficulty', $plugin).'</th>';
                echo '<th style="text-align: center;">'.get_string('words', $plugin).'</th>';
                echo '<th style="text-align: center;">'.get_string('select').html_writer::empty_tag('br').$checkbox.'</th>';
                echo '<th style="text-align: center;">'.get_string('courseid', $plugin).'</th>';
                echo '<th style="text-align: center;">'.get_string('readerid', $plugin).'</th>';
                echo '<th>'.get_string('user').'</th>';
                echo '<th style="text-align: center; min-width: 130px;">'.get_string('date').'</th>';
                echo '<th style="text-align: center; min-width: 50px;">'.get_string('time').'</th>';
                echo '<th style="min-width: 110px;">'.get_string('duration', $plugin).'</th>';
                echo '<th style="min-width: 110px;">'.get_string('ipaddress', $plugin).'</th>';
                echo '<th style="min-width: 110px;">'.get_string('status').' ('.get_string('grade').')</th>';
                echo "</tr>\n";
            }

            $similarattempt = array_key_exists($prevattemptid, $attempt->suspects);
            $prevattemptid = $attemptid;

            if ($rowstarted==false) {
                $borderclass = 'book';
            } else if ($similarattempt) {
                $borderclass = 'attempt2';
            } else {
                $borderclass = 'attempt1';
            }

            if ($similarattempt) {
                // do nothing
            } else {
                $backgroundclass = ($backgroundclass=='even' ? 'odd' : 'even');
            }

            // start html table row
            echo '<tr class="'.$borderclass.'">';
            if ($rowstarted==false) {
                $rowstarted = true;
                $rowspan = count($attempts);
                $book = $DB->get_record('reader_books', array('id' => $attempt->bookid));
                if ($book->level=='--' || $book->level=='99') {
                    $book->level = '';
                }
                if ($book->level) {
                    $book->publisher .= ' - '.$book->level;
                }
                if ($book->publisher) {
                    $book->name .= '<br /><small>('.$book->publisher.')</small>';
                }
                $book->words = number_format($book->words);
                $book->difficulty = get_string('difficultyshort', $plugin).'-'.$book->difficulty;
                echo '<td rowspan="'.$rowspan.'" class="book">'.$book->name.'</td>';
                echo '<td rowspan="'.$rowspan.'" style="text-align: center;">'.$book->difficulty.'</td>';
                echo '<td rowspan="'.$rowspan.'" style="text-align: center;">'.$book->words.'</td>';
            }

            // create checkbox for this attempt
            $params = array('type' => 'checkbox',
                            'name' => 'selected['.$attemptid.']',
                            'value' => 1);
            $checkbox = html_writer::empty_tag('input', $params);

            // format link to user profile
            $user = $DB->get_record('user', array('id' => $attempt->userid));
            if (is_enrolled($course->context, $user->id)) {
                $params = array('id' => $attempt->userid,
                                'course' => $course->id);
            } else {
                $params = array('id' => $attempt->userid,
                                'course' => SITEID);
            }
            $userlink = new moodle_url('/user/view.php', $params);
            $userlink = html_writer::link($userlink, fullname($user), array('onclick' => $newwindow));

            // format date and time of attempt
            $date = userdate($attempt->timefinish, $datefmt);
            $time = userdate($attempt->timefinish, $timefmt);

            // format attempt duration
            $duration = $attempt->timefinish - $attempt->timestart;
            if ($duration <= 0) {
                $duration = '';
            } else {
                $duration = format_time($duration);
            }

            echo '<td style="text-align: center;" class="'.$backgroundclass.' checkbox">'.$checkbox.'</td>';
            echo '<td style="text-align: center;" class="'.$backgroundclass.'">'.$courselink.'</td>';
            echo '<td style="text-align: center;" class="'.$backgroundclass.'">'.$readerlink.'</td>';
            echo '<td class="'.$backgroundclass.'">'.$userlink.'</td>';
            echo '<td class="'.$backgroundclass.' date">'.$date.'</td>';
            echo '<td class="'.$backgroundclass.'">'.$time.'</td>';
            echo '<td class="'.$backgroundclass.'">'.$duration.'</td>';
            echo '<td class="'.$backgroundclass.'">'.$attempt->ip.'</td>';
            echo '<td class="'.$backgroundclass.'">'.reader_status_string($plugin, $attempt).'</td>';
            echo "</tr>\n";
        }
    }
}

/*
 * reader_print_info_users
 *
 * @param string  $plugin
 * @param integer $id
 * @param integer $tab
 * @param array   $userid1s
 * @param boolean $usersprinted (passed by reference)
 * @return void, but may update $usersprinted
 */
function reader_print_info_users($plugin, $id, $tab, $userids1, &$usersprinted) {
    global $DB;

    static $readers = array(),
           $courses = array(),
           $backgroundclass = 'even';

    $user = null;
    $reader = null;
    $course = null;

    $userlink = '';
    $readerlink = '';
    $courselink = '';

    $class = 'odd';
    $newwindow = "this.target='_blank'";
    $datefmt = get_string('strfdateshort', $plugin);
    $timefmt = get_string('strftimeshort', $plugin);

    foreach ($userids1 as $userid1 => $userids2) {

        $rowspan1 = count($userids2, COUNT_RECURSIVE);
        $rowspan1 = 2 * ($rowspan1 - count($userids2));
        $rowstarted1 = false;

        foreach ($userids2 as $userid2 => $attemptids) {
            $rowspan2 = 2 * count($attemptids);
            $rowstarted2 = false;

            foreach ($attemptids as $attemptid1 => $attemptid2) {
                $rowspan3 = 2;

                $attempt1 = $DB->get_record('reader_attempts', array('id' => $attemptid1));
                $attempt2 = $DB->get_record('reader_attempts', array('id' => $attemptid2));

                // fetch $reader and $course records if necessary
                if ($reader && $reader->id==$attempt1->readerid) {
                    // same reader activity - do nothing
                } else {
                    // fetch $reader and format $readerlink
                    if (empty($readers[$attempt1->readerid])) {
                        $reader = $DB->get_record('reader', array('id' => $attempt1->readerid));
                        $readers[$attempt1->readerid] = $reader;
                    } else {
                        $reader = $readers[$attempt1->readerid];
                    }
                    $readerlink = new moodle_url('/mod/reader/view.php', array('r' => $reader->id));
                    $readerlink = html_writer::link($readerlink, $reader->id, array('title' => $reader->name,
                                                                                    'onclick' => $newwindow));
                    // fetch $course and format $courselink
                    if (empty($courses[$reader->course])) {
                        $course = $DB->get_record('course', array('id' => $reader->course));
                        $course->context = mod_reader::context(CONTEXT_COURSE, $course->id);
                        $courses[$reader->course] = $course;
                    } else {
                        $course = $courses[$reader->course];
                    }
                    $courselink = new moodle_url('/course/view.php', array('id' => $course->id));
                    $courselink = html_writer::link($courselink, $course->id, array('title' => $course->shortname,
                                                                                    'onclick' => $newwindow));
                }

                // start html table, if necessary
                if ($usersprinted==false) {
                    $usersprinted = true;

                    reader_print_action_form_start($plugin, $id, $tab);

                    $selectall = get_string('selectall', 'quiz');
                    $selectnone = get_string('selectnone', 'quiz');
                    $onclick = "if (this.checked) {".
                                   "select_all_in('TABLE',null,'users');".
                                   "this.title = '".addslashes_js($selectnone)."';".
                               "} else {".
                                   "deselect_all_in('TABLE',null,'users');".
                                   "this.title = '".addslashes_js($selectall)."';".
                               "}";
                    $checkbox = array('type' => 'checkbox',
                                      'name' => 'selected[0]',
                                      'value' => '1',
                                      'title' => $selectall,
                                      'onclick' => $onclick);
                    $checkbox = html_writer::empty_tag('input', $checkbox).'</th>';

                    echo '<table cellspacing="4" cellpadding="4" border="1" id="users"><tbody>'."\n";
                    echo '<tr>';
                    echo '<th>'.get_string('user').' 1</th>';
                    echo '<th>'.get_string('user').' 2</th>';
                    echo '<th style="text-align: center;">'.get_string('select').html_writer::empty_tag('br').$checkbox.'</th>';
                    echo '<th>'.get_string('book',       $plugin).'<br /><small>('.
                                get_string('publisher',  $plugin).' - '.
                                get_string('level',      $plugin).')</small></th>';
                    echo '<th style="text-align: center;">'.get_string('difficulty', $plugin).'</th>';
                    echo '<th style="text-align: center;">'.get_string('words', $plugin).'</th>';
                    echo '<th style="text-align: center;">'.get_string('courseid', $plugin).'</th>';
                    echo '<th style="text-align: center;">'.get_string('readerid', $plugin).'</th>';
                    echo '<th style="text-align: center; min-width: 130px;">'.get_string('date').'</th>';
                    echo '<th style="text-align: center; min-width: 50px;">'.get_string('time').'</th>';
                    echo '<th style="min-width: 110px;">'.get_string('duration', $plugin).'</th>';
                    echo '<th style="min-width: 110px;">'.get_string('ipaddress', $plugin).'</th>';
                    echo '<th style="min-width: 110px;">'.get_string('status').' ('.get_string('grade').')</th>';
                    echo "</tr>\n";
                }

                // start html table row
                echo '<tr class="'.($rowstarted1==false ? 'user1' : ($rowstarted2==false ? 'user2' : 'attempt1')).'">';
                if ($rowstarted1==false) {
                    $rowstarted1 = true;

                    // format link to profile of userid1
                    $user = $DB->get_record('user', array('id' => $userid1));
                    if (is_enrolled($course->context, $userid1)) {
                        $params = array('id' => $userid1, 'course' => $course->id);
                    } else {
                        $params = array('id' => $userid1, 'course' => SITEID);
                    }
                    $userlink = new moodle_url('/user/view.php', $params);
                    $userlink = html_writer::link($userlink, fullname($user), array('onclick' => $newwindow));

                    echo '<td rowspan="'.$rowspan1.'" class="userlink1">'.$userlink.'</td>';
                }
                if ($rowstarted2==false) {
                    $rowstarted2 = true;

                    // format link to profile of userid2
                    $user = $DB->get_record('user', array('id' => $userid2));
                    if (is_enrolled($course->context, $userid2)) {
                        $params = array('id' => $userid2, 'course' => $course->id);
                    } else {
                        $params = array('id' => $userid2, 'course' => SITEID);
                    }
                    $userlink = new moodle_url('/user/view.php', $params);
                    $userlink = html_writer::link($userlink, fullname($user), array('onclick' => $newwindow));

                    echo '<td rowspan="'.$rowspan2.'" class="userlink2">'.$userlink.'</td>';
                }

                $book = $DB->get_record('reader_books', array('id' => $attempt1->bookid));
                if ($book->level=='--' || $book->level=='99') {
                    $book->level = '';
                }
                if ($book->level) {
                    $book->publisher .= ' - '.$book->level;
                }
                if ($book->publisher) {
                    $book->name .= '<br /><small>('.$book->publisher.')</small>';
                }
                $book->words = number_format($book->words);
                $book->difficulty = get_string('difficultyshort', $plugin).'-'.$book->difficulty;

                // create checkbox for this attempt
                $params = array('type' => 'checkbox',
                                'name' => 'selected['.$attemptid1.']',
                                'value' => $attemptid2);
                $checkbox = html_writer::empty_tag('input', $params);

                // format date and times
                $date  = userdate($attempt1->timefinish, $datefmt);
                $time1 = userdate($attempt1->timefinish, $timefmt);
                $time2 = userdate($attempt2->timefinish, $timefmt);

                // format attempt durations
                $duration1 = $attempt1->timefinish - $attempt1->timestart;
                if ($duration1 <= 0) {
                    $duration1 = '';
                } else {
                    $duration1 = format_time($duration1);
                }
                $duration2 = $attempt2->timefinish - $attempt2->timestart;
                if ($duration2 <= 0) {
                    $duration2 = '';
                } else {
                    $duration2 = format_time($duration2);
                }

                echo '<td rowspan="'.$rowspan3.'" class="checkbox">'.$checkbox.'</td>';
                echo '<td rowspan="'.$rowspan3.'">'.$book->name.'</td>';
                echo '<td rowspan="'.$rowspan3.'" style="text-align: center;">'.$book->difficulty.'</td>';
                echo '<td rowspan="'.$rowspan3.'" style="text-align: center;">'.$book->words.'</td>';
                echo '<td rowspan="'.$rowspan3.'" style="text-align: center;">'.$courselink.'</td>';
                echo '<td rowspan="'.$rowspan3.'" style="text-align: center;">'.$readerlink.'</td>';
                echo '<td rowspan="'.$rowspan3.'" style="text-align: center;">'.$date.'</td>';

                $backgroundclass = ($backgroundclass=='even' ? 'odd' : 'even');

                echo '<td class="time1 '.$backgroundclass.'">'.$time1.'</td>';
                echo '<td class="'.$backgroundclass.'">'.$duration1.'</td>';
                echo '<td class="'.$backgroundclass.'">'.$attempt1->ip.'</td>';
                echo '<td class="'.$backgroundclass.'">'.reader_status_string($plugin, $attempt1).'</td>';
                echo "</tr>\n";

                echo '<tr class="attempt2">';
                echo '<td class="time2 '.$backgroundclass.'">'.$time2.'</td>';
                echo '<td class="'.$backgroundclass.'">'.$duration2.'</td>';
                echo '<td class="'.$backgroundclass.'">'.$attempt2->ip.'</td>';
                echo '<td class="'.$backgroundclass.'">'.reader_status_string($plugin, $attempt2).'</td>';
                echo "</tr>\n";
            }
        }
    }
}

/*
 * reader_print_action_form_start
 *
 * @param string  $plugin
 * @param integer $id
 * @param integer $tab
 * @return void
 */
function reader_print_action_form_start($plugin, $id, $tab) {

    // start the FORM
    $action = new moodle_url('/mod/reader/admin/tools/detect_cheating.php');
    $params = array('method' => 'post', 'action' => $action);
    echo html_writer::start_tag('form', $params);

    // hidden DIV for hidden elements
    echo html_writer::start_tag('div', array('style' => 'display: none;'));
    $params = array('type' => 'hidden', 'name' => 'id', 'value' => $id);
    echo html_writer::empty_tag('input', $params);
    $params = array('type' => 'hidden', 'name' => 'tab', 'value' => $tab);
    echo html_writer::empty_tag('input', $params);
    $params = array('type' => 'hidden', 'name' => 'confirmed', 'value' => '0');
    echo html_writer::empty_tag('input', $params)."\n";
    echo html_writer::end_tag('div');
}

/*
 * reader_print_action_form_end
 *
 * @param string  $plugin
 * @return void
 */
function reader_print_action_form_end($plugin) {

    // define confirm message for submit button
    $confirm = addslashes_js(get_string('confirm'));
    $selectsomerows = addslashes_js(get_string('selectsomerows', $plugin));
    $onclick = ''
        ."var found = 0;"
        ."if (this.form && this.form.elements) {"
            ."var i_max = this.form.elements.length;"
            ."for (var i=0; i<i_max; i++) {"
                ."var obj = this.form.elements[i];"
                ."if (obj.name.indexOf('selected')==0 && obj.checked) {"
                   ."found++;"
                ."}"
                ."obj = null;"
            ."}"
        ."}"
        ."if (found) {"
            ."found = confirm('$confirm');"
        ."} else {"
            ."alert('$selectsomerows');"
        ."}"
        ."if(found) {"
            ."if(this.form.elements['confirmed']) {"
                ."this.form.elements['confirmed'].value = '1';"
            ."}"
            ."return true;"
        ."} else {"
            ."return false;"
        ."}"
    ;

    // add action submit button
    echo html_writer::start_tag('div', array('class'=>'readerreportsubmit'));
    $name = 'go';
    $params = array('type'    => 'submit',
                    'name'    => $name,
                    'value'   => get_string($name),
                    'onclick' => $onclick);
    echo html_writer::empty_tag('input', $params);
    echo html_writer::end_tag('div');

    // finish FIELDSET and FORM
    echo html_writer::end_tag('form');
}

/*
 * reader_print_actions
 *
 * @param string  $plugin
 * @param array   $actions
 * @param integer $id
 * @param integer $tab
 * @return void
 */
function reader_print_actions($plugin, $actions, $id, $tab) {
    if (empty($actions)) {
        return false;
    }

    // start "actions" FIELDSET
    echo html_writer::start_tag('fieldset', array('class'=>'clearfix collapsible collapsed'));
    echo html_writer::tag('legend', get_string('actions'));

    array_unshift($actions, 'noaction');
    foreach ($actions as $action) {
        $function = 'reader_print_action_'.$action;
        if (function_exists($function)) {
            $function($plugin, $action);
        } else {
            reader_print_action($plugin, $action);
        }
    }

    echo html_writer::end_tag('fieldset');
}

/**
 * reader_print_action_updatepassed
 *
 * @param string $plugin
 * @param string $action
 * @return xxx
 */
function reader_print_action_updatepassed($plugin, $action) {
    $value = optional_param($action, 0, PARAM_INT);
    $settings = '';
    $settings .= get_string('newsetting', 'mod_reader').': ';
    $options = array(0 => get_string('failedshort', 'mod_reader').' - '.get_string('failed', 'mod_reader'),
                     1 => get_string('passedshort', 'mod_reader').' - '.get_string('passed', 'mod_reader'));
    $settings .= html_writer::select($options, $action, $value, '', array());
    reader_print_action($plugin, $action, $settings);
}

/**
 * reader_print_action_updatecheated
 *
 * @param string $plugin
 * @param string $action
 * @return xxx
 */
function reader_print_action_updatecheated($plugin, $action) {
    $value = optional_param($action, 0, PARAM_INT);
    $settings = '';
    $settings .= get_string('newsetting', 'mod_reader').': ';
    $options = array(0 => get_string('no'),
                     1 => get_string('yes').' - '.get_string('cheated', 'mod_reader'));
    $settings .= html_writer::select($options, $action, $value, '', array());
    reader_print_action($plugin, $action, $settings);
}

/**
 * reader_print_action
 *
 * @param string $action
 * @param string $settings (optional, default="")
 * @param string $label    (optional, default="")
 * @return xxx
 */
function reader_print_action($plugin, $action, $settings='', $label='') {
    echo html_writer::start_tag('div', array('id' => "readerreportaction_$action", 'class'=>'readerreportaction'));

    $name = 'action';
    $id = 'id_'.$name.'_'.$action;

    $params = array('type'  => 'radio',
                    'id'    => $id,
                    'name'  => $name,
                    'value' => $action);
    if ($action==optional_param($name, 'noaction', PARAM_ALPHA)) {
        $params['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $params);

    if ($label) {
        $label = get_string($label, $plugin);
    } else {
        $label = get_string($action, $plugin);
    }
    echo html_writer::tag('label', $label, array('for' => $id));

    if ($settings) {
        echo html_writer::tag('div', $settings, array('class' => 'actionsettings'));
    }

    echo html_writer::end_tag('div');
}

function reader_status_string($plugin, $attempt) {
    $grade = ' ('.round($attempt->percentgrade).'%)';
    if ($attempt->deleted) {
        return get_string('deleted').$grade;;
    }
    if ($attempt->cheated) {
        return get_string('cheated', $plugin).$grade;;
    }
    if ($attempt->credit) {
        return get_string('credit', $plugin).$grade;;
    }
    if (empty($attempt->timefinish)) {
        return get_string('inprogress', 'quiz');
    }
    if ($attempt->passed) {
        return get_string('passed', $plugin).$grade;
    } else {
        return get_string('failed', $plugin).$grade;
    }
}

function reader_status_menu($plugin) {
    return array(2 => get_string('credit',    $plugin),
                 1 => get_string('passed',    $plugin),
                 0 => get_string('inprogress', 'quiz'),
                -1 => get_string('failed',    $plugin),
                -2 => get_string('cheated',   $plugin),
                -4 => get_string('deleted'));
}

function reader_execute_action($plugin, $action) {
    global $DB, $OUTPUT;
    $selected  = reader_optional_param_array('selected', array(), PARAM_INT);
    if (count($selected)) {
        if (optional_param('confirmed', 0, PARAM_INT)==0) {
            // print confirmation page
        } else {
            switch ($action) {
                case 'updatecheated':
                    $field = 'cheated';
                    $value = optional_param($action, null, PARAM_INT);
                    break;
                case 'updatepassed':
                    $field = 'passed';
                    $value = optional_param($action, null, PARAM_INT);
                    break;
                default:
                    $field = null;
                    $value = null;
            }
            if (isset($field) && isset($value)) {
                $select = array_merge(array_keys($selected), $selected);
                $select = array_unique($select);
                sort($select);
                list($select, $params) = $DB->get_in_or_equal($select);
                $DB->set_field_select('reader_attempts', $field, $value, "id $select", $params);
                $msg = get_string('attemptsupdated', 'mod_reader', count($params));
                echo $OUTPUT->notification(html_writer::tag('big', $msg), 'notifysuccess');
            }
        }
    }
}

// ==========
// TODO
// ==========
// do not select attempts that are already marked as "cheated"
// send emails to students whose attempts are marked as "cheated"
// select 2 or more attempts, and show full comparison report of those students with no timelimit
// be sure "credit" and "cheated" are not confused anywhere