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
 * The main coursesearch configuration form
 *
 * @package    mod_coursesearch
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_coursesearch
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_coursesearch_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Search settings.
        $mform->addElement('header', 'coursesearchsettings', get_string('coursesearchsettings', 'coursesearch'));

        // Search scope options.
        $searchoptions = array(
            'course' => get_string('searchscope_course', 'coursesearch'),
            'activities' => get_string('searchscope_activities', 'coursesearch'),
            'resources' => get_string('searchscope_resources', 'coursesearch'),
            'forums' => get_string('searchscope_forums', 'coursesearch'),
            'all' => get_string('searchscope_all', 'coursesearch')
        );
        $mform->addElement('select', 'searchscope', get_string('searchscope', 'coursesearch'), $searchoptions);
        $mform->setDefault('searchscope', 'all');
        $mform->addHelpButton('searchscope', 'searchscope', 'coursesearch');

        // Add placeholder text option.
        $mform->addElement('text', 'placeholder', get_string('placeholder', 'coursesearch'), array('size' => '64'));
        $mform->setType('placeholder', PARAM_TEXT);
        $mform->setDefault('placeholder', get_string('defaultplaceholder', 'coursesearch'));
        $mform->addHelpButton('placeholder', 'placeholder', 'coursesearch');

        // Add display options
        $mform->addElement('header', 'displayoptions', get_string('displayoptions', 'coursesearch'));
        
        // Embedding option
        $mform->addElement('advcheckbox', 'embedded', get_string('embedded', 'coursesearch'), 
            get_string('embeddedinfo', 'coursesearch'));
        $mform->setDefault('embedded', 1); // Embedded by default
        $mform->addHelpButton('embedded', 'embedded', 'coursesearch');

        // Add standard elements.
        $this->standard_coursemodule_elements();

        // Add standard buttons.
        $this->add_action_buttons();
    }
}
