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
include "includes/conexion.php";

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$max_intentos = 5;
$ventana_segundos = 600;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usuario = trim($_POST["usuario"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $csrf = $_POST["csrf"] ?? "";

    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $error = "Solicitud invalida";
    } else {
        $intentos = $_SESSION["login_intentos"] ?? 0;
        $ultimo = $_SESSION["login_ultimo"] ?? 0;

        if ($intentos >= $max_intentos && (time() - $ultimo) < $ventana_segundos) {
            $error = "Demasiados intentos. Intenta mas tarde.";
        } else {
            $sql = "SELECT * FROM usuarios WHERE usuario = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            $ok = false;
            if ($user) {
                if (password_verify($password, $user["password"])) {
                    $ok = true;
                } elseif (hash("sha256", $password) === $user["password"]) {
                    $ok = true;
                    $nuevo_hash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $up->execute([$nuevo_hash, $user["id"]]);
                }
            }

            if ($ok) {
                session_regenerate_id(true);
                $_SESSION["usuario"] = $user["usuario"];
                $_SESSION["rol"] = $user["rol"];
                $_SESSION["login_intentos"] = 0;
                $_SESSION["login_ultimo"] = 0;
                header("Location: dashboard.php");
                exit;
            }

            $_SESSION["login_intentos"] = $intentos + 1;
            $_SESSION["login_ultimo"] = time();
            $error = "Usuario o contrasena incorrectos";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container auth-card">
<h2>Ingreso al sistema</h2>

<?php if (isset($error)) echo "<p style='color:red'>$error</p>"; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
    <input type="text" name="usuario" placeholder="Usuario" required><br><br>
    <input type="password" name="password" placeholder="Contrasena" required><br><br>
    <button>Ingresar</button>
</form>
</div>
</body>
</html>
