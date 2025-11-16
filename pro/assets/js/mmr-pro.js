/**
 * Missing Media Restorer Pro - JavaScript Enhancements
 */

(function($) {
    'use strict';

    // Pro scanner functionality
    function initProScanner() {
        const scannerInput = $('#mmr-pro-scanner-input');
        const scannerButton = $('#mmr-pro-scanner-button');
        const resultsContainer = $('#mmr-pro-scanner-results');

        if (!scannerInput.length || !scannerButton.length) return;

        scannerButton.on('click', function(e) {
            e.preventDefault();

            const directory = scannerInput.val().trim();
            if (!directory) {
                alert('Please enter a directory path to scan.');
                return;
            }

            // Show loading state
            scannerButton.prop('disabled', true).text('Scanning...');

            // AJAX request to scan directory
            $.ajax({
                url: mmrConfig.ajax_url,
                type: 'POST',
                data: {
                    action: 'mmr_pro_scan_directory',
                    directory: directory,
                    nonce: mmrConfig.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayScanResults(response.data, resultsContainer);
                    } else {
                        alert('Scan failed: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred during scanning.');
                },
                complete: function() {
                    scannerButton.prop('disabled', false).text('Scan Directory');
                }
            });
        });
    }

    // Display scan results
    function displayScanResults(data, container) {
        let html = '<div class="mmr-scan-results">';
        html += '<h4>Scan Results</h4>';

        if (data.found_files && data.found_files.length > 0) {
            html += '<div class="mmr-found-files">';
            html += '<p><strong>Found ' + data.found_files.length + ' missing media files:</strong></p>';
            html += '<ul class="mmr-file-list">';

            data.found_files.forEach(function(file) {
                html += '<li class="mmr-file-item">';
                html += '<span class="dashicons dashicons-media-default"></span>';
                html += '<span class="mmr-file-name">' + file.name + '</span>';
                html += '<span class="mmr-file-path">' + file.path + '</span>';
                html += '<button class="button button-small mmr-restore-single" data-file="' + file.path + '">Restore</button>';
                html += '</li>';
            });

            html += '</ul>';
            html += '<button class="button button-primary mmr-restore-all">Restore All Files</button>';
            html += '</div>';
        } else {
            html += '<p>No missing media files found in the specified directory.</p>';
        }

        html += '</div>';
        container.html(html);

        // Bind restore events
        bindRestoreEvents();
    }

    // Bind restore button events
    function bindRestoreEvents() {
        $('.mmr-restore-single').on('click', function() {
            const filePath = $(this).data('file');
            restoreSingleFile(filePath, $(this));
        });

        $('.mmr-restore-all').on('click', function() {
            restoreAllFiles();
        });
    }

    // Restore single file
    function restoreSingleFile(filePath, button) {
        button.prop('disabled', true).text('Restoring...');

        $.ajax({
            url: mmrConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'mmr_pro_restore_file',
                file_path: filePath,
                nonce: mmrConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.removeClass('button').addClass('button-success').text('Restored');
                    button.closest('.mmr-file-item').addClass('mmr-restored');
                } else {
                    button.prop('disabled', false).text('Failed');
                    alert('Restore failed: ' + response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Error');
                alert('An error occurred during restore.');
            }
        });
    }

    // Restore all files
    function restoreAllFiles() {
        const button = $('.mmr-restore-all');
        const fileItems = $('.mmr-file-item');

        button.prop('disabled', true).text('Restoring All...');

        // Collect all file paths
        const filePaths = [];
        fileItems.each(function() {
            const filePath = $(this).find('.mmr-restore-single').data('file');
            if (filePath) filePaths.push(filePath);
        });

        $.ajax({
            url: mmrConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'mmr_pro_restore_bulk',
                file_paths: filePaths,
                nonce: mmrConfig.nonce
            },
            success: function(response) {
                if (response.success) {
                    button.text('All Restored');
                    fileItems.addClass('mmr-restored');
                    fileItems.find('.mmr-restore-single').each(function() {
                        $(this).removeClass('button').addClass('button-success').text('Restored').prop('disabled', true);
                    });
                } else {
                    button.prop('disabled', false).text('Failed');
                    alert('Bulk restore failed: ' + response.data.message);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Error');
                alert('An error occurred during bulk restore.');
            }
        });
    }

    // Initialize pro features when document is ready
    $(document).ready(function() {
        initProScanner();
    });

})(jQuery);
