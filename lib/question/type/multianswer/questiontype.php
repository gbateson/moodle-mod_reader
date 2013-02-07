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

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

class back_multianswer_qtype {

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        global $DB;
        $status = true;

        //Get the multianswers array
        if (empty($info['#']['MULTIANSWERS']['0']['#']['MULTIANSWER'])) {
            $multianswers = array();
        } else {
            $multianswers = $info['#']['MULTIANSWERS']['0']['#']['MULTIANSWER'];
        }
        //Iterate over multianswers
        for($i = 0; $i < sizeof($multianswers); $i++) {
            $mul_info = $multianswers[$i];

            //We need this later
            $oldid = backup_todb($mul_info['#']['ID']['0']['#']);

            //Now, build the question_multianswer record structure
            $multianswer = new stdClass;
            $multianswer->question = $new_question_id;
            $multianswer->sequence = backup_todb($mul_info['#']['SEQUENCE']['0']['#']);


            //We have to recode the sequence field (a list of question ids)
            //Extracts question id from sequence
            $sequence_field = "";
            $in_first = true;
            //$tok = strtok($multianswer->sequence,",");
            $tok = explode(',', $multianswer->sequence);
            while (list($key,$value) = each($tok)) {
              if (! empty($value)) {
                //Get the answer from backup_ids
                $question = backup_getid($restore->backup_unique_code,"question",$value);
                if ($question) {
                    if ($in_first) {
                        $sequence_field .= $question->new_id;
                        $in_first = false;
                    } else {
                        $sequence_field .= ",".$question->new_id;
                    }
                }
              }
            }
            //We have the answers field recoded to its new ids
            $multianswer->sequence = $sequence_field;
            //The structure is equal to the db, so insert the question_multianswer

            $newid = $DB->insert_record('question_multianswer', $multianswer);

            //Save ids in backup_ids
            if ($newid) {
                backup_putid($restore->backup_unique_code,"question_multianswer",
                             $oldid, $newid);
            }

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
        }

        return $status;
    }

    /**
     * restore_map
     *
     * @uses $DB
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_map($old_question_id,$new_question_id,$info,$restore) {
        global $DB;
        $status = true;

        //Get the multianswers array
        $multianswers = $info['#']['MULTIANSWERS']['0']['#']['MULTIANSWER'];
        //Iterate over multianswers
        for($i = 0; $i < sizeof($multianswers); $i++) {
            $mul_info = $multianswers[$i];

            //We need this later
            $oldid = backup_todb($mul_info['#']['ID']['0']['#']);

            //Now, build the question_multianswer record structure
            $multianswer->question = $new_question_id;
            $multianswer->answers = stripslashes(backup_todb($mul_info['#']['ANSWERS']['0']['#']));
            $multianswer->positionkey = backup_todb($mul_info['#']['POSITIONKEY']['0']['#']);
            $multianswer->answertype = backup_todb($mul_info['#']['ANSWERTYPE']['0']['#']);
            $multianswer->norm = backup_todb($mul_info['#']['NORM']['0']['#']);

            //If we are in this method is because the question exists in DB, so its
            //multianswer must exist too.
            //Now, we are going to look for that multianswer in DB and to create the
            //mappings in backup_ids to use them later where restoring states (user level).

            //Get the multianswer from DB (by question and positionkey)
            $db_multianswer = $DB->get_record ("question_multianswer",array('question' => $new_question_id,
                                                      'positionkey' => $multianswer->positionkey));
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

            //We have the database multianswer, so update backup_ids
            if ($db_multianswer) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"question_multianswer",$oldid,
                             $db_multianswer->id);
            } else {
                $status = false;
            }
        }

        return $status;
    }
}

