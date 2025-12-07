=== Missing Media Restorer ===
Contributors: REVENTOR, alaminit
Tags: media, restore, missing files, backup, upload, library, images, pdf, videos
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.0.3
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Restore missing WordPress media files in safe batches to prevent server timeouts and overload.

== Description ==

Missing Media Restorer solves the critical problem of broken media links in WordPress installations. When thousands of media files (images, PDFs, videos) are referenced in the database but missing from the `wp-content/uploads` directory, your website shows broken links and 404 errors.

This powerful tool scans all attachment entries, detects missing files, and restores them into their correct year/month upload folders with a batch-based system designed for large-scale media libraries (3000+ missing files, 5GB+ data).

== Key Features ==

* **Attachment Scanner**: Reads all WordPress media entries and identifies physically missing files
* **Exact Path Reconstruction**: Determines the original upload path for each missing file via metadata
* **Batch-Based Restore Engine**: Restores missing files in small batches to prevent PHP timeout, memory issues, and network timeout
* **Drag-and-Drop Local Backup Upload**: Allows uploading large sets of local files
* **Auto-Matching by Filename**: Places restored files in their exact required folder
* **Progress Tracking**: Tracks how many files are scanned, matched, restored, or failed
* **Thumbnail Support**: Automatically restores missing thumbnail variations and image sizes
* **No Database Modifications**: Only restores missing physical files - completely safe for production sites

== How It Works ==

=== Step 1: Scan for Missing Files ===

The system scans all WordPress attachments using their metadata (`_wp_attached_file`) to detect missing physical files in the uploads folder. It creates a comprehensive list containing:
* File name
* Expected location (e.g., `wp-content/uploads/2025/02/`)
* Attachment ID
* Status

=== Step 2: Upload Local Backup Files ===

All local media files can be uploaded at once using the drag-and-drop uploader or by placing files directly in the temporary folder via FTP at `/wp-content/uploads/mmr-temp/`.

=== Step 3: Batch Restore ===

The restore engine processes files in controlled batches:
* Default batch size: 20-50 files per request
* Each batch reconstructs the year/month folder
* Moves the matching file into the correct upload directory

This prevents server crashes due to large operations.

=== Step 4: Final Verification ===

After restoration:
* The plugin re-checks each attachment
* Confirms physical file exists
* Reports any unmatched or failed files

== Why Batch Processing? ==

Restoring thousands of files (possibly 5GB+) in one request will cause:
* PHP max execution time errors
* Memory exhaustion
* Timeout by Nginx/Apache
* Browser/network request timeout

Batching ensures the process is safe, stable, and resumable.

== Installation ==

1. Download the plugin as a ZIP file
2. In your WordPress admin, go to Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate the plugin
4. Navigate to Media → Media Restorer to begin the restoration process

== Frequently Asked Questions ==

= Does this plugin modify my database? =

No. Missing Media Restorer only restores missing physical files to their correct locations. It does not make any database modifications, making it completely safe for production websites.

= What file types are supported? =

The plugin supports all common WordPress media types including images (JPG, PNG, GIF, WebP), PDFs, documents (DOC, DOCX), videos (MP4), audio files (MP3), and archives (ZIP).

= Can I use this on a large site with thousands of missing files? =

Yes. The batch processing system is specifically designed to handle large-scale media libraries (3000+ missing files, 5GB+ data) without causing server timeouts or memory issues.

= What happens if the restoration process is interrupted? =

The plugin processes files in small batches, so if the process is interrupted, you can simply resume from where it left off. Already processed files will not be restored again.

= Do I need technical knowledge to use this plugin? =

No. The interface is designed to be user-friendly with clear step-by-step instructions. However, you should have access to your WordPress media backup files.

== Screenshots ==

1. Main dashboard with 4-step workflow
2. Scan results showing missing and existing files
3. Upload interface with drag-and-drop support
4. Batch restore progress tracking
5. Final verification and results

== Changelog ==

= 1.0.3 =
* Improved batch processing performance
* Added support for additional file types
* Enhanced error handling and reporting
* Fixed thumbnail regeneration issues

= 1.0.2 =
* Added progress indicators for long-running operations
* Improved memory usage for large media libraries
* Enhanced FTP upload detection
* Fixed compatibility with WordPress 6.8+

= 1.0.1 =
* Initial release with core functionality
* Batch-based restoration system
* Drag-and-drop upload interface
* Thumbnail and metadata regeneration

== Upgrade to Pro ==

The Pro version includes additional features:

* **Smart Local Scan & Selective Upload**: Scan local directories for files that are missing on the server and upload only the matching files automatically
* **Resume Incomplete Sessions**: Resume restoration from where you left off
* **Export CSV Reports**: Export detailed reports of missing vs restored files
* **Remote Backup Synchronization**: Sync with remote backup services
* **Priority Support**: Get help from our expert support team

== Future Enhancements ==

* Resume incomplete restore sessions
* Export CSV of missing vs restored files
* Remote backup synchronization
* Advanced duplicate detection
* Scheduled automatic scans

== Developer Information ==

== Technologies Used ==

* **Tailwind CSS v4.1.17**: Latest version for modern CSS utilities
* **PostCSS**: For processing CSS with Tailwind
* **Autoprefixer**: For CSS vendor prefixes

== File Structure ==

* `missing-media-restorer.php`: Main plugin file
* `assets/css/src/tailwind.css`: Source CSS with Tailwind import and custom styles
* `assets/css/mmr-admin.css`: Compiled CSS (generated)
* `assets/js/mmr-admin.js`: JavaScript for admin interface
* `tailwind.config.js`: Tailwind configuration
* `postcss.config.js`: PostCSS configuration
* `package.json`: Node.js dependencies and scripts

== Development Setup ==

1. Clone or download the plugin to your WordPress plugins directory
2. Navigate to the plugin directory and install dependencies:
   ```bash
   npm install
   ```
3. Build the CSS:
   ```bash
   npm run prod-css
   ```
4. For development with watch mode:
   ```bash
   npm run build-css
   ```

== Security ==

* All AJAX requests include nonce verification
* File uploads are restricted to allowed file types
* Maximum file size limit of 50MB per file
- User capability checks (manage_options required)
* Sanitized filenames and paths

== License ==

This plugin is licensed under the GPL v2 or later.

== Author ==

Developed by REVENTOR for automated restoration of large-scale missing media libraries in WordPress environments.

Author URI: https://reventor.eu
