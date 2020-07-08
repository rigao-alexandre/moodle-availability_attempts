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
 * Unit tests for the attempts condition.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use availability_attempts\condition;

global $CFG;

/**
 * Unit tests for the attempts condition.
 *
 * @package availability_attempts
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availability_attempts_condition_testcase extends advanced_testcase {
    /**
     * Load required classes.
     */
    public function setUp() {
        // Load the mock info class so that it can be used.
        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
    }

    /**
     * Tests the constructor including error conditions. Also tests the
     * string conversion feature (intended for debugging only).
     */
    public function test_constructor() {
        // No parameters.
        $structure = new stdClass();
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->cm', $e->getMessage());
        }

        // Invalid $cm.
        $structure->cm = 'hello';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->cm', $e->getMessage());
        }

        $structure->cm = 42;

        // Successful construct & display with all different expected values.
        $cond = new condition($structure);
        $this->assertEquals('{attempts:cm42}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)['cm' => 42];
        $cond = new condition($structure);
        $structure->type = 'attempts';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the is_available and get_description functions.
     */
    public function test_usage() {
        global $CFG, $DB;

        $this->resetAfterTest();

        // Create course with completion turned on.
        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(
                ['numsections' => 1, 'enablecompletion' => 1],
                ['createsections' => true]
        );

        $user = $generator->create_user();

        $generator->enrol_user($user->id, $course->id);

        $this->setUser($user);

        // Create group
        $group = $generator->create_group(['courseid' => $course->id]);
        // Add user to group
        $this->assertTrue(groups_add_member($group, $user));

        // Create Quiz, Category, Question
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
                'course' => $course->id,
                'questionsperpage' => 0,
                'grade' => 100.0,
                'sumgrades' => 2,
                'attempts' => 2,
                'name' => 'Quiz!'
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        // Get basic details.
        $modinfo = get_fast_modinfo($course);

        $quizcm = $modinfo->get_cm($quiz->cmid);
        $quizobj = quiz::create($quiz->id, $user->id);

        $info = new \core_availability\mock_info($course, $user->id);

        $cond = new condition((object)[
                'cm' => (int)$quizcm->id
        ]);

        $information = $cond->get_description(false, true, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertRegExp('~User has not taken all attempts on the activity .*Quiz!.*~', $information);
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));

        $information = $cond->get_description(false, false, $info);
        $information = \core_availability\info::format_info($information, $course);
        $this->assertRegExp('~User has taken all attempts on the activity .*Quiz!.*~', $information);
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        /**
         * Attempt 1
         */
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Process some responses from the student.
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);

        // Finish the attempt.
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);

        // Re-load quiz attempt data.
        $attemptobj = quiz_attempt::create($attempt->id);

        // retest
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        /**
         * Attempt 2
         */
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 2, $attempt, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 2, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        // retest
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        /**
         * Group Override
         */
        $groupoverride = $DB->insert_record('quiz_overrides', [
                'quiz' => $quiz->id,
                'groupid' => $group->id,
                'attempts' => 3,
        ]);

        $this->assertTrue($cond->is_available(true, $info, true, $user->id));
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        /**
         * Attempt 3
         */
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 3, $attempt, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 3, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        // retest
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        /**
         * User Override (UP)
         */
        $useroverride = $DB->insert_record('quiz_overrides', [
                'quiz' => $quiz->id,
                'userid' => $user->id,
                'attempts' => 4,
        ]);

        $this->assertTrue($cond->is_available(true, $info, true, $user->id));
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        /**
         * Attempt 4
         */
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, 4, $attempt, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 4, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [1 => ['answer' => '3.14']]);
        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_finish($timenow, false);
        $attemptobj = quiz_attempt::create($attempt->id);

        // retest
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        /**
         * User Override (DOWN)
         */

        $DB->update_record('quiz_overrides', [
                'id' => $useroverride,
                'quiz' => $quiz->id,
                'userid' => $user->id,
                'attempts' => 1,
        ]);

        // retest
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        $cond = new condition((object)['cm' => 123]);
        $this->assertFalse($cond->update_dependency_id('frogs', 123, 456));
        $this->assertFalse($cond->update_dependency_id('course_modules', 12, 34));
        $this->assertTrue($cond->update_dependency_id('course_modules', 123, 456));
        $after = $cond->save();
        $this->assertEquals(456, $after->cm);
    }
}
