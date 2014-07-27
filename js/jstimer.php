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

// QuizTimer main routines.
// This will produce a floating timer that counts
// how much time is left to answer the quiz.
//

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;
?>

<script type="text/javascript">
//<![CDATA[
var timesup = "<?php print_string('timesup', 'reader'); ?>";
var quizclose = <?php echo ($cm->availableuntil - time()) - $timerstartvalue; ?>; // in seconds
var quizTimerValue = <?php echo $timerstartvalue; ?>; // in seconds
parseInt(quizTimerValue);

// @EC PF : client time when page was opened
var ec_page_start = new Date().getTime();
// @EC PF : client time when quiz should end
var ec_quiz_finish = ec_page_start + <?php echo ($timerstartvalue * 1000); ?>;

//]]>
</script>
<div id="timer">
<!--EDIT BELOW CODE TO YOUR OWN MENU-->
<table class="generalbox" border="0" cellpadding="0" cellspacing="0" style="width:150px;">
<tr>
    <td class="generalboxcontent" bgcolor="#ffffff" width="100%">
    <table class="generaltable" border="0" width="150" cellspacing="0" cellpadding="0">
    <tr>
        <th class="generaltableheader" width="100%" scope="col"><?php print_string('timeleft', 'reader'); ?></th>
    </tr>
    <tr>
        <td id="QuizTimer" class="generaltablecell" align="center" width="100%">
        <form id="clock"><div><input onfocus="blur()" type="text" id="time"
        style="background-color: transparent; border: none; width: 70%; font-family: sans-serif; font-size: 14pt; font-weight: bold; text-align: center;" />
        </div>
        </form>
        </td>
    </tr>
    </table>
    </td>
</tr>
</table>
<!--END OF EDIT-->
</div>
<script type="text/javascript">
//<![CDATA[

var timerbox = document.getElementById('timer');
var theTimer = document.getElementById('QuizTimer');
var theTop = 100;
var old = theTop;

movecounter(timerbox);

document.onload = countdown_clock(theTimer);
//]]>
</script>
