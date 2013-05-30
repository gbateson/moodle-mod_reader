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
    function restore($old_question_id, $new_question_id, $info, $restore) {
        global $DB;
        $status = true;

        //Get the orderings array
        $orderings = $info['#']['ORDERING'];

        //Iterate over orderings
        foreach ($orderings as $i => $ordering) {

            // build the question_ordering record structure
            $question_ordering = new stdClass;

            $fields = array(
                'question'   => 0, // the question id
                'logical'    => 0, // 0=all, 1=random subset, 2=contiguous subset
                'studentsee' => 0, // how many items will be shown to the students
                'correctfeedback' => '',
                'incorrectfeedback' => '',
                'partiallycorrectfeedback' => '',
            );

            foreach ($fields as $fieldname => $default) {
                $FIELDNAME = strtoupper($fieldname);
                if (array_key_exists($FIELDNAME, $ordering['#'])) {
                    $question_ordering->$fieldname = backup_todb($ordering['#'][$FIELDNAME]['0']['#']);
                } else {
                    $question_ordering->$fieldname = $default;
                }
            }

            $question_ordering->question = $new_question_id;
            $newid = $DB->insert_record ('question_ordering', $question_ordering);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (! defined('RESTORE_SILENTLY')) {
                    echo '.';
                    if (($i+1) % 1000 == 0) {
                        echo '<br />';
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
