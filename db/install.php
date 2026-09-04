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
 * Install time actions for the coursesearch module.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure.
 *
 * Seeds the activity types the quick-access widget stays out of on a new site: quiz, where the
 * widget would offer a course-wide search during an attempt, and coursesearch itself, where the
 * full search form is already on the page. Only new sites get these - the setting's own default
 * is empty, so upgrading a site never changes where its widget already appears.
 *
 * @return void
 */
function xmldb_coursesearch_install() {
    set_config('disabledmodules', 'quiz,coursesearch', 'mod_coursesearch');
}
