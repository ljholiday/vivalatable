<?php
// Simple upload test script
require_once 'includes/bootstrap.php';

echo "Testing VT_Image_Manager...\n\n";

// Check upload path config
$upload_path = VT_Config::get('upload_path', 'NOT SET');
echo "Upload path config: " . $upload_path . "\n";

// Check if upload directory exists
$upload_dir = $upload_path . '/vivalatable/users/';
echo "Upload directory: " . $upload_dir . "\n";
echo "Directory exists: " . (file_exists($upload_dir) ? 'YES' : 'NO') . "\n";
echo "Directory writable: " . (is_writable(dirname($upload_dir)) ? 'YES' : 'NO') . "\n\n";

// Test image manager class
echo "VT_Image_Manager class exists: " . (class_exists('VT_Image_Manager') ? 'YES' : 'NO') . "\n";

// Test file upload constants
echo "Allowed types: " . implode(', ', VT_Image_Manager::ALLOWED_TYPES) . "\n";
echo "Cover image max dimensions: " . VT_Image_Manager::COVER_IMAGE_MAX_WIDTH . 'x' . VT_Image_Manager::COVER_IMAGE_MAX_HEIGHT . "\n";

echo "\nUpload functionality test completed.\n";
?>