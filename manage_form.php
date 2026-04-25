<?php
// Prevent direct access.
defined('MOODLE_INTERNAL') || die();
// Include Moodle's form library.
require_once($CFG->libdir . '/formslib.php');

// Define the custom form class extending moodleform.
class local_prereq_enforcer_manage_form extends moodleform {
    
    // The definition method is where you define the form fields.
    public function definition() {
        // Bring in the global database object.
        global $DB;
        // Get a reference to the active form object.
        $mform = $this->_form;
        
        // Fetch a key-value array of all courses (ID => Fullname), excluding the site front page (SITEID).
        $courses = $DB->get_records_select_menu('course', "id != ?", [SITEID], 'fullname ASC', 'id, fullname');

        // Add an 'autocomplete' dropdown field for selecting the target course, searchable by typing.
        $mform->addElement('autocomplete', 'courseid', get_string('targetcourse', 'local_prereq_enforcer'), $courses);
        // Make the target course field mandatory.
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');
        // Add a help icon next to the field using the string defined in the lang file.
        $mform->addHelpButton('courseid', 'prereqid', 'local_prereq_enforcer');

        // Initialize an array to hold radio buttons.
        $radioarray = [];
        // Create radio button for Auto mode (value 1).
        $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('mode_auto', 'local_prereq_enforcer'), 1);
        // Create radio button for Manual mode (value 2).
        $radioarray[] = $mform->createElement('radio', 'mode', '', get_string('mode_manual', 'local_prereq_enforcer'), 2);
        
        // Group the radio buttons together on the form under the "Prerequisite Mode" label.
        $mform->addGroup($radioarray, 'mode_group', get_string('mode_group', 'local_prereq_enforcer'), ['<br>'], false);
        // Set the default selection to Manual mode (2).
        $mform->setDefault('mode', 2); 

        // Add a multi-select autocomplete field for manually picking prerequisite courses.
        $mform->addElement('autocomplete', 'prereqid', get_string('manual_selection', 'local_prereq_enforcer'), $courses, ['multiple' => true]);
        
        // Dynamic form rule: Hide the manual selection dropdown if the "Auto" radio button (value 1) is selected.
        $mform->hideIf('prereqid', 'mode', 'eq', 1);

        // Add the standard Save/Submit button (passing 'false' means no Cancel button is added).
        $this->add_action_buttons(false, get_string('save_rules', 'local_prereq_enforcer'));
    }
}