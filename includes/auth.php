<?php
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]),
    "httponly" => true,
    "samesite" => "Lax"
]);
session_start();
header("Content-Type: text/html; charset=utf-8");

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}
