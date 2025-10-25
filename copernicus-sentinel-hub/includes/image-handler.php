<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CopernicusSentinelHubImageHandler {
    
    private $api_client;
    
    public function __construct() {
        $this->api_client = new CopernicusSentinelHubAPIClient();
    }
    
    /**
     * Generate HTML for displaying the satellite image
     */
    public function generate_image_html($atts) {
        // Sanitize input attributes
        $atts = array_map('sanitize_text_field', $atts);
        
        // Validate coordinates
        if (!$this->validate_coordinates($atts)) {
            return '<div class="copernicus-sentinel-hub-error">' . esc_html__( 'Error: Invalid coordinates provided.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Check if API credentials are configured
        if (!get_option('copernicus_sentinel_hub_client_id') || !get_option('copernicus_sentinel_hub_client_secret')) {
            return '<div class="copernicus-sentinel-hub-error">' . esc_html__( 'Error: Copernicus Dataspace API credentials not configured. Please configure them in the admin settings.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Generate cache filename
    $cache_filename = $this->generate_cache_filename($atts);
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

            return $this->generate_image_tag($cache_url, $atts, $metadata);
        }
        
        // Generate new image
        $bbox = array(
            'lat_min' => floatval($atts['lat_min']),
            'lat_max' => floatval($atts['lat_max']),
            'lon_min' => floatval($atts['lon_min']),
            'lon_max' => floatval($atts['lon_max'])
        );
        
        $max_cloud_cover = isset($atts['max_cloud_cover']) && $atts['max_cloud_cover'] !== '' ? floatval($atts['max_cloud_cover']) : null;
        
        $image_data = $this->api_client->get_image(
            $bbox,
            intval($atts['lookback_days']),
            floatval($atts['brightness']),
            floatval($atts['contrast']),
            $atts['collection'],
            $max_cloud_cover
        );
        
        if (!$image_data) {
            return '<div class="copernicus-sentinel-hub-error">' . esc_html__( 'Error: Unable to retrieve satellite image. Please check your coordinates and try again.', 'copernicus-sentinel-hub' ) . '</div>';
        }
        
        // Save image to cache
        if (!file_exists(COPERNICUS_SENTINEL_HUB_CACHE_DIR)) {
            wp_mkdir_p(COPERNICUS_SENTINEL_HUB_CACHE_DIR);
        }

        // $image_data may now be array with 'image' and 'metadata'
        $image_bytes = is_array($image_data) && isset($image_data['image']) ? $image_data['image'] : $image_data;
        $metadata = is_array($image_data) && isset($image_data['metadata']) ? $image_data['metadata'] : null;

        if (false === file_put_contents($cache_path, $image_bytes)) {
            error_log('[Copernicus Sentinel Hub] Failed writing image cache file: ' . $cache_path);
            return '<div class="copernicus-sentinel-hub-error">' . esc_html__( 'Error: Unable to store cached image on the server.', 'copernicus-sentinel-hub' ) . '</div>';
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

        return $this->generate_image_tag($cache_url, $atts, $metadata);
    }
    
    /**
     * Validate coordinate parameters
     */
    private function validate_coordinates($atts) {
        $lat_min = floatval($atts['lat_min']);
        $lat_max = floatval($atts['lat_max']);
        $lon_min = floatval($atts['lon_min']);
        $lon_max = floatval($atts['lon_max']);
        
        // Check if coordinates are in valid ranges
        if ($lat_min < -90 || $lat_min > 90 ||
            $lat_max < -90 || $lat_max > 90 ||
            $lon_min < -180 || $lon_min > 180 ||
            $lon_max < -180 || $lon_max > 180) {
            return false;
        }
        
        // Check if bounding box is valid
        if ($lat_min >= $lat_max || $lon_min >= $lon_max) {
            return false;
        }
        
        // Check if bounding box is not too large (max 1 degree)
        if (($lat_max - $lat_min) > 1 || ($lon_max - $lon_min) > 1) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate cache filename based on parameters
     */
    /**
     * Generate cache filename based on parameters.
     * Made public so AJAX refresh handler can reuse identical logic.
     */
    public function generate_cache_filename($atts) {
        $params = array(
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
        
        $hash = md5(serialize($params));
        return 'copernicus_sentinel_' . $hash . '.jpg';
    }
    
    /**
     * Check if cached image is still valid
     */
    private function is_cache_valid($cache_path) {
        if (!file_exists($cache_path)) {
            return false;
        }
        
        $cache_duration = get_option('copernicus_sentinel_hub_cache_duration', 24) * 3600; // Convert hours to seconds
        $file_age = time() - filemtime($cache_path);
        
        return $file_age < $cache_duration;
    }
    
    /**
     * Generate HTML image tag with controls
     */
    private function generate_image_tag($image_url, $atts, $metadata = null) {
        $unique_id = 'copernicus-sentinel-image-' . uniqid();
        
        // Sanitize attributes
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        $class = esc_attr($atts['class']);
        $brightness = floatval($atts['brightness']);
        $contrast = floatval($atts['contrast']);
        
        // Generate CSS filter based on brightness/contrast
        $filter_style = $this->generate_css_filter($brightness, $contrast);
        
        ob_start();
        ?>
        <div class="copernicus-sentinel-hub-container" id="<?php echo esc_attr($unique_id); ?>" style="max-width: <?php echo $width; ?>;">
            <div class="copernicus-sentinel-hub-image-wrapper">
             <img src="<?php echo esc_url($image_url); ?>" 
                 alt="<?php echo esc_attr__( 'Copernicus Sentinel Satellite Image', 'copernicus-sentinel-hub' ); ?>" 
                     class="<?php echo $class; ?>"
                     style="<?php echo $filter_style; ?>" />
            </div>
            
            <?php if ($brightness != 0 || $contrast != 0): ?>
            <div class="copernicus-sentinel-hub-controls">
                <div class="control-group">
                    <label><?php echo esc_html__( 'Brightness', 'copernicus-sentinel-hub' ); ?>: <span class="brightness-value"><?php echo number_format($brightness, 1); ?></span></label>
                    <input type="range" 
                           class="brightness-slider" 
                           min="-2" 
                           max="2" 
                           step="0.1" 
                           value="<?php echo esc_attr($brightness); ?>" 
                           data-target="<?php echo esc_attr($unique_id); ?>" />
                </div>
                
                <div class="control-group">
                    <label><?php echo esc_html__( 'Contrast', 'copernicus-sentinel-hub' ); ?>: <span class="contrast-value"><?php echo number_format($contrast, 1); ?></span></label>
                    <input type="range" 
                           class="contrast-slider" 
                           min="-2" 
                           max="2" 
                           step="0.1" 
                           value="<?php echo esc_attr($contrast); ?>" 
                           data-target="<?php echo esc_attr($unique_id); ?>" />
                </div>
                
                <button type="button" class="reset-controls" data-target="<?php echo esc_attr($unique_id); ?>"><?php echo esc_html__( 'Reset', 'copernicus-sentinel-hub' ); ?></button>
            </div>
            <?php endif; ?>
            
            <div class="copernicus-sentinel-hub-info">
                <small>
                    <?php echo esc_html__( 'Collection', 'copernicus-sentinel-hub' ); ?>: <?php echo esc_html(strtoupper($atts['collection'])); ?> |
                    <?php echo esc_html__( 'Coordinates', 'copernicus-sentinel-hub' ); ?>: <?php echo esc_html($atts['lat_min']); ?>, <?php echo esc_html($atts['lon_min']); ?> 
                    <?php echo esc_html__( 'to', 'copernicus-sentinel-hub' ); ?> <?php echo esc_html($atts['lat_max']); ?>, <?php echo esc_html($atts['lon_max']); ?>
                    | <?php echo esc_html__( 'Lookback', 'copernicus-sentinel-hub' ); ?>: <?php echo esc_html($atts['lookback_days']); ?> <?php echo esc_html__( 'days', 'copernicus-sentinel-hub' ); ?>
                </small>
                <?php if (!empty($metadata) && isset($metadata['datetime'])): ?>
                <div class="copernicus-sentinel-hub-metadata">
                    <small>
                        <?php
                        // Try to format the datetime into a friendly UTC string
                        $friendly_date = esc_html($metadata['datetime']);
                        try {
                            $dt = new DateTime($metadata['datetime']);
                            $dt->setTimezone(new DateTimeZone('UTC'));
                            $friendly_date = $dt->format('j M Y, H:i').' UTC';
                        } catch (Exception $e) {
                            // leave as-is if parsing fails
                        }
                        ?>
                        <?php echo esc_html__( 'Date', 'copernicus-sentinel-hub' ); ?>: <?php echo $friendly_date; ?>
                        <?php if (isset($metadata['cloud_cover'])): ?> | <?php echo esc_html__( 'Cloud cover', 'copernicus-sentinel-hub' ); ?>: <?php echo esc_html(number_format(floatval($metadata['cloud_cover']), 1)); ?>%
                        <?php endif; ?>
                        <?php if (isset($metadata['selected_threshold']) && $metadata['selected_threshold'] !== null): ?>
                            | <?php printf( esc_html__( 'Chose most recent image within %d%% cloud cover', 'copernicus-sentinel-hub' ), intval($metadata['selected_threshold']) ); ?>
                        <?php endif; ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var container = $('#<?php echo esc_js($unique_id); ?>');
            var img = container.find('img');
            
            // Brightness control
            container.find('.brightness-slider').on('input', function() {
                var brightness = parseFloat($(this).val());
                var contrast = parseFloat(container.find('.contrast-slider').val()) || 0;
                
                container.find('.brightness-value').text(brightness.toFixed(1));
                updateImageFilter(img, brightness, contrast);
            });
            
            // Contrast control
            container.find('.contrast-slider').on('input', function() {
                var contrast = parseFloat($(this).val());
                var brightness = parseFloat(container.find('.brightness-slider').val()) || 0;
                
                container.find('.contrast-value').text(contrast.toFixed(1));
                updateImageFilter(img, brightness, contrast);
            });
            
            // Reset controls
            container.find('.reset-controls').on('click', function() {
                container.find('.brightness-slider').val(0);
                container.find('.contrast-slider').val(0);
                container.find('.brightness-value').text('0.0');
                container.find('.contrast-value').text('0.0');
                
                img.css('filter', '');
            });
            
            function updateImageFilter(img, brightness, contrast) {
                var brightnessPercent = 100 + (brightness * 50);
                var contrastPercent = 100 + (contrast * 50);
                
                var filterStyle = 'brightness(' + brightnessPercent + '%) contrast(' + contrastPercent + '%)';
                img.css('filter', filterStyle);
            }
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Generate CSS filter based on brightness and contrast values
     */
    private function generate_css_filter($brightness, $contrast) {
        if ($brightness == 0 && $contrast == 0) {
            return '';
        }
        
        $brightness_percent = 100 + ($brightness * 50);
        $contrast_percent = 100 + ($contrast * 50);
        
        return "filter: brightness({$brightness_percent}%) contrast({$contrast_percent}%);";
    }
}