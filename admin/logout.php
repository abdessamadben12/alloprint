<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    logout();
}
header('Location: login.php');
exit;
