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
 * Question bank restore code.
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 *//** */

// Todo:
    // the restoration of the parent and sortorder fields in the category table needs
    // a lot more thought. We should probably use a library function to add the category
    // rather than just writing it to the database

    // whereever it says "/// We have to recode the .... field" we should put in a check
    // to see if the recoding was successful and throw an appropriate error otherwise

//This is the "graphical" structure of the question database:
    //To see, put your terminal to 160cc

    // The following holds student-independent information about the questions
    //
    //          question_categories
    //             (CL,pk->id)
    //                  |
    //                  |
    //                  |.......................................
    //                  |                                      .
    //                  |                                      .
    //                  |    -------question_datasets------    .
    //                  |    |  (CL,pk->id,fk->question,  |    .
    //                  |    |   fk->dataset_definition)  |    .
    //                  |    |                            |    .
    //                  |    |                            |    .
    //                  |    |                            |    .
    //                  |    |                    question_dataset_definitions
    //                  |    |                      (CL,pk->id,fk->category)
    //              question                                   |
    //        (CL,pk->id,fk->category,files)                   |
    //                  |                             question_dataset_items
    //                  |                          (CL,pk->id,fk->definition)
    //                  |
    //                  |
    //                  |
    //             --------------------------------------------------------------------------------------------------------------
    //             |             |              |              |                       |                  |                     |
    //             |             |              |              |                       |                  |                     |
    //             |             |              |              |             question_calculated          |                     |
    //      question_truefalse   |     question_multichoice    |          (CL,pl->id,fk->question)        |                     |
    // (CL,pk->id,fk->question)  |   (CL,pk->id,fk->question)  |                       .                  |                     |  question_randomsamatch
    //             .             |              .              |                       .                  |                     |--(CL,pk->id,fk->question)
    //             .    question_shortanswer    .      question_numerical              .         question_multianswer.          |
    //             .  (CL,pk->id,fk->question)  .  (CL,pk->id,fk->question)            .        (CL,pk->id,fk->question)        |
    //             .             .              .              .                       .                  .                     |       question_match
    //             .             .              .              .                       .                  .                     |--(CL,pk->id,fk->question)
    //             .             .              .              .                       .                  .                     |             .
    //             .             .              .              .                       .                  .                     |             .
    //             .             .              .              .                       .                  .                     |             .
    //             .             .              .              .                       .                  .                     |    question_match_sub
    //             ........................................................................................                     |--(CL,pk->id,fk->question)
    //                                                   .                                                                      |
    //                                                   .                                                                      |
    //                                                   .                                                                      |  question_numerical_units
    //                                             question_answers                                                             |--(CL,pk->id,fk->question)
    //                                         (CL,pk->id,fk->question)----------------------------------------------------------
    //
    //
    // The following holds the information about student interaction with the questions
    //
    //             question_sessions
    //      (UL,pk->id,fk->attempt,question)
    //                    .
    //                    .
    //             question_states
    //       (UL,pk->id,fk->attempt,question)
    //
    // Meaning: pk->primary key field of the table
    //          fk->foreign key to link with parent
    //          nt->nested field (recursive data)
    //          SL->site level info
    //          CL->course level info
    //          UL->user level info
    //          files->table may have files
    //
    //-----------------------------------------------------------

    require_once($CFG->dirroot.'/lib/questionlib.php');
    /**
    * Returns the best question category (id) found to restore one
    * question category from a backup file. Works by stamp.
    *
    * @param object $restore preferences for restoration
    * @param array $contextinfo fragment of decoded xml
    * @return object best context instance for this category to be in
    */
    function restore_question_get_best_category_context($restore, $contextinfo) {
        switch ($contextinfo['LEVEL'][0]['#']) {
            case 'module':
                if (! $instanceinfo = backup_getid($restore->backup_unique_code, 'course_modules', $contextinfo['INSTANCE'][0]['#'])){
                    //module has not been restored, probably not selected for restore
                    return false;
                }
                $tocontext = reader_get_context(CONTEXT_MODULE, $instanceinfo->new_id);
                break;
            case 'course':
                $tocontext = reader_get_context(CONTEXT_COURSE, $restore->course_id);
                break;
            case 'coursecategory':
                //search COURSECATEGORYLEVEL steps up the course cat tree or
                //to the top of the tree if steps are exhausted.
                $catno = $contextinfo['COURSECATEGORYLEVEL'][0]['#'];
                $catid = get_field('course', 'category', 'id', $restore->course_id);
                while ($catno > 1){
                    $nextcatid = get_field('course_categories', 'parent', 'id', $catid);
                    if ($nextcatid == 0){
                        break;
                    }
                    $catid == $nextcatid;
                    $catno--;
                }
                $tocontext = reader_get_context(CONTEXT_COURSECAT, $catid);
                break;
            case 'system':
                $tocontext = reader_get_context(CONTEXT_SYSTEM);
                break;
        }
        return $tocontext;
    }

    /**
     * restore_question_categories
     *
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_question_categories($info, $restore) {
        $status = true;
        //Iterate over each category
        reset($info);
        while (list($key,$category) = each($info)) {
          if ($category->id != 226) {
             //echo "Restore start: ".$category->id;
            $status = $status && restore_question_category($category, $restore);
            //echo "Restore end: ".$category->id;
          }
        }
        $status = $status && restore_recode_category_parents($restore);
        return $status;
    }

    /**
     * restore_question_category
     *
     * @uses $DB
     * @param xxx $category
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_question_category($category, $restore){
        global $DB;
        $status = true;
        //Skip empty categories (some backups can contain them)

        if (! empty($category->id)) {
            //Get record from reader_backup_ids
            $data = backup_getid($restore->backup_unique_code, "question_categories", $category->id);

            if ($data) {
                //Now get completed xmlized object
                $info = $data->info;
                //traverse_xmlize($info);                                                                     //Debug
                //print_object ($GLOBALS['traverse_array']);                                                  //Debug
                //$GLOBALS['traverse_array']="";                                                              //Debug

                //Now, build the question_categories record structure
                $question_cat = new stdClass;
                $question_cat->name = backup_todb($info['QUESTION_CATEGORY']['#']['NAME']['0']['#']);
                $question_cat->info = backup_todb($info['QUESTION_CATEGORY']['#']['INFO']['0']['#']);
                $question_cat->stamp = backup_todb($info['QUESTION_CATEGORY']['#']['STAMP']['0']['#']);
                //parent is fixed after all categories are restored and we know all the new ids.
                $question_cat->parent = backup_todb($info['QUESTION_CATEGORY']['#']['PARENT']['0']['#']);
                $question_cat->sortorder = backup_todb($info['QUESTION_CATEGORY']['#']['SORTORDER']['0']['#']);
                if (! $question_cat->stamp) {
                    $question_cat->stamp = make_unique_id_code();
                }
                if (isset($info['QUESTION_CATEGORY']['#']['PUBLISH'])) {
                    $course = $restore->course_id;
                    $publish = backup_todb($info['QUESTION_CATEGORY']['#']['PUBLISH']['0']['#']);
                    if ($publish){
                        $tocontext = reader_get_context(CONTEXT_SYSTEM);
                    } else {
                        $tocontext = reader_get_context(CONTEXT_COURSE, $course);
                    }
                } else {
                    if (! $tocontext = restore_question_get_best_category_context($restore, $info['QUESTION_CATEGORY']['#']['CONTEXT']['0']['#'])){
                        return $status; // context doesn't exist - a module has not been restored
                    }
                }
                $question_cat->contextid = $tocontext->id;

                //does cat exist ?? if it does we check if the cat and questions already exist whether we have
                //add permission or not if we have no permission to add questions to SYSTEM or COURSECAT context
                //AND the question does not already exist then we create questions in COURSE context.
                if (! $fcat = $DB->get_record('question_categories',array('contextid'=> $question_cat->contextid, 'stamp'=> $question_cat->stamp))){
                    //no preexisting cat
                    if ((($tocontext->contextlevel == CONTEXT_SYSTEM) ||  ($tocontext->contextlevel == CONTEXT_COURSECAT))
                            && !has_capability('moodle/question:add', $tocontext)){
                        //no preexisting cat and no permission to create questions here
                        //must restore to course.
                        $tocontext = reader_get_context(CONTEXT_COURSE, $restore->course_id);
                    }
                    $question_cat->contextid = $tocontext->id;
                    if (! $fcat = $DB->get_record('question_categories',array('contextid'=> $question_cat->contextid, 'stamp'=> $question_cat->stamp))){
                        $question_cat->id = $DB->insert_record ("question_categories", $question_cat);
                    } else {
                        $question_cat = $fcat;
                    }
                    //we'll be restoring all questions here.
                    backup_putid($restore->backup_unique_code, "question_categories", $category->id, $question_cat->id);
                } else {
                    $question_cat = $fcat;
                    //we found an existing best category
                    //but later if context is above course need to check if there are questions need creating in category
                    //if we do need to create questions and permissions don't allow it create new category in course
                }

                //Do some output
                if (! defined('RESTORE_SILENTLY')) {
                    echo "<li>".get_string('category', 'quiz')." \"".$question_cat->name."\"<br />";
                }

                backup_flush(300);

                //start with questions
                //echo "Adding questions start ".$question_cat->id."<br />";
                if ($question_cat->id) {
                    //We have the newid, update reader_backup_ids
                    //Now restore question
                    //print_r ($info);
                    $status = restore_questions($category->id, $question_cat, $info, $restore);
                    //echo "Adding questions end ".$question_cat->id."<br />";
                } else {
                    $status = false;
                }
                if (! defined('RESTORE_SILENTLY')) {
                    echo '</li>';
                }
            } else {
                echo 'Could not get backup info for question category'. $category->id;
            }
        }
        return $status;
    }

    /**
     * restore_recode_category_parents
     *
     * @uses $CFG
     * @uses $DB
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_recode_category_parents($restore){
        global $CFG, $DB;
        $status = true;
        //Now we have to recode the parent field of each restored category
        $categories = $DB->get_records_sql("SELECT old_id, new_id
                                       FROM {reader_backup_ids}
                                       WHERE backup_code = ? AND
                                             table_name = 'question_categories'", array($restore->backup_unique_code));
        if ($categories) {
            //recode all parents to point at their old parent cats no matter what context the parent is now in
            foreach ($categories as $category) {
                $restoredcategory = $DB->get_record('question_categories',array('id'=>$category->new_id));
                if ($restoredcategory && $restoredcategory->parent != 0) {
                    $updateobj = new object();
                    $updateobj->id = $restoredcategory->id;
                    $idcat = backup_getid($restore->backup_unique_code,'question_categories',$restoredcategory->parent);
                    if ($idcat->new_id) {
                        $updateobj->parent = $idcat->new_id;
                    } else {
                        $updateobj->parent = 0;
                    }
                    $status = $status && $DB->update_record('question_categories', $updateobj);
                }
            }
            //now we have recoded all parents, check through all parents and set parent to be
            //grand parent / great grandparent etc where there is one in same context
            //or else set parent to 0 (top level category).
            $toupdate = array();
            foreach ($categories as $category) {
                $restoredcategory = $DB->get_record('question_categories',array('id'=>$category->new_id));
                if ($restoredcategory && $restoredcategory->parent != 0) {
                    $nextparentid = $restoredcategory->parent;
                    do {
                        if (! $parent = $DB->get_record('question_categories', array('id'=> $nextparentid))){
                            if (! defined('RESTORE_SILENTLY')) {
                                echo 'Could not find parent for question category '. $category->id.' recoding as top category item.<br />';
                            }
                            break;//record fetch failed finish loop
                        } else {
                            $nextparentid = $parent->parent;
                        }
                    } while (($nextparentid != 0) && ($parent->contextid != $restoredcategory->contextid));
                    if (! $parent || ($parent->id != $restoredcategory->parent)){
                        //change needs to be made to the parent field.
                        if ($parent && ($parent->contextid == $restoredcategory->contextid)){
                            $toupdate[$restoredcategory->id] = $parent->id;
                        } else {
                            //searched up the tree till we came to the top and did not find cat in same
                            //context or there was an error getting next parent record
                            $toupdate[$restoredcategory->id] = 0;
                        }
                    }
                }
            }
            //now finally do the changes to parent field.
            foreach ($toupdate as  $id => $parent){
                $updateobj = new object();
                $updateobj->id = $id;
                $updateobj->parent = $parent;
                $status = $status && $DB->update_record('question_categories', $updateobj);
            }
        }
        return $status;
    }

    /**
     * restore_questions
     *
     * @uses $CFG
     * @uses $DB
     * @uses $QTYPES
     * @param xxx $old_category_id
     * @param xxx $best_question_cat
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function restore_questions($old_category_id, $best_question_cat, $info, $restore) {

        global $CFG, $DB, $QTYPES;

        $status = true;
        $restored_questions = array();

        //Get the questions array
        if (! empty($info['QUESTION_CATEGORY']['#']['QUESTIONS'])) {
            $questions = $info['QUESTION_CATEGORY']['#']['QUESTIONS']['0']['#']['QUESTION'];
        } else {
            $questions = array();
        }
        //print_r ($info);
        //print_r ($questions);
        //die;

        //Iterate over questions
        for($i = 0; $i < sizeof($questions); $i++) {
            $que_info = $questions[$i];
            //traverse_xmlize($que_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($que_info['#']['ID']['0']['#']);

            //Now, build the question record structure
            $question = new object;
            $question->parent = backup_todb($que_info['#']['PARENT']['0']['#']);
            //$question->name = str_replace(array("\'", '\"'), array("'", '"'),backup_todb($que_info['#']['NAME']['0']['#']));
            $question->name = stripslashes(backup_todb($que_info['#']['NAME']['0']['#']));
            //$question->questiontext = str_replace(array("\'", '\"'), array("'", '"'),backup_todb($que_info['#']['QUESTIONTEXT']['0']['#']));
            $question->questiontext = stripslashes(backup_todb($que_info['#']['QUESTIONTEXT']['0']['#']));
            $question->questiontextformat = backup_todb($que_info['#']['QUESTIONTEXTFORMAT']['0']['#']);
            $question->image = backup_todb($que_info['#']['IMAGE']['0']['#']);
            $question->generalfeedback = backup_todb_optional_field($que_info, 'GENERALFEEDBACK', '');
            $question->defaultgrade = backup_todb($que_info['#']['DEFAULTGRADE']['0']['#']);
            $question->penalty = backup_todb($que_info['#']['PENALTY']['0']['#']);
            $question->qtype = backup_todb($que_info['#']['QTYPE']['0']['#']);
            $question->length = backup_todb($que_info['#']['LENGTH']['0']['#']);
            $question->stamp = backup_todb($que_info['#']['STAMP']['0']['#']);
            $question->version = backup_todb($que_info['#']['VERSION']['0']['#']);
            $question->hidden = backup_todb($que_info['#']['HIDDEN']['0']['#']);
            $question->timecreated = backup_todb_optional_field($que_info, 'TIMECREATED', 0);
            $question->timemodified = backup_todb_optional_field($que_info, 'TIMEMODIFIED', 0);
            $question->createdby = backup_todb_optional_field($que_info, 'CREATEDBY', null);
            $question->modifiedby = backup_todb_optional_field($que_info, 'MODIFIEDBY', null);

            //Check if the question exists by category, stamp, and version
            //first check for the question in the context specified in backup
            $existingquestion = $DB->get_record ("question", array('category' => $best_question_cat->id, 'stamp' => $question->stamp,'version' => $question->version));
            //If the question exists, only record its id
            //always use existing question, no permissions check here
            if ($existingquestion) {
                $question = $existingquestion;
                $creatingnewquestion = false;
            } else {
                //then if context above course level check permissions and if no permission
                //to restore above course level then restore to cat in course context.
                $bestcontext = get_context_instance_by_id($best_question_cat->contextid);
                if (($bestcontext->contextlevel == CONTEXT_SYSTEM ||  $bestcontext->contextlevel == CONTEXT_COURSECAT)
                        && !has_capability('moodle/question:add', $bestcontext)){
                    if (! isset($course_question_cat)) {
                        $coursecontext = reader_get_context(CONTEXT_COURSE, $restore->course_id);
                        $course_question_cat = clone($best_question_cat);
                        $course_question_cat->contextid = $coursecontext->id;
                        //create cat if it doesn't exist
                        if (! $fcat = $DB->get_record('question_categories','contextid', $course_question_cat->contextid, 'stamp', $course_question_cat->stamp)){
                            $course_question_cat->id = $DB->insert_record ("question_categories", $course_question_cat);
                            backup_putid($restore->backup_unique_code, "question_categories", $old_category_id, $course_question_cat->id);
                        } else {
                            $course_question_cat = $fcat;
                        }
                        //will fix category parents after all questions and categories restored. Will set parent to 0 if
                        //no parent in same context.
                    }
                    $question->category = $course_question_cat->id;

                    //does question already exist in course cat
                    $existingquestion = $DB->get_record ("question", array('category' => $question->category, 'stamp' => $question->stamp, 'version' => $question->version));
                } else {
                    //permissions ok, restore to best cat
                    $question->category = $best_question_cat->id;
                }
                if (! $existingquestion){
                    //The structure is equal to the db, so insert the question
                    //print_r ($question);
                    $question->id = $DB->insert_record ("question", $question);

                    $creatingnewquestion = true;
                } else {
                    $question = $existingquestion;
                    $creatingnewquestion = false;
                }
            }

            // Fixing bug #5482: random questions have parent field set to its own id,
            //                   see: $QTYPES['random']->get_question_options()
            if ($question->qtype == 'random' && $creatingnewquestion) {
                $question->parent = $question->id;
                $status = $DB->set_field('question', 'parent', $question->parent, array('id' => $question->id));
            }

            //Save newid to backup tables
            if ($question->id) {
                //We have the newid, update reader_backup_ids
                backup_putid($restore->backup_unique_code, "question", $oldid, $question->id);
            }

            $restored_questions[$i] = new stdClass;
            $restored_questions[$i]->newid  = $question->id;
            $restored_questions[$i]->oldid  = $oldid;
            $restored_questions[$i]->qtype  = $question->qtype;
            $restored_questions[$i]->parent = $question->parent;
            $restored_questions[$i]->is_new = $creatingnewquestion;
        }
        backup_flush(300);

        // Loop again, now all the question id mappings exist, so everything can
        // be restored.
        for($i = 0; $i < sizeof($questions); $i++) {
            $que_info = $questions[$i];

            $newid = $restored_questions[$i]->newid;
            $oldid = $restored_questions[$i]->oldid;

            $question = new object;
            $question->qtype = $restored_questions[$i]->qtype;
            $question->parent = $restored_questions[$i]->parent;
                    //echo "$oldid,$newid";
                    //print_r ($que_info);
                    //print_r ($restore);
                    //die;

        /// If it's a new question in the DB, restore it
        //echo $question->qtype."<br />";
            //if ($restored_questions[$i]->is_new) {
            /// We have to recode the parent field
                if ($question->parent && $question->qtype != 'random') {
                /// If the parent field needs to be changed, do it here. Random questions are dealt with above.
                    if ($parent = backup_getid($restore->backup_unique_code,"question",$question->parent)) {
                        $question->parent = $parent->new_id;
                        if ($question->parent != $restored_questions[$i]->parent) {
                            if (! $DB->set_field('question', 'parent', $question->parent, array('id' => $newid))) {
                                echo 'Could not update parent '.$question->parent.' for question '.$oldid.'<br />';
                                $status = false;
                            }
                        }
                    } else {
                        echo 'Reset parent id ('.$question->parent.') for question '.$oldid.' (newid='.$newid.') to zero<br />';
                        $DB->set_field('question', 'parent', 0, array('id' => $newid));
                        $restored_questions[$i]->parent = 0;
                        $question->parent = 0;
                        // Note: we can continue, so do not set status to false
                    }
                }

                //Now, restore every question_answers in this question
                $status = question_restore_answers($oldid,$newid,$que_info,$restore);
                // Restore questiontype specific data
                if (array_key_exists($question->qtype, $QTYPES)) {
                    $status = $QTYPES[$question->qtype]->restore($oldid,$newid,$que_info,$restore);
                } else {
                    echo 'Unknown question type '.$question->qtype.' for question '.$oldid.'<br />';
                    $status = false;
                }

            //}

            //Do some output
            if (($i+1) % 2 == 0) {
                if (! defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 40 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }
        }

        return $status;
    }

    /**
     * backup_todb_optional_field
     *
     * @param xxx $data
     * @param xxx $field
     * @param xxx $default
     * @return xxx
     * @todo Finish documenting this function
     */
    function backup_todb_optional_field($data, $field, $default) {
        if (array_key_exists($field, $data['#'])) {
            return backup_todb($data['#'][$field]['0']['#']);
        } else {
            return $default;
        }
    }

    /**
     * question_restore_answers
     *
     * @uses $CFG
     * @uses $DB
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_restore_answers($old_question_id,$new_question_id,$info,$restore) {

        global $CFG, $DB;

        $status = true;
        $qtype = backup_todb($info['#']['QTYPE']['0']['#']);

        //Get the answers array
        if (isset($info['#']['ANSWERS']['0']['#']['ANSWER'])) {
            $answers = $info['#']['ANSWERS']['0']['#']['ANSWER'];

            //Iterate over answers
            for($i = 0; $i < sizeof($answers); $i++) {
                $ans_info = $answers[$i];
                //traverse_xmlize($ans_info);                                                                 //Debug
                //print_object ($GLOBALS['traverse_array']);                                                  //Debug
                //$GLOBALS['traverse_array']="";                                                              //Debug

                //We'll need this later!!
                $oldid = backup_todb($ans_info['#']['ID']['0']['#']);

                //Now, build the question_answers record structure
                $answer = new stdClass;
                $answer->question = $new_question_id;
                $answer->answer = stripslashes(backup_todb($ans_info['#']['ANSWER_TEXT']['0']['#']));
                $answer->fraction = backup_todb($ans_info['#']['FRACTION']['0']['#']);
                $answer->feedback = backup_todb($ans_info['#']['FEEDBACK']['0']['#']);

                // Update 'match everything' answers for numerical questions coming from old backup files.
                if ($qtype == 'numerical' && $answer->answer == '') {
                    $answer->answer = '*';
                }

                $noneed = 0;

                if ($qtype == 'multichoice' && empty($answer->answer)) {
                    $noneed = 1;
                }

                if ($noneed == 0) {
                  //The structure is equal to the db, so insert the question_answers
                  $newid = $DB->insert_record ("question_answers",$answer);

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

                  if ($newid) {
                      //We have the newid, update reader_backup_ids
                      backup_putid($restore->backup_unique_code,"question_answers",$oldid,
                                   $newid);
                  } else {
                      $status = false;
                  }
                }
            }
        }

        return $status;
    }

    /**
     * question_restore_map_answers
     *
     * @uses $CFG
     * @uses $DB
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_restore_map_answers($old_question_id,$new_question_id,$info,$restore) {

        global $CFG, $DB;

        $status = true;

        if (! isset($info['#']['ANSWERS'])) {    // No answers in this question (eg random)
            return $status;
        }

        //Get the answers array
        $answers = $info['#']['ANSWERS']['0']['#']['ANSWER'];

        //Iterate over answers
        for($i = 0; $i < sizeof($answers); $i++) {
            $ans_info = $answers[$i];
            //traverse_xmlize($ans_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($ans_info['#']['ID']['0']['#']);

            //Now, build the question_answers record structure
            $answer->question = $new_question_id;
            $answer->answer = backup_todb($ans_info['#']['ANSWER_TEXT']['0']['#']);
            $answer->fraction = backup_todb($ans_info['#']['FRACTION']['0']['#']);
            $answer->feedback = backup_todb($ans_info['#']['FEEDBACK']['0']['#']);

            //If we are in this method is because the question exists in DB, so its
            //answers must exist too.
            //Now, we are going to look for that answer in DB and to create the
            //mappings in reader_backup_ids to use them later where restoring states (user level).

            //Get the answer from DB (by question and answer)
            $db_answer = $DB->get_record_sql('SELECT * FROM {question_answers} WHERE `question` = ? AND `answer` = ?', array($new_question_id, $answer->answer));

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

            if ($db_answer) {
                //We have the database answer, update reader_backup_ids
                backup_putid($restore->backup_unique_code,"question_answers",$oldid,
                             $db_answer->id);
            } else {
                $status = false;
            }
        }

        return $status;
    }

    /**
     * question_restore_numerical_units
     *
     * @uses $CFG
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_restore_numerical_units($old_question_id,$new_question_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the numerical array
        if (! empty($info['#']['NUMERICAL_UNITS'])) {
            $numerical_units = $info['#']['NUMERICAL_UNITS']['0']['#']['NUMERICAL_UNIT'];
        } else {
            $numerical_units = array();
        }

        //Iterate over numerical_units
        for($i = 0; $i < sizeof($numerical_units); $i++) {
            $nu_info = $numerical_units[$i];
            //traverse_xmlize($nu_info);                                                                  //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            // Check to see if this until already exists in the database, which it might, for
            // Historical reasons.
            $unit = backup_todb($nu_info['#']['UNIT']['0']['#']);
            if (! record_exists('question_numerical_units', 'question', $new_question_id, 'unit', $unit)) {

                //Now, build the question_numerical_UNITS record structure.
                $numerical_unit = new stdClass;
                $numerical_unit->question = $new_question_id;
                $numerical_unit->multiplier = backup_todb($nu_info['#']['MULTIPLIER']['0']['#']);
                $numerical_unit->unit = $unit;

                //The structure is equal to the db, so insert the question_numerical_units
                $newid = insert_record("question_numerical_units", $numerical_unit);

                if (! $newid) {
                    $status = false;
                }
            }
        }

        return $status;
    }

    /**
     * question_restore_dataset_definitions
     *
     * @uses $CFG
     * @param xxx $old_question_id
     * @param xxx $new_question_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_restore_dataset_definitions($old_question_id,$new_question_id,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the dataset_definitions array
        $dataset_definitions = $info['#']['DATASET_DEFINITIONS']['0']['#']['DATASET_DEFINITION'];

        //Iterate over dataset_definitions
        for($i = 0; $i < sizeof($dataset_definitions); $i++) {
            $dd_info = $dataset_definitions[$i];
            //traverse_xmlize($dd_info);                                                                  //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the question_dataset_DEFINITION record structure
            $dataset_definition = new stdClass;
            $dataset_definition->category = backup_todb($dd_info['#']['CATEGORY']['0']['#']);
            $dataset_definition->name = backup_todb($dd_info['#']['NAME']['0']['#']);
            $dataset_definition->type = backup_todb($dd_info['#']['TYPE']['0']['#']);
            $dataset_definition->options = backup_todb($dd_info['#']['OPTIONS']['0']['#']);
            $dataset_definition->itemcount = backup_todb($dd_info['#']['ITEMCOUNT']['0']['#']);

            //We have to recode the category field (only if the category != 0)
            if ($dataset_definition->category != 0) {
                $category = backup_getid($restore->backup_unique_code,"question_categories",$dataset_definition->category);
                if ($category) {
                    $dataset_definition->category = $category->new_id;
                } else {
                    echo 'Could not recode category id '.$dataset_definition->category.' for dataset definition'.$dataset_definition->name.'<br />';
                }
            }

            //Now, we hace to decide when to create the new records or reuse an existing one
            $create_definition = false;

            //If the dataset_definition->category = 0, it's a individual question dataset_definition, so we'll create it
            if ($dataset_definition->category == 0) {
                $create_definition = true;
            } else {
                //The category isn't 0, so it's a category question dataset_definition, we have to see if it exists
                //Look for a definition with the same category, name and type
                if ($definitionrec = get_record_sql("SELECT d.*
                                                     FROM {$CFG->prefix}question_dataset_definitions d
                                                     WHERE d.category = '$dataset_definition->category' AND
                                                           d.name = '$dataset_definition->name' AND
                                                           d.type = '$dataset_definition->type'")) {
                    //Such dataset_definition exist. Now we must check if it has enough itemcount
                    if ($definitionrec->itemcount < $dataset_definition->itemcount) {
                        //We haven't enough itemcount, so we have to create the definition as an individual question one.
                        $dataset_definition->category = 0;
                        $create_definition = true;
                    } else {
                        //We have enough itemcount, so we'll reuse the existing definition
                        $create_definition = false;
                        $newid = $definitionrec->id;
                    }
                } else {
                    //Such dataset_definition doesn't exist. We'll create it.
                    $create_definition = true;
                }
            }

            //If we've to create the definition, do it
            if ($create_definition) {
                //The structure is equal to the db, so insert the question_dataset_definitions
                $newid = insert_record ("question_dataset_definitions",$dataset_definition);
                if ($newid) {
                    //Restore question_dataset_items
                    $status = question_restore_dataset_items($newid,$dd_info,$restore);
                }
            }

            //Now, we must have a definition (created o reused). Its id is in newid. Create the question_datasets record
            //to join the question and the dataset_definition
            if ($newid) {
                $question_dataset = new stdClass;
                $question_dataset->question = $new_question_id;
                $question_dataset->datasetdefinition = $newid;
                $newid = insert_record ("question_datasets",$question_dataset);
            }

            if (! $newid) {
                $status = false;
            }
        }

        return $status;
    }

    /**
     * question_restore_dataset_items
     *
     * @uses $CFG
     * @param xxx $definitionid
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_restore_dataset_items($definitionid,$info,$restore) {

        global $CFG;

        $status = true;

        //Get the items array
        $dataset_items = $info['#']['DATASET_ITEMS']['0']['#']['DATASET_ITEM'];

        //Iterate over dataset_items
        for($i = 0; $i < sizeof($dataset_items); $i++) {
            $di_info = $dataset_items[$i];
            //traverse_xmlize($di_info);                                                                  //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the question_dataset_ITEMS record structure
            $dataset_item = new stdClass;
            $dataset_item->definition = $definitionid;
            $dataset_item->itemnumber = backup_todb($di_info['#']['NUMBER']['0']['#']);
            $dataset_item->value = backup_todb($di_info['#']['VALUE']['0']['#']);

            //The structure is equal to the db, so insert the question_dataset_items
            $newid = insert_record ("question_dataset_items",$dataset_item);

            if (! $newid) {
                $status = false;
            }
        }

        return $status;
    }
//This function restores the question_states

    /**
     * question_states_restore_mods
     *
     * @uses $CFG
     * @uses $QTYPES
     * @param xxx $attempt_id
     * @param xxx $info
     * @param xxx $restore
     * @return xxx
     * @todo Finish documenting this function
     */
    function question_states_restore_mods($attempt_id,$info,$restore) {

        global $CFG, $QTYPES;

        $status = true;

        //Get the question_states array
        $states = $info['#']['STATES']['0']['#']['STATE'];
        //Iterate over states
        for($i = 0; $i < sizeof($states); $i++) {
            $res_info = $states[$i];
            //traverse_xmlize($res_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //We'll need this later!!
            $oldid = backup_todb($res_info['#']['ID']['0']['#']);

            //Now, build the STATES record structure
            $state = new stdClass;
            $state->attempt = $attempt_id;
            $state->question = backup_todb($res_info['#']['QUESTION']['0']['#']);
            $state->originalquestion = backup_todb($res_info['#']['ORIGINALQUESTION']['0']['#']);
            $state->seq_number = backup_todb($res_info['#']['SEQ_NUMBER']['0']['#']);
            $state->answer = backup_todb($res_info['#']['ANSWER']['0']['#']);
            $state->timestamp = backup_todb($res_info['#']['TIMESTAMP']['0']['#']);
            $state->event = backup_todb($res_info['#']['EVENT']['0']['#']);
            $state->grade = backup_todb($res_info['#']['GRADE']['0']['#']);
            $state->raw_grade = backup_todb($res_info['#']['RAW_GRADE']['0']['#']);
            $state->penalty = backup_todb($res_info['#']['PENALTY']['0']['#']);
            $state->oldid = $oldid; // So it is available to restore_recode_answer.

            //We have to recode the question field
            $question = backup_getid($restore->backup_unique_code,"question",$state->question);
            if ($question) {
                $state->question = $question->new_id;
            } else {
                echo 'Could not recode question id '.$state->question.' for state '.$oldid.'<br />';
            }

            //We have to recode the originalquestion field if it is nonzero
            if ($state->originalquestion) {
                $question = backup_getid($restore->backup_unique_code,"question",$state->originalquestion);
                if ($question) {
                    $state->originalquestion = $question->new_id;
                } else {
                    echo 'Could not recode originalquestion id '.$state->question.' for state '.$oldid.'<br />';
                }
            }

            //We have to recode the answer field
            //It depends of the question type !!
            //We get the question first
            if (! $question = get_record("question","id",$state->question)) {
                error("Can't find the record for question $state->question for which I am trying to restore a state");
            }
            //Depending on the qtype, we make different recodes
            if ($state->answer) {
                $state->answer = $QTYPES[$question->qtype]->restore_recode_answer($state, $restore);
            }

            //The structure is equal to the db, so insert the question_states
            $newid = insert_record ("question_states",$state);

            //Do some output
            if (($i+1) % 10 == 0) {
                if (! defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 200 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            if ($newid) {
                //We have the newid, update reader_backup_ids
                backup_putid($restore->backup_unique_code, 'question_states', $oldid, $newid);
            } else {
                $status = false;
            }
        }

        //Get the question_sessions array
        $sessions = $info['#']['NEWEST_STATES']['0']['#']['NEWEST_STATE'];
        //Iterate over question_sessions
        for($i = 0; $i < sizeof($sessions); $i++) {
            $res_info = $sessions[$i];
            //traverse_xmlize($res_info);                                                                 //Debug
            //print_object ($GLOBALS['traverse_array']);                                                  //Debug
            //$GLOBALS['traverse_array']="";                                                              //Debug

            //Now, build the NEWEST_STATES record structure
            $session = new stdClass;
            $session->attemptid = $attempt_id;
            $session->questionid = backup_todb($res_info['#']['QUESTIONID']['0']['#']);
            $session->newest = backup_todb($res_info['#']['NEWEST']['0']['#']);
            $session->newgraded = backup_todb($res_info['#']['NEWGRADED']['0']['#']);
            $session->sumpenalty = backup_todb($res_info['#']['SUMPENALTY']['0']['#']);

            if (isset($res_info['#']['MANUALCOMMENT']['0']['#'])) {
                $session->manualcomment = backup_todb($res_info['#']['MANUALCOMMENT']['0']['#']);
            } else { // pre 1.7 backups
                $session->manualcomment = backup_todb($res_info['#']['COMMENT']['0']['#']);
            }

            //We have to recode the question field
            $question = backup_getid($restore->backup_unique_code,"question",$session->questionid);
            if ($question) {
                $session->questionid = $question->new_id;
            } else {
                echo 'Could not recode question id '.$session->questionid.'<br />';
            }

            //We have to recode the newest field
            $state = backup_getid($restore->backup_unique_code,"question_states",$session->newest);
            if ($state) {
                $session->newest = $state->new_id;
            } else {
                echo 'Could not recode newest state id '.$session->newest.'<br />';
            }

            //If the session has been graded we have to recode the newgraded field
            if ($session->newgraded) {
                $state = backup_getid($restore->backup_unique_code,"question_states",$session->newgraded);
                if ($state) {
                    $session->newgraded = $state->new_id;
                } else {
                    echo 'Could not recode newest graded state id '.$session->newgraded.'<br />';
                }
            }

            //The structure is equal to the db, so insert the question_sessions
            $newid = insert_record ("question_sessions",$session);

        }

        return $status;
    }

    /**
     * Recode content links in question texts.
     * @param object $restore the restore metadata object.
     * @return boolean whether the operation succeeded.
     */
    function question_decode_content_links_caller($restore) {
        global $CFG, $QTYPES;
        $status = true;
        $i = 1;   //Counter to send some output to the browser to avoid timeouts

        // Get a list of which question types have custom field that will need decoding.
        $qtypeswithextrafields = array();
        $qtypeswithhtmlanswers = array();
        foreach ($QTYPES as $qtype => $qtypeclass) {
            $qtypeswithextrafields[$qtype] = method_exists($qtypeclass, 'decode_content_links_caller');
            $qtypeswithhtmlanswers[$qtype] = $qtypeclass->has_html_answers();
        }
        $extraprocessing = array();

        $coursemodulecontexts = array();
        $context = reader_get_context(CONTEXT_COURSE, $restore->course_id);
        $coursemodulecontexts[] = $context->id;
        $cms = get_records('course_modules', 'course', $restore->course_id, '', 'id');
        if ($cms){
            foreach ($cms as $cm){
                $context =  reader_get_context(CONTEXT_MODULE, $cm->id);
                $coursemodulecontexts[] = $context->id;
            }
        }
        $coursemodulecontextslist = join($coursemodulecontexts, ',');
        // Decode links in questions.
        if ($questions = get_records_sql('SELECT q.id, q.qtype, q.questiontext, q.generalfeedback '.
                 'FROM ' . $CFG->prefix . 'question q, '.
                 $CFG->prefix . 'question_categories qc '.
                 'WHERE q.category = qc.id '.
                 'AND qc.contextid IN (' .$coursemodulecontextslist.')')) {

            foreach ($questions as $question) {
                $questiontext = restore_decode_content_links_worker($question->questiontext, $restore);
                $generalfeedback = restore_decode_content_links_worker($question->generalfeedback, $restore);
                if ($questiontext != $question->questiontext || $generalfeedback != $question->generalfeedback) {
                    $question->questiontext = addslashes($questiontext);
                    $question->generalfeedback = addslashes($generalfeedback);
                    if (! update_record('question', $question)) {
                        $status = false;
                    }
                }

                // Do some output.
                if (++$i % 5 == 0 && !defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if ($i % 100 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }

                // Decode any questiontype specific fields.
                if ($qtypeswithextrafields[$question->qtype]) {
                    if (! array_key_exists($question->qtype, $extraprocessing)) {
                        $extraprocessing[$question->qtype] = array();
                    }
                    $extraprocessing[$question->qtype][] = $question->id;
                }
            }
        }

        // Decode links in answers.
        if ($answers = get_records_sql('SELECT qa.id, qa.answer, qa.feedback, q.qtype
               FROM ' . $CFG->prefix . 'question_answers qa,
                    ' . $CFG->prefix . 'question q,
                    ' . $CFG->prefix . 'question_categories qc
               WHERE qa.question = q.id
                 AND q.category = qc.id '.
                 'AND qc.contextid IN ('.$coursemodulecontextslist.')')) {

            foreach ($answers as $answer) {
                $feedback = restore_decode_content_links_worker($answer->feedback, $restore);
                if ($qtypeswithhtmlanswers[$answer->qtype]) {
                    $answertext = restore_decode_content_links_worker($answer->answer, $restore);
                } else {
                    $answertext = $answer->answer;
                }
                if ($feedback != $answer->feedback || $answertext != $answer->answer) {
                    unset($answer->qtype);
                    $answer->feedback = addslashes($feedback);
                    $answer->answer = addslashes($answertext);
                    if (! update_record('question_answers', $answer)) {
                        $status = false;
                    }
                }

                // Do some output.
                if (++$i % 5 == 0 && !defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if ($i % 100 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }
            }
        }

        // Do extra work for certain question types.
        foreach ($extraprocessing as $qtype => $questionids) {
            if (! $QTYPES[$qtype]->decode_content_links_caller($questionids, $restore, $i)) {
                $status = false;
            }
        }

        return $status;
    }
