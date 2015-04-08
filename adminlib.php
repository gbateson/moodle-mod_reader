<?php

/**
 * reader_format_delay
 *
 * @param xxx $seconds
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_format_delay($seconds) {

    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2419200);
    $years   = round($seconds / 29030400);

    switch (true) {
        case ($seconds <= 60): $text = ($seconds==1 ? 'one second' : "$seconds seconds"); break;
        case ($minutes <= 60): $text = ($minutes==1 ? 'one minute' : "$minutes minutes"); break;
        case ($hours   <= 24): $text = ($hours==1   ? 'one hour'   : "$hours hours"    ); break;
        case ($days    <= 7) : $text = ($days==1    ? 'one day'    : "$days days"      ); break;
        case ($weeks   <= 4) : $text = ($weeks==1   ? 'one week'   : "$weeks weeks"    ); break;
        case ($months  <=12) : $text = ($months==1  ? 'one month'  : "$months months"  ); break;
        default:               $text = ($years==1   ? 'one year'   : "$years years "   );
    }

    return "$text ";
}

/**
 * reader_format_passed
 *
 * @param string $passed
 * @param boolean $fulltext (optional, default=false)
 * @return string
 * @todo Finish documenting this function
 */
function reader_format_passed($passed, $fulltext=false) {
    $passed = strtolower($passed);
    switch ($passed) {
        case 'true': $name = 'passed'; break;
        case 'false': $name = 'failed'; break;
        case 'cheated': $name = 'cheated'; break;
        default: return $passed;
    }
    if ($fulltext==false) {
        $name .= 'short';
    }
    return get_string($name, 'mod_reader');
}

/**
 * reader_change_to_teacherview
 *
 * @todo Finish documenting this function
 */
function reader_change_to_teacherview() {
    global $DB, $USER;
    $unset = false;
    if (isset($_SESSION['SESSION']->reader_page)) {
        $unset = ($_SESSION['SESSION']->reader_page == 'view');
    }
    if (isset($_SESSION['SESSION']->reader_lasttime)) {
        $unset = ($_SESSION['SESSION']->reader_lasttime < (time() - 300));
    }
    if ($unset) {
        // in admin.php, remove settings coming from view.php
        unset($_SESSION['SESSION']->reader_page);
        unset($_SESSION['SESSION']->reader_lasttime);
        unset($_SESSION['SESSION']->reader_lastuser);
        unset($_SESSION['SESSION']->reader_lastuserfrom);
    }
    if (isset($_SESSION['SESSION']->reader_changetostudentview)) {
        // in view.php, prepare settings going to admin.php
        if ($userid = $_SESSION['SESSION']->reader_changetostudentview) {
            $_SESSION['SESSION']->reader_lastuser = $USER->id;
            $_SESSION['SESSION']->reader_page     = 'view';
            $_SESSION['SESSION']->reader_lasttime = time();
            $_SESSION['SESSION']->reader_lastuserfrom = $userid;
            if ($USER = $DB->get_record('user', array('id' => $userid))) {
                $_SESSION['SESSION']->reader_teacherview = 'teacherview';
                unset($_SESSION['SESSION']->reader_changetostudentview);
                unset($_SESSION['SESSION']->reader_changetostudentviewlink);
            }
        }
    }
}

/**
 * reader_change_to_studentview
 *
 * @param object  $context
 * @param integer $userid
 * @param string  $link
 * @param string  $location
 * @todo Finish documenting this function
 */
function reader_change_to_studentview($userid, $link, $location) {
    global $DB, $USER;
    // cancel teacherview
    unset($_SESSION['SESSION']->reader_teacherview);
    // prepare settings going to view.php
    $_SESSION['SESSION']->reader_changetostudentview = $USER->id;
    $_SESSION['SESSION']->reader_changetostudentviewlink = $link;
    $_SESSION['USER'] = $DB->get_record('user', array('id' => $userid));
    header("Location: $location");
    // script will terminate here
}

/**
 * reader_ajax_textbox_title
 *
 * @param xxx $has_capability
 * @param xxx $book
 * @param xxx $type : "words", "level" or "publisher"
 * @param xxx $text
 * @param xxx $id
 * @param xxx $act
 * @todo Finish documenting this function
 */
function reader_ajax_textbox_title($has_capability, $book, $type, $id, $act) {
    if ($has_capability) {
        $divid = $type.'title_'.$book->id;
        $inputid = $type.'title_input_'.$book->id;
        $onkeyup = "if(event.keyCode=='13') {request('admin.php?ajax=true&id={$id}&act={$act}&{$type}titleid={$book->id}&{$type}titlekey='+document.getElementById('$inputid').value,'$divid');return false;}";
        $title = '';
        $title .= html_writer::start_tag('div', array('id' => $divid));
        $title .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $inputid, 'name' => $type.'title', 'value' => $book->$type, 'onkeyup' => $onkeyup));
        $title .= html_writer::end_tag('div');
    } else {
        $title = $book->$type;
    }
    return $title;
}

