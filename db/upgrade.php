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
 * Course Search module upgrade tasks
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute coursesearch upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_coursesearch_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025040300) {
        // Define table coursesearch to be created.
        $table = new xmldb_table('coursesearch');

        // Adding fields to table coursesearch.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('intro', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('searchscope', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all');
        $table->add_field('placeholder', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('embedded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table coursesearch.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('course', XMLDB_KEY_FOREIGN, ['course'], 'course', ['id']);

        // Conditionally launch create table for coursesearch.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Coursesearch savepoint reached.
        upgrade_mod_savepoint(true, 2025040300, 'coursesearch');
    }

    // Add the embedded field to existing installations if upgrading from a version without it.
    if ($oldversion < 2025040301) {
        $table = new xmldb_table('coursesearch');
        $field = new xmldb_field('embedded', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'placeholder');

        // Add the field if it doesn't exist.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Update the plugin version.
        upgrade_mod_savepoint(true, 2025040301, 'coursesearch');
    }

    // Set default value for enablehighlight setting.
    if ($oldversion < 2025121001) {
        // Set default value for highlighting feature (enabled by default).
        $currentvalue = get_config('mod_coursesearch', 'enablehighlight');
        if ($currentvalue === false) {
            set_config('enablehighlight', 1, 'mod_coursesearch');
        }

        upgrade_mod_savepoint(true, 2025121001, 'coursesearch');
    }

    return true;
}
