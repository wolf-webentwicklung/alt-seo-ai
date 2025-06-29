<?php
/**
 * Plugin Name: AltSEO AI
 * Plugin URI: https://www.wolfwebentwicklung.de/plugins/altseo-ai
 * Author: Wolf Webentwicklung GmbH
 * Author URI: https://www.alt-seo-ai.com
 * Description: Automatically generates image alt text with Custom Keywords
 * Version: 1.0.1
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Text Domain: altseo-ai
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * AltSEO AI is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * AltSEO AI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * Next Release: ^PHP.8
 *
 * You should have received a copy of the GNU General Public License
 * along with AltSEO AI. If not, see
 * https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package AltSEO_AI
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'ALTSEO_AI_PLUGIN_VERSION', '1.0.1' );
define( 'ALTSEO_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALTSEO_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALTSEO_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files.
require_once ALTSEO_AI_PLUGIN_DIR . 'includes/class-altseo-ai-api.php';
require_once ALTSEO_AI_PLUGIN_DIR . 'includes/class-altseo-ai-content-api.php';
require_once ALTSEO_AI_PLUGIN_DIR . 'includes/class-altseo-ai-language-detector.php';
require_once ALTSEO_AI_PLUGIN_DIR . 'admin/class-altseo-ai-admin.php';
require_once ALTSEO_AI_PLUGIN_DIR . 'admin/class-altseo-ai-admin-metabox.php';
require_once ALTSEO_AI_PLUGIN_DIR . 'public/class-altseo-ai-public.php';

/**
 * Check plugin dependencies
 *
 * @return bool Whether all dependencies are met
 */
function altseo_ai_check_dependencies() {
	if ( ! is_admin() ) {
		return true;
	}

	if ( ! class_exists( 'WP_Http' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'AltSEO AI requires WP_Http class to be available.', 'altseo-ai' ) . '</p></div>';
			}
		);
		deactivate_plugins(
			plugin_basename( __FILE__ ),
			false,
			false,
			false,
			'altseo-ai'
		);
		return false;
	}

	return true;
}

/**
 * Main plugin activation function
 */
function altseo_ai_activate() {
	if ( ! altseo_ai_check_dependencies() ) {
		return;
	}

	// Flush rewrite rules on plugin activation.
	flush_rewrite_rules();

	// Set default options on activation.
	if ( false === get_option( 'altseo_enabled' ) ) {
		add_option( 'altseo_enabled', 1 );
	}

	if ( false === get_option( 'altseo_ai_key' ) ) {
		add_option( 'altseo_ai_key', '' );
	}

	if ( false === get_option( 'altseo_global_keywords' ) ) {
		add_option( 'altseo_global_keywords', '' );
	}

	if ( false === get_option( 'altseo_ai_model' ) ) {
		add_option( 'altseo_ai_model', 'gpt-3.5-turbo' );
	}

	if ( false === get_option( 'altseo_vision_ai_model' ) ) {
		add_option( 'altseo_vision_ai_model', 'gpt-4o-mini' );
	}

	if ( false === get_option( 'altseo_available_models' ) ) {
		add_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
	}

	if ( false === get_option( 'altseo_available_vision_models' ) ) {
		add_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );
	}

	if ( false === get_option( 'altseo_keyword_num' ) ) {
		add_option( 'altseo_keyword_num', 1 );
	}
}

/**
 * Plugin deactivation function
 */
function altseo_ai_deactivate() {
	// Flush rewrite rules on plugin deactivation.
	flush_rewrite_rules();
}

/**
 * Initialize the plugin
 */
function altseo_ai_init() {
	if ( ! altseo_ai_check_dependencies() ) {
		return;
	}

	// Migrate options to ensure arrays are properly stored.
	altseo_ai_migrate_options();

	// Initialize admin interface.
	if ( is_admin() ) {
		new AltSEO_AI_Admin();
		new AltSEO_AI_Admin_Metabox();
	}

	// Initialize public interface.
	new AltSEO_AI_Public( 'altseo-ai', ALTSEO_AI_PLUGIN_VERSION );

	// Load plugin text domain for internationalization.
	load_plugin_textdomain( 'altseo-ai', false, plugin_basename( __DIR__ ) . '/languages' );
}

/**
 * Migrate string options to array format if needed
 * This fixes any existing installations where options might be stored as strings
 */
function altseo_ai_migrate_options() {
	// Migrate available_models option.
	$available_models = get_option( 'altseo_available_models' );
	if ( ! is_array( $available_models ) && ! empty( $available_models ) ) {
		update_option( 'altseo_available_models', array( $available_models ) );
	}

	// Migrate available_vision_models option.
	$available_vision_models = get_option( 'altseo_available_vision_models' );
	if ( ! is_array( $available_vision_models ) && ! empty( $available_vision_models ) ) {
		update_option( 'altseo_available_vision_models', array( $available_vision_models ) );
	}
}

// Plugin lifecycle hooks.
register_activation_hook( __FILE__, 'altseo_ai_activate' );
register_deactivation_hook( __FILE__, 'altseo_ai_deactivate' );

// Initialize plugin.
add_action( 'plugins_loaded', 'altseo_ai_init' );

// Register upload filter to automatically generate alt text.
add_filter( 'wp_handle_upload', 'altseo_ai_handle_upload' );

/**
 * Automatically generate alt text on upload
 *
 * @param array $upload Upload data array.
 * @return array Modified upload data array.
 */
function altseo_ai_handle_upload( $upload ) {
	// Only proceed if the upload was successful and it's an image.
	if ( isset( $upload['error'] ) || ! isset( $upload['type'] ) ) {
		return $upload;
	}

	// Check if it's an image type.
	if ( strpos( $upload['type'], 'image/' ) !== 0 ) {
		return $upload;
	}

	// Check if AltSEO is enabled.
	if ( ! get_option( 'altseo_enabled' ) ) {
		return $upload;
	}

	// Return upload data (processing happens later in admin class).
	return $upload;
}
