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

class back_description_qtype {

    /**
     * restore
     *
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        // The default question type has nothing to restore
        return true;
    }

    /**
     * restore_map
     *
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_map($old_question_id,$new_question_id,$info,$restore) {
        // There is nothing to decode
        return true;
    }

}

