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
 * mod/reader/quiz/accessrules.php
 * Classes to enforce the various access rules that can apply to a quiz.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/**
 * reader_access_manager
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_access_manager {
    private $_readerobj;
    private $_timenow;
    private $_passwordrule = null;
    private $_securewindowrule = null;
    private $_safebrowserrule = null;
    private $_rules = array();

    /**
     * Create an instance for a particular quiz.
     * @param object $quizobj An instance of the class quiz from quiz/attemptlib.php.
     *      The quiz we will be controlling access to.
     * @param int $timenow The time to use as 'now'.
     * @param bool $canignoretimelimits Whether this user is exempt from time
     *      limits (has_capability('mod/quiz:ignoretimelimits', ...)).
     */
    public function __construct($quizobj, $timenow, $canignoretimelimits) {
        $this->_readerobj = $quizobj;
        $this->_timenow = $timenow;
        $this->create_standard_rules($canignoretimelimits);
    }

    /**
     * create_standard_rules
     *
     * @param xxx $canignoretimelimits
     * @todo Finish documenting this function
     */
    private function create_standard_rules($canignoretimelimits) {
        $quiz = $this->_readerobj->get_reader();
        if ($quiz->attempts > 0) {
            $this->_rules[] = new num_attempts_access_rule($this->_readerobj, $this->_timenow);
        }
        $this->_rules[] = new open_close_date_access_rule1($this->_readerobj, $this->_timenow);
        if (! empty($quiz->timelimit) && !$canignoretimelimits) {
            $this->_rules[] = new time_limit_access_rule($this->_readerobj, $this->_timenow);
        }
        if (! empty($quiz->delay1) || !empty($quiz->delay2)) {
            $this->_rules[] = new inter_attempt_delay_access_rule($this->_readerobj, $this->_timenow);
        }
        if (! empty($quiz->subnet)) {
            $this->_rules[] = new ipaddress_access_rule($this->_readerobj, $this->_timenow);
        }
        if (! empty($quiz->password)) {
            $this->_passwordrule = new password_access_rule($this->_readerobj, $this->_timenow);
            $this->_rules[] = $this->_passwordrule;
        }
        if (! empty($quiz->popup)) {
            if ($quiz->popup == 1) {
                $this->_securewindowrule = new securewindow_access_rule(
                        $this->_readerobj, $this->_timenow);
                $this->_rules[] = $this->_securewindowrule;
            } else if ($quiz->popup == 2) {
                $this->_safebrowserrule = new safebrowser_access_rule(
                        $this->_readerobj, $this->_timenow);
                $this->_rules[] = $this->_safebrowserrule;
            }
        }
    }

    /**
     * accumulate_messages
     *
     * @param xxx $messages (passed by reference)
     * @param xxx $new
     * @todo Finish documenting this function
     */
    private function accumulate_messages(&$messages, $new) {
        if (is_array($new)) {
            $messages = array_merge($messages, $new);
        } else if (is_string($new) && $new) {
            $messages[] = $new;
        }
    }

    /**
     * Provide a description of the rules that apply to this quiz, such
     * as is shown at the top of the quiz view page. Note that not all
     * rules consider themselves important enough to output a description.
     *
     * @return array an array of description messages which may be empty. It
     *         would be sensible to output each one surrounded by &lt;p> tags.
     */
    public function describe_rules() {
        $result = array();
        foreach ($this->_rules as $rule) {
            $this->accumulate_messages($result, $rule->description());
        }
        return $result;
    }

    /**
     * Is it OK to let the current user start a new attempt now? If there are
     * any restrictions in force now, return an array of reasons why access
     * should be blocked. If access is OK, return false.
     *
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object|false $lastattempt information about the user's last completed attempt.
     *      if there is not a previous attempt, the false is passed.
     * @return mixed An array of reason why access is not allowed, or an empty array
     *         (== false) if access should be allowed.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        $reasons = array();
        foreach ($this->_rules as $rule) {
            $this->accumulate_messages($reasons,
                    $rule->prevent_new_attempt($numprevattempts, $lastattempt));
        }
        return $reasons;
    }

    /**
     * Is it OK to let the current user start a new attempt now? If there are
     * any restrictions in force now, return an array of reasons why access
     * should be blocked. If access is OK, return false.
     *
     * @return mixed An array of reason why access is not allowed, or an empty array
     *         (== false) if access should be allowed.
     */
    public function prevent_access() {
        $reasons = array();
        foreach ($this->_rules as $rule) {
            $this->accumulate_messages($reasons, $rule->prevent_access());
        }
        return $reasons;
    }

    /**
     * Do any of the rules mean that this student will no be allowed any further attempts at this
     * quiz. Used, for example, to change the label by the grade displayed on the view page from
     * 'your current grade is' to 'your final grade is'.
     *
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object $lastattempt information about the user's last completed attempt.
     * @return bool true if there is no way the user will ever be allowed to attempt
     *      this quiz again.
     */
    public function is_finished($numprevattempts, $lastattempt) {
        foreach ($this->_rules as $rule) {
            if ($rule->is_finished($numprevattempts, $lastattempt)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Do the printheader call, etc. required for a secure page, including the necessary JS.
     *
     * @param string $title HTML title tag content, passed to printheader.
     * @param string $headtags extra stuff to go in the HTML head tag, passed to printheader.
     */
    public function setup_secure_page($title, $headtags = null) {
        $this->_securewindowrule->setup_secure_page($title, $headtags);
    }

    /**
     * show_attempt_timer_if_needed
     *
     * @uses $PAGE
     * @param xxx $attempt
     * @param xxx $timenow
     * @todo Finish documenting this function
     */
    public function show_attempt_timer_if_needed($attempt, $timenow) {
        global $PAGE;
        $timeleft = false;
        //print_r ($attempt);
        foreach ($this->_rules as $rule) {
            $ruletimeleft = $rule->time_left($attempt, $timenow);
            //echo $ruletimeleft."!!";
            if ($ruletimeleft !== false && ($timeleft === false || $ruletimeleft < $timeleft)) {
                $timeleft = $ruletimeleft;
            }
        }
        //$timeleft = time()+260;
        if ($timeleft !== false) {
            // Make sure the timer starts just above zero. If $timeleft was <= 0, then
            // this will just have the effect of causing the quiz to be submitted immediately.
            $timerstartvalue = max($timeleft, 1);
            $PAGE->requires->js_init_call('M.mod_quiz.timer.init',
                    array($timerstartvalue), false, reader_get_js_module());
        }
    }

    /**
     * @return bolean if this quiz should only be shown to students in a secure window.
     */
    public function securewindow_required($canpreview) {
        return !$canpreview && !is_null($this->_securewindowrule);
    }

    /**
     * @return bolean if this quiz should only be shown to students with safe browser.
     */
    public function safebrowser_required($canpreview) {
        return !$canpreview && !is_null($this->_safebrowserrule);
    }

    /**
     * Print a button to start a quiz attempt, with an appropriate javascript warning,
     * depending on the access restrictions. The link will pop up a 'secure' window, if
     * necessary.
     *
     * @param bool $canpreview whether this user can preview. This affects whether they must
     * use a secure window.
     * @param string $buttontext the label to put on the button.
     * @param bool $unfinished whether the button is to continue an existing attempt,
     * or start a new one. This affects whether a javascript alert is shown.
     */
    //TODO: Add this function to renderer
    public function print_start_attempt_button($canpreview, $buttontext, $unfinished) {
        global $OUTPUT;

        $url = $this->_readerobj->start_attempt_url();
        $button = new single_button($url, $buttontext);
        $button->class .= ' quizstartbuttondiv';

        if (! $unfinished) {
            $strconfirmstartattempt = $this->confirm_start_attempt_message();
            if ($strconfirmstartattempt) {
                $button->add_action(new confirm_action($strconfirmstartattempt, null,
                        get_string('startattempt', 'quiz')));
            }
        }

        $warning = '';

        if ($this->securewindow_required($canpreview)) {
            $button->class .= ' quizsecuremoderequired';

            $button->add_action(new popup_action('click', $url, 'quizpopup',
                    securewindow_access_rule::$popupoptions));

            $warning = html_writer::tag('noscript',
                    $OUTPUT->heading(get_string('noscript', 'quiz')));
        }

        return $OUTPUT->render($button) . $warning;
    }

    /**
     * Send the user back to the quiz view page. Normally this is just a redirect, but
     * If we were in a secure window, we close this window, and reload the view window we came from.
     *
     * @param bool $canpreview This affects whether we have to worry about secure window stuff.
     */
    public function back_to_view_page($canpreview, $message = '') {
        global $CFG, $OUTPUT, $PAGE;
        $url = $this->_readerobj->view_url();
        if ($this->securewindow_required($canpreview)) {
            $PAGE->set_pagelayout('popup');
            echo $OUTPUT->header();
            echo $OUTPUT->box_start();
            if ($message) {
                echo '<p>' . $message . '</p><p>' . get_string('windowclosing', 'quiz') . '</p>';
                $delay = 5;
            } else {
                echo '<p>' . get_string('pleaseclose', 'quiz') . '</p>';
                $delay = 0;
            }
            $PAGE->requires->js_function_call('M.mod_quiz.secure_window.close',
                    array($url, $delay));
            echo $OUTPUT->box_end();
            echo $OUTPUT->footer();
            die;
        } else {
            redirect($url, $message);
        }
    }

    /**
     * Print a control to finish the review. Normally this is just a link, but if we are
     * in a secure window, it needs to be a button that does M.mod_quiz.secure_window.close.
     *
     * @param bool $canpreview This affects whether we have to worry about secure window stuff.
     */
    public function print_finish_review_link($canpreview, $return = false) {
        global $CFG;
        $output = '';
        $url = $this->_readerobj->view_url();
        $output .= '<div class="finishreview">';
        if ($this->securewindow_required($canpreview)) {
            $url = addslashes_js(htmlspecialchars($url));
            $output .= '<input type="button" value="' . get_string('finishreview', 'quiz') . '" ' .
                    "onclick=\"M.mod_quiz.secure_window.close('$url', 0)\" />\n";
        } else {
            $output .= '<a href="' . $url . '">' . get_string('finishreview', 'quiz') . "</a>\n";
        }
        $output .= "</div>\n";
        if ($return) {
            return $output;
        } else {
            echo $output;
        }
    }

    /**
     * @return bolean if this quiz is password public.
     */
    public function password_required() {
        return !is_null($this->_passwordrule);
    }

    /**
     * Clear the flag in the session that says that the current user is allowed to do this quiz.
     */
    public function clear_password_access() {
        if (! is_null($this->_passwordrule)) {
            $this->_passwordrule->clear_access_allowed();
        }
    }

    /**
     * Actually ask the user for the password, if they have not already given it this session.
     * This function only returns is access is OK.
     *
     * @param bool $canpreview used to enfore securewindow stuff.
     */
    public function do_password_check($canpreview) {
        if (! is_null($this->_passwordrule)) {
            $this->_passwordrule->do_password_check($canpreview, $this);
        }
    }

    /**
     * @return string if the quiz policies merit it, return a warning string to be displayed
     * in a javascript alert on the start attempt button.
     */
    public function confirm_start_attempt_message() {
        $quiz = $this->_readerobj->get_reader();
        if ($quiz->timelimit && $quiz->attempts) {
            return get_string('confirmstartattempttimelimit', 'quiz', $quiz->attempts);
        } else if ($quiz->timelimit) {
            return get_string('confirmstarttimelimit', 'quiz');
        } else if ($quiz->attempts) {
            return get_string('confirmstartattemptlimit', 'quiz', $quiz->attempts);
        }
        return '';
    }

    /**
     * Make some text into a link to review the quiz, if that is appropriate.
     *
     * @param string $linktext some text.
     * @param object $attempt the attempt object
     * @return string some HTML, the $linktext either unmodified or wrapped in a
     *      link to the review page.
     */
    public function make_review_link($attempt, $canpreview, $reviewoptions) {
        global $CFG;

        // If review of responses is not allowed, or the attempt is still open, don't link.
        if (! $attempt->timefinish) {
            return '';
        }

        $when = quiz_attempt_state($this->_readerobj->get_reader(), $attempt);
        $reviewoptions = mod_reader_display_options::make_from_quiz(
                $this->_readerobj->get_reader(), $when);

        if (! $reviewoptions->attempt) {
            $message = $this->cannot_review_message($when, true);
            if ($message) {
                return '<span class="noreviewmessage">' . $message . '</span>';
            } else {
                return '';
            }
        }

        $linktext = get_string('review', 'quiz');

        // It is OK to link, does it need to be in a secure window?
        if ($this->securewindow_required($canpreview)) {
            return $this->_securewindowrule->make_review_link($linktext, $attempt->id);
        } else {
            return '<a href="' . $this->_readerobj->review_url($attempt->id) . '" title="' .
                    get_string('reviewthisattempt', 'quiz') . '">' . $linktext . '</a>';
        }
    }

    /**
     * If $reviewoptions->attempt is false, meaning that students can't review this
     * attempt at the moment, return an appropriate string explaining why.
     *
     * @param int $when One of the mod_reader_display_options::DURING,
     *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
     * @param bool $short if true, return a shorter string.
     * @return string an appropraite message.
     */
    public function cannot_review_message($when, $short = false) {
        $quiz = $this->_readerobj->get_reader();
        if ($short) {
            $langstrsuffix = 'short';
            $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        } else {
            $langstrsuffix = '';
            $dateformat = '';
        }

        if ($when==mod_reader_display_options::DURING) {
            return '';
        }
        if ($when==mod_reader_display_options::IMMEDIATELY_AFTER) {
            return '';
        }
        if ($when==mod_reader_display_options::LATER_WHILE_OPEN) {
            if ($quiz->timeclose && $quiz->reviewattempt & mod_reader_display_options::AFTER_CLOSE) {
                return get_string('noreviewuntil' . $langstrsuffix, 'quiz', userdate($quiz->timeclose, $dateformat));
            }
        }
        return get_string('noreview' . $langstrsuffix, 'quiz');
    }
}

/**
 * A base class that defines the interface for the various quiz access rules.
 * Most of the methods are defined in a slightly unnatural way because we either
 * want to say that access is allowed, or explain the reason why it is block.
 * Therefore instead of is_access_allowed(...) we have prevent_access(...) that
 * return false if access is permitted, or a string explanation (which is treated
 * as true) if access should be blocked. Slighly unnatural, but acutally the easist
 * way to implement this.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class reader_access_rule_base {
    public $_quiz;
    public $_readerobj;
    public $_timenow;
    /**
     * Create an instance of this rule for a particular quiz.
     * @param object $quiz the quiz we will be controlling access to.
     */
    public function __construct($quizobj, $timenow) {
        $this->_readerobj = $quizobj;
        $this->_quiz = $quizobj->get_reader();
        $this->_timenow = $timenow;
    }
    /**
     * Whether or not a user should be allowed to start a new attempt at this quiz now.
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object $lastattempt information about the user's last completed attempt.
     * @return string false if access should be allowed, a message explaining the
     *      reason if access should be prevented.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        return false;
    }
    /**
     * Whether or not a user should be allowed to start a new attempt at this quiz now.
     * @return string false if access should be allowed, a message explaining the
     *      reason if access should be prevented.
     */
    public function prevent_access() {
        return false;
    }
    /**
     * Information, such as might be shown on the quiz view page, relating to this restriction.
     * There is no obligation to return anything. If it is not appropriate to tell students
     * about this rule, then just return ''.
     * @return mixed a message, or array of messages, explaining the restriction
     *         (may be '' if no message is appropriate).
     */
    public function description() {
        return '';
    }
    /**
     * If this rule can determine that this user will never be allowed another attempt at
     * this quiz, then return true. This is used so we can know whether to display a
     * final grade on the view page. This will only be called if there is not a currently
     * active attempt for this user.
     * @param int $numattempts the number of previous attempts this user has made.
     * @param object $lastattempt information about the user's last completed attempt.
     * @return bool true if this rule means that this user will never be allowed another
     * attempt at this quiz.
     */
    public function is_finished($numprevattempts, $lastattempt) {
        return false;
    }

    /**
     * If, because of this rule, the user has to finish their attempt by a certain time,
     * you should override this method to return the amount of time left in seconds.
     * @param object $attempt the current attempt
     * @param int $timenow the time now. We don't use $this->_timenow, so we can
     * give the user a more accurate indication of how much time is left.
     * @return mixed false if there is no deadline, of the time left in seconds if there is one.
     */
    public function time_left($attempt, $timenow) {
        //echo "7634756435";
        return false;
    }
}



/**
 * A rule enforcing open and close dates.
 *
 * @copyright  2009 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class open_close_date_access_rule1 extends reader_access_rule_base {

    /**
     * description
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function description() {
        $result = array();
        if ($this->_timenow < $this->_quiz->timeopen) {
            $result[] = get_string('quiznotavailable', 'quiz', userdate($this->_quiz->timeopen));
        } else if ($this->_quiz->timeclose && $this->_timenow > $this->_quiz->timeclose) {
            $result[] = get_string('quizclosed', 'quiz', userdate($this->_quiz->timeclose));
        } else {
            if ($this->_quiz->timeopen) {
                $result[] = get_string('quizopenedon', 'quiz', userdate($this->_quiz->timeopen));
            }
            if ($this->_quiz->timeclose) {
                $result[] = get_string('quizcloseson', 'quiz', userdate($this->_quiz->timeclose));
            }
        }
        return $result;
    }

    /**
     * prevent_access
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function prevent_access() {
        if ($this->_timenow < $this->_quiz->timeopen || ($this->_quiz->timeclose && $this->_timenow > $this->_quiz->timeclose)) {
            return get_string('notavailable', 'quiz');
        }
        return false;
    }

    /**
     * is_finished
     *
     * @param xxx $numprevattempts
     * @param xxx $lastattempt
     * @return xxx
     * @todo Finish documenting this function
     */
    public function is_finished($numprevattempts, $lastattempt) {
        return $this->_quiz->timeclose && $this->_timenow > $this->_quiz->timeclose;
    }

    /**
     * time_left
     *
     * @param xxx $attempt
     * @param xxx $timenow
     * @return xxx
     * @todo Finish documenting this function
     */
    public function time_left($attempt, $timenow) {
        // If this is a teacher preview after the close date, do not show
        // the time.
        //print_r ($this->_quiz);
        //$this->_quiz->timeclose = $this->_quiz->timelimit * 60;

        //echo "[".$this->_quiz->timeclose."/".$timenow."]";

        if ($attempt->preview && $timenow > $this->_quiz->timeclose) {
            return false;
        }

        // Otherwise, return to the time left until the close date, providing
        // that is less than QUIZ_SHOW_TIME_BEFORE_DEADLINE
        if ($this->_quiz->timeclose) {
            $timeleft = $this->_quiz->timeclose - $timenow;
            if ($timeleft < QUIZ_SHOW_TIME_BEFORE_DEADLINE) {
                return $timeleft;
            }
        }
        return false;
    }
}
