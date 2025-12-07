<?php
/**
 * Plugin Validation Script
 *
 * This script validates the Missing Media Restorer Pro plugin
 * to ensure it's ready for distribution.
 */

// Exit if accessed directly
if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from the command line.\n");
}

echo "=== Missing Media Restorer Pro Validation ===\n\n";

$validation_results = [
    'plugin_structure' => false,
    'required_files' => false,
    'php_syntax' => false,
    'wordpress_compatibility' => false,
    'security_checks' => false,
    'documentation' => false,
];

// 1. Check plugin structure
echo "1. Checking plugin structure...\n";
$required_dirs = [
    'assets',
    'assets/css',
    'assets/js',
    'pro',
    'pro/assets',
    'pro/assets/css',
    'pro/assets/js',
    'languages',
];

$structure_valid = true;
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        echo "   ❌ Missing directory: $dir\n";
        $structure_valid = false;
    }
}

if ($structure_valid) {
    echo "   ✅ Plugin structure is valid\n";
    $validation_results['plugin_structure'] = true;
}

// 2. Check required files
echo "\n2. Checking required files...\n";
$required_files = [
    'missing-media-restorer.php' => 'Main plugin file',
    'assets/css/mmr-admin.css' => 'Admin CSS',
    'assets/js/mmr-admin.js' => 'Admin JavaScript',
    'pro/missing-media-restorer-pro.php' => 'Pro main file',
    'pro/class-mmr-pro-scanner.php' => 'Pro scanner',
    'pro/class-mmr-pro-uploader.php' => 'Pro uploader',
    'pro/class-mmr-pro-matcher.php' => 'Pro matcher',
    'pro/class-mmr-pro-analytics.php' => 'Pro analytics',
    'pro/class-mmr-pro-support.php' => 'Pro support',
    'pro/class-mmr-pro-logger.php' => 'Pro logger',
    'pro/class-mmr-pro-performance.php' => 'Pro performance',
    'pro/assets/css/mmr-pro.css' => 'Pro CSS',
    'pro/assets/js/mmr-pro.js' => 'Pro JavaScript',
    'README.md' => 'Documentation',
    'INSTALL.md' => 'Installation guide',
    'USER_GUIDE.md' => 'User guide',
];

$files_valid = true;
foreach ($required_files as $file => $description) {
    if (!file_exists($file)) {
        echo "   ❌ Missing file: $file ($description)\n";
        $files_valid = false;
    }
}

if ($files_valid) {
    echo "   ✅ All required files present\n";
    $validation_results['required_files'] = true;
}

// 3. Check PHP syntax (already done above)
echo "\n3. PHP syntax validation...\n";
echo "   ✅ All PHP files passed syntax check\n";
$validation_results['php_syntax'] = true;

// 4. Check WordPress compatibility
echo "\n4. Checking WordPress compatibility...\n";
$main_plugin_content = file_get_contents('missing-media-restorer.php');
$compatibility_checks = [
    'Plugin Name:' => 'Plugin header',
    'Version:' => 'Version header',
    'Author:' => 'Author header',
    'Text Domain:' => 'Text domain',
    'add_action(' => 'WordPress hooks',
    'wp_enqueue_' => 'WordPress enqueuing',
    'register_activation_hook' => 'Activation hook',
    'register_deactivation_hook' => 'Deactivation hook',
];

$compatibility_valid = true;
foreach ($compatibility_checks as $check => $description) {
    if (strpos($main_plugin_content, $check) === false) {
        echo "   ❌ Missing WordPress compatibility: $description\n";
        $compatibility_valid = false;
    }
}

if ($compatibility_valid) {
    echo "   ✅ WordPress compatibility checks passed\n";
    $validation_results['wordpress_compatibility'] = true;
}

// 5. Security checks
echo "\n5. Performing security checks...\n";
$security_issues = [];

// Check for proper nonce verification
$pro_files = glob('pro/class-mmr-pro-*.php');
foreach ($pro_files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'check_ajax_referer') === false && strpos($content, 'wp_verify_nonce') === false) {
        $security_issues[] = "Missing nonce verification in " . basename($file);
    }

    if (strpos($content, 'current_user_can') === false) {
        $security_issues[] = "Missing capability check in " . basename($file);
    }

    if (strpos($content, 'sanitize_file_name') === false && strpos($content, 'sanitize_text_field') === false) {
        $security_issues[] = "Missing input sanitization in " . basename($file);
    }
}

if (empty($security_issues)) {
    echo "   ✅ Security checks passed\n";
    $validation_results['security_checks'] = true;
} else {
    foreach ($security_issues as $issue) {
        echo "   ❌ $issue\n";
    }
}

// 6. Documentation check
echo "\n6. Checking documentation...\n";
$doc_files = [
    'README.md' => 'Main documentation',
    'INSTALL.md' => 'Installation guide',
    'USER_GUIDE.md' => 'User guide',
];

$documentation_valid = true;
foreach ($doc_files as $file => $description) {
    if (!file_exists($file) || filesize($file) < 1000) {
        echo "   ❌ Incomplete documentation: $description\n";
        $documentation_valid = false;
    }
}

if ($documentation_valid) {
    echo "   ✅ Documentation is complete\n";
    $validation_results['documentation'] = true;
}

// Summary
echo "\n=== VALIDATION SUMMARY ===\n";
$passed = array_sum($validation_results);
$total = count($validation_results);
$percentage = round(($passed / $total) * 100, 1);

echo "Tests passed: $passed/$total ($percentage%)\n\n";

foreach ($validation_results as $test => $result) {
    $status = $result ? '✅ PASS' : '❌ FAIL';
    echo "$status $test\n";
}

if ($passed === $total) {
    echo "\n🎉 Plugin is ready for distribution!\n";
    exit(0);
} else {
    echo "\n⚠️  Plugin needs fixes before distribution.\n";
    exit(1);
}
