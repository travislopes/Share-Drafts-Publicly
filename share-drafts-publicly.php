<?php

	/*
	Plugin Name: Share Drafts Publicy
	Description: A plugin that provides a secret link for non-logged in users to view post drafts.
	Version: 1.0.1
	Author: BinaryM Inc - Travis Lopes
	Author URI: http://binarym.com/
	License: GPL2
	*/

	class Share_Drafts_Publicly {
		
		public function __construct() {
			add_action('post_submitbox_misc_actions', array($this, 'add_link_option'));
			add_action('save_post', array($this, 'save_link_option'));
			add_filter('the_posts', array($this, 'show_draft'));
			add_filter('post_row_actions', array($this, 'add_post_row_action'));
		}
		
		// Add option for public draft link to Publish meta box
		public function add_link_option() {
			global $post;
			if($post->post_status === 'draft' || $post->post_status === 'pending') {
				$secret_key = get_post_meta($post->ID, '_draft_secret_key', true);
				echo '<div class="misc-pub-section">
					<input id="make-draft-public" name="make-draft-public" type="checkbox" value="true"'. ((!empty($secret_key)) ? ' checked="checked"' : '') .' /> 
					<label for="make-draft-public" class="selectit">'. __('Make draft public?') .'</label> 
					'. ((!empty($secret_key)) ? '<a href="'. add_query_arg('secret', $secret_key, get_permalink($post->ID)) .'">'. __('Public Link') .'</a>' : '')
				.'</div>';
			}
		}
		
		// Add or remove post meta for public draft link on post save
		public function save_link_option($post_id) {
			if ((defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id) || empty($post_id))
				return false;

			if(isset($_POST['make-draft-public']) && $_POST['make-draft-public'] === 'true') {
				add_post_meta($post_id, '_draft_secret_key', wp_generate_password(6, false, false), true);
			} else {
				delete_post_meta($post_id, '_draft_secret_key');	
			}
		}
		
		// Make draft viewable if secret key is set and correct for post
		public function show_draft($posts) {
			global $wp_query, $wpdb;
			if(isset($_GET['secret']) && $wp_query->is_main_query() && get_post_meta($wp_query->query_vars['p'], '_draft_secret_key', true) === $_GET['secret'])
				$posts = $wpdb->get_results($wp_query->request);
			return $posts;
		}
		
		// Add Public Draft Link to post actions
		public function add_post_row_action($actions) {
			global $post;
			
			$secret_key = get_post_meta($post->ID, '_draft_secret_key', true);
			if(!empty($secret_key))
				$actions['public_link'] = '<a href="'. add_query_arg('secret', $secret_key, get_permalink($post->ID)) .'">'. __('Public Draft Link') .'</a>';
				
			return $actions;
		}
		
	}
	
	$share_drafts_publicly = new Share_Drafts_Publicly();