/**
 * reader_setbookinstances
 *
 * @param xxx $id
 * @param xxx $reader
 * @todo Finish documenting this function
 */
function reader_setbookinstances($cmid, $reader) {
    global $CFG, $DB, $output;

    if ($reader->bookinstances == 0) {
        echo '<div>'.get_string('coursespecificquizselection', 'mod_reader').'</div>';
    }

    $currentbooks = array();
    if ($books = $DB->get_records('reader_book_instances', array('readerid' => $reader->id), 'id', 'id, bookid, readerid')) {
        foreach ($books as $book) {
            $currentbooks[$book->bookid] = true;
        }
    }

    $publishers = array();
    if ($books = $DB->get_records('reader_books', array(), 'publisher, name', 'id, publisher, level, name')) {
        foreach ($books as $book) {
            if (empty($publishers[$book->publisher])) {
                $publishers[$book->publisher] = array();
            }
            if (empty($publishers[$book->publisher][$book->level])) {
                $publishers[$book->publisher][$book->level] = array();
            }
            $book->checked = isset($currentbooks[$book->id]);
            $publishers[$book->publisher][$book->level][$book->id] = $book;
        }
    }
    unset($currentbooks, $books, $book);

    $uniqueid = 0;
    $uniqueids = array();

    $checked = new stdClass();
    $checked->publishers = array();
    $checked->levels     = array();

    foreach ($publishers as $publisher => $levels) {
        foreach ($levels as $level => $bookids) {
            foreach ($bookids as $bookid => $bookname) {
                $uniqueid++;
                $uniqueids[$bookid] = $uniqueid;

                if (empty($checked->publishers[$publisher])) {
                    $checked->publishers[$publisher] = array();
                }
                $checked->publishers[$publisher][] = $uniqueid;

                if (empty($checked->levels[$publisher])) {
                    $checked->levels[$publisher] = array();
                }
                if (empty($checked->levels[$publisher][$level])) {
                    $checked->levels[$publisher][$level] = array();
                }
                $checked->levels[$publisher][$level][] = $uniqueid;
            }
        }
    }

    echo $output->box_start('generalbox');
    require_once('js/hide.js');

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function setChecked(obj,from,to) {'."\n";
    echo '    for (var i=from; i<=to; i++) {'."\n";
    echo     '    if (document.getElementById("quiz_" + i)) {'."\n";
    echo '            document.getElementById("quiz_" + i).checked = obj.checked;'."\n";
    echo '        }'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo '<form action="admin.php?a=admin&id='.$cmid.'&act=setbookinstances" method="post" id="mform1">';
    echo '<div style="width:600px">';
    echo '<a href="#" onclick="expandall();return false;">Show All</a>';
    echo ' / ';
    echo '<a href="#" onclick="collapseall();return false;">Hide All</a>';
    echo '<br />';

    //vivod
    $count = 0;

    $submitonclick = array();
    $submitonclicktop = array();

    if (! empty($publishers)) {
        foreach ($publishers as $publisher => $levels) {
            $count++;
            echo '<br /><a href="#" onclick="toggle(\'comments_'.$count.'\');return false">
                  <span id="comments_'.$count.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';
            echo ' <b>'.$publisher.' &nbsp;</b>';

            echo '<span id="comments_'.$count.'"><input type="checkbox" name="installall['.$count.']" onclick="setChecked(this,'.$checked->publishers[$publisher][0].','.end($checked->publishers[$publisher]).')" value="" /><span id="seltext_'.$count.'">Select All</span>';

            $topsubmitonclick = $count;
            foreach ($levels as $level => $bookids) {
                $count++;

                echo '<div style="padding-left:40px;padding-top:10px;padding-bottom:10px;"><a href="#" onclick="toggle(\'comments_'.$count.'\');return false">
                      <span id="comments_'.$count.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';

                echo '<b>'.$level.' &nbsp;</b>';
                echo '<span id="comments_'.$count.'"><input type="checkbox" name="installall['.$count.']" onclick="setChecked(this,'.$checked->levels[$publisher][$level][0].','.end($checked->levels[$publisher][$level]).')" value="" /><span id="seltext_'.$count.'">Select All</span>';

                foreach ($bookids as $bookid => $book) {
                    echo '<div style="padding-left:20px;"><input type="checkbox" name="quiz[]" id="quiz_'.$uniqueids[$bookid].'" value="'.$bookid.'"';
                    if ($book->checked) {
                        echo ' checked="checked"';
                        $submitonclick[$count] = 1;
                        $submitonclicktop[$topsubmitonclick] = 1;
                    }
                    echo ' /> &nbsp; '.$book->name.'</div>';
                }
                echo '</span></div>';
            }
            echo '</span>';
        }

        echo '<div style="margin-top:40px;margin-left:200px;"><input type="submit" name="showquizzes" value="Show Students Selected Quizzes" /></div>';
    }

    echo '<input type="hidden" name="step" value="1" />';

    echo '</div>';
    echo '</form>';

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";

    echo 'var vh_numspans = '.$count.';'."\n";
    echo 'collapseall();'."\n";

    foreach ($submitonclicktop as $key => $value) {
        echo 'expand("comments_'.$key.'");'."\n";
    }
    foreach ($submitonclick as $key => $value) {
        echo 'expand("comments_'.$key.'");'."\n";
    }

    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo $output->box_end();
}

/**
 * reader_datetime_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_datetime_selector($name, $value, $disabled) {
    $output = '';

    $year  = array_combine(range(1970, 2020), range(1970, 2020));
    $month = array_combine(range(1, 12), range(1, 12));
    $day   = array_combine(range(1, 31), range(1, 31));
    $hour  = range(0, 23);
    $min   = range(0, 59);

    // convert months to month names
    foreach ($month as $m) {
        $month[$m] = userdate(gmmktime(12,0,0,$m,15,2000), "%B");
    }

    // convert hours to double-digits
    foreach ($hour as $h) {
        $hour[$h] = sprintf('%02d', $h);
    }

    // convert minutes to double-digits
    foreach ($min as $m) {
        $min[$m] = sprintf('%02d', $m);
    }

    $defaultvalue = ($value==0 ? time() : $value);
    $fields = array('year' => '%Y',  'month' => '%m', 'day' => '%d', 'hour' => '%H', 'min'  => '%M');
    foreach ($fields as $field => $fmt) {

        $selected = intval(gmstrftime($fmt, $defaultvalue));
        $output .= html_writer::select($$field,  $name.'_'.$field,  $selected, '', array('disabled' => $disabled));

        // add separator, if necessary
        switch ($field) {
            case 'day': $output .= ' &nbsp; '; break;
            case 'hour': $output .= ':'; break;
        }
    }

    // javascript to toggle "disable" property of select elements
    $onchange = 'var obj = document.getElementsByTagName("select");'.
                'if (obj) {'.
                    'var i_max = obj.length;'.
                    'for (var i=0; i<i_max; i++) {'.
                        'if (obj[i].id && obj[i].id.indexOf("menu'.$name.'_")==0) {'.
                            'obj[i].disabled = this.checked;'.
                        '}'.
                    '}'.
                '}';

    // add "disabled" checkbox
    $params = array('id'   => 'id_'.$name.'_disabled',
                    'name' => $name.'_disabled',
                    'type' => 'checkbox',
                    'value' => 1,
                    'onchange' => $onchange);
    if ($disabled) {
        $params['checked'] = 'checked';
    }
    $output .= html_writer::empty_tag('input', $params);
    $output .= get_string('disable');

    return $output;
}

/**
 * reader_grade_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_grade_selector($name, $value) {
    $grades = range(0, 100);
    foreach ($grades as $g) {
        $grades[$g] = "$g %";
    }
    $grades = array('' => '') + $grades;
    return html_writer::select($grades, $name, $value, '');
}

/**
 * reader_duration_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_duration_selector($name, $value) {

    $duration = array_combine(range(0, 50, 10), range(0, 50, 10)) +
                array_combine(range(1*60, 5*60, 60), range(1, 5)) +
                array_combine(range(10*60, 15*60, 300), range(10, 15, 5));

    foreach ($duration as $num => $text) {
        if ($num < 60) {
            if ($text==1) {
                $text .= ' second';
            } else {
                $text .= ' seconds';
            }
        } else if ($num <= 3600) {
            if ($text==1) {
                $text .= ' minute';
            } else {
                $text .= ' minutes';
            }
        } else {
            if ($text==1) {
                $text .= ' hour';
            } else {
                $text .= ' hours';
            }
        }
        $duration[$num] = $text;
    }
    $duration = array('' => '') + $duration;
    return html_writer::select($duration, $name, $value, '');
}

/**
 * reader_order_object
 *
 * @param xxx $array
 * @param xxx $key
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_order_object($array, $key) {
    $tmp = array();
    foreach($array as $akey => $array2) {
        $tmp[$akey] = $array2->$key;
    }
    sort($tmp, SORT_NUMERIC);
    $tmp2 = array();
    $tmp_size = count($tmp);
    foreach($tmp as $key => $value) {
        $tmp2[$key] = $array[$key];
    }
    return $tmp2;
}

/**
 * reader_make_table_headers
 *
 * @uses $CFG
 * @uses $USER
 * @param xxx $titlesarray
 * @param xxx $orderby "ASC" or "DESC"
 * @param xxx $sort name of a table column
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_make_table_headers(&$table, $headers, $orderby, $sort, $params) {
    global $CFG;

    if ($orderby == 'ASC') {
        $direction = 'DESC';
        $directionimg = 'down';
    } else {
        $direction = 'ASC';
        $directionimg = 'up';
    }

    $table->head = array();
    foreach ($headers as $text => $columnname) {
        $header = $text;

        if ($columnname) {

            // append sort icon
            if ($sort == $columnname) {
                $imgparams = array('theme' => $CFG->theme, 'image' => "t/$directionimg", 'rev' => $CFG->themerev);
                $header .= ' '.html_writer::empty_tag('img', array('src' => new moodle_url('/theme/image.php', $imgparams), 'alt' => ''));
            }

            // convert $header to link
            $params['sort'] = $columnname;
            $params['orderby'] = $direction;
            $header = html_writer::tag('a', $header, array('href' => new moodle_url('/mod/reader/admin.php', $params)));
        }

        // add header to table
        $table->head[] = $header;
    }
}

/**
 * reader_sort_table
 *
 * @param xxx $table
 * @param xxx $columns
 * @param xxx $sortdirection
 * @param xxx $sortcolumn
 * @param array $dates (optional, default=null)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_sort_table(&$table, $columns, $sortdirection, $sortcolumn, $dates=null) {

    if (empty($table->data)) {
        return; // nothing to do
    }

    $columnnames = array_values($columns);
    $columnnames = array_flip($columnnames);
    // $columnnames maps column-name => column-number

    if ($sortcolumn) {
        if (array_key_exists($sortcolumn, $columnnames)) {
            $sortindex = $columnnames[$sortcolumn];
        } else {
            $sortindex = 0; // default is first column
        }

        $values = array();
        foreach ($table->data as $r => $row) {
            $values[$r] = strip_tags($row->cells[$sortindex]->text);
        }

        if (empty($sortdirection) || $sortdirection=='ASC') {
            asort($values);
        } else {
            arsort($values);
        }
    } else {
        // sorting not required, but we still want to format dates
        $values = range(0, count($table->data) - 1);
    }

    $data = array();
    foreach (array_keys($values) as $r) {
        if ($dates) {
            // format date columns - must be done after sorting
            foreach ($dates as $columnname => $fmt) {
                $c = $columnnames[$columnname];
                $date = $table->data[$r]->cells[$c]->text;
                $table->data[$r]->cells[$c]->text = date($fmt, $date);
            }
        }
        $data[] = $table->data[$r];
    }
    $table->data = $data;
}

/**
 * reader_print_group_select_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $gid
 * @param xxx $courseid
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_print_group_select_box($courseid, $link) {
    global $CFG, $COURSE, $gid;

    $groups = groups_get_all_groups ($courseid);

    if ($groups) {
        echo '<table style="width:100%"><tr><td align="right">';
        echo '<form action="" method="post" id="mform_gr">';
        echo '<select name="gid" id="id_gid">';
        echo '<option value="0">'.get_string('allgroups', 'mod_reader').'</option>';
        foreach ($groups as $groupid => $group) {
            if ($groupid == $gid) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            echo '<option value="'.$groupid.'"'.$selected.'>'.$group->name.'</option>';
        }
        echo '</select>';
        echo '<input type="submit" id="form_gr_submit" value="'.get_string('go').'" />';
        echo '</form>';
        echo '</td></tr></table>'."\n";

        // javascript to submit group form automatically and hide "Go" button
        echo '<script type="text/javascript">'."\n";
        echo "//<![CDATA[\n";
        echo "var obj = document.getElementById('id_gid');\n";
        echo "if (obj) {\n";
        echo "    obj.onchange = new Function('this.form.submit(); return true;');\n";
        echo "}\n";
        echo "var obj = document.getElementById('form_gr_submit');\n";
        echo "if (obj) {\n";
        echo "    obj.style.display = 'none';\n";
        echo "}\n";
        echo "obj = null;\n";
        echo "//]]>\n";
        echo "</script>\n";
    }
}

/**
 * reader_get_pages
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $table
 * @param xxx $page
 * @param xxx $perpage
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_pages($table, $page, $perpage) {
    global $CFG, $COURSE;

    $totalcount = count ($table);
    $startrec  = $page * $perpage;
    $finishrec = $startrec + $perpage;

    if (empty($table)) {
        $table = array();
    }
    $viewtable = array();
    foreach ($table as $key => $value) {
        if ($key >= $startrec && $key < $finishrec) {
            $viewtable[] = $value;
        }
    }

    return array($totalcount, $viewtable, $startrec, $finishrec, $page);
}

/**
 * reader_username_link
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @param xxx $courseid
 * @param xxx $nolink (optional, default = false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_username_link($userdata, $courseid, $nolink=false) {
    $username = $userdata->username;
    if ($nolink) {
        return $username; // e.g. for excel
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $userid, 'course' => $courseid);
    $params = array('href' => new moodle_url('/user/view.php', $params));
    return html_writer::tag('a', $username, $params);
}

/**
 * reader_fullname_link_viewasstudent
 *
 * @param xxx $userdata
 * @param xxx $id
 * @param xxx $nolink (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link_viewasstudent($userdata, $id, $nolink=false) {
    $fullname = $userdata->firstname.' '.$userdata->lastname;
    if ($nolink) {
        return $fullname;
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $id, 'userid' => $userid);
    $params = array('href' => new moodle_url('/mod/reader/view_loginas.php', $params));
    return html_writer::tag('a', $fullname, $params);
}

/**
 * reader_fullname_link
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @param xxx $courseid
 * @param xxx $nolink (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link($userdata, $courseid, $nolink=false) {
    $fullname = $userdata->firstname.' '.$userdata->lastname;
    if ($nolink) {
        return $fullname;
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $userid, 'course' => $courseid);
    $params = array('href' => new moodle_url('/user/view.php', $params));
    return html_writer::tag('a', $fullname, $params);
}

/**
 * reader_select_perpage
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $book
 * @param xxx $id
 * @param xxx $act
 * @param xxx $sort
 * @param xxx $orderby
 * @param xxx $gid
 * @todo Finish documenting this function
 */
function reader_select_perpage($id, $act, $sort, $orderby, $gid) {
    global $CFG, $COURSE, $_SESSION;

    echo '<table style="width:100%"><tr><td align="right">';

    $params = array('action' => new moodle_url('/mod/reader/admin.php'), 'method' => 'get', 'class' => 'popupform');
    echo html_writer::start_tag('form', $params);

    $params = array('a' => 'admin',  'id'  => $id,
                    'act'  => $act,  'gid' => $gid,
                    'sort' => $sort, 'orderby' => $orderby,
                    'book' => optional_param('book', '', PARAM_CLEAN));
    foreach ($params as $name => $value) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $name, 'value' => $value));
    }

    echo 'Perpage ';

    echo html_writer::start_tag('select', array('id' => 'id_perpage', 'name' => 'perpage'));

    $perpages = array(30, 60, 100, 200, 500);
    foreach ($perpages as $perpage) {

        $params = array('value' => $perpage);
        if ($_SESSION['SESSION']->reader_perpage == $perpage) {
            $params['selected'] = 'selected';
        }
        echo html_writer::tag('option', $perpage, $params);
    }

    echo html_writer::end_tag('select');

    $params = array('type' => 'submit', 'id' => 'id_perpage_submit', 'name' => 'perpage_submit', 'value' => get_string('go'));
    echo html_writer::empty_tag('input', $params);

    echo html_writer::end_tag('form');
    echo '</td></tr></table>';

    // javascript to submit perpage form automatically and hide "Go" button
    echo '<script type="text/javascript">'."\n";
    echo "//<![CDATA[\n";
    echo "var obj = document.getElementById('id_perpage');\n";
    echo "if (obj) {\n";
    echo "    obj.onchange = new Function('this.form.submit(); return true;');\n";
    echo "}\n";
    echo "var obj = document.getElementById('id_perpage_submit');\n";
    echo "if (obj) {\n";
    echo "    obj.style.display = 'none';\n";
    echo "}\n";
    echo "obj = null;\n";
    echo "//]]>\n";
    echo "</script>\n";
}

