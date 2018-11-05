<?php
// essential strings
$string['modulename'] = 'Reader';
$string['modulename_help'] = 'The Reader module allows teachers to set reading goals for students, and allows students to demonstrate they have achieved the specified reading goals.';
$string['modulename_link'] = 'mod/reader/view';
$string['modulenameplural'] = 'Reader';
$string['pluginadministration'] = 'Reader Administration';
$string['pluginname'] = 'Reader';

// roles strings
$string['reader:addinstance'] = 'Add a new Reader activity';
$string['reader:manageattempts'] = 'Manage attempts';
$string['reader:managebooks'] = 'Manage books';
$string['reader:manageremotesites'] = 'Manage remote sites';
$string['reader:managetools'] = 'Manage tools';
$string['reader:manageusers'] = 'Manage users';
$string['reader:viewallbooks'] = 'View all books';
$string['reader:viewbooks'] = 'View books';
$string['reader:viewreports'] = 'View reports';
$string['reader:viewversion'] = 'View version';

// config strings
$string['configbookcovers'] = '';
$string['configcheatedmessage'] = '';
$string['configcheckbox'] = '';
$string['configcheckcheating'] = '';
$string['configclearedmessage'] = '';
$string['configgoal'] = '';
$string['configintro'] = 'The values you set here define the default values that are used in the settings form when you create a new Reader activity.

You can also configure which reader settings are considered advanced.';
$string['configkeeplocalbookdifficulty'] = '';
$string['configkeepoldquizzes'] = '';
$string['configlevelcheck'] = '';
$string['configmaxgrade'] = '';
$string['configminpassgrade'] = '';
$string['configmreadersettings'] = 'These settings allow students from this Moodle site to access the quizzes on mReader.org';
$string['configmreadersiteid'] = 'The numeric ID under which this Moodle site is registered on mReader.org';
$string['configmreadersitekey'] = 'The secret key that allows students from this Moodle site to access the quizzes on mReader.org';
$string['configmreaderurl'] = 'The URL on which students can access the quizzes on mReader.org';
$string['confignextlevel'] = '';
$string['confignotifycheating'] = '';
$string['configprevlevel'] = '';
$string['configquestionscores'] = '';
$string['configserverpassword'] = 'The password required to download Moodle Reader quizzes onto this Moodle site.';
$string['configserversettings'] = 'These settings allow a teacher or admin to download Moodle Reader quizzes onto this Moodle site.  
The username and password are those that you use to login to MoodleReader.org.  
The username must have been authorized for downloading by staff at MoodleReader.org.';
$string['configserverurl'] = 'The URL from where you can download Moodle Reader quizzes onto this Moodle site.';
$string['configserverusername'] = 'The username required to download Moodle Reader quizzes onto this Moodle site.';
$string['configshowpercentgrades'] = '';
$string['configshowprogressbar'] = '';
$string['configshowreviewlinks'] = '';
$string['configstoplevel'] = '';
$string['configthislevel'] = '';
$string['configupdate'] = '';
$string['configusecourse'] = '';
$string['configwordsorpoints'] = '';

// event strings
$string['event_attempt_added'] = 'Reader quiz attempt added';
$string['event_attempt_added_description'] = 'The user with id "{$a->userid}" started a quiz attempt for the "reader" activity with course module id "{$a->cmid}"';
$string['event_attempt_added_explanation'] = 'A user has just started an attempt at a Reader quiz';
$string['event_attempt_deleted'] = 'Reader quiz attempt deleted';
$string['event_attempt_deleted_description'] = 'The user with id "{$a->userid}" deleted a quiz attempt for the "reader" activity with course module id "{$a->cmid}"';
$string['event_attempt_deleted_explanation'] = 'A user has just deleted an attempt at a Reader quiz';
$string['event_attempt_edited'] = 'Reader quiz attempt edited';
$string['event_attempt_edited_description'] = 'The user with id "{$a->userid}" edited a quiz attempt for the "reader" activity with course module id "{$a->cmid}"';
$string['event_attempt_edited_explanation'] = 'A user has just edited an attempt at a Reader quiz';
$string['event_attempt_submitted'] = 'Reader quiz attempt submitted';
$string['event_attempt_submitted_description'] = 'The user with id "{$a->userid}" submitted a quiz attempt for the "reader" activity with course module id "{$a->cmid}"';
$string['event_attempt_submitted_explanation'] = 'A user has just submitted an attempt at a Reader quiz';
$string['event_base'] = 'Reader event detected';
$string['event_base_description'] = 'The user with id "{$a->userid}" initiated an event in the "reader" activity with course module id "{$a->cmid}"';
$string['event_base_explanation'] = 'An event was  detected by the Reader module';
$string['event_book_added'] = 'Reader book added';
$string['event_book_added_description'] = 'The user with id "{$a->userid}" added a book for the "reader" activity with course module id "{$a->cmid}"';
$string['event_book_added_explanation'] = 'A user has just added data about a Reader book';
$string['event_book_deleted'] = 'Reader book deleted';
$string['event_book_deleted_description'] = 'The user with id "{$a->userid}" deleted a book for the "reader" activity with course module id "{$a->cmid}"';
$string['event_book_deleted_explanation'] = 'A user has just deleted data about a Reader book';
$string['event_book_edited'] = 'Reader book edited';
$string['event_book_edited_description'] = 'The user with id "{$a->userid}" edited a book for the "reader" activity with course module id "{$a->cmid}"';
$string['event_book_edited_explanation'] = 'A user has just edited data about a Reader book';
$string['event_books_downloaded'] = 'Reader books downloaded';
$string['event_books_downloaded_description'] = 'The user with id "{$a->userid}" downloaded books for the "reader" activity with course module id "{$a->cmid}"';
$string['event_books_downloaded_explanation'] = 'A user has just downloaded data about Reader books';
$string['event_cron_run'] = 'Reader cron run';
$string['event_cron_run_description'] = 'The Reader cron job was run';
$string['event_cron_run_explanation'] = 'The Reader cron has just been run';
$string['event_downloads_viewed'] = 'Reader downloads viewed';
$string['event_downloads_viewed_description'] = 'The user with id "{$a->userid}" viewed data about downloads for the "reader" activity with course module id "{$a->cmid}"';
$string['event_downloads_viewed_explanation'] = 'A user has just viewed data about Reader downloads';
$string['event_message_added'] = 'Reader message added';
$string['event_message_added_description'] = 'The user with id "{$a->userid}" added a message for the "reader" activity with course module id "{$a->cmid}"';
$string['event_message_added_explanation'] = 'A user has just added a message to a Reader activity';
$string['event_message_deleted'] = 'Reader message deleted';
$string['event_message_deleted_description'] = 'The user with id "{$a->userid}" deleted a message for the "reader" activity with course module id "{$a->cmid}"';
$string['event_message_deleted_explanation'] = 'A user has just deleted a message from a Reader activity';
$string['event_message_edited'] = 'Reader message edited';
$string['event_message_edited_description'] = 'The user with id "{$a->userid}" edited a message for the "reader" activity with course module id "{$a->cmid}"';
$string['event_message_edited_explanation'] = 'A user has just edited a message for a Reader activity';
$string['event_quiz_added'] = 'Reader quiz added';
$string['event_quiz_added_description'] = 'The user with id "{$a->userid}" added a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_added_explanation'] = 'A user has just added a quiz to Reader activity';
$string['event_quiz_delay_set'] = 'Reader quiz delay set';
$string['event_quiz_delay_set_description'] = 'The user with id "{$a->userid}" set a quiz delay for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_delay_set_explanation'] = 'A user has just set a delay on a Reader quiz';
$string['event_quiz_deleted'] = 'Reader quiz deleted';
$string['event_quiz_deleted_description'] = 'The user with id "{$a->userid}" deleted a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_deleted_explanation'] = 'A user has just deleted a Reader quiz';
$string['event_quiz_edited'] = 'Reader quiz edited';
$string['event_quiz_edited_description'] = 'The user with id "{$a->userid}" edited a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_edited_explanation'] = 'A user has just edited a Reader quiz';
$string['event_quiz_finished'] = 'Reader quiz finished';
$string['event_quiz_finished_description'] = 'The user with id "{$a->userid}" finished a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_finished_explanation'] = 'A user has just finished a Reader quiz';
$string['event_quiz_selected'] = 'Reader quiz selected';
$string['event_quiz_selected_description'] = 'The user with id "{$a->userid}" selected a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_selected_explanation'] = 'A user has just selected a Reader quiz';
$string['event_quiz_started'] = 'Reader quiz started';
$string['event_quiz_started_description'] = 'The user with id "{$a->userid}" started a quiz for the "reader" activity with course module id "{$a->cmid}"';
$string['event_quiz_started_explanation'] = 'A user has just started a Reader quiz';
$string['event_report_bookdetailed_viewed'] = 'Reader report viewed: Books (detailed)';
$string['event_report_bookdetailed_viewed_description'] = 'The user with id "{$a->userid}" viewed the "Books (detailed)" report for the "reader" activity with course module id "{$a->cmid}"';
$string['event_report_bookdetailed_viewed_explanation'] = 'A user has just viewed a Reader report: Books (detailed)';
$string['event_report_booksummary_viewed'] = 'Reader report viewed: Books (summary)';
$string['event_report_booksummary_viewed_description'] = 'The user with id "{$a->userid}" viewed the "Books (summary)" report for the "reader" activity with course module id "{$a->cmid}"';
$string['event_report_booksummary_viewed_explanation'] = 'A user has just viewed a Reader report: Books (summary)';
$string['event_report_groups_viewed'] = 'Reader report viewed: Groups (summary)';
$string['event_report_groups_viewed_description'] = 'The user with id "{$a->userid}" viewed the "Groups (summary)" report for the "reader" activity with course module id "{$a->cmid}"';
$string['event_report_groups_viewed_explanation'] = 'A user has just viewed a Reader report: Groups (summary)';
$string['event_report_userdetailed_viewed'] = 'Reader report viewed: Users (detailed)';
$string['event_report_userdetailed_viewed_description'] = 'The user with id "{$a->userid}" viewed the "Users (detailed)" report for the "reader" activity with course module id "{$a->cmid}"';
$string['event_report_userdetailed_viewed_explanation'] = 'A user has just viewed a Reader report: Users (detailed)';
$string['event_report_usersummary_viewed'] = 'Reader report viewed: Users (summary)';
$string['event_report_usersummary_viewed_description'] = 'The user with id "{$a->userid}" viewed the "Users (summary)" report for the "reader" activity with course module id "{$a->cmid}"';
$string['event_report_usersummary_viewed_explanation'] = 'A user has just viewed a Reader report: Users (summary)';
$string['event_tool_run'] = 'Reader admin tool run';
$string['event_tool_run_description'] = 'The user with id "{$a->userid}" ran an admin tool for the "reader" activity with course module id "{$a->cmid}"';
$string['event_tool_run_explanation'] = 'A user has just run a Reader tool: {$a}';
$string['event_user_goal_set'] = 'Reader user goal set';
$string['event_user_goal_set_description'] = 'The user with id "{$a->userid}" set a student reading goal for the "reader" activity with course module id "{$a->cmid}"';
$string['event_user_goal_set_explanation'] = 'A user has just set a reading goal on a Reader activity';
$string['event_user_level_set'] = 'Reader user level set';
$string['event_user_level_set_description'] = 'The user with id "{$a->userid}" set a student reading level for the "reader" activity with course module id "{$a->cmid}"';
$string['event_user_level_set_explanation'] = 'A user has just set the reading level for a student on a Reader activity';
$string['event_users_exported'] = 'Reader users exported';
$string['event_users_exported_description'] = 'The user with id "{$a->userid}" exported student data for the "reader" activity with course module id "{$a->cmid}"';
$string['event_users_exported_explanation'] = 'A user has just exported student data from a Reader activity';
$string['event_users_imported'] = 'Reader users imported';
$string['event_users_imported_description'] = 'The user with id "{$a->userid}" imported student data for the "reader" activity with course module id "{$a->cmid}"';
$string['event_users_imported_explanation'] = 'A user has just imported student data to a Reader activity';

