<?php
require __DIR__ . '/config/session.php';
unset($_SESSION['customer_id'], $_SESSION['customer_name']);
header('Location: index.php');
exit;
