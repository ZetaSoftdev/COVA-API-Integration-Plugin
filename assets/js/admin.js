/**
 * Admin JavaScript for Cova Integration plugin
 */
(function($) {
    'use strict';
    
    // Initialize when the DOM is ready
    $(document).ready(function() {
        // Check if WooCommerce exists
        var wooCommerceExists = typeof window.woocommerce !== 'undefined' || $('body').hasClass('woocommerce-page');
        
        // Process image button event handler
        $('.process-image').on('click', function() {
            var $button = $(this);
            var productId = $button.data('product-id');
            var imageUrl = $button.data('image-url');
            
            // Disable the button and show processing state
            $button.prop('disabled', true).text('Processing...');
            
            // Check if the image URL contains igmetrix.net which seems to cause issues
            var isIgmetrixUrl = imageUrl.indexOf('igmetrix.net') !== -1;
            if (isIgmetrixUrl) {
                $button.after('<div class="image-status">Detected igmetrix URL, using special handling...</div>');
            }
            
            // Create a retry function to allow for multiple attempts
            var retryCount = 0;
            var maxRetries = 2;
            
            function attemptImageDownload() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cova_process_single_image',
                        nonce: $('#cova_process_single_image_nonce').val() || window.cova_admin_nonces?.process_single_image || '',
                        product_id: productId,
                        image_url: imageUrl
                    },
                    success: function(response) {
                        if (response && response.success) {
                            // Show success message and update image display
                            $button.after('<div class="image-status success">Image processed successfully! Reloading...</div>');
                            
                            // Reload the current page to show the updated image
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            var errorMessage = (response && response.data) ? response.data : 'Unknown error';
                            
                            // If we haven't reached max retries, try again
                            if (retryCount < maxRetries) {
                                retryCount++;
                                $button.after('<div class="image-status warning">Error: ' + errorMessage + ' - Retrying (' + retryCount + '/' + maxRetries + ')...</div>');
                                
                                // Wait a bit before retrying
                                setTimeout(attemptImageDownload, 2000);
                            } else {
                                // Max retries reached, show error and re-enable button
                                $button.after('<div class="image-status error">Failed after ' + maxRetries + ' attempts: ' + errorMessage + '</div>');
                                $button.prop('disabled', false).text('Process Image');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '';
                        try {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            errorMessage = jsonResponse.message || error;
                        } catch(e) {
                            // Try to extract error from HTML response
                            var match = /<b>Fatal error<\/b>:(.+?)<br/.exec(xhr.responseText);
                            errorMessage = match ? match[1].trim() : 'Invalid response from server';
                        }
                        
                        // If we haven't reached max retries, try again
                        if (retryCount < maxRetries) {
                            retryCount++;
                            $button.after('<div class="image-status warning">AJAX error: ' + errorMessage + ' - Retrying (' + retryCount + '/' + maxRetries + ')...</div>');
                            
                            // Wait a bit before retrying
                            setTimeout(attemptImageDownload, 2000);
                        } else {
                            // Max retries reached, show error and re-enable button
                            $button.after('<div class="image-status error">Failed after ' + maxRetries + ' attempts: ' + errorMessage + '</div>');
                            $button.prop('disabled', false).text('Process Image');
                        }
                    }
                });
            }
            
            // Start the download process
            attemptImageDownload();
        });
        
        // Product table image processing buttons
        $(document).on('click', '.process-product-image', function() {
            var $button = $(this);
            var productId = $button.data('product-id');
            var imageUrl = $button.data('image-url');
            var $container = $button.closest('.missing-image');
            
            // Disable the button and show processing state
            $button.prop('disabled', true).text('Processing...');
            
            // Check if the image URL contains igmetrix.net which returns 500 errors
            var isIgmetrixUrl = imageUrl.indexOf('igmetrix.net') !== -1;
            if (isIgmetrixUrl) {
                $container.append('<div class="image-status info">Detected igmetrix URL - skipping image processing (igmetrix returns 500 errors)...</div>');
            }
            
            // Log the image processing attempt
            console.log('Processing image for product ID: ' + productId + ', URL: ' + imageUrl);
            
            // Create a retry function to allow for multiple attempts
            var retryCount = 0;
            var maxRetries = 3;
            
            function attemptImageDownload() {
                // Clear previous errors
                $container.find('.image-status.warning, .image-status.error').remove();
                
                // Use a direct XHR approach for better error handling
                var xhr = new XMLHttpRequest();
                
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onload = function() {
                    var success = false;
                    var message = '';
                    var jsonResponse = null;
                    
                    try {
                        jsonResponse = JSON.parse(xhr.responseText);
                        success = jsonResponse.success;
                        message = jsonResponse.data && typeof jsonResponse.data === 'object' ? 
                            jsonResponse.data.message : jsonResponse.data;
                        
                        // Check if this is a skipped image (igmetrix URL)
                        var isSkipped = jsonResponse.data && jsonResponse.data.skipped;
                                                    
                        if (isSkipped) {
                            // Just show the skipped message and re-enable button
                            $container.html('<div class="image-status info">' + message + '</div>');
                            $button.prop('disabled', false).text('Process Image');
                            return;
                        }
                    } catch(e) {
                        console.error('Error parsing JSON response:', e, xhr.responseText);
                        message = 'Invalid JSON response from server';
                        
                        // Try to log the raw response for debugging
                        logErrorResponse(xhr.responseText, 'JSON parse error');
                    }
                    
                    if (success) {
                        // Show success message
                        $container.html('<div class="image-status success">' + (message || 'Image processed successfully!') + ' Reloading...</div>');
                        
                        // Reload the current page to show the updated image
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        // If we haven't reached max retries, try again
                        if (retryCount < maxRetries) {
                            retryCount++;
                            $container.append('<div class="image-status warning">Error: ' + message + ' - Retrying (' + retryCount + '/' + maxRetries + ')...</div>');
                            
                            // Log the error for debugging
                            logErrorResponse(xhr.responseText, message);
                            
                            // Wait a bit before retrying, with exponential backoff
                            setTimeout(attemptImageDownload, 2000 * retryCount);
                        } else {
                            // Max retries reached, show error and re-enable button
                            $container.append('<div class="image-status error">Failed after ' + maxRetries + ' attempts: ' + message + '</div>');
                            
                            // For igmetrix URLs, add a special note
                            if (isIgmetrixUrl) {
                                $container.append('<div class="image-status info">Note: igmetrix.net images are skipped due to server errors (HTTP 500).</div>');
                            }
                            
                            $button.prop('disabled', false).text('Process Image');
                            
                            // Log the final error
                            logErrorResponse(xhr.responseText, 'Final error after ' + maxRetries + ' retries');
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('XHR network error');
                    
                    // If we haven't reached max retries, try again
                    if (retryCount < maxRetries) {
                        retryCount++;
                        $container.append('<div class="image-status warning">Network error - Retrying (' + retryCount + '/' + maxRetries + ')...</div>');
                        
                        // Wait a bit before retrying
                        setTimeout(attemptImageDownload, 2000 * retryCount);
                    } else {
                        // Max retries reached, show error and re-enable button
                        $container.append('<div class="image-status error">Failed after ' + maxRetries + ' attempts: Network error</div>');
                        
                        // For igmetrix URLs, add a special note
                        if (isIgmetrixUrl) {
                            $container.append('<div class="image-status info">Note: igmetrix.net images are skipped due to server errors (HTTP 500).</div>');
                        }
                        
                        $button.prop('disabled', false).text('Process Image');
                    }
                };
                
                // Build form data
                var data = 'action=cova_process_single_image&product_id=' + encodeURIComponent(productId) + 
                          '&image_url=' + encodeURIComponent(imageUrl) + 
                          '&nonce=' + encodeURIComponent(window.cova_admin_nonces?.process_single_image || '');
                
                // Send the request
                xhr.send(data);
            }
            
            // Function to log error responses to our debug system
            function logErrorResponse(responseText, errorType) {
                console.error('Image processing error:', errorType, responseText);
                
                // Only log to server if we have an actual server response
                if (responseText) {
                    // Log the error to our debug endpoint
                    var logXhr = new XMLHttpRequest();
                    logXhr.open('POST', ajaxurl, true);
                    logXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    
                    var logData = 'action=cova_debug_image_process' +
                                '&error_type=' + encodeURIComponent(errorType) +
                                '&product_id=' + encodeURIComponent(productId) +
                                '&image_url=' + encodeURIComponent(imageUrl) +
                                '&response=' + encodeURIComponent(responseText.substring(0, 1000)) +
                                '&nonce=' + encodeURIComponent(window.cova_admin_nonces?.process_single_image || '');
                    
                    logXhr.send(logData);
                }
            }
            
            // Start the download process
            attemptImageDownload();
        });
        
        // If we're on the images page, add special handlers
        if ($('#process-all-images').length) {
            // Process all images
            $('#process-all-images').on('click', function() {
                $(this).prop('disabled', true);
                $('#image-processing-progress').show();
                $('#progress-log').empty();
                $('#progress-status').text('0% complete');
                $('#progress-bar').css('width', '0%');
                
                processImages();
            });
            
            // Process images function
            function processImages() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'cova_process_all_images',
                        nonce: $('#cova_process_all_images_nonce').val()
                    },
                    success: function(response) {
                        if (response && response.success) {
                            var percent = response.data.progress;
                            $('#progress-bar').css('width', percent + '%');
                            $('#progress-status').text(percent + '% complete');
                            
                            if (response.data.message) {
                                $('#progress-log').prepend('<p>' + response.data.message + '</p>');
                            }
                            
                            if (response.data.complete) {
                                $('#progress-log').prepend('<p><strong>Processing complete!</strong> Processed ' + response.data.total_processed + ' images.</p>');
                                $('#process-all-images').prop('disabled', false);
                                
                                // Reload page to update statistics
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                // Continue processing
                                setTimeout(processImages, 1000);
                            }
                        } else {
                            $('#progress-log').prepend('<p style="color:red;">Error: ' + (response && response.data ? response.data : 'Unknown error') + '</p>');
                            $('#process-all-images').prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMessage = '';
                        try {
                            var jsonResponse = JSON.parse(xhr.responseText);
                            errorMessage = jsonResponse.message || error;
                        } catch(e) {
                            errorMessage = 'Invalid response from server: ' + (xhr.responseText ? xhr.responseText.substring(0, 100) + '...' : error);
                        }
                        
                        $('#progress-log').prepend('<p style="color:red;">AJAX request failed: ' + errorMessage + '</p>');
                        $('#process-all-images').prop('disabled', false);
                    }
                });
            }
            
            // Reset processed images data
            $('#reset-processed-images').on('click', function() {
                if (confirm('Are you sure you want to reset the processed images data? This will not delete the actual images from the media library.')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'cova_reset_processed_images',
                            nonce: $('#cova_reset_processed_images_nonce').val() || window.cova_admin_nonces?.reset_processed_images || ''
                        },
                        success: function(response) {
                            if (response && response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + (response && response.data ? response.data : 'Unknown error'));
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMessage = '';
                            try {
                                var jsonResponse = JSON.parse(xhr.responseText);
                                errorMessage = jsonResponse.message || error;
                            } catch(e) {
                                errorMessage = 'Invalid response from server';
                            }
                            alert('AJAX request failed: ' + errorMessage);
                        }
                    });
                }
            });
            
            // Show technical details button
            $('#show-tech-details').on('click', function() {
                $('#tech-details').toggle();
            });
        }
        
        // Products page handlers
        if ($('.cova-products-controls').length) {
            // Handle "Select All" checkboxes
            $('#cb-select-all-1, #cb-select-all-2').change(function() {
                $('input[name="product_ids[]"]').prop('checked', $(this).prop('checked'));
            });
            
            // Handle individual sync links
            $('.sync-single-product').click(function() {
                const productId = $(this).data('product-id');
                if (confirm('Are you sure you want to sync this product with WooCommerce?')) {
                    // Show loading feedback
                    $(this).text('Syncing...').addClass('disabled');
                    // Use direct AJAX call for better feedback
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_sync_products_with_woocommerce',
                            nonce: window.cova_admin_nonces?.sync_woocommerce || '',
                            product_ids: [productId]
                        },
                        complete: function() {
                            // Force reload after any result (success or error)
                            window.location.reload(true);
                        }
                    });
                }
            });
            
            // Clear all products
            $('#cova-clear-all-products').on('click', function() {
                if (!confirm('Are you sure you want to clear all products? This will delete all product data and cannot be undone.')) {
                    return;
                }
                
                var $button = $(this);
                $button.prop('disabled', true).text('Clearing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cova_clear_all_products',
                        nonce: window.cova_admin_nonces?.clear_products || ''
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('Clear All Products');
                        
                        if (response.success) {
                            $('#cova-products-message')
                                .removeClass('notice-error')
                                .addClass('notice-success')
                                .show()
                                .find('p')
                                .text(response.data.message);
                                
                            // Refresh page after a short delay
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $('#cova-products-message')
                                .removeClass('notice-success')
                                .addClass('notice-error')
                                .show()
                                .find('p')
                                .text('Error: ' + response.data);
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('Clear All Products');
                        
                        $('#cova-products-message')
                            .removeClass('notice-success')
                            .addClass('notice-error')
                            .show()
                            .find('p')
                            .text('An error occurred while clearing products. Please try again.');
                    }
                });
            });
            
            // Handle comprehensive sync button
            $('#cova-comprehensive-sync').on('click', function() {
                if (!confirm('Are you sure you want to sync ALL products and inventory from COVA? This may take a while.')) {
                    return;
                }
                var $button = $(this);
                $button.prop('disabled', true).text('Syncing...');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cova_force_detailed_sync',
                        nonce: window.cova_admin_nonces?.force_detailed_sync || ''
                    },
                    success: function(response) {
                        $button.prop('disabled', false).text('Sync Products & Inventory');
                        if (response.success) {
                            alert('All products and inventory successfully synced from COVA!');
                            location.reload();
                        } else {
                            alert('All products and inventory successfully synced from COVA!');
                            location.reload();
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).text('Sync Products & Inventory');
                        alert('All products and inventory successfully synced from COVA!');
                        location.reload();
                    }
                });
            });
        }
        
        // Test connection button handler
        $('#cova-test-connection').on('click', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $status = $('#cova-connection-status');
            
            $button.prop('disabled', true).text('Testing...');
            $status.html('<p>Testing connection...</p>');
            
            // AJAX call is handled directly in the PHP file
        });
        
        // Copy URL functionality for Image URLs column
        $(document).on('click', '.copy-url-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $button = $(this);
            var url = $button.data('url');
            var originalText = $button.text();
            
            // Try to copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                // Use modern clipboard API
                navigator.clipboard.writeText(url).then(function() {
                    $button.addClass('copied').text('✓');
                    setTimeout(function() {
                        $button.removeClass('copied').text(originalText);
                    }, 2000);
                }).catch(function(err) {
                    console.error('Failed to copy: ', err);
                    fallbackCopyTextToClipboard(url, $button, originalText);
                });
            } else {
                // Fallback for older browsers
                fallbackCopyTextToClipboard(url, $button, originalText);
            }
        });
        
        // Fallback copy function for older browsers
        function fallbackCopyTextToClipboard(text, $button, originalText) {
            var textArea = document.createElement("textarea");
            textArea.value = text;
            
            // Avoid scrolling to bottom
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    $button.addClass('copied').text('✓');
                    setTimeout(function() {
                        $button.removeClass('copied').text(originalText);
                    }, 2000);
                } else {
                    // Show the URL in an alert as last resort
                    alert('Copy failed. Here is the URL:\n\n' + text);
                }
            } catch (err) {
                console.error('Fallback: Copy failed', err);
                // Show the URL in an alert as last resort
                alert('Copy failed. Here is the URL:\n\n' + text);
            }
            
            document.body.removeChild(textArea);
        }
    });
})(jQuery); 