<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

if ($_SESSION["rol"] !== "admin") {
    die("Acceso no autorizado");
}

$master_error = "";
$action_error = "";
$action_success = "";

if (empty($_SESSION["master_ok"])) {
    die("Acceso no autorizado");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $action_error = "Solicitud invalida";
    } else {
        $accion = $_POST["accion"] ?? "";
        if ($accion === "crear") {
            $usuario = trim($_POST["usuario"] ?? "");
            $password = trim($_POST["password"] ?? "");
            $rol = trim($_POST["rol"] ?? "usuario");
            if ($usuario === "" || $password === "") {
                $action_error = "Usuario y contrasena son obligatorios";
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, rol) VALUES (?,?,?)");
                $stmt->execute([$usuario, hash("sha256", $password), $rol]);
                $action_success = "Usuario creado";
            }
        } elseif ($accion === "rol") {
            $id = (int)($_POST["id"] ?? 0);
            $rol = trim($_POST["rol"] ?? "usuario");
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?");
                $stmt->execute([$rol, $id]);
                $action_success = "Rol actualizado";
            }
        } elseif ($accion === "password") {
            $id = (int)($_POST["id"] ?? 0);
            $password = trim($_POST["password"] ?? "");
            if ($id > 0 && $password !== "") {
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([hash("sha256", $password), $id]);
                $action_success = "Contrasena actualizada";
            } else {
                $action_error = "Contrasena obligatoria";
            }
        } elseif ($accion === "eliminar") {
            $id = (int)($_POST["id"] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $action_success = "Usuario eliminado";
            }
        }
    }
}

$usuarios = $pdo->query("SELECT id, usuario, rol FROM usuarios ORDER BY usuario")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administrar usuarios</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container">
    <div class="page-header">
        <h2>Administracion de usuarios</h2>
        <div class="actions"></div>
    </div>

    <?php if ($action_error) echo "<p class='error'>$action_error</p>"; ?>
    <?php if ($action_success) echo "<p class='success'>$action_success</p>"; ?>

    <div class="grid">
        <div class="card">
            <h3>Crear usuario</h3>
            <form method="post">
                <input type="hidden" name="accion" value="crear">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                <label>Usuario</label>
                <input type="text" name="usuario" required>
                <label>Contrasena</label>
                <input type="password" name="password" required>
                <label>Rol</label>
                <select name="rol">
                    <option value="usuario">Usuario</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit">Crear</button>
            </form>
        </div>

        <div class="card">
            <h3>Usuarios</h3>
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u["usuario"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($u["rol"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <form class="inline-form" method="post">
                                <input type="hidden" name="accion" value="rol">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                                <select name="rol">
                                    <option value="usuario" <?= $u["rol"] === "usuario" ? "selected" : "" ?>>Usuario</option>
                                    <option value="admin" <?= $u["rol"] === "admin" ? "selected" : "" ?>>Admin</option>
                                </select>
                                <button class="btn btn-edit" type="submit">Actualizar</button>
                            </form>
                            <form class="inline-form" method="post">
                                <input type="hidden" name="accion" value="password">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                                <input type="password" name="password" placeholder="Nueva contrasena" required>
                                <button class="btn btn-add" type="submit">Cambiar</button>
                            </form>
                            <form class="inline-form" method="post" onsubmit="return confirm('Eliminar usuario?')">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                                <input type="hidden" name="id" value="<?= (int)$u["id"] ?>">
                                <button class="btn btn-delete" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
