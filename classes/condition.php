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
 * Activity attempts condition.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_attempts;

use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Activity attempts condition.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int ID of module that this depends on */
    protected $cmid;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get cmid.
        if (isset($structure->cm) && is_number($structure->cm)) {
            $this->cmid = (int)$structure->cm;
        } else {
            throw new \coding_exception('Missing or invalid ->cm for attempt condition');
        }
    }

    public function save(): stdClass {
        return (object)[
            'type' => 'attempts',
            'cm' => $this->cmid
        ];
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * Intended for unit testing, as normally the JSON values are constructed
     * by JavaScript code.
     *
     * @param int $cmid Course-module id of other activity
     * @return stdClass Object representing condition
     */
    public static function get_json($cmid): stdClass {
        return (object)[
            'type' => 'attempts',
            'cm' => (int)$cmid
        ];
    }

    public function is_available($not, info $info, $grabthelot, $userid): bool {
        global $CFG;

        $modinfo = $info->get_modinfo();

        if (!array_key_exists($this->cmid, $modinfo->cms)) {
            // If the cmid cannot be found, always return false regardless
            // of the condition or $not state. (Will be displayed in the
            // information message.)
            $allow = false;
        } else {
            $allow = true;

            // $target = $info->get_course_module();
            $source = $modinfo->get_cm($this->cmid);

            switch ($source->modname) {
                case 'quiz':
                    require_once($CFG->dirroot.'/mod/quiz/locallib.php');

                    $quizobj = \quiz::create($source->instance, $userid);

                    // quiz has not unlimited attempts
                    if ($quizobj->get_num_attempts_allowed() > 0) {
                        $userattempts = quiz_get_user_attempts($source->instance, $userid, 'finished', true);

                        // user has taken at least one attempt
                        if ($userattempts) {
                            $totaluserattempts = count($userattempts);
                            // $maxuserattempt = max(array_keys($userattempts));

                            $lastfinishedattempt = end($userattempts);

                            $accessmanager = $quizobj->get_access_manager(time());

                            $quizstatus = (
                                $accessmanager->is_finished($totaluserattempts, $lastfinishedattempt)
                                // || !empty($accessmanager->prevent_new_attempt($totaluserattempts, $lastfinishedattempt))
                                // || !empty($accessmanager->prevent_access())
                            );

                            // user has attempts left
                            if (!$quizstatus) {
                                $allow = false;
                            }
                        } else {
                            // quiz has not unlimited attempts and user has not take any attempt
                            $allow = false;
                        }
                    }
                break;

                default: break;
            }

            if ($not) {
                $allow = !$allow;
            }
        }

        return $allow;
    }

    public function get_description($full, $not, info $info): string {
        // Get name for module.
        $modinfo = $info->get_modinfo();
        if (!array_key_exists($this->cmid, $modinfo->cms)) {
            $modname = get_string('missing', 'availability_attempts');
        } else {
            $modname = '<AVAILABILITY_CMNAME_' . $modinfo->cms[$this->cmid]->id . '/>';
        }

        // Work out which lang string to use.
        if ($not) {
            $str = 'requires_not_attempts';
        } else {
            $str = 'requires_attempts';
        }

        return get_string($str, 'availability_attempts', $modname);
    }

    protected function get_debug_string(): string {
        return 'cm' . $this->cmid;
    }

    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name): bool {
        global $DB;
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'course_module', $this->cmid);
        if (!$rec || !$rec->newitemid) {
            // If we are on the same course (e.g. duplicate) then we can just
            // use the existing one.
            if ($DB->record_exists('course_modules', ['id' => $this->cmid, 'course' => $courseid])) {
                return false;
            }
            // Otherwise it's a warning.
            $this->cmid = 0;
            $logger->process('Restored item (' . $name . ') has availability condition on module that was not restored', \backup::LOG_WARNING);
        } else {
            $this->cmid = (int)$rec->newitemid;
        }
        return true;
    }

    public function update_dependency_id($table, $oldid, $newid): bool {
        if ($table === 'course_modules' && (int)$this->cmid === (int)$oldid) {
            $this->cmid = $newid;
            return true;
        } else {
            return false;
        }
    }
}
