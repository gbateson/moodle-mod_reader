<?php

require_once($CFG->dirroot.'/mod/reader/lib.php');

class reader_downloader {

    /** values for download $type */
    const BOOKS_WITH_QUIZZES    = 1;
    const BOOKS_WITHOUT_QUIZZES = 0;

    public $remotesites = array();

    public $downloaded = array();

    public $available = array();

    public function __construct() {
    }

    public function get_book_table($type) {
        switch ($type) {
            case self::BOOKS_WITH_QUIZZES: return 'reader_books';
            case self::BOOKS_WITHOUT_QUIZZES: return 'reader_noquiz';
        }
        return ''; // shouldn't happen !!
    }

    public function get_downloaded_items($type, $r=0) {
        global $DB;

        $this->downloaded[$r] = new reader_items();

        $booktable = $this->get_book_table($type);
        if ($records = $DB->get_records($booktable)) {
            foreach ($records as $record) {

                $publisher = $record->publisher;
                $level     = $record->level;
                $itemname  = $record->name;

                if (! isset($this->downloaded[$r]->items[$publisher])) {
                    $this->downloaded[$r]->items[$publisher] = new reader_items();
                }
                if (! isset($this->downloaded[$r]->items[$publisher]->items[$level])) {
                    $this->downloaded[$r]->items[$publisher]->items[$level] = new reader_items();
                }
                $this->downloaded[$r]->items[$publisher]->items[$level]->items[$itemname] = true;
            }
        }
    }

    public function add_remotesite($remotesite) {
        $this->remotesites[] = $remotesite;
    }

    public function add_available_items($type, $itemids) {
        foreach ($this->remotesites as $r => $remotesite) {
            $this->available[$r] = $remotesite->get_available_items($type, $itemids, $this->downloaded[$r]);
        }
    }

    public function check_selected_itemids($publishers, $levels, &$itemids) {
        if (count($publishers)==0 && count($levels)==0) {
            return false; // nothing to do
        }
        foreach ($this->available as $r => $available) {
            $i = 0;
            foreach ($available->items as $publishername => $levels) {
                $i++;

                $ii = 0;
                foreach ($levels->items as $levelname => $items) {
                    $ii++;

                    if (in_array($i, $publishers) || in_array($i.'_'.$ii, $levels)) {
                        foreach ($items->items as $itemname => $itemid) {
                            if (! in_array($itemid, $itemids)) {
                                $itemids[] = $itemid;
                            }
                        }
                    }
                }
            }
        }
    }

