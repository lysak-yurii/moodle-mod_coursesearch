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
 * Site-level display defaults for mod_coursesearch
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursesearch\local;

/**
 * Accessors for the site-level display defaults.
 *
 * This is consulted where no activity instance is available to read the setting from
 * (the quick-access widget and the course-level search page), and it seeds the activity
 * form so that new instances start from the site preference. An instance that stores its
 * own value always wins over it.
 *
 * The accessor lives in an autoloaded class rather than in locallib.php so that the footer
 * hook can read it without loading the whole search library on every page.
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class defaults {
    /**
     * Whether search results are grouped by section when no instance setting applies.
     *
     * @return bool True to group by section (default), false for a flat list.
     */
    public static function grouped(): bool {
        $grouped = get_config('mod_coursesearch', 'defaultgrouped');
        // Not configured yet (fresh install, or a site upgraded from before this setting
        // existed): keep the historical default of grouping by section.
        if ($grouped === false || $grouped === null) {
            return true;
        }
        return ((int)$grouped === 1);
    }
}
