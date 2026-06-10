<?php
// ============================================================
// ZD Birthdays — Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'zdbd');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default — change for production

define('APP_BASE',      '/ZD_Birthdays');   // subfolder under htdocs — change for production
define('APP_TIMEZONE',  'Africa/Lusaka');
define('APP_ROOT',      dirname(__DIR__));
define('UPLOAD_PATH',   APP_ROOT . '/uploads');
define('UPLOAD_URL',    APP_BASE . '/uploads');
define('LOCK_TIMEOUT',  600);   // 10 minutes in seconds
define('WISH_EDIT_WINDOW', 30); // seconds
define('MAX_IMAGES',    1);     // one photo per employee
define('MAX_FILE_SIZE', 5242880); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('ALLOWED_IMAGE_EXTS',  ['jpg','jpeg','png','gif','webp']);
define('ADMIN_PATH', APP_ROOT . '/admin');
