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
 * Front-end class.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_attempts;

defined('MOODLE_INTERNAL') || die();

/**
 * Front-end class.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * @var array Cached init parameters
     */
    protected $cacheparams = [];

    /**
     * @var string IDs of course, cm, and section for cache (if any)
     */
    protected $cachekey = '';

    /**
     * @var array Mapped mods
     */
    protected $allow = ['quiz' => true];

    protected function get_javascript_strings() {
        return [
            'label_cm',
            'label_attempts'
        ];
    }

    protected function get_javascript_init_params($course, \cm_info $cm = null, \section_info $section = null) {
        global $DB;

        // Use cached result if available. The cache is just because we call it
        // twice (once from allow_add) so it's nice to avoid doing all the
        // print_string calls twice.
        $cachekey = $course->id . ',' . ($cm ? $cm->id : '') . ($section ? $section->id : '');
        if ($cachekey !== $this->cachekey) {
            // Get list of activities to fill the dropdown.
            $context = \context_course::instance($course->id);
            $cms = [];
            $modinfo = get_fast_modinfo($course);
            foreach ($modinfo->cms as $id => $othercm) {
                if (!array_key_exists($othercm->modname, $this->allow)) {
                    continue;
                }

                // Add each course-module if it is not the one currently being edited.
                if ((empty($cm) || $cm->id != $id) && !$othercm->deletioninprogress) {
                    $allowed = true;

                    $_temp = (object)[
                        'id' => $id,
                        'name' => get_string('modulename', $othercm->modname) . ' - ' . format_string($othercm->name, true, ['context' => $context])
                    ];

                    $_details = $DB->get_record($othercm->modname, ['id' => $othercm->instance]);

                    switch ($othercm->modname) {
                        case 'quiz':
                            $allowed = +$_details->attempts > 0;
                        break;

                        default: break;
                    }

                    if ($allowed) {
                        $cms[] = $_temp;
                    }
                }
            }

            $this->cachekey = $cachekey;
            $this->cacheinitparams = [$cms];
        }

        return $this->cacheinitparams;
    }

    protected function allow_add($course, \cm_info $cm = null, \section_info $section = null) {
        global $CFG;

        // Check if there's at least one other module with attempts info.
        $params = $this->get_javascript_init_params($course, $cm, $section);
        return ((array)$params[0]) != false;
    }
}