// more strings
$string['action'] = 'Action';
$string['actionblockquizzestext'] = 'You will be blocked from taking any more quizzes until your teacher removes the block';
$string['actiondelayquizzestext'] = 'You will be delayed from taking any more quizzes until the waiting time has passed.';
$string['actionemailstudenttext'] = 'An email will be sent to you.';
$string['actionemailteachertext'] = 'An email will be sent to the teacher(s) of this course.';
$string['activemessages'] = 'Active messages';
$string['activityoverview'] = 'Click here to view information about your extensive reading activity';
$string['add'] = 'Add';
$string['add_phpdoc'] = 'Add PHP doc comments';
$string['add_phpdoc_desc'] = 'Add PHP doc comments to php, js and css files.';
$string['addbookinstance'] = 'Add more books to this course';
$string['addmoregoals'] = 'Add {no} more goals';
$string['addmorerates'] = 'Add {no} more rates';
$string['addonemorerate'] = 'Add {no} more rate';
$string['addquiztoreader'] = 'Add course quizzes to reader quizzes';
$string['adjoiningcomputers'] = 'On adjoining computers';
$string['adjustscores'] = 'Adjust scores';
$string['adminarea'] = 'Admin area';
$string['all'] = 'All';
$string['allbooks'] = 'Download/fix covers for ALL books available to the Reader module';
$string['allcourses'] = 'All courses';
$string['alldone'] = 'All done';
$string['allgroups'] = 'All groups';
$string['alllevels'] = 'All levels';
$string['allowpromotion'] = 'Allow promotion';
$string['allparticipants'] = 'All participants';
$string['allterms'] = 'all terms';
$string['anytime'] = 'Any time';
$string['anywhere'] = 'Anywhere';
$string['arrange'] = 'Arrange';
$string['assignpointsbookshavenoquizzes'] = 'Award points for books that have no quizzes';
$string['atlevel'] = ' at Level';
$string['attemptedbooks'] = 'Download/fix only covers for books that have been ATTEMPTED on this site';
$string['attempts'] = 'Attempts';
$string['attemptsallowedn'] = 'Attempts allowed: {$a}';
$string['attemptscoremanagement'] = 'Attempt and score management';
$string['attemptsupdated'] = '{$a} attempts were updated';
$string['atttemptsgroupedbybook'] = 'Attempts grouped by book';
$string['atttemptsgroupedbyuser'] = 'Attempts grouped by user';
$string['available'] = 'Available';
$string['availablefrom'] = 'Available from';
$string['availablefrom_help'] = 'Students can only to access this activity after the date and time specified here. Before this date and time, it will not be available.';
$string['availableitems'] = 'Available items';
$string['availablenolonger'] = 'Sorry, this activity is no longer available. It closed {$a}.';
$string['availablenotyet'] = 'Sorry, this activity is not available yet. It will open {$a}.';
$string['availableuntil'] = 'Available until';
$string['availableuntil_help'] = 'Students can only to access this activity up until the date and time specified here. After this date and time, it will not be available.';
$string['averageduration'] = 'Average duration';
$string['averageduration_help'] = 'The average duration of attempts at Reader quizzes';
$string['averagefailed'] = 'Average failed';
$string['averagefailed_help'] = 'The average number of Reader quizzes failed per student';
$string['averagegrade'] = 'Average grade';
$string['averagegrade_help'] = 'The average grade achieved on Reader quizzes';
$string['averagepassed'] = 'Average passed';
$string['averagepassed_help'] = 'The average number of Reader quizzes passed per student';
$string['averagepoints'] = 'Average points';
$string['averagepointsallterms'] = 'Average points (all terms)';
$string['averagepointsallterms_help'] = 'The average number of points earned by each student in this group considering all points that any of them have ever earned in any Reader activity on this Moodle site';
$string['averagepointsthisterm'] = 'Average points (this term)';
$string['averagepointsthisterm_help'] = 'The average number of points earned by each student in this group considering only points earned in this Reader activity during the current term';
$string['averagerating'] = 'Average rating';
$string['averagetaken'] = 'Average taken';
$string['averagetaken_help'] = 'The average number of Reader quizzes taken per student';
$string['averagewords'] = 'Average words';
$string['averagewordsallterms'] = 'Average words (all terms)';
$string['averagewordsallterms_help'] = 'The average number of words earned by each student in this group considering all words that any of them have ever earned in any Reader activity on this Moodle site';
$string['averagewordsthisterm'] = 'Average words (this term)';
$string['averagewordsthisterm_help'] = 'The average number of words earned by each student in this group considering only words earned in this Reader activity during the current term';
$string['awardbookpoints'] = 'Give credit for books to selected students';
$string['awardextrapoints'] = 'Award extra points to selected students';
$string['awardpointsmanually'] = 'There is no quiz for this book. Instead, please ask your teacher to award the words/points manually.';
$string['best'] = 'Best';
$string['blockquizattempts'] = 'Block further quiz attempts';
$string['book'] = 'Book';
$string['bookadded'] = 'Book added: {$a}';
$string['bookcovers'] = 'Show book covers';
$string['bookcovers_help'] = '**Yes**
: Show the book covers on the main page of this Reader activity.

**No**
: Do NOT show the book covers on the main page of this Reader activity.';
$string['bookdifficulty'] = 'Book difficulty';
$string['bookeditdetails'] = 'Edit book details';
$string['bookhasnoquiz'] = 'This book has no quiz.';
$string['bookinstances'] = 'Use subset of quizzes';
$string['bookinstances_help'] = '**Yes**
: This Reader activity will use only a subset of the quizzes available in the Reader quizzes course. Additionally, custom values for word count and book difficulty may be specified. Note that enabling this option will cause some extra load on your web server.

