<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/app/core/Database.php';
require_once dirname(__DIR__) . '/app/core/Session.php';
require_once dirname(__DIR__) . '/app/core/Helper.php';
require_once dirname(__DIR__) . '/app/core/Auth.php';

Session::start();
Database::getInstance();
Database::migrate();
Database::seed();

echo "Session status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Authed: " . var_export(Auth::isAuthenticated(), true) . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Auth user: " . var_export(isset($_SESSION['user_id']), true) . "\n";

// Try login
$result = Auth::login('admin', 'admin123');
echo "Login result: " . var_export($result, true) . "\n";
echo "Authed after login: " . var_export(Auth::isAuthenticated(), true) . "\n";
echo "User ID: " . Auth::userId() . "\n";
echo "User name: " . Auth::getUserName() . "\n";
