/* Copernicus Sentinel Hub WordPress Plugin JavaScript */

(function($) {
    'use strict';
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        initializeCopernicusSentinelHub();
    });
    
    function initializeCopernicusSentinelHub() {
        // Initialize image controls
        initializeImageControls();
        
        // Initialize admin features if on admin page
        if ($('.copernicus-sentinel-hub-admin').length > 0) {
            initializeAdminFeatures();
        }
    }
    
    function initializeImageControls() {
        // Handle brightness and contrast sliders
        $(document).on('input', '.copernicus-sentinel-hub-controls input[type="range"]', function() {
            var container = $(this).closest('.copernicus-sentinel-hub-container');
            var img = container.find('.copernicus-sentinel-hub-image');
            
            var brightness = parseFloat(container.find('.brightness-slider').val()) || 0;
            var contrast = parseFloat(container.find('.contrast-slider').val()) || 0;
            
            // Update display values
            container.find('.brightness-value').text(brightness.toFixed(1));
            container.find('.contrast-value').text(contrast.toFixed(1));
            
            // Apply CSS filters
            updateImageFilter(img, brightness, contrast);
        });
        
        // Handle reset button
        $(document).on('click', '.reset-controls', function() {
            var container = $(this).closest('.copernicus-sentinel-hub-container');
            var img = container.find('.copernicus-sentinel-hub-image');
            
            // Reset sliders
            container.find('.brightness-slider').val(0);
            container.find('.contrast-slider').val(0);
            container.find('.brightness-value').text('0.0');
            container.find('.contrast-value').text('0.0');
            
            // Remove filters
            img.css('filter', '');
        });
        
        // Handle image loading states
        $(document).on('load', '.copernicus-sentinel-hub-image', function() {
            $(this).closest('.copernicus-sentinel-hub-container').removeClass('loading');
        });
        
        $(document).on('error', '.copernicus-sentinel-hub-image', function() {
            var container = $(this).closest('.copernicus-sentinel-hub-container');
            container.removeClass('loading');
            container.find('.copernicus-sentinel-hub-image-wrapper').html(
                '<div class="copernicus-sentinel-hub-error">Failed to load satellite image.</div>'
            );
        });
    }
    
    function updateImageFilter(img, brightness, contrast) {
        var brightnessPercent = 100 + (brightness * 50);
        var contrastPercent = 100 + (contrast * 50);
        
        var filterStyle = 'brightness(' + brightnessPercent + '%) contrast(' + contrastPercent + '%)';
        img.css('filter', filterStyle);
    }
    
    function initializeAdminFeatures() {
        // Test API connection
        $('#test-api-connection').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.text();
            
            button.text('Testing...').prop('disabled', true);
            
            $.ajax({
                url: copernicus_sentinel_hub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'copernicus_sentinel_hub_test_connection',
                    nonce: copernicus_sentinel_hub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var message = response.data.message;
                        if (response.data.cloud_cover) {
                            message += ' Found image with ' + response.data.cloud_cover + ' cloud cover.';
                        }
                        showAdminMessage(message, 'success');
                    } else {
                        showAdminMessage(response.data.message || 'Connection test failed', 'error');
                    }
                },
                error: function() {
                    showAdminMessage('Connection test failed - server error', 'error');
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Clear cache
        $('#clear-cache').on('click', function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all cached images?')) {
                return;
            }
            
            var button = $(this);
            var originalText = button.text();
            
            button.text('Clearing...').prop('disabled', true);
            
            $.ajax({
                url: copernicus_sentinel_hub_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'copernicus_sentinel_hub_clear_cache',
                    nonce: copernicus_sentinel_hub_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showAdminMessage(response.data.message, 'success');
                        updateCacheStatus();
                    } else {
                        showAdminMessage('Failed to clear cache', 'error');
                    }
                },
                error: function() {
                    showAdminMessage('Failed to clear cache - server error', 'error');
                },
                complete: function() {
                    button.text(originalText).prop('disabled', false);
                }
            });
        });
        
        // Update cache status on page load
        updateCacheStatus();
        
        // Auto-refresh cache status every 30 seconds
        setInterval(updateCacheStatus, 30000);
    }
    
    function updateCacheStatus() {
        $.ajax({
            url: copernicus_sentinel_hub_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'copernicus_sentinel_hub_cache_status',
                nonce: copernicus_sentinel_hub_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var statusHtml = '<div class="cache-status-widget">';
                    statusHtml += '<h4>Cache Status</h4>';
                    statusHtml += '<div class="cache-status-item"><span>Files:</span><span>' + data.files + '</span></div>';
                    statusHtml += '<div class="cache-status-item"><span>Total Size:</span><span>' + data.total_size_formatted + '</span></div>';
                    
                    if (data.oldest_file) {
                        statusHtml += '<div class="cache-status-item"><span>Oldest:</span><span>' + data.oldest_file + '</span></div>';
                    }
                    if (data.newest_file) {
                        statusHtml += '<div class="cache-status-item"><span>Newest:</span><span>' + data.newest_file + '</span></div>';
                    }
                    
                    statusHtml += '</div>';
                    
                    $('#cache-status-container').html(statusHtml);
                }
            }
        });
    }
    
    function showAdminMessage(message, type) {
        var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        var messageHtml = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';
        
        $('.wrap h1').after(messageHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.notice.is-dismissible').fadeOut();
        }, 5000);
    }
    
    // Utility function to refresh images
    window.copernicusSentinelHubRefreshImage = function(containerId, params) {
        var container = $('#' + containerId);
        container.addClass('loading');
        
        $.ajax({
            url: copernicus_sentinel_hub_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'copernicus_sentinel_hub_refresh_image',
                nonce: copernicus_sentinel_hub_ajax.nonce,
                lat_min: params.lat_min,
                lat_max: params.lat_max,
                lon_min: params.lon_min,
                lon_max: params.lon_max,
                lookback_days: params.lookback_days || 30,
                brightness: params.brightness || 0,
                contrast: params.contrast || 0,
                collection: params.collection || 'sentinel-2-l2a'
            },
            success: function(response) {
                if (response.success) {
                    container.replaceWith(response.data.html);
                } else {
                    container.removeClass('loading');
                    showAdminMessage('Failed to refresh image', 'error');
                }
            },
            error: function() {
                container.removeClass('loading');
                showAdminMessage('Failed to refresh image - server error', 'error');
            }
        });
    };
    
})(jQuery);