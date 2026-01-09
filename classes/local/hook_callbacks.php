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
 * Hook callbacks for mod_coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursesearch\local;

/**
 * Hook callbacks for the coursesearch module.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Callback for the before_footer_html_generation hook.
     *
     * Injects highlighting JavaScript on course pages where highlight parameter is present.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        // Check if highlighting is enabled in admin settings.
        $enablehighlight = get_config('mod_coursesearch', 'enablehighlight');
        // Get_config() may return '0', 0, false, or null (not configured yet).
        // Treat null as enabled (default), and treat any 0-ish value as disabled.
        if ($enablehighlight !== null && (int)$enablehighlight === 0) {
            return;
        }

        // Run on course view pages and supported module pages.
        // Note: H5P (hvp, h5pactivity) is NOT supported - content is rendered in iframe.
        $supportedpagetypes = [
            'course-view',
            'mod-page-view',
            'mod-book-view',
            'mod-lesson-view',
            'mod-wiki-view',
            'mod-forum-discuss',
            'mod-glossary-showentry',
            'mod-data-view',
        ];
        $issupported = false;
        foreach ($supportedpagetypes as $type) {
            if (strpos($PAGE->pagetype, $type) === 0) {
                $issupported = true;
                break;
            }
        }
        if (!$issupported) {
            return;
        }

        // Only load AMD module if there's a highlight parameter in the URL.
        $highlight = optional_param('highlight', '', PARAM_TEXT);
        if (!empty($highlight)) {
            $PAGE->requires->js_call_amd('mod_coursesearch/scrolltohighlight', 'init');
        }
    }

    /**
     * Callback for the before_footer_html_generation hook.
     *
     * Injects floating quick-access search widget on course pages where a coursesearch activity exists.
     * Appears on any page within a course context (course view, module pages, etc.) as long as
     * the feature is enabled and a coursesearch activity exists in the course.
     *
     * @param \core\hook\output\before_footer_html_generation $hook The hook instance.
     * @return void
     */
    public static function inject_floating_widget(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $DB, $COURSE;

        // Check if floating widget is enabled in admin settings.
        $enablefloatingwidget = get_config('mod_coursesearch', 'enablefloatingwidget');
        // Get_config() may return '0', 0, false, or null (not configured yet).
        // Treat null as enabled (default), and treat any 0-ish value as disabled.
        if ($enablefloatingwidget !== null && (int)$enablefloatingwidget === 0) {
            return;
        }

        // Exclude H5P pages - H5P content is rendered in iframe and may have its own search functionality,
        // which would cause the widget to appear embedded in the H5P content area.
        // Check both page type and module type to catch all H5P variations.
        $h5ppagetypes = ['mod-hvp-view', 'mod-h5pactivity-view', 'mod-hvp', 'mod-h5pactivity'];
        foreach ($h5ppagetypes as $h5ptype) {
            if (strpos($PAGE->pagetype, $h5ptype) === 0) {
                return;
            }
        }

        // Also check if the current page's module is an H5P module.
        if ($PAGE->cm) {
            $modname = $PAGE->cm->modname;
            if ($modname === 'hvp' || $modname === 'h5pactivity') {
                return;
            }
        }

        // Get course ID from page context or global $COURSE.
        // This works for any page type within a course (course-view, mod-*-view, etc.).
        $courseid = null;
        if (!empty($COURSE->id) && $COURSE->id != SITEID) {
            $courseid = $COURSE->id;
        } else {
            // Try to get course from page context.
            $context = $PAGE->context;
            if ($context && $context instanceof \context_course) {
                $courseid = $context->instanceid;
            } else if ($context && $context instanceof \context_module) {
                // For module pages, get course from module.
                $cm = $PAGE->cm;
                if ($cm && !empty($cm->course)) {
                    $courseid = $cm->course;
                }
            } else if ($context && $context instanceof \context_block) {
                // For block contexts, try to get course from parent context.
                $parentcontext = $context->get_parent_context();
                if ($parentcontext && $parentcontext instanceof \context_course) {
                    $courseid = $parentcontext->instanceid;
                }
            }
        }

        // Check if we have a valid course ID (must be in a course context, not site level).
        if (empty($courseid) || $courseid == SITEID) {
            return;
        }

        // Check if there's at least one coursesearch activity in this course.
        $sql = "SELECT cs.id, cs.name, cs.placeholder, cm.id as cmid
                  FROM {coursesearch} cs
                  JOIN {course_modules} cm ON cm.instance = cs.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cs.course = :courseid
                   AND m.name = 'coursesearch'
                   AND cm.visible = 1
              ORDER BY cs.id ASC
                 LIMIT 1";
        $coursesearch = $DB->get_record_sql($sql, ['courseid' => $courseid]);

        if (!$coursesearch) {
            return;
        }

        // Check if user has capability to view the course search activity.
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($coursesearch->cmid);
        $context = \context_module::instance($coursesearch->cmid);
        if (!has_capability('mod/coursesearch:view', $context)) {
            return;
        }

        // Get placeholder text.
        $defaultplaceholder = get_string('defaultplaceholder', 'coursesearch');
        $placeholder = !empty($coursesearch->placeholder) ? $coursesearch->placeholder : $defaultplaceholder;

        // Get vertical offset setting (default to 80px if not set).
        $verticaloffset = get_config('mod_coursesearch', 'floatingwidgetverticaloffset');
        if ($verticaloffset === false || $verticaloffset === null) {
            $verticaloffset = 80; // Default value.
        } else {
            $verticaloffset = (int)$verticaloffset;
            // Ensure non-negative value.
            if ($verticaloffset < 0) {
                $verticaloffset = 80;
            }
        }

        // Create the form URL.
        $formurl = new \moodle_url('/mod/coursesearch/view.php', ['id' => $coursesearch->cmid]);

        // Get renderer.
        $renderer = $PAGE->get_renderer('mod_coursesearch');

        // Render the floating widget template.
        $templatecontext = [
            'formurl' => $formurl->out(false),
            'cmid' => $coursesearch->cmid,
            'placeholder' => $placeholder,
            'searchlabel' => get_string('search', 'coursesearch'),
            'verticaloffset' => $verticaloffset,
        ];

        $html = $renderer->render_from_template('mod_coursesearch/floating_widget', $templatecontext);
        $hook->add_html($html);

        // Load the JavaScript module for the floating widget.
        $PAGE->requires->js_call_amd('mod_coursesearch/floatingwidget', 'init');
    }
}
