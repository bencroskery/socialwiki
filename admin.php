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
 * Delete wiki pages or versions
 *
 * This will show options for deleting wiki pages or purging page versions
 * If user have wiki:managewiki ability then only this page will show delete
 * options
 *
 * @package mod_socialwiki
 * @copyright 2011 Rajesh Taneja
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require($CFG->dirroot . '/mod/socialwiki/locallib.php');
require($CFG->dirroot . '/mod/socialwiki/pagelib.php');

$pageid = required_param('pageid', PARAM_INT); // Page ID.
$delete = optional_param('delete', 0, PARAM_INT); // ID of the page to be deleted.
$option = optional_param('option', 1, PARAM_INT); // Option ID.
$listall = optional_param('listall', 0, PARAM_INT); // List all pages.
$toversion = optional_param('toversion', 0, PARAM_INT); // Max version to be deleted.
$fromversion = optional_param('fromversion', 0, PARAM_INT); // Min version to be deleted.

if (!$page = socialwiki_get_page($pageid)) {
    print_error('incorrectpageid', 'socialwiki');
}
if (!$subwiki = socialwiki_get_subwiki($page->subwikiid)) {
    print_error('incorrectsubwikiid', 'socialwiki');
}
if (!$cm = get_coursemodule_from_instance("socialwiki", $subwiki->wikiid)) {
    print_error('invalidcoursemodule');
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
if (!$wiki = socialwiki_get_wiki($subwiki->wikiid)) {
    print_error('incorrectwikiid', 'socialwiki');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/socialwiki:managewiki', $context);

add_to_log($course->id, "socialwiki", "admin", "admin.php?pageid=".$page->id, $page->id, $cm->id);

// Delete page if a page ID to delete was supplied.
if (!empty($delete) && confirm_sesskey()) {
    socialwiki_delete_pages($context, $delete, $page->subwikiid);
    // When current wiki page is deleted, then redirect user to create that page, as
    // current pageid is invalid after deletion.
    if ($pageid == $delete) {
        $params = array('id' => $cm->id);
        $url = new moodle_url('/mod/socialwiki/home.php', $params);
        redirect($url);
    }
}

// Delete version if toversion and fromversion are set.
if (!empty($toversion) && !empty($fromversion) && confirm_sesskey()) {
    // Make sure all versions should not be deleted...
    $versioncount = socialwiki_count_wiki_page_versions($pageid);
    $versioncount -= 1; // Ignore version 0.
    $totalversionstodelete = $toversion - $fromversion;
    $totalversionstodelete += 1; // Added 1 as toversion should be included.

    if (($totalversionstodelete >= $versioncount) || ($versioncount <= 1)) {
        print_error('incorrectdeleteversions', 'socialwiki');
    } else {
        $versions = array();
        for ($i = $fromversion; $i <= $toversion; $i++) {
            // Add all version to deletion list which exist.
            if (socialwiki_get_wiki_page_version($pageid, $i)) {
                array_push($versions, $i);
            }
        }
        $purgeversions[$pageid] = $versions;
        socialwiki_delete_page_versions($purgeversions);
    }
}

$wikipage = new page_socialwiki_admin($wiki, $subwiki, $cm);

$wikipage->set_page($page);
$wikipage->print_header();

$wikipage->set_view($option, empty($listall) ? false : true);
$wikipage->print_content();

$wikipage->print_footer();
