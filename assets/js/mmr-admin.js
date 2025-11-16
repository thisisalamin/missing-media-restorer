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
		scanProgressInterval: null,
	};

	/**
	 * Initialize the plugin on page load
	 */
	function mmrInit() {
		console.log( 'MMR initializing...' );
		console.log( 'mmrConfig available:', typeof mmrConfig !== 'undefined' ? mmrConfig : 'NOT FOUND' );
		attachEventListeners();
		// Ensure indicator matches initial step
		updateStepIndicator( 1 );
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
		const refreshFilesBtn = document.getElementById( 'mmr-refresh-files-btn' );
		const newScanBtn = document.getElementById( 'mmr-new-scan-btn' );
		const proBtn = document.getElementById( 'mmr-btn-pro' );
		const closeProModalBtn = document.getElementById( 'mmr-close-pro-modal' );

		console.log( 'Attaching event listeners. Scan button found:', !!scanBtn );
		if ( scanBtn ) {
			scanBtn.addEventListener( 'click', startScan );
			console.log( 'Scan button event listener attached' );
		}

		// Step navigation buttons

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

		// Refresh files button - check for files already uploaded via FTP
		if ( refreshFilesBtn ) {
			refreshFilesBtn.addEventListener( 'click', refreshFilesList );
		}

	// Run Scan button - start a new scan from anywhere
	if ( newScanBtn ) {
		newScanBtn.addEventListener( 'click', function() {
			console.log( 'Run Scan button clicked from header' );
			startNewScan();
		} );
	}		// Pro modal events
		if ( proBtn ) {
			proBtn.addEventListener( 'click', showProModal );
		}

		if ( closeProModalBtn ) {
			closeProModalBtn.addEventListener( 'click', hideProModal );
		}

		// Close modal when clicking outside
		document.addEventListener( 'click', function( e ) {
			const modal = document.getElementById( 'mmr-pro-modal' );
			if ( modal && e.target === modal ) {
				hideProModal();
			}
		} );

		// Close modal on Escape key
		document.addEventListener( 'keydown', function( e ) {
			if ( e.key === 'Escape' ) {
				hideProModal();
			}
		} );

		const continueMatchBtn = document.getElementById( 'mmr-continue-match-btn' );
		if ( continueMatchBtn ) {
			continueMatchBtn.addEventListener( 'click', function() {
				navigateToStep( 3 );
				// prepare match summary (if already available)
				prepareMatchSummary();
			} );
		}

		const backToUploadBtn = document.getElementById( 'mmr-back-to-upload-btn' );
		if ( backToUploadBtn ) {
			backToUploadBtn.addEventListener( 'click', function() {
				navigateToStep( 2 );
			} );
		}

		const continueRestoreBtn = document.getElementById( 'mmr-continue-restore-btn' );
		if ( continueRestoreBtn ) {
			continueRestoreBtn.addEventListener( 'click', function() {
				navigateToStep( 4 );
			} );
		}
	}

	/**
	 * Start scanning for missing files
	 */
	function startScan() {
		console.log( 'Start scan button clicked' );
		if ( mmrState.scanning ) {
			return;
		}

		mmrState.scanning = true;
		const scanBtn = document.getElementById( 'mmr-scan-btn' );
		if ( scanBtn ) {
			scanBtn.disabled = true;
			scanBtn.innerHTML = '<span class="dashicons dashicons-search"></span> Scanning...';
		}

		// Show (indeterminate) scan progress UI
		showScanProgress();

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

			// finalize indeterminate scan progress
			completeScanProgress();
			mmrState.scanning = false;
			if ( scanBtn ) {
				scanBtn.disabled = false;
				scanBtn.innerHTML = '<span class="dashicons dashicons-search"></span> Start Scanning';
			}
		};

		xhr.onerror = function() {
			showError( 'Network error during scan' );
			completeScanProgress( true );
			mmrState.scanning = false;
			if ( scanBtn ) {
				scanBtn.disabled = false;
				scanBtn.innerHTML = '<span class="dashicons dashicons-search"></span> Start Scanning';
			}
		};

		xhr.send( data );
	}

