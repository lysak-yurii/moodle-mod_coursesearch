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

/**
 * Search results container renderable class
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_results implements renderable, templatable {

    /** @var string The search query */
    protected $query;

    /** @var array Array of search_result objects */
    protected $results;

    /** @var int Total count of results */
    protected $count;

    /**
     * Constructor
     *
     * @param string $query The search query
     * @param array $results Array of search_result objects
     */
    public function __construct(string $query, array $results = []) {
        $this->query = $query;
        $this->results = $results;
        $this->count = count($results);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Export each result.
        $exportedresults = [];
        foreach ($this->results as $result) {
            if ($result instanceof search_result) {
                $exportedresults[] = $result->export_for_template($output);
            }
        }

        // Prepare count object for language string.
        $countobj = new \stdClass();
        $countobj->count = $this->count;
        $countobj->query = s($this->query);

        return [
            'query' => s($this->query),
            'hasresults' => ($this->count > 0),
            'noresults' => ($this->count === 0),
            'results' => $exportedresults,
            'count' => $this->count,
            'resultscount' => get_string('searchresultscount', 'coursesearch', $countobj),
            'noresultsmessage' => get_string('noresults', 'coursesearch', s($this->query)),
            'heading' => get_string('searchresultsfor', 'coursesearch', s($this->query)),
        ];
    }
}

