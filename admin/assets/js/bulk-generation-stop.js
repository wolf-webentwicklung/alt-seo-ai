/**
 * AltSEO AI+ Bulk Generation Stop Feature
 *
 * @package AltSEO_AI_Plus
 */

document.addEventListener(
	'DOMContentLoaded',
	function () {
		// Wait for Vue app to initialize.
		setTimeout(
			function () {
				// Check if our app is loaded.
				const appElement = document.getElementById( 'altseo-app' );
				if ( ! appElement ) {
					return;
				}

				// Add our stop functionality to the Vue instance.
				if ( window.altSeoAppInstance ) {
					console.log( 'AltSEO AI+: Adding bulk generation stop functionality' );

					// Make sure the BulkTools component returns the stopBulkGeneration method.
					if ( window.altSeoAppInstance.$refs.bulkTools ) {
						const bulkToolsComponent = window.altSeoAppInstance.$refs.bulkTools;

						// Ensure the component has the stopBulkGeneration method.
						if ( ! bulkToolsComponent.stopBulkGeneration ) {
							console.log( 'AltSEO AI+: Adding missing stopBulkGeneration method' );

							// Define the stopBulkGeneration method if it doesn't exist.
							bulkToolsComponent.stopBulkGeneration = async function ( type ) {
								try {
									const progressObj = type === 'keywords' ?
										this.keywordProgress : this.altProgress;

									// Set frontend flag to stop the process loop.
									this.processStopped = true;

									// Send AJAX request to stop the generation process on the backend.
									const formData = new FormData();
									formData.append( 'action', 'altseo_stop_bulk_generation' );
									formData.append( 'nonce', window.altSeoData.nonce || '' );

									const response = await fetch(
										window.altSeoData.ajaxUrl || '/wp-admin/admin-ajax.php',
										{
											method: 'POST',
											body: formData
										}
									);

									if ( ! response.ok ) {
										throw new Error( `HTTP error ! status: ${response.status}` );
									}

									const result = await response.json();

									if ( result.success ) {
										// Update UI to reflect stopped process.
										progressObj.halted  = true;
										progressObj.message = '⚠️ Process halted by user';

										// Reset generation states.
										if ( type === 'keywords' ) {
											this.isGeneratingKeywords = false;
										} else {
											this.isGeneratingAlts = false;
										}

										// Hide progress bar after a delay and reset flags.
										setTimeout(
											() => {
												// phpcs:ignore WordPress.WhiteSpace.PrecisionAlignment.Found
												progressObj.show = false;
												// phpcs:ignore WordPress.WhiteSpace.PrecisionAlignment.Found
												progressObj.halted = false;
												// phpcs:ignore WordPress.WhiteSpace.PrecisionAlignment.Found
												this.processStopped = false; // Reset the stop flag for next run.
											},
											5000
										);
									} else {
										console.error( 'Failed to stop generation process:', result );
										this.processStopped = false; // Reset flag if stop failed.
									}
								} catch ( error ) {
									console.error( 'Error stopping generation process:', error );
									this.processStopped = false; // Reset flag on error.
								}
							};
						}
					}
				}
			},
			1000
		); // Wait for 1 second to ensure Vue has mounted.
	}
);