/**
 * reader_print_search_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $OUTPUT
 * @uses $_SESSION
 * @uses $book
 * @uses $searchtext
 * @param xxx $id
 * @param xxx $act
 * @todo Finish documenting this function
 */
function reader_print_search_form($id='', $act='', $book='') {
    global $OUTPUT;

    $id = optional_param('id', 0, PARAM_INT);
    $act = optional_param('act', null, PARAM_CLEAN);
    $book = optional_param('book', null, PARAM_CLEAN);
    $searchtext = optional_param('searchtext', null, PARAM_CLEAN);
    $searchtext = str_replace('\\"', '"', $searchtext);

    $output = '';

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'book' => $book);
    $action = new moodle_url('/mod/reader/admin.php', $params);

    $params = array('action' => $action, 'method' => 'post', 'id' => 'mform1');
    $output .= html_writer::start_tag('form', $params);

    $params = array('type' => 'text', 'name' => 'searchtext', 'value' => $searchtext, 'style' => 'width:120px;');
    $output .= html_writer::empty_tag('input', $params);

    $params = array('type' => 'submit', 'name' => 'submit', 'value' => get_string('search', 'mod_reader'));
    $output .= html_writer::empty_tag('input', $params);

    $output .= html_writer::end_tag('form');

    if ($searchtext) {
        $params = array('id' => $id, 'act' => $act);
        $output .= $OUTPUT->single_button(new moodle_url('/mod/reader/admin.php', $params), get_string('showall', 'mod_reader'), 'post', $params);
    }

    echo '<table style="width:100%"><tr><td align="right">'.$output.'</td></tr></table>';
}

