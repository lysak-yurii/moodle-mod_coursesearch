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

/**
 * Search form renderable class
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_form implements renderable, templatable {
    /** @var moodle_url The form action URL */
    protected $formurl;

    /** @var int The course module ID */
    protected $cmid;

    /** @var string The placeholder text for the search input */
    protected $placeholder;

    /** @var string The current search query */
    protected $query;

    /** @var string The current filter value */
    protected $filter;

    /** @var bool Whether the form is embedded in the course page */
    protected $embedded;

    /** @var array The filter options */
    protected $filteroptions;

    /** @var string The intro/description HTML content */
    protected $intro;

    /**
     * Constructor
     *
     * @param moodle_url $formurl The form action URL
     * @param int $cmid The course module ID
     * @param string $placeholder The placeholder text
     * @param string $query The current search query
     * @param string $filter The current filter value
     * @param bool $embedded Whether the form is embedded
     * @param array $filteroptions The filter options (value => label)
     * @param string $intro The intro/description HTML content
     */
    public function __construct(
        moodle_url $formurl,
        int $cmid,
        string $placeholder = '',
        string $query = '',
        string $filter = 'all',
        bool $embedded = false,
        array $filteroptions = [],
        string $intro = ''
    ) {
        $this->formurl = $formurl;
        $this->cmid = $cmid;
        $this->placeholder = $placeholder;
        $this->query = $query;
        $this->filter = $filter;
        $this->embedded = $embedded;
        $this->filteroptions = $filteroptions;
        $this->intro = $intro;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // Build filter options array for template.
        $filters = [];
        foreach ($this->filteroptions as $value => $label) {
            $filters[] = [
                'value' => $value,
                'label' => $label,
                'id' => 'filter_' . $value,
                'checked' => ($this->filter === $value),
            ];
        }

        return [
            'formurl' => $this->formurl->out(false),
            'cmid' => $this->cmid,
            'placeholder' => $this->placeholder,
            'query' => $this->query,
            'embedded' => $this->embedded,
            'hasfilters' => !empty($filters),
            'filters' => $filters,
            'hasintro' => !empty($this->intro),
            'intro' => $this->intro,
        ];
    }
}
