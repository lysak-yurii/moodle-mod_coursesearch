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

namespace mod_coursesearch\output;

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use context_module;

/**
 * Module index page renderable class
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_index implements renderable, templatable {
    /** @var array Array of coursesearch instances */
    protected $instances;

    /**
     * Constructor
     *
     * @param array $instances Array of coursesearch instances from get_all_instances_in_course()
     */
    public function __construct(array $instances) {
        $this->instances = $instances;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $items = [];

        foreach ($this->instances as $instance) {
            $context = context_module::instance($instance->coursemodule);
            $viewurl = new moodle_url('/mod/coursesearch/view.php', ['id' => $instance->coursemodule]);

            $items[] = [
                'name' => format_string($instance->name, true, ['context' => $context]),
                'url' => $viewurl->out(false),
                'description' => format_module_intro('coursesearch', $instance, $instance->coursemodule),
            ];
        }

        return [
            'hasinstances' => !empty($items),
            'instances' => $items,
        ];
    }
}