/**
 * reader_check_search_text
 *
 * @param xxx $searchtext
 * @param xxx $coursestudent
 * @param xxx $book (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_check_search_text($searchtext, $coursestudent, $book=false) {

    $searchtext = trim($searchtext);
    if ($searchtext=='') {
        return true; // no search string, so everything matches
    }

    if (strstr($searchtext, '"')) {
        $texts = str_replace('\"', '"', $searchtext);
        $texts = explode('"', $searchtext);
    } else {
        $texts = explode(' ', $searchtext);
    }
    array_filter($texts); // remove blanks

    foreach ($texts as $text) {
        $text = strtolower($text);

        if ($coursestudent) {
            $username  = strtolower($coursestudent->username);
            $firstname = strtolower($coursestudent->firstname);
            $lastname  = strtolower($coursestudent->lastname);
            if (strstr($username, $text) || strstr("$firstname $lastname", $text)) {
                return true;
            }
        }

        if ($book) {
            if (is_array($book)) {
                $booktitle = strtolower($book['booktitle']);
                $booklevel = strtolower($book['booklevel']);
                $publisher = strtolower($book['publisher']);
            } else {
                $booktitle = strtolower($book->name);
                $booklevel = strtolower($book->level);
                $publisher = strtolower($book->publisher);
            }

            if (strpos($booktitle, $text)===false && strpos($booklevel, $text)==false || strpos($publisher, $text)==false) {
                // do nothing
            } else {
                return true;
            }
        }
    }

    return false; // no part of the searchtext matched user or book details
}

/**
 * reader_check_search_text_quiz
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @param xxx $searchtext
 * @param xxx $book
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_check_search_text_quiz($searchtext, $book) {

    $searchtext = trim($searchtext);
    if ($searchtext=='') {
        return true; // no search string, so everything matches
    }

    if (strstr($searchtext, '"')) {
        $texts = str_replace('\"', '"', $searchtext);
        $texts = explode('"', $searchtext);
    } else {
        $texts = explode(' ', $searchtext);
    }
    array_filter($texts); // remove blanks

    foreach ($texts as $text) {
        $text = strtolower($text);
        if ($book) {
            if (is_array($book)) {
                $booktitle = strtolower($book['booktitle']);
                $booklevel = strtolower($book['booklevel']);
                $publisher = strtolower($book['publisher']);
            } else {
                $booktitle = strtolower($book->name);
                $level     = strtolower($book->level);
                $publisher = strtolower($book->publisher);
            }

            if (strpos($booktitle, $text)===false && strpos($booklevel, $text)==false || strpos($publisher, $text)==false) {
                // do nothing
            } else {
                return true;
            }
        }
    }
    return false;
}

/**
 * reader_level_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $readerlevel
 * @param xxx $slevel
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_level_menu($userid, $readerlevel, $slevel) {
    global $id, $act, $gid, $sort, $orderby, $page;

    if (empty($readerlevel)) {
        $readerlevel = new stdClass();
    }
    if (! isset($readerlevel->$slevel)) {
        $readerlevel->$slevel = 0;
    }

    $values = range(0, 14);
    $name = 'level_'.$userid.'_'.$slevel;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'changelevel' => $value, 'userid' => $userid, 'slevel' => $slevel);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($value == $readerlevel->$slevel) {
            $params['selected'] = 'selected';
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_promotionstop_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $data
 * @param xxx $field
 * @param xxx $rand
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_promotionstop_menu($userid, $data, $field, $rand) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    if (empty($data)) {
        $data = new stdClass();
    }
    if (empty($data->$field)) {
        $data->$field = 0; // default
    }

    $values = array(0,1,2,3,4,5,6,7,8,9,10,12,99);
    $name = '_stoppr_'.$rand.'_'.$userid;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, $field => $value, 'userid' => $userid);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($value == $data->$field) {
            $params['selected'] = 'selected';
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_goals_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $studentlevel
 * @param xxx $field
 * @param xxx $rand
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_goals_menu($userid, $studentlevel, $field, $rand, $reader) {
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $goal = 0;

    if (! empty($studentlevel->goal)) {
        $goal = $studentlevel->goal;
    }

    if (empty($goal)) {
        $data = $DB->get_records('reader_goals', array('readerid' => $reader->id));
        foreach ($data as $data_) {
            $noneed = false;
            if (! empty($data_->groupid)) {
                if (! groups_is_member($data_->groupid, $userid)) {
                    $noneed = true;
                }
            }
            if (! empty($data_->level)) {
                if ($studentlevel->currentlevel != $data_->level) {
                    $noneed = true;
                }
            }
            if (! $noneed) {
                $goal = $data_->goal;
            }
        }
    }
    if (empty($goal) && !empty($reader->goal)) {
        $goal = $reader->goal;
    }

    if (isset($studentlevel->$field) && $studentlevel->$field) {
        $selectedvalue = $studentlevel->$field;
    } else {
        $selectedvalue = $goal;
    }

    if (empty($reader->wordsorpoints)) { // default = 0 = words, 1 = points
        $values = array(
            0,5000,6000,7000,8000,9000,
            10000,12500,15000,20000,25000,30000,35000,40000,45000,50000,60000,70000,80000,90000,
            100000,125000,150000,175000,200000,250000,300000,350000,400000,450000,500000
        );
        if (! empty($goal) && ! in_array($goal, $values)) {
            $temp = array();
            $i_max = count($values) - 1;
            for ($i=0; $i<=$i_max; $i++) {
                if ($i < $i_max && $goal < $values[$i+1] && $goal > $values[$i]) {
                    $temp[] = $goal;
                    $temp[] = $values[$i];
                } else {
                    $temp[] = $values[$i];
                }
            }
            $values = $temp;
        }
    } else {
        $values = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
    }

    $name = 'goal_'.$rand.'_'.$userid;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'set'.$field => $value, 'userid' => $userid);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($value==$selectedvalue) {
            $params['selected'] = "selected";
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_promo_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $data
 * @param xxx $field
 * @param xxx $rand
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_promo_menu($userid, $data, $field, $rand) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $values = array(0 => get_string('disallowpromotion', 'mod_reader'),
                    1 => get_string('allowpromotion', 'mod_reader'));
    $name = 'promo_'.$rand.'_'.$userid;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $key => $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, $field => $key, 'userid' => $userid);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($key == $data->$field) {
            $params['selected'] = "selected";
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_ip_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_ip_menu($userid, $reader) {
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $params = array('readerid' => $reader->id, 'userid' => $userid);
    if ($data = $DB->get_record('reader_strict_users_list', $params)) {
        $selectedvalue = $data->needtocheckip;
    } else {
        $selectedvalue = 0;
    }

    $values = array(0 => 'No', 1 => 'Yes');
    $name = 'selectip_'.$userid.'_'.$reader->id;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $key => $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'setip' => 1, 'userid' => $userid, 'needip' => $key);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($key == $selectedvalue) {
            $params['selected'] = "selected";
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_difficulty_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $difficulty
 * @param xxx $bookid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_difficulty_menu($difficulty, $bookid, $reader) {
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $values = array(0,1,2,3,4,5,6,7,8,9,10,12,13,14);
    $name = 'difficulty_'.$bookid.'_'.$difficulty;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'difficulty' => $value, 'bookid' => $bookid, 'slevel' => $difficulty);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($value == $difficulty) {
            $params['selected'] = "selected";
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_length_menu
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $length
 * @param xxx $bookid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_length_menu($length, $bookid, $reader) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $values = array(
        0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00,3.00,4.00,5.00,6.00,7.00,8.00,9.00,10.00,
        15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,110,120,130,140,150,160,170,175,180,190,200,225,250,275,300,350,400
    );

    $name = 'length_'.$bookid.'_'.$length;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'id_'.$name));

    $onchange = "request('admin.php?ajax=true&' + this.options[this.selectedIndex].value, 'id_$name'); return false;";
    $output .= html_writer::start_tag('select', array('id' => 'id_select_'.$name, 'name' => 'select_'.$name, 'onchange' => $onchange));

    foreach ($values as $value) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'length' => $value, 'bookid' => $bookid, 'slevel' => $length);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($value == $length) {
            $params['selected'] = "selected";
        }
        $output .= html_writer::tag('option', $value, $params);
    }

    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_ra_checkbox
 *
 * @uses $CFG
 * @uses $USER
 * @uses $act
 * @uses $excel
 * @uses $id
 * @param xxx $data
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_ra_checkbox($data) {
    global $act, $excel, $id;

    if ($excel) {
        return ($data['checkbox']==1 ? 'yes' : 'no');
    }

    $target_id = 'atcheck_'.$data['id'];
    $target_url = "'admin.php?'+'ajax=true&id=$id&act=$act&checkattempt=".$data['id']."&checkattemptvalue='+(this.checked ? 1 : 0)";

    $params = array('type'    => 'checkbox',
                    'name'    => 'checkattempt',
                    'value'   => '1',
                    'onclick' => "request($target_url,'$target_id')");

    if ($data['checkbox'] == 1) {
        $params['checked'] = 'checked';
    }

    // create checkbox INPUT element and target DIV
    return html_writer::empty_tag('input', $params).
           html_writer::tag('div', '', array('id' => $target_id));
}

/**
 * reader_groups_get_user_groups
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $userid (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_groups_get_user_groups($userid=0) {
    global $CFG, $DB, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $select = 'g.id, gg.groupingid';
    $from =   '{groups} g '.
              'JOIN {groups_members} gm ON gm.groupid = g.id '.
              'LEFT JOIN {groupings_groups} gg ON gg.groupid = g.id';
    $where  = 'gm.userid = ?';
    $params = array($userid);

    if (! $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
        return array('0' => array());
    }

    $result    = array();
    $allgroups = array();

    foreach ($rs as $group) {
        $allgroups[$group->id] = $group->id;
        if (is_null($group->groupingid)) {
            continue;
        }
        if (! array_key_exists($group->groupingid, $result)) {
            $result[$group->groupingid] = array();
        }
        $result[$group->groupingid][$group->id] = $group->id;
    }
    $rs->close();

    $result['0'] = array_keys($allgroups); // all groups

    return $result;
}

////////////////////////////////////////////////////////////////////////////////
// Reader menu class
////////////////////////////////////////////////////////////////////////////////

/**
 * reader_menu
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_menu {
    protected $sections = array();

    /**
     * __construct
     *
     * @param xxx $sections
     * @todo Finish documenting this function
     */
    public function __construct($sections) {
        $this->sections = $sections;
    }

    /**
     * out
     *
     * @param xxx $context
     * @return xxx
     * @todo Finish documenting this function
     */
    public function out($context) {
        $out = ''; // '<h3>'.get_string('menu', 'mod_reader').':</h3>';
        $started_sections = false;
        foreach ($this->sections as $sectionname => $items) {
            $started_items = false;
            foreach ($items as $item) {
                if ($itemtext = $item->out($context)) {
                    if ($started_sections==false) {
                        $started_sections = true;
                        $out .= '<ul class="readermenusections">';
                    }
                    if ($started_items==false) {
                        $started_items = true;
                        $out .= '<li class="readermenusection"><b>'.get_string($sectionname, 'mod_reader').'</b><ul class="readermenuitems">';
                    }
                    $out .= '<li class="readermenuitem">'.$itemtext.'</li>';
                }
            }
            if ($started_items) {
                $out .= '</ul></li>';
            }
        }
        if ($started_sections) {
            $out .= '</ul>';
            $out = '<div class="readermenu">'.$out.'</div>';
            $out .= '<div style="clear:both;"></div>';
        }
        return $out;
    }
}

