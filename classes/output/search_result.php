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
 * Single search result renderable class
 *
 * @package    mod_coursesearch
 * @copyright  2025 Yurii Lysak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_result implements renderable, templatable {
    /** @var string The result name/title */
    protected $name;

    /** @var moodle_url The result URL */
    protected $url;

    /** @var string The module name (e.g., 'page', 'forum', 'section') */
    protected $modname;

    /** @var string|moodle_url The icon URL */
    protected $iconurl;

    /** @var string The snippet with highlighted search term */
    protected $snippet;

    /** @var string What was matched (title, content, description) */
    protected $matchtype;

    /** @var string|null The forum name (if applicable) */
    protected $forumname;

    /** @var bool Whether this is a section result */
    protected $issection;

    /** @var bool Whether this is a subsection result */
    protected $issubsection;

    /** @var int Section number this result belongs to */
    protected $sectionnumber;

    /** @var string Section name this result belongs to */
    protected $sectionname;

    /** @var int|null Parent section number (for subsections) */
    protected $parentsectionnumber;

    /** @var string|null Parent section name (for subsections) */
    protected $parentsectionname;

    /** @var bool Whether this result represents a group of matches */
    protected $isgrouped;

    /** @var int Number of matches in the group (for grouped results) */
    protected $matchcount;

    /** @var string Activity name (for grouped results) */
    protected $activityname;

    /** @var moodle_url|null URL to main activity page (for grouped results) */
    protected $activityurl;

    /** @var string|moodle_url|null Activity icon (for grouped results) */
    protected $activityicon;

    /** @var array Array of individual match results (for grouped results) */
    protected $matches;

    /**
     * Get a human-readable result type label for tooltips.
     *
     * @return string
     */
    protected function get_result_type_label(): string {
        if ($this->modname === 'section') {
            return get_string('section');
        }

        if ($this->modname === 'subsection') {
            return get_string('modulename', 'subsection');
        }

        return get_string('modulename', $this->modname);
    }

    /**
     * Constructor
     *
     * @param string $name The result name
     * @param moodle_url $url The result URL
     * @param string $modname The module name
     * @param string|moodle_url $iconurl The icon URL
     * @param string $snippet The content snippet
     * @param string $matchtype What was matched
     * @param string|null $forumname The forum name (if applicable)
     * @param int $sectionnumber Section number (default 0)
     * @param string $sectionname Section name (default empty)
     * @param bool $issubsection Whether this is a subsection (default false)
     * @param int|null $parentsectionnumber Parent section number for subsections
     * @param string|null $parentsectionname Parent section name for subsections
     * @param bool $isgrouped Whether this represents a group of matches
     * @param int $matchcount Number of matches in group (for grouped results)
     * @param string $activityname Activity name (for grouped results)
     * @param moodle_url|null $activityurl URL to main activity (for grouped results)
     * @param string|moodle_url|null $activityicon Activity icon (for grouped results)
     * @param array $matches Array of individual match results (for grouped results)
     */
    public function __construct(
        string $name,
        moodle_url $url,
        string $modname,
        $iconurl,
        string $snippet = '',
        string $matchtype = '',
        ?string $forumname = null,
        int $sectionnumber = 0,
        string $sectionname = '',
        bool $issubsection = false,
        ?int $parentsectionnumber = null,
        ?string $parentsectionname = null,
        bool $isgrouped = false,
        int $matchcount = 0,
        string $activityname = '',
        ?moodle_url $activityurl = null,
        $activityicon = null,
        array $matches = []
    ) {
        $this->name = $name;
        $this->url = $url;
        $this->modname = $modname;
        $this->iconurl = $iconurl;
        $this->snippet = $snippet;
        $this->matchtype = $matchtype;
        $this->forumname = $forumname;
        $this->issection = ($modname === 'section' || $modname === 'subsection');
        $this->issubsection = $issubsection;
        $this->sectionnumber = $sectionnumber;
        $this->sectionname = $sectionname;
        $this->parentsectionnumber = $parentsectionnumber;
        $this->parentsectionname = $parentsectionname;
        $this->isgrouped = $isgrouped;
        $this->matchcount = $matchcount;
        $this->activityname = $activityname;
        $this->activityurl = $activityurl;
        $this->activityicon = $activityicon;
        $this->matches = $matches;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        // If this is a grouped result, export grouped structure.
        if ($this->isgrouped) {
            // Determine activity icon URL string.
            $activityiconurlstr = '';
            if ($this->activityicon instanceof moodle_url) {
                $activityiconurlstr = $this->activityicon->out(false);
            } else if (is_string($this->activityicon)) {
                $activityiconurlstr = $this->activityicon;
            }

            // Export individual matches.
            $exportedmatches = [];
            foreach ($this->matches as $match) {
                if ($match instanceof search_result) {
                    $exportedmatches[] = $match->export_for_template($output);
                }
            }

            return [
                'isgrouped' => true,
                'activityname' => $this->activityname,
                'activityurl' => $this->activityurl ? $this->activityurl->out(false) : '',
                'hasactivityurl' => ($this->activityurl !== null),
                'activityiconurl' => $activityiconurlstr,
                'activitymodname' => $this->modname,
                'resulttype' => $this->get_result_type_label(),
                'matchcount' => $this->matchcount,
                'hasmatches' => !empty($exportedmatches),
                'matches' => $exportedmatches,
                'section_number' => $this->sectionnumber,
                'section_name' => $this->sectionname,
                'parent_section_number' => $this->parentsectionnumber,
                'parent_section_name' => $this->parentsectionname,
                'issubsection' => $this->issubsection,
            ];
        }

        // Regular individual result export.
        // Determine icon URL string.
        $iconurlstr = '';
        if ($this->iconurl instanceof moodle_url) {
            $iconurlstr = $this->iconurl->out(false);
        } else if (is_string($this->iconurl)) {
            $iconurlstr = $this->iconurl;
        }

        // For sections, use a folder icon.
        if ($this->issection) {
            $iconurlstr = (new moodle_url('/pix/i/folder.png'))->out(false);
        }

        // Check if this is a title match - if so, don't show snippet (it would repeat the title).
        $istitlematch = (stripos($this->matchtype, 'title') !== false);
        $showsnippet = !empty($this->snippet) && !$istitlematch;

        return [
            'isgrouped' => false,
            'name' => $this->name,
            'url' => $this->url->out(false),
            'modname' => $this->modname,
            'iconurl' => $iconurlstr,
            'iconalt' => $this->issection ? get_string('section') : $this->modname,
            'resulttype' => $this->get_result_type_label(),
            'snippet' => $this->snippet,
            'hassnippet' => $showsnippet,
            'matchtype' => $this->matchtype,
            'hasmatchtype' => !empty($this->matchtype),
            'forumname' => $this->forumname,
            'hasforumname' => !empty($this->forumname),
            'issection' => $this->issection,
            'issubsection' => $this->issubsection,
            'isforum' => ($this->modname === 'forum'),
            'section_number' => $this->sectionnumber,
            'section_name' => $this->sectionname,
            'parent_section_number' => $this->parentsectionnumber,
            'parent_section_name' => $this->parentsectionname,
        ];
    }
}
