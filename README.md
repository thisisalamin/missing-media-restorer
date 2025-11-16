# Missing Media Restorer for WordPress

## Overview

This project solves a large-scale missing media issue in WordPress installations. When thousands of media files (images, PDFs, videos) are referenced in the database but missing from the `wp-content/uploads` directory, the website shows broken links and 404 errors. This tool scans all attachment entries, detects missing files, and restores them into their correct year/month upload folders.

The system supports extremely large media libraries (3000+ missing files, 5GB+ data) and is designed to work safely without timeouts.

---

## Key Features

* **Attachment Scanner**: Reads all WordPress media entries and identifies physically missing files.
* **Exact Path Reconstruction**: Determines the original upload path for each missing file via metadata.
* **Batch-Based Restore Engine**:

  * Restores missing files in small batches.
  * Prevents PHP timeout, memory issues, and network timeout.
  * Suitable for heavy workloads and large file sizes.
* **Drag-and-Drop Local Backup Upload**: Allows uploading large sets of local files.
* **Auto-Matching by Filename**: Places restored files in their exact required folder.
* **Progress Tracking**: Tracks how many files are scanned, matched, restored, or failed.

---

## Workflow

### 1. Scan for Missing Files

The system scans all WordPress attachments using their metadata (`_wp_attached_file`) to detect missing physical files in the uploads folder.

### 2. Generate Missing File List

A list is created containing:

* File name
* Expected location (e.g., `wp-content/uploads/2025/02/`)
* Attachment ID
* Status

### 3. Upload Local Backup Files

All local media files can be uploaded at once using the drag-and-drop uploader.

### 4. Batch Restore

The restore engine processes files in controlled batches:

* Default batch size: **20–50 files per request**
* Each batch reconstructs the year/month folder
* Moves the matching file into the correct upload directory

This prevents server crashes due to large operations.

### 5. Final Verification

After restoration:

* The plugin re-checks each attachment
* Confirms physical file exists
* Reports any unmatched or failed files

---

## Why Batch Processing?

Because restoring thousands of files (possibly 5GB+) in one request will cause:

* PHP max execution time errors
* Memory exhaustion
* Timeout by Nginx/Apache
* Browser/network request timeout

Batching ensures the process is safe, stable, and resumable.

---

## Folder Structure

The tool restores files exactly according to their metadata path, for example:

```
/wp-content/uploads/2025/02/Biskek-EBRD_OCCO.pdf
```

Missing folder paths are auto-created if necessary.

---

## Requirements

* WordPress 5.0+
* PHP 7.4+ recommended
* Enough server disk space to restore missing files

---

## Notes

* No database modifications are made — only the missing physical files are restored.
* Supports thousands of files without performance issues.
* Safe for production websites.

---

## Pro Features

* **Smart Local Scan & Selective Upload**: Scan local directories for files that are missing on the server and upload only the matching files automatically. This prevents unnecessary uploads and saves bandwidth.

---

## Future Enhancements

* Resume incomplete restore sessions
* Export CSV of missing vs restored files
* Remote backup synchronization


---

## Author

Developed for automated restoration of large-scale missing media libraries in WordPress environments.
