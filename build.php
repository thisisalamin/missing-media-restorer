<?php
/**
 * Missing Media Restorer Pro - Build Script
 *
 * This script prepares the plugin for distribution by:
 * 1. Creating a clean build directory
 * 2. Copying necessary files
 * 3. Minifying CSS and JS files
 * 4. Creating a distribution ZIP file
 * 5. Generating version information
 */

// Exit if accessed directly
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

// Configuration
$config = [
    'plugin_name' => 'missing-media-restorer',
    'version' => '1.0.0',
    'build_dir' => __DIR__ . '/build',
    'dist_dir' => __DIR__ . '/dist',
    'exclude_patterns' => [
        '*.log',
        '*.tmp',
        '.DS_Store',
        'node_modules/',
        '.git/',
        'build/',
        'dist/',
        '*.md',
        'build.php',
        'package-lock.json',
        'postcss.config.js',
        'tailwind.config.js',
    ],
];

// Colors for terminal output
$colors = [
    'reset' => "\033[0m",
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'magenta' => "\033[35m",
    'cyan' => "\033[36m",
    'white' => "\033[37m",
];

function color_output($text, $color = 'reset') {
    global $colors;
    echo $colors[$color] . $text . $colors['reset'] . "\n";
}

function clean_directory($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                clean_directory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }
}

function copy_files($src, $dst, $exclude_patterns = []) {
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $relative_path = substr($file->getPathname(), strlen($src) + 1);

        // Check if file should be excluded
        $should_exclude = false;
        foreach ($exclude_patterns as $pattern) {
            if (fnmatch($pattern, $relative_path) || fnmatch($pattern, $file->getFilename())) {
                $should_exclude = true;
                break;
            }
        }

        if ($should_exclude) {
            continue;
        }

        $dest_path = $dst . '/' . $relative_path;

        if ($file->isDir()) {
            if (!is_dir($dest_path)) {
                mkdir($dest_path, 0755, true);
            }
        } else {
            copy($file->getPathname(), $dest_path);
        }
    }
}

function minify_css($input_file, $output_file) {
    $css = file_get_contents($input_file);

    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

    // Remove whitespace
    $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);

    // Remove extra spaces
    $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);

    file_put_contents($output_file, $css);
}

function minify_js($input_file, $output_file) {
    $js = file_get_contents($input_file);

    // Remove comments (single line and multi-line)
    $js = preg_replace('/\/\/.*$/m', '', $js);
    $js = preg_replace('/\/\*.*?\*\//s', '', $js);

    // Remove whitespace
    $js = str_replace(["\r\n", "\r", "\n", "\t"], '', $js);

    // Remove extra spaces
    $js = preg_replace('/\s*([{}();,=])\s*/', '$1', $js);

    file_put_contents($output_file, $js);
}

function create_zip($source, $destination) {
    if (!extension_loaded('zip')) {
        throw new Exception('ZIP extension not loaded');
    }

    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Cannot open zip file: $destination");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $relative_path = substr($file->getPathname(), strlen($source) + 1);

        if ($file->isDir()) {
            $zip->addEmptyDir($relative_path);
        } else {
            $zip->addFile($file->getPathname(), $relative_path);
        }
    }

    $zip->close();
}

function generate_version_info($config) {
    $version_info = [
        'version' => $config['version'],
        'build_date' => date('Y-m-d H:i:s'),
        'build_number' => time(),
        'php_version' => PHP_VERSION,
        'wordpress_version' => '5.0+', // Minimum required
        'requirements' => [
            'php' => '7.4+',
            'wordpress' => '5.0+',
            'mysql' => '5.6+',
            'memory' => '128MB',
        ],
        'features' => [
            'Smart Scanner',
            'Advanced Uploader',
            'Intelligent Matcher',
            'Analytics & Reporting',
            'Support System',
            'Performance Optimization',
        ],
    ];

    file_put_contents(
        $config['build_dir'] . '/version.json',
        json_encode($version_info, JSON_PRETTY_PRINT)
    );
}

