<?php
/**
Plugin Name: Share Drafts Publicy
Description: Provide a secret link to non-logged in users to view post drafts.
Version: 1.1.5
Author: Travis Lopes
Author URI: http://travislop.es
License: GPL2
Text Domain: share_drafts_publicly
Domain Path: /languages
*/

class Share_Drafts_Publicly {

	/**
	 * Instance of Share Drafts Publicly class.
	 *
	 * @var    object
	 * @access private
	 * @static
	 */
	private static $_instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @since  1.1
	 * @access public
	 * @static
	 *
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
	 * @since  1.0
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
	 * @since  1.1
	 * @access public
	 *
	 * @uses Share_Drafts_Publicly::enqueue_script()
	 */
	public function scripts() {

		// Localize strings.
		$localization = array(
			'nonce'  => wp_create_nonce( 'share-drafts-publicly' ),
			'postId' => get_the_ID() ? get_the_ID() : ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 ),
		);

		wp_register_script( 'share-drafts-publicly', plugin_dir_url( __FILE__ ) . 'js/share-drafts-publicly.js', array( 'jquery' ), filemtime( plugin_dir_path( __FILE__ ) . 'js/share-drafts-publicly.js' ), true );
		wp_localize_script( 'share-drafts-publicly', 'shareDraftsPublicly', $localization );

