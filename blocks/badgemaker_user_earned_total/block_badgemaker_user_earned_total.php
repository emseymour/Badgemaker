<?php

/**
 * @package    Badgemaker
 * @copyright  2016 We Are Snook <code@wearesnook.com>
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html GNU GPLv3
 */

global $CFG;

require_once($CFG->libdir . "/badgeslib.php");

class block_badgemaker_user_earned_total extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_badgemaker_user_earned_total');
    }

    public function specialization() {
        if (empty($this->config->title)) {
            $this->title = get_string('user_earned', 'block_badgemaker_user_earned_total');
        } else {
            $this->title = $this->config->title;
        }
    }

    public function get_content() {
      if ($this->content !== null) {
        return $this->content;
      }

      global $DB, $CFG, $USER;

      require_once($CFG->dirroot . '/lib/badgeslib.php');

      $badges = badges_get_user_badges($USER->id, 0, 0, 0, '', true);

      $this->content         =  new stdClass;
      $cp = get_string('earned_prefix', 'block_badgemaker_user_earned_total');
      $cs = get_string('earned_suffix', 'block_badgemaker_user_earned_total');
      $this->content->text = $cp;
      $this->content->text .= html_writer::start_span('lead');
      $this->content->text .= '<b>';
      $this->content->text .= html_writer::link(new moodle_url('/local/badgemaker/badge_library.php'), ''.count($badges));
      $this->content->text .= '</b>';
      $this->content->text .= html_writer::end_span();
      $this->content->text .= ' '.$cs;
      return $this->content;
    }
  }

?>
