<?php
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$master_cfg = include __DIR__ . "/master_config.php";
$master_user = (string)($master_cfg["usuario"] ?? "");
$master_pass = (string)($master_cfg["password"] ?? "");

if (isset($_GET["master_logout"])) {
    unset($_SESSION["master_ok"]);
    header("Location: admin_usuarios.php");
    exit;
}

if (!empty($_POST["accion"]) && $_POST["accion"] === "master_login") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $master_error = "Solicitud invalida";
    } else {
        $u = trim($_POST["master_usuario"] ?? "");
        $p = trim($_POST["master_password"] ?? "");
        if ($u === $master_user && $p === $master_pass) {
            $_SESSION["master_ok"] = true;
            header("Location: admin_usuarios.php");
            exit;
        } else {
            $master_error = "Credenciales invalidas";
        }
    }
}
