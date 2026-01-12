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

        // Exclude H5P content files and embedded content, but allow H5P activity view pages.
        // H5P view pages (mod/h5pactivity/view.php, mod/hvp/view.php) should show the widget,
        // but H5P content files served via pluginfile.php or contentbank should not.

        // Exclude SCORM player pages (when SCORM content is being played), but allow SCORM view/start pages.
        // SCORM player pages are rendered in iframe/popup and should not show the widget.
        $scormplayerpagetypes = ['mod-scorm-player', 'mod-scorm-play'];
        foreach ($scormplayerpagetypes as $scormplayertype) {
            if (strpos($PAGE->pagetype, $scormplayertype) === 0) {
                return;
            }
        }

        // Get page URL for various checks.
        $url = $PAGE->url;
        if ($url) {
            $urlpath = $url->get_path();
            $querystring = $url->get_query_string() ?? '';
            $fullurl = $url->out(false);
            $params = $url->params();

            // Exclude H5P embed/player pages (where H5P content is actually rendered in iframe).
            // These are different from view pages - they're the pages that load inside the iframe.
            // Check for H5P-specific embed URLs or parameters that indicate embedded content.
            if (
                stripos($urlpath, 'mod/h5pactivity/embed.php') !== false ||
                stripos($urlpath, 'mod/hvp/embed.php') !== false ||
                stripos($urlpath, 'h5p/embed.php') !== false ||
                stripos($urlpath, '/embed.php') !== false ||
                (isset($params['embed']) && ($params['embed'] === '1' || $params['embed'] === 'true'))
            ) {
                return;
            }

            // Check URL for SCORM player.php pages.
            if (stripos($urlpath, 'mod/scorm/player.php') !== false || stripos($urlpath, 'player.php') !== false) {
                // Check if it's actually a player page (has player-specific parameters).
                if (isset($params['a']) || isset($params['scoid']) || isset($params['display'])) {
                    return;
                }
            }

            // Exclude any page serving H5P content - check multiple ways to be robust.
            // 1. Check the full request URI for .h5p file references.
            $requesturi = $_SERVER['REQUEST_URI'] ?? '';
            if (stripos($requesturi, '.h5p') !== false) {
                return;
            }

            // 2. Check page URL for H5P content.
            // Check if URL contains .h5p anywhere (path, query, or full URL).
            if (
                stripos($urlpath, '.h5p') !== false ||
                stripos($querystring, '.h5p') !== false ||
                stripos($fullurl, '.h5p') !== false
            ) {
                return;
            }

            // Check if serving files via pluginfile.php with contentbank component.
            if (stripos($urlpath, 'pluginfile.php') !== false) {
                // Check for contentbank in the path.
                if (stripos($urlpath, 'contentbank') !== false) {
                    return;
                }
                // Also check URL parameters for component=contentbank.
                if (isset($params['component']) && $params['component'] === 'contentbank') {
                    return;
                }
            }
        }

        // 3. Check page context for content bank context.
        $context = $PAGE->context;
        if ($context) {
            // Check if context is content bank related.
            $contextpath = $context->path ?? '';
            if (stripos($contextpath, '/contentbank') !== false) {
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
