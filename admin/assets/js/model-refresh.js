/**
 * AltSEO AI+ Model Refresh JavaScript
 *
 * Handles AJAX requests for refreshing OpenAI model lists
 *
 * @package AltSEO_AI_Plus
 * @since 1.0.1
 */

(function ($) {
	'use strict';

	$( document ).ready(
		function () {
			// Handle refresh models button click (keywords).
			$( '#refresh_models_btn' ).on(
				'click',
				function () {
					var $button      = $( this );
					var originalText = $button.text();
					var $messageDiv  = $( '#model_refresh_message' );

					// Validate that nonce exists.
					if ( ! altseoModels || ! altseoModels.nonce) {
						$messageDiv.text( 'Security error: Missing authentication token' ).addClass( 'error' ).show();
						return;
					}

					$button.text( 'Refreshing...' ).prop( 'disabled', true );
					$messageDiv.hide();

					$.ajax(
						{
							url: altseoModels.ajax_url,
							type: 'POST',
							data: {
								action: 'altseo_refresh_models',
								nonce: altseoModels.nonce
							},
							success: function (response) {
								if (response.success) {
									// Clear existing options.
									$( '#altseo_ai_model' ).empty();

									// Add new options - use safe DOM creation to prevent XSS.
									$.each(
										response.data.models,
										function (i, model) {
											var selected = (model === $( '#altseo_ai_model' ).data( 'selected' )) ? 'selected' : '';
											$( '#altseo_ai_model' ).append(
												$( '<option></option>' )
												.val( model )
												.text( model )
												.prop( 'selected', selected )
											);
										}
									);

									// Show success message using safe DOM manipulation.
									var $successMessage = $( '<span>' ).css( 'color', 'green' ).text( response.data.message );
									$( '#model_refresh_message' ).empty().append( $successMessage ).show().delay( 3000 ).fadeOut();
								} else {
									// Handle both string and object error format.
									var errorMessage = 'Unknown error';
									if (response.data) {
										if (typeof response.data === 'string') {
											errorMessage = response.data;
										} else if (response.data.message) {
											errorMessage = response.data.message;
										}
									}

									var $errorMessage = $( '<span>' ).css( 'color', 'red' ).text( 'Error: ' + errorMessage );
									$( '#model_refresh_message' ).empty().append( $errorMessage ).show().delay( 3000 ).fadeOut();
								}
							},
							error: function () {
								$( '#model_refresh_message' ).html( '<span style="color:red;">Network error while refreshing models</span>' ).show().delay( 3000 ).fadeOut();
							},
							complete: function () {
								$button.text( originalText ).prop( 'disabled', false );
							}
						}
					);
				}
			);

			// Handle refresh vision models button click.
			$( '#refresh_vision_models_btn' ).on(
				'click',
				function () {
					var $button      = $( this );
					var originalText = $button.text();

					$button.text( 'Refreshing...' ).prop( 'disabled', true );

					$.ajax(
						{
							url: altseoModels.ajax_url,
							type: 'POST',
							data: {
								action: 'altseo_refresh_vision_models',
								nonce: altseoModels.nonce
							},
							success: function (response) {
								if (response.success) {
									// Clear existing options.
									$( '#altseo_vision_ai_model' ).empty();

									// Add new options using safe DOM creation to prevent XSS.
									$.each(
										response.data.models,
										function (i, model) {
											var selected = (model === $( '#altseo_vision_ai_model' ).data( 'selected' )) ? 'selected' : '';
											$( '#altseo_vision_ai_model' ).append(
												$( '<option></option>' )
												.val( model )
												.text( model )
												.prop( 'selected', selected )
											);
										}
									);

									// Show success message using safe DOM manipulation.
									var $successMessage = $( '<span>' ).css( 'color', 'green' ).text( response.data.message );
									$( '#vision_model_refresh_message' ).empty().append( $successMessage ).show().delay( 3000 ).fadeOut();
								} else {
									// Handle both string and object error format.
									var errorMessage = 'Unknown error';
									if (response.data) {
										if (typeof response.data === 'string') {
											errorMessage = response.data;
										} else if (response.data.message) {
											errorMessage = response.data.message;
										}
									}

									var $errorMessage = $( '<span>' ).css( 'color', 'red' ).text( 'Error: ' + errorMessage );
									$( '#vision_model_refresh_message' ).empty().append( $errorMessage ).show().delay( 3000 ).fadeOut();
								}
							},
							error: function () {
								$( '#vision_model_refresh_message' ).html( '<span style="color:red;">Network error while refreshing vision models</span>' ).show().delay( 3000 ).fadeOut();
							},
							complete: function () {
								$button.text( originalText ).prop( 'disabled', false );
							}
						}
					);
				}
			);
		}
	);
})( jQuery );