<?php
/**
 * The admin-specific functionality of the plugin.
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
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AltSEO_AI_Plus
 * @subpackage AltSEO_AI_Plus/admin
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */
class AltSEO_AI_Plus_Admin {

	/**
	 * Add security headers
	 */
	public function add_security_headers() {
		if ( is_admin() ) {
			// Prevent MIME type sniffing.
			header( 'X-Content-Type-Options: nosniff' );
			// Prevent clickjacking.
			header( 'X-Frame-Options: SAMEORIGIN' );
			// XSS Protection.
			header( 'X-XSS-Protection: 1; mode=block' );
		}
	}

	/**
	 * Initialize the class and set its properties.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'add_security_headers' ) );
		add_action( 'admin_menu', array( $this, 'plugin_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_ajax_bulk_generate_keyword', array( $this, 'ajax_bulk_generate_keyword' ) );
		add_action( 'wp_ajax_ajax_bulk_generate_alt', array( $this, 'ajax_bulk_generate_alt' ) );
		add_action( 'wp_ajax_altseo_refresh_models', array( $this, 'ajax_refresh_models' ) );
		add_action( 'wp_ajax_altseo_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_altseo_stop_bulk_generation', array( $this, 'ajax_stop_bulk_generation' ) );
		add_action( 'wp_ajax_altseo_refresh_vision_models', array( $this, 'ajax_refresh_vision_models' ) );
		add_action( 'wp_ajax_altseo_cleanup_bulk_states', array( $this, 'ajax_cleanup_bulk_states' ) );
		add_action( 'wp_ajax_altseo_get_bulk_status', array( $this, 'ajax_get_bulk_status' ) );
		add_action( 'wp_ajax_altseo_get_image_alt', array( $this, 'ajax_get_image_alt' ) );
		add_action( 'wp_ajax_nopriv_altseo_get_image_alt', array( $this, 'ajax_get_image_alt' ) );
	}

	/**
	 * Register the plugin settings page.
	 */
	public function plugin_settings_page() {
		global $altseo_ai_plus_custom_menu;
		$altseo_ai_plus_custom_menu = add_submenu_page(
			'upload.php',                                  // Parent menu slug (Media).
			__( 'AltSeo-AI Plus Settings', 'altseo-ai-plus' ), // Page title.
			__( 'AltSeo-AI Plus', 'altseo-ai-plus' ),     // Menu title.
			'manage_options',                              // Capability.
			'altseo-ai-plus-settings',                     // Menu slug.
			array( $this, 'settings' )                     // Callback function.
		);
	}

