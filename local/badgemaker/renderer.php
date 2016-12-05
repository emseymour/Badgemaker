<?php

/**
 * @package    Badgemaker
 * @copyright  2016 We Are Snook <code@wearesnook.com>
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 */

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . "/badges/renderer.php");

/**
 * Standard HTML output renderer for badges
 */
class badgemaker_renderer extends core_badges_renderer {

    // A combo of render_badge_user_collection and the table from render_badge_management
    // Search box is moved above heading so it is obvious it is for both tables in the badge library.
    protected function render_badge_user_collection(badge_user_collection $badges)
    {
        global $CFG, $USER, $SITE;
        $paging = new paging_bar($badges->totalcount, $badges->page, $badges->perpage, $this->page->url, 'page');
        $backpack = $badges->backpack;
        $mybackpack = new moodle_url('/badges/mybackpack.php');
        $htmlpagingbar = $this->render($paging);

        // Set backpack connection string.
        $backpackconnect = '';
        if (!empty($CFG->badges_allowexternalbackpack) && is_null($backpack)) {
            $backpackconnect = $this->output->box(get_string('localconnectto', 'badges', $mybackpack->out()), 'noticebox');
        }
        // Search box.
        $searchform = $this->output->box($this->helper_search_form($badges->search), 'boxwidthwide boxaligncenter');

        // Download all button.
        $downloadall = $this->output->single_button(
            new moodle_url('/badges/mybadges.php', array('downloadall' => true, 'sesskey' => sesskey())),
            get_string('downloadall'), 'POST', array('class' => 'activatebadge'));

        // Local badges.
        //$localhtml = html_writer::start_tag('div', array('id' => 'issued-badge-table', 'class' => 'generalbox'));
        $heading = get_string('localbadges', 'badges', format_string($SITE->fullname, true, array('context' => context_system::instance())));
        $localhtml = $searchform;

        $localhtml .= html_writer::start_tag('div', array('id' => 'issued-badge-table', 'class' => 'generalbox'));
        $localhtml .= $this->output->heading_with_help($heading, 'localbadgesh', 'badges');

        if ($badges->badges) {
            $downloadbutton = $this->output->heading(get_string('badgesearned', 'badges', $badges->totalcount), 4, 'activatebadge');
            $downloadbutton .= $downloadall;

            // Table
            $table = new html_table();
            $table->attributes['class'] = 'collection';

            $sortbyname = $this->helper_sortable_heading(get_string('name'),
                'name', $badges->sort, $badges->dir);
            $sortbystatus = $this->helper_sortable_heading(get_string('status', 'badges'),
                'status', $badges->sort, $badges->dir);
            $table->head = array(
                $sortbyname,
                get_string('status', 'badges'),
                // MH $sortbystatus,
                // MH get_string('bcriteria', 'badges'),
                // MHget_string('awards', 'badges')
                // MH get_string('actions')
            );

            $table->colclasses = array('name', 'status'); // MH $table->colclasses = array('name', 'status', 'criteria', 'awards', 'actions');
            // MH
            $table->colclasses[] = 'course';
            $table->head[] = get_string('course', 'moodle');
            $table->colclasses[] = 'dateEarned';
            $table->head[] = 'Date earned';//get_string('awards', 'badges');
            if($this->has_any_action_capability()){
                $table->head[] = get_string('actions');
                $table->colclasses[] = get_string('actions');
            }

            foreach ($badges->badges as $b) {
                $style = array(); // MH $style = !$b->is_active() ? array('class' => 'dimmed') : array();

                // MH
                $context = $this->page->context;
                if($b->type == BADGE_TYPE_COURSE){
                    $context = context_course::instance($b->courseid);
                }
                //var_export($b);
                $forlink =  print_badge_image($b, $context) . ' ' . // MH $forlink =  print_badge_image($b, $this->page->context) . ' ' .
                    html_writer::start_tag('span') . $b->name . html_writer::end_tag('span');
                $name = html_writer::link(new moodle_url('/badges/overview.php', array('id' => $b->id)), $forlink, $style);
                $status = 'Earned';// MH $b->statstring;

                if($b->type == BADGE_TYPE_SITE) {
                    $course = "N/A";
                }else{
                    $course = $b->courseFullname; // MH $criteria = self::print_badge_criteria($b, 'short');
                }

                $icon = new pix_icon('i/valid',
                    get_string('dateearned', 'badges',
                        userdate($b->dateissued, get_string('strftimedatefullshort', 'core_langconfig'))));
                $badgeurl = new moodle_url('/badges/badge.php', array('hash' => $b->uniquehash));
                $awarded = $this->output->action_icon($badgeurl, $icon, null, null, true);

                $row = array($name, $status); // MH $row = array($name, $status, $criteria, $awards, $actions);

                // MH
                $row[] = $course;
                $row[] = $awarded;


                $download = $status = $push = '';
                //if (($userid == $USER->id) && !$profile) {
                $url = new moodle_url('/local/badgemaker/badge_library.php', array('download' => $b->id, 'hash' => $b->uniquehash, 'sesskey' => sesskey())); // MH URL changed to badge library
                $notexpiredbadge = (empty($b->dateexpire) || $b->dateexpire > time());
                $backpackexists = badges_user_has_backpack($USER->id);
                if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                    $assertion = new moodle_url('/badges/assertion.php', array('b' => $b->uniquehash));
                    $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                    $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
                }

                $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
                if ($b->visible) {
                    $url = new moodle_url('/local/badgemaker/badge_library.php', array('hide' => $b->issuedid, 'sesskey' => sesskey())); // MH URL changed to badge_library
                    $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
                } else {
                    $url = new moodle_url('/local/badgemaker/badge_library.php', array('show' => $b->issuedid, 'sesskey' => sesskey()));  // MH URL changed to badge_library
                    $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
                }
                //}
                $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
                //$items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
                //$actions = self::print_badge_table_actions($b, $this->page->context);
                $row[] = $actions;


                $table->data[] = $row;
            }
            $htmltable = html_writer::table($table);

            // MH $htmllist = $this->print_badges_list_with_date($badges->badges, $USER->id);
            $localhtml .= $backpackconnect . $downloadbutton  . $htmlpagingbar . $htmltable . $htmlpagingbar;
        } else {
            $localhtml .= $this->output->notification(get_string('nobadges', 'badges'));
        }
        $localhtml .= html_writer::end_tag('div');

