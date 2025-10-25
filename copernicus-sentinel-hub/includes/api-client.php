<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CopernicusSentinelHubAPIClient {
    
    private $client_id;
    private $client_secret;
    private $base_url = 'https://sh.dataspace.copernicus.eu';
    private $auth_url = 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token';
    
    public function __construct() {
        $this->client_id = get_option('copernicus_sentinel_hub_client_id');
        $this->client_secret = get_option('copernicus_sentinel_hub_client_secret');
    }
    
    /**
     * Get access token for API requests
     */
    private function get_access_token() {
        // Check if credentials are configured
        if (empty($this->client_id) || empty($this->client_secret)) {
            error_log('Copernicus Sentinel Hub: Client credentials not configured');
            return false;
        }
        
        $cache_key = 'copernicus_sentinel_hub_access_token';
        $access_token = get_transient($cache_key);
        
        if ($access_token) {
            return $access_token;
        }
        
        $response = wp_remote_post($this->auth_url, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('Copernicus Sentinel Hub API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $expires_in = isset($body['expires_in']) ? $body['expires_in'] - 60 : 3540; // Subtract 60s for safety
            set_transient($cache_key, $body['access_token'], $expires_in);
            return $body['access_token'];
        }
        
        return false;
    }
    
    /**
     * Search for images with smart cloud cover selection using Copernicus catalog API
     */
    public function search_images($bbox, $lookback_days, $collection = 'sentinel-2-l2a', $max_cloud_cover = null) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        $from_date = date('Y-m-d\\TH:i:s\\Z', strtotime("-{$lookback_days} days"));
        $to_date = date('Y-m-d\\TH:i:s\\Z');

        // Single catalog request for the full period requested by the user.
        $query_params = array(
            'collections' => $collection,
            'bbox' => implode(',', array(
                $bbox['lon_min'],
                $bbox['lat_min'],
                $bbox['lon_max'],
                $bbox['lat_max']
            )),
            'datetime' => $from_date . '/' . $to_date,
            'limit' => 100,
            'fields' => 'id,type,geometry,bbox,properties.datetime,properties.eo:cloud_cover'
        );

        $url = $this->base_url . '/api/v1/catalog/1.0.0/search?' . http_build_query($query_params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log('Copernicus Sentinel Hub Search Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['features']) || !is_array($body['features']) || empty($body['features'])) {
            return false;
        }

        // Normalize features: extract datetime and cloud_cover
        $features = array();
        foreach ($body['features'] as $feature) {
            $datetime = isset($feature['properties']['datetime']) ? $feature['properties']['datetime'] : null;
            $cloud_cover = isset($feature['properties']['eo:cloud_cover']) ? floatval($feature['properties']['eo:cloud_cover']) : 100.0;
            if ($datetime === null) {
                // skip entries without datetime
                continue;
            }
            $features[] = array(
                'feature' => $feature,
                'datetime' => $datetime,
                'cloud_cover' => $cloud_cover
            );
        }

        if (empty($features)) {
            return false;
        }

        // Sort features by datetime descending (most recent first) - we'll pick the most recent that meets the cloud threshold
        usort($features, function($a, $b) {
            return strcmp($b['datetime'], $a['datetime']);
        });

        // Determine starting threshold:
        // - If user supplies a value, start EXACTLY at that value (clamped 0-100)
        // - If not provided, start at 0 (prefer perfectly clear images)
        $start_threshold = ($max_cloud_cover !== null)
            ? max(0, min(100, floatval($max_cloud_cover)))
            : 0.0;

        // Helper to find most recent feature with cloud_cover <= current threshold
        $find_most_recent_under_threshold = function($features, $thresh) {
            foreach ($features as $entry) {
                if ($entry['cloud_cover'] <= $thresh) {
                    return $entry['feature'];
                }
            }
            return null;
        };

        // Always try the exact starting threshold first, then relax upward in 10% increments
        $initial_match = $find_most_recent_under_threshold($features, $start_threshold);
        if ($initial_match !== null) {
            return array(
                'feature' => $initial_match,
                'selected_threshold' => $start_threshold
            );
        }

        // Relax by +10% steps until 100%
        for ($t = ceil($start_threshold / 10) * 10; $t <= 100; $t += 10) {
            $match = $find_most_recent_under_threshold($features, $t);
            if ($match !== null) {
                return array(
                    'feature' => $match,
                    'selected_threshold' => $t
                );
            }
        }

        // Fallback: most recent overall if no threshold produced a match
        return array(
            'feature' => $features[0]['feature'],
            'selected_threshold' => null
        );
    }
    
    /**
     * Generate and download satellite image using Copernicus process API
     */
    public function get_image($bbox, $lookback_days, $brightness = 0, $contrast = 0, $collection = 'sentinel-2-l2a', $max_cloud_cover = null) {
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }
        
        // Find best image using smart cloud cover selection
        $image_info = $this->search_images($bbox, $lookback_days, $collection, $max_cloud_cover);
        if (!$image_info) {
            return false;
        }
        
        // Calculate image dimensions based on bbox (maintain 10m resolution)
        $lat_diff = abs($bbox['lat_max'] - $bbox['lat_min']);
        $lon_diff = abs($bbox['lon_max'] - $bbox['lon_min']);
        
        // Account for latitude when calculating longitude distance
        $center_lat = ($bbox['lat_max'] + $bbox['lat_min']) / 2;
        $lat_correction = cos(deg2rad($center_lat));
        
        // Calculate proper distances in meters accounting for latitude
        $lat_distance_m = $lat_diff * 111000; // ~111km per degree latitude (constant)
        $lon_distance_m = $lon_diff * 111000 * $lat_correction; // longitude varies with latitude
        
        // Convert to pixels (10m resolution)
        $width = intval($lon_distance_m / 10);
        $height = intval($lat_distance_m / 10);
        
        // Limit maximum dimensions to prevent huge images (preserve aspect ratio)
        $max_dimension = 2048;
        if ($width > $max_dimension || $height > $max_dimension) {
            $scale = $max_dimension / max($width, $height);
            $width = intval($width * $scale);
            $height = intval($height * $scale);
        }
        
        // Ensure reasonable minimum dimensions (preserve aspect ratio)
        $min_dimension = 64;
        if ($width < $min_dimension && $height < $min_dimension) {
            $scale = $min_dimension / max($width, $height);
            $width = intval($width * $scale);
            $height = intval($height * $scale);
        }
        
        // Prepare evalscript with brightness/contrast adjustments
        $evalscript = $this->generate_evalscript($brightness, $contrast);
        
        // Determine if search_images returned a wrapper with feature + selected_threshold
        $selected_threshold = null;
        if (is_array($image_info) && isset($image_info['feature'])) {
            $feature = $image_info['feature'];
            $selected_threshold = isset($image_info['selected_threshold']) ? $image_info['selected_threshold'] : null;
        } else {
            $feature = $image_info; // legacy single-feature return (shouldn't happen now)
        }

        // Get the datetime from the selected image feature
        $datetime = isset($feature['properties']['datetime']) ? $feature['properties']['datetime'] : null;
        $cloud_cover = isset($feature['properties']['eo:cloud_cover']) ? floatval($feature['properties']['eo:cloud_cover']) : null;
        
        $request_data = array(
            'input' => array(
                'bounds' => array(
                    'bbox' => array(
                        floatval($bbox['lon_min']),
                        floatval($bbox['lat_min']),
                        floatval($bbox['lon_max']),
                        floatval($bbox['lat_max'])
                    ),
                    'properties' => array(
                        'crs' => 'http://www.opengis.net/def/crs/EPSG/0/4326'
                    )
                ),
                'data' => array(
                    array(
                        'type' => $collection,
                        'dataFilter' => array(
                            'timeRange' => array(
                                'from' => date('Y-m-d\TH:i:s\Z', strtotime($datetime . ' -1 hour')),
                                'to' => date('Y-m-d\TH:i:s\Z', strtotime($datetime . ' +1 hour'))
                            )
                        )
                    )
                )
            ),
            'output' => array(
                'width' => $width,
                'height' => $height,
                'responses' => array(
                    array(
                        'identifier' => 'default',
                        'format' => array(
                            'type' => 'image/jpeg'
                        )
                    )
                )
            ),
            'evalscript' => $evalscript
        );
        
        $response = wp_remote_post($this->base_url . '/api/v1/process', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'body' => json_encode($request_data),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Copernicus Sentinel Hub Process Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Copernicus Sentinel Hub API returned status: ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);

        // Return image bytes and metadata (datetime, cloud cover, selected threshold)
        return array(
            'image' => $body,
            'metadata' => array(
                'datetime' => $datetime,
                'cloud_cover' => $cloud_cover,
                'selected_threshold' => $selected_threshold
            )
        );
    }
    
    /**
     * Generate evalscript with brightness and contrast adjustments
     */
    private function generate_evalscript($brightness = 0, $contrast = 0) {
        // Clamp values to valid range
        $brightness = max(-2, min(2, floatval($brightness)));
        $contrast = max(-2, min(2, floatval($contrast)));
        
        // Convert to factors
        $brightness_factor = $brightness * 0.3; // Scale to reasonable range
        $contrast_factor = 1 + ($contrast * 0.5); // Scale to reasonable range
        
        $evalscript = <<<EVALSCRIPT
//VERSION=3

function setup() {
    return {
        input: ["B02", "B03", "B04"],
        output: { bands: 3 }
    };
}

function evaluatePixel(sample) {
    let r = sample.B04;
    let g = sample.B03;
    let b = sample.B02;
    
    // Apply brightness
    r = r + {$brightness_factor};
    g = g + {$brightness_factor};
    b = b + {$brightness_factor};
    
    // Apply contrast
    r = (r - 0.5) * {$contrast_factor} + 0.5;
    g = (g - 0.5) * {$contrast_factor} + 0.5;
    b = (b - 0.5) * {$contrast_factor} + 0.5;
    
    // Clamp values
    r = Math.max(0, Math.min(1, r));
    g = Math.max(0, Math.min(1, g));
    b = Math.max(0, Math.min(1, b));
    
    return [r * 3.5, g * 3.5, b * 3.5];
}
EVALSCRIPT;
        
        return $evalscript;
    }
}