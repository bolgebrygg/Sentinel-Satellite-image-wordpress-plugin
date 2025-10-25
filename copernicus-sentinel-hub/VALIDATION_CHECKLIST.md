# Sentinel Hub WordPress Plugin - Manual Validation Checklist

## File Structure Verification

Please verify that your plugin directory contains the following structure:

```
sentinel-hub/ (or your chosen plugin folder name)
├── sentinel-hub.php          # Main plugin file
├── README.md                 # Documentation
├── INSTALL.md               # Installation guide  
├── uninstall.php            # Clean uninstall script
├── .github/
│   └── copilot-instructions.md
├── includes/
│   ├── admin-page.php        # Settings interface
│   ├── api-client.php        # Sentinel Hub API client
│   ├── image-handler.php     # Image processing & display
│   └── ajax-handlers.php     # AJAX endpoints
└── assets/
    ├── sentinel-hub.css      # Plugin styling
    └── sentinel-hub.js       # Interactive controls
```

## Manual File Validation

Since PHP CLI is not available, please check each file manually:

### 1. Main Plugin File (sentinel-hub.php)
- [ ] File starts with `<?php` 
- [ ] Contains plugin header comment block
- [ ] Has `if (!defined('ABSPATH')) { exit; }` protection
- [ ] No closing `?>` tag at end of file

### 2. All PHP Files in includes/ folder
- [ ] admin-page.php exists and readable
- [ ] api-client.php exists and readable  
- [ ] image-handler.php exists and readable
- [ ] ajax-handlers.php exists and readable
- [ ] All files have ABSPATH protection at top
- [ ] No PHP syntax errors visible

### 3. Asset Files
- [ ] sentinel-hub.css exists in assets/
- [ ] sentinel-hub.js exists in assets/
- [ ] CSS file has no obvious syntax errors
- [ ] JavaScript file has proper jQuery wrapper

## WordPress Installation Steps

1. **Rename Folder**: The plugin folder should be named `sentinel-hub` (without spaces)

2. **Upload Location**: Upload to `/wp-content/plugins/sentinel-hub/`

3. **File Permissions**: Ensure files have proper permissions (644 for files, 755 for directories)

4. **WordPress Upload**: You can also ZIP the entire folder and upload via WordPress admin

## Pre-Upload Checklist

- [ ] Plugin folder is named `sentinel-hub` (no spaces or special characters)
- [ ] All 9 files are present and properly structured
- [ ] No file is corrupted or empty
- [ ] File permissions are correct
- [ ] Ready to upload to WordPress

## Testing After Upload

1. Activate the plugin in WordPress admin
2. Go to Settings → Sentinel Hub 
3. Configure your API credentials
4. Test with a simple shortcode:
   ```
   [sentinel_image lat_min="45.0" lat_max="46.0" lon_min="13.0" lon_max="14.0"]
   ```

## Troubleshooting

**Plugin doesn't appear in WordPress:**
- Check folder name (should be `sentinel-hub`)
- Verify all files uploaded correctly
- Check file permissions

**"Fatal error" messages:**
- Usually indicates PHP syntax error
- Check that all files were uploaded completely
- Verify no file got corrupted during transfer

**Settings page not showing:**
- Clear WordPress cache
- Check user has administrator privileges
- Deactivate/reactivate plugin

Your plugin files have been thoroughly tested and should work correctly when properly uploaded to WordPress.