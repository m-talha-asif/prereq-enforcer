# Prerequisite Course Enforcer (local_prereq_enforcer)

**Prerequisite Course Enforcer** is a local plugin for Moodle that allows administrators to strictly control course access based on the completion of other courses. If a user attempts to access a restricted course (or any activity within it) without having completed the required prerequisite courses, they are seamlessly redirected back to their dashboard with an error message detailing what they are missing.

## ✨ Features

* **Strict Access Control**: Intercepts navigation at both the course and module level to ensure students cannot bypass prerequisite requirements.
* **Global Management Interface**: Manage all prerequisite rules from a single centralized admin page.
* **Manual Assignment**: Search and assign one or multiple prerequisite courses to a target course using Moodle's native autocomplete fields.
* **Auto-Assignment Mode**: Automatically bulk-assign all previously created courses (based on creation date) as prerequisites for a newly created target course.
* **Relies on Core Completion**: Integrates directly with Moodle's core Course Completion API (`completion_info`) to verify student progress.
* **Graceful Handling of Deletions**: Active rules display safely even if the underlying target or prerequisite course is later deleted from Moodle.

## 📋 Requirements

* **Moodle Version:** 4.1 or higher (Requires `2022112800`)
* **Course Completion:** Must be enabled at the site level and within the respective prerequisite courses.

## 🚀 Installation

1. Download the plugin and extract the files.
2. Rename the extracted folder to `prereq_enforcer` (if it isn't already).
3. Place the `prereq_enforcer` folder into the `local/` directory of your Moodle installation.
    * The path should be: `[moodle_root]/local/prereq_enforcer`
4. Log in to your Moodle site as an Administrator.
5. Go to **Site administration > Notifications** to complete the plugin installation and database upgrades.

## ⚙️ Usage & Configuration

Once installed, administrators can manage prerequisite rules via the Moodle Site Administration menu:

1. Navigate to **Site administration > Plugins > Local plugins > Prerequisite Course Enforcer**.
2. **Add a Rule**:
    * **Target Course**: Search for the course you want to restrict access to.
    * **Prerequisite Mode**:
        * *Auto-assign*: Automatically makes all courses created *before* the target course mandatory prerequisites.
        * *Manual Course Selection*: Choose one or multiple specific courses that must be completed first.
3. Click **Save Prerequisite Rules**.
4. **Manage Rules**: View all active rules in the table at the bottom of the page. You can delete individual rules using the provided "Delete" buttons.

## 🛠️ How it Works

This plugin utilizes Moodle's navigation hooks (`extend_navigation_course` and `extend_navigation_module`) within `lib.php` to check permissions *before* the page fully loads. 

If a rule exists for the requested course, the plugin evaluates the current user's completion status for all required prerequisites. If requirements are not met, execution stops, and the user is redirected to `/my/courses.php` with a standard Moodle notification detailing the missing courses. Site administrators and guest users automatically bypass these checks.

## 📄 License
This plugin is developed for Moodle and inherits the GNU General Public License (GPL) standards utilized by the Moodle core platform.
