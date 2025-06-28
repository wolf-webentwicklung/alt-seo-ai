/**
 * AltSEO AI+ Fallback Detector
 * Detects when Vue.js fails to load and shows appropriate error message
 *
 * @package AltSEO_AI_Plus
 */

(function () {
	'use strict';

	var checkCount       = 0;
	var maxChecks        = 3;
	var hasVueAppMounted = false;

	// Listen for our custom Vue app mounted event.
	window.addEventListener(
		'altseo-vue-mounted',
		function () {
			hasVueAppMounted = true;
			console.log( 'AltSEO AI+: Vue app successfully mounted' );
		}
	);

	// Wait for DOM to be ready.
	document.addEventListener(
		'DOMContentLoaded',
		function () {
			// Wait for styles to load before checking.
			setTimeout(
				function () {
					checkVueLoading();
				},
				5000
			); // Wait 5 seconds for Vue and styles to load.

			// Final check after a longer delay.
			setTimeout(
				function () {
					checkVueLoading();
				},
				10000
			); // Final check after 10 seconds.
		}
	);

	function checkVueLoading() {
		checkCount++;

		// If we already detected Vue mounted, don't show error.
		if (hasVueAppMounted) {
			return;
		}

		// Check if Vue is available and our app has mounted.
		var appElement     = document.getElementById( 'altseo-app' );
		var loadingElement = document.getElementById( 'loading-fallback' );
		var errorElement   = document.getElementById( 'connection-error' );

		if ( ! appElement) {
			return; // Not on the right page.
		}

		// Skip check if error is already displayed.
		if (errorElement && errorElement.style.display === 'block') {
			return;
		}

		// Check if Vue loaded and our app initialized.
		var vueLoaded  = (typeof Vue !== 'undefined');
		var appMounted = false;

		if (vueLoaded && appElement) {
			// Check if our Vue app actually mounted by looking for Vue-rendered content.
			var vueElements = appElement.querySelectorAll( 'form' ) ||
							appElement.querySelectorAll( 'input[type="text"]' ) ||
							appElement.querySelectorAll( 'select' ) ||
							appElement.querySelectorAll( 'button' );

			// More specific check - look for the settings form or any Vue-generated content.
			var hasVueContent = appElement.innerHTML.includes( 'OpenAI Key' ) ||
								appElement.innerHTML.includes( 'altseo' ) ||
								vueElements.length > 5; // Vue app should have multiple form elements.

			appMounted = hasVueContent;

			// Also check if the loading element is still visible after Vue should have loaded.
			if (loadingElement && loadingElement.style.display !== 'none' && ! hasVueContent) {
				appMounted = false;
			}
		}

		// Only show error if we've reached max checks and Vue still hasn't mounted.
		if (( ! vueLoaded || ! appMounted) && checkCount >= maxChecks) {
			showConnectionError();
		} else if (checkCount < maxChecks && ( ! vueLoaded || ! appMounted)) {
			// Schedule another check.
			setTimeout(
				function () {
					checkVueLoading();
				},
				2000
			);
		}
	}

	function showConnectionError() {
		var loadingElement = document.getElementById( 'loading-fallback' );
		var errorElement   = document.getElementById( 'connection-error' );

		if (loadingElement) {
			loadingElement.style.display = 'none';
		}

		if (errorElement) {
			errorElement.style.display = 'block';

			// Add some animation.
			errorElement.style.opacity    = '0';
			errorElement.style.transition = 'opacity 0.5s ease-in';
			setTimeout(
				function () {
					errorElement.style.opacity = '1';
				},
				100
			);
		}

		// Log error for debugging.
		console.warn( 'AltSEO AI+: Vue.js failed to load. This may be due to internet connectivity issues.' );

		// Try to detect the specific issue.
		detectConnectivityIssue();
	}

	function detectConnectivityIssue() {
		// Test if we can reach external CDN.
		var testImage     = new Image();
		testImage.onload  = function () {
			console.log( 'AltSEO AI+: Internet connection appears to be working. Vue.js CDN may be blocked or unavailable.' );
		};
		testImage.onerror = function () {
			console.log( 'AltSEO AI+: Cannot reach external resources. Please check internet connection.' );
		};
		testImage.src     = 'https://unpkg.com/vue@3/dist/vue.global.prod.js?' + Date.now();
	}

	// Listen for script load errors.
	window.addEventListener(
		'error',
		function (e) {
			if (e.target && e.target.src && e.target.src.includes( 'vue' )) {
				console.error( 'AltSEO AI+: Failed to load Vue.js from CDN' );
				setTimeout( showConnectionError, 500 );
			}
		}
	);

})();
