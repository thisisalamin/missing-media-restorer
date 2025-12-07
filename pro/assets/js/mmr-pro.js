/**
 * Missing Media Restorer Pro - Enhanced JavaScript Features
 */

(function($) {
    'use strict';

    // Global variables for pro features
    window.MMR_Pro = {
        scanner: null,
        uploader: null,
        matcher: null,
        analytics: null,
        support: null,
        currentSession: null,
        progressInterval: null,
        chunkSize: 1024 * 1024, // 1MB chunks
    };

    // Initialize all pro features
    $(document).ready(function() {
        // Initialize common modules
        initProgressTracking();
        initChunkedUpload();
        initRealTimeUpdates();

        // Initialize page-specific modules based on current page
        initializePageSpecificModules();
    });

    // Initialize modules based on current page
    function initializePageSpecificModules() {
        // Get current page from body class or URL
        const bodyClasses = $('body').attr('class') || '';

        if (bodyClasses.includes('mmr-pro-scanner')) {
            initProScanner();
        } else if (bodyClasses.includes('mmr-pro-uploader')) {
            initProUploader();
        } else if (bodyClasses.includes('mmr-pro-matcher')) {
            initProMatcher();
        } else if (bodyClasses.includes('mmr-pro-analytics')) {
            initProAnalytics();
        } else if (bodyClasses.includes('mmr-pro-support')) {
            initProSupport();
        } else {
            // Fallback: try to detect based on container elements
            if ($('.mmr-pro-scanner-container').length) {
                initProScanner();
            }
            if ($('.mmr-pro-uploader-container').length) {
                initProUploader();
            }
            if ($('.mmr-pro-matcher-container').length) {
                initProMatcher();
            }
            if ($('.mmr-pro-analytics-container').length) {
                initProAnalytics();
            }
            if ($('.mmr-pro-support-container').length) {
                initProSupport();
            }
        }
    }

    // Pro Scanner Module
    function initProScanner() {
        MMR_Pro.scanner = {
            init: function() {
                this.bindEvents();
                this.setupAdvancedOptions();
            },

            bindEvents: function() {
                $('#mmr-pro-scan-button').on('click', this.performScan.bind(this));
                $('#mmr-pro-deep-scan-button').on('click', this.performDeepScan.bind(this));
                $('#mmr-pro-fuzzy-match-button').on('click', this.performFuzzyMatch.bind(this));
            },

            setupAdvancedOptions: function() {
                // Initialize advanced scan options
                $('.mmr-pro-scan-option').each(function() {
                    const $option = $(this);
                    const savedValue = localStorage.getItem('mmr_pro_scan_' + $option.data('option'));
                    if (savedValue) {
                        $option.prop('checked', savedValue === 'true');
                    }

                    $option.on('change', function() {
                        localStorage.setItem('mmr_pro_scan_' + $option.data('option'), $(this).prop('checked'));
                    });
                });
            },

            performScan: function() {
                const $button = $('#mmr-pro-scan-button');
                const directory = $('#mmr-pro-scan-directory').val().trim();

                if (!directory) {
                    this.showNotification('Please enter a directory path to scan.', 'error');
                    return;
                }

                const options = {
                    recursive: $('#mmr-pro-recursive-scan').prop('checked'),
                    fuzzy_matching: $('#mmr-pro-fuzzy-matching').prop('checked'),
                    file_size_limit: parseInt($('#mmr-pro-file-size-limit').val()) * 1024 * 1024,
                };

                this.showScanProgress($button);
                this.executeScan(directory, options);
            },

            performDeepScan: function() {
                const $button = $('#mmr-pro-deep-scan-button');
                const directory = $('#mmr-pro-deep-scan-directory').val().trim();

                if (!directory) {
                    this.showNotification('Please enter a directory path for deep scan.', 'error');
                    return;
                }

                const options = {
                    recursive: true,
                    fuzzy_matching: true,
                    file_size_limit: parseInt($('#mmr-pro-deep-file-size-limit').val()) * 1024 * 1024,
                    include_extensions: $('#mmr-pro-include-extensions').val(),
                    exclude_patterns: $('#mmr-pro-exclude-patterns').val(),
                };

                this.showScanProgress($button);
                this.executeDeepScan(directory, options);
            },

            performFuzzyMatch: function() {
                const scannedFiles = this.getScannedFiles();
                if (!scannedFiles || scannedFiles.length === 0) {
                    this.showNotification('No scanned files available for fuzzy matching.', 'error');
                    return;
                }

                const threshold = parseFloat($('#mmr-pro-fuzzy-threshold').val()) || 0.7;
                this.executeFuzzyMatch(scannedFiles, threshold);
            },

            executeScan: function(directory, options) {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_scan_local_directory',
                        directory_path: directory,
                        ...options,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayScanResults(response.data);
                            this.trackScanProgress(response.data);
                        } else {
                            this.showNotification('Scan failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during scanning.', 'error');
                    }.bind(this),
                    complete: function() {
                        this.hideScanProgress();
                    }.bind(this)
                });
            },

            executeDeepScan: function(directory, options) {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_deep_scan',
                        directory_path: directory,
                        ...options,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayDeepScanResults(response.data);
                            this.trackScanProgress(response.data);
                        } else {
                            this.showNotification('Deep scan failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during deep scan.', 'error');
                    }.bind(this),
                    complete: function() {
                        this.hideScanProgress();
                    }.bind(this)
                });
            },

            executeFuzzyMatch: function(scannedFiles, threshold) {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_fuzzy_match',
                        scanned_files: JSON.stringify(scannedFiles),
                        similarity_threshold: threshold,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayFuzzyMatchResults(response.data);
                        } else {
                            this.showNotification('Fuzzy matching failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during fuzzy matching.', 'error');
                    }.bind(this)
                });
            },

            displayScanResults: function(data) {
                const $container = $('#mmr-pro-scan-results');
                let html = this.generateScanResultsHTML(data);
                $container.html(html);
                this.bindResultEvents();
            },

            displayDeepScanResults: function(data) {
                const $container = $('#mmr-pro-deep-scan-results');
                let html = this.generateDeepScanResultsHTML(data);
                $container.html(html);
                this.bindResultEvents();
            },

            displayFuzzyMatchResults: function(data) {
                const $container = $('#mmr-pro-fuzzy-match-results');
                let html = this.generateFuzzyMatchResultsHTML(data);
                $container.html(html);
                this.bindMatchEvents();
            },

            generateScanResultsHTML: function(data) {
                let html = '<div class="mmr-pro-scan-results">';
                html += '<h3>Scan Results</h3>';
                html += '<div class="mmr-pro-scan-stats">';
                html += '<div class="stat-item"><span class="stat-label">Files Scanned:</span> <span class="stat-value">' + data.scanned_files + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">Matching Files:</span> <span class="stat-value">' + data.matching_files.length + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">Scan Size:</span> <span class="stat-value">' + this.formatBytes(data.scan_size) + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">Scan Time:</span> <span class="stat-value">' + data.scan_time + 's</span></div>';
                html += '</div>';

                if (data.matching_files.length > 0) {
                    html += '<div class="mmr-pro-matching-files">';
                    html += '<h4>Matching Files</h4>';
                    html += '<div class="file-grid">';

                    data.matching_files.forEach(function(file) {
                        html += this.generateFileCard(file);
                    }.bind(this));

                    html += '</div>';
                    html += '<div class="mmr-pro-actions">';
                    html += '<button class="button button-primary mmr-pro-upload-matching">Upload Matching Files</button>';
                    html += '<button class="button mmr-pro-selective-upload">Selective Upload</button>';
                    html += '</div>';
                    html += '</div>';
                }

                if (data.errors.length > 0) {
                    html += '<div class="mmr-pro-errors">';
                    html += '<h4>Errors</h4>';
                    html += '<ul class="error-list">';
                    data.errors.forEach(function(error) {
                        html += '<li class="error-item">' + error + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }

                html += '</div>';
                return html;
            },

            generateFileCard: function(file) {
                let html = '<div class="file-card" data-file="' + encodeURIComponent(JSON.stringify(file)) + '">';
                html += '<div class="file-icon"><span class="dashicons dashicons-media-default"></span></div>';
                html += '<div class="file-info">';
                html += '<h5 class="file-name">' + file.filename + '</h5>';
                html += '<p class="file-path">' + file.local_path + '</p>';
                html += '<div class="file-meta">';
                html += '<span class="file-size">' + this.formatBytes(file.size) + '</span>';
                html += '<span class="file-modified">' + new Date(file.modified * 1000).toLocaleDateString() + '</span>';
                html += '</div>';
                html += '</div>';
                html += '<div class="file-actions">';
                html += '<button class="button button-small mmr-pro-upload-single">Upload</button>';
                html += '<button class="button button-small mmr-pro-preview-file">Preview</button>';
                html += '</div>';
                html += '</div>';
                return html;
            },

            showScanProgress: function($button) {
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Scanning...');
                $('.mmr-pro-scan-progress').show();
            },

            hideScanProgress: function() {
                $('#mmr-pro-scan-button, #mmr-pro-deep-scan-button').prop('disabled', false).text('Scan');
                $('.mmr-pro-scan-progress').hide();
            },

            showNotification: function(message, type) {
                const notification = $('<div class="mmr-pro-notification mmr-pro-' + type + '">' + message + '</div>');
                $('body').append(notification);

                setTimeout(function() {
                    notification.fadeOut(function() {
                        notification.remove();
                    });
                }, 3000);
            },

            formatBytes: function(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            },

            getScannedFiles: function() {
                // Get scanned files from current results
                const files = [];
                $('.file-card').each(function() {
                    try {
                        const fileData = JSON.parse(decodeURIComponent($(this).data('file')));
                        files.push(fileData);
                    } catch (e) {
                        console.error('Error parsing file data:', e);
                    }
                });
                return files;
            },

            bindResultEvents: function() {
                $('.mmr-pro-upload-single').on('click', function() {
                    const fileData = JSON.parse(decodeURIComponent($(this).closest('.file-card').data('file')));
                    MMR_Pro.uploader.uploadSingleFile(fileData);
                });

                $('.mmr-pro-upload-matching').on('click', function() {
                    const files = this.getScannedFiles();
                    MMR_Pro.uploader.uploadMultipleFiles(files);
                }.bind(this));

                $('.mmr-pro-selective-upload').on('click', function() {
                    this.showSelectiveUploadModal();
                }.bind(this));

                $('.mmr-pro-preview-file').on('click', function() {
                    const fileData = JSON.parse(decodeURIComponent($(this).closest('.file-card').data('file')));
                    this.previewFile(fileData);
                }.bind(this));
            },

            bindMatchEvents: function() {
                $('.mmr-pro-accept-match').on('click', function() {
                    const matchData = $(this).data('match');
                    MMR_Pro.matcher.acceptMatch(matchData);
                });

                $('.mmr-pro-reject-match').on('click', function() {
                    const matchData = $(this).data('match');
                    MMR_Pro.matcher.rejectMatch(matchData);
                });
            },

            trackScanProgress: function(data) {
                const sessionId = 'scan_' + Date.now();
                MMR_Pro.currentSession = sessionId;

                MMR_Pro.analytics.trackProgress(sessionId, 'scan', {
                    files_scanned: data.scanned_files,
                    matching_files: data.matching_files.length,
                    scan_size: data.scan_size,
                    scan_time: data.scan_time
                });
            }
        };

        MMR_Pro.scanner.init();
    }

    // Pro Uploader Module
    function initProUploader() {
        MMR_Pro.uploader = {
            init: function() {
                this.bindEvents();
                this.setupDropZone();
                this.setupProgressBars();
            },

            bindEvents: function() {
                $('#mmr-pro-upload-files').on('change', this.handleFileSelect.bind(this));
                $('#mmr-pro-bulk-upload-button').on('click', this.performBulkUpload.bind(this));
                $('#mmr-pro-chunked-upload-button').on('click', this.enableChunkedUpload.bind(this));
            },

            setupDropZone: function() {
                const $dropZone = $('#mmr-pro-drop-zone');

                $dropZone.on('dragover', function(e) {
                    e.preventDefault();
                    $dropZone.addClass('drag-over');
                });

                $dropZone.on('dragleave', function(e) {
                    e.preventDefault();
                    $dropZone.removeClass('drag-over');
                });

                $dropZone.on('drop', function(e) {
                    e.preventDefault();
                    $dropZone.removeClass('drag-over');

                    const files = e.originalEvent.dataTransfer.files;
                    this.handleFiles(files);
                }.bind(this));
            },

            setupProgressBars: function() {
                // Initialize progress bars with animations
                $('.mmr-pro-progress-bar').each(function() {
                    const $bar = $(this);
                    const $fill = $bar.find('.progress-fill');
                    const $text = $bar.find('.progress-text');

                    $bar.data('original-width', $fill.width());
                });
            },

            handleFileSelect: function(e) {
                const files = e.target.files;
                this.handleFiles(files);
            },

            handleFiles: function(files) {
                const fileList = Array.from(files);
                this.displayFileList(fileList);

                if (fileList.length > 0) {
                    $('#mmr-pro-upload-button').show();
                }
            },

            displayFileList: function(files) {
                const $container = $('#mmr-pro-file-list');
                $container.empty();

                files.forEach(function(file, index) {
                    const fileItem = this.generateFileItem(file, index);
                    $container.append(fileItem);
                }.bind(this));

                this.updateTotalSize(files);
            },

            generateFileItem: function(file, index) {
                let html = '<div class="file-item" data-index="' + index + '">';
                html += '<div class="file-icon">' + this.getFileIcon(file.type) + '</div>';
                html += '<div class="file-details">';
                html += '<h5 class="file-name">' + file.name + '</h5>';
                html += '<p class="file-size">' + this.formatBytes(file.size) + '</p>';
                html += '</div>';
                html += '<div class="file-actions">';
                html += '<button class="button button-small remove-file">Remove</button>';
                html += '</div>';
                html += '<div class="file-progress" style="display: none;">';
                html += '<div class="mmr-pro-progress-bar"><div class="progress-fill"></div><div class="progress-text">0%</div></div>';
                html += '</div>';
                html += '</div>';
                return html;
            },

            getFileIcon: function(fileType) {
                if (fileType.startsWith('image/')) {
                    return '<span class="dashicons dashicons-format-image"></span>';
                } else if (fileType.startsWith('video/')) {
                    return '<span class="dashicons dashicons-format-video"></span>';
                } else if (fileType.startsWith('audio/')) {
                    return '<span class="dashicons dashicons-format-audio"></span>';
                } else {
                    return '<span class="dashicons dashicons-media-default"></span>';
                }
            },

            updateTotalSize: function(files) {
                const totalSize = files.reduce((total, file) => total + file.size, 0);
                $('#mmr-pro-total-size').text(this.formatBytes(totalSize));
            },

            performBulkUpload: function() {
                const files = this.getSelectedFiles();
                if (files.length === 0) {
                    this.showNotification('Please select files to upload.', 'error');
                    return;
                }

                const options = {
                    overwrite_existing: $('#mmr-pro-overwrite-existing').prop('checked'),
                    create_thumbnails: $('#mmr-pro-create-thumbnails').prop('checked'),
                    skip_mime_validation: $('#mmr-pro-skip-mime-validation').prop('checked'),
                    batch_size: parseInt($('#mmr-pro-batch-size').val()) || 10,
                };

                this.executeBulkUpload(files, options);
            },

            executeBulkUpload: function(files, options) {
                const sessionId = 'upload_' + Date.now();
                MMR_Pro.currentSession = sessionId;

                const formData = new FormData();
                files.forEach(function(file) {
                    formData.append('files[]', file);
                });

                Object.keys(options).forEach(function(key) {
                    formData.append(key, options[key]);
                });

                formData.append('action', 'mmr_pro_bulk_upload');
                formData.append('nonce', mmrConfig.nonce);

                this.uploadWithProgress(formData, sessionId);
            },

            uploadWithProgress: function(formData, sessionId) {
                const $button = $('#mmr-pro-bulk-upload-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Uploading...');

                // Track upload progress
                MMR_Pro.analytics.trackProgress(sessionId, 'upload', {
                    total_files: formData.getAll('files[]').length,
                    options: {
                        overwrite_existing: formData.get('overwrite_existing'),
                        create_thumbnails: formData.get('create_thumbnails'),
                    }
                });

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percentComplete = (e.loaded / e.total) * 100;
                                this.updateUploadProgress(percentComplete);
                            }
                        }.bind(this));
                        return xhr;
                    }.bind(this),
                    success: function(response) {
                        if (response.success) {
                            this.handleUploadSuccess(response.data, sessionId);
                        } else {
                            this.handleUploadError(response.data);
                        }
                    }.bind(this),
                    error: function(xhr, status, error) {
                        this.handleUploadError({message: 'Upload failed: ' + error});
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Upload Files');
                    }
                });
            },

            updateUploadProgress: function(percent) {
                $('.file-progress .progress-fill').css('width', percent + '%');
                $('.file-progress .progress-text').text(Math.round(percent) + '%');
            },

            handleUploadSuccess: function(data, sessionId) {
                this.showNotification('Files uploaded successfully!', 'success');
                this.displayUploadResults(data);

                MMR_Pro.analytics.trackProgress(sessionId, 'upload', {
                    status: 'completed',
                    uploaded: data.uploaded.length,
                    failed: data.failed.length,
                    total_size: data.total_size,
                    upload_time: data.upload_time
                });
            },

            handleUploadError: function(error) {
                this.showNotification(error.message || 'Upload failed', 'error');
            },

            displayUploadResults: function(data) {
                const $container = $('#mmr-pro-upload-results');
                let html = '<div class="upload-results">';
                html += '<h4>Upload Results</h4>';

                if (data.uploaded.length > 0) {
                    html += '<div class="uploaded-files">';
                    html += '<h5>Successfully Uploaded (' + data.uploaded.length + ')</h5>';
                    data.uploaded.forEach(function(file) {
                        html += '<div class="result-item success">' + file.name + '</div>';
                    });
                    html += '</div>';
                }

                if (data.failed.length > 0) {
                    html += '<div class="failed-files">';
                    html += '<h5>Failed Uploads (' + data.failed.length + ')</h5>';
                    data.failed.forEach(function(file) {
                        html += '<div class="result-item error">' + file.name + ': ' + file.error + '</div>';
                    });
                    html += '</div>';
                }

                html += '</div>';
                $container.html(html);
            },

            getSelectedFiles: function() {
                const files = [];
                $('#mmr-pro-file-list .file-item').each(function() {
                    const index = $(this).data('index');
                    const fileInput = $('#mmr-pro-upload-files')[0];
                    if (fileInput.files[index]) {
                        files.push(fileInput.files[index]);
                    }
                });
                return files;
            },

            enableChunkedUpload: function() {
                // Enable chunked upload for large files
                const files = this.getSelectedFiles();
                const largeFiles = files.filter(file => file.size > 10 * 1024 * 1024); // Files > 10MB

                if (largeFiles.length > 0) {
                    this.uploadLargeFilesInChunks(largeFiles);
                } else {
                    this.showNotification('No large files found for chunked upload.', 'info');
                }
            },

            uploadLargeFilesInChunks: function(files) {
                files.forEach(function(file) {
                    this.uploadFileInChunks(file);
                }.bind(this));
            },

            uploadFileInChunks: function(file) {
                const chunks = Math.ceil(file.size / MMR_Pro.chunkSize);
                const fileId = 'file_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

                for (let i = 0; i < chunks; i++) {
                    const start = i * MMR_Pro.chunkSize;
                    const end = Math.min(start + MMR_Pro.chunkSize, file.size);
                    const chunk = file.slice(start, end);

                    this.uploadChunk(chunk, fileId, i, chunks, file.name);
                }
            },

            uploadChunk: function(chunk, fileId, chunkIndex, totalChunks, fileName) {
                const formData = new FormData();
                formData.append('action', 'mmr_pro_chunk_upload');
                formData.append('nonce', mmrConfig.nonce);
                formData.append('chunk', chunk);
                formData.append('file_id', fileId);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('file_name', fileName);

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            if (response.data.upload_complete) {
                                this.showNotification('File uploaded successfully: ' + fileName, 'success');
                            }
                        } else {
                            this.showNotification('Chunk upload failed: ' + response.data.error, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('Chunk upload failed', 'error');
                    }.bind(this)
                });
            },

            showNotification: function(message, type) {
                const notification = $('<div class="mmr-pro-notification mmr-pro-' + type + '">' + message + '</div>');
                $('body').append(notification);

                setTimeout(function() {
                    notification.fadeOut(function() {
                        notification.remove();
                    });
                }, 3000);
            },

            formatBytes: function(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        };

        MMR_Pro.uploader.init();
    }

    // Pro Matcher Module
    function initProMatcher() {
        MMR_Pro.matcher = {
            init: function() {
                this.bindEvents();
                this.setupMatchingOptions();
            },

            bindEvents: function() {
                $('#mmr-pro-advanced-match-button').on('click', this.performAdvancedMatch.bind(this));
                $('#mmr-pro-batch-match-button').on('click', this.performBatchMatch.bind(this));
                $('#mmr-pro-verify-match-button').on('click', this.verifyMatch.bind(this));
            },

            setupMatchingOptions: function() {
                // Initialize matching algorithm weights
                $('.mmr-pro-algorithm-weight').each(function() {
                    const $slider = $(this);
                    const $value = $slider.siblings('.weight-value');

                    $slider.on('input', function() {
                        $value.text($(this).val());
                    });
                });
            },

            performAdvancedMatch: function() {
                const missingFiles = this.getMissingFiles();
                const availableFiles = this.getAvailableFiles();

                if (missingFiles.length === 0 || availableFiles.length === 0) {
                    this.showNotification('Please provide both missing and available files for matching.', 'error');
                    return;
                }

                const options = {
                    algorithms: this.getSelectedAlgorithms(),
                    threshold: parseFloat($('#mmr-pro-match-threshold').val()) || 0.7,
                    max_matches: parseInt($('#mmr-pro-max-matches').val()) || 5,
                };

                this.executeAdvancedMatch(missingFiles, availableFiles, options);
            },

            executeAdvancedMatch: function(missingFiles, availableFiles, options) {
                const $button = $('#mmr-pro-advanced-match-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Matching...');

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_advanced_match',
                        missing_files: JSON.stringify(missingFiles),
                        available_files: JSON.stringify(availableFiles),
                        ...options,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayMatchResults(response.data);
                            this.trackMatchingProgress(response.data);
                        } else {
                            this.showNotification('Matching failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during matching.', 'error');
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Advanced Match');
                    }
                });
            },

            performBatchMatch: function() {
                const batchData = {
                    missing_files: this.getMissingFiles(),
                    available_files: this.getAvailableFiles(),
                    batch_size: parseInt($('#mmr-pro-batch-size').val()) || 50,
                    options: {
                        algorithms: this.getSelectedAlgorithms(),
                        threshold: parseFloat($('#mmr-pro-match-threshold').val()) || 0.7,
                    }
                };

                this.executeBatchMatch(batchData);
            },

            executeBatchMatch: function(batchData) {
                const $button = $('#mmr-pro-batch-match-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Batch Matching...');

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_batch_match',
                        batch_data: JSON.stringify(batchData),
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayBatchMatchResults(response.data);
                        } else {
                            this.showNotification('Batch matching failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during batch matching.', 'error');
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Batch Match');
                    }
                });
            },

            verifyMatch: function() {
                const missingFilePath = $('#mmr-pro-missing-file-path').val();
                const candidateFilePath = $('#mmr-pro-candidate-file-path').val();

                if (!missingFilePath || !candidateFilePath) {
                    this.showNotification('Both file paths are required for verification.', 'error');
                    return;
                }

                this.executeMatchVerification(missingFilePath, candidateFilePath);
            },

            executeMatchVerification: function(missingFilePath, candidateFilePath) {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_verify_match',
                        missing_file_path: missingFilePath,
                        candidate_file_path: candidateFilePath,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayVerificationResults(response.data);
                        } else {
                            this.showNotification('Verification failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during verification.', 'error');
                    }.bind(this)
                });
            },

            displayMatchResults: function(data) {
                const $container = $('#mmr-pro-match-results');
                let html = '<div class="match-results">';
                html += '<h3>Matching Results</h3>';
                html += '<div class="match-statistics">';
                html += '<div class="stat-item"><span class="stat-label">Total Missing:</span> <span class="stat-value">' + data.statistics.total_missing + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">Matches Found:</span> <span class="stat-value">' + data.statistics.matches_found + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">High Confidence:</span> <span class="stat-value">' + data.statistics.high_confidence + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">Medium Confidence:</span> <span class="stat-value">' + data.statistics.medium_confidence + '</span></div>';
                html += '</div>';

                if (data.matches.length > 0) {
                    html += '<div class="matches-list">';
                    data.matches.forEach(function(match) {
                        html += this.generateMatchCard(match);
                    }.bind(this));
                    html += '</div>';
                }

                html += '</div>';
                $container.html(html);
                this.bindMatchEvents();
            },

            generateMatchCard: function(match) {
                const confidence = match.best_match.confidence;
                const confidenceClass = confidence >= 0.9 ? 'high' : confidence >= 0.7 ? 'medium' : 'low';

                let html = '<div class="match-card confidence-' + confidenceClass + '" data-match="' + encodeURIComponent(JSON.stringify(match)) + '">';
                html += '<div class="match-header">';
                html += '<h4 class="missing-filename">' + match.missing_file.filename + '</h4>';
                html += '<div class="confidence-badge">' + Math.round(confidence * 100) + '%</div>';
                html += '</div>';
                html += '<div class="match-details">';
                html += '<h5>Best Match: ' + match.best_match.file.filename + '</h5>';
                html += '<div class="match-algorithms">';
                match.best_match.details.forEach(function(detail) {
                    html += '<span class="algorithm-tag">' + detail + '</span>';
                });
                html += '</div>';
                html += '</div>';
                html += '<div class="match-actions">';
                html += '<button class="button button-small accept-match">Accept</button>';
                html += '<button class="button button-small reject-match">Reject</button>';
                html += '<button class="button button-small verify-match">Verify</button>';
                html += '</div>';
                html += '</div>';
                return html;
            },

            displayVerificationResults: function(data) {
                const $container = $('#mmr-pro-verification-results');
                let html = '<div class="verification-results">';
                html += '<h4>Verification Results</h4>';
                html += '<div class="verification-status ' + (data.verified ? 'verified' : 'not-verified') + '">';
                html += '<span class="status-icon">' + (data.verified ? '✓' : '✗') + '</span>';
                html += '<span class="status-text">' + (data.verified ? 'Files Match' : 'Files Do Not Match') + '</span>';
                html += '</div>';

                html += '<div class="verification-details">';
                html += '<div class="detail-item"><span class="detail-label">Hash Match:</span> <span class="detail-value">' + (data.hash_match ? 'Yes' : 'No') + '</span></div>';
                html += '<div class="detail-item"><span class="detail-label">Size Match:</span> <span class="detail-value">' + (data.size_match ? 'Yes' : 'No') + '</span></div>';
                html += '</div>';

                if (data.error) {
                    html += '<div class="verification-error">' + data.error + '</div>';
                }

                html += '</div>';
                $container.html(html);
            },

            getMissingFiles: function() {
                // Get missing files from current context
                return window.mmrMissingFiles || [];
            },

            getAvailableFiles: function() {
                // Get available files from current context
                return window.mmrAvailableFiles || [];
            },

            getSelectedAlgorithms: function() {
                const algorithms = [];
                $('.mmr-pro-algorithm:checked').each(function() {
                    algorithms.push($(this).val());
                });
                return algorithms;
            },

            bindMatchEvents: function() {
                $('.accept-match').on('click', function() {
                    const matchData = JSON.parse(decodeURIComponent($(this).closest('.match-card').data('match')));
                    this.acceptMatch(matchData);
                }.bind(this));

                $('.reject-match').on('click', function() {
                    const matchData = JSON.parse(decodeURIComponent($(this).closest('.match-card').data('match')));
                    this.rejectMatch(matchData);
                }.bind(this));

                $('.verify-match').on('click', function() {
                    const matchData = JSON.parse(decodeURIComponent($(this).closest('.match-card').data('match')));
                    this.verifyMatchData(matchData);
                }.bind(this));
            },

            acceptMatch: function(matchData) {
                // Process accepted match
                this.showNotification('Match accepted for: ' + matchData.missing_file.filename, 'success');
                // Add to restoration queue
            },

            rejectMatch: function(matchData) {
                // Process rejected match
                this.showNotification('Match rejected for: ' + matchData.missing_file.filename, 'info');
            },

            verifyMatchData: function(matchData) {
                const missingFile = matchData.missing_file;
                const candidateFile = matchData.best_match.file;

                // Fill verification form
                $('#mmr-pro-missing-file-path').val(missingFile.path || missingFile.local_path);
                $('#mmr-pro-candidate-file-path').val(candidateFile.path || candidateFile.local_path);

                // Execute verification
                this.executeMatchVerification(
                    missingFile.path || missingFile.local_path,
                    candidateFile.path || candidateFile.local_path
                );
            },

            trackMatchingProgress: function(data) {
                const sessionId = 'match_' + Date.now();
                MMR_Pro.currentSession = sessionId;

                MMR_Pro.analytics.trackProgress(sessionId, 'match', {
                    total_missing: data.statistics.total_missing,
                    matches_found: data.statistics.matches_found,
                    high_confidence: data.statistics.high_confidence,
                    medium_confidence: data.statistics.medium_confidence,
                    low_confidence: data.statistics.low_confidence
                });
            },

            showNotification: function(message, type) {
                const notification = $('<div class="mmr-pro-notification mmr-pro-' + type + '">' + message + '</div>');
                $('body').append(notification);

                setTimeout(function() {
                    notification.fadeOut(function() {
                        notification.remove();
                    });
                }, 3000);
            }
        };

        MMR_Pro.matcher.init();
    }

    // Pro Analytics Module
    function initProAnalytics() {
        MMR_Pro.analytics = {
            init: function() {
                this.bindEvents();
                this.loadAnalyticsData();
            },

            bindEvents: function() {
                $('#mmr-pro-generate-report-button').on('click', this.generateReport.bind(this));
                $('#mmr-pro-export-analytics-button').on('click', this.exportAnalytics.bind(this));
                $('#mmr-pro-refresh-analytics-button').on('click', this.refreshAnalytics.bind(this));
            },

            loadAnalyticsData: function() {
                this.getAnalyticsSummary();
                this.getDetailedAnalytics();
            },

            getAnalyticsSummary: function() {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'mmr_pro_get_analytics',
                        summary: true,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayAnalyticsSummary(response.data.summary);
                        }
                    }.bind(this)
                });
            },

            getDetailedAnalytics: function() {
                const args = {
                    date_from: $('#mmr-pro-date-from').val(),
                    date_to: $('#mmr-pro-date-to').val(),
                    action_type: $('#mmr-pro-action-filter').val(),
                    status: $('#mmr-pro-status-filter').val()
                };

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'mmr_pro_get_analytics',
                        ...args,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayDetailedAnalytics(response.data.analytics);
                        }
                    }.bind(this)
                });
            },

            displayAnalyticsSummary: function(summary) {
                const $container = $('#mmr-pro-analytics-summary');
                let html = '<div class="analytics-summary">';

                if (summary.overall) {
                    html += '<div class="summary-overall">';
                    html += '<h3>Overall Statistics</h3>';
                    html += '<div class="stats-grid">';
                    html += '<div class="stat-card"><h4>' + summary.overall.total_operations + '</h4><p>Total Operations</p></div>';
                    html += '<div class="stat-card"><h4>' + summary.overall.completed + '</h4><p>Completed</p></div>';
                    html += '<div class="stat-card"><h4>' + summary.overall.failed + '</h4><p>Failed</p></div>';
                    html += '<div class="stat-card"><h4>' + Math.round(summary.overall.avg_duration || 0) + 's</h4><p>Avg Duration</p></div>';
                    html += '</div>';
                    html += '</div>';
                }

                if (summary.by_action_type) {
                    html += '<div class="summary-by-action">';
                    html += '<h3>By Action Type</h3>';
                    html += '<div class="action-stats">';

                    summary.by_action_type.forEach(function(action) {
                        html += '<div class="action-stat">';
                        html += '<h4>' + action.action_type + '</h4>';
                        html += '<div class="action-details">';
                        html += '<span>Operations: ' + action.total_operations + '</span>';
                        html += '<span>Success Rate: ' + Math.round((action.completed / action.total_operations) * 100) + '%</span>';
                        html += '<span>Avg Duration: ' + Math.round(action.avg_duration || 0) + 's</span>';
                        html += '</div>';
                        html += '</div>';
                    });

                    html += '</div>';
                    html += '</div>';
                }

                html += '</div>';
                $container.html(html);
            },

            displayDetailedAnalytics: function(analytics) {
                const $container = $('#mmr-pro-detailed-analytics');
                let html = '<div class="detailed-analytics">';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Session ID</th><th>Action</th><th>Status</th><th>Progress</th><th>Duration</th><th>Items</th><th>Date</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                analytics.forEach(function(item) {
                    html += '<tr>';
                    html += '<td>' + item.session_id + '</td>';
                    html += '<td>' + item.action_type + '</td>';
                    html += '<td><span class="status-badge status-' + item.status + '">' + item.status + '</span></td>';
                    html += '<td>' + item.progress + '%</td>';
                    html += '<td>' + (item.duration || '-') + 's</td>';
                    html += '<td>' + item.processed_items + '/' + item.total_items + '</td>';
                    html += '<td>' + new Date(item.created_at).toLocaleDateString() + '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                $container.html(html);
            },

            generateReport: function() {
                const reportType = $('#mmr-pro-report-type').val();
                const dateFrom = $('#mmr-pro-report-date-from').val();
                const dateTo = $('#mmr-pro-report-date-to').val();
                const format = $('#mmr-pro-report-format').val();

                const $button = $('#mmr-pro-generate-report-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Generating...');

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_generate_report',
                        report_type: reportType,
                        date_from: dateFrom,
                        date_to: dateTo,
                        format: format,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayReportResults(response.data);
                            if (format === 'json' || format === 'csv') {
                                this.downloadReport(response.data, format);
                            }
                        } else {
                            this.showNotification('Report generation failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during report generation.', 'error');
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Generate Report');
                    }
                });
            },

            exportAnalytics: function() {
                const format = $('#mmr-pro-export-format').val();
                const dateFrom = $('#mmr-pro-export-date-from').val();
                const dateTo = $('#mmr-pro-export-date-to').val();

                // Create form for file download
                const form = $('<form>', {
                    method: 'POST',
                    action: mmrConfig.ajax_url
                });

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'action',
                    value: 'mmr_pro_export_analytics'
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'format',
                    value: format
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'date_from',
                    value: dateFrom
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'date_to',
                    value: dateTo
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: 'nonce',
                    value: mmrConfig.nonce
                }));

                $('body').append(form);
                form.submit();
                form.remove();
            },

            refreshAnalytics: function() {
                this.loadAnalyticsData();
                this.showNotification('Analytics data refreshed', 'success');
            },

            trackProgress: function(sessionId, actionType, data) {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_track_progress',
                        session_id: sessionId,
                        action_type: actionType,
                        data: JSON.stringify(data),
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Progress tracked successfully');
                        }
                    }
                });
            },

            displayReportResults: function(data) {
                const $container = $('#mmr-pro-report-results');
                let html = '<div class="report-results">';
                html += '<h3>Generated Report</h3>';
                html += '<div class="report-info">';
                html += '<p><strong>Type:</strong> ' + data.report_type + '</p>';
                html += '<p><strong>Generated:</strong> ' + new Date(data.generated_at).toLocaleString() + '</p>';
                html += '<p><strong>Period:</strong> ' + data.period.from + ' to ' + data.period.to + '</p>';
                html += '</div>';
                html += '</div>';
                $container.html(html);
            },

            downloadReport: function(data, format) {
                const filename = 'mmr-report-' + new Date().toISOString().split('T')[0] + '.' + format;
                const content = typeof data === 'string' ? data : JSON.stringify(data, null, 2);

                const blob = new Blob([content], { type: format === 'csv' ? 'text/csv' : 'application/json' });
                const url = URL.createObjectURL(blob);

                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            },

            showNotification: function(message, type) {
                const notification = $('<div class="mmr-pro-notification mmr-pro-' + type + '">' + message + '</div>');
                $('body').append(notification);

                setTimeout(function() {
                    notification.fadeOut(function() {
                        notification.remove();
                    });
                }, 3000);
            }
        };

        MMR_Pro.analytics.init();
    }

    // Pro Support Module
    function initProSupport() {
        MMR_Pro.support = {
            init: function() {
                this.bindEvents();
                this.loadSupportData();
            },

            bindEvents: function() {
                $('#mmr-pro-create-ticket-button').on('click', this.createTicket.bind(this));
                $('#mmr-pro-search-kb-button').on('click', this.searchKnowledgeBase.bind(this));
                $('#mmr-pro-run-diagnostic-button').on('click', this.runDiagnostic.bind(this));
                $('#mmr-pro-get-system-info-button').on('click', this.getSystemInfo.bind(this));
            },

            loadSupportData: function() {
                this.getTickets();
                this.getKnowledgeBaseArticles();
            },

            createTicket: function() {
                const ticketData = {
                    subject: $('#mmr-pro-ticket-subject').val(),
                    message: $('#mmr-pro-ticket-message').val(),
                    priority: $('#mmr-pro-ticket-priority').val(),
                    category: $('#mmr-pro-ticket-category').val(),
                    include_system_info: $('#mmr-pro-include-system-info').prop('checked')
                };

                if (!ticketData.subject || !ticketData.message) {
                    this.showNotification('Subject and message are required', 'error');
                    return;
                }

                const $button = $('#mmr-pro-create-ticket-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Creating...');

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_create_ticket',
                        ...ticketData,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.showNotification('Ticket created successfully: ' + response.data.ticket_id, 'success');
                            this.clearTicketForm();
                            this.getTickets();
                        } else {
                            this.showNotification('Failed to create ticket: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred while creating the ticket', 'error');
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Create Ticket');
                    }
                });
            },

            getTickets: function() {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'mmr_pro_get_tickets',
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayTickets(response.data.tickets);
                        }
                    }.bind(this)
                });
            },

            displayTickets: function(tickets) {
                const $container = $('#mmr-pro-tickets-list');
                let html = '<div class="tickets-list">';

                if (tickets.length === 0) {
                    html += '<p>No support tickets found.</p>';
                } else {
                    html += '<table class="wp-list-table widefat fixed striped">';
                    html += '<thead><tr>';
                    html += '<th>Ticket ID</th><th>Subject</th><th>Priority</th><th>Status</th><th>Created</th><th>Actions</th>';
                    html += '</tr></thead>';
                    html += '<tbody>';

                    tickets.forEach(function(ticket) {
                        html += this.generateTicketRow(ticket);
                    }.bind(this));

                    html += '</tbody>';
                    html += '</table>';
                }

                html += '</div>';
                $container.html(html);
            },

            generateTicketRow: function(ticket) {
                let html = '<tr>';
                html += '<td>' + ticket.ticket_id + '</td>';
                html += '<td>' + ticket.subject + '</td>';
                html += '<td><span class="priority-badge priority-' + ticket.priority + '">' + ticket.priority + '</span></td>';
                html += '<td><span class="status-badge status-' + ticket.status + '">' + ticket.status + '</span></td>';
                html += '<td>' + new Date(ticket.created_at).toLocaleDateString() + '</td>';
                html += '<td><button class="button button-small view-ticket" data-ticket="' + ticket.id + '">View</button></td>';
                html += '</tr>';
                return html;
            },

            searchKnowledgeBase: function() {
                const query = $('#mmr-pro-kb-search').val();
                const category = $('#mmr-pro-kb-category').val();

                if (!query) {
                    this.showNotification('Please enter a search query', 'error');
                    return;
                }

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'mmr_pro_search_kb',
                        query: query,
                        category: category,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayKbResults(response.data.results);
                        }
                    }.bind(this)
                });
            },

            displayKbResults: function(results) {
                const $container = $('#mmr-pro-kb-results');
                let html = '<div class="kb-results">';

                if (results.length === 0) {
                    html += '<p>No articles found matching your search.</p>';
                } else {
                    html += '<h4>Found ' + results.length + ' articles</h4>';
                    results.forEach(function(article) {
                        html += this.generateKbArticle(article);
                    }.bind(this));
                }

                html += '</div>';
                $container.html(html);
            },

            generateKbArticle: function(article) {
                let html = '<div class="kb-article" data-slug="' + article.slug + '">';
                html += '<h5><a href="#" class="kb-article-link">' + article.title + '</a></h5>';
                html += '<div class="kb-meta">';
                html += '<span class="kb-category">' + article.category + '</span>';
                html += '<span class="kb-views">' + article.views + ' views</span>';
                html += '</div>';
                html += '<div class="kb-excerpt">' + this.truncateText(article.content, 150) + '</div>';
                html += '</div>';
                return html;
            },

            runDiagnostic: function() {
                const $button = $('#mmr-pro-run-diagnostic-button');
                $button.prop('disabled', true).html('<span class="spinner is-active"></span> Running...');

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_diagnostic',
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displayDiagnosticResults(response.data);
                        } else {
                            this.showNotification('Diagnostic failed: ' + response.data.message, 'error');
                        }
                    }.bind(this),
                    error: function() {
                        this.showNotification('An error occurred during diagnostic', 'error');
                    }.bind(this),
                    complete: function() {
                        $button.prop('disabled', false).text('Run Diagnostic');
                    }
                });
            },

            displayDiagnosticResults: function(results) {
                const $container = $('#mmr-pro-diagnostic-results');
                let html = '<div class="diagnostic-results">';
                html += '<h4>Diagnostic Results</h4>';
                html += '<div class="diagnostic-summary">';
                html += '<div class="summary-item passed"><span class="count">' + results.summary.passed + '</span> Passed</div>';
                html += '<div class="summary-item failed"><span class="count">' + results.summary.failed + '</span> Failed</div>';
                html += '<div class="summary-item warning"><span class="count">' + results.summary.warnings + '</span> Warnings</div>';
                html += '</div>';
                html += '<div class="diagnostic-tests">';

                results.tests.forEach(function(test) {
                    html += '<div class="test-result status-' + test.status + '">';
                    html += '<h5>' + test.name + '</h5>';
                    html += '<p>' + test.message + '</p>';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
                $container.html(html);
            },

            getSystemInfo: function() {
                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mmr_pro_system_info',
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.displaySystemInfo(response.data.info);
                        }
                    }.bind(this)
                });
            },

            displaySystemInfo: function(info) {
                const $container = $('#mmr-pro-system-info-display');
                let html = '<div class="system-info">';
                html += '<h4>System Information</h4>';
                html += '<div class="info-sections">';

                Object.keys(info).forEach(function(section) {
                    html += '<div class="info-section">';
                    html += '<h5>' + section.charAt(0).toUpperCase() + section.slice(1) + '</h5>';
                    html += '<table class="widefat">';

                    Object.keys(info[section]).forEach(function(key) {
                        html += '<tr>';
                        html += '<td>' + key.replace(/_/g, ' ').charAt(0).toUpperCase() + key.replace(/_/g, ' ').slice(1) + '</td>';
                        html += '<td>' + (Array.isArray(info[section][key]) ? info[section][key].join(', ') : info[section][key]) + '</td>';
                        html += '</tr>';
                    });

                    html += '</table>';
                    html += '</div>';
                });

                html += '</div>';
                html += '</div>';
                $container.html(html);
            },

            clearTicketForm: function() {
                $('#mmr-pro-ticket-subject').val('');
                $('#mmr-pro-ticket-message').val('');
                $('#mmr-pro-ticket-priority').val('normal');
                $('#mmr-pro-ticket-category').val('general');
                $('#mmr-pro-include-system-info').prop('checked', false);
            },

            truncateText: function(text, length) {
                if (text.length <= length) return text;
                return text.substr(0, length) + '...';
            },

            showNotification: function(message, type) {
                const notification = $('<div class="mmr-pro-notification mmr-pro-' + type + '">' + message + '</div>');
                $('body').append(notification);

                setTimeout(function() {
                    notification.fadeOut(function() {
                        notification.remove();
                    });
                }, 3000);
            }
        };

        MMR_Pro.support.init();
    }

    // Progress Tracking Module
    function initProgressTracking() {
        MMR_Pro.progressTracker = {
            init: function() {
                this.setupProgressBars();
                this.bindEvents();
            },

            setupProgressBars: function() {
                // Initialize all progress bars with smooth animations
                $('.mmr-pro-progress-bar').each(function() {
                    const $bar = $(this);
                    const $fill = $bar.find('.progress-fill');
                    const $text = $bar.find('.progress-text');

                    $bar.data('target', 0);
                    $bar.data('current', 0);
                });
            },

            bindEvents: function() {
                // Bind progress update events
                $(document).on('mmr:progress', this.updateProgress.bind(this));
                $(document).on('mmr:progress:complete', this.completeProgress.bind(this));
                $(document).on('mmr:progress:error', this.errorProgress.bind(this));
            },

            updateProgress: function(e, data) {
                const $progress = $('#' + data.progressId);
                if (!$progress.length) return;

                const $fill = $progress.find('.progress-fill');
                const $text = $progress.find('.progress-text');

                // Smooth animation
                const current = $progress.data('current') || 0;
                const target = data.percent;
                const step = (target - current) / 10;

                let iteration = 0;
                const interval = setInterval(function() {
                    iteration++;
                    const newValue = current + (step * iteration);

                    if (iteration >= 10 || (step > 0 && newValue >= target) || (step < 0 && newValue <= target)) {
                        $fill.css('width', target + '%');
                        $text.text(Math.round(target) + '%');
                        $progress.data('current', target);
                        clearInterval(interval);
                    } else {
                        $fill.css('width', newValue + '%');
                        $text.text(Math.round(newValue) + '%');
                        $progress.data('current', newValue);
                    }
                }, 30);
            },

            completeProgress: function(e, data) {
                this.updateProgress(e, { progressId: data.progressId, percent: 100 });
                const $progress = $('#' + data.progressId);
                $progress.addClass('completed');

                if (data.message) {
                    this.showProgressMessage(data.progressId, data.message, 'success');
                }
            },

            errorProgress: function(e, data) {
                const $progress = $('#' + data.progressId);
                $progress.addClass('error');

                if (data.message) {
                    this.showProgressMessage(data.progressId, data.message, 'error');
                }
            },

            showProgressMessage: function(progressId, message, type) {
                const $container = $('#' + progressId).closest('.progress-container');
                const $message = $container.find('.progress-message');

                $message.text(message).removeClass('success error').addClass(type).show();

                setTimeout(function() {
                    $message.fadeOut();
                }, 5000);
            }
        };

        MMR_Pro.progressTracker.init();
    }

    // Chunked Upload Module
    function initChunkedUpload() {
        MMR_Pro.chunkedUpload = {
            init: function() {
                this.setupChunkedUpload();
            },

            setupChunkedUpload: function() {
                // Enable chunked upload for large files
                $('#mmr-pro-enable-chunked-upload').on('change', function() {
                    const enabled = $(this).prop('checked');
                    $('#mmr-pro-chunk-size-container').toggle(enabled);
                });
            }
        };

        MMR_Pro.chunkedUpload.init();
    }

    // Real-time Updates Module
    function initRealTimeUpdates() {
        MMR_Pro.realTimeUpdates = {
            init: function() {
                this.setupWebSocket();
                this.setupPolling();
            },

            setupWebSocket: function() {
                // WebSocket connection for real-time updates
                if (typeof WebSocket !== 'undefined' && mmrConfig.websocket_url) {
                    try {
                        this.ws = new WebSocket(mmrConfig.websocket_url);

                        this.ws.onopen = function() {
                            console.log('WebSocket connected for real-time updates');
                        };

                        this.ws.onmessage = function(e) {
                            const data = JSON.parse(e.data);
                            this.handleRealTimeUpdate(data);
                        }.bind(this);

                        this.ws.onclose = function() {
                            console.log('WebSocket disconnected, falling back to polling');
                            this.setupPolling();
                        }.bind(this);
                    } catch (e) {
                        console.log('WebSocket not available, using polling');
                        this.setupPolling();
                    }
                } else {
                    this.setupPolling();
                }
            },

            setupPolling: function() {
                // Fallback to polling for real-time updates
                if (MMR_Pro.progressInterval) {
                    clearInterval(MMR_Pro.progressInterval);
                }

                MMR_Pro.progressInterval = setInterval(function() {
                    this.pollProgress();
                }.bind(this), 2000); // Poll every 2 seconds
            },

            pollProgress: function() {
                if (!MMR_Pro.currentSession) return;

                $.ajax({
                    url: mmrConfig.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'mmr_pro_get_progress',
                        session_id: MMR_Pro.currentSession,
                        nonce: mmrConfig.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            this.handleProgressUpdate(response.data);
                        }
                    }.bind(this)
                });
            },

            handleRealTimeUpdate: function(data) {
                switch (data.type) {
                    case 'progress':
                        this.handleProgressUpdate(data.data);
                        break;
                    case 'complete':
                        this.handleCompleteUpdate(data.data);
                        break;
                    case 'error':
                        this.handleErrorUpdate(data.data);
                        break;
                }
            },

            handleProgressUpdate: function(data) {
                $(document).trigger('mmr:progress', {
                    progressId: data.session_id,
                    percent: data.progress,
                    message: data.message
                });
            },

            handleCompleteUpdate: function(data) {
                $(document).trigger('mmr:progress:complete', {
                    progressId: data.session_id,
                    message: data.message
                });
            },

            handleErrorUpdate: function(data) {
                $(document).trigger('mmr:progress:error', {
                    progressId: data.session_id,
                    message: data.message
                });
            }
        };

        MMR_Pro.realTimeUpdates.init();
    }

})(jQuery);
