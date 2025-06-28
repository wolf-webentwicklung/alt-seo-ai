<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://www.wolfwebentwicklung.de
 * @since      1.0.1
 *
 * @package    AltSEO_AI_Plus
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin options and transients
 */
function altseo_ai_plus_uninstall() {
	// Plugin options.
	delete_option( 'altseo_ai_key' );
	delete_option( 'altseo_global_keywords' );
	delete_option( 'altseo_enabled' );
	delete_option( 'altseo_keyword_num' );
	delete_option( 'altseo_seo_keywords_count' );
	delete_option( 'altseo_ai_model' );
	delete_option( 'altseo_vision_ai_model' );
	delete_option( 'altseo_available_models' );
	delete_option( 'altseo_available_vision_models' );

	// Bulk generation options and transients.
	delete_option( 'altseo_bulk_keyword_post_list' );
	delete_option( 'altseo_bulk_keyword_post_list_total' );
	delete_option( 'altseo_bulk_keyword_lock' );
	delete_option( 'altseo_bulk_alt_post_list' );
	delete_option( 'altseo_bulk_alt_post_list_total' );
	delete_option( 'altseo_bulk_alt_lock' );
	delete_option( 'altseo_bulk_keyword_stopped' );
	delete_option( 'altseo_bulk_alt_stopped' );
	delete_transient( 'altseo_bulk_generation_running' );

	// Remove custom capabilities.
	$admin_role = get_role( 'administrator' );
	if ( $admin_role ) {
		$admin_role->remove_cap( 'manage_altseo_options' );
	}

	// Note: We intentionally do not delete post meta 'altseo_keywords_tag'.
	// as this contains SEO data that should be preserved even if the plugin is removed.
}

altseo_ai_plus_uninstall();
