<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.wolfwebentwicklung.de
 * @since      1.0.0
 *
 * @package    AltSEO_AI
 * @subpackage AltSEO_AI/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the public-facing side of the site.
 *
 * @package    AltSEO_AI
 * @subpackage AltSEO_AI/public
 * @author     Wolf Webentwicklung GmbH
 */
class AltSEO_AI_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string $plugin_name       The name of the plugin.
	 * @param    string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Initialize hooks.
		$this->init();
	}

	/**
	 * Initialize hooks for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	private function init() {
		// Register scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add additional frontend processing.
		add_filter( 'the_content', array( $this, 'process_content_fix_alt' ), 999 );

		// Add Elementor-specific frontend hooks.
		if ( $this->is_elementor_active() ) {
			add_filter( 'elementor/frontend/the_content', array( $this, 'process_elementor_content' ), 999 );
			add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'enqueue_elementor_scripts' ) );
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'fix_elementor_image_attributes' ), 999, 3 );
		}
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// No styles needed currently.
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Base frontend script if needed.
		wp_enqueue_script(
			$this->plugin_name . '-public',
			plugin_dir_url( __FILE__ ) . 'js/altseo-ai-public.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name . '-public',
			'altSeoPublicData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'altseo_frontend_nonce' ),
			)
		);
	}

	/**
	 * Enqueue Elementor-specific scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_elementor_scripts() {
		wp_enqueue_script(
			$this->plugin_name . '-elementor-fix',
			plugin_dir_url( __FILE__ ) . 'js/altseo-elementor-fix.js',
			array( 'jquery' ),
			$this->version,
			true
		);

		wp_localize_script(
			$this->plugin_name . '-elementor-fix',
			'altSeoElementorData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'altseo_frontend_nonce' ),
			)
		);
	}

	/**
	 * Process content to ensure alt attributes are properly set
	 *
	 * @param    string $content    The post content.
	 * @return   string                The processed content.
	 * @since    1.0.0
	 */
	public function process_content_fix_alt( $content ) {
		// Process all images in the content to ensure they have alt attributes.
		return preg_replace_callback(
			'/<img([^>]*)alt=""([^>]*)>/',
			array( $this, 'replace_empty_alt_callback' ),
			$content
		);
	}

	/**
	 * Process Elementor content specifically
	 *
	 * @param    string $content    The Elementor content.
	 * @return   string                The processed content.
	 * @since    1.0.0
	 */
	public function process_elementor_content( $content ) {
		// Process all images in Elementor content.
		return $this->process_content_fix_alt( $content );
	}

	/**
	 * Callback for replacing empty alt attributes
	 *
	 * @param    array $matches    The regex matches.
	 * @return   string                The replacement HTML.
	 * @since    1.0.0
	 */
	private function replace_empty_alt_callback( $matches ) {
		$before_alt = $matches[1];
		$after_alt  = $matches[2];

		// Try to extract image ID.
		$image_id = null;

		// Look for wp-image-{ID} class.
		if ( preg_match( '/class=["\'](.*?)wp-image-(\d+)(.*?)["\']/', $before_alt . $after_alt, $class_matches ) ) {
			$image_id = $class_matches[2];
		}

		// Look for data-id attribute.
		if ( ! $image_id && preg_match( '/data-id=["\'](\d+)["\']/', $before_alt . $after_alt, $data_matches ) ) {
			$image_id = $data_matches[1];
		}

		// Look for attachment ID in SRC.
		if ( ! $image_id && preg_match( '/src=["\'](.*?)["\']/', $before_alt . $after_alt, $src_matches ) ) {
			$src      = $src_matches[1];
			$image_id = attachment_url_to_postid( $src );
		}

		// If we found an image ID, get its alt text.
		if ( $image_id ) {
			$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_text ) ) {
				return '<img' . $before_alt . 'alt="' . esc_attr( $alt_text ) . '"' . $after_alt . '>';
			}
		}

		return $matches[0];
	}

	/**
	 * Filter image attributes specifically for Elementor
	 *
	 * @param    array   $attr        The image attributes.
	 * @param    WP_Post $attachment  The attachment post.
	 * @param    string  $size        The image size (unused, required by WordPress filter).
	 * @return   array                  The filtered attributes.
	 * @since    1.0.0
	 */
	public function fix_elementor_image_attributes( $attr, $attachment, $size ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Size parameter is not used, but is required by the WordPress filter.
		// Only process if alt attribute is empty or missing.
		if ( empty( $attr['alt'] ) ) {
			$alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_text ) ) {
				$attr['alt'] = $alt_text;
			}
		}

		return $attr;
	}

	/**
	 * Check if Elementor is active
	 *
	 * @return   boolean   True if Elementor is active.
	 * @since    1.0.0
	 */
	private function is_elementor_active() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}
}
