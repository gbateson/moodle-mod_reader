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
 * mod/reader/admin/books/download/progress.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/**
 * reader_download_progress_task
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_progress_task {
    /** the name of this task */
    public $name = '';

    /** the percentage to which this task is complete */
    public $percent = 0;

    /** the weighting of this task toward its parent task */
    public $weighting = 0;

    /** the total weighting of the child tasks */
    public $childweighting = 0;

    /** the parent task object */
    public $parenttask = null;

    /** an array of child tasks */
    public $tasks = array();

    /**
     * __construct
     *
     * @param xxx $name (optional, default="")
     * @param xxx $weighting (optional, default=100)
     * @param xxx $tasks (optional, default=array())
     * @return xxx
     * @todo Finish documenting this function
     */
    public function __construct($name='', $weighting=100, $tasks=array()) {
        $this->name = $name;
        $this->weighting = $weighting;
        $this->add_tasks($tasks);
    }

    /**
     * add_tasks
     *
     * @param xxx $tasks
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_tasks($tasks) {
        foreach ($tasks as $taskid => $task) {
            $this->add_task($taskid, $task);
        }
    }

    /**
     * add_task
     *
     * @param xxx $taskid
     * @param xxx $task
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_task($taskid, $task) {
        if (is_string($task)) {
            $taskid = $task;
            $task = new reader_download_progress_task();
        }
        $task->set_parenttask($this);
        $this->tasks[$taskid] = $task;
        $this->childweighting += $task->weighting;
    }

    /**
     * get_task
     *
     * @param xxx $taskid
     * @param xxx $task
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_task($taskid) {
        if (empty($this->tasks[$taskid])) {
            return false; // shouldn't happen !!
        }
        return $this->tasks[$taskid];
    }

    /**
     * set_parenttask
     *
     * @param xxx $parenttask (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_parenttask($parenttask) {
        $this->parenttask = $parenttask;
    }

    /**
     * set_title
     *
     * @param string $title
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_title($title='') {
        if ($this->parenttask && $title) {
            $this->parenttask->set_title($title);
        }
    }

    /**
     * finish
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish($title='') {
        $this->set_percent(100, $title);
    }

    /**
     * set_percent
     *
     * @param integer $percent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_percent($percent, $title='') {
        $this->percent = $percent;
        if ($this->parenttask) {
            $this->parenttask->checktasks($title);
        }
    }

    /**
     * checktasks
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function checktasks($title='') {
        if ($this->childweighting) {
            $childweighting = 0;
            foreach ($this->tasks as $task) {
                $childweighting += ($task->weighting * ($task->percent / 100));
            }
            $percent = round(100 * ($childweighting / $this->childweighting));
        } else {
            $percent = 0;
        }
        $this->set_percent($percent, $title);
    }
}

/**
 * reader_download_progress_bar
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_progress_bar extends reader_download_progress_task {

    /** a Moodle progress bar to display the progress of the download */
    private $bar = null;

    /** the title displayed in the progress bar */
    private $title = null;

    /** the time after which more processing time will be requested */
    private $timeout = 0;

    /** object to store current ids */
    public $current = null;

    /**
     * __construct
     *
     * @param xxx $name
     * @param xxx $weighting
     * @param xxx $tasks (optional, default=array())
     * @return xxx
     * @todo Finish documenting this function
     */
    public function __construct($name='', $weighting=100, $tasks=array()) {
        parent::__construct($name, $weighting, $tasks);
        self::allow_html_in_title();
        $this->bar = new progress_bar($name, 500, true);
        $this->title = get_string($this->name, 'mod_reader');
        $this->start_current();
        $this->reset_timeout();
    }

    /**
     * allow_html_in_title
     *
     * @return void, but will send content to browser
     * @todo Finish documenting this function
     */
    static function allow_html_in_title() {
        global $CFG;
        if (floatval($CFG->release) >= 2.8) {
            echo '<script type="text/javascript">'."\n";
            echo "//<![CDATA[\n";
            echo "if (window.updateProgressBar) {\n";
            echo "    var r = new RegExp('Y\\\\.Escape.html\\\\(([^)]*)\\\\)', 'g');\n";
            echo "    var s = window.updateProgressBar.toString();\n";
            echo "    eval(s.replace(r, '".'$1'."'));\n";
            echo "    s = null;\n";
            echo "    r = null;\n";
            echo "}\n";
            echo "//]]>\n";
            echo "</script>\n";
        }
    }

    /**
     * create
     *
     * @param array $itemids
     * @param string $name
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create($itemids, $name, $weighting=100) {
        $tasks = array();
        $tasks['items'] = self::create_items($itemids);
        return new reader_download_progress_bar($name, $weighting, $tasks);
    }

    /**
     * create_items
     *
     * @param array $items
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_items($items, $weighting=100) {
        $tasks = array();
        foreach ($items as $item) {
            $taskid = (is_object($item) ? $item->id : $item);
            $tasks[$taskid] = self::create_item($item);
        }
        return new reader_download_progress_task('items', $weighting, $tasks);
    }

    /**
     * create_item
     *
     * @param xxx $item
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_item($item, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 20);
        if (isset($item->quiz)) {
            $tasks['quiz'] = self::create_quiz($item->quiz, 80);
        }
        return new reader_download_progress_task('item', $weighting, $tasks);
    }

    /**
     * create_quiz
     *
     * @param xxx $quiz
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_quiz($quiz, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 10);
        if (isset($quiz->categories)) {
            $tasks['categories'] = self::create_categories($quiz->categories, 80);
        }
        if (isset($quiz->instances)) {
            $tasks['instances'] = self::create_instances($quiz->instances, 10);
        }
        return new reader_download_progress_task('quiz', $weighting, $tasks);
    }

    /**
     * create_instances
     *
     * @param array $instances
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_instances($instances, $weighting=100) {
        $tasks = array();
        foreach ($instances as $instance) {
            $taskid = (is_object($instance) ? $instance->id : $instance);
            $tasks[$taskid] = new reader_download_progress_task('instance');
        }
        return new reader_download_progress_task('instances', $weighting, $tasks);
    }

    /**
     * create_categories
     *
     * @param array $categories
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_categories($categories, $weighting=100) {
        $tasks = array();
        foreach ($categories as $category) {
            $taskid = (is_object($category) ? $category->id : $category);
            $tasks[$taskid] = self::create_category($category);
        }
        return new reader_download_progress_task('categories', $weighting, $tasks);
    }

    /**
     * create_category
     *
     * @param xxx $category
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_category($category, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 20);
        if (isset($category->questions)) {
            $tasks['questions'] = self::create_questions($category->questions, 80);
        }
        return new reader_download_progress_task('category', $weighting, $tasks);
    }

    /**
     * create_questions
     *
     * @param array $questions
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_questions($questions, $weighting=100) {
        $tasks = array();
        foreach ($questions as $question) {
            $taskid = (is_object($question) ? $question->id : $question);
            $tasks[$taskid] = self::create_question($question);
        }
        return new reader_download_progress_task('questions', $weighting, $tasks);
    }

    /**
     * create_question
     *
     * @param xxx $question
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_question($question, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 10);
        $tasks['options'] = new reader_download_progress_task('options', 10);
        if (isset($question->answers)) {
            $tasks['answers'] = self::create_answers($question->answers, 80);
        }
        return new reader_download_progress_task('question', $weighting, $tasks);
    }

    /**
     * create_answers
     *
     * @param array $answers
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_answers($answers, $weighting=100) {
        $tasks = array();
        foreach ($answers as $answer) {
            $taskid = (is_object($answer) ? $answer->id : $answer);
            $tasks[$taskid] = new reader_download_progress_task('answer');
        }
        return new reader_download_progress_task('answers', $weighting, $tasks);
    }

    /**
     * set_percent
     *
     * @param integer $percent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_percent($percent, $title='') {
        parent::set_percent($percent);
        $this->set_title($title);
    }

    /**
     * start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_title($title='') {
        if ($title) {
            $this->title = $title;
        }
        $this->update();
    }

    /**
     * update
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function update() {
        $this->reset_timeout(); // request more time
        $this->bar->update($this->percent, 100, $this->title);
    }

    /**
     * reset_timeout
     *
     * @param integer $timeout (optional, default=300)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function reset_timeout($moretime=300) {
        $time = time();
        if ($this->timeout < $time && $this->percent < 100) {
            $this->timeout = ($time + $moretime);
            set_time_limit($moretime);
        }
    }

    /**
     * start_current
     *
     * @param string  $type  (optional, default='')
     * @param integer $id    (optional, default=0)
     * @param string  $title (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_current($type='', $id=0, $title='') {
        $field = $type.'id';
        if ($type && isset($this->current->$field)) {
            $this->current->$field = $id;
        } else {
            $this->current = new stdClass();
        }

        // setup ids (drop-throughs are intentional)
        switch ($type) {
            case ''        : $this->current->itemid     = 0;
            case 'item'    : $this->current->instanceid = 0;
            case 'instance': $this->current->categoryid = 0;
            case 'category': $this->current->questionid = 0;
            case 'question': $this->current->answerid   = 0;
        }

        $this->set_title($title);
    }

    /**
     * finish_current
     *
     * @param string  $type  (optional, default='')
     * @param string  $title (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_current($type='', $title='') {
        // assemble required ids (drop-throughs are intentional)
        switch ($type) {
            case 'answer'  : $answerid   = $this->current->answerid;
            case 'options' : // drop though
            case 'question': $questionid = $this->current->questionid;
            case 'instance': $instanceid = $this->current->instanceid;
            case 'category': $categoryid = $this->current->categoryid;
            case 'item'    : $itemid     = $this->current->itemid;
        }

        // initiate "finish()" method of appropriate object
        switch ($type) {
            case ''        : $this->finish($title);
                             unset($this->tasks['items']);
                             break;
            case 'item'    : $this->tasks['items']->tasks[$itemid]->finish($title);
                             unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']);
                             break;
            case 'instance': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['instances']->tasks[$instanceid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['instances']->tasks[$instanceid]);
                             break;
            case 'category': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]);
                             break;
            case 'question': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]);
                             break;
            case 'options' : $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['options']->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['options']);
                             break;
            case 'answer'  : $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['answers']->tasks[$answerid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['answers']->tasks[$answerid]);
                             break;
        }
    }

    /**
     * add_quiz
     *
     * @param xxx $categories
     * @param xxx $instances
     * @param integer $weighting (optional, default=80)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_quiz($categories, $instances, $weighting=80) {
        $itemid = $this->current->itemid;
        $quiz = (object)array('categories' => $categories, 'instances' => $instances);
        $this->tasks['items']->tasks[$itemid]->add_task('quiz', self::create_quiz($quiz, $weighting));
    }

    /**
     * start_item
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_item($id, $title='') {
        $this->start_current('item', $id, $title);
    }

    /**
     * start_instance
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_instance($id, $title='') {
        $this->start_current('instance', $id, $title);
    }

    /**
     * start_category
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_category($id, $title='') {
        $this->start_current('category', $id, $title);
    }

    /**
     * start_question
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_question($id, $title='') {
        $this->start_current('question', $id, $title);
    }

    /**
     * start_answer
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_answer($id, $title='') {
        $this->start_current('answer', $id, $title);
    }

    /**
     * finish_item
     *
     * @param xxx $itemid
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_item($title='') {
        $this->finish_current('item', $title);
    }

    /**
     * finish_instances
     *
     * @param xxx $itemid
     * @param xxx $instanceid
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_instance($title='') {
        $this->finish_current('instance', $title);
    }

    /**
     * finish_category
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_category($title='') {
        $this->finish_current('category', $title);
    }

    /**
     * finish_question
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_question($title='') {
        $this->finish_current('question', $title);
    }

    /**
     * finish_options
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_options($title='') {
        $this->finish_current('options', $title);
    }

    /**
     * finish_answer
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_answer($title='') {
        $this->finish_current('answer', $title);
    }
}
