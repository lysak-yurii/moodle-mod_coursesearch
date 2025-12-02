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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);
$context = context_course::instance($course->id);

// Note: course_module_instance_list_viewed is abstract in newer Moodle versions
// We'll skip this event for now

$PAGE->set_url('/mod/coursesearch/index.php', array('id' => $course->id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'coursesearch');
echo $OUTPUT->heading($modulenameplural);

if (! $coursesearches = get_all_instances_in_course('coursesearch', $course)) {
    notice(get_string('nocourseinstances', 'coursesearch'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$table->head  = array(get_string('name'), get_string('description'));
$table->align = array('left', 'left');

foreach ($coursesearches as $coursesearch) {
    $context = context_module::instance($coursesearch->coursemodule);
    $link = html_writer::link(
        new moodle_url('/mod/coursesearch/view.php', array('id' => $coursesearch->coursemodule)),
        format_string($coursesearch->name, true, array('context' => $context))
    );
    
    $description = format_module_intro('coursesearch', $coursesearch, $coursesearch->coursemodule);
    
    $table->data[] = array($link, $description);
}

echo html_writer::table($table);
echo $OUTPUT->footer();
