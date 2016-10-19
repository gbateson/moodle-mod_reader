===========================================
The Reader module for Moodle >= 2.0
===========================================

The Reader module for Moodle >= 2.0 tracks the students' reading achievements by maintaining a total of the number words each student reads. After reading one of the books at an appropriate reading level, a student takes a quiz to demonstrate a reasonable understanding of the content of the book. If they pass the quiz, the number of words in the book is added to the total number of words they have read. Students are encouraged to work toward the reading goal, which is the number of words the teacher expects them to read in a term. Various reports are available to the teacher who can adjust the reading goals, student levels, and book difficulty if required.

===========================================
To INSTALL or UPDATE the Reader module
===========================================

    1. Get the files for this plugin from any one of the following locations:

        (a) GIT: https://github.com/gbateson/moodle-mod_reader.git
        (b) zip: the Moodle.org -> Plugins repository (search for Reader)
        (c) zip: http://bateson.kochi-tech.ac.jp/zip/plugins_mod_reader.zip

       If you are installing from a zip file, unzip the zip file, to create a folder called "reader" and then upload or move this "reader" folder to the "mod" folder on your Moodle >= 2.x site to create a new folder at "mod/reader" - not "mod/reader/reader" :-)

    2. Log in to Moodle as an administrator to initiate the install/upgrade process. If the install/upgrade does not begin automatically, you can initiate it manually by navigating to the following item on your Moodle site:
        (a) Administration -> Site administration -> Notifications

    3. You will also need to install the the Ordering question type, which is available from the following location:
        (a) https://moodle.org/plugins/view.php?plugin=qtype_ordering

===========================================
To SETUP the Reader module on your site
===========================================

    1. Create a username on http://moodlereader.org/

    2. Contact Tom Robb by email (tom@moodlereader.org) and request that your username on moodlereader.org be given authorization to download the Reader module quizzes.

    3. Create a new course on your Moodle site called "Reader Quizzes". This course will be used to store any Reader quizzes that you download. MAKE SURE THE COURSE HIDDEN FROM STUDENTS.

    4. Enter the download information in the settings for the Reader module on your Moodle site.
        (a) Site administration ->︎ Plugins ->︎ Activity modules ->︎ Reader
        (b) Reader quizzes course  : select the "Reader Quizzes" course
        (c) Reader server URL      : http://moodlereader.net/quizbank
        (d) Reader server username : (your username on moodlereader.org)
        (e) Reader server password : (your password on moodlereader.org)

===========================================
To ADD a Reader activity to a Moodle course
===========================================

    1. Login to your Moodle site as a teacher or administrator, and navigate to a course page.

    2. Enable "Edit mode" on the course page.

    3. Locate the topic/week where you wish to add the Reader activity.

    4. Click the "Add an activity or resource" link and select "Reader" from the list of activities.

    5. Input a name for the Reader activity and review other settings.

    6. Click "Save changes" at bottom of page.

===========================================
To DOWNLOAD QUIZZES for the Reader module
===========================================

    Before downloading Reader quizzes, please ensure that you have setup the Reader module on your Moodle site, as described earlier in this document. i.e. you have created a hidden "Reader Quizzes" course and added your MoodleReader.org access information to the Reader module settings on your Moodle site.

    1. Within a Reader activity, navigate to the "Books" tab, and then the "Download book data (and quizzes)" tab.

    2. From the list of books, select the books whose data and quizzes you wish to download.

    3. At the botom of the page, select the course category and name of the hidden "Reader Quizzes" course.

    4. Click the DOWNLOAD button, and confirm that the books are downloaded as expected.

===========================================
To ACCESS QUIZZES for the Reader module
===========================================

    1. Students navigate to their Moodle course, and click the link to view the Reader activity.

    2. Students locate the quiz for a book by using either the "Search for a book" input box or the "Select a book" menus.

    3. When students have located the required quiz, they click the "Take this quiz" button.

    4. Please remember that students can only take each quiz once. They cannot retake quizzes. Furthermore, they cannot take quizzes for books with essentially the same title as books they have previously read. Therefore, they should be sure to read a book completely, BEFORE attempting the quiz.

===========================================
To REPORT BUGS and GET MORE INFORMATION
===========================================

    For more information, tutorials and online discussion forums, please visit:
    http://moodlereader.org/

    The online forum to report bugs with the Reader module and get more help is at the following URL:
    http://moodlereader.org/mod/forum/view.php?f=23