**No**
: This Reader activity will use all the quizzes in the Reader quizzes course, and will use only the standard Reader book data for word count and reading level';
$string['bookinstancesdisabled'] = 'This Reader activity is not using a subset of quizzes, so it does not require entries in the book_instances table';
$string['booklevelmanagement'] = 'Book & Level Management';
$string['booknotadded'] = 'Book NOT added: {$a}';
$string['booknotfound'] = 'Book not found {$a}';
$string['booknotupdated'] = 'Book NOT updated: {$a}';
$string['bookquiznumber'] = 'Book quiz number';
$string['bookrating'] = 'Book rating';
$string['bookrating0'] = 'I didn\'t like it at all';
$string['bookrating1'] = 'It was so-so';
$string['bookrating2'] = 'It was okay';
$string['bookrating3'] = 'It was great';
$string['bookratingslevel'] = 'Display student book ratings for each book level';
$string['books'] = 'Books';
$string['booksaddbook'] = 'Add new book';
$string['booksallwith'] = 'any book that has been read at least once';
$string['booksavailable'] = '{$a} book(s) available';
$string['booksavailableall'] = 'all available books';
$string['booksavailablewith'] = 'available books that have been read at least one';
$string['booksavailablewithout'] = 'available books that have not been read yet';
$string['bookseditcourse'] = 'Edit books (Course)';
$string['bookseditcourse_help'] = 'On this page you can specify which Reader books are to be made available in this course. Additionally you specify the level and word count for those books. If a level or word count value is not specified here, then the default value for that book on this Moodle site will be used.';
$string['bookseditsite'] = 'Edit books (Site)';
$string['bookseditsite_help'] = 'On this page you can set add, edit, and delete information about Reader books on this Modle site. Note that values set here for any book will be replaced if data for that book is downloaded again from the main Moodle Reader website. Also, the values may be overridden local on individual courses.';
$string['booksreadinpreviousterms'] = 'Books read in previous terms';
$string['booksreadsincedate'] = 'Books read since {$a}';
$string['booksreadsincepromotion'] = 'Books read since your promotion on {$a}';
$string['booksreadthisterm'] = 'Books read this term';
$string['booksviaapi'] = 'Book data (via API)';
$string['bookswithoutquizzes'] = 'Books without quizzes';
$string['bookswithquizzes'] = 'Books with quizzes';
$string['booktitle'] = 'Book title';
$string['booktype'] = 'Books to include';
$string['bookupdated'] = 'Book updated: {$a}';
$string['cannotaccesscourse'] = 'Sorry, you do not have permission to manage activities in the "{$a}" course.';
$string['cannotcreatecourse'] = 'Sorry, the download cannot proceed because you do not have permission to create a new course or edit the current course.';
$string['cannotdownloadata'] = 'Sorry, there was a problem downloading data for the Reader module';
$string['changeallstoplevelto'] = 'Change all maximum levels to ';
$string['changeallto'] = 'Change all to ';
$string['changecurrentlevel'] = 'Change all current levels to ';
$string['changedifficultyfrom'] = 'Change reading levels from';
$string['changelevelfrom'] = 'Change level name from';
$string['changenumberofsectionsinquiz'] = 'Reset number of sections in quiz repository course';
$string['changepointsfrom'] = 'Change points from';
$string['changepublisherfrom'] = 'Change publisher name from';
$string['changequiz'] = 'Change quiz';
$string['changereaderlevel'] = 'Change Reading Level, Length or Word Count';
$string['changestartlevel'] = 'Change all start level to ';
$string['cheated'] = 'Cheated';
$string['cheatedmessage'] = 'Cheated message';
$string['cheatedmessage_help'] = 'This message wil be sent to students who are judged by the Reader module to have cheated.';
$string['cheatedmessagedefault'] = 'We are sorry to say that the MoodleReader program has discovered that you have probably cheated when you took the above quiz. "Cheating" means that you either helped another person to take the quiz or that you received help from someone else to take the quiz. Both people have been marked "cheated".

Sometimes the computer makes mistakes. If you honestly did not receive help and did not help someone else, then please inform your teacher and your points will be restored.

--The MoodleReader Module Manager';
$string['cheatedshort'] = 'C';
$string['cheatsheet'] = 'Cheat sheet';
$string['check_email'] = 'Check email';
$string['check_email_desc'] = 'Send two test emails to the gueststudent user, one via Moodle mail and one via PHP mail.';
$string['checkbox'] = 'Show checkboxes';
$string['checkbox_help'] = '**Yes**
: Show checkboxes on the teacher report pages in the Admin area.

**No**
: Do NOT show checkboxes on the teacher reports pages in the Admin area.

This setting will be removed in the future, when the Admin area has been phased out.';
$string['checkcheating'] = 'Check for cheating';
$string['checkcheating_help'] = 'This setting specifies whether or not IP address should be checked when students attempt Reader quizzes.

**Off**
: IP addresses will not be checked

**Anywhere**
: If two students start the same quiz at a similar time and both pass, they will be judged by the Reader module to have cheated.

**On adjoining computers**
: If two students start the same quiz at a similar time from a similar IP address and both pass, they will be judged by the Reader module to have cheated.';
$string['checkonlythiscourse'] = 'Check only this course';
$string['checksuspiciousactivity'] = 'Check logs for suspicious activity';
$string['chooseaction'] = 'Choose an action and click "Go"';
$string['choosedifficulty'] = 'Please choose reading level';
$string['chooselevel'] = 'Please choose level';
$string['choosepublisher'] = 'Please choose publisher';
$string['clearedmessage'] = 'Cleared message';
$string['clearedmessage_help'] = 'This message is sent to students who were judged by the Reader module to have cheated but where later cleared by the teacher.';
$string['clearedmessagedefault'] = 'We are happy to inform you that your points for the above quiz have been restored. We apologize for the mistake!

--The MoodleReader Module Manager';
$string['clicktocontinue'] = 'Click here to continue';
$string['complete'] = 'Complete';
$string['completequizattempt'] = 'Before you can take any new quizzes, you must finish this quiz for "{$a}". Click on the link below to resume your previous attempt at this quiz.';
$string['completionpass'] = 'Require passing grade';
$string['completiontotalwords'] = 'Require reading total';
$string['completiontotalwords_help'] = 'The reading total a student must achieve within this activity in order for it to be marked complete.';
$string['confirmdeleteattempts'] = 'Do you really want to delete these attempts?';
$string['confirmstartattemptlimit'] = 'Number of attempts allowed:  {$a}. You are about to start a new attempt.  Do you wish to proceed?';
$string['confirmstartattempttimelimit'] = 'This quiz has a time limit and is limited to {$a} attempt(s). You are about to start a new attempt.  Do you wish to proceed?';
$string['confirmstarttimelimit'] = 'The quiz has a time limit. Are you sure that you wish to start?';
$string['countactive'] = 'Active students';
$string['countactive_help'] = 'The number of students who have taken at least one Reader quiz';
$string['countfailed'] = 'Failed quizzes';
$string['countinactive'] = 'Inactive students';
$string['countinactive_help'] = 'Number of students who hove not taken any Reader quizzes';
$string['countpassed'] = 'Passed quizzes';
$string['countrating'] = 'Number of ratings';
$string['courseid'] = 'Course ID';
$string['coursespecificquizselection'] = 'Course-specific quiz selection" to "Yes" in the module set-up screen.';
$string['createcoversets_l'] = 'Create Cover Sets by Level & Publisher';
$string['createcoversets_t'] = 'Create Cover Sets by Publisher &amp; Level';
$string['credit'] = 'Extra credit';
$string['creditshort'] = 'X';
$string['curlerror'] = 'CURL error: {$a}';
$string['current'] = 'Current';
$string['currentcourse'] = 'Current course';
$string['currentlevel'] = 'Current level';
$string['dataallavailable'] = 'data for all {$a} book(s) is available';
$string['dataalldownloaded'] = 'data for all {$a} book(s) has been downloaded';
$string['datasomeavailable'] = 'data for {$a->new} out of {$a->all} book(s) is available';
$string['defaultcategoryname'] = 'Reader Quizzes';
$string['defaultcoursename'] = 'Reader Quizzes';
$string['defaultgoal'] = 'Default goal';
$string['defaultgoals'] = 'Default goals';
$string['defaultquestioncategoryinfo'] = '{$a->category} questions for {$a->quiz}';
$string['defaultrates'] = 'Default rates';
$string['definelogindetails'] = 'Please define login details: {$a}';
$string['delayineffect'] = 'Quiz delay is currently in effect';
$string['delayquizattempts'] = 'Delay further quiz attempts';
$string['delete'] = 'Delete';
$string['deleteallattempts'] = 'Delete all attempts at Reader quizzes';
$string['deleteallattempts_help'] = 'As a rule, you should NOT delete attempts at Reader quizzes.

You should only delete attempts if you are absolutely sure that students in this course will never take Reader quizzes again on this Moodle site ever again.

