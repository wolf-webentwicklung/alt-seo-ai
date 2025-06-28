/**
 * AltSEO AI+ jQuery functionality for tag generation.
 *
 * @package AltSEO_AI_Plus
 */

jQuery( document ).ready(
	function () {
		jQuery( '#altseo_global_keywords' ).tagsInput(
			{
				// Setup the tags input keyword field.
				'height': '100px',
				'width': '400px',
			}
		);

		jQuery( "button#generate_keyword_btn" ).click(
			function () {
				if ( confirm( "Are you sure to generate keywords now?" ) ) {
					// User confirms to run this action now.
					jQuery( "div.progress_section" ).show();
					jQuery( "div.progress_bar" ).css( "background", "linear-gradient(to right, #2271b1 0%, #f0f0f1 0%)" );
					jQuery( "div.progress_bar" ).attr( "data-percentage", "0%" );

					function ajax_bulk_altseo_keyword_gen() {
						jQuery.ajax(
							{
								type: "post",
								url: ajaxurl,
								data: {								action: "ajax_bulk_generate_keyword",
								},
								success: function ( response ) {
									// console.log(response).
									jQuery( "div.progress_bar" ).css( "background", "linear-gradient(to right, #2271b1 " + response + "%, #f0f0f1 " + response + "%)" );
									jQuery( "div.progress_bar" ).attr( "data-percentage", response + "%" );
									jQuery( "div.progress_report" ).text( response + "% complete...." );
									if ( response != 100 ) {
										ajax_bulk_altseo_keyword_gen();
									} else {
										// Completion handling.
										jQuery( "div.progress_report" ).text( "100% complete - Keywords generated successfully!" );
										setTimeout(
											function () {
												jQuery( "div.progress_section" ).fadeOut( 500 );
												jQuery( "div.progress_bar" ).removeAttr( "data-percentage" );
											},
											3000
										);
									}
								},
								error: function ( response ) {
									console.log( 'ajax-error' );
								}
							}
						); // End of ajax.
					}

					ajax_bulk_altseo_keyword_gen();
				} // End of confirm box.
			}
		);

		jQuery( "button#generate_alt_btn" ).click(
			function () {
				if ( confirm( "Are you sure to generate alt tags now?" ) ) {
					// User confirms to run this action now.
					jQuery( "div.progress_section2" ).show();
					jQuery( "div.progress_bar2" ).css( "background", "linear-gradient(to right, #2271b1 0%, #f0f0f1 0%)" );
					jQuery( "div.progress_bar2" ).attr( "data-percentage", "0%" );

					function ajax_bulk_altseo_alt_gen() {
						jQuery.ajax(
							{
								type: "post",
								url: ajaxurl,
								data: {								action: "ajax_bulk_generate_alt",
								},
								success: function ( response ) {
									// console.log(response).
									jQuery( "div.progress_bar2" ).css( "background", "linear-gradient(to right, #2271b1 " + response + "%, #f0f0f1 " + response + "%)" );
									jQuery( "div.progress_bar2" ).attr( "data-percentage", response + "%" );
									jQuery( "div.progress_report2" ).text( response + "% complete...." );
									if ( response != 100 ) {
										ajax_bulk_altseo_alt_gen();
									} else {
										// Completion handling.
										jQuery( "div.progress_report2" ).text( "100% complete - Alt tags generated successfully!" );
										setTimeout(
											function () {
												jQuery( "div.progress_section2" ).fadeOut( 500 );
												jQuery( "div.progress_bar2" ).removeAttr( "data-percentage" );
											},
											3000
										);
									}
								},
								error: function ( response ) {
									console.log( 'ajax-error' );
								}
							}
						); // End of ajax.
					}

					ajax_bulk_altseo_alt_gen();
				} // End of confirm box.
			}
		);

		// UI Switch functionality.
		jQuery( "input#altseo_enabled" ).change(
			function () {
				var isChecked = jQuery( this ).is( ':checked' );
				var label     = jQuery( this ).closest( '.ui-switch-container' ).find( '.ui-switch-label' );
				label.text( isChecked ? 'Enabled' : 'Disabled' );
			}
		);
	}
);
