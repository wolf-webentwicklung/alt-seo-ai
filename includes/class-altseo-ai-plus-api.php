<?php
/**
 * The API management class file.
 *
 * @package AltSEO_AI_Plus
 * @since   1.0.0
 */

/**
 * The API management class.
 *
 * This is used to connect with OpenAI's API for AltSEO AI+.
 *
 * @since      1.0.0
 * @package    AltSEO_AI_Plus
 * @subpackage AltSEO_AI_Plus/includes
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */
class AltSEO_AI_Plus_API {
	/**
	 * The API key used to connect wit OpenAI client.
	 *
	 * @var string  $api_key The API key for connecting with the client.
	 */
	private $api_key;

	/**
	 * The API client URL.
	 *
	 * @var string $base_url The base URL of the API client.
	 */
	private $base_url;

	/**
	 * Current Elementor widget being processed.
	 *
	 * @var object $current_elementor_widget Current widget instance.
	 */
	private $current_elementor_widget;



	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Get API key and clean it.
		$api_key       = get_option( 'altseo_ai_key' );
		$this->api_key = $this->clean_api_key( $api_key );

		// Validate API key format.
		if ( ! empty( $this->api_key ) && ! $this->is_valid_api_key_format( $this->api_key ) ) {
			$this->api_key = '';
		}

		// Initialize Elementor compatibility hooks.
		$this->init_elementor_compatibility();

