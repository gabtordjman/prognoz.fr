<?php
require __DIR__ . '/../../app/bootstrap.php';

requireAdminGate();
adminLogout();
header('Location: ' . url('admin/login.php'));
exit;