function verify_plugin_structure($build_dir) {
    $required_files = [
        'missing-media-restorer.php',
        'README.md',
        'assets/js/mmr-admin.js',
        'assets/css/mmr-admin.css',
        'pro/missing-media-restorer-pro.php',
        'pro/class-mmr-pro-scanner.php',
        'pro/class-mmr-pro-uploader.php',
        'pro/class-mmr-pro-matcher.php',
        'pro/class-mmr-pro-analytics.php',
        'pro/class-mmr-pro-support.php',
        'pro/assets/js/mmr-pro.js',
        'pro/assets/css/mmr-pro.css',
    ];

    $missing_files = [];

    foreach ($required_files as $file) {
        if (!file_exists($build_dir . '/' . $file)) {
            $missing_files[] = $file;
        }
    }

    if (!empty($missing_files)) {
        throw new Exception("Missing required files: " . implode(', ', $missing_files));
    }

    // Check main plugin file
    $main_plugin_content = file_get_contents($build_dir . '/missing-media-restorer.php');
    if (strpos($main_plugin_content, 'Plugin Name:') === false) {
        throw new Exception("Main plugin file is missing plugin header");
    }

    return true;
}

// Main build process
try {
    color_output("Starting Missing Media Restorer Pro build process...", 'blue');

    // Clean and create directories
    color_output("Cleaning directories...", 'yellow');
    clean_directory($config['build_dir']);
    clean_directory($config['dist_dir']);

    if (!is_dir($config['build_dir'])) {
        mkdir($config['build_dir'], 0755, true);
    }

    if (!is_dir($config['dist_dir'])) {
        mkdir($config['dist_dir'], 0755, true);
    }

    // Copy files to build directory
    color_output("Copying files...", 'yellow');
    copy_files(__DIR__, $config['build_dir'], $config['exclude_patterns']);

    // Minify CSS files
    color_output("Minifying CSS files...", 'yellow');
    $css_files = [
        'assets/css/mmr-admin.css',
        'pro/assets/css/mmr-pro.css',
    ];

    foreach ($css_files as $css_file) {
        $input_file = $config['build_dir'] . '/' . $css_file;
        $output_file = $config['build_dir'] . '/' . str_replace('.css', '.min.css', $css_file);

        if (file_exists($input_file)) {
            minify_css($input_file, $output_file);
            color_output("Minified: $css_file", 'green');
        }
    }

    // Minify JS files
    color_output("Minifying JavaScript files...", 'yellow');
    $js_files = [
        'assets/js/mmr-admin.js',
        'pro/assets/js/mmr-pro.js',
    ];

    foreach ($js_files as $js_file) {
        $input_file = $config['build_dir'] . '/' . $js_file;
        $output_file = $config['build_dir'] . '/' . str_replace('.js', '.min.js', $js_file);

        if (file_exists($input_file)) {
            minify_js($input_file, $output_file);
            color_output("Minified: $js_file", 'green');
        }
    }

    // Generate version information
    color_output("Generating version information...", 'yellow');
    generate_version_info($config);

    // Verify plugin structure
    color_output("Verifying plugin structure...", 'yellow');
    verify_plugin_structure($config['build_dir']);

    // Create distribution ZIP
    color_output("Creating distribution ZIP...", 'yellow');
    $zip_filename = $config['plugin_name'] . '-pro-v' . $config['version'] . '.zip';
    $zip_path = $config['dist_dir'] . '/' . $zip_filename;

    create_zip($config['build_dir'], $zip_path);

    // Calculate file sizes
    $build_size = directory_size($config['build_dir']);
    $zip_size = filesize($zip_path);

    // Display results
    color_output("\n✅ Build completed successfully!", 'green');
    color_output("Build directory size: " . format_bytes($build_size), 'cyan');
    color_output("ZIP file size: " . format_bytes($zip_size), 'cyan');
    color_output("Distribution file: $zip_path", 'cyan');

    color_output("\n📋 Build Summary:", 'blue');
    color_output("- Plugin: Missing Media Restorer Pro", 'white');
    color_output("- Version: " . $config['version'], 'white');
    color_output("- Build Date: " . date('Y-m-d H:i:s'), 'white');
    color_output("- Files processed: " . count_files($config['build_dir']), 'white');

} catch (Exception $e) {
    color_output("\n❌ Build failed: " . $e->getMessage(), 'red');
    exit(1);
}

function directory_size($dir) {
    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $size += $file->getSize();
    }

    return $size;
}

function count_files($dir) {
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $count++;
        }
    }

    return $count;
}

function format_bytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}
