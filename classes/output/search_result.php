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
     */
    public function __construct(
        string $name,
        moodle_url $url,
        string $modname,
        $iconurl,
        string $snippet = '',
        string $matchtype = '',
        ?string $forumname = null
    ) {
        $this->name = $name;
        $this->url = $url;
        $this->modname = $modname;
        $this->iconurl = $iconurl;
        $this->snippet = $snippet;
        $this->matchtype = $matchtype;
        $this->forumname = $forumname;
        $this->issection = ($modname === 'section');
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
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

        return [
            'name' => $this->name,
            'url' => $this->url->out(false),
            'modname' => $this->modname,
            'iconurl' => $iconurlstr,
            'iconalt' => $this->issection ? get_string('section') : $this->modname,
            'snippet' => $this->snippet,
            'hassnippet' => !empty($this->snippet),
            'matchtype' => $this->matchtype,
            'hasmatchtype' => !empty($this->matchtype),
            'forumname' => $this->forumname,
            'hasforumname' => !empty($this->forumname),
            'issection' => $this->issection,
            'isforum' => ($this->modname === 'forum'),
        ];
    }
}

