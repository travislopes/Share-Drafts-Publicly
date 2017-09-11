# Share Drafts Publicly
**Contributors:** travislopes, pereirinha

**Requires at least:** 3.0

**Tested up to:** 4.8.1

### Description
Need to show a post to someone but they don't have a WordPress user account? Share Drafts Publicly allows you to generate a private URL for non-users to view post drafts with a simple click of a button.

### Changelog
* v1.1.5
	* Added filters for meta box priority: `sdp_meta_box_priority`;
	* Added filters for labels: `sdp_label_meta_box_title`, `sdp_label_make_public`, `sdp_label_make_private`, `sdp_label_public_link`, `sdp_label_invalid_request`, `sdp_label_error_make_private`, `sdp_label_error_make_public`.
* v1.1.4
	* Added security enhancements.
* v1.1.3
	* Fixed PHP error when determining if draft should be shown.
* v1.1.2
	* Fixed PHP notice when getting draft URL or draft status.
	* Fixed WordPress coding standards issues.
* v1.1.1
	* Added "sdp_allowed_post_status" filter for setting which post statuses will present the "Share Drafts Publicly" metabox.
* v1.1
	* Moved public draft controls to separate meta box.
* v1.0.1
	* Fixed bug where secret key changes on saving a new draft
* v1.0.0
	* Initial release

### Installation
#### Requirements
* WordPress version 3.0 and later (tested at 3.5.1)

#### Installation
1. Unpack the download package.
1. Upload all files to the `/wp-content/plugins/` directory, with folder
1. Activate the plugin through the 'Plugins' menu in WordPress
