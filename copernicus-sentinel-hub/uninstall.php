<?php
/**
 * Uninstall script for Copernicus Sentinel Hub WordPress Plugin
 * This file runs when the plugin is deleted from WordPress
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('copernicus_sentinel_hub_client_id');
delete_option('copernicus_sentinel_hub_client_secret');
delete_option('copernicus_sentinel_hub_cache_duration');
delete_option('copernicus_sentinel_hub_max_cache_images');

// Remove transients (cached access tokens)
delete_transient('copernicus_sentinel_hub_access_token');

// Clear and remove cache directory
$cache_dir = wp_upload_dir()['basedir'] . '/copernicus-sentinel-hub-cache/';
if (file_exists($cache_dir)) {
    // Remove all cache files
    $files = glob($cache_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    
    // Remove cache directory
    rmdir($cache_dir);
}

// Clean up any user meta (if we stored any user-specific settings)
// delete_user_meta($user_id, 'copernicus_sentinel_hub_preferences');

// Remove any custom database tables (none created by this plugin)
// If you add custom tables in future versions, remove them here

// Clean up any scheduled events (none used by this plugin)
// wp_clear_scheduled_hook('copernicus_sentinel_hub_cleanup_cache');
?>