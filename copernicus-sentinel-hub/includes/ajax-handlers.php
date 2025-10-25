<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// AJAX handler for refreshing images
add_action('wp_ajax_copernicus_sentinel_hub_refresh_image', 'copernicus_sentinel_hub_refresh_image');
add_action('wp_ajax_nopriv_copernicus_sentinel_hub_refresh_image', 'copernicus_sentinel_hub_refresh_image');

function copernicus_sentinel_hub_refresh_image() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'copernicus_sentinel_hub_nonce')) {
        wp_die( esc_html__( 'Security check failed', 'copernicus-sentinel-hub' ) );
    }
    
    // Sanitize input parameters
    $lat_min = sanitize_text_field($_POST['lat_min']);
    $lat_max = sanitize_text_field($_POST['lat_max']);
    $lon_min = sanitize_text_field($_POST['lon_min']);
    $lon_max = sanitize_text_field($_POST['lon_max']);
    $lookback_days = intval($_POST['lookback_days']);
    $brightness = floatval($_POST['brightness']);
    $contrast = floatval($_POST['contrast']);
    $collection = sanitize_text_field($_POST['collection']);
    
    // Prepare attributes array
    $atts = array(
        'lat_min' => $lat_min,
        'lat_max' => $lat_max,
        'lon_min' => $lon_min,
        'lon_max' => $lon_max,
        'lookback_days' => $lookback_days,
        'brightness' => $brightness,
        'contrast' => $contrast,
        'collection' => $collection,
        'width' => '100%',
        'height' => '400px',
        'class' => 'copernicus-sentinel-hub-image'
    );
    
    // Force cache refresh by deleting existing cached image (use unified cache filename logic)
    include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/image-handler.php';
    $image_handler = new CopernicusSentinelHubImageHandler();
    $cache_filename = $image_handler->generate_cache_filename($atts);
    $cache_path = COPERNICUS_SENTINEL_HUB_CACHE_DIR . $cache_filename;
    $meta_path = $cache_path . '.json';
    if (file_exists($cache_path) && is_file($cache_path)) {
        unlink($cache_path);
    }
    if (file_exists($meta_path) && is_file($meta_path)) {
        unlink($meta_path);
    }
    
    // Generate new image HTML
    $html = $image_handler->generate_image_html($atts);
    
    wp_send_json_success(array(
        'html' => $html
    ));
}

// AJAX handler for clearing cache
add_action('wp_ajax_copernicus_sentinel_hub_clear_cache', 'copernicus_sentinel_hub_clear_cache');

function copernicus_sentinel_hub_clear_cache() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'copernicus_sentinel_hub_nonce')) {
        wp_die( esc_html__( 'Security check failed', 'copernicus-sentinel-hub' ) );
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'Insufficient permissions', 'copernicus-sentinel-hub' ) );
    }
    
    $cache_dir = COPERNICUS_SENTINEL_HUB_CACHE_DIR;
    $files_deleted = 0;
    
    if (file_exists($cache_dir)) {
        $files = glob($cache_dir . '*.{jpg,jpeg,png,tiff}', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $files_deleted++;
            }
        }
    }
    
    wp_send_json_success(array(
        'message' => sprintf( esc_html__( 'Successfully deleted %d cached images.', 'copernicus-sentinel-hub' ), $files_deleted )
    ));
}

// AJAX handler for getting cache status
add_action('wp_ajax_copernicus_sentinel_hub_cache_status', 'copernicus_sentinel_hub_cache_status');

function copernicus_sentinel_hub_cache_status() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'copernicus_sentinel_hub_nonce')) {
        wp_die( esc_html__( 'Security check failed', 'copernicus-sentinel-hub' ) );
    }
    
    $cache_dir = COPERNICUS_SENTINEL_HUB_CACHE_DIR;
    $cache_info = array(
        'files' => 0,
        'total_size' => 0,
        'oldest_file' => null,
        'newest_file' => null
    );
    
    if (file_exists($cache_dir)) {
        $files = glob($cache_dir . '*.{jpg,jpeg,png,tiff}', GLOB_BRACE);
        $cache_info['files'] = count($files);
        
        $oldest_time = time();
        $newest_time = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size = filesize($file);
                $cache_info['total_size'] += $size;
                
                $mtime = filemtime($file);
                if ($mtime < $oldest_time) {
                    $oldest_time = $mtime;
                    $cache_info['oldest_file'] = date('Y-m-d H:i:s', $mtime);
                }
                if ($mtime > $newest_time) {
                    $newest_time = $mtime;
                    $cache_info['newest_file'] = date('Y-m-d H:i:s', $mtime);
                }
            }
        }
    }
    
    // Format size
    $cache_info['total_size_formatted'] = size_format($cache_info['total_size']);
    
    wp_send_json_success($cache_info);
}

// AJAX handler for testing API connection
add_action('wp_ajax_copernicus_sentinel_hub_test_connection', 'copernicus_sentinel_hub_test_connection');

function copernicus_sentinel_hub_test_connection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'copernicus_sentinel_hub_nonce')) {
        wp_die( esc_html__( 'Security check failed', 'copernicus-sentinel-hub' ) );
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'Insufficient permissions', 'copernicus-sentinel-hub' ) );
    }
    
    include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/api-client.php';
    $api_client = new CopernicusSentinelHubAPIClient();
    
    // Test with a small search request
    $test_bbox = array(
        'lat_min' => 45.0,
        'lat_max' => 45.1,
        'lon_min' => 13.0,
        'lon_max' => 13.1
    );
    
    $result = $api_client->search_images($test_bbox, 30, 'sentinel-2-l2a');
    
    if ($result !== false) {
        // search_images may return a wrapper with 'feature' => <feature>
        $feature = null;
        if (is_array($result) && isset($result['feature'])) {
            $feature = $result['feature'];
        } elseif (is_array($result) && isset($result[0])) {
            // defensive: if an array of features was returned
            $feature = $result[0];
        } else {
            $feature = $result;
        }

        $cloud_cover = 'N/A';
        if (is_array($feature) && isset($feature['properties']) && isset($feature['properties']['eo:cloud_cover'])) {
            $cloud_cover = $feature['properties']['eo:cloud_cover'] . '%';
        }

        wp_send_json_success(array(
            'message' => esc_html__( 'Copernicus Dataspace API connection successful!', 'copernicus-sentinel-hub' ),
            'images_found' => is_array($result) ? 1 : 0,
            'cloud_cover' => $cloud_cover
        ));
    } else {
        wp_send_json_error(array(
            'message' => esc_html__( 'API connection failed. Please check your Client ID and Client Secret.', 'copernicus-sentinel-hub' )
        ));
    }
}