    public function add_selected_itemids($type, $itemids, $r=0) {
        global $DB, $OUTPUT;

        if (empty($itemids)) {
            return false; // nothing to do
        }

        $remotesite = $this->remotesites[$r];
        if (! $xml = $remotesite->download_quizzes($type, $itemids)) {
            return false; // shouldn't happen !!
        }

        $output = '';
        $started_list = false;
        foreach ($xml['myxml']['#']['item'] as $i => $item) {

            // set up default values for record from $tablename
            $record = (object)array(
                'publisher'  => '',
                'series'     => '',
                'level'      => '',
                'difficulty' => 0,
                'name'       => '',
                'words'      => 0,
                'genre'      => '',
                'fiction'    => '',
                'image'      => '',
                'length'     => '',
                'private'    => 0,
                'sametitle'  => '',
                'hidden'     => 0,
                'maxtime'    => 0,
            );

            // transfer values from this $item to this $record
            $fields = get_object_vars($record);
            foreach ($fields as $field => $defaultvalue) {
                if (isset($item['@'][$field])) {
                    $value = $item['@'][$field];
                } else if ($field=='name') {
                    $value = $item['@']['title'];
                } else {
                    $value = $defaultvalue;
                }
                $record->$field = $value;
            }
            $record->quizid = 0; // ignore any incoming "quizid" values

            // initialize array to hold messages
            $msg = array();

            // add or update the $DB information
            $params = array('publisher' => $record->publisher,
                            'level'     => $record->level,
                            'name'      => $record->name);
            if ($record->id = $DB->get_field($tablename, 'id', $params)) {
                $DB->update_record($tablename, $record);
                $msg[] = 'Book data was updated: '.$record->name;
            } else {
                unset($record->id);
                $record->id = $DB->insert_record($tablename, $record);
                $msg[] = 'Book data was added: '.$record->name;
            }

            // download associated image (i.e. book cover)
            $this->download_image($itemid, $record->image, $r);
            //$msg[] = 'Book image was dowloaded: '.$record->image;

            if (count($msg)) {
                if ($started_list==false) {
                    $started_list = true;
                    $output .= html_writer::start_tag('div');
                    $output .= html_writer::start_tag('ul');
                }
                $output .= html_writer::tag('li', implode('</li><li>', $msg));;
            }

            if (! isset($this->downloaded[$r]->items[$record->publisher])) {
                $this->downloaded[$r]->items[$publisher] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$record->publisher]->items[$record->level])) {
                $this->downloaded[$r]->items[$record->publisher]->items[$record->level] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$record->publisher]->items[$record->level]->items[$record->name])) {
                $this->downloaded[$r]->items[$record->publisher]->items[$record->level]->items[$record->name] = true;
                $this->available[$r]->items[$record->publisher]->items[$record->level]->newcount--;
                $this->available[$r]->items[$record->publisher]->newcount--;
                $this->available[$r]->newcount--;
            }
        }

        if ($started_list==true) {
            $output .= html_writer::end_tag('ul');
            $output .= html_writer::end_tag('div');
        }

        if ($output) {
            echo $OUTPUT->box($output, 'generalbox', 'notice');
        }
    }

    public function download_image($itemid, $filename, $r=0) {
        global $CFG;
        make_upload_directory('reader/images');

        $remotesite = $this->remotesites[$r];
        $url = $remotesite->get_image_url($type, $itemid);

        if ($contents = @file_get_contents($url)) {
            if ($fp = @fopen($CFG->dataroot.'/reader/images/'.$filename, 'w+')) {
                @fwrite($fp, $contents);
                @fclose($fp);
            }
        }
    }
}

class reader_remotesite {

    /** the default download url for this remote site */
    const DEFAULT_URL = '';

    /** the basic connection parameters */
    public $baseurl = '';
    public $username = '';
    public $password = '';

    public function __construct($baseurl='', $username='', $password='') {
        $this->baseurl = ($baseurl ? $baseurl : self::DEFAULT_URL);
        $this->username = $username;
        $this->password = $password;
    }

    public function download_xml($url, $post=false) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/xmlize.php');
        if ($xml = $this->get_curlfile($url, $post)) {
            return xmlize($xml);
        }
        return false; // shouldn't happen !!
    }

    public function download_publishers($type, $itemids) {
        $url = $this->get_publishers_url($type, $itemids);
        return $this->download_xml($url);
    }

    public function get_publishers_url($type, $itemids) {
        return $this->baseurl;
    }

    public function get_publishers_params($type, $itemids) {
        return null;
    }

    public function download_quizzes($type, $itemids) {
        $url = $this->get_quizzes_url($type, $itemids);
        return $this->download_xml($url);
    }

    public function get_quizzes_url($type, $itemids) {
        return $this->baseurl;
    }

    public function get_quizzes_params($type, $itemids) {
        return null;
    }

    public function get_image_url($type, $itemid) {
        return $this->baseurl;
    }

    public function get_image_params($itemid) {
        return null;
    }

    public function get_curlfile($url, $post=false) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($post) {
            $postfields = array();
            foreach ($post as $key1 => $value1) {
                if (is_array($value1)) {
                    foreach ($value1 as $key2 => $value2) {
                        if (is_array($value2)) {
                            foreach ($value2 as $key3 => $value3) {
                                $postfields[] = $key1.'['.$key2.']['.$key3.']='.$value3;
                            }
                        } else {
                            $postfields[] = $key1.'['.$key2.']='.$value2;
                        }
                    }
                } else {
                    $postfields[] = $key1.'='.$value1;
                }
            }
            if ($postfields = implode('&', $postfields)) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            }
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false; // shouldn't happen !!
        }

        return $result;
    }
}

class reader_remotesite_moodlereadernet extends reader_remotesite {
    const DEFAULT_URL = 'http://moodlereader.net/quizbank';

