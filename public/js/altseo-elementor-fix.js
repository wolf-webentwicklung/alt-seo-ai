/**
 * AltSEO AI+ Elementor Compatibility Script.
 *
 * Fixes dynamic alt text attributes in Elementor.
 *
 * @package AltSEO_AI_Plus
 */

(function () {
	'use strict';

	// Function to fix dynamic alt attributes.
	function fixElementorDynamicAlts() {
		// Find all images with empty alt attributes.
		var images = document.querySelectorAll( 'img[alt=""]' );

		images.forEach(
			function (img) {
				// Try to get ID from classes.
				var classes = img.className.split( ' ' );
				var imageId = null;

				// Look for wp-image-{ID} class.
				classes.forEach(
					function (cls) {
						if (cls.indexOf( 'wp-image-' ) === 0) {
							imageId = cls.replace( 'wp-image-', '' );
						}
					}
				);

				// Look for data-id attribute.
				if ( ! imageId && img.dataset && img.dataset.id) {
					imageId = img.dataset.id;
				}

				// Look for special elementor attributes.
				if ( ! imageId && img.dataset && img.dataset.settings) {
					try {
						var settings = JSON.parse( img.dataset.settings );
						if (settings.image && settings.image.id) {
							imageId = settings.image.id;
						}
					} catch (e) {
						console.error( 'Error parsing Elementor image settings:', e );
					}
				}

				// Try to extract ID from the source URL.
				if ( ! imageId && img.src) {
					// Check if URL contains -\d+ before file extension.
					var match = img.src.match( /-(\d+)\.(jpe?g|png|gif|svg|webp)/i );
					if (match && match[1]) {
						// This might be the attachment ID in the filename.
						imageId = match[1];
					}
				}

				if (imageId) {
					// Store the image element reference for access in the XHR callback.
					var imgElement = img;

					// Make AJAX call to get the alt text.
					var xhr = new XMLHttpRequest();
					xhr.open( 'POST', altSeoElementorData.ajaxUrl, true );
					xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
					xhr.onreadystatechange = function () {
						if (xhr.readyState === 4) {
							if (xhr.status === 200) {
								try {
									var response = JSON.parse( xhr.responseText );
									if (response.success && response.data) {
										// Set the alt attribute.
										imgElement.alt = response.data;
										// Also update the aria-label if it exists.
										if (imgElement.hasAttribute( 'aria-label' )) {
											imgElement.setAttribute( 'aria-label', response.data );
										}
									}
								} catch (e) {
									console.error( 'Error parsing AltSEO response:', e );
								}
							} else {
								console.error( 'AltSEO request failed with status:', xhr.status );
							}
						}
					};
					xhr.send( 'action=altseo_get_image_alt&image_id=' + imageId + '&nonce=' + altSeoElementorData.nonce );
				}
			}
		);
	}

	// Run once on page load.
	document.addEventListener(
		'DOMContentLoaded',
		function () {
			// Wait a bit to ensure Elementor has rendered everything.
			setTimeout( fixElementorDynamicAlts, 500 );

			// Add mutation observer to handle dynamically added content.
			var observer = new MutationObserver(
				function (mutations) {
					var shouldFix = false;

					mutations.forEach(
						function (mutation) {
							if (mutation.addedNodes && mutation.addedNodes.length > 0) {
								// Check if any of the added nodes might contain images.
								var addedNodesLength = mutation.addedNodes.length;
								for (var i = 0; i < addedNodesLength; i++) {
									var node = mutation.addedNodes[i];
									if (node.nodeType === 1) { // Element node.
										if (node.tagName === 'IMG' || node.querySelector( 'img' )) {
											shouldFix = true;
											break;
										}
									}
								}
							}
						}
					);

					if (shouldFix) {
						setTimeout( fixElementorDynamicAlts, 500 );
					}
				}
			);

			// Start observing the entire document.
			observer.observe(
				document.body,
				{
					childList: true,
					subtree: true
				}
			);

			// Also listen for Elementor frontend events if Elementor is available.
			if (window.elementorFrontend) {
				// Run after Elementor frontend init.
				if (elementorFrontend.hooks) {
					elementorFrontend.hooks.addAction(
						'frontend/element_ready/global',
						function () {
							setTimeout( fixElementorDynamicAlts, 500 );
						}
					);
				}
			}
		}
	);
})();