This is because by deleting attempts at Reader quizzes, you allow students to retake any Reader quizzes they had previously taken. This is NOT how the Reader module is supposed to work. Students should only ever get ONE chance to take a Reader quiz.

Deleting attempts at Reader quizzes will also reset the reading scores for all students to zero, which is probably not what you, or the students, want.';
$string['deleteattempts'] = 'Delete seleted attempts';
$string['deletecategories'] = 'Delete categories';
$string['deletecourses'] = 'Delete courses';
$string['deleted'] = 'Deleted';
$string['deletedshort'] = 'D';
$string['deletegoals'] = 'Delete goals for groups and levels';
$string['deletegoals_help'] = 'The default reading goals for particular groups or reading levels will be deleted.';
$string['deletemessages'] = 'Delete Reader messages';
$string['deletemessages_help'] = 'All messages that appear on the students\' main page for Reader activities in this course will be deleted.';
$string['deleterates'] = 'Delete rates for groups and levels';
$string['deleterates_help'] = 'The rates between Reader quizzes for particular groups or reading levels will be removed.';
$string['detect_cheating'] = 'Detect cheating';
$string['detect_cheating_desc'] = 'Scan the attempt logs and report suspicious activity that might indicate cheating.';
$string['difficulty'] = 'Difficulty';
$string['difficultyshort'] = 'RL';
$string['disallowpromotion'] = 'Do NOT allow promotion';
$string['displayoptions'] = 'Display options';
$string['downloadbooksviaapi'] = 'Download book data (via API)';
$string['downloadbookswithoutquizzes'] = 'Download book data (no quizzes)';
$string['downloadbookswithquizzes'] = 'Download book data and quizzes';
$string['downloadedbooks'] = 'Data for the following books was downloaded:';
$string['downloadexcel'] = 'Download Excel';
$string['downloadextrapoints'] = 'Please download "Extra points" quizzes';
$string['downloadmode'] = 'Mode';
$string['downloadmode_help'] = 'This page can be in the following two modes:

**Normal**
: In normal mode, only books whose data has not already been downloaded, or books whose data has been updated, will be available. Books for which the most recent data has already been downloaded will not be available.

**Repair**
: In repair mode, all books are available for download. If any data has been previously downloaded, it will be overwritten by the newly downloaded data. Use this mode if you want to repair faulty quizzes or incorrect data. Note that even if quizzes are overwritten, data about students attempts at those quizzes will be retained.';
$string['downloads'] = 'Downloads';
$string['downloadsettings'] = 'Download settings';
$string['duration'] = 'Duration';
$string['edit'] = 'Edit';
$string['editquiztoreader'] = 'Delete quizzes';
$string['err_regex_float'] = 'This setting must be a decimal number between 0.0 and 10.0';
$string['err_regex_integer'] = 'This setting must be an integer between 0 and 100,000';
$string['error'] = 'Error: {$a}';
$string['errorsfound'] = 'Errors found';
$string['export'] = 'Export';
$string['export_reader_tables'] = 'Export reader tables';
$string['export_reader_tables_desc'] = 'Export Reader database tables. Note that the exported data contains no course or user names, only ids.';
$string['exportstudentrecords'] = 'Export student records';
$string['extrapoints'] = 'Extra Points';
$string['extrapoints0'] = '0.5 Points';
$string['extrapoints1'] = '1 Point';
$string['extrapoints2'] = '2 Points';
$string['extrapoints3'] = '3 Points';
$string['extrapoints4'] = '4 Points';
$string['extrapoints5'] = '5 Points';
$string['extrapoints6'] = '6 Points';
$string['extrawords'] = '{$a} words';
$string['failed'] = 'Failed';
$string['failedshort'] = 'F';
$string['filename'] = 'File name';
$string['fileuploaded'] = 'File was uploaded';
$string['find_faultyquizzes'] = 'Find faulty quizzes';
$string['find_faultyquizzes_desc'] = 'Find quizzes that have questions with no correct answer, or questions that have become orphaned.';
$string['finishreview'] = 'Finish review';
$string['fix_bookcovers'] = 'Fix book covers';
$string['fix_bookcovers_desc'] = 'Detect books that are missing a book-cover image, and attempt to download the book-cover image.';
$string['fix_bookinstances'] = 'Fix book instances';
$string['fix_bookinstances_desc'] = 'Ensure that all books have a associated record in the reader_book_instances table.';
$string['fix_coursesections'] = 'Fix course sections';
$string['fix_coursesections_desc'] = 'Tidy up the main page of courses containing Reader quizzes.

* order sections by publisher name and level difficulty
* ensure each section contains only books from one publisher
* merge multiple sections for the same publisher
* remove empty sections
* reset number of course sections';
$string['fix_installxml'] = 'Fix db/install.xml';
$string['fix_installxml_desc'] = 'Make XML tags in db/install.xml compatible with Moodle <= 2.5

* add NEXT and PREVIOUS attributes to TABLE, FIELD, KEY and INDEX tags
* add SEQUENCE attributes to FIELD tags with TYPE="int"';
$string['fix_missingquizzes'] = 'Fix missing quizzes';
$string['fix_missingquizzes_desc'] = 'Unify duplicate Reader books and quizzes.

* merge duplicate books
* fix books which share the same quiz
* merge duplicate quizzes
* fix books and attempts for which the quiz is missing';
$string['fix_questioncategories'] = 'Fix question categories';
$string['fix_questioncategories_desc'] = 'Tidy up Reader questions and question categories.

* unset all invalid parent question ids
* delete Reader questions not used in any Reader quizzes
* move Reader course question categories to the appropriate Reader quiz
* remove slashes from category names and descriptions
* standardize names of Ordering categories';
$string['fix_slashesinnames'] = 'Fix slashes in names';
$string['fix_slashesinnames_desc'] = 'Remove any slashes in the names of Reader books and questions categories.';
$string['fix_wrongattempts'] = 'Fix wrong attempts';
$string['fix_wrongattempts_desc'] = 'Detect and fix any attempts at Reader quizzes where the name of the quiz does not match the name of the book in the Reader log';
$string['fixattempts'] = 'Fixing duplicate Reader attempts';
$string['fixcontexts'] = 'Fixing faulty contexts in Quiz question categories';
$string['fixingsumgrades'] = 'Fixing grades on attempts at Reader quizzes ...';
$string['fixinstances'] = 'Checking Reader question instances';
$string['fixmissingquizzes'] = 'Fix missing quizzes';
$string['fixmissingquizzesinfo'] = 'The upgrade has been paused, so that you can decide whether or not you wish to download and install Reader module quizzes that are missing on this Moodle site.

If you select "Yes", the missing quizzes will be downloaded and installed.

If you select "No", any Reader books whose quiz are missing will be marked as having no quiz data.

Note that even if you choose "No", the word counts for each student in Reader activities will not be affected by this operation.

Do you wish to download and install missing Reader module quizzes for books on this Moodle site?';
$string['fixmultichoice'] = 'Fixing Reader multichoice questions';
$string['fixordering'] = 'Updating ordering questions for Reader module';
$string['fixquestiontext'] = 'Fix HTML tags in Reader questions';
$string['fixquizslots'] = 'Fixing faulty question slots in Quiz attempts';
$string['fixslashesinnames'] = 'Remove slashes in book titles';
$string['fixwrongquizid'] = '"{$a->name}" (book id={$a->id}) has unexpected quiz id';
$string['fixwrongquizidinfo'] = 'The upgrade has been paused, so that you can decide which quiz should be associated with this book.

Please review the information below and select the quiz you wish to be associated with this book.';
$string['forcedownload'] = 'Force download';
$string['forcedtimedelay'] = 'Set forced time delay';
$string['forcedtimerate'] = 'Set forced reading rate';
$string['fromthistime'] = 'From this time';
$string['fullreportbybooktitle'] = 'Full Report by Book Title';
$string['fullreportquiztoreader'] = 'Full Report by Student';
$string['genre'] = 'Genre';
$string['getstarted'] = '**Getting started**

* The goal of this activity is to read a lot of books and build up your reading total.
* Your reading total is the total number of words in all the books that you have read.

**Choose a book**

* You should choose books that you can read easily without using a dictionary.
* Also, choose books that you are interested in, because they are more fun to read.

**Take an online quiz**

* After you have read a book, take an online quiz.
* Use the search boxes on this page to find the quiz you want. When you have found your quiz, click the button to start the quiz.
* Each quiz has several questions about the book. The questions are different for each student.
* If you pass the quiz, the number of words in the book will be added to your reading total. If you fail the quiz, your reading total does not change.
* ***You cannot retake quizzes***, so please do the quizzes carefully.';
$string['goal'] = 'Goal';
$string['groupgoals'] = 'Goals for specific groups';
$string['grouprates'] = 'Rates for specific groups';
$string['hidden'] = 'Hidden';
$string['ifimagealreadyexists'] = 'If image already exists in images folder (name)';
$string['ignoredate'] = 'Term start date';
$string['ignoredate_help'] = 'The date of the start of the current term.

