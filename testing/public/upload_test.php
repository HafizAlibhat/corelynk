<?php
// Test file upload configuration
echo "<h3>File Upload Configuration Test</h3>";
echo "file_uploads: " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

// Check upload directory
$uploadPath = FCPATH . 'uploads/products/';
echo "<br><h3>Directory Test</h3>";
echo "Upload path: " . $uploadPath . "<br>";
echo "Directory exists: " . (is_dir($uploadPath) ? 'Yes' : 'No') . "<br>";
echo "Directory writable: " . (is_writable($uploadPath) ? 'Yes' : 'No') . "<br>";

// List existing files
echo "<br><h3>Existing Files</h3>";
if (is_dir($uploadPath)) {
    $files = scandir($uploadPath);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo $file . "<br>";
        }
    }
}
?>
