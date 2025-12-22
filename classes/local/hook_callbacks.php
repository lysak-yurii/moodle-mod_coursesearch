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
        if ($enablehighlight === '0') {
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
}
