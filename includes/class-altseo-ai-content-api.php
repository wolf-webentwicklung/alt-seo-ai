<?php
/**
 * Content API class file.
 *
 * @package AltSEO_AI
 * @since   1.0.0
 */

/**
 * The content handling class.
 *
 * This is used to handle operations related to post content processing for AltSEO AI+.
 *
 * @since      1.0.0
 * @package    AltSEO_AI
 * @subpackage AltSEO_AI/includes
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */
class AltSEO_AI_Content_API {
	/**
	 * Strips HTML tags and returns clean text only.
	 *
	 * @param int $post_id ID of post to fetch content from.
	 * @return string|bool Clean text content or false if post not found
	 * @since 1.0.0
	 */
	public function get_clean_texts_from_post( $post_id ) {
		// Validate post_id is numeric.
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		// Get post object.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Get and clean content.
		$text = $post->post_content;

		// Remove shortcodes for AI processing.
		$pattern = '/\[mwai_.*?\]/';
		$text    = preg_replace( $pattern, '', $text );

		// Clean and prepare text.
		$text = $this->clean_text( $text );
		$text = $this->clean_sentences( $text );

		return $text;
	}

	/**
	 * Clean raw text by removing HTML and normalizing line breaks.
	 *
	 * @param string $raw_text The raw text to clean.
	 * @return string Cleaned text
	 * @since 1.0.0
	 */
	public function clean_text( $raw_text = '' ) {
		$text = html_entity_decode( $raw_text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/[\r\n]+/', "\n", $text );
		return $text . ' ';
	}

	/**
	 * Clean and deduplicate sentences from text.
	 *
	 * @param string $text Text to clean and process.
	 * @return string Processed text with unique sentences
	 * @since 1.0.0
	 */
	public function clean_sentences( $text ) {
		// Maximum tokens to consider (limit for API calls).
		$max_tokens = 2000;

		// Split into sentences (works for multiple languages).
		$sentences        = preg_split( '/(?<=[.?!。．！？])+/u', $text );
		$hashes           = array();
		$unique_sentences = array();
		$length           = 0;

		// Process each sentence.
		foreach ( $sentences as $sentence ) {
			// Trim whitespace and control characters.
			$sentence = preg_replace( '/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $sentence );

			// Skip empty sentences.
			if ( empty( $sentence ) ) {
				continue;
			}

			// Deduplicate using hash.
			$hash = md5( $sentence );
			if ( ! in_array( $hash, $hashes, true ) ) {
				// Simple estimation of token count.
				$tokens_count = ceil( mb_strlen( $sentence ) / 4 );

				// Skip if we'd exceed token limit.
				if ( $length + $tokens_count > $max_tokens ) {
					continue;
				}

				$hashes[]           = $hash;
				$unique_sentences[] = $sentence;
				$length            += $tokens_count;
			}
		}

		// Join sentences and clean result.
		$fresh_text = implode( ' ', $unique_sentences );
		$fresh_text = preg_replace( '/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $fresh_text );

		return $fresh_text;
	}

	/**
	 * Safe input handling for quoted text.
	 *
	 * @param string $text Text to process.
	 * @return string Processed text
	 * @since 1.0.0
	 */
	public function safe_input_from_quote( $text ) {
		$text = str_replace( '\"', '"', $text );
		$text = str_replace( "\'", "'", $text );
		$text = str_replace( '"', '\"', $text );
		$text = str_replace( "'", '\"', $text );
		$text = str_replace( PHP_EOL, '', $text );
		return $text;
	}
}
