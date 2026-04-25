<?php
// Prevents direct access to this script; it must be run within the Moodle environment.
defined('MOODLE_INTERNAL') || die();

// Hook triggered when Moodle builds the navigation for a course page.
function local_prereq_enforcer_extend_navigation_course($navigation, $course, $context) {
    // Bring in Moodle's global page and script variables.
    global $PAGE, $SCRIPT;

    // If the user is not actively viewing the main course page, do nothing.
    if ($SCRIPT !== '/course/view.php') {
        return;
    }

    // Ensure the navigation being built is for the course the user is currently viewing.
    if ($PAGE->course->id != $course->id) {
        return;
    }

    // Call the custom function to check prerequisites and potentially redirect the user.
    local_prereq_enforcer_check_and_redirect($course->id);
}

// Hook triggered when Moodle builds the navigation for a specific activity/module inside a course.
function local_prereq_enforcer_extend_navigation_module($navigation, $cm) {
    // Bring in global variables.
    global $PAGE, $SCRIPT;

    // If the script being accessed doesn't start with '/mod/' (meaning it's not a course module), do nothing.
    if (strpos($SCRIPT, '/mod/') !== 0) {
        return;
    }

    // Ensure the module being built matches the module the user is actually viewing.
    if (isset($PAGE->cm->id) && $PAGE->cm->id != $cm->id) {
        return;
    }

    // Pass the parent course ID of this module to the checker function to see if the whole course is restricted.
    local_prereq_enforcer_check_and_redirect($cm->course);
}

// The core logic function that checks rules and enforces them.
function local_prereq_enforcer_check_and_redirect($courseid) {
    // Bring in Moodle's database, user, and config globals.
    global $DB, $USER, $CFG;

    // Site administrators and guest users bypass prerequisite rules.
    if (is_siteadmin() || isguestuser()) {
        return;
    }

    // Fetch all prerequisite rules for this specific course ID from the custom database table.
    $rules = $DB->get_records('local_prereq_enforcer', ['courseid' => $courseid]);

    // If there are no rules for this course, allow access (do nothing).
    if (empty($rules)) {
        return; 
    }

    // Include Moodle's core course completion library.
    require_once($CFG->libdir . '/completionlib.php');

    // Initialize an empty array to store the names of courses the user still needs to complete.
    $missingcourses = [];

    // Loop through every prerequisite rule found for this course.
    foreach ($rules as $rule) {
        // Fetch the details of the required course from the main 'course' table.
        $prereqcourse = $DB->get_record('course', ['id' => $rule->prereqid], '*', IGNORE_MISSING);
        
        // If the required course no longer exists in Moodle, skip this rule.
        if (!$prereqcourse) {
            continue; 
        }

        // Create a completion_info object to check the user's progress in the prerequisite course.
        $completion = new \completion_info($prereqcourse);
        
        // Check if the current user has NOT completed the prerequisite course.
        if (!$completion->is_course_complete($USER->id)) {
            // If not complete, add the course's full name to the missing list.
            $missingcourses[] = $prereqcourse->fullname;
        }
    }

    // If the user is missing one or more required courses...
    if (!empty($missingcourses)) {
        // Prepare to redirect them back to their dashboard/my courses page.
        $redirecturl = new \moodle_url('/my/courses.php');
        // Convert the array of missing course names into a comma-separated string.
        $missinglist = implode(', ', $missingcourses);
        // Build the error message using the translated string and the list of missing courses.
        $errormsg = get_string('prereq_missing', 'local_prereq_enforcer') . ' ' . $missinglist;
        // Queue the error message to display on the next page load.
        \core\notification::error($errormsg);
        // Execute the redirect.
        redirect($redirecturl);
        // Stop all further script execution to ensure the user cannot load the restricted page.
        exit;
    }
}