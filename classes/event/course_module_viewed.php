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
 * The mod_coursesearch course module viewed event.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursesearch\event;

/**
 * The mod_coursesearch course module viewed event class.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_module_viewed extends \core\event\course_module_viewed {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'coursesearch';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/coursesearch/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Return the localised name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return \get_string('eventcoursemoduleviewed', 'coursesearch');
    }

    /**
     * Return the description of the event.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' viewed the course search activity with instance id '{$this->objectid}' " .
            "in the course with id '{$this->courseid}'.";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        // Make sure this class is correctly used.
        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }
}
