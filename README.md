# Sentinel-Satellite-image-wordpress-plugin

This has been built entirey by vibe coding -- no beauty no creativity 


WordPress plugin that fetches recent Copernicus (Sentinel) imagery with smart cloud filtering, adjustable brightness/contrast, caching, and simple shortcodes. Enter Client ID/Secret, pick a bounding box, optionally set max_cloud_cover; plugin returns latest clear scene with metadata

WordPress post or page using two shortcodes. It authenticates with a Client ID and Secret, performs one catalog search per request across a user‑defined lookback window, then applies a progressive cloud‑cover selection strategy: start at the requested threshold (or 0% if none) and relax upward in 10% increments until the most recent acceptable scene is found. Retrieved imagery is processed via the Copernicus process API with an Evalscript that enables brightness and contrast adjustment. A lightweight caching layer stores image files plus JSON metadata (timestamp, cloud cover, threshold) inside a dedicated uploads subdirectory, pruning stale or excess items based on duration and count settings. The “simple” shortcode outputs a clean, responsive image with metadata caption; the “interactive” variant adds real‑time sliders and reset controls. All user‑facing strings are translation‑ready, and error conditions (missing credentials, invalid bounding box, retrieval failure) are handled gracefully. The README guides complete beginners through installation, credentials, troubleshooting, and practical examples. The design emphasizes minimal API calls, clear image provenance, predictable performance, and easy extensibility for future collections or visualization enhancements. Secure, maintainable, WordPress-native architecture overall foundation.

<img width="738" height="789" alt="msedge_UlGpZSJksm" src="https://github.com/user-attachments/assets/8a965228-1815-4395-91fb-c57c982f5e70" />


# Copernicus Sentinel Hub WordPress Plugin

Beginner‑friendly WordPress plugin for showing fresh Sentinel satellite imagery (Copernicus Dataspace) on any post or page with a simple shortcode. No coding, no cron jobs, no external libraries required.

## TL;DR (Fast Start)
1. Install the plugin (zip must contain a single folder named `copernicus-sentinel-hub`).
2. Activate it in WordPress.
3. Go to: Settings → Copernicus Sentinel Hub.
4. Paste your Copernicus Dataspace Client ID and Client Secret.
5. Press “Test API Connection”. Must show success.
6. Add this to a post: `[copernicus_simple_image lat_min="45.00" lat_max="45.05" lon_min="13.00" lon_max="13.05" lookback_days="14"]`
7. View the post. You should see a satellite image plus date & cloud cover.

If it fails, scroll to “Troubleshooting”.



## Features

- **Copernicus Dataspace Integration**: Direct integration with Copernicus Dataspace Sentinel Hub API
- **Smart Image Selection**: Configurable cloud cover threshold with preference for recent images. The plugin fetches the catalog once for the requested period and performs local filtering in 10% cloud-cover increments until a match is found (see details below)
- **Multiple Collections**: Support for Sentinel-2 L2A, L1C, and Sentinel-1 GRD collections
- **Caching System**: Intelligent caching system to minimize API calls (configurable duration, max 15 images)
- **Image Controls**: Real-time brightness and contrast adjustment (-2 to +2 range)
- **High Resolution**: Retrieves images at full available resolution (10-meter pixel maximum)
- **Easy Integration**: Simple shortcode system for embedding images in posts and pages

## Installation (Detailed – Absolute Beginner)
You have two ways to install: ZIP upload (easiest) or manual FTP.

### Option A: Upload a Zip (Recommended)
1. In your local computer, ensure the folder structure looks like:
    ```
    copernicus-sentinel-hub/
       copernicus-sentinel-hub.php
       uninstall.php
       includes/
       assets/
       README.md
    ```
2. Right–click the folder and compress/zip it. The zip MUST contain that folder at top level (not its contents directly).
3. In WordPress admin: Plugins → Add New → Upload Plugin.
4. Select the zip and click Install Now.
5. If WordPress says “Destination folder already exists”, delete the existing folder first via FTP or hosting file manager (`wp-content/plugins/copernicus-sentinel-hub/`) then retry.
6. Click Activate.

### Option B: Manual FTP
1. Extract the zip locally.
2. Upload the entire `copernicus-sentinel-hub` folder into `wp-content/plugins/`.
3. Go to WordPress: Plugins → Activate.

### After Activation
1. Go to Settings → Copernicus Sentinel Hub.
2. Enter Client ID & Client Secret.
3. (Optional) adjust cache duration & maximum cached images.
4. Click “Test API Connection”. You want a success message.
5. Add a shortcode to a page/post (see Usage below).

## Copernicus Dataspace Setup

