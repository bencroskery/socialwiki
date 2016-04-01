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
 * Create a new page.
 *
 * This will show options for creating a new page.
 *
 * @package   mod_socialwiki
 * @copyright 2015 NMAI-lab
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require($CFG->dirroot . '/mod/socialwiki/locallib.php');
require($CFG->dirroot . '/mod/socialwiki/pagelib.php');
require($CFG->dirroot . '/mod/socialwiki/create_form.php');

// New action will display a form contains page title and page format selections.
// Create action will create a new page in db, and redirect to page editing page.
$action = optional_param('action', 'new', PARAM_TEXT); // Two actions: new and create.
$title  = optional_param('title', get_string('newpage', 'socialwiki'), PARAM_TEXT); // Title of the new page, can be empty.
$wid    = optional_param('wid', 0, PARAM_INT);   // Wiki ID.
$swid   = optional_param('swid', 0, PARAM_INT);  // Subwiki ID.
$group  = optional_param('group', 0, PARAM_INT); // Group ID.
$uid    = optional_param('uid', 0, PARAM_INT);   // User ID.

// Create action must be submitted by moodle form so sesskey must be checked.
if ($action == 'create') {
    if (!confirm_sesskey()) {
        print_error('invalidsesskey', 'socialwiki');
    }
}

if (!empty($swid)) {
    $subwiki = socialwiki_get_subwiki($swid);

    if (!$wiki = socialwiki_get_wiki($subwiki->wikiid)) {
        print_error('invalidwikiid', 'socialwiki');
    }
} else {
    $subwiki = socialwiki_get_subwiki_by_group($wid, $group, $uid);
    if (!$wiki = socialwiki_get_wiki($wid)) {
        print_error('invalidwikiid', 'socialwiki');
    }
}

if (!$cm = get_coursemodule_from_instance('socialwiki', $wiki->id)) {
    print_error('invalidcoursemoduleid', 'socialwiki');
}

$groups = new stdClass();
if (groups_get_activity_groupmode($cm)) {
    $modulecontext = context_module::instance($cm->id);
    $canaccessgroups = has_capability('moodle/site:accessallgroups', $modulecontext);
    if ($canaccessgroups) {
        $groups->availablegroups = groups_get_all_groups($cm->course);
        $allpart = new stdClass();
        $allpart->id = '0';
        $allpart->name = get_string('allparticipants');
        array_unshift($groups->availablegroups, $allpart);
    } else {
        $groups->availablegroups = groups_get_all_groups($cm->course, $USER->id);
    }
    if (!empty($group)) {
        $groups->currentgroup = $group;
    } else {
        $groups->currentgroup = groups_get_activity_group($cm);
    }
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_login($course, true, $cm);

$wikipage = new page_socialwiki_create($wiki, $subwiki, $cm);

if (!empty($swid)) {
    $wikipage->set_gid($subwiki->groupid);
    $wikipage->set_uid($subwiki->userid);
    $wikipage->set_swid($swid);
} else {
    $wikipage->set_wid($wid);
    $wikipage->set_gid($group);
    $wikipage->set_uid($uid);
}

$wikipage->set_availablegroups($groups);
$wikipage->set_title($title);

// Set page action, and initialise moodle form.
$wikipage->set_action($action);

if ($action === 'create') {
    $newpageid = $wikipage->create_page($title);
    // Have the user like the page they are creating.
    $subwikiid = socialwiki_get_page($newpageid)->subwikiid;
    socialwiki_add_like($USER->id, $newpageid, $subwikiid);
    redirect($CFG->wwwroot . '/mod/socialwiki/edit.php?pageid=' . $newpageid . "&makenew=1");
} else {
    // Go straight to editing if we know the page title and we're in force format mode.
    if ((int) $wiki->forceformat == 1 && $title != get_string('newpage', 'socialwiki')) {
        $newpageid = $wikipage->create_page($title);
        // Have the user like the page they are creating.
        $subwikiid = socialwiki_get_page($newpageid)->subwikiid;
        socialwiki_add_like($USER->id, $newpageid, $subwikiid);
        redirect($CFG->wwwroot . '/mod/socialwiki/edit.php?pageid=' . $newpageid . "&makenew=1");
    } else {
        $wikipage->print_header();
        $wikipage->print_content($title);
        $wikipage->print_footer();
    }
}