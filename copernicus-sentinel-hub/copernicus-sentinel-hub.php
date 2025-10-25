<?php
/**
 * Plugin Name: Copernicus Sentinel Hub Satellite Imagery
 * Plugin URI: https://github.com/your-username/copernicus-sentinel-hub-wp-plugin
 * Description: Display satellite imagery from Copernicus Dataspace Sentinel Hub API with customizable parameters, caching, and image controls.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: copernicus-sentinel-hub
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COPERNICUS_SENTINEL_HUB_VERSION', '1.0.0');
define('COPERNICUS_SENTINEL_HUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COPERNICUS_SENTINEL_HUB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COPERNICUS_SENTINEL_HUB_CACHE_DIR', wp_upload_dir()['basedir'] . '/copernicus-sentinel-hub-cache/');
define('COPERNICUS_SENTINEL_HUB_CACHE_URL', wp_upload_dir()['baseurl'] . '/copernicus-sentinel-hub-cache/');

// Main plugin class
class CopernicusSentinelHubPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        // Enqueue admin scripts for plugin settings page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_shortcode('copernicus_sentinel_image', array($this, 'sentinel_image_shortcode'));
        add_shortcode('copernicus_simple_image', array($this, 'simple_image_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Create cache directory if it doesn't exist
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            wp_mkdir_p(COPERNICUS_SENTINEL_HUB_CACHE_DIR);
        }
        
        // Clean old cache files
        $this->clean_cache();
    }
    
    public function activate() {
        // Set default options
        add_option('copernicus_sentinel_hub_client_id', '');
        add_option('copernicus_sentinel_hub_client_secret', '');
        add_option('copernicus_sentinel_hub_cache_duration', 24); // 24 hours
        add_option('copernicus_sentinel_hub_max_cache_images', 15);
        
        // Create cache directory
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            wp_mkdir_p(COPERNICUS_SENTINEL_HUB_CACHE_DIR);
        }
    }
    
    public function deactivate() {
        // Clean up cache directory
        $this->clear_all_cache();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Copernicus Sentinel Hub Settings',
            'Copernicus Sentinel Hub',
            'manage_options',
            'copernicus-sentinel-hub-settings',
            array($this, 'admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('copernicus_sentinel_hub_settings', 'copernicus_sentinel_hub_client_id');
        register_setting('copernicus_sentinel_hub_settings', 'copernicus_sentinel_hub_client_secret');
        register_setting('copernicus_sentinel_hub_settings', 'copernicus_sentinel_hub_cache_duration');
        register_setting('copernicus_sentinel_hub_settings', 'copernicus_sentinel_hub_max_cache_images');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('copernicus-sentinel-hub-js', COPERNICUS_SENTINEL_HUB_PLUGIN_URL . 'assets/copernicus-sentinel-hub.js', array('jquery'), COPERNICUS_SENTINEL_HUB_VERSION, true);
        wp_enqueue_style('copernicus-sentinel-hub-css', COPERNICUS_SENTINEL_HUB_PLUGIN_URL . 'assets/copernicus-sentinel-hub.css', array(), COPERNICUS_SENTINEL_HUB_VERSION);
        
        wp_localize_script('copernicus-sentinel-hub-js', 'copernicus_sentinel_hub_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('copernicus_sentinel_hub_nonce')
        ));
    }

    /**
     * Enqueue scripts/styles for the admin settings page only
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our options page
        if ($hook !== 'settings_page_copernicus-sentinel-hub-settings') {
            return;
        }

        wp_enqueue_script('copernicus-sentinel-hub-js', COPERNICUS_SENTINEL_HUB_PLUGIN_URL . 'assets/copernicus-sentinel-hub.js', array('jquery'), COPERNICUS_SENTINEL_HUB_VERSION, true);
        wp_enqueue_style('copernicus-sentinel-hub-css', COPERNICUS_SENTINEL_HUB_PLUGIN_URL . 'assets/copernicus-sentinel-hub.css', array(), COPERNICUS_SENTINEL_HUB_VERSION);

        wp_localize_script('copernicus-sentinel-hub-js', 'copernicus_sentinel_hub_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('copernicus_sentinel_hub_nonce')
        ));
    }
    
    public function admin_page() {
        include COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    public function sentinel_image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'lat_min' => '',
            'lat_max' => '',
            'lon_min' => '',
            'lon_max' => '',
            'lookback_days' => 30,
            'width' => '800', // normalize to numeric later
            'height' => 'auto',
            'brightness' => 0,
            'contrast' => 0,
            'class' => 'copernicus-sentinel-hub-image',
            'collection' => 'sentinel-2-l2a',
            'max_cloud_cover' => ''
        ), $atts);
        
        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);

        // Normalize width to integer (avoid double px). Height left as-is for CSS flexibility.
        $atts['width'] = $this->normalize_dimension($atts['width']);
        
        // Validate required parameters
        if (empty($atts['lat_min']) || empty($atts['lat_max']) || 
            empty($atts['lon_min']) || empty($atts['lon_max'])) {
            return '<div class="copernicus-sentinel-hub-error">' . esc_html__( 'Error: Bounding box coordinates are required.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Include the image handler
        include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/image-handler.php';
        $image_handler = new CopernicusSentinelHubImageHandler();
        
        return $image_handler->generate_image_html($atts);
    }
    
    public function simple_image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'lat_min' => '',
            'lat_max' => '',
            'lon_min' => '',
            'lon_max' => '',
            'lookback_days' => 30,
            'width' => '800',
            'height' => '600',
            'brightness' => 0,   
            'contrast' => 0,
            'collection' => 'sentinel-2-l2a',
            'alt' => __( 'Satellite Image', 'copernicus-sentinel-hub' ),
            'native_size' => 'false',
            'max_cloud_cover' => ''
        ), $atts);
        
        // Sanitize attributes
        $atts = array_map('sanitize_text_field', $atts);

        // Normalize width/height to integers for consistent styling (unless native_size true)
        $atts['width'] = $this->normalize_dimension($atts['width']);
        $atts['height'] = $this->normalize_dimension($atts['height']);
        
        // Validate required parameters
        if (empty($atts['lat_min']) || empty($atts['lat_max']) || 
            empty($atts['lon_min']) || empty($atts['lon_max'])) {
            return '<div style="color: red; font-weight: bold;">' . esc_html__( 'Error: Bounding box coordinates are required.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Check if API credentials are configured
        if (!get_option('copernicus_sentinel_hub_client_id') || !get_option('copernicus_sentinel_hub_client_secret')) {
            return '<div style="color: red; font-weight: bold;">' . esc_html__( 'Error: Copernicus Dataspace API credentials not configured.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Include the image handler and API client
        include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/image-handler.php';
        include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/api-client.php';
        
        $image_handler = new CopernicusSentinelHubImageHandler();
        
        // Generate cache filename
        $cache_params = array(
            'lat_min' => $atts['lat_min'],
            'lat_max' => $atts['lat_max'],
            'lon_min' => $atts['lon_min'],
            'lon_max' => $atts['lon_max'],
            'lookback_days' => $atts['lookback_days'],
            'brightness' => $atts['brightness'],
            'contrast' => $atts['contrast'],
            'collection' => $atts['collection'],
            'max_cloud_cover' => isset($atts['max_cloud_cover']) ? $atts['max_cloud_cover'] : ''
        );
        
        $hash = md5(serialize($cache_params));
        $cache_filename = 'copernicus_sentinel_' . $hash . '.jpg';
        $cache_path = COPERNICUS_SENTINEL_HUB_CACHE_DIR . $cache_filename;
        $cache_url = COPERNICUS_SENTINEL_HUB_CACHE_URL . $cache_filename;
        
        // Check if cached image exists and is still valid
        $meta_path = COPERNICUS_SENTINEL_HUB_CACHE_DIR . $cache_filename . '.json';
        if (file_exists($cache_path) && $this->is_cache_valid($cache_path)) {
            // Read metadata if available
            $metadata = null;
            if (file_exists($meta_path)) {
                $meta_contents = file_get_contents($meta_path);
                $metadata = json_decode($meta_contents, true);
            }
            
            return $this->generate_simple_image_html($cache_url, $atts, $metadata);
        }
        
        // Generate new image
        $api_client = new CopernicusSentinelHubAPIClient();
        $bbox = array(
            'lat_min' => floatval($atts['lat_min']),
            'lat_max' => floatval($atts['lat_max']),
            'lon_min' => floatval($atts['lon_min']),
            'lon_max' => floatval($atts['lon_max'])
        );
        
        $max_cloud_cover = isset($atts['max_cloud_cover']) && $atts['max_cloud_cover'] !== '' ? floatval($atts['max_cloud_cover']) : null;
        
        $image_data = $api_client->get_image(
            $bbox,
            intval($atts['lookback_days']),
            floatval($atts['brightness']),
            floatval($atts['contrast']),
            $atts['collection'],
            $max_cloud_cover
        );
        
        if (!$image_data) {
            return '<div style="color: red; font-weight: bold;">' . esc_html__( 'Error: Unable to retrieve satellite image. Please check your coordinates and try again.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Create cache directory if needed
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            wp_mkdir_p(COPERNICUS_SENTINEL_HUB_CACHE_DIR);
        }
        
        // Save image to cache (handle both old format and new metadata format)
        $image_bytes = is_array($image_data) && isset($image_data['image']) ? $image_data['image'] : $image_data;
        $metadata = is_array($image_data) && isset($image_data['metadata']) ? $image_data['metadata'] : null;
        
        if (false === file_put_contents($cache_path, $image_bytes)) {
            error_log('[Copernicus Sentinel Hub] Failed writing image cache file: ' . $cache_path);
        }
        
        // Save metadata sidecar JSON
        if ($metadata !== null) {
            $meta_json = json_encode($metadata);
            if ($meta_json !== false) {
                if (false === file_put_contents($meta_path, $meta_json)) {
                    error_log('[Copernicus Sentinel Hub] Failed writing metadata cache file: ' . $meta_path);
                }
            }
        }
        
        return $this->generate_simple_image_html($cache_url, $atts, $metadata);
    }
    
    private function generate_simple_image_html($image_url, $atts, $metadata = null) {
        // Check if native_size is requested (no scaling)
        if (strtolower($atts['native_size']) === 'true') {
            $style = 'display:block;';
        } else {
            $style = 'max-width:' . intval($atts['width']) . 'px;height:auto;display:block;';
        }
        
        $html = '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($atts['alt']) . '" style="' . esc_attr($style) . '" />';
        
        // Add caption with metadata if available
        if (!empty($metadata) && isset($metadata['datetime'])) {
            $caption_parts = array();
            
            // Format datetime
            $friendly_date = esc_html($metadata['datetime']);
            try {
                $dt = new DateTime($metadata['datetime']);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $friendly_date = $dt->format('j M Y, H:i') . ' UTC';
            } catch (Exception $e) {
                // leave as-is if parsing fails
            }
            $caption_parts[] = 'Date: ' . $friendly_date;
            
            // Add cloud cover if available
            if (isset($metadata['cloud_cover']) && $metadata['cloud_cover'] !== null) {
                $caption_parts[] = 'Cloud cover: ' . number_format(floatval($metadata['cloud_cover']), 1) . '%';
            }
            
            if (!empty($caption_parts)) {
                $html .= '<div style="font-size: 12px; color: #666; margin-top: 5px; font-style: italic;">' . implode(' | ', $caption_parts) . '</div>';
            }
        }
        
        return $html;
    }
    
    private function is_cache_valid($cache_path) {
        if (!file_exists($cache_path)) {
            return false;
        }
        
        $cache_duration = get_option('copernicus_sentinel_hub_cache_duration', 24) * 3600; // Convert hours to seconds
        $file_age = time() - filemtime($cache_path);
        
        return $file_age < $cache_duration;
    }
    
    private function clean_cache() {
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            return;
        }
        
        $cache_duration = get_option('copernicus_sentinel_hub_cache_duration', 24) * 3600; // Convert hours to seconds
        $max_images = get_option('copernicus_sentinel_hub_max_cache_images', 15);
        
        $files = glob(COPERNICUS_SENTINEL_HUB_CACHE_DIR . '*.{jpg,jpeg,png,tiff}', GLOB_BRACE);
        if (!$files) {
            return;
        }
        
        // Remove old files
        foreach ($files as $file) {
            if (time() - filemtime($file) > $cache_duration) {
                unlink($file);
            }
        }
        
        // Keep only max number of images (remove oldest)
        $files = glob(COPERNICUS_SENTINEL_HUB_CACHE_DIR . '*.{jpg,jpeg,png,tiff}', GLOB_BRACE);
        if (count($files) > $max_images) {
            // Sort by modification time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $files_to_remove = array_slice($files, 0, count($files) - $max_images);
            foreach ($files_to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    private function clear_all_cache() {
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            return;
        }
        
        $files = glob(COPERNICUS_SENTINEL_HUB_CACHE_DIR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        rmdir(COPERNICUS_SENTINEL_HUB_CACHE_DIR);
    }

    /**
     * Normalize dimension value to a positive integer (strip px, enforce bounds).
     */
    private function normalize_dimension($value) {
        if ($value === 'auto') {
            return 'auto';
        }
        // Remove anything that's not digit
        $num = intval(preg_replace('/[^0-9]/', '', (string)$value));
        if ($num <= 0) {
            $num = 100; // safe fallback
        }
        // Upper bound to avoid absurd sizes
        if ($num > 5000) {
            $num = 5000;
        }
        return $num;
    }
}

// Initialize the plugin
new CopernicusSentinelHubPlugin();

// Include additional files
include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/api-client.php';
include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/image-handler.php';
include_once COPERNICUS_SENTINEL_HUB_PLUGIN_DIR . 'includes/ajax-handlers.php';