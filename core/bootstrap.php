<?php
/**
 * bootstrap.php
 * Included at the top of every page. Starts a hardened session,
 * loads core classes and helpers. Nothing here talks to the database
 * directly (see Database.php) and nothing here renders HTML.
 */

$cfg = require __DIR__ . '/../config/app.php';

// --- Hardened session configuration (must run before session_start) ---
if (session_status() === PHP_SESSION_NONE) {
    session_name($cfg['session_name']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // Enable this once the site is served over HTTPS:
    // ini_set('session.cookie_secure', '1');
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never show raw PHP errors to end users
ini_set('log_errors', '1');

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/helpers.php';
