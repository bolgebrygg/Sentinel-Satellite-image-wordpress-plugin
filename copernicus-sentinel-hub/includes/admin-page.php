<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['submit'])) {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['_wpnonce'], 'copernicus_sentinel_hub_settings')) {
        wp_die('Security check failed');
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Sanitize and update options
    update_option('copernicus_sentinel_hub_client_id', sanitize_text_field($_POST['copernicus_sentinel_hub_client_id']));
    update_option('copernicus_sentinel_hub_client_secret', sanitize_text_field($_POST['copernicus_sentinel_hub_client_secret']));
    update_option('copernicus_sentinel_hub_cache_duration', intval($_POST['copernicus_sentinel_hub_cache_duration']));
    update_option('copernicus_sentinel_hub_max_cache_images', intval($_POST['copernicus_sentinel_hub_max_cache_images']));
    
    echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
}

$client_id = get_option('copernicus_sentinel_hub_client_id', '');
$client_secret = get_option('copernicus_sentinel_hub_client_secret', '');
$cache_duration = get_option('copernicus_sentinel_hub_cache_duration', 24);
$max_cache_images = get_option('copernicus_sentinel_hub_max_cache_images', 15);
?>

<div class="wrap copernicus-sentinel-hub-admin">
    <h1>Copernicus Sentinel Hub Settings</h1>
    
    <div class="card" style="max-width: 800px;">
        <h2 class="title">Copernicus Dataspace API Configuration</h2>
        
        <form method="post" action="">
            <?php wp_nonce_field('copernicus_sentinel_hub_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="copernicus_sentinel_hub_client_id">Client ID</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="copernicus_sentinel_hub_client_id" 
                               name="copernicus_sentinel_hub_client_id" 
                               value="<?php echo esc_attr($client_id); ?>" 
                               class="regular-text" 
                               required />
                        <p class="description">Your Copernicus Dataspace Client ID</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="copernicus_sentinel_hub_client_secret">Client Secret</label>
                    </th>
                    <td>
                        <input type="password" 
                               id="copernicus_sentinel_hub_client_secret" 
                               name="copernicus_sentinel_hub_client_secret" 
                               value="<?php echo esc_attr($client_secret); ?>" 
                               class="regular-text" 
                               required />
                        <p class="description">Your Copernicus Dataspace Client Secret</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="copernicus_sentinel_hub_cache_duration">Cache Duration (hours)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="copernicus_sentinel_hub_cache_duration" 
                               name="copernicus_sentinel_hub_cache_duration" 
                               value="<?php echo esc_attr($cache_duration); ?>" 
                               min="1" 
                               max="168" 
                               class="small-text" />
                        <p class="description">How long to cache images (1-168 hours, default: 24)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="copernicus_sentinel_hub_max_cache_images">Max Cache Images</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="copernicus_sentinel_hub_max_cache_images" 
                               name="copernicus_sentinel_hub_max_cache_images" 
                               value="<?php echo esc_attr($max_cache_images); ?>" 
                               min="5" 
                               max="50" 
                               class="small-text" />
                        <p class="description">Maximum number of images to keep in cache (5-50, default: 15)</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <div style="margin-top: 20px;">
            <button type="button" id="test-api-connection" class="button button-secondary">
                Test API Connection
            </button>
        </div>
    </div>
    
    <div class="card" style="max-width: 800px;">
        <h2 class="title">API Setup Instructions</h2>
        
        <h3>Getting Your Copernicus Dataspace Credentials</h3>
        <ol>
            <li>Visit <a href="https://dataspace.copernicus.eu/" target="_blank">Copernicus Dataspace</a></li>
            <li>Create an account or log in</li>
            <li>Go to your <strong>User Dashboard</strong></li>
            <li>Navigate to <strong>API Access</strong> or <strong>Applications</strong></li>
            <li>Create a new application or use existing credentials</li>
            <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong></li>
        </ol>
        
        <h3>API Endpoints Used</h3>
        <ul>
            <li><strong>Authentication:</strong> https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token</li>
            <li><strong>Catalog Search:</strong> https://sh.dataspace.copernicus.eu/api/v1/catalog/1.0.0/search</li>
            <li><strong>Image Processing:</strong> https://sh.dataspace.copernicus.eu/api/v1/process</li>
        </ul>
    </div>
    
    <div class="card" style="max-width: 800px;">
        <h2 class="title">Usage Instructions</h2>
        
        <h3>Basic Shortcode</h3>
        <code>[copernicus_sentinel_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="30"]</code>
        
        <h3>Advanced Shortcode with Collection</h3>
        <code>[copernicus_sentinel_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="7" collection="sentinel-2-l1c" brightness="0.5" contrast="-0.2"]</code>
        
        <h3>Parameters</h3>
        <ul>
            <li><strong>lat_min, lat_max:</strong> Latitude bounds (required)</li>
            <li><strong>lon_min, lon_max:</strong> Longitude bounds (required)</li>
            <li><strong>lookback_days:</strong> Days to look back for imagery (default: 30)</li>
            <li><strong>collection:</strong> Satellite collection (default: sentinel-2-l2a)</li>
            <li><strong>brightness:</strong> Brightness adjustment (-2 to +2, default: 0)</li>
            <li><strong>contrast:</strong> Contrast adjustment (-2 to +2, default: 0)</li>
            <li><strong>width, height:</strong> Image constraints (default: 800px, auto)</li>
        </ul>
        
        <h3>Available Collections</h3>
        <ul>
            <li><strong>sentinel-2-l2a:</strong> Sentinel-2 Level 2A (atmospherically corrected)</li>
            <li><strong>sentinel-2-l1c:</strong> Sentinel-2 Level 1C (top-of-atmosphere)</li>
            <li><strong>sentinel-1-grd:</strong> Sentinel-1 Ground Range Detected</li>
        </ul>
        
        <h3>Cache Management</h3>
        <p>Images are automatically cached to minimize API calls. The cache is cleaned based on your settings above.</p>
        
        <div style="margin-top: 20px;">
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('copernicus_sentinel_hub_clear_cache'); ?>
                <input type="hidden" name="clear_cache" value="1">
                <button type="submit" class="button button-secondary" 
                        onclick="return confirm('Are you sure you want to clear all cached images?')">
                    Clear All Cache
                </button>
            </form>
            <div id="cache-status-container" style="margin-top: 15px;"></div>
        </div>
        
        <?php
        if (isset($_POST['clear_cache']) && $_POST['clear_cache'] == '1') {
            // Verify nonce for security
            if (wp_verify_nonce($_POST['_wpnonce'], 'copernicus_sentinel_hub_clear_cache')) {
                $cache_dir = wp_upload_dir()['basedir'] . '/copernicus-sentinel-hub-cache/';
                if (file_exists($cache_dir)) {
                    $files = glob($cache_dir . '*');
                    $files_cleared = 0;
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                            $files_cleared++;
                        }
                    }
                    echo '<div class="notice notice-success"><p>Cache cleared successfully! Removed ' . esc_html($files_cleared) . ' files.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
            }
        }
        ?>
    </div>
</div>