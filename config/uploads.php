<?php
// Có thể thay bằng đường dẫn tuyệt đối khác; luôn đặt ngoài htdocs/webroot.
$database = require __DIR__ . '/database.php';
return getenv('CV_UPLOAD_DIR') ?: dirname(__DIR__, 3) . '/campusvoice-storage/' . $database['name'];
