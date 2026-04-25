<?php
// Prevent direct access.
defined('MOODLE_INTERNAL') || die();

// Check if the current user has permission to view site configuration.
if ($hassiteconfig) {
    // Add a new link to the 'localplugins' section of the Site Administration tree.
    $ADMIN->add('localplugins', new admin_externalpage(
        // Internal unique identifier for this page.
        'local_prereq_enforcer_manage',
        // The display name of the link (drawn from the lang file).
        get_string('pluginname', 'local_prereq_enforcer'),
        // The actual URL the link points to.
        new moodle_url('/local/prereq_enforcer/manage.php')
    ));
}