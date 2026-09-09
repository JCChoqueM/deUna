<?php
// This file simulates what router.php does
$_GET['url'] = 'login';
$_SERVER['REQUEST_METHOD'] = 'GET';
echo "Before index.php require\n";
require_once __DIR__ . '/index.php';
echo "After index.php require\n";
