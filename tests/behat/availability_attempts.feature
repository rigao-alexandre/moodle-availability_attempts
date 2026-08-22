@availability @availability_attempts
Feature: availability_attempts
  In order to control student access to activities
  As a teacher
  I need to set a condition based on whether a student has exhausted another activity's attempts

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 1 | C1        | topics | 1                |
    And the following "users" exist:
      | username | email         |
      | teacher1 | t@example.com |
      | student1 | s@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name    | attempts | questionsperpage | grade | sumgrades |
      | quiz     | C1     | Quiz 1  | 1        | 0                | 10    | 1         |
      | page     | C1     | Recovery |         |                  |       |           |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name           | questiontext              |
      | Test questions   | truefalse | First question | Answer the first question |
    And quiz "Quiz 1" contains the following questions:
      | question       | page |
      | First question | 1    |

  @javascript
  Scenario: Recovery activity is hidden until the source quiz's attempts are exhausted
    Given I am on the "Recovery" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Attempts" "button" in the "Add restriction..." "dialogue"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I set the field "Activity or Resource" to "Quiz - Quiz 1"
    And I press "Save and return to course"

    # Student has not attempted the quiz yet, so the recovery page stays hidden.
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "Recovery" in the "region-main" "region"

    # Student uses their only attempt.
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    And I am on "Course 1" course homepage
    Then I should see "Recovery" in the "region-main" "region"

  @javascript
  Scenario: Recovery activity combines Attempts with native Activity completion pass/fail
    # The recommended pattern from the README: instead of a raw grade percentage (which has no
    # notion of the quiz's own "grade to pass"), combine Attempts with the native Activity
    # completion condition using the quiz's own completion-tracked pass/fail state.
    Given the following "activities" exist:
      | activity | course | name       | attempts | questionsperpage | grade | sumgrades | completion | completionusegrade | completionpassgrade | gradepass |
      | quiz     | C1     | Quiz 2     | 1        | 0                 | 10    | 1         | 2          | 1                   | 1                    | 5.00      |
      | page     | C1     | Recovery 2 |          |                   |       |           |            |                     |                      |           |
    And quiz "Quiz 2" contains the following questions:
      | question       | page |
      | First question | 1    |
    And I am on the "Recovery 2" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Attempts" "button" in the "Add restriction..." "dialogue"
    And I click on "//*[contains(@class,'availability_attempts')]/ancestor::div[contains(@class,'availability-item')][1]//a[contains(@class,'availability-eye')]/img" "xpath_element"
    And I set the field "Activity or Resource" to "Quiz - Quiz 2"
    And I click on "Add restriction..." "button"
    And I click on "Activity completion" "button" in the "Add restriction..." "dialogue"
    And I click on "//*[contains(@class,'availability_completion')]/ancestor::div[contains(@class,'availability-item')][1]//a[contains(@class,'availability-eye')]/img" "xpath_element"
    And I set the following fields in the ".availability_completion" "css_element" to these values:
      | Required completion status | must be complete with fail grade |
      | cm                         | Quiz 2                           |
    And I press "Save and return to course"

    # Student has not attempted the quiz yet: neither condition is met, so hidden.
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "Recovery 2" in the "region-main" "region"

    # Student uses their only attempt and answers incorrectly (fails the quiz).
    And user "student1" has attempted "Quiz 2" with responses:
      | slot | response |
      | 1    | False    |
    And I am on "Course 1" course homepage
    Then I should see "Recovery 2" in the "region-main" "region"
