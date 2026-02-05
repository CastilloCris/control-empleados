<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

// solo admin
if ($_SESSION["rol"] !== "admin") {
    die("Acceso no autorizado");
}

// validar ID
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    header("Location: empleados.php");
    exit;
}

// obtener datos actuales
$sql = "SELECT * FROM empleados WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

// guardar cambios
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $error = "Solicitud invalida";
    } else {
    $nombre = trim($_POST["nombre"] ?? "");
    $cuil = trim($_POST["cuil"] ?? "");
    $funcion = trim($_POST["funcion"] ?? "");
    $situacion = trim($_POST["situacion"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $legajo = trim($_POST["legajo"] ?? "");
    $fecha_ingreso = trim($_POST["fecha_ingreso"] ?? "");

    try {
        $sql = "UPDATE empleados SET
            nombre_apellido = ?,
            cuil = ?,
            funcion = ?,
            situacion = ?,
            categoria = ?,
            legajo = ?,
            fecha_ingreso = ?
            WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre,
            $cuil,
            $funcion,
            $situacion,
            $categoria,
            $legajo,
            $fecha_ingreso,
            $id
        ]);

        registrarHistorial(
            $pdo,
            $id,
            $_SESSION["usuario"],
            "edicion",
            "Se actualizo el empleado " . $nombre
        );

        header("Location: empleados.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error al actualizar (CUIL duplicado u otro problema)";
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar empleado</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container">

    <h2>Editar empleado</h2>

    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
        <input type="text" name="nombre" value="<?= htmlspecialchars($empleado["nombre_apellido"], ENT_QUOTES, "UTF-8") ?>" required><br><br>

        <input type="text" name="cuil" value="<?= htmlspecialchars($empleado["cuil"], ENT_QUOTES, "UTF-8") ?>" required><br><br>

        <input type="text" name="funcion" value="<?= htmlspecialchars($empleado["funcion"], ENT_QUOTES, "UTF-8") ?>"><br><br>

        <select name="situacion" required>
            <option <?= $empleado["situacion"] == "Permanente" ? "selected" : "" ?>>Permanente</option>
            <option <?= $empleado["situacion"] == "Contratado" ? "selected" : "" ?>>Contratado</option>
            <option <?= $empleado["situacion"] == "Plan" ? "selected" : "" ?>>Plan</option>
        </select><br><br>

        <input type="text" name="categoria" value="<?= htmlspecialchars($empleado["categoria"], ENT_QUOTES, "UTF-8") ?>"><br><br>

        <input type="text" name="legajo" value="<?= htmlspecialchars($empleado["legajo"], ENT_QUOTES, "UTF-8") ?>"><br><br>

        <input type="date" name="fecha_ingreso"
            value="<?= htmlspecialchars($empleado["fecha_ingreso"], ENT_QUOTES, "UTF-8") ?>" required><br><br>

        <button>Guardar cambios</button>
    </form>

    <br>
    <a href="empleados.php" class="btn btn-back">&larr; Volver</a>
</div>
</body>
</html>