    public function get_publishers_url($type, $itemids) {
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_publishers_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_publishers_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index-noq.php', $params);
        }
        return parent::get_publishers_url($type, $itemids); // shouldn't happen !!
    }

    public function get_publishers_params($type, $itemids) {
        return array('a' => 'publishers', 'login' => $this->username, 'password' => $this->password);
    }

    public function get_quizzes_url($type, $itemids) {
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index-noq.php', $params);
        }
        return parent::get_quizzes_url($type, $itemids); // shouldn't happen !!
    }

    public function get_quizzes_params($type, $itemids) {
        return array('a' => 'quizzes', 'login' => $this->username, 'password' => $this->password);
    }

    public function get_image_url($type, $itemid) {
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemid);
            return new moodle_url($this->baseurl.'/getfile.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemid);
            return new moodle_url($this->baseurl.'/getfilenoq.php', $params);
        }
        return parent::get_image_url($type, $itemid); // shouldn't happen !!
    }

    public function get_image_params($itemid) {
        return array('imageid' => $itemid);
    }

    public function get_available_items($type, $itemids, $downloaded) {
        $items = $this->download_publishers($type, $itemids);

        $available = new reader_download_items();
        foreach ($items['myxml']['#']['item'] as $item) {

            $publisher = $item['@']['publisher'];
            $needpass  = $item['@']['needpass'];
            $level     = $item['@']['level'];
            $itemid    = $item['@']['id'];
            $itemname  = $item['#'];

            if ($publisher=='Extra_Points' || $publisher=='testing' || $publisher=='_testing_only') {
                continue; // ignore these publisher categories
            }

            if (! isset($available->items[$publisher])) {
                $available->items[$publisher] = new reader_download_items();
            }
            if (! isset($available->items[$publisher]->items[$level])) {
                $available->items[$publisher]->items[$level] = new reader_download_items();
            }

            if ($needpass=='true') {
                $available->items[$publisher]->items[$level]->needpassword = true;
            }

            $available->count++;
            $available->items[$publisher]->count++;
            $available->items[$publisher]->items[$level]->count++;

            if (empty($downloaded->items[$publisher]->items[$level]->items[$itemname])) {
                $available->newcount++;
                $available->items[$publisher]->newcount++;
                $available->items[$publisher]->items[$level]->newcount++;
            }

            $available->items[$publisher]->items[$level]->items[$itemname] = $itemid;
        }

        // sort items by name
        ksort($available->items);
        $publishers = array_keys($available->items);
        foreach ($publishers as $publisher) {
            uksort($available->items[$publisher]->items, array($this, 'sort_by_name'));
            $levels = array_keys($available->items[$publisher]->items);
            foreach ($levels as $level) {
                ksort($available->items[$publisher]->items[$level]->items);
            }
        }

        return $available;
    }

    function sort_by_name($a, $b) {

        // search and replace strings
        $search = array('/\b(-+|Level|Stage)/', '/\bEasyStart$/', '/\bStarter$/', '/\bBeginner$/', '/\bElementary$/', '/\bPre-Intermediate$/', '/\bUpper-Intermediate$/', '/Booksworms/');
        $replace = array('', 0, 0, 1, 2, 3, 5, 'Bookworms');
        $split = '/^(.*?)([0-9]+)$/';

        // get filtered name (a)
        $aname = preg_replace($search, $replace, $a);
        if (preg_match($split, $aname, $matches)) {
            $aname = trim($matches[1]);
            $anum  = intval($matches[2]);
        } else {
            $anum = 0;
        }

        // get filtered name (b)
        $bname = preg_replace($search, $replace, $b);
        if (preg_match($split, $bname, $matches)) {
            $bname = trim($matches[1]);
            $bnum  = intval($matches[2]);
        } else {
            $bnum = 0;
        }

        // deal with empty names always go last
        if ($aname || $bname) {
            if ($aname=='') {
                return -1;
            }
            if ($bname=='') {
                return 1;
            }
            if ($aname < $bname) {
                return -1;
            }
            if ($aname > $bname) {
                return 1;
            }
        }

        // compare level/stage/word numbers
        if ($anum < $bnum) {
            return -1;
        }
        if ($anum > $bnum) {
            return 1;
        }

        // same name && same level/stage/word number
        return 0;
    }
}

class reader_items {
    public $count = 0;
    public $items = array();
}

class reader_download_items extends reader_items {
    public $newcount = 0;
    public $needpassword = false;
}
