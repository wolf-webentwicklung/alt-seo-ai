<?php
/**
 * The admin metabox functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AltSEO_AI_Plus
 * @subpackage AltSEO_AI_Plus/admin
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin metabox functionality.
 *
 * Handles the meta box display and functionality in the admin area.
 *
 * @since 1.0.0
 */
class AltSEO_AI_Plus_Admin_Metabox {
	/**
	 * Hook into the appropriate actions when the class is constructed.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'save_post', array( $this, 'single_post_auto_save_keywords' ), 10, 3 );
		add_filter( 'the_content', array( $this, 'show_alt_frontend' ), 9999999, 1 );
	}

	/**
	 * Adds the meta box container.
	 *
	 * @since 1.0.0
	 */
	public function add_meta_box() {
		add_meta_box(
			'altseo_admin_metabox01',
			__( 'AltSeo-AI + Keywords', 'altseo-ai-plus' ),
			array( $this, 'render_meta_box_content' ),
			'',
			'side',
			'low'
		);
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return int|void
	 * @since 1.0.0
	 */
	public function save( $post_id ) {
		/**
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */
		// Check if our nonce is set.
		if ( ! isset( $_POST['altseo_admin_nonce'] ) ) {
			return $post_id;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['altseo_admin_nonce'] ) );

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'altseo_metabox' ) ) {
			return $post_id;
		}

		/**
		 * If this is an autosave, our form has not been submitted,
		 * so we don't want to do anything.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		// Check if this is a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return $post_id;
		}

		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return $post_id;
			}
		} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
		}

		// OK, it's safe for us to save the data now.

		// Make sure our field is set.
		if ( ! isset( $_POST['altseo_keywords'] ) ) {
			return $post_id;
		}

		// Sanitize the user input with length validation.
		$data = sanitize_text_field( wp_unslash( $_POST['altseo_keywords'] ) );

		// Validate length (max 500 characters for keywords).
		if ( strlen( $data ) > 500 ) {
			$data = substr( $data, 0, 500 );
		}

		// Update the meta field.
		update_post_meta( $post_id, 'altseo_keywords_tag', $data );
	}

	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 * @since 1.0.0
	 */
	public function render_meta_box_content( $post ) {
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'altseo_metabox', 'altseo_admin_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$value = get_post_meta( $post->ID, 'altseo_keywords_tag', true );

		// Display the form, using the current value.
		?>
		<script>
			jQuery(document).ready(function(){
				jQuery('#altseo_keywords').tagsInput({
					'height':'100px',
					'width':'220px',
				});
			});
		</script>
		<input type="text" id="altseo_keywords" name="altseo_keywords" value="<?php echo esc_attr( $value ); ?>" size="45" />
		<?php
	}

	/**
	 * Auto-save keywords for a single post
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 * @param bool    $update  Whether this is an update.
	 * @since 1.0.0
	 */
	public function single_post_auto_save_keywords( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Check if the feature is enabled and content is available.
		if ( get_option( 'altseo_enabled' ) && ! empty( $post->post_content ) ) {
			// Ensure the post type is one of the allowed types.
			if ( in_array( $post->post_type, apply_filters( 'altseo_ai_plus_post_types', array( 'post', 'page' ) ), true ) === false ) {
				return;
			}

			// Only generate keywords and alt tags for published posts.
			if ( 'publish' !== $post->post_status ) {
				return;
			}

			// Initialize the API.
			$altseo_api = new AltSEO_AI_Plus_API();

			// Update keywords.
			$altseo_api->update_keywords( $post_id, true );

			// Generate alt tags.
			$altseo_api->generate_alt( $post_id );
		}
	}

	/**
	 * Show alt text on frontend
	 *
	 * @param string $content The post content.
	 * @return string Modified content with alt tags
	 * @since 1.0.0
	 */
	public function show_alt_frontend( $content ) {
		$altseo_api = new AltSEO_AI_Plus_API();
		return $altseo_api->process_content_add_alt( $content );
	}
}

