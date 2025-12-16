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
 * Library of interface functions and constants for module coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * List of features supported in Course Search module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function coursesearch_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function coursesearch_reset_userdata($data) {
    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function coursesearch_get_view_actions() {
    return ['view', 'search'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * @return array
 */
function coursesearch_get_post_actions() {
    return [];
}

/**
 * Add coursesearch instance.
 * @param stdClass $data
 * @param mod_coursesearch_mod_form $mform
 * @return int new coursesearch instance id
 */
function coursesearch_add_instance($data, $mform = null) {
    global $DB;

    $cmid = $data->coursemodule;

    $data->timemodified = time();

    // You might want to add more options here.
    $data->id = $DB->insert_record('coursesearch', $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);

    $comptime = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'coursesearch', $data->id, $comptime);

    return $data->id;
}

/**
 * Update coursesearch instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function coursesearch_update_instance($data, $mform) {
    global $DB;

    $cmid = $data->coursemodule;

    $data->timemodified = time();
    $data->id = $data->instance;

    $DB->update_record('coursesearch', $data);

    $comptime = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'coursesearch', $data->id, $comptime);

    return true;
}

/**
 * Delete coursesearch instance.
 * @param int $id
 * @return bool true
 */
function coursesearch_delete_instance($id) {
    global $DB;

    if (!$coursesearch = $DB->get_record('coursesearch', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('coursesearch', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'coursesearch', $id, null);

    $DB->delete_records('coursesearch', ['id' => $coursesearch->id]);

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function coursesearch_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, embedded';
    $coursesearch = $DB->get_record('coursesearch', ['id' => $coursemodule->instance], $fields);
    if (!$coursesearch) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $coursesearch->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('coursesearch', $coursesearch, $coursemodule->id, false);
    }

    // If the search bar is set to be embedded, tell the course renderer to display it inline.
    if (!empty($coursesearch->embedded)) {
        $info->content = $info->content ?? '';

        // Set a custom flag to indicate this module should be rendered inline.
        $info->customdata = ['embedded' => true];

        // This is the key part - tell Moodle to use our custom renderer.
        $info->content_items_online = true;
        $info->content_online = true;
        $info->onclick_online = true;
    }

    return $info;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $coursesearch     coursesearch object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 */
function coursesearch_view($coursesearch, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $coursesearch->id,
    ];

    $event = \mod_coursesearch\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('coursesearch', $coursesearch);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Serves the coursesearch course format content.
 *
 * @param cm_info $cm Course module object
 * @return string HTML to display
 */
function coursesearch_cm_info_view(cm_info $cm) {
    global $CFG, $PAGE, $DB;

    // Only continue if the module is set to be embedded.
    if (empty($cm->customdata['embedded'])) {
        return '';
    }

    // Get the coursesearch record.
    $coursesearch = $DB->get_record('coursesearch', ['id' => $cm->instance], '*', MUST_EXIST);

    // Include renderer file.
    require_once($CFG->dirroot . '/mod/coursesearch/renderer.php');

    // Get the renderer.
    $renderer = $PAGE->get_renderer('mod_coursesearch');

    // Render the embedded search form.
    return $renderer->render_embedded_search_form($coursesearch, $cm);
}

/**
 * Overwrites the content output for a course module
 *
 * This function is used to display the embedded search form directly in the course page
 *
 * @param cm_info $cm Course module info object
 */
function coursesearch_cm_info_dynamic(cm_info $cm) {
    global $CFG, $DB, $PAGE;

    // Note: JavaScript for scrolling is now handled client-side via sessionStorage.
    // No need to load AMD modules here.

    // Check if the module should be embedded.
    $coursesearch = $DB->get_record('coursesearch', ['id' => $cm->instance], 'embedded');

    if (!$coursesearch || empty($coursesearch->embedded)) {
        return;
    }

    // Include the renderer.
    require_once($CFG->dirroot . '/mod/coursesearch/renderer.php');

    // Get the full coursesearch record.
    $fullcoursesearch = $DB->get_record('coursesearch', ['id' => $cm->instance], '*', MUST_EXIST);

    // Get the renderer.
    $renderer = $PAGE->get_renderer('mod_coursesearch');

    // Generate the embedded search form.
    $content = $renderer->render_embedded_search_form($fullcoursesearch, $cm);

    // Set the content to be displayed in the course page.
    $cm->set_content($content);

    // Hide the view link since the content is already embedded.
    $cm->set_no_view_link();
}