/**
 * reader_menu_item
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_menu_item {

    protected $displaystring = '';
    protected $capability    = '';
    protected $scriptname    = '';
    protected $scriptparams  = array();
    protected $fullme        = null;

    /**
     * __construct
     *
     * @param xxx $displaystring
     * @param xxx $capability
     * @param xxx $scriptname
     * @param xxx $scriptparams
     * @todo Finish documenting this function
     */
    public function __construct($displaystring, $capability, $scriptname, $scriptparams) {
        $this->displaystring = $displaystring;
        $this->capability    = $capability;
        $this->scriptname    = $scriptname;
        $this->scriptparams  = $scriptparams;
    }

    /**
     * get_fullme
     *
     * @uses $FULLME
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function get_fullme() {
        global $FULLME;
        if (is_null($this->fullme)) {
            $strpos = strpos($FULLME, '?');
            if ($strpos===false) {
                $strpos = strlen($FULLME);
            }
            $url = substr($FULLME, 0, $strpos);
            $values = substr($FULLME, $strpos + 1);
            $values = explode('&', $values);
            $values = array_filter($values); // remove blanks
            $params = array();
            foreach ($values as $value) {
                if (strpos($value, '=')==false) {
                    continue;
                }
                list($name, $value) = explode('=', $value, 2);
                $params[$name] = $value;
            }
            $this->fullme = new moodle_url($url, $params);
        }
        return $this->fullme;
    }

    /**
     * out
     *
     * @param xxx $context
     * @return xxx
     * @todo Finish documenting this function
     */
    public function out($context) {
        $out = '';
        if (has_capability('mod/reader:'.$this->capability, $context)) {
            $out = get_string($this->displaystring, 'mod_reader');
            $url = new moodle_url('/mod/reader/'.$this->scriptname, $this->scriptparams);
            if ($url->compare($this->get_fullme(), URL_MATCH_PARAMS)) {
                // current page - do not convert to link
            } else {
                $out = '<a href="'.$url.'">'.$out.'</a>';
            }
        }
        return $out;
    }
}
