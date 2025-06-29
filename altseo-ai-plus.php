<?php
/**
 * Plugin Name: AltSEO AI Plus
 * Plugin URI: https://www.wolfwebentwicklung.de/plugins/altseo-ai-plus
 * Author: Wolf Webentwicklung GmbH
 * Author URI: https://www.alt-seo-ai.com
 * Description: Automatically generates image alt text with Custom Keywords
 * Version: 1.0.1
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Text Domain: altseo-ai-plus
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * AltSEO AI Plus is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * AltSEO AI Plus is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * Next Release: ^PHP.8
 *
 * You should have received a copy of the GNU General Public License
 * along with AltSEO AI Plus. If not, see
 * https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package AltSEO_AI_Plus
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'ALTSEO_AI_PLUS_PLUGIN_VERSION', '1.0.1' );
define( 'ALTSEO_AI_PLUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALTSEO_AI_PLUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ALTSEO_AI_PLUS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files.
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'includes/class-altseo-ai-plus-api.php';
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'includes/class-altseo-ai-plus-content-api.php';
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'includes/class-altseo-ai-plus-language-detector.php';
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'admin/class-altseo-ai-plus-admin.php';
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'admin/class-altseo-ai-plus-admin-metabox.php';
require_once ALTSEO_AI_PLUS_PLUGIN_DIR . 'public/class-altseo-ai-plus-public.php';

/**
 * Check plugin dependencies
 *
 * @return bool Whether all dependencies are met
 */
function altseo_ai_plus_check_dependencies() {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, '7.2', '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__(
					'AltSEO AI Plus requires PHP 7.2 or higher. Please update PHP.',
					'altseo-ai-plus'
				);
				echo '</p></div>';
			}
		);
		return false;
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), '5.2', '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__(
					'AltSEO AI Plus requires WordPress 5.2 or higher. Please update WordPress.',
					'altseo-ai-plus'
				);
				echo '</p></div>';
			}
		);
		return false;
	}

	// Check required PHP extensions.
	if ( ! extension_loaded( 'json' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__(
					'AltSEO AI Plus requires the JSON PHP extension.',
					'altseo-ai-plus'
				);
				echo '</p></div>';
			}
		);
		return false;
	}

	return true;
}

// Initialize the plugin only if dependencies are met.
if ( altseo_ai_plus_check_dependencies() ) {
	/**
	 * Main plugin initialization
	 */
	function altseo_ai_plus_init() {
		$admin_menu    = new AltSEO_AI_Plus_Admin();
		$admin_metabox = new AltSEO_AI_Plus_Admin_Metabox();
		$public        = new AltSEO_AI_Plus_Public( 'altseo-ai-plus', ALTSEO_AI_PLUS_PLUGIN_VERSION );
	}

	// Initialize the plugin.
	altseo_ai_plus_init();

	/**
	 * Register activation hook
	 */
	function altseo_ai_plus_activate() {
		// Set default options on activation.
		if ( ! get_option( 'altseo_enabled' ) ) {
			add_option( 'altseo_enabled', 1 );
			add_option( 'altseo_keyword_num', 1 );
		}

		// Create custom capabilities.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_altseo_options' );
		}

		// Clear any transients.
		delete_transient( 'altseo_bulk_generation_running' );
	}
	register_activation_hook( __FILE__, 'altseo_ai_plus_activate' );

	/**
	 * Register deactivation hook
	 */
	function altseo_ai_plus_deactivate() {
		// Clean up transients.
		delete_transient( 'altseo_bulk_generation_running' );
	}
	register_deactivation_hook( __FILE__, 'altseo_ai_plus_deactivate' );

	/**
	 * Load plugin text domain for translation
	 */
	function altseo_ai_plus_load_textdomain() {
		load_plugin_textdomain(
			'altseo-ai-plus',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
	add_action( 'plugins_loaded', 'altseo_ai_plus_load_textdomain' );
}
