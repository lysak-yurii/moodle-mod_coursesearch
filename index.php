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
 * Display information about all the coursesearch modules in the requested course
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use mod_coursesearch\output\module_index;

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);
$context = context_course::instance($course->id);

// Check if user has capability to view coursesearch modules.
require_capability('mod/coursesearch:view', $context);

// Note: course_module_instance_list_viewed is abstract in newer Moodle versions.
// We'll skip this event for now.

$PAGE->set_url('/mod/coursesearch/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'coursesearch');
echo $OUTPUT->heading($modulenameplural);

$coursesearches = get_all_instances_in_course('coursesearch', $course);

if (!$coursesearches) {
    notice(get_string('nocourseinstances', 'coursesearch'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Create and render the module index.
$index = new module_index($coursesearches);
echo $OUTPUT->render($index);

echo $OUTPUT->footer();
