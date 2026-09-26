<?php
require __DIR__ . '/core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $_SESSION = [];
    session_destroy();
}
redirect('login.php');
