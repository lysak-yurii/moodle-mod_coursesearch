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
 * Renderer for coursesearch module
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_coursesearch\output\search_form;
use mod_coursesearch\output\search_results;
use mod_coursesearch\output\module_index;

/**
 * Coursesearch module renderer class
 */
class mod_coursesearch_renderer extends plugin_renderer_base {
    /**
     * Renders the search form
     *
     * @param search_form $searchform The search form renderable
     * @return string HTML content
     */
    protected function render_search_form(search_form $searchform): string {
        $data = $searchform->export_for_template($this);
        return $this->render_from_template('mod_coursesearch/search_form', $data);
    }

    /**
     * Renders the search results
     *
     * @param search_results $results The search results renderable
     * @return string HTML content
     */
    protected function render_search_results(search_results $results): string {
        $data = $results->export_for_template($this);
        return $this->render_from_template('mod_coursesearch/search_results', $data);
    }

    /**
     * Renders the module index page
     *
     * @param module_index $index The module index renderable
     * @return string HTML content
     */
    protected function render_module_index(module_index $index): string {
        $data = $index->export_for_template($this);
        return $this->render_from_template('mod_coursesearch/module_index', $data);
    }

    /**
     * Renders the search form for embedded display
     *
     * @param object $coursesearch The coursesearch instance
     * @param object $cm The course module
     * @return string HTML content
     */
    public function render_embedded_search_form($coursesearch, $cm): string {
        // Get the placeholder text.
        $defaultplaceholder = get_string('defaultplaceholder', 'coursesearch');
        $placeholder = !empty($coursesearch->placeholder) ? $coursesearch->placeholder : $defaultplaceholder;

        // Create the form URL.
        $formurl = new moodle_url('/mod/coursesearch/view.php', ['id' => $cm->id]);

        // Create the search form renderable with embedded flag set to true.
        $searchform = new search_form(
            $formurl,
            $cm->id,
            $placeholder,
            '', // No query for embedded form.
            'all', // Default filter.
            true, // Embedded = true.
            [] // No filters for embedded form.
        );

        return $this->render($searchform);
    }
}
