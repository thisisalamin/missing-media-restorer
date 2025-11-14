/**
 * Missing Media Restorer - Admin JavaScript
 * Handles scan, upload, and batch restore operations via AJAX
 *
 * @package MissingMediaRestorer
 */

(function() {
	'use strict';

	// Global state for the plugin
	const mmrState = {
		scanning: false,
		restoring: false,
		currentBatch: 1,
		totalBatches: 0,
		batchSize: 20,
		totalFiles: 0,
		filesRestored: 0,
		uploadedFiles: [],
		missingFiles: [],
	};

	/**
	 * Initialize the plugin on page load
	 */
	function mmrInit() {
		attachEventListeners();
	}

	/**
	 * Attach event listeners to buttons and dropzone
	 */
	function attachEventListeners() {
		const scanBtn = document.getElementById( 'mmr-scan-btn' );
		const restoreBtn = document.getElementById( 'mmr-restore-btn' );
		const clearTempBtn = document.getElementById( 'mmr-clear-temp-btn' );
		const dropzone = document.getElementById( 'mmr-upload-dropzone' );
		const fileInput = document.getElementById( 'mmr-file-input' );
		const filesInput = document.getElementById( 'mmr-files-input' );
		const selectFilesBtn = document.getElementById( 'mmr-select-files-btn' );
		const selectFolderBtn = document.getElementById( 'mmr-select-folder-btn' );

		if ( scanBtn ) {
			scanBtn.addEventListener( 'click', startScan );
		}

		if ( restoreBtn ) {
			restoreBtn.addEventListener( 'click', startBatchRestore );
		}

		if ( clearTempBtn ) {
			clearTempBtn.addEventListener( 'click', clearTemporaryFiles );
		}

		// Dropzone events
		if ( dropzone ) {
			dropzone.addEventListener( 'dragover', handleDragOver );
			dropzone.addEventListener( 'dragleave', handleDragLeave );
			dropzone.addEventListener( 'drop', handleDrop );
		}

		// File input events
		if ( fileInput ) {
			fileInput.addEventListener( 'change', handleFileSelect );
		}

		if ( filesInput ) {
			filesInput.addEventListener( 'change', handleFileSelect );
		}

		// Button events
		if ( selectFilesBtn ) {
			selectFilesBtn.addEventListener( 'click', () => {
				if ( filesInput ) {
					filesInput.click();
				}
			} );
		}

		if ( selectFolderBtn ) {
			selectFolderBtn.addEventListener( 'click', () => {
				if ( fileInput ) {
					fileInput.click();
				}
			} );
		}
	}

	/**
	 * Start scanning for missing files
	 */
	function startScan() {
		if ( mmrState.scanning ) {
			return;
		}

		mmrState.scanning = true;
		const scanBtn = document.getElementById( 'mmr-scan-btn' );
		scanBtn.disabled = true;
		scanBtn.textContent = 'Scanning...';

		// Make AJAX request to scan for missing files
		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', mmrConfig.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		const data = new URLSearchParams();
		data.append( 'action', 'mmr_scan_missing_files' );
		data.append( 'nonce', mmrConfig.nonce );

		xhr.onload = function() {
			if ( xhr.status === 200 ) {
				try {
					const response = JSON.parse( xhr.responseText );
					handleScanResponse( response );
				} catch ( e ) {
					console.error( 'Error parsing scan response:', e );
					showError( 'Failed to parse scan response' );
				}
			} else {
				showError( 'Scan request failed with status ' + xhr.status );
			}

			mmrState.scanning = false;
			scanBtn.disabled = false;
			scanBtn.textContent = 'Start Scan';
		};

		xhr.onerror = function() {
			showError( 'Network error during scan' );
			mmrState.scanning = false;
			scanBtn.disabled = false;
			scanBtn.textContent = 'Start Scan';
		};

		xhr.send( data );
	}

	/**
	 * Handle scan response
	 *
	 * @param {Object} response - The response from the server
	 */
	function handleScanResponse( response ) {
		if ( response.success ) {
			const data = response.data;
			const missingFiles = data.missing_files || [];
			const existingFiles = data.existing_files || [];

			mmrState.missingFiles = missingFiles;
			mmrState.totalFiles = data.missing_count || 0;

			// Calculate total batches needed
			mmrState.totalBatches = Math.ceil( mmrState.totalFiles / mmrState.batchSize );

			// Display results
			const resultsDiv = document.getElementById( 'mmr-scan-results' );
			const outputDiv = document.getElementById( 'mmr-scan-output' );

			if ( resultsDiv ) {
				resultsDiv.style.display = 'block';
				let html = '<div style="margin-bottom: 20px;">';
				html += '<p><strong style="font-size: 16px; color: #23282d;">Scan Summary:</strong></p>';
				html += '<p style="margin: 5px 0;"><strong style="color: #28a745;">✓ Existing Files:</strong> ' + existingFiles.length + '</p>';
				html += '<p style="margin: 5px 0;"><strong style="color: #dc3545;">✗ Missing Files:</strong> ' + missingFiles.length + '</p>';
				html += '<p style="margin: 5px 0;"><strong style="color: #0073aa;">Total Files:</strong> ' + data.total_count + '</p>';

				if ( missingFiles.length > 0 ) {
					html += '<p style="margin-top: 10px; color: #666;"><em>Missing files will be restored in ' + mmrState.totalBatches + ' batches of ' + mmrState.batchSize + ' files each.</em></p>';
				}

				html += '</div>';

				// Display existing files (green)
				if ( existingFiles.length > 0 ) {
					html += '<div style="margin-top: 20px;">';
					html += '<h4 style="color: #28a745; margin-bottom: 10px;">✓ Available Files (' + existingFiles.length + '):</h4>';
					html += '<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px; max-height: 250px; overflow-y: auto;">';
					html += '<ul style="list-style: none; padding: 0; margin: 0;">';

					existingFiles.forEach( function( file ) {
						html += '<li style="padding: 8px 0; border-bottom: 1px solid #c3e6cb; font-size: 13px;">';
						html += '<strong style="color: #155724;">✓ ' + file.filename + '</strong><br>';
						html += '<small style="color: #0c5460;">📁 ' + file.full_path + '</small>';
						html += '</li>';
					} );

					html += '</ul>';
					html += '</div>';
					html += '</div>';
				}

				// Display missing files (red)
				if ( missingFiles.length > 0 ) {
					html += '<div style="margin-top: 20px;">';
					html += '<h4 style="color: #dc3545; margin-bottom: 10px;">✗ Missing Files (' + missingFiles.length + '):</h4>';
					html += '<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
					html += '<ul style="list-style: none; padding: 0; margin: 0;">';

					missingFiles.forEach( function( file ) {
						html += '<li style="padding: 8px 0; border-bottom: 1px solid #f5c6cb; font-size: 13px;">';
						html += '<strong style="color: #721c24;">✗ ' + file.filename + '</strong><br>';
						html += '<small style="color: #721c24;">📁 ' + file.full_path + '</small>';
						html += '</li>';
					} );

					html += '</ul>';
					html += '</div>';
					html += '</div>';
				}

				outputDiv.innerHTML = html;

				// Enable restore button only if there are missing files
				const restoreBtn = document.getElementById( 'mmr-restore-btn' );
				if ( restoreBtn && missingFiles.length > 0 ) {
					restoreBtn.disabled = false;
				}
			}

			console.log( 'Scan complete:', response.data );
		} else {
			showError( response.data?.message || 'Scan failed' );
		}
	}

	/**
	 * Start the batch restore process
	 */
	function startBatchRestore() {
		if ( mmrState.restoring || mmrState.totalFiles === 0 ) {
			return;
		}

		mmrState.restoring = true;
		mmrState.currentBatch = 1;
		mmrState.filesRestored = 0;

		const restoreBtn = document.getElementById( 'mmr-restore-btn' );
		const progressContainer = document.getElementById( 'mmr-progress-container' );

		if ( restoreBtn ) {
			restoreBtn.disabled = true;
		}

		if ( progressContainer ) {
			progressContainer.style.display = 'block';
		}

		// Start processing batches
		processBatch();
	}

	/**
	 * Process a single batch of files
	 */
	function processBatch() {
		if ( mmrState.currentBatch > mmrState.totalBatches ) {
			// All batches completed
			completeBatchRestore();
			return;
		}

		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', mmrConfig.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		const data = new URLSearchParams();
		data.append( 'action', 'mmr_batch_restore' );
		data.append( 'nonce', mmrConfig.nonce );
		data.append( 'batch', mmrState.currentBatch );
		data.append( 'batch_size', mmrState.batchSize );

		xhr.onload = function() {
			if ( xhr.status === 200 ) {
				try {
					const response = JSON.parse( xhr.responseText );
					handleBatchResponse( response );
				} catch ( e ) {
					console.error( 'Error parsing batch response:', e );
					showError( 'Failed to parse batch response' );
					completeBatchRestore();
				}
			} else {
				showError( 'Batch request failed with status ' + xhr.status );
				completeBatchRestore();
			}
		};

		xhr.onerror = function() {
			showError( 'Network error during batch processing' );
			completeBatchRestore();
		};

		xhr.send( data );
	}

	/**
	 * Handle batch restore response
	 *
	 * @param {Object} response - The response from the server
	 */
	function handleBatchResponse( response ) {
		if ( response.success ) {
			const data = response.data;
			const filesProcessed = data.files_processed || 0;

			mmrState.filesRestored += filesProcessed;

			// Update progress display
			updateProgress();

			// Log processed files with detailed info
			if ( data.processed_files && Array.isArray( data.processed_files ) ) {
				const outputDiv = document.getElementById( 'mmr-restore-output' );
				if ( outputDiv ) {
					data.processed_files.forEach( function( file ) {
						const logEntry = document.createElement( 'div' );
						logEntry.className = 'mmr-log-entry mmr-' + file.status;
						let message = '[' + file.status.toUpperCase() + '] ' + file.path;
						if ( file.message ) {
							message += ' - ' + file.message;
						}
						logEntry.textContent = message;
						outputDiv.appendChild( logEntry );
						outputDiv.scrollTop = outputDiv.scrollHeight;
					} );
				}
			}

			// Check if complete or continue
			if ( data.is_complete || mmrState.currentBatch >= mmrState.totalBatches ) {
				completeBatchRestore();
			} else {
				// Process next batch after a small delay
				mmrState.currentBatch++;
				setTimeout( processBatch, 500 );
			}
		} else {
			showError( data?.message || 'Batch restore failed' );
			completeBatchRestore();
		}
	}

	/**
	 * Update progress display
	 */
	function updateProgress() {
		const progressFill = document.getElementById( 'mmr-progress-fill' );
		const progressText = document.getElementById( 'mmr-progress-text' );

		if ( progressFill || progressText ) {
			const percentage = ( mmrState.filesRestored / mmrState.totalFiles ) * 100;

			if ( progressFill ) {
				progressFill.style.width = Math.min( percentage, 100 ) + '%';
			}

			if ( progressText ) {
				progressText.textContent = 'Restored ' + mmrState.filesRestored + ' of ' + mmrState.totalFiles + ' files (' + Math.round( percentage ) + '%)';
			}
		}
	}

	/**
	 * Complete the batch restore process
	 */
	function completeBatchRestore() {
		mmrState.restoring = false;

		const restoreBtn = document.getElementById( 'mmr-restore-btn' );
		if ( restoreBtn ) {
			restoreBtn.disabled = false;
		}

		const clearTempBtn = document.getElementById( 'mmr-clear-temp-btn' );
		if ( clearTempBtn ) {
			clearTempBtn.style.display = 'inline-block';
		}

		const progressText = document.getElementById( 'mmr-progress-text' );
		if ( progressText ) {
			progressText.textContent = 'Restore complete! ' + mmrState.filesRestored + ' of ' + mmrState.totalFiles + ' files restored.';
			progressText.style.color = '#28a745';
		}

		console.log( 'Batch restore complete' );
	}

	/**
	 * Handle dragover event for upload dropzone
	 */
	function handleDragOver( e ) {
		e.preventDefault();
		e.stopPropagation();
		e.currentTarget.classList.add( 'mmr-dragover' );
	}

	/**
	 * Handle dragleave event for upload dropzone
	 */
	function handleDragLeave( e ) {
		e.preventDefault();
		e.stopPropagation();
		e.currentTarget.classList.remove( 'mmr-dragover' );
	}

	/**
	 * Handle drop event for upload dropzone
	 */
	function handleDrop( e ) {
		e.preventDefault();
		e.stopPropagation();
		e.currentTarget.classList.remove( 'mmr-dragover' );

		const files = e.dataTransfer.files;
		handleFileUpload( files );
	}

	/**
	 * Handle file selection via input
	 */
	function handleFileSelect( e ) {
		const files = e.target.files;
		if ( files.length > 0 ) {
			startChunkedUpload( Array.from( files ) );
		}

		// Reset file input
		e.target.value = '';
	}

	/**
	 * Start chunked upload of files
	 *
	 * @param {Array} files - Array of files to upload
	 */
	function startChunkedUpload( files ) {
		if ( files.length === 0 ) {
			return;
		}

		mmrState.uploadedFiles = files;
		const chunkSize = 20; // Upload 20 files per chunk
		const totalChunks = Math.ceil( files.length / chunkSize );

		console.log( 'Starting chunked upload: ' + files.length + ' files in ' + totalChunks + ' chunks of ' + chunkSize + ' files each' );

		// Show uploading state
		const uploadList = document.getElementById( 'mmr-upload-list' );
		if ( uploadList ) {
			uploadList.innerHTML = '<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; border-radius: 4px;"><p style="margin: 0; color: #0073aa;">📤 Uploading files in batches... (0/' + totalChunks + ' chunks completed)</p></div>';
		}

		// Upload first chunk
		uploadChunk( files, 0, chunkSize, totalChunks );
	}

	/**
	 * Upload a single chunk of files
	 *
	 * @param {Array} allFiles - All files to upload
	 * @param {number} chunkIndex - Current chunk index
	 * @param {number} chunkSize - Number of files per chunk
	 * @param {number} totalChunks - Total number of chunks
	 */
	function uploadChunk( allFiles, chunkIndex, chunkSize, totalChunks ) {
		const startIndex = chunkIndex * chunkSize;
		const endIndex = Math.min( startIndex + chunkSize, allFiles.length );
		const chunkFiles = allFiles.slice( startIndex, endIndex );

		console.log( 'Uploading chunk ' + ( chunkIndex + 1 ) + ' of ' + totalChunks + ' with ' + chunkFiles.length + ' files' );

		// Create FormData for this chunk
		const formData = new FormData();
		formData.append( 'action', 'mmr_upload_files' );
		formData.append( 'nonce', mmrConfig.nonce );
		formData.append( 'chunk', chunkIndex + 1 );
		formData.append( 'total_chunks', totalChunks );

		// Add each file in this chunk to FormData
		chunkFiles.forEach( function( file ) {
			formData.append( 'files[]', file );
		} );

		// Send chunk via AJAX
		const xhr = new XMLHttpRequest();
		xhr.timeout = 300000; // 5 minute timeout
		xhr.open( 'POST', mmrConfig.ajaxUrl, true );

		xhr.onload = function() {
			console.log( 'Chunk ' + ( chunkIndex + 1 ) + ' response received. Status:', xhr.status );

			if ( xhr.status === 200 ) {
				try {
					// Extract JSON from response (remove PHP warnings if present)
					const responseText = xhr.responseText.trim();
					const jsonStart = responseText.indexOf( '{' );
					const jsonEnd = responseText.lastIndexOf( '}' ) + 1;

					if ( jsonStart !== -1 && jsonEnd > jsonStart ) {
						const jsonString = responseText.substring( jsonStart, jsonEnd );
						const response = JSON.parse( jsonString );
						console.log( 'Chunk ' + ( chunkIndex + 1 ) + ' parsed successfully' );

						if ( response.success ) {
							// Update progress
							const uploadList = document.getElementById( 'mmr-upload-list' );
							if ( uploadList ) {
								const percentComplete = ( ( chunkIndex + 1 ) / totalChunks ) * 100;
								uploadList.innerHTML = '<div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; border-radius: 4px;"><p style="margin: 0; color: #0073aa;">📤 Uploading files in batches... (' + ( chunkIndex + 1 ) + '/' + totalChunks + ' chunks completed - ' + Math.round( percentComplete ) + '%)</p></div>';
							}

							// If this was the last chunk, process all responses
							if ( chunkIndex + 1 >= totalChunks ) {
								console.log( 'All chunks uploaded successfully' );
								// Fetch all uploaded files
								getAllUploadedFiles();
							} else {
								// Upload next chunk
								setTimeout( function() {
									uploadChunk( allFiles, chunkIndex + 1, chunkSize, totalChunks );
								}, 500 );
							}
						} else {
							showUploadError( 'Chunk ' + ( chunkIndex + 1 ) + ' failed: ' + ( response.data?.message || 'Unknown error' ) );
						}
					} else {
						console.error( 'Could not find JSON in response' );
						showUploadError( 'Invalid server response format for chunk ' + ( chunkIndex + 1 ) );
					}
				} catch ( e ) {
					console.error( 'JSON Parse Error for chunk ' + ( chunkIndex + 1 ) + ':', e );
					showUploadError( 'Failed to parse response for chunk ' + ( chunkIndex + 1 ) );
				}
			} else {
				console.error( 'HTTP Error for chunk ' + ( chunkIndex + 1 ) + ':', xhr.status, xhr.statusText );
				showUploadError( 'Chunk ' + ( chunkIndex + 1 ) + ' upload failed with HTTP ' + xhr.status );
			}
		};

		xhr.onerror = function() {
			console.error( 'Network error during chunk ' + ( chunkIndex + 1 ) + ' upload' );
			showUploadError( 'Network error uploading chunk ' + ( chunkIndex + 1 ) + '. Please try again.' );
		};

		xhr.ontimeout = function() {
			console.error( 'Upload timeout for chunk ' + ( chunkIndex + 1 ) );
			showUploadError( 'Chunk ' + ( chunkIndex + 1 ) + ' upload timed out. Please try again.' );
		};

		xhr.send( formData );
	}

	/**
	 * Get list of all uploaded files from temp directory
	 */
	function getAllUploadedFiles() {
		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', mmrConfig.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		const data = new URLSearchParams();
		data.append( 'action', 'mmr_get_available_files' );
		data.append( 'nonce', mmrConfig.nonce );

		xhr.onload = function() {
			if ( xhr.status === 200 ) {
				try {
					const responseText = xhr.responseText.trim();
					const jsonStart = responseText.indexOf( '{' );
					const jsonEnd = responseText.lastIndexOf( '}' ) + 1;

					if ( jsonStart !== -1 && jsonEnd > jsonStart ) {
						const jsonString = responseText.substring( jsonStart, jsonEnd );
						const response = JSON.parse( jsonString );

						if ( response.success ) {
							const uploadedFiles = response.data.files || [];
							displayUploadSummary( uploadedFiles );
						}
					}
				} catch ( e ) {
					console.error( 'Error fetching uploaded files:', e );
				}
			}
		};

		xhr.send( data );
	}

	/**
	 * Display upload summary
	 *
	 * @param {Array} uploadedFiles - List of uploaded files
	 */
	function displayUploadSummary( uploadedFiles ) {
		const uploadList = document.getElementById( 'mmr-upload-list' );

		if ( uploadList ) {
			let html = '<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px;">';
			html += '<p style="margin: 0 0 10px 0; color: #155724;"><strong>✓ Upload Complete!</strong></p>';
			html += '<p style="margin: 0; color: #155724;"><strong>' + uploadedFiles.length + ' file(s)</strong> uploaded and ready to restore.</p>';

			if ( uploadedFiles.length > 0 ) {
				html += '<p style="margin: 10px 0 0 0; font-size: 12px; color: #0c5460;">Files are stored in: /mmr-temp/</p>';
				html += '<details style="margin-top: 10px;"><summary style="cursor: pointer; color: #155724;">View uploaded files (' + uploadedFiles.length + ')</summary>';
				html += '<div style="margin-top: 10px; max-height: 250px; overflow-y: auto;">';
				html += '<ul style="list-style: none; padding: 0; margin: 0;">';

				uploadedFiles.forEach( function( file ) {
					html += '<li style="padding: 4px 0; font-size: 12px; border-bottom: 1px solid #c3e6cb;">';
					html += '<strong>' + file.name + '</strong> (' + formatFileSize( file.size ) + ')';
					html += '</li>';
				} );

				html += '</ul>';
				html += '</div>';
				html += '</details>';
			}

			html += '</div>';

			// Also show comparison with missing files
			if ( mmrState.missingFiles.length > 0 ) {
				html += '<div style="margin-top: 15px;">';
				html += '<h5 style="color: #ffc107; margin-bottom: 10px;">! File Matching Status:</h5>';
				html += '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; max-height: 250px; overflow-y: auto;">';
				html += '<ul style="list-style: none; padding: 0; margin: 0;">';

				let matchedCount = 0;
				mmrState.missingFiles.forEach( function( missingFile ) {
					const isAvailable = uploadedFiles.some( f => f.name === missingFile.filename );
					if ( isAvailable ) {
						matchedCount++;
						html += '<li style="padding: 6px 0; border-bottom: 1px solid #ffe69c; font-size: 12px;">';
						html += '<span style="color: #28a745; font-weight: bold;">✓</span> ' + missingFile.filename;
						html += '</li>';
					} else {
						html += '<li style="padding: 6px 0; border-bottom: 1px solid #ffe69c; font-size: 12px;">';
						html += '<span style="color: #dc3545; font-weight: bold;">✗</span> ' + missingFile.filename;
						html += '</li>';
					}
				} );

				html += '</ul>';
				html += '</div>';
				html += '<p style="margin-top: 10px; font-size: 12px; color: #666;"><strong>' + matchedCount + '</strong> of <strong>' + mmrState.missingFiles.length + '</strong> missing files are available to restore.</p>';
				html += '</div>';
			}

			uploadList.innerHTML = html;
		}
	}	/**
	 * Handle upload response
	 *
	 * @param {Object} response - The response from the server
	 */
	function handleUploadResponse( response ) {
		const uploadList = document.getElementById( 'mmr-upload-list' );

		if ( response.success ) {
			const data = response.data;
			let html = '<h4>Upload Results:</h4>';
			html += '<p><strong>' + data.uploaded_count + ' file(s) uploaded successfully</strong></p>';

			if ( data.uploaded_files.length > 0 ) {
				html += '<div style="margin-top: 15px;">';
				html += '<h5 style="color: #28a745; margin-bottom: 10px;">✓ Available Files:</h5>';
				html += '<div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 4px; max-height: 250px; overflow-y: auto;">';
				html += '<ul style="list-style: none; padding: 0; margin: 0;">';
				data.uploaded_files.forEach( function( file ) {
					html += '<li style="padding: 6px 0; border-bottom: 1px solid #c3e6cb; font-size: 13px;">';
					html += '<strong style="color: #155724;">' + file.name + '</strong>';
					html += ' <small style="color: #0c5460;">(' + formatFileSize( file.size ) + ')</small>';
					html += '</li>';
				} );
				html += '</ul>';
				html += '</div>';
				html += '</div>';
			}

			if ( mmrState.missingFiles.length > 0 ) {
				html += '<div style="margin-top: 15px;">';
				html += '<h5 style="color: #ffc107; margin-bottom: 10px;">! Missing Files (Need to Upload):</h5>';
				html += '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px; max-height: 250px; overflow-y: auto;">';
				html += '<ul style="list-style: none; padding: 0; margin: 0;">';
				mmrState.missingFiles.forEach( function( file ) {
					const isAvailable = data.uploaded_files.some( f => f.name === file.filename );
					const status = isAvailable ? '✓' : '✗';
					const statusColor = isAvailable ? '#28a745' : '#dc3545';
					html += '<li style="padding: 6px 0; border-bottom: 1px solid #ffe69c; font-size: 13px;">';
					html += '<span style="color: ' + statusColor + '; font-weight: bold;">' + status + '</span> ';
					html += '<strong style="color: #856404;">' + file.filename + '</strong>';
					html += ' <small style="color: #664d03;">(' + file.full_path + ')</small>';
					html += '</li>';
				} );
				html += '</ul>';
				html += '</div>';
				html += '</div>';
			}

			if ( data.failed_files.length > 0 ) {
				html += '<div style="margin-top: 15px;">';
				html += '<h5 style="color: #dc3545; margin-bottom: 10px;">✗ Failed Files:</h5>';
				html += '<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; border-radius: 4px;">';
				html += '<ul style="list-style: none; padding: 0; margin: 0;">';
				data.failed_files.forEach( function( file ) {
					html += '<li style="padding: 6px 0; border-bottom: 1px solid #f5c6cb; font-size: 13px;">';
					html += '<strong style="color: #721c24;">' + file.name + '</strong> - ' + file.error;
					html += '</li>';
				} );
				html += '</ul>';
				html += '</div>';
				html += '</div>';
			}

			if ( uploadList ) {
				uploadList.innerHTML = html;
			}

			console.log( 'Upload complete:', data );
		} else {
			showUploadError( response.data?.message || 'Upload failed' );
		}
	}

	/**
	 * Show upload error message
	 *
	 * @param {string} message - The error message
	 */
	function showUploadError( message ) {
		const uploadList = document.getElementById( 'mmr-upload-list' );
		if ( uploadList ) {
			uploadList.innerHTML = '<p style="color: #dc3545;"><strong>Error:</strong> ' + message + '</p>';
		}
		console.error( 'Upload Error:', message );
	}

	/**
	 * Clear temporary files from the server
	 */
	function clearTemporaryFiles() {
		const clearTempBtn = document.getElementById( 'mmr-clear-temp-btn' );
		if ( clearTempBtn ) {
			clearTempBtn.disabled = true;
			clearTempBtn.textContent = 'Clearing...';
		}

		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', mmrConfig.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );

		const data = new URLSearchParams();
		data.append( 'action', 'mmr_clear_temp_files' );
		data.append( 'nonce', mmrConfig.nonce );

		xhr.onload = function() {
			if ( xhr.status === 200 ) {
				try {
					const response = JSON.parse( xhr.responseText );
					if ( response.success ) {
						const outputDiv = document.getElementById( 'mmr-restore-output' );
						if ( outputDiv ) {
							const logEntry = document.createElement( 'div' );
							logEntry.className = 'mmr-log-entry mmr-restored';
							logEntry.textContent = 'Cleared ' + response.data.cleared_count + ' temporary file(s)';
							outputDiv.appendChild( logEntry );
						}
					} else {
						showError( response.data?.message || 'Failed to clear temporary files' );
					}
				} catch ( e ) {
					console.error( 'Error parsing clear response:', e );
					showError( 'Failed to parse clear response' );
				}
			} else {
				showError( 'Clear request failed with status ' + xhr.status );
			}

			if ( clearTempBtn ) {
				clearTempBtn.disabled = false;
				clearTempBtn.textContent = 'Clear Temporary Files';
			}
		};

		xhr.onerror = function() {
			showError( 'Network error during clear operation' );
			if ( clearTempBtn ) {
				clearTempBtn.disabled = false;
				clearTempBtn.textContent = 'Clear Temporary Files';
			}
		};

		xhr.send( data );
	}

	/**
	 * Format file size for display
	 *
	 * @param {number} bytes - The file size in bytes
	 * @returns {string} Formatted file size
	 */
	function formatFileSize( bytes ) {
		if ( bytes === 0 ) return '0 Bytes';

		const k = 1024;
		const sizes = [ 'Bytes', 'KB', 'MB', 'GB' ];
		const i = Math.floor( Math.log( bytes ) / Math.log( k ) );

		return Math.round( ( bytes / Math.pow( k, i ) ) * 100 ) / 100 + ' ' + sizes[ i ];
	}

	/**
	 * Show error message to user
	 *
	 * @param {string} message - The error message
	 */
	function showError( message ) {
		const progressText = document.getElementById( 'mmr-progress-text' );
		const outputDiv = document.getElementById( 'mmr-restore-output' );

		if ( progressText ) {
			progressText.textContent = 'Error: ' + message;
			progressText.style.color = '#dc3545';
		}

		if ( outputDiv ) {
			const errorEntry = document.createElement( 'div' );
			errorEntry.className = 'mmr-log-entry mmr-error';
			errorEntry.textContent = 'ERROR: ' + message;
			outputDiv.appendChild( errorEntry );
		}

		console.error( 'MMR Error:', message );
	}

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', mmrInit );
	} else {
		mmrInit();
	}

})();