Any attempts at Reader quizzes taken before this date will not be included in the report totals for this the current term.

However, attempts in previous terms are not ignored completely. They will be included in the report totals for "All terms".

Also, please note that students can never retake Reader quizzes, including those taken in previous terms, unless the teacher deletes the previous attempt.';
$string['image'] = 'Image';
$string['imageadded'] = 'Image added: {$a}';
$string['import'] = 'Import';
$string['import_reader_tables'] = 'Import reader tables';
$string['import_reader_tables_desc'] = 'Recreate an entire Moodle site from just the Reader database tables. This tool will create courses, users, groups, Reader activies and quizzes.';
$string['importreadertables'] = 'Import Reader tables';
$string['importstudentrecord'] = 'Import student record';
$string['in1000sofwords'] = 'in 1000s of words';
$string['includepublishers'] = 'Search publisher names too?';
$string['incorrect'] = ' - incorrect';
$string['incorrect2'] = ' - Sorry, please obtain the correct password from the publisher(s). Click "Install Quizzes" to download your other selections.';
$string['incorrectbooksreadinpreviousterms'] = 'View failed quizzes';
$string['induration'] = 'in';
$string['install_quizzes'] = 'Install Quizzes';
$string['installedbooks'] = 'Download/fix only covers for books already INSTALLED on this site';
$string['ipaddress'] = 'IP address';
$string['isgreaterthan'] = 'is greater than';
$string['islessthan'] = 'is less than';
$string['itemsdownloaded'] = '{$a} items dowloaded';
$string['keeplocalbookdifficulty'] = 'Keep local book difficulty settings';
$string['keepoldquizzes'] = 'Keep old quizzes';
$string['lastupdatedtime'] = 'The quizzes on this site were last updated on {$a}.  Do you want to update the site now?';
$string['level'] = 'Level';
$string['levelcheck'] = 'Restrict reading level';
$string['levelcheck_help'] = '**Yes**
: Students will only be allowed to take Reader quizzes for books at or near their current reading level. The number of quizzes that students are allowed to take is specified in the settings on this page for "Quizzes at current/previous/next level"

**No**
: Students will always be allowed to take Reader quizzes for books at any reading level.';
$string['levelgoal'] = 'Level {$a} goal';
$string['levelgoals'] = 'Goals for specific levels';
$string['leveli'] = 'Level {$a}';
$string['levelrate'] = 'Level {$a} rate';
$string['levelrates'] = 'Rates for specific levels';
$string['levels'] = 'Levels';
$string['likebook'] = 'How did you like this book?';
$string['logoutrequired'] = 'You cannot continue because you are currently logged in as {$a}.

To continue, please click the "Log out" button below, and then login again as yourself.';
$string['mainpagesettings'] = 'Main page settings';
$string['makebookavailable'] = 'Make books available';
$string['makebookavailableproblem'] = 'Oops, could NOT make book available in this course: {$a}';
$string['makebookavailablesuccess'] = 'Book is now available in this course: {$a}';
$string['makenewquizzesavailable'] = 'Make new quizzes available in this course only';
$string['makenewquizzesavailable2'] = 'Make new quizzes available in all courses on this site [default]';
$string['massrename'] = 'Mass changes';
$string['max'] = 'Less than or equal to';
$string['maxgrade'] = 'Maximum grade';
$string['maxgrade_help'] = 'The maximum grade for this Reader activity.

The number of words read, as a fraction of the reading goal, will be scaled to this maximum grade and passed to the gradebook.

Usually the maximum grade will be 100, but if all students have the same reading goal, it may help students understand their grade if this setting is the same as the reading goal.';
$string['maxquizattemptrate'] = 'Maximum quiz attempt rate';
$string['maxquizattemptrate_help'] = 'The maximum rate at which students may attempt Reader quizzes. If a student tries to attempt more than the specified number of quizzes within the specified duration, then the specified action will be taken.';
$string['maxquizattemptratetext'] = 'Please do not take more than {$a}. If you exceed this rate, the following action will be taken:';
$string['maxquizfailurerate'] = 'Maximum quiz failure rate';
$string['maxquizfailurerate_help'] = 'The maximum rate at which students may fail Reader quizzes. If a student fails more than the specified number of quizzes within the specified duration, then the specified action will be taken.';
$string['maxquizfailureratetext'] = 'If you fail more than {$a}, the following action will be taken:';
$string['maxtimebetweenquizzes'] = 'Max time between quizzes';
$string['menu'] = 'Menu';
$string['mergingtables'] = 'Merging tables: {$a->old} into {$a->new}';
$string['messagefromyourteacher'] = 'Message from your teacher';
$string['migratinglogs'] = 'Migrating Reader logs';
$string['min'] = 'Greater than or equal to';
$string['minimumdelay'] = 'Maximum delay';
$string['minpassgrade'] = 'Quiz pass grade';
$string['minpassgrade_help'] = 'The minimum pass grade, as a percentage, for quizzes in this Reader activity.

Attempts with percentage grade lower than this value will be marked as failed.';
$string['minquizattemptrate'] = 'Minimum quiz attempt rate';
$string['minquizattemptrate_help'] = 'The minimum rate at which students may attempt Reader quizzes. If a student does not continue to attempt at least the specified number of quizzes within the specified duration, then the specified action will be taken.';
$string['minquizattemptratetext'] = 'Please take at least {$a}. If you fall below this rate, the following action will be taken:';
$string['morenewattempts'] = '{$a} more new attempts ...';
$string['move_quizzes'] = 'Move quizzes';
$string['move_quizzes_desc'] = 'Move Reader quizzes from the current course to the main course for Reader quizzes.';
$string['movedquizzes'] = '{$a} quizzes moved successfully';
$string['mreadersettings'] = 'Access mReader quizzes';
$string['mreadersiteid'] = 'Site ID for mReader quizzes';
$string['mreadersitekey'] = 'Key for mReader quizzes';
$string['mreaderurl'] = 'URL for mReader quizzes';
$string['needdeletethisattemptstoo'] = 'Need delete this Attempts too';
$string['needtocheckupdates'] = 'This site has not checked for quiz updates in ({$a} days). Check now?';
$string['newdate'] = 'New date';
$string['newreaderattempts'] = 'New Reader attempts';
$string['newreadinggoal'] = 'New goal';
$string['newreadinglevel'] = 'New level';
$string['newsetting'] = 'New value for this setting';
$string['newtime'] = 'New time';
$string['nextlevel'] = 'Quizzes at next level';
$string['nextlevel_help'] = 'The number of quizzes that a student may take from the next reading level, i.e. the reading level that is just above their current reading level. Note that these quizzes do NOT count toward promotion.';
$string['no_password'] = 'No password required';
$string['noaction'] = 'Take no action';
$string['nobooksfound'] = 'No books found';
$string['nobooksinlist'] = 'No books found for your reading level';
$string['nodownloaditems'] = 'No items are available for download';
$string['noemailever'] = 'Sending email is disabled due to $CFG->noemailever config setting.';
$string['noincorrectquizzes'] = 'You have not failed any quizzes';
$string['nomoreattempts'] = 'No more attempts are allowed';
$string['noquizzesfound'] = 'No quizzes found';
$string['noreaders'] = 'No Reader activities found in this course';
$string['norecordsmatch'] = 'No records were selected using the current filters and display options.';
$string['noreview'] = 'You are not allowed to review this quiz';
$string['noreviewshort'] = 'Not permitted';
$string['normalmode'] = 'Normal';
$string['noscript'] = 'JavaScript must be enabled to continue!';
$string['nosearchresults'] = 'No books matching your search are available';
$string['notavailable'] = 'This quiz is not currently available';
$string['nothavepermissioncreateinstance'] = 'Sorry you do not have permission to do this';
$string['notifycheating'] = 'Notify cheats';
$string['notifycheating_help'] = 'If this settings enabled, then students that are judged to have cheated on attempts at Reader quizzes will be sent the "Cheated message" below';
$string['numattempts'] = '{$a} attempts';
$string['numberofextrapoints'] = 'Number of extra points';
$string['off'] = 'Off';
$string['oneattempt'] = '1 attempt';
$string['onlybookswithmorethan'] = 'Only books with more than';
$string['outputformat'] = 'Output format';
$string['pagesettings'] = 'Page settings';
$string['passed'] = 'Passed';
$string['passedshort'] = 'P';
$string['passworderror'] = 'The password entered was incorrect';
$string['passwords_list'] = 'Passwords list';
$string['percentactive'] = 'Active percent';
$string['percentactive_help'] = 'The percentage of students who have taken at least one Reader quiz';
$string['percentinactive'] = 'Inactive percent';
$string['percentinactive_help'] = 'The percentage of students who have not taken any Reader quizzes';
$string['pleaseaskyourinstructor'] = ' Please ask your instructor to move your level up if it is too easy for you.';
$string['pleaseclose'] = 'Your request has been processed. You can now close this window';
$string['pleaseselectpublisher'] = 'Please Select Publisher';
$string['pleasespecifyyourclassgroup'] = 'Please specify your class group or search for a specific student.';
$string['pleasewait'] = 'Please wait';
$string['points'] = 'Points';
$string['pointsex11'] = 'Length (Ex. 1.1)';
$string['popup'] = 'Use "secure" window';
$string['popup_help'] = 'If "Yes" is selected,

