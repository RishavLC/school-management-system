<?php
/**
 * Application configuration.
 * BASE_URL should match the folder name under htdocs, e.g. http://localhost/lilliput-school
 */
return [
    'app_name'  => 'Lilliput School Management System',
    'base_url'  => '/lilliput-school',      // change if your folder name differs
    'upload_dir'=> __DIR__ . '/../assets/uploads',
    'upload_url'=> '/lilliput-school/assets/uploads',
    'session_name' => 'lilliput_session',
    // session expires after this many seconds of inactivity
    'session_lifetime' => 3600,
];
