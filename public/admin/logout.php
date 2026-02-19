<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';

logout_user();
header('Location: ' . APP_BASE . '/admin/login.php');
exit;
