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
 * @package questionbank
 * @subpackage questiontypes
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

class back_truefalse_qtype {

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        global $DB;
        $status = true;

        //Get the truefalse array
        if (array_key_exists('TRUEFALSE', $info['#'])) {
            $truefalses = $info['#']['TRUEFALSE'];
        } else {
            $truefalses = array();
        }

        //Iterate over truefalse
        for($i = 0; $i < sizeof($truefalses); $i++) {
            $tru_info = $truefalses[$i];

            //Now, build the question_truefalse record structure
            $truefalse = new stdClass;
            $truefalse->question = $new_question_id;
            $truefalse->trueanswer = stripslashes(backup_todb($tru_info['#']['TRUEANSWER']['0']['#']));
            $truefalse->falseanswer = stripslashes(backup_todb($tru_info['#']['FALSEANSWER']['0']['#']));

            ////We have to recode the trueanswer field
            $answer = backup_getid($restore->backup_unique_code,"question_answers",$truefalse->trueanswer);
            if ($answer) {
                $truefalse->trueanswer = $answer->new_id;
            }

            ////We have to recode the falseanswer field
            $answer = backup_getid($restore->backup_unique_code,"question_answers",$truefalse->falseanswer);
            if ($answer) {
                $truefalse->falseanswer = $answer->new_id;
            }

            //The structure is equal to the db, so insert the question_truefalse
            $newid = $DB->insert_record ("question_truefalse", $truefalse);

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