/**
 * Show a simple indeterminate scan progress animation while scanning
 */
function showScanProgress() {
	const container = document.getElementById( 'mmr-scan-progress-container' );
	const fill = document.getElementById( 'mmr-scan-progress-fill' );
	const text = document.getElementById( 'mmr-scan-progress-text' );

	if ( ! container || ! fill || ! text ) {
		return;
	}

	// Ensure container is visible
	container.classList.remove( 'hidden' );
	fill.style.width = '6%';
	text.textContent = 'Scanning…';
	text.style.color = '#64748b';

	// create an indeterminate animated progress - increases until 70% then resets
	let width = 6;
	mmrState.scanProgressInterval = setInterval( function() {
		width += Math.random() * 8;
		if ( width > 72 ) {
			width = 25; // loop a little to keep the motion fair
		}
		fill.style.width = Math.min( width, 72 ) + '%';
	}, 450 );
}

	/**
	 * Stop and finalize the scan progress UI
	 * @param {boolean} error - if true, show error state
	 */
	function completeScanProgress( error ) {
		const container = document.getElementById( 'mmr-scan-progress-container' );
		const fill = document.getElementById( 'mmr-scan-progress-fill' );
		const text = document.getElementById( 'mmr-scan-progress-text' );

		if ( mmrState.scanProgressInterval ) {
			clearInterval( mmrState.scanProgressInterval );
			mmrState.scanProgressInterval = null;
		}

		if ( ! container || ! fill || ! text ) {
			return;
		}

		if ( error ) {
			text.textContent = 'Scan failed';
			text.style.color = '#dc3545';
			fill.style.width = '100%';
			fill.style.background = 'linear-gradient(90deg, #ef4444, #f97316)';
			// hide after short delay
			setTimeout( function() {
				container.classList.add( 'hidden' );
				fill.style.width = '0%';
				fill.style.background = '';
			}, 2200 );
			return;
		}

		// hide immediately on success
		container.classList.add( 'hidden' );
	}	/**
	 * Handle scan response
	 *
	 * @param {Object} response - The response from the server
	 */
	function handleScanResponse( response ) {
		console.log( 'Scan response:', response );
		if ( response.success ) {
			const data = response.data;
			const missingFiles = data.missing_files || [];
			const existingFiles = data.existing_files || [];

			mmrState.missingFiles = missingFiles;
			// Total files for restore should be the count of missing files
			mmrState.totalFiles = missingFiles.length || 0;

			// Calculate total batches needed
			mmrState.totalBatches = Math.ceil( mmrState.totalFiles / mmrState.batchSize );

			// Display results
			const resultsDiv = document.getElementById( 'mmr-scan-results' );
			const outputDiv = document.getElementById( 'mmr-scan-output' );

			if ( resultsDiv && outputDiv ) {
				resultsDiv.classList.remove( 'hidden' );
				let html = '';

				// Scan Summary Section with simple but noticeable styling
				html += '<div class="mmr-scan-summary mb-4">';
				html += '<div class="flex flex-col gap-3">';
				html += '<div class="flex items-center justify-between flex-wrap gap-2">';
				html += '<span class="mmr-scan-label">';
				html += '<span class="dashicons dashicons-search text-[14px]"></span>';
				html += '<span>Scan Summary</span>';
				html += '</span>';
				html += '<div class="flex flex-wrap items-center gap-2 text-[11px]">';
				const totalCount = data.total_count || ( existingFiles.length + missingFiles.length );
				const existingCount = existingFiles.length;
				const missingCount = missingFiles.length;
				const batches = mmrState.totalBatches || Math.ceil( missingCount / mmrState.batchSize );

				html += '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200 shadow-sm"><span class="dashicons dashicons-admin-media text-[12px]"></span><span class="font-semibold">' + totalCount + '</span> total items</span>';
				html += '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-green-100 text-green-700 border border-green-200 shadow-sm"><span class="dashicons dashicons-yes text-green-600 text-[12px]"></span><span class="font-semibold">' + existingCount + '</span> existing</span>';
				html += '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-red-100 text-red-700 border border-red-200 shadow-sm"><span class="dashicons dashicons-no text-[12px]"></span><span class="font-semibold">' + missingCount + '</span> missing</span>';
				html += '<span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200 shadow-sm"><span class="dashicons dashicons-update text-[12px]"></span><span class="font-semibold">' + batches + '</span> batches</span>';
				html += '</div>';
				html += '</div>';
				html += '</div>';
				html += '</div>';

				// Existing and Missing Files Cards
				html += '<div class="grid gap-4 md:grid-cols-2 text-[11px]">';

				// Existing files card
				html += '<div class="mmr-scan-result-card mmr-existing-card">';
				html += '<div class="mmr-card-header">';
				html += '<span class="dashicons dashicons-yes text-[14px]"></span>';
				html += '<span>Existing Files (' + existingFiles.length + ')</span>';
				html += '</div>';
				html += '<div class="p-3">';
				if ( existingFiles.length ) {
					html += '<div class="mmr-files-list-container">';
					html += '<ul>';
					existingFiles.forEach( function( file ) {
						html += '<li>';
						html += '<span class="mmr-file-name">' + file.filename + '</span>';
						html += '<span class="mmr-file-path">' + file.full_path + '</span>';
						html += '</li>';
					} );
					html += '</ul>';
					html += '</div>';
				} else {
					html += '<p class="text-emerald-700/80 px-2 py-1">No existing files detected.</p>';
				}
				html += '</div>';
				html += '</div>';

				// Missing files card
				html += '<div class="mmr-scan-result-card mmr-missing-card">';
				html += '<div class="mmr-card-header">';
				html += '<span class="dashicons dashicons-warning text-[14px]"></span>';
				html += '<span>Missing Files (' + missingFiles.length + ')</span>';
				html += '</div>';
				html += '<div class="p-3">';
				if ( missingFiles.length ) {
					html += '<div class="mmr-files-list-container">';
					html += '<ul>';
					missingFiles.forEach( function( file ) {
						html += '<li>';
						html += '<span class="mmr-file-name">' + file.filename + '</span>';
						html += '<span class="mmr-file-path">' + file.full_path + '</span>';
						html += '</li>';
					} );
					html += '</ul>';
					html += '</div>';
				} else {
					html += '<p class="text-red-700/80 px-2 py-1">No missing files detected.</p>';
				}
				html += '</div>';
				html += '</div>';
				html += '</div>';

				// Continue button
				html += '<div class="mt-3 flex items-center justify-between text-[11px]">';
				html += '<p class="text-slate-500">Next, bring in the backup files you want to restore.</p>';
				html += '<button id="mmr-continue-upload-btn" type="button" class="mmr-btn-primary inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-medium">';
				html += '<span>Continue to upload</span>';
				html += '<span class="dashicons dashicons-arrow-right-alt2 text-[13px]"></span>';
				html += '</button>';
				html += '</div>';

				outputDiv.innerHTML = html;

				const continueUploadBtn = document.getElementById( 'mmr-continue-upload-btn' );
				if ( continueUploadBtn ) {
					continueUploadBtn.addEventListener( 'click', function() {
						navigateToStep( 2 );
					} );
				}
			}
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
	 * Navigate to a specific wizard step
	 * @param {number} step
	 */
	function navigateToStep( step ) {
		console.log( 'Navigating to step:', step );
		// hide all step containers
		document.querySelectorAll( '.mmr-step-container' ).forEach( function( el ) {
			el.classList.remove( 'mmr-step-active' );
			el.style.display = 'none';
		} );

		// show requested step
		const target = document.getElementById( 'mmr-step-' + step );
		console.log( 'Target element:', target );
		if ( target ) {
			target.classList.add( 'mmr-step-active' );
			target.style.display = 'block';
		}

		// update indicator
		updateStepIndicator( step );
	}

	/**
	 * Update step indicator visuals
	 */
	function updateStepIndicator( step ) {
		// Update logical step state (for any legacy step markers if present)
		document.querySelectorAll( '.mmr-step' ).forEach( function( el ) {
			const stepNum = parseInt( el.getAttribute( 'data-step' ), 10 );
			el.classList.remove( 'mmr-step-active' );
			el.classList.remove( 'mmr-step-completed' );
			if ( stepNum < step ) {
				el.classList.add( 'mmr-step-completed' );
			} else if ( stepNum === step ) {
				el.classList.add( 'mmr-step-active' );
			}
		} );

		// Update new Tailwind-based step chips
		const stepItems = document.querySelectorAll( 'nav ol li[data-step]' );
		stepItems.forEach( function( li ) {
			const btn = li.querySelector( '.mmr-step-chip' );
			const badge = btn ? btn.querySelector( 'span:first-child' ) : null;
			const thisStep = parseInt( li.getAttribute( 'data-step' ), 10 );

			if ( ! btn ) {
				return;
			}

			btn.classList.remove( 'mmr-step-chip-active' );
			btn.classList.remove( 'mmr-step-chip-inactive' );
			btn.classList.remove( 'bg-slate-900', 'text-slate-50' );
			btn.classList.remove( 'text-slate-500', 'text-slate-700' );

			if ( badge ) {
				badge.classList.remove( 'mmr-step-active-icon', 'mmr-step-inactive-icon' );
				badge.classList.remove( 'border-slate-700', 'border-slate-300' );
			}

			if ( thisStep === step ) {
				btn.classList.add( 'mmr-step-chip-active' );
				if ( badge ) {
					badge.classList.add( 'mmr-step-active-icon' );
				}
			} else {
				btn.classList.add( 'mmr-step-chip-inactive' );
				if ( badge ) {
					badge.classList.add( 'mmr-step-inactive-icon' );
				}
			}
		} );
	}

	/**
	 * Prepare & render match summary in Step 3
	 */
	function prepareMatchSummary() {
		const matchWrap = document.getElementById( 'mmr-match-summary' );
		if ( ! matchWrap ) {
			return;
		}

		const uploadedList = mmrState.uploadedFiles || [];
		const missingList = mmrState.missingFiles || [];

		let matched = 0;
		let unmatched = 0;

		let html = '';

		if ( missingList.length === 0 ) {
			html = '<p class="text-[11px] text-slate-500">No missing files found. Please run a scan first.</p>';
			matchWrap.innerHTML = html;
			return;
		}

		// Find matches by filename (case-insensitive + tolerate common sanitization)
		const matchedItems = [];
		const unmatchedItems = [];

		function normalize( name ) {
			return String( name || '' ).toLowerCase().trim();
		}

		missingList.forEach( function( mf ) {
			const mfName = normalize( mf.filename );
			const mfBase = mfName.replace(/\.[^/.]+$/, '');
			const mfExt = (mfName.indexOf('.') !== -1) ? mfName.split('.').pop() : '';

			const isAvailable = uploadedList.some( function( f ) {
				// uploaded item may be a File object or a simple object with .name
				const uploadedName = normalize( f.name || f );

				if ( uploadedName === mfName ) {
					return true;
				}

				// compare base names and allow common suffixes added during upload (e.g., _1, -1)
				const uploadedBase = uploadedName.replace(/\.[^/.]+$/, '');
				const uploadedExt = (uploadedName.indexOf('.') !== -1) ? uploadedName.split('.').pop() : '';

				if ( uploadedExt && mfExt && uploadedExt === mfExt ) {
					if ( uploadedBase === mfBase ) {
						return true;
					}
					if ( uploadedBase.startsWith( mfBase + '_' ) || uploadedBase.startsWith( mfBase + '-' ) ) {
						return true;
					}
				}

				return false;
			} );

			if ( isAvailable ) {
				matched++;
				matchedItems.push( mf );
			} else {
				unmatched++;
				unmatchedItems.push( mf );
			}
		} );

		// Match Summary Cards (Status Overview)
		html += '<div class="grid gap-4 md:grid-cols-2 mb-4 text-[11px]">';

		// Matched Files Card
		html += '<div class="mmr-match-card mmr-matched-card">';
		html += '<div class="flex items-center justify-between mb-2">';
		html += '<span class="mmr-match-title">';
		html += '<span class="dashicons dashicons-yes-alt text-[14px]"></span>';
		html += '<span>Matched Files</span>';
		html += '</span>';
		html += '<span class="text-[12px] font-bold text-emerald-700">' + matched + '</span>';
		html += '</div>';
		html += '<p class="text-[10px] text-emerald-700/90 leading-relaxed">These files have a backup ready and will be restored in the next step.</p>';
		html += '</div>';

		// Unmatched Files Card
		html += '<div class="mmr-match-card mmr-unmatched-card">';
		html += '<div class="flex items-center justify-between mb-2">';
		html += '<span class="mmr-match-title">';
		html += '<span class="dashicons dashicons-warning text-[14px]"></span>';
		html += '<span>Unmatched Files</span>';
		html += '</span>';
		html += '<span class="text-[12px] font-bold text-amber-700">' + unmatched + '</span>';
		html += '</div>';
		html += '<p class="text-[10px] text-amber-700/90 leading-relaxed">These remain missing until their backup files appear with matching names.</p>';
		html += '</div>';
		html += '</div>';

		// Detailed File Lists
		html += '<div class="grid gap-4 md:grid-cols-2 text-[11px]">';

		if ( matchedItems.length > 0 ) {
			html += '<div>';
			html += '<h4 class="mb-2 text-[11px] font-bold text-emerald-900 flex items-center gap-1">';
			html += '<span class="dashicons dashicons-yes text-[12px] text-emerald-600"></span>';
			html += '<span>Matched Files (' + matchedItems.length + ')</span>';
			html += '</h4>';
			html += '<div class="mmr-files-list-container border-2 border-emerald-200">';
			html += '<ul>';
			matchedItems.forEach( function( it ) {
				html += '<li>';
				html += '<span class="mmr-file-name text-emerald-900">' + it.filename + '</span>';
				html += '<span class="mmr-file-path">' + it.path + '</span>';
				html += '</li>';
			} );
			html += '</ul>';
			html += '</div>';
			html += '</div>';
		}

		if ( unmatchedItems.length > 0 ) {
			html += '<div>';
			html += '<h4 class="mb-2 text-[11px] font-bold text-amber-900 flex items-center gap-1">';
			html += '<span class="dashicons dashicons-warning text-[12px] text-amber-600"></span>';
			html += '<span>Unmatched Files (' + unmatchedItems.length + ')</span>';
			html += '</h4>';
			html += '<div class="mmr-files-list-container border-2 border-amber-200">';
			html += '<ul>';
			unmatchedItems.forEach( function( it ) {
				html += '<li>';
				html += '<span class="mmr-file-name text-amber-900">' + it.filename + '</span>';
				html += '<span class="mmr-file-path">' + it.path + '</span>';
				html += '</li>';
			} );
			html += '</ul>';
			html += '</div>';
			html += '</div>';
		}
		html += '</div>';

		matchWrap.innerHTML = html;
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
							mmrState.uploadedFiles = uploadedFiles;
							// update state and display
							mmrState.uploadedFiles = uploadedFiles;
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
	 * Refresh files list - fetch files already uploaded to /mmr-temp/ via FTP
	 */
	function refreshFilesList() {
		const refreshBtn = document.getElementById( 'mmr-refresh-files-btn' );
		if ( refreshBtn ) {
			refreshBtn.disabled = true;
			refreshBtn.textContent = '🔄 Refreshing...';
		}

		// Fetch files from temp directory
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
							if ( uploadedFiles.length > 0 ) {
								displayUploadSummary( uploadedFiles );
							} else {
								const uploadList = document.getElementById( 'mmr-upload-list' );
								if ( uploadList ) {
									uploadList.innerHTML = '<p style="color: #0073aa; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">No files found in /mmr-temp/ folder. Please upload files via FTP or use the browser upload above.</p>';
								}
							}
						}
					}
				} catch ( e ) {
					console.error( 'Error refreshing files:', e );
					const uploadList = document.getElementById( 'mmr-upload-list' );
					if ( uploadList ) {
						uploadList.innerHTML = '<p style="color: #dc3545; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 4px;">Error refreshing files list. Please try again.</p>';
					}
				}
			}

			if ( refreshBtn ) {
				refreshBtn.disabled = false;
				refreshBtn.textContent = '🔄 Refresh Files List';
			}
		};

		xhr.onerror = function() {
			if ( refreshBtn ) {
				refreshBtn.disabled = false;
				refreshBtn.textContent = '🔄 Refresh Files List';
			}
			const uploadList = document.getElementById( 'mmr-upload-list' );
			if ( uploadList ) {
				uploadList.innerHTML = '<p style="color: #dc3545; padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 4px;">Network error. Please try again.</p>';
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

		// Ensure global state is updated so later steps (Match & Review)
		// use the same uploaded files list (fixes Refresh -> Continue to Match mismatch)
		mmrState.uploadedFiles = uploadedFiles || [];

		if ( uploadList ) {
			let html = '';
			if ( uploadedFiles.length === 0 ) {
				html += '<p class="text-[11px] text-slate-500">No files detected yet. Drag files above or use the buttons.</p>';
			} else {
				// Upload Summary Box
				html += '<div class="mmr-upload-summary mb-3">';
				html += '<div class="mb-2 flex items-center justify-between">';
				html += '<span class="mmr-upload-label">';
				html += '<span class="dashicons dashicons-media-default text-[16px]"></span>';
				html += '<span>' + uploadedFiles.length + ' File(s) Ready</span>';
				html += '</span>';
				html += '</div>';
				html += '<p class="text-[11px] text-emerald-800 mb-2">Files are prepared in the temporary upload folder and ready for matching.</p>';
				html += '<details class="mt-2">';
				html += '<summary class="cursor-pointer text-[11px] font-semibold text-emerald-900 hover:text-emerald-700 flex items-center gap-1">';
				html += '<span class="dashicons dashicons-visibility text-[13px]"></span>';
				html += '<span>View uploaded files (' + uploadedFiles.length + ')</span>';
				html += '</summary>';
				html += '<div class="mmr-upload-files-list">';
				html += '<ul class="divide-y divide-slate-100">';
				uploadedFiles.forEach( function( file ) {
					html += '<li class="px-3 py-2 flex items-center justify-between gap-2 hover:bg-slate-50 transition">';
					html += '<span class="truncate font-medium text-slate-800 text-[11px]">' + file.name + '</span>';
					html += '<span class="shrink-0 text-[10px] text-slate-500 font-medium">' + formatFileSize( file.size ) + '</span>';
					html += '</li>';
				} );
				html += '</ul>';
				html += '</div>';
				html += '</details>';
				html += '</div>';

				// Matching Status Section
				if ( mmrState.missingFiles.length > 0 ) {
					let matchedCount = 0;
					mmrState.missingFiles.forEach( function( missingFile ) {
						const isAvailable = uploadedFiles.some( f => f.name === missingFile.filename );
						if ( isAvailable ) {
							matchedCount++;
						}
					} );

					html += '<div class="mmr-matching-status">';
					html += '<div class="mb-3 flex items-center justify-between flex-wrap gap-2">';
					html += '<span class="mmr-matching-label">';
					html += '<span class="dashicons dashicons-search text-[16px]"></span>';
					html += '<span>Matching Status</span>';
					html += '</span>';
					html += '<span class="text-[10px] font-semibold text-amber-900">' + matchedCount + ' / ' + mmrState.missingFiles.length + ' Matched</span>';
					html += '</div>';

					// Matching bar indicator
					const matchPercent = ( matchedCount / mmrState.missingFiles.length ) * 100;
					html += '<div class="mb-3 w-full h-2 bg-amber-100 rounded-full overflow-hidden border border-amber-200">';
					html += '<div class="h-full rounded-full" style="width: ' + matchPercent + '%; background: linear-gradient(90deg, #10b981, #059669); box-shadow: 0 0 8px rgba(16,185,129,0.4);"></div>';
					html += '</div>';

					html += '<p class="text-[11px] text-amber-900">';
					if ( matchedCount === mmrState.missingFiles.length ) {
						html += '<span class="font-semibold text-emerald-700">✓ Perfect match!</span> All missing files have a backup ready. Proceed to the next step.';
					} else if ( matchedCount > 0 ) {
						html += '<span class="font-semibold">' + matchedCount + ' file(s) matched.</span> ' + ( mmrState.missingFiles.length - matchedCount ) + ' file(s) remain unmatched until their backups appear.';
					} else {
						html += '<span class="font-semibold text-red-700">No matches found.</span> Upload files with matching names to proceed with restoration.';
					}
					html += '</p>';
					html += '</div>';
				}
			}

			uploadList.innerHTML = html;

			const restoreBtn = document.getElementById( 'mmr-restore-btn' );
			if ( restoreBtn && uploadedFiles.length > 0 ) {
				restoreBtn.disabled = false;
			}

			const hint = document.getElementById( 'mmr-upload-hint' );
			if ( hint && uploadedFiles.length > 0 ) {
				hint.classList.remove( 'hidden' );
			}
		}
	}

	/**
	 * Clear temporary files
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
	 * Start a new scan - reset state, navigate to scan step, and run scan
	 */
	function startNewScan() {
		// Reset the global state
		mmrState.scanning = false;
		mmrState.restoring = false;
		mmrState.currentBatch = 1;
		mmrState.totalBatches = 0;
		mmrState.totalFiles = 0;
		mmrState.filesRestored = 0;
		mmrState.uploadedFiles = [];
		mmrState.missingFiles = [];

		// Clear the scan results display
		const resultsDiv = document.getElementById( 'mmr-scan-results' );
		if ( resultsDiv ) {
			resultsDiv.classList.add( 'hidden' );
			const outputDiv = document.getElementById( 'mmr-scan-output' );
			if ( outputDiv ) {
				outputDiv.innerHTML = '';
			}
		}

		// Clear upload list
		const uploadList = document.getElementById( 'mmr-upload-list' );
		if ( uploadList ) {
			uploadList.innerHTML = '';
		}

		// Clear match summary
		const matchSummary = document.getElementById( 'mmr-match-summary' );
		if ( matchSummary ) {
			matchSummary.innerHTML = '';
		}

		// Clear restore output
		const restoreOutput = document.getElementById( 'mmr-restore-output' );
		if ( restoreOutput ) {
			restoreOutput.innerHTML = '';
		}

		// Reset progress
		const progressFill = document.getElementById( 'mmr-progress-fill' );
		const progressText = document.getElementById( 'mmr-progress-text' );
		if ( progressFill ) {
			progressFill.style.width = '0%';
		}
		if ( progressText ) {
			progressText.textContent = 'Waiting to start…';
		}

		// Navigate back to scan step
		navigateToStep( 1 );

		// Now trigger the scan
		startScan();
	}

	/**
	 * Show the Pro features modal
	 */
	function showProModal() {
		const modal = document.getElementById( 'mmr-pro-modal' );
		if ( modal ) {
			modal.classList.remove( 'hidden' );
			document.body.style.overflow = 'hidden'; // Prevent background scrolling
		}
	}

	/**
	 * Hide the Pro features modal
	 */
	function hideProModal() {
		const modal = document.getElementById( 'mmr-pro-modal' );
		if ( modal ) {
			modal.classList.add( 'hidden' );
			document.body.style.overflow = ''; // Restore scrolling
		}
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