	/**
	 * Enqueue scripts and styles for admin pages
	 *
	 * @param string $hook The current admin page hook.
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $hook ) {
		global $altseo_ai_plus_custom_menu;
		$allowed = array( $altseo_ai_plus_custom_menu, 'post-new.php', 'post.php' );
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}
		// Load Vue.js app only on the plugin's settings page.
		if ( $altseo_ai_plus_custom_menu === $hook ) {
			// Enqueue Vue.js from CDN for production - load in head to prevent FOUC.
			wp_enqueue_script( 'vue-js', 'https://unpkg.com/vue@3/dist/vue.global.prod.js', array(), '3.3.4', false );

			// Enqueue our compiled Vue app - load in footer after Vue.
			wp_enqueue_style( 'altseo-vue-styles', plugin_dir_url( __FILE__ ) . 'assets/dist/bundle.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/dist/bundle.css' ) );
			wp_enqueue_style( 'altseo-bulk-tools-styles', plugin_dir_url( __FILE__ ) . 'assets/css/altseo-bulk-tools.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/altseo-bulk-tools.css' ) );
			wp_enqueue_script( 'altseo-vue-app', plugin_dir_url( __FILE__ ) . 'assets/dist/bundle.js', array( 'vue-js' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/dist/bundle.js' ), true );

			// Add fallback detection script - load in footer.
			wp_enqueue_script( 'altseo-fallback-detector', plugin_dir_url( __FILE__ ) . 'assets/js/fallback-detector.js', array(), '1.0.1', true );

			// Pass data to the Vue app.
			wp_localize_script(
				'altseo-vue-app',
				'altSeoData',
				array(
					'apiKey'              => wp_kses_post( get_option( 'altseo_ai_key' ) ),
					'models'              => array_map( 'esc_html', get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) ) ),
					'selectedModel'       => esc_html( get_option( 'altseo_ai_model', 'gpt-3.5-turbo' ) ),
					'visionModels'        => array_map( 'esc_html', get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) ) ),
					'selectedVisionModel' => esc_html( get_option( 'altseo_vision_ai_model', 'gpt-4o-mini' ) ),
					'globalKeywords'      => esc_html( get_option( 'altseo_global_keywords' ) ),
					'enabled'             => get_option( 'altseo_enabled' ) === '1' || get_option( 'altseo_enabled' ) === 1 || get_option( 'altseo_enabled' ) === true,
					'keywordNum'          => intval( get_option( 'altseo_keyword_num', 1 ) ),
					'seoKeywordsCount'    => intval( get_option( 'altseo_seo_keywords_count', 1 ) ),
					'ajaxUrl'             => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'               => wp_create_nonce( 'altseo_admin_nonce' ),
					'refreshModelsNonce'  => wp_create_nonce( 'altseo_admin_nonce' ),
					'fallbackUrl'         => esc_url( admin_url( 'admin.php?page=altseo-ai-plus-settings&use_legacy=1' ) ),
					'pluginUrl'           => esc_url( plugin_dir_url( __FILE__ ) ),
				)
			);
		}

		// Load legacy scripts for post editor pages.
		wp_enqueue_style( 'altseo_tag_input_css', plugin_dir_url( __FILE__ ) . 'assets/css/jquery.tagsinput.min.css', array(), '1.0.0' );
		wp_enqueue_script( 'altseo_tag_input_js', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.tagsinput.min.js', array(), '1.0.0', true );
		wp_enqueue_style( 'altseo_ai_alt_style', plugin_dir_url( __FILE__ ) . 'assets/css/altseo-ai-tag-style.css', array(), '1.0.0' );
		wp_enqueue_script( 'altseo_ai_alt_js', plugin_dir_url( __FILE__ ) . 'assets/js/altseo-ai-tag-js.js', array(), '1.0.0', true );

		// Load the bulk generation stop functionality and model refresh for legacy form.
		if ( $altseo_ai_plus_custom_menu === $hook ) {
			wp_enqueue_script( 'altseo-bulk-stop', plugin_dir_url( __FILE__ ) . 'assets/js/bulk-generation-stop.js', array( 'altseo-vue-app' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/js/bulk-generation-stop.js' ), true );
			wp_enqueue_script( 'altseo_model_refresh_js', plugin_dir_url( __FILE__ ) . 'assets/js/model-refresh.js', array( 'jquery' ), '1.0.0', true );
			wp_localize_script(
				'altseo_model_refresh_js',
				'altseoModels',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'altseo_admin_nonce' ),
				)
			);
		}

		if ( ! wp_script_is( 'jquery' ) ) {
			wp_enqueue_script( 'jquery' );
		}
	}

	/**
	 * Render the settings page
	 *
	 * @since 1.0.0
	 */
	public function settings() {
		// Check if we should show legacy form or Vue app.
		$use_vue = apply_filters( 'altseo_ai_plus_use_vue_interface', true );

		// Force legacy mode ONLY for this request if specifically requested (fallback).
		if ( isset( $_GET['use_legacy'] ) && '1' === $_GET['use_legacy'] ) {
			$use_vue = false;
		}

		if ( $use_vue ) {
			// Vue.js App Container.
			?>
			<div id="alt-seo-ai-plus-settings-wrap" class="wrap">
				<div id="altseo-app">
					<div id="loading-fallback" style="text-align: center; padding: 50px; min-height: 400px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
						<span class="spinner is-active" style="float: none; margin: 0 0 15px 0; visibility: visible;"></span>
						<p style="margin: 0; color: #666;">Loading AltSEO AI+ Settings...</p>
						<p style="margin: 10px 0 0 0; font-size: 12px; color: #999;">Please wait while the interface loads</p>
					</div>
					<div id="connection-error" style="display: none; text-align: center; padding: 50px; min-height: 400px;">
						<div style="background: #fff2cd; border: 1px solid #dba617; border-radius: 4px; padding: 20px; max-width: 600px; margin: 0 auto;">
							<h3 style="color: #8a6914; margin-top: 0;">⚠️ Connection Error</h3>
							<p style="color: #8a6914; margin-bottom: 20px;">
								Unable to load the modern interface due to internet connectivity issues. 
								This could be due to:
							</p>
							<ul style="text-align: left; color: #8a6914; margin-bottom: 20px;">
								<li>No internet connection</li>
								<li>Firewall blocking external resources</li>
								<li>CDN service temporarily unavailable</li>
							</ul>
							<p style="color: #8a6914; margin-bottom: 20px;">
								<strong>Please check your internet connection and try again.</strong>
							</p>
							<div style="margin-top: 20px;">
								<button onclick="retryVueInterface()" class="button button-primary" style="margin-right: 10px;">
									🔄 Retry
								</button>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=altseo-ai-plus-settings&use_legacy=1' ) ); ?>" class="button button-secondary">
									📝 Use Legacy Interface
								</a>
							</div>
							
							<script>
							function retryVueInterface() {
								// Get current URL without use_legacy parameter.
								var currentUrl = window.location.href;
								var baseUrl = currentUrl.split('?')[0];
								var params = new URLSearchParams(window.location.search);
								
								// Remove use_legacy parameter if it exists.
								params.delete('use_legacy');
								
								// Construct clean URL.
								var cleanUrl = baseUrl + '?page=altseo-ai-plus-settings';
								if (params.toString()) {
									cleanUrl += '&' + params.toString();
								}
								
								// Reload the page with clean URL.
								window.location.href = cleanUrl;
							}
							</script>
						</div>
					</div>
				</div>
			</div>
			<?php
			return;
		}
			// Legacy PHP Form (fallback).
		?>
		<div id="alt-seo-ai-plus-settings-wrap" class="wrap">
		<h2>Alt Seo AI + Settings</h2>
		
		<?php if ( isset( $_GET['use_legacy'] ) && '1' === $_GET['use_legacy'] ) : ?>
		<div class="notice notice-info is-dismissible" style="margin: 20px 0;">
			<p>
				<strong>💡 Using Legacy Interface</strong> - 
				You're currently using the legacy interface due to connectivity issues. 
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=altseo-ai-plus-settings' ) ); ?>" class="button button-small">
					Try Modern Interface Again
				</a>
			</p>
		</div>
		<?php endif; ?>
		
		<?php
			$msg = '';
		if ( isset( $_POST['altseo_global_keywords'] ) || isset( $_POST['altseo_ai_key'] ) ) {
			// Verify nonce for security.
			if ( ! isset( $_POST['altseo_legacy_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['altseo_legacy_nonce'] ) ), 'altseo_legacy_settings' ) ) {
				wp_die( esc_html__( 'Security check failed. Please try again.', 'altseo-ai-plus' ) );
			}

			// Check user capabilities.
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'altseo-ai-plus' ) );
			}

			// Sanitize and validate all inputs.
			update_option( 'altseo_global_keywords', sanitize_text_field( wp_unslash( $_POST['altseo_global_keywords'] ) ) );
			update_option( 'altseo_ai_key', sanitize_text_field( wp_unslash( $_POST['altseo_ai_key'] ) ) );

			if ( isset( $_POST['altseo_keyword_num'] ) ) {
				$keyword_num = intval( $_POST['altseo_keyword_num'] );
				if ( $keyword_num >= 1 && $keyword_num <= 10 ) {
					update_option( 'altseo_keyword_num', $keyword_num );
				}
			}

			if ( isset( $_POST['altseo_seo_keywords_count'] ) ) {
				$seo_keywords_count = intval( $_POST['altseo_seo_keywords_count'] );
				if ( $seo_keywords_count >= 1 && $seo_keywords_count <= 10 ) {
					update_option( 'altseo_seo_keywords_count', $seo_keywords_count );
				}
			}
			if ( isset( $_POST['altseo_ai_model'] ) ) {
				$model            = sanitize_text_field( wp_unslash( $_POST['altseo_ai_model'] ) );
				$available_models = get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
				if ( in_array( $model, $available_models, true ) ) {
					update_option( 'altseo_ai_model', $model );
				}
			}
			if ( isset( $_POST['altseo_vision_ai_model'] ) ) {
				$vision_model            = sanitize_text_field( wp_unslash( $_POST['altseo_vision_ai_model'] ) );
				$available_vision_models = get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );
				if ( in_array( $vision_model, $available_vision_models, true ) ) {
					update_option( 'altseo_vision_ai_model', $vision_model );
				}
			}
			if ( isset( $_POST['altseo_enabled'] ) ) {
				update_option( 'altseo_enabled', '1' );
			} else {
				update_option( 'altseo_enabled', '0' );
			}
			$msg = '<span style="font-weight:bold;margin-left:20px;"> &#10004; ' . esc_html__( 'Saved Successfully!', 'altseo-ai-plus' ) . '</span>';
		}

			$altseo_ai_key          = esc_html( get_option( 'altseo_ai_key' ) );
			$altseo_global_keywords = esc_html( get_option( 'altseo_global_keywords' ) );
			$altseo_enabled         = get_option( 'altseo_enabled' ) === '1' || get_option( 'altseo_enabled' ) === 1 || get_option( 'altseo_enabled' ) === true;
			$altseo_keyword_num     = intval( get_option( 'altseo_keyword_num', 1 ) );
			$altseo_api             = new AltSEO_AI_Plus_API();
			$altseo_ai_model        = $altseo_api->get_ai_model();
			$altseo_vision_ai_model = $altseo_api->get_vision_ai_model();

			// Get available models.
			$available_models        = get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
			$available_vision_models = get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );
		?>
		<form method="post" action="" name="alt-seo-ai-plus-settings-form" id="alt-seo-ai-plus-settings-form" class="altseo-settings-form">
			<?php wp_nonce_field( 'altseo_legacy_settings', 'altseo_legacy_nonce' ); ?>
			<div class="altseo-logo-section">
				<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'assets/images/alt-seo-ai-logo.png' ); ?>" alt="AltSEO AI+ Logo" class="altseo-form-logo" />
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label>OpenAI Key:</label>
								<span class="field-description">Enter your OpenAI API key to enable AI-powered alt text and keyword generation</span>
							</th>
							<td><input type="password" size="57" name="altseo_ai_key" id="altseo_ai_key" value="<?php echo esc_html( $altseo_ai_key ); ?>" />  </td>
						</tr>
						<tr>
							<th>
								<label>OpenAI Model (Keywords):</label>
								<span class="field-description">Select which OpenAI model to use for generating keywords and text content</span>
							</th>
							<td>
								<select name="altseo_ai_model" style="width:200px" id="altseo_ai_model" data-selected="<?php echo esc_attr( $altseo_ai_model ); ?>">
									<?php foreach ( $available_models as $model ) : ?>
										<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $altseo_ai_model, $model ); ?>><?php echo esc_html( $model ); ?></option>
									<?php endforeach; ?>
								</select>
								<button id="refresh_models_btn" type="button" class="button-secondary">Refresh Models</button>
								<div id="model_refresh_message" style="display:none;"></div>
							</td>
						</tr>
						<tr>
							<th>
								<label>OpenAI Vision Model (Images):</label>
								<span class="field-description">Select which vision-capable OpenAI model to use for generating image alt texts</span>
							</th>
							<td>
								<select name="altseo_vision_ai_model" style="width:200px" id="altseo_vision_ai_model" data-selected="<?php echo esc_attr( $altseo_vision_ai_model ); ?>">
									<?php foreach ( $available_vision_models as $model ) : ?>
										<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $altseo_vision_ai_model, $model ); ?>><?php echo esc_html( $model ); ?></option>
									<?php endforeach; ?>
								</select>
								<button id="refresh_vision_models_btn" type="button" class="button-secondary">Refresh Vision Models</button>
								<div id="vision_model_refresh_message" style="display:none;"></div>
							</td>
						</tr>
						<tr>
							<th>
								<label>SEO Keywords:</label>
								<span class="field-description">Default keywords to use when specific post keywords are not available</span>
							</th>
							<td><input type="text" size="50" name="altseo_global_keywords" id="altseo_global_keywords" value="<?php echo esc_html( $altseo_global_keywords ); ?>" />  </td>
						</tr>
						<tr>
							<th>
								<label>SEO Keywords for Alt Tags:</label>
								<span class="field-description">How many random SEO keywords to include in generated alt tags</span>
							</th>
							<td>
								<select name="altseo_seo_keywords_count" style="width:100px" id="altseo_seo_keywords_count">
									<?php
									$seo_keywords_count = get_option( 'altseo_seo_keywords_count', 1 );
									for ( $i = 1; $i <= 10; $i++ ) :
										?>
										<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $seo_keywords_count, $i ); ?>><?php echo esc_html( $i ); ?></option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>
								<label>Auto Generate on Save:</label>
								<span class="field-description">Automatically generate keywords and alt tags when posts are saved or updated</span>
							</th>
							<td>
								<div class="ui-switch-container">
									<label class="ui-switch">
										<input type="checkbox" name="altseo_enabled" id="altseo_enabled" value="yes" 
										<?php
										if ( $altseo_enabled ) {
											?>
											checked<?php } ?> />
										<span class="ui-switch-slider"></span>
									</label>
									<span class="ui-switch-label"><?php echo $altseo_enabled ? 'Enabled' : 'Disabled'; ?></span>
								</div>
							</td>
						</tr>
						<tr>
							<th>
								<label>Keywords to Generate:</label>
								<span class="field-description">Number of keywords the AI should generate for each post</span>
							</th>
							<td>
								<select name="altseo_keyword_num" style="width:100px" id="altseo_keyword_num">
									<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
										<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $altseo_keyword_num, $i ); ?>><?php echo esc_html( $i ); ?></option>
									<?php endfor; ?>
								</select>
							</td>
						</tr>
							<tr>
								<th>
									<label>Generate Keywords:</label>
									<span class="field-description">Process all existing posts to generate keywords in bulk</span>
								</th>
								<td>
									<button id="generate_keyword_btn" type="button" class="button-secondary">Generate Keywords For All Posts Now</button>
									<div class="progress_section">
										<div class="progress_bar"></div>
										<div class="progress_report">0% complete ....</div>
									</div>
								</td>
							</tr>                            <tr>
								<th>
									<label>Generate Alt Tags:</label>
									<span class="field-description">Process all existing posts to generate alt tags for images in bulk</span>
								</th>
								<td>
									<button id="generate_alt_btn" type="button" class="button-secondary">Generate Alt Tags For All Posts Now</button>
									<div class="progress_section2">
										<div class="progress_bar2"></div>
										<div class="progress_report2">0% complete ....</div>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" /> <?php echo wp_kses_post( $msg ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler for bulk keyword generation.
	 *
	 * @since 1.0.0
	 */
	public function ajax_bulk_generate_keyword() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'altseo_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
			wp_die();
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			wp_die();
		}

		// Increase script timeout for bulk operations.
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 ); // 5 minutes.
		}

		// Check if process has been stopped before doing anything.
		if ( get_option( 'altseo_bulk_keyword_stopped' ) ) {
			delete_option( 'altseo_bulk_keyword_stopped' );
			delete_option( 'altseo_bulk_keyword_post_list' );
			delete_option( 'altseo_bulk_keyword_post_list_total' );
			delete_option( 'altseo_bulk_keyword_lock' ); // Clear lock.
			wp_send_json_success(
				array(
					'percentage' => -1,
					'message'    => 'Process was stopped',
				)
			);
			wp_die();
		}

		// Check for existing lock to prevent concurrent processing.
		$lock_time = get_option( 'altseo_bulk_keyword_lock' );
		if ( $lock_time && ( time() - $lock_time ) < 120 ) { // 2 minute lock timeout.
			wp_send_json_error(
				array(
					'percentage' => 0,
					'message'    => 'Another process is already running',
				)
			);
			wp_die();
		}

		// Set lock.
		update_option( 'altseo_bulk_keyword_lock', time() );

		try {
			if ( ! get_option( 'altseo_bulk_keyword_post_list' ) ) {
				$all_post_ids = get_posts(
					array(
						'fields'         => 'ids', // Only get post IDs.
						'posts_per_page' => -1,
						'post_type'      => apply_filters( 'altseo_ai_plus_post_types', array( 'post', 'page' ) ),
						'orderby'        => 'ID',
						'order'          => 'ASC', // Process in consistent order.
						'post_status'    => 'publish',
					)
				);

				// Log all posts that will be processed.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'AltSEO AI+: Keyword generation will process ' . count( $all_post_ids ) . ' posts: ' . implode( ', ', $all_post_ids ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}

				update_option( 'altseo_bulk_keyword_post_list', $all_post_ids );
				update_option( 'altseo_bulk_keyword_post_list_total', count( $all_post_ids ) );
			}

			// Check again if process has been stopped during initialization.
			if ( get_option( 'altseo_bulk_keyword_stopped' ) ) {
				delete_option( 'altseo_bulk_keyword_stopped' );
				delete_option( 'altseo_bulk_keyword_post_list' );
				delete_option( 'altseo_bulk_keyword_post_list_total' );
				delete_option( 'altseo_bulk_keyword_lock' ); // Clear lock.
				wp_send_json_success(
					array(
						'percentage' => -1,
						'message'    => 'Process was stopped',
					)
				);
				wp_die();
			}

			$post_ids    = get_option( 'altseo_bulk_keyword_post_list' );
			$total_posts = get_option( 'altseo_bulk_keyword_post_list_total' );

			if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
				// No more posts to process or invalid data.
				delete_option( 'altseo_bulk_keyword_post_list' );
				delete_option( 'altseo_bulk_keyword_post_list_total' );
				delete_option( 'altseo_bulk_keyword_lock' ); // Clear lock.
				wp_send_json_success(
					array(
						'percentage' => 100,
						'message'    => 'Processing complete',
					)
				);
				wp_die();
			}

			$current_post_id = array_shift( $post_ids );
			$remaining_posts = count( $post_ids );
			$processed_posts = $total_posts - $remaining_posts - 1; // -1 for the current post being processed.
			$percentage      = floor( ( $processed_posts / $total_posts ) * 100 );

			// Log progress details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "AltSEO AI+: Keyword generation progress - Processing post $current_post_id ($processed_posts/$total_posts = $percentage%). Remaining: " . implode( ', ', array_slice( $post_ids, 0, 5 ) ) . ( $remaining_posts > 5 ? '...' : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			update_option( 'altseo_bulk_keyword_post_list', $post_ids );
			$altseo_api = new AltSEO_AI_Plus_API();
			$altseo_api->update_keywords( $current_post_id, true ); // Force update for bulk generation.

			// Clear lock before responding.
			delete_option( 'altseo_bulk_keyword_lock' );

			// Check if we're done.
			if ( ! count( $post_ids ) ) {
				delete_option( 'altseo_bulk_keyword_post_list' );
				delete_option( 'altseo_bulk_keyword_post_list_total' );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'AltSEO AI+: Keyword generation completed successfully for all posts' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				wp_send_json_success(
					array(
						'percentage' => 100,
						'message'    => 'All posts processed successfully',
					)
				);
			} else {
				wp_send_json_success(
					array(
						'percentage' => $percentage,
						'message'    => "Processing post $current_post_id... ($processed_posts of $total_posts)",
					)
				);
			}
		} catch ( Exception $e ) {
			// Clear lock on error.
			delete_option( 'altseo_bulk_keyword_lock' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AltSEO AI+: Bulk keyword generation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error(
				array(
					'message' => 'Error processing: ' . $e->getMessage(),
				)
			);
		}

		wp_die(); // this is required to terminate immediately and return a proper response.
	}

	/**
	 * AJAX handler for bulk alt text generation
	 *
	 * @since 1.0.0
	 */
	public function ajax_bulk_generate_alt() {
		// Check nonce for security.
		if ( ! check_ajax_referer( 'altseo_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
			wp_die();
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
			wp_die();
		}

		// Increase script timeout for bulk operations.
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 ); // 5 minutes.
		}

		// Check if process has been stopped before doing anything.
		if ( get_option( 'altseo_bulk_alt_stopped' ) ) {
			delete_option( 'altseo_bulk_alt_stopped' );
			delete_option( 'altseo_bulk_alt_post_list' );
			delete_option( 'altseo_bulk_alt_post_list_total' );
			delete_option( 'altseo_bulk_alt_lock' ); // Clear lock.
			wp_send_json_success(
				array(
					'percentage' => -1,
					'message'    => 'Process was stopped',
				)
			);
			wp_die();
		}

		// Check for existing lock to prevent concurrent processing.
		$lock_time = get_option( 'altseo_bulk_alt_lock' );
		if ( $lock_time && ( time() - $lock_time ) < 120 ) { // 2 minute lock timeout.
			wp_send_json_error(
				array(
					'percentage' => 0,
					'message'    => 'Another process is already running',
				)
			);
			wp_die();
		}

		// Set lock.
		update_option( 'altseo_bulk_alt_lock', time() );

		try {
			if ( ! get_option( 'altseo_bulk_alt_post_list' ) ) {
				$all_post_ids = get_posts(
					array(
						'fields'         => 'ids', // Only get post ID.
						'posts_per_page' => -1,
						'post_type'      => apply_filters( 'altseo_ai_plus_post_types', array( 'post', 'page' ) ),
						'orderby'        => 'ID',
						'order'          => 'ASC', // Process in consistent order.
						'post_status'    => 'publish',
					)
				);

				// Log all posts that will be processed.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'AltSEO AI+: Alt generation will process ' . count( $all_post_ids ) . ' posts: ' . implode( ', ', $all_post_ids ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}

				update_option( 'altseo_bulk_alt_post_list', $all_post_ids );
				update_option( 'altseo_bulk_alt_post_list_total', count( $all_post_ids ) );
			}

			// Check again if process has been stopped during initialization.
			if ( get_option( 'altseo_bulk_alt_stopped' ) ) {
				delete_option( 'altseo_bulk_alt_stopped' );
				delete_option( 'altseo_bulk_alt_post_list' );
				delete_option( 'altseo_bulk_alt_post_list_total' );
				delete_option( 'altseo_bulk_alt_lock' ); // Clear lock.
				wp_send_json_success(
					array(
						'percentage' => -1,
						'message'    => 'Process was stopped',
					)
				);
				wp_die();
			}

			$post_ids    = get_option( 'altseo_bulk_alt_post_list' );
			$total_posts = get_option( 'altseo_bulk_alt_post_list_total' );

			if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
				// No more posts to process or invalid data.
				delete_option( 'altseo_bulk_alt_post_list' );
				delete_option( 'altseo_bulk_alt_post_list_total' );
				delete_option( 'altseo_bulk_alt_lock' ); // Clear lock.
				wp_send_json_success(
					array(
						'percentage' => 100,
						'message'    => 'Processing complete',
					)
				);
				wp_die();
			}

			$current_post_id = array_shift( $post_ids );
			$remaining_posts = count( $post_ids );
			$processed_posts = $total_posts - $remaining_posts - 1; // -1 for the current post being processed.
			$percentage      = floor( ( $processed_posts / $total_posts ) * 100 );

			// Log progress details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "AltSEO AI+: Alt generation progress - Processing post $current_post_id ($processed_posts/$total_posts = $percentage%). Remaining: " . implode( ', ', array_slice( $post_ids, 0, 5 ) ) . ( $remaining_posts > 5 ? '...' : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// Update the list atomically.
			update_option( 'altseo_bulk_alt_post_list', $post_ids );

			// Process the current post.
			$altseo_api = new AltSEO_AI_Plus_API();
			$altseo_api->generate_alt( $current_post_id );

			// Clear lock before responding.
			delete_option( 'altseo_bulk_alt_lock' );

			// Check if we're done.
			if ( ! count( $post_ids ) ) { // 100% done.
				delete_option( 'altseo_bulk_alt_post_list' );
				delete_option( 'altseo_bulk_alt_post_list_total' );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'AltSEO AI+: Alt generation completed successfully for all posts' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				wp_send_json_success(
					array(
						'percentage' => 100,
						'message'    => 'All posts processed successfully',
					)
				);
			} else {
				wp_send_json_success(
					array(
						'percentage' => $percentage,
						'message'    => "Processing post $current_post_id... ($processed_posts of $total_posts)",
					)
				);
			}
		} catch ( Exception $e ) {
			// Clear lock on error.
			delete_option( 'altseo_bulk_alt_lock' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'AltSEO AI+: Bulk alt generation error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			wp_send_json_error(
				array(
					'message' => 'Error processing: ' . $e->getMessage(),
				)
			);
		}

		wp_die(); // this is required to terminate immediately and return a proper response.
	}

	/**
	 * AJAX handler to refresh available models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_refresh_models() {
		// Verify nonce for security.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'altseo_admin_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$api    = new AltSEO_AI_Plus_API();
		$models = $api->fetch_available_models();

		if ( empty( $models ) ) {
			wp_send_json_error( array( 'message' => 'Failed to fetch models or no models available' ) );
		}

		// Save models to option.
		update_option( 'altseo_available_models', $models );
		wp_send_json_success(
			array(
				'models'  => $models,
				'message' => 'Models refreshed successfully!',
			)
		);
	}

	/**
	 * AJAX handler to refresh Vision models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_refresh_vision_models() {
		// Verify nonce for security.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'altseo_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
			return;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			return;
		}

		$api    = new AltSEO_AI_Plus_API();
		$models = $api->fetch_available_vision_models();

		if ( empty( $models ) ) {
			wp_send_json_error( array( 'message' => 'No vision models found or API error' ) );
			return;
		}

		// Save vision models to option.
		update_option( 'altseo_available_vision_models', $models );
		wp_send_json_success(
			array(
				'models'  => $models,
				'message' => 'Vision models refreshed successfully!',
			)
		);
	}

	/**
	 * Handle AJAX request to save plugin settings
	 *
	 * @since 1.0.1
	 * @return void
	 */
	public function ajax_save_settings() {
		// Verify nonce for security.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'altseo_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid security token' ) );
			wp_die();
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
			wp_die();
		}

		try {
			// Save the settings with proper sanitization and validation.
			if ( isset( $_POST['altseo_ai_key'] ) ) {
				// Get and clean the API key thoroughly.
				$api_key = sanitize_text_field( wp_unslash( $_POST['altseo_ai_key'] ) );
				$api_key = trim( $api_key );

				// Log information for debugging.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'AltSEO AI+: Processing API key save - Key length: ' . strlen( $api_key ) .
							', First chars: ' . ( strlen( $api_key ) >= 3 ? substr( $api_key, 0, 3 ) : 'N/A' )
					);
				}

				// Create API instance for advanced debugging.
				$temp_api = new AltSEO_AI_Plus_API();
				$temp_api->debug_api_key( $api_key );

				// If key is empty, clear it.
				if ( '' === $api_key ) {
					update_option( 'altseo_ai_key', '' );
					// No need to validate an empty key.
				} else {
					// Create API instance for validation.
					$api = new AltSEO_AI_Plus_API();

					// Set a temporary API key for validation.
					$api->set_api_key( $api_key );

					// Verify the API key.
					$verification = $api->verify_api_key();

					if ( $verification['success'] ) {
						update_option( 'altseo_ai_key', $api_key );
					} else {
						// Return validation error message.
						wp_send_json_error( array( 'message' => 'API Key Error: ' . $verification['message'] ) );
						wp_die();
					}
				}
			}

			if ( isset( $_POST['altseo_ai_model'] ) ) {
				$model = sanitize_text_field( wp_unslash( $_POST['altseo_ai_model'] ) );
				// Validate model from available models.
				$available_models = get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
				if ( in_array( $model, $available_models, true ) ) {
					update_option( 'altseo_ai_model', $model );
				}
			}

			if ( isset( $_POST['altseo_vision_ai_model'] ) ) {
				$vision_model = sanitize_text_field( wp_unslash( $_POST['altseo_vision_ai_model'] ) );
				// Validate vision model from available models.
				$available_vision_models = get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );
				if ( in_array( $vision_model, $available_vision_models, true ) ) {
					update_option( 'altseo_vision_ai_model', $vision_model );
				}
			}

			if ( isset( $_POST['altseo_global_keywords'] ) ) {
				$keywords = sanitize_text_field( wp_unslash( $_POST['altseo_global_keywords'] ) );
				// Limit keywords to reasonable length.
				if ( strlen( $keywords ) <= 500 ) {
					update_option( 'altseo_global_keywords', $keywords );
				}
			}

			if ( isset( $_POST['altseo_enabled'] ) ) {
				$enabled_value = ( 'yes' === $_POST['altseo_enabled'] || true === $_POST['altseo_enabled'] || '1' === $_POST['altseo_enabled'] || 1 === $_POST['altseo_enabled'] ) ? '1' : '0';
				update_option( 'altseo_enabled', $enabled_value );
			} else {
				update_option( 'altseo_enabled', '0' );
			}

			if ( isset( $_POST['altseo_keyword_num'] ) ) {
				$keyword_num = intval( $_POST['altseo_keyword_num'] );
				// Ensure valid range (1-10).
				if ( $keyword_num >= 1 && $keyword_num <= 10 ) {
					update_option( 'altseo_keyword_num', $keyword_num );
				} else {
					update_option( 'altseo_keyword_num', 1 ); // Default to 1 if out of range.
				}
			}

			if ( isset( $_POST['altseo_seo_keywords_count'] ) ) {
				$seo_keywords_count = intval( $_POST['altseo_seo_keywords_count'] );
				// Ensure valid range (1-10).
				if ( $seo_keywords_count >= 1 && $seo_keywords_count <= 10 ) {
					update_option( 'altseo_seo_keywords_count', $seo_keywords_count );
				} else {
					update_option( 'altseo_seo_keywords_count', 1 ); // Default to 1 if out of range.
				}
			}

			wp_send_json_success( array( 'message' => 'Settings saved successfully!' ) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => 'Error saving settings: ' . $e->getMessage() ) );
		}

		wp_die();
	}

	/**
	 * AJAX handler for getting image alt text for frontend
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_image_alt() {
		// Verify nonce for security.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'altseo_frontend_nonce' ) ) {
			wp_send_json_error( 'Invalid security token' );
			return;
		}

		// Get the image ID.
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		if ( ! $image_id ) {
			wp_send_json_error( 'Invalid image ID' );
			return;
		}

		// Get the alt text.
		$alt_text = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		// If empty, try to generate it.
		if ( empty( $alt_text ) ) {
			try {
				$altseo_api = new AltSEO_AI_Plus_API();

				// Try to generate alt text for this image.
				$attachment_url = wp_get_attachment_url( $image_id );
				if ( $attachment_url ) {
					// Get keywords from the parent post, if possible.
					$parent_post_id = get_post_field( 'post_parent', $image_id );
					$keywords       = '';

					if ( $parent_post_id ) {
						$keywords = get_post_meta( $parent_post_id, 'altseo_keywords_tag', true );
					}

					// Generate alt text.
					$alt_text = $altseo_api->generate_image_alt( $attachment_url, $keywords, $parent_post_id );

					// Save the generated alt text.
					if ( ! empty( $alt_text ) ) {
						update_post_meta( $image_id, '_wp_attachment_image_alt', $alt_text );
					}
				}
			} catch ( Exception $e ) {
				// Log the error but continue.
				if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( 'AltSEO AI+: Error generating alt text for image ID ' . $image_id . ': ' . $e->getMessage() );
				}
			}
		}

		// Return the alt text (empty or generated).
		wp_send_json_success( $alt_text );
	}
}
