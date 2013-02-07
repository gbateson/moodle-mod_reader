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

///
/// This class contains some special features in order to make the
/// question type embeddable within a multianswer (cloze) question
///

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

class back_ordering_qtype  {

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        global $DB;
        $status = true;

        //Get the orderings array
        $orderings = $info['#']['ORDERING'];

        //Iterate over orderings
        for($i = 0; $i < sizeof($orderings); $i++) {
            $mul_info = $orderings[$i];

            //Now, build the question_ordering record structure
            $ordering = new stdClass;
            $ordering->question = $new_question_id;
            $ordering->logical = backup_todb($mul_info['#']['LOGICAL']['0']['#']);
            $ordering->studentsee = backup_todb($mul_info['#']['STUDENTSEE']['0']['#']);
            if (array_key_exists("CORRECTFEEDBACK", $mul_info['#'])) {
                $ordering->correctfeedback = backup_todb($mul_info['#']['CORRECTFEEDBACK']['0']['#']);
            } else {
                $ordering->correctfeedback = '';
            }
            if (array_key_exists("PARTIALLYCORRECTFEEDBACK", $mul_info['#'])) {
                $ordering->partiallycorrectfeedback = backup_todb($mul_info['#']['PARTIALLYCORRECTFEEDBACK']['0']['#']);
            } else {
                $ordering->partiallycorrectfeedback = '';
            }
            if (array_key_exists("INCORRECTFEEDBACK", $mul_info['#'])) {
                $ordering->incorrectfeedback = backup_todb($mul_info['#']['INCORRECTFEEDBACK']['0']['#']);
            } else {
                $ordering->incorrectfeedback = '';
            }

            //We have to recode the answers field (a list of answers id)
            //Extracts answer id from sequence
            $answers_field = "";
            $in_first = true;
            $tok = @strtok($ordering->answers,",");
            while ($tok) {
                //Get the answer from backup_ids
                $answer = backup_getid($restore->backup_unique_code,"question_answers",$tok);
                if ($answer) {
                    if ($in_first) {
                        $answers_field .= $answer->new_id;
                        $in_first = false;
                    } else {
                        $answers_field .= ",".$answer->new_id;
                    }
                }
                //check for next
                $tok = strtok(",");
            }
            //We have the answers field recoded to its new ids
            $ordering->answers = $answers_field;

            //The structure is equal to the db, so insert the question_shortanswer
            $newid = $DB->insert_record ("question_ordering",$ordering);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (! defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if (! $newid) {
                $status = false;
            }
        }

        return $status;
    }

}