        return $localhtml;//$htmlpagingbar . $localhtml . $htmlpagingbar;
    }
// returns true if has any action, used to display the actions and status column in the badge library table.
    function has_any_action_capability()
    {
        global $PAGE;
        return has_any_capability(array(
            'moodle/badges:viewawarded',
            'moodle/badges:createbadge',
            'moodle/badges:awardbadge',
            'moodle/badges:configuremessages',
            'moodle/badges:configuredetails',
            'moodle/badges:deletebadge'), $PAGE->context);
    }

    // Copied from badges renderer and following modifications made:
    //  - Criteria changed for course.
    //  - actions column shown if has any action capability.
    //  - Badge status column shown if has any action capability.
    protected function render_badge_management(badge_management $badges) {
        $paging = new paging_bar($badges->totalcount, $badges->page, $badges->perpage, $this->page->url, 'page');

        // New badge button.
        $htmlnew = '';
        if (has_capability('moodle/badges:createbadge', $this->page->context)) {
            $n['type'] = $this->page->url->get_param('type');
            $n['id'] = $this->page->url->get_param('id');
            $htmlnew = $this->output->single_button(new moodle_url('/badges/newbadge.php', $n), get_string('add_new_site_badge', 'local_badgemaker')); // MH /badges/ put in URL
        }

        $htmlpagingbar = $this->render($paging);
        $table = new html_table();
        $table->attributes['class'] = 'collection';

        $sortbyname = $this->helper_sortable_heading(get_string('name'),
            'name', $badges->sort, $badges->dir);
        $sortbystatus = $this->helper_sortable_heading(get_string('status', 'badges'),
            'status', $badges->sort, $badges->dir);
        $table->head = array(
            $sortbyname,
            // MH $sortbystatus,
            // MH get_string('bcriteria', 'badges'),
            // MHget_string('awards', 'badges')
            // MH get_string('actions')
        );

        $table->colclasses = array('name'); // MH $table->colclasses = array('name', 'status', 'criteria', 'awards', 'actions');
        // MH
        if (has_capability('moodle/badges:createbadge', $this->page->context)) {
            $table->colclasses[] = 'status';
            $table->head[] = $sortbystatus;
        }
        $table->colclasses[] = 'course';
        $table->head[] = get_string('course', 'moodle');
        $table->colclasses[] = 'awards';
        $table->head[] = get_string('awards', 'badges');
        if($this->has_any_action_capability()){
            $table->head[] = get_string('actions');
            $table->colclasses[] = get_string('actions');
        }

        foreach ($badges->badges as $b) {
            $style = !$b->is_active() ? array('class' => 'dimmed') : array();

            // MH
            $context = $this->page->context;
            if($b->type == BADGE_TYPE_COURSE){
                $context = context_course::instance($b->courseid);
            }

            $forlink =  print_badge_image($b, $context) . ' ' . // MH $forlink =  print_badge_image($b, $this->page->context) . ' ' .
                html_writer::start_tag('span') . $b->name . html_writer::end_tag('span');
            $name = html_writer::link(new moodle_url('/badges/overview.php', array('id' => $b->id)), $forlink, $style);
            $status = $b->statstring;

            if($b->type == BADGE_TYPE_SITE) {
                $course = "N/A";
            }else{
                $course = $b->courseFullname; // MH $criteria = self::print_badge_criteria($b, 'short');
            }

            if ($this->has_any_action_capability()) {
                $awards = html_writer::link(new moodle_url('/badges/recipients.php', array('id' => $b->id)), $b->awards);
            } else {
                $awards = $b->awards;
            }

            $row = array($name); // MH $row = array($name, $status, $criteria, $awards, $actions);

            // MH
            if ($this->has_any_action_capability()) {
                $row[] = $status;
            }
            $row[] = $course;
            $row[] = $awards;
            if($this->has_any_action_capability()){
                $actions = self::print_badge_table_actions($b, $this->page->context);
                $row[] = $actions;
            }

            $table->data[] = $row;
        }
        $htmltable = html_writer::table($table);

        return $htmlnew . $htmlpagingbar . $htmltable . $htmlpagingbar;
    }

    public function print_combined_overview_list($earnedBadges, $earnableBadges) {
      global $USER, $CFG;
      $badges = array();
      foreach ($earnedBadges as $eb) {
        $badges[] = $eb;
      }
      foreach($earnableBadges as $eb) {
        $badges[] = $eb;
      }
      foreach ($badges as $badge) {
          $earnedThisOne = in_array($badge, $earnedBadges);
          $imageClass = $earnedThisOne ? 'small-badge-icon' : 'ghosted-small-badge-icon';
          $textClass = $earnedThisOne ? 'badge-name' : 'ghosted-badge-name';
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }

          $name = html_writer::tag('span', $bname, array('class' => $textClass));

          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => $imageClass, 'height' => $badgesize, 'width' => $badgesize));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          if ($earnedThisOne) {
            if (($userid == $USER->id) && !$profile) {
                $url = new moodle_url('mybadges.php', array('download' => $badge->id, 'hash' => $badge->uniquehash, 'sesskey' => sesskey()));
                $notexpiredbadge = (empty($badge->dateexpire) || $badge->dateexpire > time());
                $backpackexists = badges_user_has_backpack($USER->id);
                if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                    $assertion = new moodle_url('/badges/assertion.php', array('b' => $badge->uniquehash));
                    $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                    $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
                }

                $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
                if ($badge->visible) {
                    $url = new moodle_url('mybadges.php', array('hide' => $badge->issuedid, 'sesskey' => sesskey()));
                    $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
                } else {
                    $url = new moodle_url('mybadges.php', array('show' => $badge->issuedid, 'sesskey' => sesskey()));
                    $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
                }
            }

            if (!$profile) {
                $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
            } else {
                if (!$external) {
                    $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
                } else {
                    $hash = hash('md5', $badge->hostedUrl);
                    $url = new moodle_url('/badges/external.php', array('hash' => $hash, 'user' => $userid));
                }
            }
          } else {
            $url = new moodle_url('/badges/overview.php', array('id' => $badge->id));
          }

          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    public function print_small_awarded_list($badges, $badgesize = 40) {
      global $USER, $CFG;
      foreach ($badges as $badge) {
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }

          $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));

          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'small-badge-image', 'height' => $badgesize, 'width' => $badgesize));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          if (($userid == $USER->id) && !$profile) {
              $url = new moodle_url('mybadges.php', array('download' => $badge->id, 'hash' => $badge->uniquehash, 'sesskey' => sesskey()));
              $notexpiredbadge = (empty($badge->dateexpire) || $badge->dateexpire > time());
              $backpackexists = badges_user_has_backpack($USER->id);
              if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                  $assertion = new moodle_url('/badges/assertion.php', array('b' => $badge->uniquehash));
                  $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                  $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
              }

              $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
              if ($badge->visible) {
                  $url = new moodle_url('mybadges.php', array('hide' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
              } else {
                  $url = new moodle_url('mybadges.php', array('show' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
              }
          }

          if (!$profile) {
              $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
          } else {
              if (!$external) {
                  $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
              } else {
                  $hash = hash('md5', $badge->hostedUrl);
                  $url = new moodle_url('/badges/external.php', array('hash' => $hash, 'user' => $userid));
              }
          }
          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    public function print_small_to_earn_list($badges, $badgesize = 40) {
      global $USER, $CFG;
      foreach ($badges as $badge) {
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }

          $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));
          // var_dump($badge);die();
          // $di = $badge->dateissued;

          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'small-badge-icon', 'width' => $badgesize, 'height' => $badgesize));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          $url = new moodle_url('/badges/overview.php', array('id' => $badge->id));

          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    public function print_meta_badges_list($badges) {
      global $USER, $CFG;
      foreach ($badges as $badge) {
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }

          $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));
          // var_dump($badge);die();
          // $di = $badge->dateissued;

          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image'));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          $url = new moodle_url('/badges/overview.php', array('id' => $badge->id));

          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    public function recent_course_badges_list($badges) {
      global $USER, $CFG;
      foreach ($badges as $badge) {
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }
          $di = $badge->dateissued;
          $bname .= ' to '.html_writer::start_span('bold').$badge->firstname.' '.$badge->lastname.html_writer::end_span().' on '.userdate($di, '%d/%m/%y');
          $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));

          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image'));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          if (($userid == $USER->id) && !$profile) {
              $url = new moodle_url('mybadges.php', array('download' => $badge->id, 'hash' => $badge->uniquehash, 'sesskey' => sesskey()));
              $notexpiredbadge = (empty($badge->dateexpire) || $badge->dateexpire > time());
              $backpackexists = badges_user_has_backpack($USER->id);
              if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                  $assertion = new moodle_url('/badges/assertion.php', array('b' => $badge->uniquehash));
                  $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                  $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
              }

              $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
              if ($badge->visible) {
                  $url = new moodle_url('mybadges.php', array('hide' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
              } else {
                  $url = new moodle_url('mybadges.php', array('show' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
              }
          }

          if (!$profile) {
              $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
          } else {
              if (!$external) {
                  $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
              } else {
                  $hash = hash('md5', $badge->hostedUrl);
                  $url = new moodle_url('/badges/external.php', array('hash' => $hash, 'user' => $userid));
              }
          }
          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    public function awarded_course_badges_list($badges) {
      global $USER, $CFG;
      foreach ($badges as $badge) {
          if (!$external) {
              $context = ($badge->type == BADGE_TYPE_SITE) ? context_system::instance() : context_course::instance($badge->courseid);
              $bname = $badge->name;
              $imageurl = moodle_url::make_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/', 'f1', false);
          } else {
              $bname = s($badge->assertion->badge->name);
              $imageurl = $badge->imageUrl;
          }

          $di = $badge->dateissued;
          $bname .= ' on '.userdate($di, '%d/%m/%y');

          $name = html_writer::tag('span', $bname, array('class' => 'badge-name'));
          // var_dump($badge);die();


          $image = html_writer::empty_tag('img', array('src' => $imageurl, 'class' => 'badge-image'));
          if (!empty($badge->dateexpire) && $badge->dateexpire < time()) {
              $image .= $this->output->pix_icon('i/expired',
                      get_string('expireddate', 'badges', userdate($badge->dateexpire)),
                      'moodle',
                      array('class' => 'expireimage'));
              $name .= '(' . get_string('expired', 'badges') . ')';
          }

          $download = $status = $push = '';
          if (($userid == $USER->id) && !$profile) {
              $url = new moodle_url('mybadges.php', array('download' => $badge->id, 'hash' => $badge->uniquehash, 'sesskey' => sesskey()));
              $notexpiredbadge = (empty($badge->dateexpire) || $badge->dateexpire > time());
              $backpackexists = badges_user_has_backpack($USER->id);
              if (!empty($CFG->badges_allowexternalbackpack) && $notexpiredbadge && $backpackexists) {
                  $assertion = new moodle_url('/badges/assertion.php', array('b' => $badge->uniquehash));
                  $action = new component_action('click', 'addtobackpack', array('assertion' => $assertion->out(false)));
                  $push = $this->output->action_icon(new moodle_url('#'), new pix_icon('t/backpack', get_string('addtobackpack', 'badges')), $action);
              }

              $download = $this->output->action_icon($url, new pix_icon('t/download', get_string('download')));
              if ($badge->visible) {
                  $url = new moodle_url('mybadges.php', array('hide' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/hide', get_string('makeprivate', 'badges')));
              } else {
                  $url = new moodle_url('mybadges.php', array('show' => $badge->issuedid, 'sesskey' => sesskey()));
                  $status = $this->output->action_icon($url, new pix_icon('t/show', get_string('makepublic', 'badges')));
              }
          }

          if (!$profile) {
              $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
          } else {
              if (!$external) {
                  $url = new moodle_url('/badges/badge.php', array('hash' => $badge->uniquehash));
              } else {
                  $hash = hash('md5', $badge->hostedUrl);
                  $url = new moodle_url('/badges/external.php', array('hash' => $hash, 'user' => $userid));
              }
          }
          $actions = html_writer::tag('div', $push . $download . $status, array('class' => 'badge-actions'));
          $items[] = html_writer::link($url, $image . $actions . $name, array('title' => $bname));
      }

      return html_writer::alist($items, array('class' => 'badges'));
    }

    // Copied from badges renderer and following modifications made:
    // A table instead of the regular badge layout.
    // Has the issued date as seen on the site badges page.
//    protected function render_badge_user_collection(badge_user_collection $badges) {
//        var_export($badges);
//    }
}