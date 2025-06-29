<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @since      1.0.0
 * @package    AltSEO_AI
 * @subpackage AltSEO_AI/admin
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
 * @package    AltSEO_AI
 * @subpackage AltSEO_AI/admin
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */
class AltSEO_AI_Admin {

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
		add_action( 'wp_ajax_altseo_refresh_models', array( $this, 'ajax_refresh_models' ) );
		add_action( 'wp_ajax_altseo_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_altseo_refresh_vision_models', array( $this, 'ajax_refresh_vision_models' ) );
		add_action( 'wp_ajax_altseo_get_image_alt', array( $this, 'ajax_get_image_alt' ) );
		add_action( 'wp_ajax_nopriv_altseo_get_image_alt', array( $this, 'ajax_get_image_alt' ) );
	}

	/**
	 * Register the plugin settings page.
	 */
	public function plugin_settings_page() {
		global $altseo_ai_custom_menu;
		$altseo_ai_custom_menu = add_submenu_page(
			'upload.php',                                  // Parent menu slug (Media).
			__( 'AltSeo-AI Settings', 'altseo-ai' ), // Page title.
			__( 'AltSeo-AI', 'altseo-ai' ),     // Menu title.
			'manage_options',                              // Capability.
			'altseo-ai-settings',                     // Menu slug.
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
		global $altseo_ai_custom_menu;
		$allowed = array( $altseo_ai_custom_menu, 'post-new.php', 'post.php' );
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}
		// Load Vue.js app only on the plugin's settings page.
		if ( $altseo_ai_custom_menu === $hook ) {
			// Enqueue Vue.js from CDN for production - load in head to prevent FOUC.
			wp_enqueue_script( 'vue-js', 'https://unpkg.com/vue@3/dist/vue.global.prod.js', array(), '3.3.4', false );

			// Enqueue our compiled Vue app - load in footer after Vue.
			wp_enqueue_style( 'altseo-vue-styles', plugin_dir_url( __FILE__ ) . 'assets/dist/bundle.css', array(), filemtime( plugin_dir_path( __FILE__ ) . 'assets/dist/bundle.css' ) );
			wp_enqueue_script( 'altseo-vue-app', plugin_dir_url( __FILE__ ) . 'assets/dist/bundle.js', array( 'vue-js' ), filemtime( plugin_dir_path( __FILE__ ) . 'assets/dist/bundle.js' ), true );

			// Add fallback detection script - load in footer.
			wp_enqueue_script( 'altseo-fallback-detector', plugin_dir_url( __FILE__ ) . 'assets/js/fallback-detector.js', array(), '1.0.1', true );

			// Pass data to the Vue app.
			// Ensure models are always arrays before applying array_map.
			$available_models = get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
			if ( ! is_array( $available_models ) ) {
				$available_models = array( $available_models );
			}

			$available_vision_models = get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );
			if ( ! is_array( $available_vision_models ) ) {
				$available_vision_models = array( $available_vision_models );
			}

			wp_localize_script(
				'altseo-vue-app',
				'altSeoData',
				array(
					'apiKey'              => wp_kses_post( get_option( 'altseo_ai_key' ) ),
					'models'              => array_map( 'esc_html', $available_models ),
					'selectedModel'       => esc_html( get_option( 'altseo_ai_model', 'gpt-3.5-turbo' ) ),
					'visionModels'        => array_map( 'esc_html', $available_vision_models ),
					'selectedVisionModel' => esc_html( get_option( 'altseo_vision_ai_model', 'gpt-4o-mini' ) ),
					'enabled'             => get_option( 'altseo_enabled' ) === '1' || get_option( 'altseo_enabled' ) === 1 || get_option( 'altseo_enabled' ) === true,
					'keywordNum'          => intval( get_option( 'altseo_keyword_num', 1 ) ),
					'ajaxUrl'             => esc_url( admin_url( 'admin-ajax.php' ) ),
					'nonce'               => wp_create_nonce( 'altseo_admin_nonce' ),
					'refreshModelsNonce'  => wp_create_nonce( 'altseo_admin_nonce' ),
					'fallbackUrl'         => esc_url( admin_url( 'admin.php?page=altseo-ai-settings&use_legacy=1' ) ),
					'pluginUrl'           => esc_url( plugin_dir_url( __FILE__ ) ),
				)
			);
		}

		// Load legacy scripts for post editor pages.
		wp_enqueue_style( 'altseo_tag_input_css', plugin_dir_url( __FILE__ ) . 'assets/css/jquery.tagsinput.min.css', array(), '1.0.0' );
		wp_enqueue_script( 'altseo_tag_input_js', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.tagsinput.min.js', array(), '1.0.0', true );
		wp_enqueue_style( 'altseo_ai_alt_style', plugin_dir_url( __FILE__ ) . 'assets/css/altseo-ai-tag-style.css', array(), '1.0.0' );
		wp_enqueue_script( 'altseo_ai_alt_js', plugin_dir_url( __FILE__ ) . 'assets/js/altseo-ai-tag-js.js', array(), '1.0.0', true );

		// Load the model refresh for legacy form.
		if ( $altseo_ai_custom_menu === $hook ) {
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
		$use_vue = apply_filters( 'altseo_ai_use_vue_interface', true );

		// Force legacy mode ONLY for this request if specifically requested (fallback).
		if ( isset( $_GET['use_legacy'] ) && '1' === $_GET['use_legacy'] ) {
			$use_vue = false;
		}

		if ( $use_vue ) {
			// Vue.js App Container.
			?>
			<div id="alt-seo-ai-settings-wrap" class="wrap">
				<div id="altseo-app">
					<div id="loading-fallback" style="text-align: center; padding: 50px; min-height: 400px; display: flex; align-items: center; justify-content: center; flex-direction: column;">
						<span class="spinner is-active" style="float: none; margin: 0 0 15px 0; visibility: visible;"></span>
						<p style="margin: 0; color: #666;">Loading AltSEO AI Settings...</p>
					</div>
					<div id="connection-error" style="display: none; text-align: center; padding: 50px; min-height: 400px;">
					</div>
				</div>
			</div>
			<?php
			return;
		}
			// Legacy PHP Form (fallback).
		?>
		<div id="alt-seo-ai-settings-wrap" class="wrap">
		<h2>Alt Seo AI Settings</h2>
		
		<?php if ( isset( $_GET['use_legacy'] ) && '1' === $_GET['use_legacy'] ) : ?>
		<div class="notice notice-info is-dismissible" style="margin: 20px 0;">
		</div>
		<?php endif; ?>
		
		<?php
			$msg = '';
		if ( isset( $_POST['altseo_global_keywords'] ) || isset( $_POST['altseo_ai_key'] ) || isset( $_POST['submit'] ) ) {
			$msg = '<span style="font-weight:bold;margin-left:20px;"> &#10004; ' . esc_html__( 'Saved Successfully!', 'altseo-ai' ) . '</span>';

			// Verify nonce.
			if ( ! isset( $_POST['altseo_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['altseo_admin_nonce'] ) ), 'altseo_admin_nonce' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'altseo-ai' ) );
			}

			// Handle global keywords.
			if ( isset( $_POST['altseo_global_keywords'] ) ) {
				$global_keywords = sanitize_text_field( wp_unslash( $_POST['altseo_global_keywords'] ) );
				update_option( 'altseo_global_keywords', $global_keywords );
			}

			// Handle API key.
			if ( isset( $_POST['altseo_ai_key'] ) ) {
				$api_key = sanitize_text_field( wp_unslash( $_POST['altseo_ai_key'] ) );
				update_option( 'altseo_ai_key', $api_key );
			}

			// Handle enabled option.
			$enabled = isset( $_POST['altseo_enabled'] ) ? 1 : 0;
			update_option( 'altseo_enabled', $enabled );

			// Handle keyword number.
			if ( isset( $_POST['altseo_keyword_num'] ) ) {
				$keyword_num = intval( $_POST['altseo_keyword_num'] );
				if ( $keyword_num >= 1 && $keyword_num <= 10 ) {
					update_option( 'altseo_keyword_num', $keyword_num );
				} else {
					update_option( 'altseo_keyword_num', 1 ); // Default to 1 if out of range.
				}
			}

			// Handle AI model selection.
			if ( isset( $_POST['altseo_ai_model'] ) ) {
				$ai_model = sanitize_text_field( wp_unslash( $_POST['altseo_ai_model'] ) );
				update_option( 'altseo_ai_model', $ai_model );
			}

			// Handle vision AI model selection.
			if ( isset( $_POST['altseo_vision_ai_model'] ) ) {
				$vision_ai_model = sanitize_text_field( wp_unslash( $_POST['altseo_vision_ai_model'] ) );
				update_option( 'altseo_vision_ai_model', $vision_ai_model );
			}
		}

		// Get current values.
		$altseo_enabled          = get_option( 'altseo_enabled', 1 );
		$altseo_ai_key           = get_option( 'altseo_ai_key', '' );
		$altseo_global_keywords  = get_option( 'altseo_global_keywords', '' );
		$altseo_keyword_num      = intval( get_option( 'altseo_keyword_num', 1 ) );
		$altseo_ai_model         = get_option( 'altseo_ai_model', 'gpt-3.5-turbo' );
		$altseo_vision_ai_model  = get_option( 'altseo_vision_ai_model', 'gpt-4o-mini' );
		$available_models        = get_option( 'altseo_available_models', array( 'gpt-3.5-turbo' ) );
		$available_vision_models = get_option( 'altseo_available_vision_models', array( 'gpt-4o-mini' ) );

		// Ensure models are always arrays.
		if ( ! is_array( $available_models ) ) {
			$available_models = array( $available_models );
		}

		if ( ! is_array( $available_vision_models ) ) {
			$available_vision_models = array( $available_vision_models );
		}
		?>

		<form method="post" action="" class="altseo-settings-form">
			<?php wp_nonce_field( 'altseo_admin_nonce', 'altseo_admin_nonce' ); ?>
			
			<div class="altseo-settings-section">
				<h3><?php esc_html_e( 'API Configuration', 'altseo-ai' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="altseo_ai_key"><?php esc_html_e( 'OpenAI API Key', 'altseo-ai' ); ?></label>
						</th>
						<td>
							<input type="password" id="altseo_ai_key" name="altseo_ai_key" value="<?php echo esc_attr( $altseo_ai_key ); ?>" size="45" class="regular-text" />
							<p class="description">
								<?php
								printf(
									/* translators: %s: OpenAI API URL */
									esc_html__( 'Enter your OpenAI API key. Get one from %s', 'altseo-ai' ),
									'<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="altseo_ai_model"><?php esc_html_e( 'AI Model', 'altseo-ai' ); ?></label>
						</th>
						<td>
							<select name="altseo_ai_model" id="altseo_ai_model">
								<?php foreach ( $available_models as $model ) : ?>
									<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $altseo_ai_model, $model ); ?>>
										<?php echo esc_html( $model ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="refresh_models_btn" class="button">
								<?php esc_html_e( 'Refresh Models', 'altseo-ai' ); ?>
							</button>
							<div id="model_refresh_message" style="display: none; margin-top: 5px;"></div>
							<p class="description">
								<?php esc_html_e( 'Select the AI model for keyword generation.', 'altseo-ai' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="altseo_vision_ai_model"><?php esc_html_e( 'Vision AI Model', 'altseo-ai' ); ?></label>
						</th>
						<td>
							<select name="altseo_vision_ai_model" id="altseo_vision_ai_model">
								<?php foreach ( $available_vision_models as $model ) : ?>
									<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $altseo_vision_ai_model, $model ); ?>>
										<?php echo esc_html( $model ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="refresh_vision_models_btn" class="button">
								<?php esc_html_e( 'Refresh Vision Models', 'altseo-ai' ); ?>
							</button>
							<div id="vision_model_refresh_message" style="display: none; margin-top: 5px;"></div>
							<p class="description">
								<?php esc_html_e( 'Select the AI model for image analysis and alt text generation.', 'altseo-ai' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="altseo-settings-section">
				<h3><?php esc_html_e( 'General Settings', 'altseo-ai' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Enable Plugin', 'altseo-ai' ); ?>
						</th>
						<td>
							<label for="altseo_enabled">
								<input type="checkbox" id="altseo_enabled" name="altseo_enabled" value="1" <?php checked( $altseo_enabled, 1 ); ?> />
								<?php esc_html_e( 'Activate AltSEO AI', 'altseo-ai' ); ?>
							</label>
							<p class="description">
								<span class="field-description">Automatically generate keywords and alt tags when posts are saved or updated</span>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="altseo_global_keywords"><?php esc_html_e( 'Global Keywords', 'altseo-ai' ); ?></label>
						</th>
						<td>
							<input type="text" id="altseo_global_keywords" name="altseo_global_keywords" value="<?php echo esc_attr( $altseo_global_keywords ); ?>" size="45" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Enter global keywords separated by commas. These will be used for all generated alt text.', 'altseo-ai' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="altseo_keyword_num"><?php esc_html_e( 'Number of Keywords', 'altseo-ai' ); ?></label>
						</th>
						<td>
							<select name="altseo_keyword_num" style="width:100px" id="altseo_keyword_num">
								<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
									<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $altseo_keyword_num, $i ); ?>><?php echo esc_html( $i ); ?></option>
								<?php endfor; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Number of keywords to generate per post (1-10).', 'altseo-ai' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<input type="submit" name="submit" id="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'altseo-ai' ); ?>" />
				<?php echo wp_kses_post( $msg ); ?>
			</p>
		</form>
		</div>
		<?php
	}

	/**
	 * AJAX handler for refreshing available models
	 *
	 * @since 1.0.0
	 */
	public function ajax_refresh_models() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'altseo_admin_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'altseo-ai' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'altseo-ai' ) );
		}

		$api_key = get_option( 'altseo_ai_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please set your OpenAI API key first.', 'altseo-ai' ) ) );
		}

		$altseo_api = new AltSEO_AI_API();
		$models     = $altseo_api->fetch_available_models();

		if ( ! empty( $models ) ) {
			update_option( 'altseo_available_models', $models );
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Models refreshed successfully!', 'altseo-ai' ),
					'models'  => $models,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to fetch models. Please check your API key.', 'altseo-ai' ) ) );
		}
	}

	/**
	 * AJAX handler for refreshing available vision models
	 *
	 * @since 1.0.0
	 */
	public function ajax_refresh_vision_models() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'altseo_admin_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'altseo-ai' ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'altseo-ai' ) );
		}

		$api_key = get_option( 'altseo_ai_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please set your OpenAI API key first.', 'altseo-ai' ) ) );
		}

		$altseo_api = new AltSEO_AI_API();
		$models     = $altseo_api->fetch_available_vision_models();

		if ( ! empty( $models ) ) {
			update_option( 'altseo_available_vision_models', $models );
			wp_send_json_success(
				array(
					'message' => esc_html__( 'Vision models refreshed successfully!', 'altseo-ai' ),
					'models'  => $models,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to fetch vision models. Please check your API key.', 'altseo-ai' ) ) );
		}
	}

	/**
	 * AJAX handler for saving settings from Vue app
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'altseo_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'altseo-ai' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'altseo-ai' ) ) );
		}

		try {
			// Handle API key.
			if ( isset( $_POST['altseo_ai_key'] ) ) {
				$api_key = sanitize_text_field( wp_unslash( $_POST['altseo_ai_key'] ) );
				update_option( 'altseo_ai_key', $api_key );
			}

			// Handle global keywords.
			if ( isset( $_POST['altseo_global_keywords'] ) ) {
				$global_keywords = sanitize_text_field( wp_unslash( $_POST['altseo_global_keywords'] ) );
				update_option( 'altseo_global_keywords', $global_keywords );
			}

			// Handle enabled option - always process this field.
			// Vue.js sends 'yes' when checked, empty string when unchecked.
			$enabled_value = isset( $_POST['altseo_enabled'] ) ? sanitize_text_field( wp_unslash( $_POST['altseo_enabled'] ) ) : '';
			$enabled       = ( 'true' === $enabled_value || 'yes' === $enabled_value || '1' === $enabled_value ) ? 1 : 0;

			update_option( 'altseo_enabled', $enabled );

			// Handle keyword number.
			if ( isset( $_POST['altseo_keyword_num'] ) ) {
				$keyword_num = intval( $_POST['altseo_keyword_num'] );
				if ( $keyword_num >= 1 && $keyword_num <= 10 ) {
					update_option( 'altseo_keyword_num', $keyword_num );
				} else {
					update_option( 'altseo_keyword_num', 1 ); // Default to 1 if out of range.
				}
			}

			// Handle AI model selection.
			if ( isset( $_POST['altseo_ai_model'] ) ) {
				$ai_model = sanitize_text_field( wp_unslash( $_POST['altseo_ai_model'] ) );
				update_option( 'altseo_ai_model', $ai_model );
			}

			// Handle vision AI model selection.
			if ( isset( $_POST['altseo_vision_ai_model'] ) ) {
				$vision_ai_model = sanitize_text_field( wp_unslash( $_POST['altseo_vision_ai_model'] ) );
				update_option( 'altseo_vision_ai_model', $vision_ai_model );
			}

			wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully!', 'altseo-ai' ) ) );

		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to save settings.', 'altseo-ai' ) ) );
		}
	}

	/**
	 * AJAX handler for getting image alt text
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_image_alt() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'altseo_admin_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'altseo-ai' ) ) );
		}

		// Check user permissions.
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'altseo-ai' ) ) );
		}

		// Check if plugin is enabled.
		if ( ! get_option( 'altseo_enabled' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'AltSEO AI is not enabled.', 'altseo-ai' ) ) );
		}

		// Check if we have an API key.
		$api_key = get_option( 'altseo_ai_key' );
		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please set your OpenAI API key first.', 'altseo-ai' ) ) );
		}

		// Get image URL and keywords from request.
		$image_url = isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '';
		$keywords  = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		$post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( empty( $image_url ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Image URL is required.', 'altseo-ai' ) ) );
		}

		try {
			// Generate keywords for the post if not provided and we have a post ID.
			if ( empty( $keywords ) && $post_id > 0 ) {
				// Try to get existing keywords for this post.
				$keywords = get_post_meta( $post_id, 'altseo_keywords_tag', true );
				if ( empty( $keywords ) ) {
					// Try to generate keywords for this post.
					$altseo_api = new AltSEO_AI_API();
					$keywords   = $altseo_api->generate_keywords( $post_id, true );
				}
			}

			// Try to generate alt text for this image.
			$altseo_api = new AltSEO_AI_API();
			if ( $post_id > 0 ) {
				// Generate alt text.
				$alt_text = $altseo_api->generate_image_alt( $image_url, $keywords, $post_id );
			} else {
				// Generate alt text without post context.
				$alt_text = $altseo_api->generate_image_alt( $image_url, $keywords );
			}

			if ( ! empty( $alt_text ) ) {
				wp_send_json_success(
					array(
						'alt_text' => $alt_text,
						'keywords' => $keywords,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Failed to generate alt text.', 'altseo-ai' ) ) );
			}
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while generating alt text.', 'altseo-ai' ) ) );
		}
	}
}