		// Initialize Divi compatibility hooks.
		$this->init_divi_compatibility();
	}

	/**
	 * Initialize Elementor compatibility hooks
	 *
	 * @since 1.0.0
	 */
	private function init_elementor_compatibility() {
		// Hook into Elementor's image rendering
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'elementor_image_attributes' ), 10, 3 );

		// Hook into Elementor's dynamic content
		add_filter( 'elementor/dynamic_tags/get_image_alt', array( $this, 'elementor_dynamic_image_alt' ), 10, 2 );
		
		// Hook for Elementor dynamic tags rendering
		add_filter( 'elementor/dynamic_tags/render_tag', array( $this, 'elementor_render_dynamic_tag' ), 10, 2 );
		
		// Hook into Elementor frontend output
		add_filter( 'elementor/frontend/html_output', array( $this, 'fix_elementor_empty_alt' ), 20, 1 );
		
		// Hook for Elementor frontend rendering
		add_action( 'elementor/frontend/after_enqueue_styles', array( $this, 'elementor_frontend_hooks' ) );

		// Hook for Elementor widget image rendering
		add_filter( 'elementor/widget/render_content', array( $this, 'elementor_widget_render_content' ), 10, 2 );

		// Additional Elementor-specific hooks
		add_filter( 'elementor/frontend/the_content', array( $this, 'elementor_process_content' ), 10, 1 );
		add_action( 'elementor/frontend/widget/before_render', array( $this, 'elementor_before_widget_render' ), 10, 1 );

		// Hook into WordPress image functions that Elementor uses
		add_filter( 'wp_get_attachment_image', array( $this, 'elementor_attachment_image_filter' ), 10, 5 );

		// Hook into the image attributes specifically for featured images
		add_filter( 'post_thumbnail_html', array( $this, 'elementor_post_thumbnail_html' ), 10, 5 );
		
		// Add frontend script to fix dynamic alt attributes
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_elementor_alt_fix_script' ) );
	}

	/**
	 * Initialize Divi compatibility hooks
	 *
	 * @since 1.0.0
	 */
	private function init_divi_compatibility() {
		// Hook into Divi's image rendering
		add_filter( 'et_pb_image_src', array( $this, 'divi_image_src_filter' ), 10, 2 );

		// Hook into Divi's module content
		add_filter( 'et_pb_module_content', array( $this, 'divi_module_content_filter' ), 10, 3 );

		// Hook into Divi's image alt text
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'divi_image_attributes' ), 5, 3 );
	}

	/**
	 * Filter image attributes for Elementor compatibility
	 *
	 * @param array        $attr       Attributes for the image markup.
	 * @param WP_Post      $attachment Image attachment post.
	 * @param string|array $size  Requested size.
	 * @return array Modified attributes
	 * @since 1.0.0
	 */
	public function elementor_image_attributes( $attr, $attachment, $size ) {
		// Only process if Elementor is active and we're on frontend
		if ( ! $this->is_elementor_active() || is_admin() ) {
			return $attr;
		}

		// Get our generated alt text for this attachment
		$our_alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

		// If we have our alt text and it's not empty, use it
		if ( ! empty( $our_alt_text ) ) {
			$attr['alt'] = $our_alt_text;
		}

		return $attr;
	}

	/**
	 * Filter Elementor dynamic image alt text
	 *
	 * @param string $alt_text The alt text.
	 * @param array  $settings  Widget settings.
	 * @return string Modified alt text
	 * @since 1.0.0
	 */
	public function elementor_dynamic_image_alt( $alt_text, $settings ) {
		// Only process if we're dealing with featured images or attachment images
		if ( isset( $settings['image']['id'] ) ) {
			$attachment_id = $settings['image']['id'];
			$our_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			
			if ( ! empty( $our_alt_text ) ) {
				return $our_alt_text;
			}
		} elseif ( isset( $settings['id'] ) ) {
			// Sometimes Elementor uses a direct ID property
			$attachment_id = $settings['id'];
			$our_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			
			if ( ! empty( $our_alt_text ) ) {
				return $our_alt_text;
			}
		} elseif ( isset( $settings['image']['url'] ) && ! empty( $settings['image']['url'] ) ) {
			// Try to get attachment ID from URL
			$attachment_id = attachment_url_to_postid( $settings['image']['url'] );
			
			if ( $attachment_id ) {
				$our_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
				
				if ( ! empty( $our_alt_text ) ) {
					return $our_alt_text;
				}
			}
		}

		return $alt_text;
	}

	/**
	 * Add frontend hooks for Elementor
	 *
	 * @since 1.0.0
	 */
	public function elementor_frontend_hooks() {
		// Add filter to modify Elementor's image output
		add_filter( 'elementor/frontend/widget/should_render', array( $this, 'elementor_should_render_widget' ), 10, 2 );
	}

	/**
	 * Filter Elementor widget rendering to inject our alt text
	 *
	 * @param string                 $content Widget content.
	 * @param \Elementor\Widget_Base $widget Widget instance.
	 * @return string Modified content
	 * @since 1.0.0
	 */
	public function elementor_widget_render_content( $content, $widget ) {
		// Only process image-related widgets
		if ( ! in_array( $widget->get_name(), array( 'image', 'theme-post-featured-image' ), true ) ) {
			return $content;
		}

		// Get widget settings
		$settings = $widget->get_settings_for_display();

		// Process featured image widgets
		if ( 'theme-post-featured-image' === $widget->get_name() ) {
			$content = $this->process_elementor_featured_image_content( $content );
		}

		// Process regular image widgets
		if ( 'image' === $widget->get_name() && isset( $settings['image']['id'] ) ) {
			$attachment_id = $settings['image']['id'];
			$our_alt_text  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			if ( ! empty( $our_alt_text ) ) {
				// Replace empty or existing alt attributes with our generated one
				$content = preg_replace(
					'/(<img[^>]*?)alt=["\'][^"\']*["\']([^>]*>)/i',
					'$1alt="' . esc_attr( $our_alt_text ) . '"$2',
					$content
				);

				// If no alt attribute exists, add it
				if ( ! preg_match( '/alt\s*=/i', $content ) ) {
					$content = preg_replace(
						'/(<img[^>]*?)(\/?>)/i',
						'$1 alt="' . esc_attr( $our_alt_text ) . '"$2',
						$content
					);
				}
			}
		}

		return $content;
	}

	/**
	 * Process Elementor featured image content
	 *
	 * @param string $content The widget content.
	 * @return string Modified content
	 * @since 1.0.0
	 */
	private function process_elementor_featured_image_content( $content ) {
		global $post;

		if ( ! $post || ! has_post_thumbnail( $post->ID ) ) {
			return $content;
		}

		$featured_image_id = get_post_thumbnail_id( $post->ID );
		$our_alt_text      = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );

		if ( ! empty( $our_alt_text ) ) {
			// Replace empty or existing alt attributes with our generated one
			$content = preg_replace(
				'/(<img[^>]*?)alt=["\'][^"\']*["\']([^>]*>)/i',
				'$1alt="' . esc_attr( $our_alt_text ) . '"$2',
				$content
			);

			// If no alt attribute exists, add it
			if ( ! preg_match( '/alt\s*=/i', $content ) ) {
				$content = preg_replace(
					'/(<img[^>]*?)(\/?>)/i',
					'$1 alt="' . esc_attr( $our_alt_text ) . '"$2',
					$content
				);
			}
		}

		return $content;
	}

	/**
	 * Check if Elementor is active and we're on frontend
	 *
	 * @return bool True if Elementor is active
	 * @since 1.0.0
	 */
	public function is_elementor_active() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' );
	}

	/**
	 * Check if Divi is active
	 *
	 * @return bool True if Divi is active
	 * @since 1.0.0
	 */
	public function is_divi_active() {
		return defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_pb_is_allowed' );
	}

	/**
	 * Filter to determine if Elementor widget should render
	 *
	 * @param bool                   $should_render Whether to render the widget.
	 * @param \Elementor\Widget_Base $widget Widget instance.
	 * @return bool Whether to render
	 * @since 1.0.0
	 */
	public function elementor_should_render_widget( $should_render, $widget ) {
		// Always allow rendering, we just want to hook into the process
		return $should_render;
	}

	/**
	 * Process Elementor frontend content to inject alt text
	 *
	 * @param string $content The content to process.
	 * @return string Modified content
	 * @since 1.0.0
	 */
	public function elementor_process_content( $content ) {
		// Only process if Elementor is active and we're on frontend
		if ( ! $this->is_elementor_active() || is_admin() ) {
			return $content;
		}

		// Process all images in the content and ensure they have proper alt text
		$content = preg_replace_callback(
			'/<img([^>]+)>/i',
			array( $this, 'elementor_image_callback' ),
			$content
		);

		return $content;
	}

	/**
	 * Callback for processing images in Elementor content
	 *
	 * @param array $matches Regex matches.
	 * @return string Modified image tag
	 * @since 1.0.0
	 */
	private function elementor_image_callback( $matches ) {
		$img_tag    = $matches[0];
		$attributes = $matches[1];

		// Extract the attachment ID from various possible sources
		$attachment_id = null;

		// Try to get attachment ID from wp-image class
		if ( preg_match( '/wp-image-(\d+)/', $attributes, $id_matches ) ) {
			$attachment_id = intval( $id_matches[1] );
		}

		// Try to get attachment ID from data-id attribute
		if ( ! $attachment_id && preg_match( '/data-id=["\'](\d+)["\']/', $attributes, $id_matches ) ) {
			$attachment_id = intval( $id_matches[1] );
		}

		// If we found an attachment ID, try to get our alt text
		if ( $attachment_id ) {
			$our_alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			if ( ! empty( $our_alt_text ) ) {
				// Check if alt attribute already exists
				if ( preg_match( '/alt\s*=\s*["\'][^"\']*["\']/', $attributes ) ) {
					// Replace existing alt attribute
					$img_tag = preg_replace(
						'/alt\s*=\s*["\'][^"\']*["\']/',
						'alt="' . esc_attr( $our_alt_text ) . '"',
						$img_tag
					);
				} else {
					// Add alt attribute
					$img_tag = str_replace( '<img', '<img alt="' . esc_attr( $our_alt_text ) . '"', $img_tag );
				}
			}
		}

		return $img_tag;
	}

	/**
	 * Filter Divi image source and attributes
	 *
	 * @param string $src Image source.
	 * @param array  $args Image arguments.
	 * @return string Modified source
	 * @since 1.0.0
	 */
	public function divi_image_src_filter( $src, $args ) {
		// This is mainly for tracking - the actual alt text injection happens in other filters
		return $src;
	}

	/**
	 * Filter Divi module content to inject alt text
	 *
	 * @param string $content Module content.
	 * @param string $function_name Module function name.
	 * @param array  $props Module properties.
	 * @return string Modified content
	 * @since 1.0.0
	 */
	public function divi_module_content_filter( $content, $function_name, $props ) {
		// Only process if Divi is active and we're dealing with image modules
		if ( ! $this->is_divi_active() || is_admin() ) {
			return $content;
		}

		// Process image-related Divi modules
		if ( in_array( $function_name, array( 'et_pb_image', 'et_pb_gallery' ), true ) ) {
			$content = preg_replace_callback(
				'/<img([^>]+)>/i',
				array( $this, 'divi_image_callback' ),
				$content
			);
		}

		return $content;
	}

	/**
	 * Filter image attributes for Divi compatibility
	 *
	 * @param array        $attr       Attributes for the image markup.
	 * @param WP_Post      $attachment Image attachment post.
	 * @param string|array $size  Requested size.
	 * @return array Modified attributes
	 * @since 1.0.0
	 */
	public function divi_image_attributes( $attr, $attachment, $size ) {
		// Only process if Divi is active and we're on frontend
		if ( ! $this->is_divi_active() || is_admin() ) {
			return $attr;
		}

		// Get our generated alt text for this attachment
		$our_alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

		// If we have our alt text and it's not empty, use it
		if ( ! empty( $our_alt_text ) ) {
			$attr['alt'] = $our_alt_text;
		}

		return $attr;
	}

	/**
	 * Validate API key format
	 *
	 * @param string $api_key The API key to validate.
	 * @return bool True if valid format, false otherwise
	 * @since 1.0.0
	 */
	private function is_valid_api_key_format( $api_key ) {
		// OpenAI API keys have multiple possible formats:
		// Legacy format: sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.
		// Current format: sk-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx.
		// Also support keys with hyphens and underscores that appear in some cases.

		// Cleaned up regex: starts with sk-, followed by alphanumeric chars, hyphens and underscores, with minimum length.
		return preg_match( '/^sk-[a-zA-Z0-9_-]{16,}$/', $api_key );
	}

	/**
	 * Check if API key is configured and valid
	 *
	 * @return bool True if API key is configured
	 * @since 1.0.0
	 */
	public function has_api_key() {
		return ! empty( $this->api_key );
	}

	/**
	 * Auto update the keywords for a post
	 *
	 * @param int  $post_id      Post ID to update keywords for.
	 * @param bool $force_update Whether to force update even if keywords exist.
	 * @return string|void       Generated keywords or void
	 * @since 1.0.0
	 */
	public function update_keywords( $post_id, $force_update = false ) {
		// Validate API key first.
		if ( ! $this->has_api_key() ) {
			return '';
		}

		// Validate post_id.
		$post_id = absint( $post_id );
		if ( ! $post_id || ! get_post( $post_id ) ) {
			return '';
		}

		// Only skip if not forcing update.
		if ( ! $force_update && get_post_meta( $post_id, 'altseo_keywords_tag', true ) ) {
			return;
		}

		// Delete existing keywords if forcing an update.
		if ( $force_update ) {
			delete_post_meta( $post_id, 'altseo_keywords_tag' );
		}

		$content_api  = new AltSEO_AI_Plus_Content_API();
		$post_content = $content_api->get_clean_texts_from_post( $post_id );
		if ( ! $post_content ) {
			return '';
		}

		// Detect the language of the post content.
		$detected_language = $this->detect_content_language( $post_content );

		// Get user's preferred keyword count with validation.
		$keyword_num = intval( get_option( 'altseo_keyword_num', 1 ) );
		if ( $keyword_num < 1 || $keyword_num > 10 ) {
			$keyword_num = 1; // Default to 1 if invalid.
		}

		// Sanitize content for API request (limit length).
		$max_content_length = 2000; // Reasonable limit for API.
		if ( mb_strlen( $post_content ) > $max_content_length ) {
			$post_content = mb_substr( $post_content, 0, $max_content_length );
		}

		$p1 = array(
			'role'    => 'system',
			'content' => 'You are an SEO expert. Generate keywords based on the following content: ' . $post_content,
		);

		$p2 = array(
			'role'    => 'user',
			'content' => "Generate exactly {$keyword_num} best short, focused keywords for SEO for the context provided. Provide them as a comma-separated list. CRITICAL REQUIREMENT: You MUST return the keywords in {$detected_language} language only. The content language has been detected as {$detected_language}, so all keywords must be in {$detected_language}. Do not translate or use any other language. Use the exact same language as the original content.",
		);

		$prompt = wp_json_encode( $p1 ) . ',' . wp_json_encode( $p2 );
		$args   = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body'    => '{
                "model": "' . $this->get_ai_model() . '",
                "messages": [
                ' . $prompt . '],
                "max_tokens": ' . $this->get_max_tokens_for_model() . ',
                "temperature": 0.7,
                "top_p": 1,
                "frequency_penalty": 0,
                "presence_penalty": 0
            }',
			'timeout' => 45, // Increased timeout for keyword generation.
		);

		// Retry logic with exponential backoff.
		$max_retries = 3;
		$retry_delay = 1; // Start with 1 second delay.

		for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

			// Better error handling.
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();

				// If this is the last attempt, return empty.
				if ( $attempt === $max_retries ) {
					return '';
				}

				// Wait before retrying (exponential backoff).
				sleep( $retry_delay );
				$retry_delay *= 2;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 429 === $response_code || $response_code >= 500 ) {

				// If this is the last attempt, continue to parse response anyway.
				if ( $attempt === $max_retries ) {
					break;
				}

				// Wait before retrying (exponential backoff).
				sleep( $retry_delay );
				$retry_delay *= 2;
				continue;
			}

			// Success - break out of retry loop.
			break;
		}

		$response   = wp_remote_retrieve_body( $response );
		$data_array = json_decode( $response, true );

		$keywords = '';
		if ( isset( $data_array['choices'][0]['message']['content'] ) ) {
			$keywords = $data_array['choices'][0]['message']['content'];
		}

		if ( $keywords ) {
			// Format keywords consistently (remove quotes, trim spaces).
			$keywords = trim( str_replace( '"', '', $keywords ) );

			// Ensure we're getting exactly the number of keywords requested.
			$keyword_array = array_map( 'trim', explode( ',', $keywords ) );
			// Slice the array to get exactly the number of keywords requested.
			$keyword_array = array_slice( $keyword_array, 0, $keyword_num );
			// Rebuild the comma-separated string.
			$keywords = implode( ', ', $keyword_array );

			update_post_meta( $post_id, 'altseo_keywords_tag', $keywords );
		}
	}

	/**
	 * Get alt text for image.
	 *
	 * @param string $src     Image URL to request alt text for.
	 * @param string $keywords Keywords for this post.
	 * @param int    $post_id  The post ID for language detection.
	 * @return string Generated alt text
	 * @since 1.0.0
	 */
	public function generate_image_alt( $src, $keywords, $post_id = null ) {

		// Validate image URL first.
		if ( empty( $src ) || trim( $src ) === '' ) {
			return '';
		}

		// Validate that it's a proper URL (contains protocol or is a relative path).
		if ( ! filter_var( $src, FILTER_VALIDATE_URL ) && ! preg_match( '/^\/|^\.\/|^\.\.\//', $src ) ) {
			return '';
		}

		// Detect language from post content if post_id is provided.
		$detected_language = 'English'; // Default.
		if ( $post_id ) {
			$content_api  = new AltSEO_AI_Plus_Content_API();
			$post_content = $content_api->get_clean_texts_from_post( $post_id );
			if ( $post_content ) {
				$detected_language = $this->detect_content_language( $post_content );
			}
		}

		// Try base64 encoding for all images (OpenAI now requires this).
		$image_content = $this->get_base64_encoded_image( $src );

		if ( ! $image_content ) {
			return '';
		}

		// Prepare the query based on whether we have base64 encoded content or URL.
		$content_array = array(
			array(
				'type' => 'text',
				'text' => "Generate a short alt text (max 125 characters but not less than 15 characters) that describes this image. CRITICAL REQUIREMENT: You MUST write the alt text in {$detected_language} language only. Include some of these keywords naturally: " . $keywords . ". The content language has been detected as {$detected_language}, so the alt text must be in {$detected_language}.",
			),
		);

		// Detect image type from URL extension for proper MIME type.
		$image_extension = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
		$mime_type       = 'image/jpeg'; // Default.

		switch ( $image_extension ) {
			case 'png':
				$mime_type = 'image/png';
				break;
			case 'gif':
				$mime_type = 'image/gif';
				break;
			case 'webp':
				$mime_type = 'image/webp';
				break;
			case 'jpg':
			case 'jpeg':
			default:
				$mime_type = 'image/jpeg';
				break;
		}

		// Add image content as base64.
		$content_array[] = array(
			'type'      => 'image_url',
			'image_url' => array(
				'url' => "data:$mime_type;base64," . $image_content,
			),
		);

		// Prepare the full query for the API.
		$query = array(
			array(
				'role'    => 'user',
				'content' => $content_array,
			),
		);

		// Force vision-capable model for image processing.
		$vision_model = $this->get_vision_model();

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body'    => wp_json_encode(
				array(
					'model'      => $vision_model,
					'messages'   => $query,
					'max_tokens' => $this->get_max_tokens_for_model( $vision_model ),
				)
			),
			'timeout' => 60, // Increased timeout for vision model processing.
		);

		// Retry logic with exponential backoff.
		$max_retries = 3;
		$retry_delay = 1; // Start with 1 second delay.

		for ( $attempt = 1; $attempt <= $max_retries; $attempt++ ) {
			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();

				// If this is the last attempt, return empty.
				if ( $attempt === $max_retries ) {
					return '';
				}

				// Wait before retrying (exponential backoff).
				sleep( $retry_delay );
				$retry_delay *= 2;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 429 === $response_code || 500 <= $response_code ) {

				// If this is the last attempt, continue to parse response anyway.
				if ( $attempt === $max_retries ) {
					break;
				}

				// Wait before retrying (exponential backoff).
				sleep( $retry_delay );
				$retry_delay *= 2;
				continue;
			}

			// Success - break out of retry loop.
			break;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return '';
		}

		$alt_text = trim( str_replace( '"', '', $data['choices'][0]['message']['content'] ) );
		return $alt_text;
	}

	/**
	 * Generate alt text for all images in a post
	 *
	 * @param int $post_id The post ID to process.
	 * @since 1.0.0
	 */
	public function generate_alt( $post_id ) {
		// Prevent infinite loops when updating post content.
		if ( get_post_meta( $post_id, 'altseo_updating_content', true ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$text = $post->post_content;
		if ( ! $text ) {
			return;
		}

		// Generate page-specific keywords for this post.
		$page_keywords = get_post_meta( $post_id, 'altseo_keywords_tag', true );
		if ( ! $page_keywords ) {
			$page_keywords = $this->generate_keywords( $post_id, true );
		}

		// Get the number of SEO keywords to include.
		$seo_keywords_count = intval( get_option( 'altseo_seo_keywords_count', 3 ) );

		// Get random general SEO keywords.
		$random_seo_keywords = $this->get_random_seo_keywords( $seo_keywords_count );

		// Initialize DOMDocument.
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );

		// Load HTML properly without encoding issues.
		try {
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $text, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Intentionally empty - DOM parsing errors are expected with malformed HTML and can be safely ignored.
		}

		$images = $dom->getElementsByTagName( 'img' );

		// Clear any existing alt attributes to force regeneration.
		delete_post_meta( $post_id, 'altseo_alts' );
		$alt_array_saved = array();

		// Process each image.
		foreach ( $images as $image ) {
			$src = $image->getAttribute( 'src' );

			// Skip images with empty or invalid src attributes.
			if ( empty( $src ) || trim( $src ) === '' ) {
				continue;
			}

			$filename = preg_replace( '/[^a-zA-Z0-9_]/', '_', pathinfo( basename( $src ), PATHINFO_FILENAME ) );

			// Skip images with empty or invalid filenames.
			if ( empty( $filename ) || '' === trim( $filename ) || '_' === $filename ) {
				continue;
			}

			if ( ! isset( $alt_array_saved[ $filename ] ) ) {
				// Always generate new alt text (force overwrite).
				$generated_alt = $this->generate_image_alt( $src, $page_keywords, $post_id );

				if ( $generated_alt ) {
					// Enhance the alt text by combining generated alt, general keywords, and page keywords.
					$enhanced_alt                 = $this->enhance_alt_text( $generated_alt, $random_seo_keywords, $page_keywords, $post_id );
					$alt_array_saved[ $filename ] = $enhanced_alt;
				} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse
					// No alt text generated - this can happen if the image analysis fails or API is unavailable.
				}
			}
		}

		// Save the generated alt attributes.
		update_post_meta( $post_id, 'altseo_alts', $alt_array_saved );

		// Process featured image if present.
		if ( has_post_thumbnail( $post_id ) ) {

			$featured_image_id  = get_post_thumbnail_id( $post_id );
			$featured_image_src = wp_get_attachment_image_src( $featured_image_id, 'full' );

			if ( $featured_image_src ) {
				$featured_image_url = $featured_image_src[0];
				$featured_filename  = preg_replace( '/[^a-zA-Z0-9_]/', '_', pathinfo( basename( $featured_image_url ), PATHINFO_FILENAME ) );

				// Get current alt text to check if we need to update.
				$current_featured_alt = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );

				// Generate alt text for featured image (always force overwrite).
				$featured_alt = $this->generate_image_alt( $featured_image_url, $page_keywords, $post_id );

				if ( $featured_alt ) {
					// Enhance the featured image alt text.
					$enhanced_featured_alt = $this->enhance_alt_text( $featured_alt, $random_seo_keywords, $page_keywords, $post_id );

					// Update the WordPress attachment's alt text metadata directly.
					$update_result = update_post_meta( $featured_image_id, '_wp_attachment_image_alt', $enhanced_featured_alt );

					if ( false !== $update_result ) {
						// Also save to our custom meta for tracking.
						$featured_alt_array = array( $featured_filename => $enhanced_featured_alt );
						update_post_meta( $post_id, 'altseo_featured_image_alts', $featured_alt_array );

						// Elementor compatibility: Set Elementor-specific meta if Elementor is active
						if ( $this->is_elementor_active() ) {
							// Update Elementor's image alt text meta
							update_post_meta( $featured_image_id, '_elementor_alt_text', $enhanced_featured_alt );

							// Clear any Elementor-related caches if possible
							if ( function_exists( 'wp_cache_flush' ) ) {
								wp_cache_flush();
							}
						}

						// Clear attachment cache to ensure changes are visible immediately.
						if ( function_exists( 'clean_attachment_cache' ) ) {
							clean_attachment_cache( $featured_image_id );
						}

						// Clear any related caches.
						if ( function_exists( 'wp_cache_delete' ) ) {
							wp_cache_delete( $featured_image_id, 'posts' );
							wp_cache_delete( $post_id, 'posts' );
						}
					}
				}
			}
		}

		// Now update the actual post content with the new alt attributes.
		$this->update_post_content_with_alt_text( $post_id, $alt_array_saved );

		// Force apply featured image alt text for page builders
		$this->force_apply_featured_image_alt( $post_id );

		libxml_clear_errors(); // Clear any parsing errors.
	}

	/**
	 * Process content and add alt text to images
	 *
	 * @param string $content The post content.
	 * @return string Modified content with alt tags
	 * @since 1.0.0
	 */
	public function process_content_add_alt( $content ) {
		global $post;

		// Avoid errors if no post or content.
		if ( ! $post || empty( $content ) ) {
			return $content;
		}

		// Get alt texts for images in the content.
		$alt_texts = get_post_meta( $post->ID, 'altseo_alts', true );

		// Get the featured image alt text from post meta.
		$featured_image_alt = get_post_meta( $post->ID, 'altseo_featured_image_alts', true );

		// Process the featured image.
		if ( has_post_thumbnail( $post->ID ) ) {
			$featured_image_id  = get_post_thumbnail_id( $post->ID );
			$featured_image_src = wp_get_attachment_image_src( $featured_image_id, 'full' );
			$featured_image_url = $featured_image_src[0];

			// Extract filename from the featured image URL.
			$featured_filename = preg_replace( '/[^a-zA-Z0-9_]/', '_', pathinfo( basename( $featured_image_url ), PATHINFO_FILENAME ) );

			// Get the alt text for the featured image.
			$featured_alt_text = isset( $featured_image_alt[ $featured_filename ] ) ? $featured_image_alt[ $featured_filename ] : '';

			// Add or update the alt attribute for the featured image.
			$content = preg_replace(
				'/<img[^>]+class=["\'][^"\']*wp-post-image[^"\']*["\'][^>]*>/i',
				'<img class="wp-post-image" alt="' . esc_attr( $featured_alt_text ) . '" src="' . esc_url( $featured_image_url ) . '">',
				$content
			);
		}

		// Process all other images in the content.
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $index => $img_tag ) {
				$img_src = $matches[1][ $index ]; // Extract image src.

				// Extract filename from src.
				$filename = preg_replace( '/[^a-zA-Z0-9_]/', '_', pathinfo( basename( $img_src ), PATHINFO_FILENAME ) );

				// Fetch the corresponding alt text from array (if available).
				$new_alt_text = isset( $alt_texts[ $filename ] ) ? $alt_texts[ $filename ] : '';

				// Check if <img> tag already has an alt attribute.
				if ( preg_match( '/alt=["\'](.*?)["\']/i', $img_tag ) ) {
					// Replace existing alt attribute.
					$new_img_tag = preg_replace( '/alt=["\'](.*?)["\']/i', 'alt="' . esc_attr( $new_alt_text ) . '"', $img_tag );
				} else {
					// If no alt attribute, add one.
					$new_img_tag = preg_replace( '/<img/i', '<img alt="' . esc_attr( $new_alt_text ) . '"', $img_tag );
				}

				// Replace the old <img> tag with the updated one.
				$content = str_replace( $img_tag, $new_img_tag, $content );
			}
		}
		return $content;
	}

	/**
	 * Fetch available models from OpenAI API
	 *
	 * @return array List of available models
	 * @since 1.0.0
	 */
	public function fetch_available_models() {
		$models = array();

		// Only proceed if we have an API key.
		if ( empty( $this->api_key ) ) {
			return $models;
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( 'https://api.openai.com/v1/models', $args );

		if ( is_wp_error( $response ) ) {
			return $models;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $models;
		}

		// Filter for only chat completion models (gpt*).
		$chat_models = array();
		foreach ( $data['data'] as $model ) {
			if ( isset( $model['id'] ) && strpos( $model['id'], 'gpt' ) === 0 ) {
				$chat_models[] = $model['id'];
			}
		}

		// Sort models alphabetically.
		sort( $chat_models );

		return $chat_models;
	}

	/**
	 * Fetch available vision-capable models from OpenAI API
	 *
	 * @return array List of available vision-capable models
	 * @since 1.0.0
	 */
	public function fetch_available_vision_models() {
		$models = array();

		// Only proceed if we have an API key.
		if ( empty( $this->api_key ) ) {
			return $models;
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		$response = wp_remote_get( 'https://api.openai.com/v1/models', $args );

		if ( is_wp_error( $response ) ) {
			return $models;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return $models;
		}

		// Filter for only vision-capable models.
		$vision_models = array();
		foreach ( $data['data'] as $model ) {
			if ( isset( $model['id'] ) ) {
				$model_id = $model['id'];
				// Check if it's a vision-capable model.
				if ( strpos( $model_id, 'gpt-4o' ) === 0 ||
					strpos( $model_id, 'gpt-4-vision' ) === 0 ||
					strpos( $model_id, 'gpt-4-turbo' ) === 0 ) {
					$vision_models[] = $model_id;
				}
			}
		}

		// Sort models alphabetically.
		sort( $vision_models );

		return $vision_models;
	}

	/**
	 * Get the selected AI model or return default if not set
	 *
	 * @return string The AI model to use
	 * @since 1.0.0
	 */
	public function get_ai_model() {
		return get_option( 'altseo_ai_model', 'gpt-3.5-turbo' );
	}

	/**
	 * Get the selected vision AI model or return default if not set
	 *
	 * @return string The vision AI model to use
	 * @since 1.0.0
	 */
	public function get_vision_ai_model() {
		return get_option( 'altseo_vision_ai_model', 'gpt-4o-mini' );
	}

	/**
	 * Get appropriate max_tokens value based on selected model
	 *
	 * @param string $model The OpenAI model name.
	 * @return int Appropriate max_tokens value
	 * @since 1.0.0
	 */
	private function get_max_tokens_for_model( $model = null ) {
		if ( ! $model ) {
			$model = $this->get_ai_model();
		}

		// Set max_tokens based on model.
		if ( strpos( $model, 'gpt-4' ) === 0 ) {
			if ( strpos( $model, 'gpt-4o' ) === 0 ) {
				return 4000; // GPT-4o models.
			}
			return 4000; // GPT-4 models.
		} elseif ( strpos( $model, 'gpt-3.5-turbo' ) === 0 ) {
			return 3000; // GPT-3.5 models.
		}

		// Default to a safe value.
		return 1000;
	}

	/**
	 * Get base64 encoded image content with size limitations
	 *
	 * @param string $url Image URL.
	 * @return string|null Base64 encoded image or null if too large/not found
	 * @since 1.0.0
	 */
	private function get_base64_encoded_image( $url ) {

		// Clean and validate the URL.
		$url = trim( $url );
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// Get WordPress uploads directory info.
		$uploads_dir  = wp_upload_dir();
		$uploads_url  = rtrim( $uploads_dir['baseurl'], '/' );
		$uploads_path = rtrim( $uploads_dir['basedir'], '/' );

		// Convert URL to local file path.
		$file_path = str_replace( $uploads_url, $uploads_path, $url );

		$image_data = null;

		// Try to read local file first.
		if ( file_exists( $file_path ) && is_readable( $file_path ) ) {

			// Check file size (8MB limit for OpenAI).
			$file_size = filesize( $file_path );
			if ( $file_size > 8 * 1024 * 1024 ) {
				return null;
			}

			// Read the file using WordPress filesystem.
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$image_data = $wp_filesystem->get_contents( $file_path );
			if ( false !== $image_data ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				// Successfully read the file - continue with the existing image_data.
			} else {
				$image_data = null;
			}
		}

		// If local file reading failed, try HTTP download as fallback.
		if ( null === $image_data ) {

			$response = wp_remote_get(
				$url,
				array(
					'timeout'   => 45, // Increased timeout for image downloads.
					'headers'   => array(
						'User-Agent' => 'AltSEO-AI-Plugin/1.0',
					),
					'sslverify' => false, // Allow self-signed certificates for local development.
				)
			);

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				return null;
			}

			$image_data     = wp_remote_retrieve_body( $response );
			$content_length = strlen( $image_data );

			if ( $content_length > 8 * 1024 * 1024 ) {
				return null;
			}

			if ( $image_data && $content_length > 0 ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				// Successfully downloaded image data - continue with the existing image_data.
			} else {
				return null;
			}
		}

		// Validate image data and encode.
		if ( $image_data && strlen( $image_data ) > 0 ) {
			// Verify it's actually image data by checking magic bytes.
			if ( function_exists( 'finfo_open' ) ) {
				$file_info = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = finfo_buffer( $file_info, $image_data );
				finfo_close( $file_info );

				// Check if it's a valid image (compatible with older PHP versions).
				if ( strpos( $mime_type, 'image/' ) !== 0 ) {
					return null;
				}
			}

			// Encode image for OpenAI Vision API (legitimate use for API transmission).
			// Note: base64_encode is required by OpenAI API for image data transmission.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$encoded = base64_encode( $image_data );
			return $encoded;
		}

		return null;
	}

	/**
	 * Combine current alt text with SEO keywords and page-specific keywords using AI
	 *
	 * @param string $current_alt Current alt text of the image.
	 * @param string $random_seo_keywords Randomly selected general SEO keywords.
	 * @param string $page_keywords Page-specific keywords generated by AI.
	 * @param int    $post_id The post ID for language detection.
	 * @return string Enhanced alt text
	 * @since 1.0.0
	 */
	public function enhance_alt_text( $current_alt, $random_seo_keywords, $page_keywords, $post_id = null ) {
		// Detect language from post content if post_id is provided.
		$detected_language = 'English'; // Default.
		if ( $post_id ) {
			$content_api  = new AltSEO_AI_Plus_Content_API();
			$post_content = $content_api->get_clean_texts_from_post( $post_id );
			if ( $post_content ) {
				$detected_language = $this->detect_content_language( $post_content );
			}
		}

		// Prepare the prompt for combining the elements.
		$prompt  = "Combine the following three elements into a single, natural-sounding image alt text:\n\n";
		$prompt .= '1) Current image alt description: ' . $current_alt . "\n";
		$prompt .= '2) General SEO keywords that must be included: ' . $random_seo_keywords . "\n";
		$prompt .= '3) Page-specific keywords that must be included: ' . $page_keywords . "\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- Create ONE fluent sentence that naturally integrates ALL given keywords\n";
		$prompt .= "- Must include every single keyword provided\n";
		$prompt .= "- Sound human-written and grammatically correct\n";
		$prompt .= "- Avoid keyword lists or stuffing\n";
		$prompt .= "- CRITICAL REQUIREMENT: You MUST write the alt text in {$detected_language} language only\n";
		$prompt .= "- The content language has been detected as {$detected_language}, so the alt text must be in {$detected_language}\n";
		$prompt .= "- Maximum 125 characters\n\n";
		$prompt .= "Example: If image description is 'Tree on a field', general SEO keywords are 'Germany', and page keywords are 'agriculture, farming', then result should be: 'A tree standing in an agricultural farming field in Germany.'\n\n";
		$prompt .= "Return only the enhanced alt text in {$detected_language}, nothing else.";

		$query = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body'    => wp_json_encode(
				array(
					'model'             => $this->get_ai_model(),
					'messages'          => $query,
					'max_tokens'        => $this->get_max_tokens_for_model(),
					'temperature'       => 0.7,
					'top_p'             => 1,
					'frequency_penalty' => 0,
					'presence_penalty'  => 0,
				)
			),
			'timeout' => 30,
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

		if ( is_wp_error( $response ) ) {
			return $current_alt; // Return original if API fails.
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return $current_alt; // Return original if API fails.
		}

		$enhanced_alt = trim( str_replace( '"', '', $data['choices'][0]['message']['content'] ) );

		// Ensure we don't return empty or invalid text.
		if ( empty( $enhanced_alt ) || strlen( $enhanced_alt ) < 10 ) {
			return $current_alt;
		}

		return $enhanced_alt;
	}

	/**
	 * Generate page-specific keywords for a post using AI
	 *
	 * @param int $post_id The post ID.
	 * @return string Comma-separated list of page-specific keywords
	 * @since 1.0.0
	 */
	public function generate_keywords( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		// Get post title and content.
		$title   = $post->post_title;
		$content = wp_strip_all_tags( $post->post_content );

		// Detect the language of the content.
		$detected_language = $this->detect_content_language( $content );

		// Limit content length for API efficiency.
		$content = substr( $content, 0, 1000 );

		$prompt  = "Analyze the following webpage content and generate 3-5 relevant SEO keywords that best describe the main topics and themes:\n\n";
		$prompt .= 'Title: ' . $title . "\n\n";
		$prompt .= 'Content: ' . $content . "\n\n";
		$prompt .= "Requirements:\n";
		$prompt .= "- Return only keywords separated by commas\n";
		$prompt .= "- Focus on main topics and themes\n";
		$prompt .= "- Keep keywords relevant and specific\n";
		$prompt .= "- Maximum 5 keywords\n";
		$prompt .= "- CRITICAL REQUIREMENT: You MUST return keywords in {$detected_language} language only\n";
		$prompt .= "- The content language has been detected as {$detected_language}, so all keywords must be in {$detected_language}\n";
		$prompt .= "- No explanations or additional text\n\n";
		$prompt .= 'Example output: photography, landscape, nature, mountains, outdoor';

		$query = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$args = array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			),
			'body'    => wp_json_encode(
				array(
					'model'             => $this->get_ai_model(),
					'messages'          => $query,
					'max_tokens'        => $this->get_max_tokens_for_model(),
					'temperature'       => 0.7,
					'top_p'             => 1,
					'frequency_penalty' => 0,
					'presence_penalty'  => 0,
				)
			),
			'timeout' => 30,
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', $args );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return '';
		}

		$keywords = trim( str_replace( '"', '', $data['choices'][0]['message']['content'] ) );

		// Clean up and validate keywords.
		if ( empty( $keywords ) || strlen( $keywords ) < 3 ) {
			return '';
		}

		return $keywords;
	}

	/**
	 * Get random SEO keywords from the global keywords list
	 *
	 * @param int $count Number of keywords to select.
	 * @return string Comma-separated list of random keywords
	 * @since 1.0.0
	 */
	private function get_random_seo_keywords( $count ) {
		$global_keywords = get_option( 'altseo_global_keywords', '' );
		if ( empty( $global_keywords ) ) {
			return '';
		}

		// Split keywords and clean them.
		$keywords_array = array_map( 'trim', explode( ',', $global_keywords ) );
		$keywords_array = array_filter( $keywords_array ); // Remove empty elements.

		if ( empty( $keywords_array ) ) {
			return '';
		}

		// Shuffle and select the requested number.
		shuffle( $keywords_array );
		$selected_keywords = array_slice( $keywords_array, 0, $count );

		return implode( ', ', $selected_keywords );
	}

	/**
	 * Detect the language of given text content using comprehensive language detector
	 *
	 * @param string $text The text content to analyze.
	 * @return string The detected language name (e.g., "English", "Spanish", "French", etc.)
	 * @since 1.0.0
	 */
	private function detect_content_language( $text ) {
		// Initialize the language detector if not already done.
		if ( ! class_exists( 'AltSEO_AI_Plus_Language_Detector' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-altseo-ai-plus-language-detector.php';
		}

		$detector = new AltSEO_AI_Plus_Language_Detector();
		return $detector->detect( $text );
	}

	/**
	 * Get a vision-capable model for image processing
	 *
	 * @return string Vision-capable OpenAI model name
	 * @since 1.0.0
	 */
	public function get_vision_model() {
		// Use the user's selected vision model, fall back to gpt-4o-mini.
		return $this->get_vision_ai_model();
	}

	/**
	 * Update the post content in the database with generated alt text
	 * This ensures alt text changes are permanently saved, not just displayed on frontend
	 *
	 * @param int   $post_id The post ID.
	 * @param array $alt_array_saved Array of generated alt texts.
	 * @since 1.0.0
	 */
	private function update_post_content_with_alt_text( $post_id, $alt_array_saved ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$content          = $post->post_content;
		$original_content = $content;
		$updated_images   = 0;

		// Process all images in the content and forcefully update their alt attributes.
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $index => $img_tag ) {
				$img_src = $matches[1][ $index ]; // Extract image src.

				// Extract filename from src.
				$filename = preg_replace( '/[^a-zA-Z0-9_]/', '_', pathinfo( basename( $img_src ), PATHINFO_FILENAME ) );

				// Skip if no alt text was generated for this image.
				if ( ! isset( $alt_array_saved[ $filename ] ) ) {
					continue;
				}

				$new_alt_text = $alt_array_saved[ $filename ];

				// Check if <img> tag already has an alt attribute.
				if ( preg_match( '/alt=["\'](.*?)["\']/i', $img_tag, $alt_matches ) ) {
					$old_alt_text = $alt_matches[1];
					// Replace existing alt attribute.
					$new_img_tag = preg_replace( '/alt=["\'](.*?)["\']/i', 'alt="' . esc_attr( $new_alt_text ) . '"', $img_tag );
				} else {
					// If no alt attribute, add one.
					$new_img_tag = preg_replace( '/<img/i', '<img alt="' . esc_attr( $new_alt_text ) . '"', $img_tag );
				}

				// Replace the old <img> tag with the updated one.
				$content = str_replace( $img_tag, $new_img_tag, $content );
				++$updated_images;
			}
		}

		// Only update the post if content actually changed.
		if ( $original_content !== $content ) {
			// Use a flag to prevent infinite loops.
			$update_data = array(
				'ID'           => $post_id,
				'post_content' => $content,
				'meta_input'   => array(
					'altseo_updating_content' => true,
				),
			);

			// Update the post content in the database.
			$update_result = wp_update_post( $update_data, true );

			if ( ! is_wp_error( $update_result ) ) {
				// Remove the flag.
				delete_post_meta( $post_id, 'altseo_updating_content' );

				// Clear any caching to ensure changes are visible immediately.
				if ( function_exists( 'wp_cache_delete' ) ) {
					wp_cache_delete( $post_id, 'posts' );
				}

				// Clear object cache if available.
				if ( function_exists( 'clean_post_cache' ) ) {
					clean_post_cache( $post_id );
				}
			}
		}
	}

	/**
	 * Verify API key by testing connection to OpenAI
	 *
	 * @return array Result of verification with status and message
	 * @since 1.0.0
	 */
	public function verify_api_key() {
		$result = array(
			'success' => false,
			'message' => '',
		);

		// Apply thorough cleaning to the API key.
		$this->api_key = $this->clean_api_key( $this->api_key );

		if ( empty( $this->api_key ) ) {
			$result['message'] = 'API key is empty. Please enter a valid OpenAI API key.';
			return $result;
		}

		// Log info about the key to debug.
		$key_length      = strlen( $this->api_key );
		$key_starts_with = substr( $this->api_key, 0, 3 );

		if ( ! $this->is_valid_api_key_format( $this->api_key ) ) {
			$prefix            = isset( $this->api_key[0], $this->api_key[1], $this->api_key[2] ) ? substr( $this->api_key, 0, 3 ) : 'N/A';
			$result['message'] = 'Invalid API key format. OpenAI API keys start with "sk-" followed by alphanumeric characters.';
			return $result;
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 15,
		);

		// Use models endpoint for verification.
		$response = wp_remote_get( 'https://api.openai.com/v1/models', $args );

		if ( is_wp_error( $response ) ) {
			$result['message'] = 'Connection error: ' . $response->get_error_message();
			return $result;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 === $status_code && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$result['success'] = true;
			$result['message'] = 'API key verified successfully.';
			return $result;
		}

		if ( 401 === $status_code ) {
			$result['message'] = 'Authentication error: Invalid API key or insufficient permissions.';
		} elseif ( 429 === $status_code ) {
			$result['message'] = 'Rate limit exceeded: Your API requests are being throttled. Please try again later.';
		} elseif ( isset( $data['error']['message'] ) ) {
			$result['message'] = 'API error: ' . $data['error']['message'];
		} else {
			$result['message'] = 'Unexpected error. Status code: ' . $status_code;
		}

		return $result;
	}

	/**
	 * Set API key directly (useful for validation)
	 *
	 * @param string $api_key New API key to set.
	 * @return void
	 * @since 1.0.0
	 */
	public function set_api_key( $api_key ) {
		// Sanitize and clean API key.
		$api_key       = sanitize_text_field( $api_key );
		$this->api_key = $this->clean_api_key( $api_key );

		// Log the cleaned key for debugging.
		$key_length = strlen( $this->api_key );
		$key_prefix = substr( $this->api_key, 0, 3 );
	}

	/**
	 * Clean API key of any problematic characters
	 *
	 * @param string $api_key The API key to clean.
	 * @return string Cleaned API key
	 * @since 1.0.0
	 */
	private function clean_api_key( $api_key ) {
		// Remove all whitespace (spaces, tabs, line breaks).
		$api_key = preg_replace( '/\s+/', '', $api_key );

		// Remove any invisible unicode characters like zero-width spaces.
		$api_key = preg_replace( '/[\x00-\x1F\x7F\xA0]/u', '', $api_key );

		// Remove any quotes that might have been copied with the key.
		$api_key = str_replace( array( '"', "'", '`' ), '', $api_key );

		return $api_key;
	}

	/**
	 * Debug API key format issues
	 *
	 * This provides detailed logging about potential issues with an API key
	 *
	 * @param string $api_key The API key to debug.
	 * @return void
	 * @since 1.0.0
	 */
	public function debug_api_key( $api_key ) {
		$length             = strlen( $api_key );
		$prefix             = strlen( $api_key ) >= 3 ? substr( $api_key, 0, 3 ) : 'N/A';
		$has_spaces         = strpos( $api_key, ' ' ) !== false;
		$has_newlines       = strpos( $api_key, "\n" ) !== false || strpos( $api_key, "\r" ) !== false;
		$has_tabs           = strpos( $api_key, "\t" ) !== false;
		$character_analysis = array();

		// Check individual characters.
		$max_chars = min( $length, 10 );
		for ( $i = 0; $i < $max_chars; $i++ ) {
			$char                 = $api_key[ $i ];
			$ord                  = ord( $char );
			$character_analysis[] = "Pos $i: Char '$char' (ASCII: $ord)";
		}

		// Check if it matches the expected format.
		$matches_format = preg_match( '/^sk-[a-zA-Z0-9_-]{16,}$/', $api_key );

		// Test with different regex patterns.
		$simple_match = preg_match( '/^sk-/', $api_key );
	}

	/**
	 * Force apply alt text to featured images for page builders
	 *
	 * @param int $post_id The post ID to process.
	 * @since 1.0.0
	 */
	public function force_apply_featured_image_alt( $post_id ) {
		if ( ! has_post_thumbnail( $post_id ) ) {
			return;
		}

		$featured_image_id = get_post_thumbnail_id( $post_id );
		$our_alt_text      = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );

		// Additional meta fields that some page builders might use
		if ( ! empty( $our_alt_text ) ) {
			// Update various meta fields that page builders might check
			update_post_meta( $featured_image_id, 'alt', $our_alt_text );
			update_post_meta( $featured_image_id, '_wp_attachment_alt', $our_alt_text );

			// For Elementor
			if ( $this->is_elementor_active() ) {
				update_post_meta( $featured_image_id, '_elementor_image_alt', $our_alt_text );
			}

			// For Divi
			if ( $this->is_divi_active() ) {
				update_post_meta( $featured_image_id, '_et_pb_image_alt', $our_alt_text );
			}
		}
	}

	/**
	 * Filter for Elementor dynamic tags rendering
	 * 
	 * @param mixed                   $value The tag value.
	 * @param \Elementor\Core\DynamicTags\Base_Tag $tag The tag instance.
	 * @return mixed The filtered value
	 * @since 1.0.0
	 */
	public function elementor_render_dynamic_tag( $value, $tag ) {
		// Only process alt text tags
		if ( $tag->get_name() === 'alt-text' || strpos( $tag->get_name(), 'alt' ) !== false ) {
			// If the value is empty, try to get the alt text from related image
			if ( empty( $value ) ) {
				$settings = $tag->get_settings();
				
				// Try to get the image ID from the settings
				$image_id = null;
				
				if ( isset( $settings['image']['id'] ) ) {
					$image_id = $settings['image']['id'];
				} elseif ( isset( $settings['fallback']['id'] ) ) {
					$image_id = $settings['fallback']['id'];
				}
				
				// If we found an image ID, get its alt text
				if ( $image_id ) {
					$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
					if ( ! empty( $alt_text ) ) {
						return $alt_text;
					}
				}
			}
		}
		
		return $value;
	}

	/**
	 * Filter HTML output to fix empty alt attributes
	 *
	 * @param string $html The HTML output.
	 * @return string The filtered HTML output
	 * @since 1.0.0
	 */
	public function fix_elementor_empty_alt( $html ) {
		// Only process if we're on frontend
		if ( is_admin() ) {
			return $html;
		}
		
		// Use regex to find images with empty alt attributes
		return preg_replace_callback(
			'/<img([^>]*)alt=""([^>]*)>/',
			array( $this, 'replace_empty_alt_callback' ),
			$html
		);
	}

	/**
	 * Callback for replacing empty alt attributes
	 *
	 * @param array $matches The regex matches.
	 * @return string The modified HTML
	 * @since 1.0.0
	 */
	private function replace_empty_alt_callback( $matches ) {
		$before_alt = $matches[1];
		$after_alt = $matches[2];
		
		// Try to extract the image ID from class or data attributes
		$image_id = null;
		
		// Look for wp-image-{ID} class
		if ( preg_match( '/class=["\'](.*?)wp-image-(\d+)(.*?)["\']/', $before_alt . $after_alt, $class_matches ) ) {
			$image_id = $class_matches[2];
		}
		
		// Look for data-id attribute
		if ( ! $image_id && preg_match( '/data-id=["\'](\d+)["\']/', $before_alt . $after_alt, $data_matches ) ) {
			$image_id = $data_matches[1];
		}
		
		// Look for attachment ID in the SRC
		if ( ! $image_id && preg_match( '/src=["\'](.*?)["\']/', $before_alt . $after_alt, $src_matches ) ) {
			$src = $src_matches[1];
			$image_id = attachment_url_to_postid( $src );
		}
		
		// If we found an image ID, get its alt text
		if ( $image_id ) {
			$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_text ) ) {
				// Replace the empty alt with the actual alt text
				return '<img' . $before_alt . 'alt="' . esc_attr( $alt_text ) . '"' . $after_alt . '>';
			}
		}
		
		// If we couldn't find or fix it, return the original match
		return $matches[0];
	}

	/**
	 * Filter Elementor attachment image
	 *
	 * @param string       $html    Image HTML.
	 * @param int          $post_id Attachment ID.
	 * @param string       $size    Image size.
	 * @param bool         $icon    Whether to use an icon.
	 * @param array|string $attr    Image attributes.
	 * @return string Modified image HTML
	 * @since 1.0.0
	 */
	public function elementor_attachment_image_filter( $html, $post_id, $size, $icon, $attr ) {
		// Only process if Elementor is active and we're on frontend
		if ( ! $this->is_elementor_active() || is_admin() ) {
			return $html;
		}
		
		// Check if the alt attribute is empty or not present
		if ( empty( $attr['alt'] ) && strpos( $html, 'alt=""' ) !== false ) {
			// Get our alt text for this attachment
			$our_alt_text = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
			
			// If we have our alt text, replace the empty alt
			if ( ! empty( $our_alt_text ) ) {
				$html = str_replace( 'alt=""', 'alt="' . esc_attr( $our_alt_text ) . '"', $html );
			}
		}
		
		return $html;
	}

	/**
	 * Filter post thumbnail HTML for Elementor
	 *
	 * @param string       $html    The post thumbnail HTML.
	 * @param int          $post_id The post ID.
	 * @param int          $thumbnail_id The thumbnail ID.
	 * @param string|array $size    The thumbnail size.
	 * @param array        $attr    The thumbnail attributes.
	 * @return string Modified HTML
	 * @since 1.0.0
	 */
	public function elementor_post_thumbnail_html( $html, $post_id, $thumbnail_id, $size, $attr ) {
		// Only process if Elementor is active and we're on frontend
		if ( ! $this->is_elementor_active() || is_admin() ) {
			return $html;
		}
		
		// Check if the alt attribute is empty
		if ( strpos( $html, 'alt=""' ) !== false || ! isset( $attr['alt'] ) ) {
			// Get our alt text for this attachment
			$our_alt_text = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );
			
			// If we have our alt text, replace the empty alt
			if ( ! empty( $our_alt_text ) ) {
				$html = str_replace( 'alt=""', 'alt="' . esc_attr( $our_alt_text ) . '"', $html );
			}
		}
		
		return $html;
	}

	/**
	 * Enqueue frontend script to fix Elementor dynamic alt tags
	 * 
	 * @since 1.0.0
	 */
	public function enqueue_elementor_alt_fix_script() {
		// Only enqueue on frontend when Elementor is active
		if ( ! $this->is_elementor_active() || is_admin() ) {
			return;
		}
		
		// Make sure public js directory exists
		$plugin_dir_path = plugin_dir_path( dirname( __FILE__ ) );
		$js_dir_path = $plugin_dir_path . 'public/js';
		
		if ( ! file_exists( $js_dir_path ) ) {
			wp_mkdir_p( $js_dir_path );
		}
		
		// Path to the JS file
		$js_file_path = $js_dir_path . '/altseo-elementor-fix.js';
		
		// Create the file if it doesn't exist
		if ( ! file_exists( $js_file_path ) ) {
			$js_content = <<<'EOT'
/*!
 * AltSEO AI+ Elementor Compatibility Script
 * Fixes dynamic alt text attributes in Elementor
 */

(function() {
    'use strict';

    // Function to fix dynamic alt attributes
    function fixElementorDynamicAlts() {
        // Find all images with empty alt attributes
        var images = document.querySelectorAll('img[alt=""]');
        
        images.forEach(function(img) {
            // Try to get ID from classes
            var classes = img.className.split(' ');
            var imageId = null;
            
            // Look for wp-image-{ID} class
            classes.forEach(function(cls) {
                if (cls.indexOf('wp-image-') === 0) {
                    imageId = cls.replace('wp-image-', '');
                }
            });
            
            // Look for data-id attribute
            if (!imageId && img.dataset && img.dataset.id) {
                imageId = img.dataset.id;
            }
            
            // Look for special elementor attributes
            if (!imageId && img.dataset && img.dataset.settings) {
                try {
                    var settings = JSON.parse(img.dataset.settings);
                    if (settings.image && settings.image.id) {
                        imageId = settings.image.id;
                    }
                } catch(e) {
                    console.error('Error parsing Elementor image settings:', e);
                }
            }
            
            // Try to extract ID from the source URL
            if (!imageId && img.src) {
                // Check if URL contains -\\d+ before file extension
                var match = img.src.match(/-(\d+)\.(jpe?g|png|gif|svg|webp)/i);
                if (match && match[1]) {
                    // This might be the attachment ID in the filename
                    imageId = match[1];
                }
            }
            
            if (imageId) {
                // Store the image element reference for access in the XHR callback
                var imgElement = img;
                
                // Make AJAX call to get the alt text
                var xhr = new XMLHttpRequest();
                xhr.open('POST', altSeoElementorData.ajaxUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        if (xhr.status === 200) {
                            try {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success && response.data) {
                                    // Set the alt attribute
                                    imgElement.alt = response.data;
                                    // Also update the aria-label if it exists
                                    if (imgElement.hasAttribute('aria-label')) {
                                        imgElement.setAttribute('aria-label', response.data);
                                    }
                                }
                            } catch(e) {
                                console.error('Error parsing AltSEO response:', e);
                            }
                        } else {
                            console.error('AltSEO request failed with status:', xhr.status);
                        }
                    }
                };
                xhr.send('action=altseo_get_image_alt&image_id=' + imageId + '&nonce=' + altSeoElementorData.nonce);
            }
        });
    }
    
    // Run once on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Wait a bit to ensure Elementor has rendered everything
        setTimeout(fixElementorDynamicAlts, 500);
        
        // Add mutation observer to handle dynamically added content
        var observer = new MutationObserver(function(mutations) {
            var shouldFix = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    // Check if any of the added nodes might contain images
                    for (var i = 0; i < mutation.addedNodes.length; i++) {
                        var node = mutation.addedNodes[i];
                        if (node.nodeType === 1) { // Element node
                            if (node.tagName === 'IMG' || node.querySelector('img')) {
                                shouldFix = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (shouldFix) {
                setTimeout(fixElementorDynamicAlts, 500);
            }
        });
        
        // Start observing the entire document
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Also listen for Elementor frontend events if Elementor is available
        if (window.elementorFrontend) {
            // Run after Elementor frontend init
            if (elementorFrontend.hooks) {
                elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                    setTimeout(fixElementorDynamicAlts, 500);
                });
            }
        }
    });
})();
EOT;

			file_put_contents( $js_file_path, $js_content );
		}
		
		// Register and enqueue the script
		$plugin_dir_url = plugin_dir_url( dirname( __FILE__ ) );
		wp_enqueue_script(
			'altseo-elementor-fix',
			$plugin_dir_url . 'public/js/altseo-elementor-fix.js',
			array( 'jquery' ),
			filemtime( $js_file_path ),
			true
		);
		
		// Localize script with essential data
		wp_localize_script(
			'altseo-elementor-fix',
			'altSeoElementorData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'altseo_frontend_nonce' ),
				'pluginUrl' => $plugin_dir_url,
			)
		);
	}

	/**
	 * Handle widget before rendering in Elementor
	 *
	 * @param \Elementor\Widget_Base $widget The Elementor widget instance
	 * @since 1.0.0
	 */
	public function elementor_before_widget_render( $widget ) {
		// Only process image-related widgets
		$target_widgets = array(
			'image',
			'theme-post-featured-image',
			'image-box',
			'image-carousel',
			'image-gallery',
			'media-carousel',
			'testimonial-carousel',
			'slides',
		);

		if ( ! in_array( $widget->get_name(), $target_widgets, true ) ) {
			return;
		}

		// Get widget settings
		$settings = $widget->get_settings_for_display();
		
		// Handle image settings
		if ( isset( $settings['image']['id'] ) ) {
			$image_id = $settings['image']['id'];
			$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			
			if ( ! empty( $alt_text ) ) {
				// Add the alt text to the settings
				$widget->set_settings( 'image_custom_alt', 'yes' );
				$widget->set_settings( 'image_alt', $alt_text );
				
				// For image carousel, also add it to the settings array
				if ( $widget->get_name() === 'image-carousel' && isset( $settings['carousel_image'] ) ) {
					foreach ( $settings['carousel_image'] as $index => $image ) {
						if ( isset( $image['id'] ) && $image['id'] === $image_id ) {
							$settings['carousel_image'][ $index ]['alt'] = $alt_text;
						}
					}
					$widget->set_settings( 'carousel_image', $settings['carousel_image'] );
				}
			}
		}

		// Handle special case for Post Featured Image widget
		if ( $widget->get_name() === 'theme-post-featured-image' ) {
			global $post;
			if ( $post && has_post_thumbnail( $post->ID ) ) {
				$featured_image_id = get_post_thumbnail_id( $post->ID );
				$alt_text = get_post_meta( $featured_image_id, '_wp_attachment_image_alt', true );
				
				if ( ! empty( $alt_text ) ) {
					// Add the alt text to the settings
					$widget->set_settings( 'custom_alt', 'yes' );
					$widget->set_settings( 'alt_text', $alt_text );
				}
			}
		}
	}
}