* The quiz will only start if the student has a JavaScript-enabled web-browser
* The quiz appears in a full screen popup window that covers all the other windows and has no navigation controls
* Students are prevented, as far as is possible, from using facilities like copy and paste';
$string['prevlevel'] = 'Quizzes at previous level';
$string['prevlevel_help'] = 'The number of quizzes that a student may take from the previous reading level, i.e. the reading level that is just below their current reading level. Note that these quizzes do NOT count toward promotion.';
$string['print_cheatsheet'] = 'Print cheatsheet';
$string['print_cheatsheet_desc'] = 'Display the answers for any Reader quiz.';
$string['process_addquestion'] = '<b>Add questions to quiz {$a}.</b><br />';
$string['process_courseadded'] = '<b>Course added.</b><br />';
$string['promotionnotallowed'] = 'Your teacher has stopped automatic promotion for you.';
$string['promotionsettings'] = 'Promotion settings';
$string['publisher'] = 'Publisher';
$string['publishers'] = 'Publishers';
$string['questionscores'] = 'Show question scores';
$string['questionscores_help'] = 'Should the maximum scores for each question be shown to students when they attempt a Reader quiz?

**Yes**
: Show the maximum scores for questions in Reader quizzes.

**No**
: Hide the maximum scores for questions in Reader quizzes.';
$string['quizadd'] = 'Add Reader quizzes';
$string['quizadded'] = 'Quiz added: {$a}';
$string['quizarrange'] = 'Arrange Reader quizzes';
$string['quizattemptinprogress'] = 'Quiz attempt in progress ...';
$string['quizclosed'] = 'This quiz closed on {$a}';
$string['quizcloseson'] = 'This quiz will close at {$a}';
$string['quizdelete'] = 'Delete Reader quizzes';
$string['quizfordays'] = 'Frequency restriction (days)';
$string['quizhasnoquestions'] = 'Quiz has no questions';
$string['quizmanagement'] = 'Quiz management';
$string['quizname'] = 'Quiz name';
$string['quiznotavailable'] = 'Sorry, this quiz is not currently available to you';
$string['quiznotavailableuntil'] = 'The quiz will not be available until {$a}';
$string['quizopenedon'] = 'This quiz opened at {$a}';
$string['quizsetrate'] = 'Set rate of Reader quizzes';
$string['quizshowhide'] = 'Show / Hide Reader quizzes';
$string['quiztimelimit'] = 'Time limit: {$a}';
$string['quizupdate'] = 'Update Reader quizzes';
$string['quizupdated'] = 'Quiz updated: {$a}';
$string['quizupdateswillbeapplied'] = 'Quiz updates will be applied to all courses currently using the quiz.';
$string['quizzes'] = 'Quizzes';
$string['quizzesadd'] = 'Add quizzes';
$string['quizzesadded'] = 'Quizzes Added';
$string['quizzesarrange'] = 'Arrange quizzes';
$string['quizzesdelete'] = 'Delete quizzes';
$string['quizzesmustbeinstalled'] = 'Quizzes must be installed in a course that is separate from the course that the students will log into when they take quizzes. This course is hidden from the students and is only used as a storage area for the quizzes, and is normally called "All Quizzes." The course that you have established for this purpose should be shown below.  If you haven\'t yet established a course, please click on "Create new course."';
$string['quizzespassedtable'] = 'Quizzes passed at RL-{$a}';
$string['quizzessetrate'] = 'Set reading rate';
$string['quizzesshowhide'] = 'Show/Hide quizzes';
$string['quizzesupdate'] = 'Update quizzes';
$string['rate'] = 'Rate';
$string['rate_help'] = 'The reading rate is specified as the maximum allowed number of attempts at Reader quizzes, or the minimum required number of attempts, within a specified duration.';
$string['rateaction'] = 'Action';
$string['rateaction_help'] = 'This is the action that will be taken if the reading rate restriction is violated. The following actions are available:

**Delay further quiz attempts**
: The student will be prevented from attempting another Reader quiz until the duration period has expired.

**Block further quiz attempts**
: The student will be blocked from attempting any more Reader quizzes until a teacher removes the block.

**Send email to student**
: An email will be sent to the student informing them of the rate violation.

**Send email to teacher**
: An email will be sent to the teacher informing them of the student\'s rate violation.';
$string['rategroup'] = 'Group';
$string['rategroup_help'] = 'the group to which this rate restriction applies';
$string['ratelevel'] = 'Level';
$string['ratelevel_help'] = 'the reading level to which this rate restriction applies';
$string['ratemanyconsecutively'] = '{$a->attempts} quizzes consecutively';
$string['ratemanyinduration'] = '{$a->attempts} quizzes in {$a->duration}';
$string['rateoneinduration'] = '{$a->attempts} quiz in {$a->duration}';
$string['ratetype'] = 'Type';
$string['ratetype_help'] = 'The following types of reading rates can be specified:

**Minimum quiz attempt rate**
: The minimum rate at which students may attempt Reader quizzes. If a student does not continue to attempt at least the specified number of quizzes within the specified duration, then the specified action will be taken.

**Maximum quiz attempt rate**
: The maximum rate at which students may attempt Reader quizzes. If a student tries to attempt more than the specified number of quizzes within the specified duration, then the specified action will be taken.

