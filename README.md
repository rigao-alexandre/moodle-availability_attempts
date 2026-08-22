# Moodle - Attempts Availability Condition (availability_attempts)

## Description

Restricts access to an activity or section until a student has exhausted (or, inverted, until
they have *not* exhausted) all the attempts allowed on a *different* activity in the same course.

Currently supported: **Quiz (mod_quiz)**, and only when it does not have unlimited attempts -
there is no "all attempts used" state to reach otherwise, so unlimited-attempt quizzes are left
out of the dropdown when adding this restriction.

**Main use case:** gate a grade-recovery activity so it only appears once a student has used up
every attempt on the original quiz *and* still hasn't reached a passing grade. That needs two
conditions combined with "match all" on the recovery activity: this plugin for "attempts
exhausted", plus one of the following for "hasn't passed".

- **Recommended: native "Restrict access > Activity completion"**, pointing at the source quiz,
  with "Required completion status" set to "must be complete with fail grade". This reads the
  quiz's own pass/fail state directly - no separate value to keep in sync. It does need the quiz
  to have completion tracking set to "Show activity as complete when conditions are met" with
  "Require grade" checked (and "Require passing grade" checked too, for robustness - Moodle can
  still surface a fail state without it whenever the quiz has a "Grade to pass" set and its grade
  item isn't hidden, but checking it makes the intent explicit and keeps the pass/fail signal
  correct even if the grade item's visibility changes later).
- **Alternative: native "Restrict access > Grade"**, pointing at the source quiz, with "must be <"
  checked. This one only understands raw percentage of the quiz's max grade, with no notion of
  "grade to pass" - so the percentage has to be entered by hand (`grade_to_pass / quiz_max_grade *
  100`) and re-entered by hand again if the quiz's pass grade or max grade ever changes.

See `tests/behat/availability_attempts.feature` for a working example of the recommended
combination end-to-end.

This plugin is a companion to
[Fail Grade Quiz Access Rule (quizaccess_failgrade)](https://github.com/rigao-alexandre/moodle-quizaccess_failgrade) -
they solve related but different problems. `quizaccess_failgrade` blocks a student from
re-attempting the *same* quiz once they've reached the grade to pass. This plugin instead
controls access to *other* activities based on whether attempts on a source activity have run
out. Used together, a typical setup is: `quizaccess_failgrade` stops further attempts on the
original quiz once it's passed, while this plugin reveals the recovery activity once attempts are
exhausted and (via the native Grade condition) the student still hasn't passed.

## Known limitations

### Course/user resets and local_recompletion

This plugin decides whether attempts are exhausted purely from what's currently on record for the
source quiz (`quiz_get_user_attempts()` plus the quiz's own override-aware attempts cap). It has
no notion of "training cycles". Moodle's native "Reset course" clears quiz attempts as part of a
reset by default ("Remove all quiz attempts" is checked out of the box), so a fresh cycle
correctly starts with the recovery activity hidden again. Tools that reset completion for periodic
retraining but *don't* clear attempt history - such as
[local_recompletion](https://github.com/danmarsden/moodle-local_recompletion) with its default
settings - can leave old attempts on record, which means the recovery activity may still show as
available at the start of a new cycle even though the student hasn't touched the quiz yet in that
cycle. If you use `local_recompletion` for periodic retraining, enable its "Quiz attempts: Delete"
option so each cycle starts clean.

### Only mod_quiz today

See [Status / Roadmap](#status--roadmap) below for what's planned and why the other obvious
candidates (mod_lesson, mod_scorm, mod_h5pactivity, mod_assign) aren't supported yet.

## Requirements

- Moodle 3.9 (2020060900) through Moodle 5.2, tested via CI against every stable branch in that
  range (see `.github/workflows/main.yml`).

## Installation

Please refer to the official documentation: [Installing Plugins](https://docs.moodle.org/en/Installing_plugins)

## Development

Please, use GitHub for issues.

### Contributing code or translations

If you'd like to contribute a fix, a new feature, or a translation, a **pull request is strongly
preferred over attaching a file to an issue** (e.g. a zip with translated strings). A PR shows
exactly what changed, runs through CI automatically, and is much easier to review and merge than a
file someone has to download and apply by hand. If you're not comfortable with git/GitHub, opening
an issue with the file attached is still welcome - it just takes longer to get merged.

### Reporting a bug

To help diagnose the issue, please include:

- **Moodle version** (Site administration → General → Version)
- **PHP version** and database (MySQL/MariaDB/Postgres) and its version
- **Plugin version** (see `version.php`'s `release`, e.g. `vX.Y.Z`, or the exact commit if
  installed from git)
- **Which module the condition points at** (currently only quiz) and its attempts-related
  settings: number of attempts allowed, and any group/user overrides in play
- **Steps to reproduce**, and what you expected to happen vs. what actually happened
- Any relevant error message, or entry from the Moodle/PHP error log

Reports without this context are usually much harder to act on, so including it up front saves a
round trip.

### Packaging a release for moodle.org

The Moodle plugins directory takes a zip upload rather than linking directly to this repo, so a
release needs to be packaged first. `.gitattributes` marks the files that don't belong in that zip
(CI config) via `export-ignore`, so `git archive` produces a clean package on its own - no manual
exclude flags to keep in sync:

```bash
ref=$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)
git archive --format=zip --prefix=attempts/ "$ref" -o "availability_attempts-${ref}.zip"
```

Run this after checking out (or tagging) the exact commit to release. It picks up the tag pointing
at the current commit automatically (falling back to the short commit hash if there isn't one yet)
and uses it both as the archive ref and in the output filename, so there's nothing to edit by hand.
`--prefix=attempts/` wraps the contents in a single top-level folder named after the plugin's
install path (`availability/condition/attempts`), matching what moodle.org expects. `tests/` is
intentionally still included - useful for anyone installing from the zip who wants to run the
suite locally.

## Status / Roadmap

- [x] Support mod_quiz
- [x] Unit tests
- [x] Behat tests
- [x] GDPR
- [x] Publish plugin on GitHub
- [ ] Translate to other languages
- [ ] Review English Language
- [ ] Submit to [Moodle Plugins directory](https://moodle.org/plugins/)
- [ ] Support mod_lesson - lesson has no numeric "attempts allowed" setting equivalent to quiz's.
      Its `maxattempts` field actually means "maximum tries per question", not attempts at the
      whole activity; the only whole-lesson control is `retake`, a yes/no toggle with no cap when
      enabled. Needs a product decision on how (or whether) to map that binary toggle onto this
      plugin's "attempts exhausted" condition before implementing.
- [ ] Support mod_scorm - has a native "Maximum number of attempts", but counting completed
      attempts depends on per-SCO status (`cmi.core.lesson_status` / `scorm_scoes_track`), which is
      more involved than quiz's single finished/in-progress attempt model.
- [ ] Support mod_h5pactivity - attempts are recorded, but it's not yet confirmed whether it
      exposes a native "maximum attempts" setting the same way quiz does; needs investigation into
      `mod/h5pactivity` before committing to an approach.
- [ ] Support mod_assign - only has something attempt-like when `attemptreopenmethod` is
      `manual`/`untilpass`, and there an "attempt" is a resubmission cycle after grading, not a
      closed session the way a quiz or lesson attempt is. Slipperier concept, needs more thought.

## Changelog

Notable milestones, not an exhaustive version-by-version history (see the
[GitHub releases](https://github.com/rigao-alexandre/moodle-availability_attempts/releases) for
that):

- **2020-07 - v1.0** - Initial release. Quiz support, including group/user attempts overrides.
- **2026-08 - v1.1.0** - Moodle 5.x compatibility update: replaced the removed global `\quiz` /
  `\quiz_attempt` classes with `\mod_quiz\quiz_settings` / `\mod_quiz\quiz_attempt` (Moodle 4.2+,
  falling back to the old classes on earlier branches) and the deprecated
  `quiz_attempt::process_finish()` with `process_submit()` + `process_grade_submission()` (Moodle
  5.0). Modernized the PHPUnit suite for current PHPUnit assertions and namespacing conventions.
  Fixed an undeclared `cacheinitparams` property in `frontend.php` that PHP 8.2's dynamic-property
  deprecation turned into a fatal error on the "Add restriction" dialog - found while adding the
  first Behat coverage. Added CI (`moodle-plugin-ci`, matching `quizaccess_failgrade`'s branch
  matrix) and Behat tests.

## Credits

Companion plugin to
[Fail Grade Quiz Access Rule (quizaccess_failgrade)](https://github.com/rigao-alexandre/moodle-quizaccess_failgrade) -
see [Description](#description) above for how the two relate.

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html)
