/*!
 * AltSEO AI+ Public JavaScript
 * Handles frontend functionality for AltSEO AI+
 */

(function($) {
    'use strict';

    /**
     * Fix any remaining images with empty alt attributes
     */
    function fixEmptyAltAttributes() {
        // Find all images with empty alt attributes
        var emptyAltImages = document.querySelectorAll('img[alt=""]');
        
        if (emptyAltImages.length === 0) {
            return;
        }
        
        emptyAltImages.forEach(function(img) {
            // Try to find image ID from various sources
            var imageId = null;
            
            // Check for wp-image class
            var classes = img.className.split(' ');
            for (var i = 0; i < classes.length; i++) {
                if (classes[i].indexOf('wp-image-') === 0) {
                    imageId = classes[i].replace('wp-image-', '');
                    break;
                }
            }
            
            // Check data attributes
            if (!imageId && img.dataset && img.dataset.id) {
                imageId = img.dataset.id;
            }
            
            // Process if we found an image ID
            if (imageId) {
                // Make AJAX call to get alt text
                $.ajax({
                    url: altSeoPublicData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'altseo_get_image_alt',
                        image_id: imageId,
                        nonce: altSeoPublicData.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Apply alt text to the image
                            img.alt = response.data;
                            // Also update aria-label if present
                            if (img.hasAttribute('aria-label')) {
                                img.setAttribute('aria-label', response.data);
                            }
                        }
                    },
                    error: function(xhr, textStatus, error) {
                        console.error('AltSEO: Error retrieving alt text', error);
                    }
                });
            }
        });
    }

    // Execute when DOM is fully loaded
    $(document).ready(function() {
        // Run initial fix after a short delay to ensure all content is rendered
        setTimeout(fixEmptyAltAttributes, 1000);
        
        // Watch for DOM changes to handle dynamically added images
        if (window.MutationObserver) {
            var observer = new MutationObserver(function(mutations) {
                var shouldCheck = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                        // Check if any of the added nodes are images or contain images
                        for (var i = 0; i < mutation.addedNodes.length; i++) {
                            var node = mutation.addedNodes[i];
                            if (node.nodeType === 1) { // Element node
                                if (node.tagName === 'IMG' || node.querySelector('img')) {
                                    shouldCheck = true;
                                    break;
                                }
                            }
                        }
                    }
                });
                
                if (shouldCheck) {
                    // Delay a bit to allow for any other DOM changes
                    setTimeout(fixEmptyAltAttributes, 500);
                }
            });
            
            // Start observing the document body for changes
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });

})(jQuery);
