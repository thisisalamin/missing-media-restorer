# Missing Media Restorer Pro

A comprehensive WordPress plugin for finding and restoring missing media files from your local directories. The Pro version includes advanced scanning algorithms, batch processing, analytics, and priority support features.

## Features

### Core Features (Free Version)
- Basic media scanning and restoration
- Simple file matching
- Basic upload functionality
- Admin interface integration

### Pro Features
- **Smart Local Directory Scanner**
  - Recursive and non-recursive scanning options
  - Fuzzy matching using Levenshtein distance algorithm
  - File size and extension filtering
  - Deep scan with custom filters
  - Progress tracking and performance metrics

- **Advanced Bulk Uploader**
  - Batch upload with configurable batch sizes
  - Chunked upload support for large files
  - Selective upload functionality
  - File validation and conflict resolution
  - Automatic thumbnail generation for images

- **Sophisticated Matching Algorithms**
  - Multiple matching algorithms (exact, fuzzy, metadata, size, hash, pattern)
  - Weighted scoring system for matches
  - Batch matching for large file sets
  - Match verification system
  - Confidence scoring and categorization

- **Progress Analytics & Reporting**
  - Comprehensive analytics tracking
  - Database storage for operation data
  - Detailed reporting with multiple formats
  - Performance metrics and usage statistics
  - Export functionality for reports

- **Priority Support Infrastructure**
  - Ticketing system with categories and priorities
  - Knowledge base with search functionality
  - Diagnostic tools for troubleshooting
  - System information collection
  - Support dashboard with ticket management

- **Performance Optimization**
  - Large-scale operation optimization
  - Memory management and batch processing
  - Caching system for improved performance
  - Automatic cleanup and maintenance
  - Server load monitoring

## Installation

1. Download the plugin ZIP file
2. Upload to your WordPress plugins directory (`/wp-content/plugins/`)
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under **Media > Missing Media Restorer**

## Configuration

### Basic Settings
- **Default Scan Directory**: Set the default directory to scan for missing media
- **File Extensions**: Specify which file extensions to include in scans
- **Max File Size**: Set maximum file size for processing
- **Auto-Restore**: Enable automatic restoration of found files

### Pro Settings
- **Scanning Options**: Configure recursive scanning, fuzzy matching thresholds
- **Upload Settings**: Set batch sizes, chunk sizes, and validation rules
- **Matching Algorithms**: Select and configure matching algorithms
- **Performance**: Adjust memory limits, batch sizes, and caching options

## Usage

### Basic Usage
1. Navigate to **Media > Missing Media Restorer**
2. Click "Scan for Missing Media"
3. Review the scan results
4. Select files to restore and click "Restore Selected"

### Advanced Usage (Pro)
1. **Smart Scanning**
   - Choose scan type (recursive/non-recursive)
   - Set fuzzy matching threshold
   - Apply filters for file size and extensions
   - Start deep scan for comprehensive results

2. **Batch Upload**
   - Drag and drop files to upload area
   - Configure batch settings
   - Monitor upload progress
   - Review upload results

3. **Advanced Matching**
   - Select matching algorithms
   - Adjust confidence thresholds
   - Review match scores
   - Verify and apply matches

4. **Analytics**
   - View operation history
   - Generate performance reports
   - Export data in various formats
   - Monitor system metrics

## API Reference

### Scanner API

```php
// Get scanner instance
$scanner = new MMR_Pro_Scanner();

// Scan directory
$results = $scanner->scan_directory('/path/to/directory', array(
    'recursive' => true,
    'fuzzy_threshold' => 80,
    'file_extensions' => array('jpg', 'png', 'gif'),
    'max_file_size' => 10485760, // 10MB
));

// Get scan progress
$progress = $scanner->get_scan_progress();
```

### Uploader API

```php
// Get uploader instance
$uploader = new MMR_Pro_Uploader();

// Upload files
$results = $uploader->upload_files($_FILES, array(
    'batch_size' => 10,
    'chunk_size' => 1048576, // 1MB
    'validate_files' => true,
    'generate_thumbnails' => true,
));

// Get upload progress
$progress = $uploader->get_upload_progress();
```

