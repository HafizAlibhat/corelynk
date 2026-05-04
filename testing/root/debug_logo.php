<?php
$p = 'C:/xampp/htdocs/corelynk/public/uploads/company/company-logo.png';
$j = 'C:/xampp/htdocs/corelynk/public/uploads/company/company-logo.jpg';

function info($path) {
    echo $path, "\n";
    echo '  exists: ', (file_exists($path) ? 'yes' : 'no'), "\n";
    if (file_exists($path)) {
        echo '  size: ', filesize($path), "\n";
        echo '  mime: ', (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'n/a') : 'n/a'), "\n";
    }
}

info($p);
info($j);