**Maximum quiz failure rate**
: The maximum rate at which students may fail Reader quizzes. If a student fails more than the specified number of quizzes within the specified duration, then the specified action will be taken.';
$string['ratings'] = 'ratings';
$string['readerdownload'] = 'Download Reader books (and quizzes)';
$string['readerid'] = 'Reader ID';
$string['readerquizsettings'] = 'Reader quiz settings';
$string['readerreports'] = 'Reader module reports';
$string['readinglevel'] = 'Reading Level';
$string['readinglevelshort'] = 'RL {$a}';
$string['readingreportfor'] = 'Reading Report for {$a}';
$string['readonlyfrom'] = 'Read-only from';
$string['readonlyfrom_help'] = 'After this date and time, students may view their main Reader page, but they cannot take any more quizes via this Reader activity.';
$string['readonlymode'] = 'Read-only mode';
$string['readonlymode_desc'] = 'This activity is currently in read-only mode. You can view the information on the first page of this Reader activity, but you cannot take any Reader quizzes.';
$string['readonlysincedate'] = 'This activity has been in read-only mode since {$a}.';
$string['readonlyuntil'] = 'Read-only until';
$string['readonlyuntil_help'] = 'Before this date and time, students may view their main Reader page, but they cannot take any quizzes via this Reader activity.';
$string['readonlyuntildate'] = 'This activity will stay in read-only mode until {$a}.';
$string['recommendedreadingrates'] = 'Recommend reading rates';
$string['redo_upgrade'] = 'Redo upgrade';
$string['redo_upgrade_desc'] = 'Redo an upgrade to the Reader module.';
$string['remotesitenotaccessible'] = 'Remote download site is not accessible';
$string['removebook'] = 'Remove books';
$string['removebook_help'] = 'Books that have "live" attempts cannot be deleted. In order to delete such books, you will first need to delete their "live" attempts.';
$string['removebookerror'] = 'Book was NOT removed: {$a}';
$string['removebookinstance'] = 'Remove selected books from this course';
$string['removebooksuccess'] = 'Book was removed: {$a}';
$string['repairmode'] = 'Repair';
$string['reportbookdetailed'] = 'Books (full)';
$string['reportbookratings'] = 'Book ratings';
$string['reportbooksummary'] = 'Books (summary)';
$string['reportgroupsummary'] = 'Groups';
$string['reportquiztoreader'] = 'Summary Report by Student';
$string['reports'] = 'Reports';
$string['reportsettings'] = 'Report settings';
$string['reportuserdetailed'] = 'Students (full)';
$string['reportusersummary'] = 'Students (summary)';
$string['requirepasswordmessage'] = 'To attempt this quiz you need to know the quiz password';
$string['requireqtypeordering'] = 'The Reader activity module cannot be installed or updated because the Ordering question type is missing. Please download the Ordering question type, put it at {$a}/question/type/ordering, and reload this page.';
$string['restoreattempts'] = 'Restore selected attempts';
$string['restoredeletedattempt'] = 'Restore deleted attempt';
$string['restrictlocalhost'] = 'Sorry, you cannot take quizzes on mreader.org from your current localhost URL. Please switch to a <a href="{$a}">globally accessible URL</a>.';
$string['returntocoursepage'] = 'Return to Course Page';
$string['returntoreports'] = 'Return to Reports';
$string['returntostudentlist'] = 'Return to Student List';
$string['review'] = 'Review';
$string['reviewthisattempt'] = 'Review your responses to this attempt';
$string['rowsperpage'] = 'Rows per page';
$string['run_readercron'] = 'Run Reader cron';
$string['run_readercron_desc'] = 'Run the cron job for the Reader module.';
$string['safebrowsererror'] = 'This quiz has been set up so that it may only be attempted using the Safe Exam Browser. You cannot attempt it from this web browser.';
$string['safebrowsernotice'] = 'This quiz has been configured so that students may only attempt it using the Safe Exam Browser.';
$string['scanningattempts'] = 'Scanning attempts at Reader quizzes';
$string['search'] = 'Search';
$string['search_help'] = 'To search for a particular book, enter some text contained in the title of the book and click the "Go" button';
$string['searchforabook'] = 'Search for a book';
$string['sectionname'] = 'Section name';
$string['sectiontoseparate'] = 'add quizzes to separate sections for publisher and level, adding quizzes to existing sections when available';
$string['sectiontothebottom'] = 'add all quizzes to the bottom of the hidden course in a new section';
$string['sectiontothissection'] = 'add selected quizzes to this section';
$string['sectiontypedefault'] = 'The default section(s)';
$string['sectiontypehidden'] = 'A hidden section';
$string['sectiontypelast'] = 'The last section';
$string['sectiontypenew'] = 'A new section';
$string['sectiontypevisible'] = 'A visible section';
$string['seedetailsbelow'] = 'see details below';
$string['select'] = 'Select';
$string['select_course'] = 'Select Course';
$string['select_help'] = 'Click on the kind of items you wish to be selected in the list below';
$string['selectabook'] = 'Select a book';
$string['selectalreadyexist'] = 'Select already exist';
$string['selectedbookname'] = 'Selected book name';
$string['selectipmask'] = 'Select ip mask';
$string['selectlevel'] = 'Select Level';
$string['selectpublisher'] = 'Select Publisher';
$string['selectseries'] = 'Select Series';
$string['selectsomeattempts'] = 'Select one or more attempts';
$string['selectsomebooks'] = 'Select one or more books';
$string['selectsomerows'] = 'Please check some of the boxes in the select column.';
$string['selectsomeusers'] = 'Select one or more users';
$string['selectthisquiz'] = 'Select this quiz';
$string['sendemailtostudent'] = 'Send email to student';
$string['sendemailtoteacher'] = 'Send email to teacher';
$string['sendmessage'] = 'Send message to selected students';
$string['sentemailmoodle'] = 'An email has been sent via Moodle to: {$a->email}';
$string['sentemailphp'] = 'An email has been sent via PHP mail to: {$a->email}';
$string['sentmessage'] = 'The message was successfully sent to {$a} user(s)';
$string['separategroups'] = 'Separate groups';
$string['separatelevels'] = 'Separate levels';
$string['servererror'] = 'Message from server: {$a}';
$string['serverpassword'] = 'Password for Reader quizzes';
$string['serversettings'] = 'Download Moodle Reader quizzes';
$string['serverurl'] = 'URL for Reader quizzes';
$string['serverusername'] = 'Username for Reader quizzes';
$string['setallowpromotion'] = 'Change promotion setting for selected students';
$string['setbookinstances'] = 'Select quizzes to make available to students';
$string['setcurrentlevel'] = 'Change current level for selected students';
$string['setdifficulty'] = 'Set book difficulty';
$string['setgenre'] = 'Set genre';
$string['setgoal'] = 'Set goal';
$string['setgoals'] = 'Set goals';
$string['setgoals_description'] = 'On this page you can set the reading goals for students at specific reading levels, or in specific groups. Note that the settings for individual students on the the report pages will override the settings on this page.';
$string['setlevel'] = 'Set level';
$string['setlevels'] = 'Set levels';
$string['setlevels_description'] = 'On this page you can set the reading levels for groups of students. Note that these settings will overwrite the setting for individual students on the the report pages.';
$string['setmessage'] = 'Set message';
$string['setmessagetext'] = 'Message text';
$string['setmessagetime'] = 'Display until';
$string['setname'] = 'Set book title';
$string['setpoints'] = 'Set point value';
$string['setpromotiontime'] = 'Change promotion date and time';
$string['setpublisher'] = 'Set publisher';
$string['setrates'] = 'Set rates';
$string['setrates_description'] = 'On this page you can set the reading rates for students at specific reading levels, or in specific groups.';
$string['setreadinggoal'] = 'Set reading goal';
$string['setreadinglevel'] = 'Set reading level';
$string['setstartlevel'] = 'Change start level for selected students';
$string['setstoplevel'] = 'Change maximum level for selected students';
$string['settings'] = 'Settings';
$string['setuniformgoalinpoints'] = 'Set uniform goal in points';
$string['setuniformgoalinwords'] = 'Set uniform goal in words';
$string['setwords'] = 'Set word count';
$string['show'] = 'Show';
$string['show_help'] = 'Click on the kind of items you wish to be shown in the list below';
$string['showall'] = 'Show All';
$string['showattempts'] = 'Show attempts matching these conditions';
$string['showdeleted'] = 'Show deleted attempts';
$string['showhidden'] = 'Show hidden books';
$string['showhide'] = 'Show/Hide';
$string['showhidebook'] = 'Show or hide';
$string['showhidebooks'] = 'Update the show/hide setting for selected books';
$string['showlevel'] = 'Show Level';
$string['showpercentgrades'] = 'Show percent grades';
$string['showpercentgrades_help'] = '**Yes**
: Show the grade (as a percent) for each attempt at a Reader quiz

**No**
: Do NOT show the grades for individual attempts at Reader quizzes';
$string['showpoints'] = 'Show points only';
$string['showpointsandwordcount'] = 'Show both points and word count';
$string['showprogressbar'] = 'Show progress bar';
$string['showprogressbar_help'] = '**Yes**
: Show the word count progress bar on the main page for this Reader activity

**No**
: Do NOT show the word count progress bar on the main page for this Reader activity';
$string['showreviewlinks'] = 'Show review links';
$string['showreviewlinks_help'] = '**Yes**
: Add links from values on the Reader report pages to quiz review pages showing exactly how each question in an attempt at a Reader quiz was answered.

**No**
: Do NOT add links from the values on the Reader report pages to quiz review pages.

This setting affects teachers only. It does affect students because they do not have access to the Reader report pages.';
$string['showwordcount'] = 'Show word count only';
$string['skipline'] = 'Skip line: {$a}';
$string['skipped'] = 'Skipped';
$string['skipquizdownload'] = 'Quiz "{$a->quizname}" already exists in section {$a->sectionnum}, "{$a->sectionname}", of "{$a->coursename}", and has been skipped';
$string['sofar'] = 'so far';
$string['sort_strings'] = 'Sort strings';
$string['sort_strings_desc'] = 'Sort the strings used by the Reader module.';
$string['sortfields'] = 'Sort fields';
$string['startbookdetailed'] = 'There is currently no data available for this report.';
$string['startbooksummary'] = 'There is currently no data available for this report.';
$string['startdate'] = 'Start date';
$string['starteditcourse'] = '**Getting started**

On this page you can edit and remove Reader books that are available in this course. You can also make new books available in this course, as long as they have already been download to this Moodle site using the download pages.
';
$string['starteditsite'] = '**Getting started**

On this page you can edit and remove Reader books on this Moodle site. In order to add new Reader books to this site, please use the download pages.
';
$string['startgroupsummary'] = 'There is currently no data available for this report.';
$string['startlevel'] = 'Start level';
$string['startscan'] = 'Start scan';
$string['startuserdetailed'] = 'There is currently no data available for this report.';
$string['startusersummary'] = 'There is currently no data available for this report.';
$string['stoplevel'] = 'Maximum level';
$string['stoplevel_help'] = 'Students cannot be promoted automatically beyond this level';
$string['stoplevelforce'] = 'Apply this value to ALL current users';
$string['strfattempttime'] = '%Y %b %d (%a) %H:%M';
$string['strfattempttimeshort'] = '%Y/%m/%d %H:%M';
$string['strfdateshort'] = '%Y %b %d (%a)';
$string['strfdatetimeshort'] = '%Y %b %d (%a) %H:%M';
$string['strftimeshort'] = '%H:%M';
$string['studentmanagement'] = 'Student Management';
$string['studentslevels'] = 'Change Students Levels, Promotion Policy and Goals';
$string['studentuserid'] = 'Student user ID';
$string['studentusername'] = 'Student username';
$string['studentview'] = 'Student view';
$string['subnetlength'] = 'IP mask';
$string['subnetwrong'] = 'This quiz is only accessible from certain locations, and this computer is not on the allowed list.';
$string['summaryreportbybooktitle'] = 'Summary Report by Book Title';
$string['summaryreportbyclassgroup'] = 'Summary Report by Class Group';
$string['takequizfor'] = 'Take the quiz for "{$a}"';
$string['takethisquiz'] = 'Take this quiz';
$string['targetcategory'] = 'Target category';
$string['targetcategory_help'] = 'The course category containing the course into which you wish to download the quizzes for the selected books.';
$string['targetcourse'] = 'Target course';
$string['targetcourse_help'] = 'Select the course into which you wish to download the quizzes for the selected books. Usually you should download to a hidden course that is used solely to store quizzes used by the Reader module.

