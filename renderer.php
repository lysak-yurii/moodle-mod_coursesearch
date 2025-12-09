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
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Coursesearch module renderer class
 */
class mod_coursesearch_renderer extends plugin_renderer_base {
    
    /**
     * Renders the search form for embedded display
     *
     * @param object $coursesearch The coursesearch instance
     * @param object $cm The course module
     * @return string HTML content
     */
    public function render_embedded_search_form($coursesearch, $cm) {
        global $COURSE;
        
        $output = '';
        
        // Get the placeholder text and escape it to prevent XSS
        $placeholder = !empty($coursesearch->placeholder) ? s($coursesearch->placeholder) : get_string('defaultplaceholder', 'coursesearch');
        
        // Start the container
        $output .= html_writer::start_div('coursesearch-container coursesearch-embedded');
        
        // Create the form
        $formurl = new moodle_url('/mod/coursesearch/view.php', array('id' => $cm->id));
        $output .= html_writer::start_tag('form', array('action' => $formurl, 'method' => 'get', 'class' => 'coursesearch-form'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
        
        // Create the input group
        $output .= html_writer::start_div('input-group');
        $output .= html_writer::empty_tag('input', array(
            'type' => 'text', 
            'name' => 'query', 
            'value' => '', 
            'class' => 'form-control', 
            'placeholder' => $placeholder,
            'aria-label' => get_string('search', 'coursesearch')
        ));
        
        // Add the search button
        $output .= html_writer::start_div('input-group-append');
        $output .= html_writer::tag('button', get_string('search', 'coursesearch'), array(
            'type' => 'submit', 
            'class' => 'btn btn-primary',
            'aria-label' => get_string('search', 'coursesearch')
        ));
        $output .= html_writer::end_div(); // input-group-append
        
        $output .= html_writer::end_div(); // input-group
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_div(); // coursesearch-container
        
        return $output;
    }
}
