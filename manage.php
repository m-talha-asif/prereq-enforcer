<?php
// Include Moodle's main configuration file to set up the environment.
require_once('../../config.php');
// Include Moodle's admin library for admin page setup.
require_once($CFG->libdir . '/adminlib.php');
// Include the custom form definition file.
require_once('manage_form.php'); 

// Ensure the user is logged in.
require_login();
// Tell Moodle this page is part of the site administration tree (checks permissions automatically).
admin_externalpage_setup('local_prereq_enforcer_manage');

// Check the URL for a 'delete' parameter, defaulting to 0, ensuring it's an integer.
$deleteid = optional_param('delete', 0, PARAM_INT);
// If a delete ID was passed in the URL...
if ($deleteid > 0) {
    // Verify the session key to prevent Cross-Site Request Forgery (CSRF) attacks.
    require_sesskey(); 
    // Delete the specific rule from the custom database table.
    $DB->delete_records('local_prereq_enforcer', ['id' => $deleteid]);
    // Queue a success notification.
    \core\notification::success(get_string('success_deleted', 'local_prereq_enforcer'));
    // Redirect back to this page to refresh the state and remove the URL parameter.
    redirect(new moodle_url('/local/prereq_enforcer/manage.php'));
}

// Instantiate the custom Moodle form defined in manage_form.php.
$mform = new local_prereq_enforcer_manage_form();

// If the form was submitted and the data is valid...
if ($data = $mform->get_data()) {
    // Keep track of how many new rules are successfully added.
    $added_count = 0;
    // Extract the target course ID, handling cases where it might be passed as an array.
    $courseid = is_array($data->courseid) ? (int)reset($data->courseid) : (int)$data->courseid;
    
    // If a valid target course was selected...
    if ($courseid > 0) {
        // Fetch the target course record from the database.
        $targetcourse = $DB->get_record('course', ['id' => $courseid]);
        // Initialize an array to hold the IDs of the prerequisite courses.
        $prereq_array = [];

        // If Mode 1 (Auto-assign older courses) was selected and the target course exists...
        if ($data->mode == 1 && $targetcourse) {
            // Find all courses created before the target course (excluding the site front page).
            $older_courses = $DB->get_records_select('course', 'id != ? AND timecreated < ?', [SITEID, $targetcourse->timecreated], 'timecreated ASC', 'id');
            // Add all those older course IDs to the array.
            foreach ($older_courses as $old_course) {
                $prereq_array[] = $old_course->id;
            }
        // If Mode 2 (Manual selection) was selected and courses were chosen...
        } else if ($data->mode == 2 && isset($data->prereqid)) {
            // Put the manually selected IDs into the array (handling both array and comma-separated string formats).
            $prereq_array = is_array($data->prereqid) ? $data->prereqid : explode(',', $data->prereqid);
        }

        // Ensure all prerequisite IDs are strictly integers.
        $prereq_array = array_map('intval', $prereq_array);
        // Filter out invalid IDs (e.g., 0) and prevent a course from being a prerequisite for itself.
        $prereq_array = array_filter($prereq_array, function($id) use ($courseid) {
            return $id > 0 && $id != $courseid;
        });

        // If we have valid prerequisite courses to add...
        if (!empty($prereq_array)) {
            // Fetch any prerequisite rules that already exist for this target course to avoid duplicates.
            $existing_rules = $DB->get_fieldset_select('local_prereq_enforcer', 'prereqid', 'courseid = ?', [$courseid]);
            // If none exist, default to an empty array.
            if (!$existing_rules) {
                $existing_rules = [];
            }
            // Compare the proposed rules against existing rules and only keep the new, unique ones.
            $new_rules = array_diff($prereq_array, $existing_rules);

            // Loop through the new rules.
            foreach ($new_rules as $reqid) {
                // Create a standard PHP object to represent the new database row.
                $newrule = new stdClass();
                $newrule->courseid = $courseid;
                $newrule->prereqid = $reqid; 
                // Insert the row into the custom table.
                $DB->insert_record('local_prereq_enforcer', $newrule);
                // Increment the success counter.
                $added_count++;
            }
        }
    }
    
    // If at least one rule was added, show a success message.
    if ($added_count > 0) {
        \core\notification::success(get_string('success_added', 'local_prereq_enforcer', $added_count));
    // Otherwise, warn the user that nothing changed.
    } else {
        \core\notification::warning(get_string('warning_no_add', 'local_prereq_enforcer'));
    }
    // Refresh the page to clear the form.
    redirect(new moodle_url('/local/prereq_enforcer/manage.php'));
}

// Start outputting the HTML to the browser (header).
echo $OUTPUT->header();
// Output the main page heading.
echo $OUTPUT->heading(get_string('manage_heading', 'local_prereq_enforcer'));

// Render the Moodle form.
$mform->display();

// Output a sub-heading for the list of active rules.
echo $OUTPUT->heading(get_string('active_rules', 'local_prereq_enforcer'), 3);
// Fetch all existing rules from the database.
$rules = $DB->get_records('local_prereq_enforcer');

// If rules exist, build a table to display them.
if (!empty($rules)) {
    // Instantiate a Moodle HTML table object.
    $table = new html_table();
    // Define the table headers.
    $table->head = [
        get_string('table_target', 'local_prereq_enforcer'), 
        get_string('table_requires', 'local_prereq_enforcer'), 
        get_string('table_action', 'local_prereq_enforcer')
    ];
    // Add Bootstrap styling classes to the table.
    $table->attributes['class'] = 'table table-striped mt-3';
    // Initialize the table data array.
    $table->data = [];

    // Loop through every rule in the database.
    foreach ($rules as $rule) {
        // Fetch the names of the target and prerequisite courses.
        $target = $DB->get_record('course', ['id' => $rule->courseid], 'fullname', IGNORE_MISSING);
        $req = $DB->get_record('course', ['id' => $rule->prereqid], 'fullname', IGNORE_MISSING);
        
        // If a course was deleted from Moodle, show a fallback "Deleted" string instead of breaking.
        $t_name = $target ? $target->fullname : get_string('deleted_course', 'local_prereq_enforcer', $rule->courseid);
        $r_name = $req ? $req->fullname : get_string('deleted_course', 'local_prereq_enforcer', $rule->prereqid);

        // Build the URL for the delete button, including the mandatory session key.
        $deleteurl = new moodle_url('/local/prereq_enforcer/manage.php', ['delete' => $rule->id, 'sesskey' => sesskey()]);
        // Generate the HTML for the delete button.
        $deletebtn = html_writer::link($deleteurl, get_string('delete', 'local_prereq_enforcer'), ['class' => 'btn btn-danger btn-sm']);
        // Add the row data to the table.
        $table->data[] = [$t_name, $r_name, $deletebtn];
    }
    // Render and output the table.
    echo html_writer::table($table);
} else {
    // If no rules exist, show a friendly info alert.
    echo '<div class="alert alert-info mt-3">' . get_string('no_rules', 'local_prereq_enforcer') . '</div>';
}

// Output the footer to close out the HTML page.
echo $OUTPUT->footer();