		if ( $this->enqueue_script() ) {
			wp_enqueue_script( 'share-drafts-publicly' );
		}

	}

	/**
	 * Enqueue needed styles.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @uses Share_Drafts_Publicly::enqueue_script()
	 */
	public function styles() {

		wp_register_style( 'share-drafts-publicly', plugin_dir_url( __FILE__ ) . 'css/share-drafts-publicly.css' );

		if ( $this->enqueue_script() ) {
			wp_enqueue_style( 'share-drafts-publicly' );
		}

	}

	/**
	 * Helper function to determine if script should be enqueued.
	 *
	 * @since  1.1
	 * @access public
	 *
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
	 * @since  1.1
	 * @access public
	 */
	public function add_meta_box() {

		// Get current post ID and status.
		$post_id     = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : null;
		$post_status = get_post_status( $post_id );

		/**
		 * Modify which post statuses are allowed to be shared publicly.
		 *
		 * @since 1.1.1
		 *
		 * @param array $allowed_statuses Post statuses allowed to be shared publicly.
		 */
		$allowed_statuses = apply_filters( 'sdp_allowed_post_status', array( 'draft', 'pending', 'auto-draft', 'future' ) );

		if ( in_array( $post_status, $allowed_statuses ) ) {
			add_meta_box( 'share_drafts_publicly', esc_html( apply_filters( 'sdp_meta_box_title', __( 'Share Drafts Publicly', 'share_drafts_publicly' ) ) ), array( $this, 'display_meta_box' ), null, 'side', apply_filters( 'sdp_meta_box_priority', 'low' ) );
		}

	}

	/**
	 * Display meta box contents.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @uses Share_Drafts_Publicly::is_draft_public()
	 */
	public function display_meta_box() {

		// Get draft URL.
		$draft_url = $this->get_draft_url();

		// Prepare button styling.
		$public_styling  = $this->is_draft_public() ? 'display:none;' : 'display:inline-block;';
		$private_styling = $this->is_draft_public() ? 'display:inline-block;' : 'display:none;';

		// Prepare HTML for meta box.
		ob_start();
		?>

		<input id="sdp_link" type="text" value="<?php echo esc_url( $draft_url ); ?>" style="<?php echo esc_attr( $private_styling ); ?>" class="widefat" onclick="this.setSelectionRange( 0, this.value.length );" readonly />
		<input id="sdp_make_public" class="button" type="button" style="<?php echo esc_attr( $public_styling ); ?>" name="sdp_make_public" value="<?php echo esc_attr( apply_filters( 'sdp_label_make_public', __( 'Make Draft Public', 'share_drafts_publicly' ) ) ); ?>" />
		<input id="sdp_make_private" class="button" type="button" style="<?php echo esc_attr( $private_styling ); ?>" name="sdp_make_private" value="<?php echo esc_attr( apply_filters( 'sdp_label_make_private', __( 'Make Draft Private', 'share_drafts_publicly' ) ) ); ?>" />
		<span class="spinner"></span>

		<?php
		$html = ob_get_contents();
		ob_clean();

		echo $html;

	}

	/**
	 * Show draft if provided secret key.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param array    $posts    The array of retrieved posts.
	 * @param WP_Query $wp_query The WP_Query instance.
	 *
	 * @uses wpdb::get_results()
	 * @uses wpdb::is_main_query()
	 *
	 * @return array
	 */
	public function show_draft_publicly( $posts, $wp_query ) {

		global $wpdb;

		if ( isset( $_GET['secret'] ) && $wp_query->is_main_query() && get_post_meta( $wp_query->query_vars['p'], '_draft_secret_key', true ) === sanitize_text_field( $_GET['secret'] ) ) {
			$posts = $wpdb->get_results( $wp_query->request );
		}

		return $posts;

	}

	/**
	 * Add public draft link to post actions.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array   $actions An array of row action links.
	 * @param WP_Post $post    The current Post object.
	 *
	 * @uses Share_Drafts_Publicly::get_draft_url()
	 * @uses Share_Drafts_Publicly::is_draft_public()
	 *
	 * @return array
	 */
	public function add_post_row_action( $actions, $post ) {

		if ( $this->is_draft_public( $post->ID ) ) {
			$actions['public_link'] = '<a href="' . $this->get_draft_url( $post->ID ) . '">' . esc_html( apply_filters( 'sdp_label_public_link', __( 'Public Draft Link', 'share_drafts_publicly' ) ) ) . '</a>';
		}

		return $actions;

	}

	/**
	 * Change draft status of post.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @uses Share_Drafts_Publicly::make_draft_private()
	 * @uses Share_Drafts_Publicly::make_draft_public()
	 */
	public function ajax_change_status() {

		// Verify nonce.
		if ( ! wp_verify_nonce( $_GET['nonce'], 'share-drafts-publicly' ) ) {
			wp_send_json_error( array( 'message' => esc_html( apply_filters( 'sdp_label_invalid_reques', __( 'Invalid request.', 'share_drafts_publicly' ) ) ) ) );
		}

		// Get provided informaton.
		$make    = sanitize_text_field( wp_unslash( $_GET['make'] ) );
		$post_id = sanitize_text_field( wp_unslash( $_GET['post_id'] ) );

		switch ( $make ) {

			case 'private':

				$make_private = $this->make_draft_private( $post_id );

				if ( ! $make_private ) {
					wp_send_json_error( array( 'message' => esc_html( apply_filters( 'sdp_label_error_make_private', __( 'Unable to make draft private. Please try again.', 'share_drafts_publicly' ) ) ) ) );
				} else {
					wp_send_json_success();
				}

			case 'public':

				$make_public = $this->make_draft_public( $post_id );

				if ( ! $make_public ) {
					wp_send_json_error( array( 'message' => esc_html( apply_filters( 'sdp_label_error_make_public', __( 'Unable to make draft public. Please try again.', 'share_drafts_publicly' ) ) ) ) );
				} else {
					wp_send_json_success( array( 'permalink' => $make_public ) );
				}

		}

	}

	/**
	 * Get public draft URL.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $secret_key Secret key.
	 *
	 * @return string
	 */
	public function get_draft_url( $post_id = null, $secret_key = null ) {

		// Get the post ID if not set.
		if ( empty( $post_id ) && isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
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
	 * @since  1.1
	 * @access public
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return bool
	 */
	public function is_draft_public( $post_id = null ) {

		// Get the post ID if not set.
		if ( empty( $post_id ) && isset( $_GET['post'] ) ) {
			$post_id = absint( $_GET['post'] );
		}

		// Get draft visibility status.
		$is_draft_public = get_post_meta( $post_id, '_draft_secret_key', true );

		return strlen( $is_draft_public ) > 0;

	}

	/**
	 * Make draft private.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param int $post_id Post ID to make private.
	 *
	 * @return bool
	 */
	public function make_draft_private( $post_id = 0 ) {

		// Delete post meta.
		return delete_post_meta( $post_id, '_draft_secret_key' );

	}

	/**
	 * Make draft public.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param int $post_id Post ID to make public.
	 *
	 * @uses Share_Drafts_Publicly::get_draft_url()
	 *
	 * @return string|bool
	 */
	public function make_draft_public( $post_id = 0 ) {

		// Generate secret key.
		$secret_key = wp_generate_password( 6, false, false );

		// Add secret key to post meta.
		$secret_key_status = add_post_meta( absint( $post_id ), '_draft_secret_key', $secret_key, true );

		// Return draft URL.
		return $secret_key_status ? $this->get_draft_url( $post_id, $secret_key ) : false;

	}

}

Share_Drafts_Publicly::get_instance();
