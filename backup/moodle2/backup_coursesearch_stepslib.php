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
 * Define all the backup steps that will be used by the backup_coursesearch_activity_task
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete coursesearch structure for backup, with file and id annotations
 */
class backup_coursesearch_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Define the root element describing the coursesearch instance.
        $coursesearch = new backup_nested_element('coursesearch', ['id'], [
            'name', 'intro', 'introformat', 'searchscope', 'placeholder', 'embedded', 'grouped', 'timemodified']);

        // Define sources.
        $coursesearch->set_source_table('coursesearch', ['id' => backup::VAR_ACTIVITYID]);

        // Define file annotations.
        $coursesearch->annotate_files('mod_coursesearch', 'intro', null);

        // Return the root element (coursesearch), wrapped into standard activity structure.
        return $this->prepare_activity_structure($coursesearch);
    }
}