### Matcher API

```php
// Get matcher instance
$matcher = new MMR_Pro_Matcher();

// Match files
$results = $matcher->match_files($missing_files, $available_files, array(
    'algorithms' => array('exact', 'fuzzy', 'metadata'),
    'weights' => array(
        'exact' => 100,
        'fuzzy' => 80,
        'metadata' => 60,
    ),
    'confidence_threshold' => 75,
));
```

### Analytics API

```php
// Get analytics instance
$analytics = new MMR_Pro_Analytics();

// Track operation
$analytics->track_operation('scan', array(
    'files_found' => 150,
    'execution_time' => 45.2,
    'memory_usage' => 52428800,
));

// Get report
$report = $analytics->generate_report('monthly', array(
    'format' => 'json',
    'include_charts' => true,
));
```

## Hooks and Filters

### Actions
- `mmr_pro_before_scan`: Fired before scanning starts
- `mmr_pro_after_scan`: Fired after scanning completes
- `mmr_pro_before_upload`: Fired before upload starts
- `mmr_pro_after_upload`: Fired after upload completes
- `mmr_pro_before_match`: Fired before matching starts
- `mmr_pro_after_match`: Fired after matching completes

### Filters
- `mmr_pro_scan_options`: Filter scan options
- `mmr_pro_upload_options`: Filter upload options
- `mmr_pro_match_options`: Filter match options
- `mmr_pro_file_extensions`: Filter allowed file extensions
- `mmr_pro_max_file_size`: Filter maximum file size

Example:

```php
// Add custom file extensions
add_filter('mmr_pro_file_extensions', function($extensions) {
    return array_merge($extensions, array('svg', 'webp'));
});

// Modify scan options
add_filter('mmr_pro_scan_options', function($options) {
    $options['fuzzy_threshold'] = 90;
    return $options;
});
```

## Performance Optimization

### Large-Scale Operations
- Use batch processing for large file sets
- Configure appropriate memory limits
- Enable caching for repeated operations
- Monitor server load during operations

### Recommended Settings
- **Memory Limit**: 512M or higher for large operations
- **Max Execution Time**: 300 seconds (5 minutes)
- **Batch Size**: 50-100 files per batch
- **Chunk Size**: 1MB for file uploads

### Caching
- Enable file system caching for scan results
- Configure cache TTL based on usage patterns
- Schedule regular cache cleanup
- Monitor cache size and performance

## Troubleshooting

### Common Issues

**Memory Exhausted**
- Increase PHP memory limit
- Reduce batch size
- Enable chunked uploads
- Clear temporary files

**Timeout Errors**
- Increase max execution time
- Use batch processing
- Optimize server performance
- Check server load

**File Not Found**
- Verify file paths
- Check file permissions
- Ensure files exist in scan directory
- Validate file extensions

**Matching Issues**
- Adjust fuzzy matching threshold
- Check file naming patterns
- Verify metadata consistency
- Try different algorithms

### Debug Mode
Enable debug mode to get detailed error information:

```php
// In wp-config.php
define('MMR_PRO_DEBUG', true);
define('MMR_PRO_LOG_LEVEL', 'debug');
```

### Support Tickets
Create support tickets through the plugin interface:
1. Navigate to **Media > Missing Media Restorer > Support**
2. Click "Create New Ticket"
3. Fill in ticket details
4. Attach system information
5. Submit ticket

## System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Memory: 128MB minimum, 512MB recommended
- Disk Space: 100MB minimum for plugin files

## Changelog

### Version 1.0.0
- Initial release
- Core scanning and restoration functionality
- Pro features implementation
- Performance optimization
- Support infrastructure

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support, please:
1. Check the documentation and FAQ
2. Search the knowledge base
3. Create a support ticket (Pro version)
4. Visit the plugin forums

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Credits

Developed by [Your Company Name]
Lead Developer: [Developer Name]
Quality Assurance: [QA Team]
Support Team: [Support Team]
