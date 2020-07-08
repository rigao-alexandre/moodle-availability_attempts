# Moodle - Attempts Availability Condition (availability_attempts)
## Description
The purpose of this plugin is to restrict access to modules and sections only if the user has exhausted the attempts of the given module.
## Supported Modules
### Quiz (mod_quiz)
You can use Quiz as a restriction rule only if it does not have unlimited attempts
## Use Case
 - Display a message that the user has not yet take all the attempts and that there may be a chance that he will reach the required grade
 - A course consisting of several quizzes (with more than one attempt and considering the highest grade) and a grade recovery quiz in case the user does not reach the required average, however it is not to be released while there is the possibility of obtaining the grade by performing the other modules
# Instalation
Please refer to the official documentation: [Installing Plugins](https://docs.moodle.org/en/Installing_plugins)
## Requirements
 - Moodle 3.9 (2020060900)
# Status / Roadmap
- [X] Support mod_quiz
- [X] Publish plugin on GitHub
- [X] Unit tests
- [X] GDPR
- [ ] Translate to other languages
- [ ] Review English Language
- [ ] Submit to [Moodle Plugins directory](https://moodle.org/plugins/)
- [ ] Support other modules (e.g. Assign (mod_assign))
- [ ] Behat tests
- [ ] Submit to [Moodle Plugins directory](https://moodle.org/plugins/)
# Development
Please, use GitHub for issues.
## License
Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html)