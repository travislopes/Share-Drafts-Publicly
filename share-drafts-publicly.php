<?php
/**
Plugin Name: Share Drafts Publicy
Description: Provide a secret link to non-logged in users to view post drafts.
Version: 1.1.3
Author: Travis Lopes
Author URI: http://travislop.es
License: GPL2
Text Domain: share_drafts_publicly
Domain Path: /languages
 */

class Share_Drafts_Publicly {

	/**
	 * Instance of Share Drafts Publicly class
	 *
	 * @var    object
	 * @access private
	 * @static
	 */
	private static $_instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Register needed hooks.
	 *
	 * @access public
	 */
	public function __construct() {

		// Enqueue needed scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'styles' ) );

		// Process AJAX status change.
		add_action( 'wp_ajax_share_drafts_publicly', array( $this, 'ajax_change_status' ) );

		// Add meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Show draft if provided secret key.
		add_filter( 'the_posts', array( $this, 'show_draft_publicly' ), 10, 2 );

		// Add public draft link to post actions.
		add_filter( 'post_row_actions', array( $this, 'add_post_row_action' ), 10, 2 );

	}

	/**
	 * Enqueue needed scripts.
	 *
	 * @access public
	 */
	public function scripts() {

		wp_register_script( 'share-drafts-publicly', plugins_url( 'share-drafts-publicly/js/share-drafts-publicly.js' ), array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/share-drafts-publicly.js' ), true );
		wp_localize_script( 'share-drafts-publicly', 'SDP', array( 'postId' => get_the_ID() ? get_the_ID() : absint( $_GET['post'] ) ) );

		if ( $this->enqueue_script() ) {
			wp_enqueue_script( 'share-drafts-publicly' );
		}

	}

	/**
	 * Enqueue needed styles.
	 *
	 * @access public
	 */
	public function styles() {

		wp_register_style( 'share-drafts-publicly', plugins_url( 'share-drafts-publicly/css/share-drafts-publicly.css' ) );

		if ( $this->enqueue_script() ) {
			wp_enqueue_style( 'share-drafts-publicly' );
		}

	}

	/**
	 * Helper function to determine if script should be enqueued.
	 *
	 * @access public
	 * @return bool
	 */
	public function enqueue_script() {

		// Get current page.
		global $pagenow;

		return 'post.php' === $pagenow;

	}

	/**
	 * Add meta box.
	 *
	 * @access public
	 * @return void
	 */
	public function add_meta_box() {

		$post_id          = isset( $_GET['post'] ) ? sanitize_text_field( wp_unslash( $_GET['post'] ) ) : null;
		$post_status      = get_post_status( $post_id );
		$allowed_statuses = apply_filters( 'sdp_allowed_post_status', array( 'draft', 'pending', 'auto-draft', 'future' ) );

		if ( in_array( $post_status, $allowed_statuses ) ) {
			add_meta_box( 'share_drafts_publicly', esc_html__( 'Share Drafts Publicly', 'share_drafts_publicly' ), array( $this, 'display_meta_box' ), null, 'side', 'low' );
		}

	}

	/**
	 * Display meta box contents.
	 *
	 * @access public
	 * @return void
	 */
	public function display_meta_box() {

		// Get post ID and draft URL.
		$post_id   = get_the_ID() ? get_the_ID() : sanitize_text_field( wp_unslash( $_GET['post'] ) );
		$draft_url = $this->get_draft_url();

		// Prepare button styling.
		$public_styling  = $this->is_draft_public() ? 'display:none;' : 'display:inline-block;';
		$private_styling = ! $this->is_draft_public() ? 'display:none;' : 'display:inline-block;';

		/* Prepare HTML for meta box. */
		$html  = '<input id="sdp_link" type="text" value="' . esc_attr( $draft_url ) . '" style="' . $private_styling . '" class="widefat" onclick="this.setSelectionRange( 0, this.value.length );" readonly />';
		$html .= '<input id="sdp_make_public" class="button" type="button" style="' . $public_styling . '" name="sdp_make_public" onclick="make_draft_public(' . esc_attr( $post_id ) . ');" value="' . esc_attr__( 'Make Draft Public', 'share_drafts_publicly' ) . '" />';
		$html .= '<input id="sdp_make_private" class="button" type="button" style="' . $private_styling . '" name="sdp_make_private" onclick="make_draft_private(' . esc_attr( $post_id ) . ');" value="' . esc_attr__( 'Make Draft Private', 'share_drafts_publicly' ) . '" />';
		$html .= '<span class="spinner"></span>';

		echo $html;

	}

	/**
	 * Show draft if provided secret key.
	 *
	 * @access public
	 * @param  array    $posts The array of retrieved posts.
	 * @param  WP_Query $wp_query The WP_Query instance.
	 * @return array $posts
	 */
	public function show_draft_publicly( $posts, $wp_query ) {

		global $wpdb;

		if ( isset( $_GET['secret'] ) && $wp_query->is_main_query() && get_post_meta( $wp_query->query_vars['p'], '_draft_secret_key', true ) === $_GET['secret'] ) {
			$posts = $wpdb->get_results( $wp_query->request );
		}

		return $posts;

	}

	/**
	 * Add public draft link to post actions.
	 *
	 * @access public
	 * @param  array   $actions An array of row action links.
	 * @param  WP_Post $post The post object.
	 * @return array $actions
	 */
	public function add_post_row_action( $actions, $post ) {

		if ( $this->is_draft_public( $post->ID ) ) {
			$actions['public_link'] = '<a href="' . $this->get_draft_url( $post->ID ) . '">' . esc_html__( 'Public Draft Link', 'share_drafts_publicly' ) . '</a>';
		}

		return $actions;

	}

	/**
	 * Change draft status of post.
	 *
	 * @access public
	 */
	public function ajax_change_status() {

		// Get provided informaton.
		$make    = sanitize_text_field( wp_unslash( $_GET['make'] ) );
		$post_id = sanitize_text_field( wp_unslash( $_GET['post_id'] ) );

		switch ( $make ) {

			case 'private':
				$make_private = $this->make_draft_private( $post_id );

				if ( ! $make_private ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Unable to make draft private. Please try again.', 'share_drafts_publicly' ) ) );
				} else {
					wp_send_json_success();
				}

			case 'public':
				$make_public = $this->make_draft_public( $post_id );

				if ( ! $make_public ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Unable to make draft public. Please try again.', 'share_drafts_publicly' ) ) );
				} else {
					wp_send_json_success( array( 'permalink' => $make_public ) );
				}

		}

	}

	/**
	 * Get public draft URL.
	 *
	 * @access public
	 * @param  int    $post_id (default: null) Post ID.
	 * @param  string $secret_key (default: null) Secret key.
	 * @return string
	 */
	public function get_draft_url( $post_id = null, $secret_key = null ) {

		// Get the post ID if not set.
		if ( empty( $post_id ) && isset( $_GET['post'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
		}

		// Get the permalink.
		$permalink = get_permalink( $post_id );

		// Get draft secret key.
		$secret_key = empty( $secret_key ) ? get_post_meta( $post_id, '_draft_secret_key', true ) : $secret_key;

		if ( empty( $secret_key ) ) {
			return $permalink;
		} else {
			return add_query_arg( 'secret', $secret_key, $permalink );
		}

	}

	/**
	 * Determine if draft is being shared publicly.
	 *
	 * @access public
	 * @param  int $post_id (default: null) Post ID.
	 * @return bool
	 */
	public function is_draft_public( $post_id = null ) {

		// Get the post ID if not set.
		if ( empty( $post_id ) && isset( $_GET['post'] ) ) {
			$post_id = sanitize_text_field( wp_unslash( $_GET['post'] ) );
		}

		// Get draft visibility status.
		$is_draft_public = get_post_meta( $post_id, '_draft_secret_key', true );

		return strlen( $is_draft_public ) > 0;

	}

	/**
	 * Make draft private.
	 *
	 * @access public
	 * @param  int $post_id Post ID to make private.
	 * @return bool
	 */
	public function make_draft_private( $post_id ) {

		// Delete post meta.
		return delete_post_meta( $post_id, '_draft_secret_key' );

	}

	/**
	 * Make draft public.
	 *
	 * @access public
	 * @param  int $post_id Post ID to make public.
	 * @return string|bool
	 */
	public function make_draft_public( $post_id ) {

		// Generate secret key.
		$secret_key = wp_generate_password( 6, false, false );

		// Add secret key to post meta.
		$secret_key_status = add_post_meta( $post_id, '_draft_secret_key', $secret_key, true );

		// Return draft URL.
		return $secret_key_status ? $this->get_draft_url( $post_id, $secret_key ) : false;

	}

}

Share_Drafts_Publicly::get_instance();