Select the type of course from one of the following options:

**All**
: Choose from a list of all the courses on this Moodle site.

**Hidden**
: Choose from a list of courses that are visible to you but hidden from students. Usually you should choose this option.

**Visible**
: Choose from a list of courses that are visible to you and enrolled students.

**Current**
: Reader quizzes will be downloaded into the current course.

**New**
: Reader quizzes will be downloaded into a new course. Enter a name for the new course in the text box.';
$string['targetsection'] = 'Target section';
$string['targetsection_help'] = 'Specify the course section i.e. the week or topic, into which you want to download the quizzes. The following types of section are available:

**The default section(s)**
: The quizzes will be grouped and placed in sections according to the "Publisher - Level" of their respective books.

**A hidden section**
: The quizzes will be put into the selected hidden section of the course.

**A visible section**
: The quizzes will be put into the selected visible section of the course.

**The last section**
: The quizzes will be added to the last section of the course

**A new section**
: The quizzes will be added to a new section. Enter a name for the new section in the text box.';
$string['termtype'] = 'Term';
$string['therehavebeennonewquizzesorupdates'] = 'There have been no new quizzes or updates added to the MoodleReader quiz bank since the last time you checked.';
$string['thisattempt'] = 'this attempt';
$string['thisblockunavailable'] = 'This block is currently unavailable to this student';
$string['thislevel'] = 'Quizzes at current level';
$string['thislevel_help'] = 'The number of quizzes at the current reading level that a student must pass in order to be promoted to the next reading level. Note that only quizzes passed since the most recent promotion count towards the next promotion.';
$string['thisterm'] = 'this term';
$string['timefinish'] = 'Time finished';
$string['timeleft'] = 'Time Remaining';
$string['timestart'] = 'Time started';
$string['to'] = 'to';
$string['tools'] = 'Tools';
$string['totalpoints'] = 'Total points';
$string['totalpointsallterms'] = 'Total points (all terms)';
$string['totalpointsgoal'] = 'Word/point goal';
$string['totalpointsgoal_help'] = 'The total number of words/points that students are expected to accumulate in the current term';
$string['totalpointsthisterm'] = 'Total points (this term)';
$string['totalwords'] = 'Total words';
$string['totalwordsallterms'] = 'Total words (all terms)';
$string['totalwordsthisterm'] = 'Total words (this term)';
$string['transferfromcourse'] = 'Transfer from course';
$string['type'] = 'Type';
$string['type_help'] = 'Select the type of books you want to be displayed in the list below:

**Books with quizzes**
: The page will show a list of books with quizzes that are available for download.

**Books without quizzes**
: The page will show a list of books for which data such as difficulty and word counts exist, but for which no quiz has yet been created.
';
$string['uniqueip'] = 'Require unique IP';
$string['update'] = 'Update';
$string['updatecheated'] = 'Update the cheated setting for selected attempts';
$string['updatedon'] = 'Updated on {$a}';
$string['updatepassed'] = 'Update the pass/fail setting for selected attempts';
$string['updatequizzes'] = 'Update quizzes';
$string['updates'] = 'Updates';
$string['updatesavailable'] = '{$a} update(s) available';
$string['updatinggrades'] = 'Updating Reader grades';
$string['upgradeoldquizzesinfo'] = 'The upgrade has been paused, so that you can decide whether or not you wish to keep old versions of the Reader module quizzes on this Moodle site.

If you select "Yes", all the old Reader quizzes will be kept. Choose this option if you wish to keep all statistics about answers to individual questions on old Reader quizzes.

If you select "No", old duplicate versions of Reader quizzes will be deleted leaving only the most recent visible version of each quiz. Choose this option if you are not concerned about statistics regarding answers to individual questions on old Reader quizzes and you wish to tidy up the Reader quizzes course page.

Note that even if you choose "No", the word counts in Reader activities will not be affected by this operation.

Do you wish to keep old versions of Reader module quizzes?';
$string['upgradestalefiles'] = 'Mixed Reader module versions detected, upgrade cannot continue';
$string['upgradestalefilesinfo'] = 'The Moodle update process has been paused because files from previous versions of the Reader module have been detected in the "mod/reader" directory.

This may cause problems later, so in order to continue you must ensure that the "mod/reader" directory contains only files for a single version of the Reader module.

The recommended way to clean your "mod/reader" directory is as follows:

* remove the current "mod/reader", or at least move it out of the "mod" directory
* create a new "mod/reader" directory containing only files from either a standard Reader module zip download, or from the GIT repository

Alternatively, if you give recursive write access to the following "mod/reader" folder, the files may be deleted automatically when you resume the upgrade:

* {$a->dirpath}

The "mod/reader" files that are currently blocking the upgrade are as follows:
{$a->filelist}

If you prefer, you can also delete the above file(s) yourself.

Click the button below to resume the Moodle update process.';
$string['uploaddatanoquizzes'] = 'Download data for books that have no quizzes';
$string['uploadquiztoreader'] = 'Download quizzes from the Reader Quiz Database';
$string['use_this_course'] = 'Use this course';
$string['usecourse'] = 'Reader quizzes course';
$string['usecourse_help'] = 'A course on this Moodle site that contains the Reader quizzes for this Reader activity. This course should be hidden from students. It generally contains quizzes that have been downloaded by the Reader module from an external Reader quiz repository, e.g. moodlereader.net.';
$string['usedefaultquizid'] = 'Always use default quiz';
$string['userexport'] = 'Export user data';
$string['userimport'] = 'Import user data';
$string['userlevel'] = 'User level';
$string['usernamenotfound'] = 'Username not found: {$a}';
$string['users'] = 'Users';
$string['usersallwith'] = 'all users who have attempted at least one quiz';
$string['usersenrolledall'] = 'all enrolled users';
$string['usersenrolledwith'] = 'enrolled users who have attempted at least one quiz';
$string['usersenrolledwithout'] = 'enrolled users who have not attempted any quizzes';
$string['usersetgoals'] = 'Set user goals';
$string['usersetlevels'] = 'Set user levels';
$string['usersetmessage'] = 'Set message for users';
$string['usersexport'] = 'Export data';
$string['usersimport'] = 'Import data';
$string['userssetgoals'] = 'Set goals';
$string['userssetlevels'] = 'Set levels';
$string['userssetmessage'] = 'Set message';
$string['usertype'] = 'Users to include';
$string['valueoutofrange'] = 'This value should be between {$a->min} and {$a->max}';
$string['viewattempts'] = 'View and Delete Attempts';
$string['viewlogsuspiciousactivity'] = 'View log of suspicious activity';
$string['windowclosing'] = 'This window will close shortly.';
$string['withoutdayfilter'] = 'Without day filter';
$string['words'] = 'Words';
$string['wordscount'] = 'Word count';
$string['wordsorpoints'] = 'Show words or points';
$string['wordsorpoints_help'] = '**Show word count only**
: On report pages, show only the word count for attempts.

**Show points only**
: On report pages, show only the points earned for attempts.

**Show both words and points**
: On report pages, show both the word count and the points earned for attempts.';
$string['youcannottake'] = 'You can NOT take any more quizzes at reading level {$a}';
$string['youcannotwait'] = 'This quiz closes before you will be allowed to start another attempt.';
$string['youcantakeaquizafter'] = 'You can take your next quiz after {$a}';
$string['youcantakeaquiznow'] = 'You can take a quiz now.';
$string['youcantakeasmanyquizzesasyouwant'] = ' You can take as many quizzes as you want at Level {$a}. ';
$string['youcantakeplural'] = 'You can take {$a->count} more quizzes at reading level {$a->level}. These books will count toward your reading total, but will not count toward your promotion.';
$string['youcantakesingle'] = 'You can take ONE more quiz at reading level {$a->level}. This book will count toward your reading total, but will not count toward your promotion.';
$string['youcantakeunlimited'] = 'You can take as many quizzes as you want at reading level {$a}';
$string['youhavebeenpromoted'] = 'Congratulations!! You have been promoted to Level {$a}';
$string['youmustpassplural'] = 'To be promoted, you must pass {$a->count} more quizzes at reading level {$a->level}.';
$string['youmustpasssingle'] = 'To be promoted, you must pass ONE more quiz at reading level {$a->level}.';
$string['youmustwait'] = 'You must wait before you may re-attempt this quiz. You will be allowed to start another attempt after {$a}.';
$string['yourcurrentlevel'] = 'Your reading level is currently set to level {$a}';
$string['youwerepromoted'] = 'You were promoted to reading level {$a->level} on {$a->date} at {$a->time}';
