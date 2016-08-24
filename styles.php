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
 * mod/reader/styles.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();
 ?>

/*** Modules: Reader ***/

#mod-reader-view .quizinfo {
  text-align: center;
}
#mod-reader-view #page .quizgradefeedback,
#mod-reader-view #page .quizattempt {
  text-align: center;
}
#mod-reader-view #page .quizattemptsummary td p {
  margin-top: 0;
}
#mod-reader-view .generalbox#feedback {
  width:70%;
  margin-left:auto;
  margin-right:auto;
  padding-bottom:15px;
}
#mod-reader-view .generalbox#feedback h2 {
  margin: 0 0;
}

body#mod-reader-view .generalbox#feedback .overriddennotice {
  text-align: center;
  font-size: 0.7em;
}
#mod-reader-view .generalbox#feedback h3 {
  text-align: left;
}

.generalbox#passwordbox { /* Should probably match .generalbox#intro above */
  width:70%;
  margin-left:auto;
  margin-right:auto;
}
#passwordform {
  margin: 1em 0;
}
#mod-reader-attempt #page {
  text-align: center;
}
#mod-reader-attempt .pagingbar {
  margin: 1.5em auto;
}
#mod-reader-attempt #page {
    text-align: center;
}

#mod-reader-attempt #timer .generalbox {
  width:150px
}

#mod-reader-attempt #timer {
  position:absolute;
  /*top:100px; is set by js*/
  left:10px
}

body#question-preview .quemodname,
body#question-preview .controls {
  text-align: center;
}
body#question-preview .quemodname, body#question-preview .controls {
  text-align: center;
}

#mod-reader-attempt #page .controls,
#mod-reader-review #page .controls {
  text-align: center;
  margin: 8px auto;
}
#mod-reader-review .pagingbar {
  margin: 1.5em auto;
}
#mod-reader-review .pagingbar {
  margin: 1.5em auto;
}
table.quizreviewsummary {
  margin-bottom: 1.8em;
  width: 100%;
}
table.quizreviewsummary tr {
}
table.quizreviewsummary th.cell {
  padding: 1px 0.5em 1px 1em;
  font-weight: bold;
  text-align: right;
  width: 10em;
}
table.quizreviewsummary td.cell {
  padding: 1px 1em 1px 0.5em;
}

#mod-reader-mod #reviewoptionshdr .fitem {
  float: left;
  width: 30%;
  margin-left: 10px;
  clear: none;
}
#mod-reader-mod #reviewoptionshdr .fitemtitle {
  width: 100%;
  font-weight: bold;
  text-align: left;
  height: 2.5em;
 margin-left: 0;
}
#mod-reader-mod #reviewoptionshdr fieldset.fgroup {
  width: 100%;
  text-align: left;
 margin-left: 0;
}
#mod-reader-mod #reviewoptionshdr fieldset.fgroup span {
  float: left;
  clear: left;
}

#mod-reader-edit #page .controls,
#mod-reader-edit #page .quizattemptcounts {
  text-align: center;
}
#mod-reader-edit .quizquestions h2 {
  margin-top: 0;
}
#mod-reader-edit #showbreaks {
  margin-top: 0.7em;
}
.quizquestionlistcontrols {
  text-align: center;
}

#mod-reader-report table#attempts,
#mod-reader-report table#commands,
#mod-reader-report table#itemanalysis {
  width: 80%;
  margin: auto;
}
#mod-reader-report table#attempts,
#mod-reader-report h2.main {
  clear: both;
}
#mod-reader-report table#attempts {
  margin: 20px auto;
}
#mod-reader-report table#attempts .header,
#mod-reader-report table#attempts .cell {
  padding: 4px;
}
#mod-reader-report table#attempts .header .commands {
  display: inline;
}
#mod-reader-report table#attempts .picture {
  width: 40px;
}
#mod-reader-report table#attempts td {
  border-left-width: 1px;
  border-right-width: 1px;
  border-left-style: solid;
  border-right-style: solid;
  vertical-align: middle;
}
#mod-reader-report table#attempts .header {
  text-align: left;
}
#mod-reader-report table#attempts .picture {
  text-align: center !important;
}
#mod-reader-report .controls {
  text-align: center;
}

#mod-reader-report table#itemanalysis {
  margin: 20px auto;
}
#mod-reader-report table#itemanalysis .header,
#mod-reader-report table#itemanalysis .cell {
  padding: 4px;
}
#mod-reader-report table#itemanalysis .header .commands {
  display: inline;
}
#mod-reader-report table#itemanalysis td {
  border-width: 1px;
  border-style: solid;
}
#mod-reader-report table#itemanalysis .header {
  text-align: left;
}
#mod-reader-report table#itemanalysis .numcol {
  text-align: center;
  vertical-align : middle !important;
}

#mod-reader-report table#itemanalysis .uncorrect {
  color: red;
}

#mod-reader-report table#itemanalysis .correct {
  color: blue;
  font-weight : bold;
}

#mod-reader-report table#itemanalysis .partialcorrect {
  color: green !important;
}

#mod-reader-report table#itemanalysis .qname {
  color: green !important;
}

/* manual grading */
#mod-reader-grading table#grading {
  width: 80%;
  margin: auto;
}

#mod-reader-grading table#grading {
  margin: 20px auto;
}

#mod-reader-grading table#grading .header,
#mod-reader-grading table#grading .cell {
  padding: 4px;
}

#mod-reader-grading table#grading .header .commands {
  display: inline;
}

#mod-reader-grading table#grading .picture {
  width: 40px;
}

#mod-reader-grading table#grading td {
  border-left-width: 1px;
  border-right-width: 1px;
  border-left-style: solid;
  border-right-style: solid;
  vertical-align: bottom;
}

.mod-reader .gradingdetails {
  font-size: small;
}
.quizattemptcounts {
  text-align: center;
  margin: 6px 0;
}
