<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::check()) {
    redirect(Auth::homeFor(Auth::role()));
}
redirect(base_url('login.php'));