### Getting Your API Credentials

1. Visit [Copernicus Dataspace](https://dataspace.copernicus.eu/)
2. Create an account or log in to your existing account
3. Go to your **User Dashboard**
4. Navigate to **API Access** or **Applications**
5. Create a new application or use existing credentials
6. Copy your **Client ID** and **Client Secret**

### Common Install Error: “Destination folder already exists”
Cause: The plugin folder already lives on the server from a prior install or partial upload.
Fix:
1. Deactivate the old version (if visible) and click Delete.
2. If Delete not possible, remove folder manually: `wp-content/plugins/copernicus-sentinel-hub/`.
3. Re-upload the zip.
This error is filesystem related, not a bug in the plugin code.

## API Endpoints

The plugin uses these Copernicus Dataspace endpoints:
- **Authentication**: `https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token`
- **Catalog Search**: `https://sh.dataspace.copernicus.eu/api/v1/catalog/1.0.0/search`
- **Image Processing**: `https://sh.dataspace.copernicus.eu/api/v1/process`

## Usage

There are TWO shortcodes. Start with the simple one.

### Simple Shortcode (Recommended First Test)
Lightweight output with automatic date & cloud cover.

```
[copernicus_simple_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="30"]
```

### Interactive Shortcode (Adds Sliders)
Brightness & contrast sliders, reset button, extra metadata.

```
[copernicus_sentinel_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="30"]
```

### More Examples

**Simple shortcode with native sizing:**
```
[copernicus_simple_image lat_min="58.9" lat_max="58.99" lon_min="5.7" lon_max="5.8" lookback_days="14" collection="sentinel-2-l2a" native_size="true"]
```

**Simple shortcode with cloud cover preference:**
```
[copernicus_simple_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="30" max_cloud_cover="40"]
```

**Interactive shortcode with custom styling:**
```
[copernicus_sentinel_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0" lookback_days="7" collection="sentinel-2-l1c" brightness="0.5" contrast="-0.2" width="600px"]
```

### Cloud Cover Threshold Examples (8 Scenarios)
Each example shows how `max_cloud_cover` guides image selection. The plugin tries the exact value first, then relaxes in +10% steps (e.g. 20 → 30 → 40 …) until a match is found.

1. Ultra-clear attempt (may fall back if no 0% image):
```
[copernicus_simple_image lat_min="45.00" lat_max="45.05" lon_min="13.00" lon_max="13.05" lookback_days="10" max_cloud_cover="0"]
```
Selection path: 0%, then 10%, 20%, ... until a recent image appears.

2. Practical low-cloud preference:
```
[copernicus_simple_image lat_min="40.60" lat_max="40.65" lon_min="-73.90" lon_max="-73.85" lookback_days="14" max_cloud_cover="20"]
```
Selection path: 20% → 30% → 40% ... stops at first match.
Beginner explanation: This grabs the most recent satellite picture (not a random one) of a small rectangle (about 5–6 km wide) inside the last 14 days (that’s what `lookback_days="14"` means: “search backward up to 14 days”). First it tries for ≤20% clouds. If no image in those 14 days meets that, it relaxes to 30%, then 40%, and so on until it finds the newest image that passes. The time window defines how far back we’re allowed to look; the cloud number defines how strict we are. Think of it like: time window = “how far back can we search?”, cloud % = “how picky about clouds?” Smaller % = clearer sky; bigger % = more clouds allowed.

3. Moderate tolerance (busy weather period):
```
[copernicus_simple_image lat_min="51.48" lat_max="51.53" lon_min="-0.20" lon_max="-0.15" lookback_days="7" max_cloud_cover="50"]
```
Selection path: 50% → 60% → … (Likely finds something fast.)

4. Interactive with threshold + enhancement:
```
[copernicus_sentinel_image lat_min="35.60" lat_max="35.66" lon_min="139.70" lon_max="139.76" lookback_days="30" max_cloud_cover="30" brightness="0.3" contrast="0.2"]
```
Selection path: 30% → 40% → 50% … then sliders apply client-side adjustment.

5. No threshold (auto escalate from perfectly clear):
```
[copernicus_simple_image lat_min="48.10" lat_max="48.15" lon_min="11.50" lon_max="11.55" lookback_days="21"]
```
Selection path: 0% → 10% → 20% → … until match. (Because `max_cloud_cover` omitted.)

6. High threshold for consistently cloudy region:
```
[copernicus_simple_image lat_min="-1.40" lat_max="-1.35" lon_min="-48.90" lon_max="-48.85" lookback_days="14" max_cloud_cover="70"]
```
Selection path: 70% → 80% → … (Quick acceptance of overcast imagery.)

7. Narrow box, short lookback, strict clouds (may return fairly recent clear shot or none):
```
[copernicus_simple_image lat_min="59.930" lat_max="59.950" lon_min="10.700" lon_max="10.720" lookback_days="5" max_cloud_cover="10"]
```
Selection path: 10% → 20% → …; small area + short window might need threshold relaxation.

8. Interactive, larger window for seasonal clarity search:
```
[copernicus_sentinel_image lat_min="37.70" lat_max="37.80" lon_min="-122.50" lon_max="-122.40" lookback_days="45" max_cloud_cover="25" brightness="0.4" contrast="-0.1" width="700px"]
```
Selection path: 25% → 30% → 40% …; long lookback increases chance of early match.

Tip: If an area returns a visibly cloudy image despite a low threshold, it means no clearer image existed in the period—either widen `lookback_days` or raise `max_cloud_cover`.

## Available Shortcodes (Parameter Reference)

### 1. Simple Image Shortcode: `[copernicus_simple_image]`

**Purpose:** Clean, lightweight satellite image display with metadata caption.

**Parameters:**
| Parameter | Description | Required | Default | Options |
|-----------|-------------|----------|---------|---------|
| `lat_min` | Minimum latitude | Yes | - | -90 to +90 |
| `lat_max` | Maximum latitude | Yes | - | -90 to +90 |
| `lon_min` | Minimum longitude | Yes | - | -180 to +180 |
| `lon_max` | Maximum longitude | Yes | - | -180 to +180 |
| `lookback_days` | Days to search for imagery | No | 30 | 1-365 |
| `collection` | Satellite collection | No | sentinel-2-l2a | See collections below |
| `brightness` | Brightness adjustment | No | 0 | -2 to +2 |
| `contrast` | Contrast adjustment | No | 0 | -2 to +2 |
| `width` | Maximum image width | No | 800 | Pixels (e.g., "600") |
| `alt` | Alt text for accessibility | No | "Satellite Image" | Any text |
| `native_size` | Use API's original dimensions | No | false | true/false |
| `max_cloud_cover` | Maximum acceptable cloud cover % | No | - | 0-100 (e.g., "40") |

### 2. Interactive Image Shortcode: `[copernicus_sentinel_image]`

**Purpose:** Full-featured display with interactive brightness/contrast controls.

**Parameters:**
| Parameter | Description | Required | Default | Options |
|-----------|-------------|----------|---------|---------|
| `lat_min` | Minimum latitude | Yes | - | -90 to +90 |
| `lat_max` | Maximum latitude | Yes | - | -90 to +90 |
| `lon_min` | Minimum longitude | Yes | - | -180 to +180 |
| `lon_max` | Maximum longitude | Yes | - | -180 to +180 |
| `lookback_days` | Days to search for imagery | No | 30 | 1-365 |
| `collection` | Satellite collection | No | sentinel-2-l2a | See collections below |
| `brightness` | Initial brightness | No | 0 | -2 to +2 |
| `contrast` | Initial contrast | No | 0 | -2 to +2 |
| `width` | Container max-width | No | 800px | CSS values |
| `height` | Container height | No | auto | CSS values |
| `class` | Additional CSS class | No | copernicus-sentinel-hub-image | Any CSS class |
| `max_cloud_cover` | Maximum acceptable cloud cover % | No | - | 0-100 (e.g., "40") |

### Available Collections

| Collection | Description | Best For |
|------------|-------------|----------|
| `sentinel-2-l2a` | Sentinel-2 Level 2A (atmospherically corrected) | Most accurate surface reflectance |
| `sentinel-2-l1c` | Sentinel-2 Level 1C (top-of-atmosphere) | Raw optical imagery |
| `sentinel-1-grd` | Sentinel-1 Ground Range Detected | Radar imagery, weather-independent |

## Example Locations (Copy & Paste)

### Simple Shortcode Examples

```
# New York City (clean display)
[copernicus_simple_image lat_min="40.4774" lat_max="40.9176" lon_min="-74.2591" lon_max="-73.7004" lookback_days="14"]

# London, UK (native size)
[copernicus_simple_image lat_min="51.2868" lat_max="51.6918" lon_min="-0.5103" lon_max="0.3340" collection="sentinel-2-l2a" native_size="true"]

# Tokyo, Japan (custom width)
[copernicus_simple_image lat_min="35.4122" lat_max="35.8963" lon_min="139.3092" lon_max="139.9127" lookback_days="7" width="600"]

# Norway Coastline (Sentinel-1 radar)
[copernicus_simple_image lat_min="58.9" lat_max="58.99" lon_min="5.7" lon_max="5.8" collection="sentinel-1-grd" lookback_days="14"]
```

### Interactive Shortcode Examples

```
# New York City (with controls)
[copernicus_sentinel_image lat_min="40.4774" lat_max="40.9176" lon_min="-74.2591" lon_max="-73.7004" lookback_days="14"]

# London, UK (enhanced brightness/contrast)
[copernicus_sentinel_image lat_min="51.2868" lat_max="51.6918" lon_min="-0.5103" lon_max="0.3340" collection="sentinel-2-l2a" brightness="0.3" contrast="-0.1"]

# Tokyo, Japan (compact display)
[copernicus_sentinel_image lat_min="35.4122" lat_max="35.8963" lon_min="139.3092" lon_max="139.9127" lookback_days="7" width="500px"]
```

## Features in Detail (Plain English)

### Two Display Options

1. **Simple Shortcode (`copernicus_simple_image`)**:
   - Clean, minimal HTML output
   - Automatic metadata display (date and cloud cover)
   - Mobile-responsive design
   - Native size option for pixel-perfect display
   - Faster loading and rendering

2. **Interactive Shortcode (`copernicus_sentinel_image`)**:
   - Real-time brightness/contrast controls
   - Reset functionality
   - Advanced styling and responsive design
   - Full metadata display
   - Theme-compatible styling

### Smart Cloud Cover Selection (How Your Image Is Chosen)

The plugin uses an improved image selection workflow that minimizes API requests and prefers recent, low-cloud images. Key points:

1. The plugin performs a single catalog search for the entire time window you request (the `lookback_days` period). All filtering is done locally on the returned features.
2. If you provide `max_cloud_cover`, the selection starts at that value (rounded down to the nearest 10%) and searches for the most recent image with cloud cover <= that threshold.
3. If no images meet the starting threshold, the cloud-cover threshold is relaxed in 10% increments (start +10, +20, ... up to 100%) and the most recent image meeting the first matching threshold is chosen.
4. If you do not provide `max_cloud_cover`, the algorithm starts at 0% (prefers perfectly clear images) and relaxes in 10% steps until it finds a match or reaches 100%.
5. If no images are available in the catalog for the period, the plugin will report no image available. If the catalog returns features without a usable datetime, those entries are ignored.

This approach reduces API calls (catalog accessed once per request) and gives deterministic, predictable results while preferring recency and low cloud cover.

Example behaviors:

- `max_cloud_cover="30"`: algorithm will try thresholds 30%, 40%, 50%, ... until it finds a recent image at or below that threshold.
- No `max_cloud_cover` supplied: algorithm will try 0%, 10%, 20%, ... preferring the clearest recent imagery first.

Practical example: If you request `max_cloud_cover="20"` but there are no images <=20% within the lookback window, the plugin will try 30%, then 40%, etc., and return the most recent image that satisfies the first threshold where matches exist.

### Smart Metadata Display

Both shortcodes automatically display:
- **Image Date**: Human-readable format (e.g., "12 Oct 2025, 11:14 UTC")
- **Cloud Coverage**: Percentage with one decimal precision (e.g., "68.6%")
- **Collection Info**: Shows which satellite collection was used
 - **Selection threshold**: A short explanatory caption is shown when applicable (e.g., "Chose most recent image within 30% cloud cover") — this helps explain why a slightly cloudier but much more recent image was chosen.

### Caching System (What Gets Stored & When It Cleans Up)

- **Automatic Caching**: Images are cached locally to reduce API calls
- **Metadata Storage**: Date and cloud cover cached alongside images
- **Configurable Duration**: Set cache duration from 1 to 168 hours  
- **Size Management**: Automatically manages cache size (max 15 images by default)
- **Smart Cleanup**: Old files are automatically removed

### Real-time Image Controls (Interactive Shortcode)

Interactive sliders for image enhancement:
- **Brightness**: -2 to +2 range with live preview
- **Contrast**: -2 to +2 range with live preview
- **Reset Button**: Quick reset to original values
- **Mobile-Optimized**: Touch-friendly controls on all devices

### Responsive Design

- **Mobile-First**: Optimized for all screen sizes
- **Theme Compatibility**: CSS designed to work with any WordPress theme
- **Aspect Ratio Preservation**: Images maintain correct geographic proportions
- **No Distortion**: Latitude-corrected dimensions prevent stretching

## Technical Details (Under the Hood)

### Authentication

Uses OAuth 2.0 Client Credentials flow:
- Client ID and Secret from Copernicus Dataspace
- Automatic token refresh and caching
- Secure credential storage

### Image Processing

- **Resolution**: Full 10-meter pixel resolution from Sentinel satellites
- **Format**: JPEG format optimized for web display
- **Processing**: Uses evalscript for brightness/contrast adjustments
- **Dimensions**: Automatically calculated from bounding box

### WordPress Integration

- **Shortcodes**: Custom shortcode system
- **Settings API**: WordPress-compatible configuration
- **AJAX**: Asynchronous requests for dynamic features
- **Caching**: WordPress uploads directory integration

## Troubleshooting (Fixes That Actually Work)

### Common Issues

**"API credentials not configured"**
- Solution: Configure Client ID and Secret in plugin settings
- Test connection using the "Test API Connection" button

**"Invalid coordinates provided"**
- Solution: Check coordinate ranges and bounding box format
- Ensure lat_min < lat_max and lon_min < lon_max
- Maximum bounding box size is 1 degree

**"Unable to retrieve satellite image"**
- Solution: Try increasing lookback_days parameter
- Check if imagery is available for your area and time period
- Verify your Copernicus Dataspace account status

**Images not loading**
- Solution: Check API credentials and test connection
- Verify internet connectivity
- Check WordPress debug logs for API errors

### Performance Optimization (Make It Snappy)

1. **Reasonable lookback periods**: Shorter periods = faster searches
2. **Cache settings**: Balance duration with storage space
3. **Coordinate precision**: Use appropriate decimal places
4. **Image dimensions**: Smaller images load faster

### Debug Information (Where Errors Go)

Enable WordPress debug logging:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for Copernicus API errors.

## Requirements (Minimum Stuff You Need)

- WordPress 5.0 or higher
- PHP 7.4 or higher
- cURL extension enabled
- Active Copernicus Dataspace account
- Write permissions in WordPress uploads directory

## Differences from Standard Sentinel Hub (Why This Plugin Exists)

This plugin is specifically designed for **Copernicus Dataspace**, which differs from commercial Sentinel Hub:

- **Free Access**: Copernicus Dataspace provides free access to Copernicus data
- **Authentication**: Uses Client ID/Secret instead of OAuth token/secret
- **Endpoints**: Different API base URLs and authentication endpoints
- **Collections**: Specific collection naming conventions
- **Processing**: Same evalscript functionality with Copernicus infrastructure

## Support (Where To Look Before Asking)

For issues related to:
- **Plugin functionality**: Check WordPress debug logs
- **API errors**: Verify Copernicus Dataspace account and credentials
- **Image quality**: Adjust collection, time period, or image controls
- **Performance**: Optimize cache settings and coordinate precision

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.2.0 (October 2025)
- Exact cloud cover threshold honored first before gradual 10% relax
- Unified cache invalidation across AJAX and shortcode logic
- Added i18n (translation-ready) for all user-facing strings
- Added safer file write error handling (logs failures instead of silent)
- Normalized width/height handling to prevent duplicate `pxpx`
- Improved README for beginners with clearer error recovery steps

### Version 1.1.1 (October 2025)
- Documentation: clarified admin UI options and testing steps. Added note that the plugin uses the WordPress HTTP API for network calls (no PHP CLI required) and described the Test API Connection and Clear Cache actions available in the settings page.

### Version 1.1.0 (October 2025)
- **New Simple Shortcode**: Added `copernicus_simple_image` for clean, lightweight display
- **Metadata Display**: Automatic date and cloud cover information on all images
- **Responsive Design**: Complete mobile optimization for all shortcodes
- **Aspect Ratio Fix**: Corrected latitude-based dimension calculations to prevent distortion
- **Native Size Option**: Added `native_size="true"` parameter for pixel-perfect display
- **Theme Compatibility**: Enhanced CSS to prevent WordPress theme interference
- **Admin Interface**: Fixed Test API Connection button functionality
- **Improved Caching**: Added metadata storage alongside cached images
 - **Cloud-cover selection update**: Catalog is now fetched once for the requested period and filtering is done locally; cloud-cover thresholds are relaxed in 10% increments (e.g., start at user-specified threshold or 0%, then 10%, 20%, … up to 100%) to find the most recent acceptable image.

### Version 1.0.0
- Initial release for Copernicus Dataspace
- Client ID/Secret authentication
- Multiple collection support
- Copernicus-specific API integration
- Real-time image controls
- Intelligent caching system

## Credits

- Built for WordPress
- Integrates with [Copernicus Dataspace](https://dataspace.copernicus.eu/) 
- Uses Sentinel satellite imagery from the European Space Agency
- Powered by Copernicus Dataspace Sentinel Hub API
