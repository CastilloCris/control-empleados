<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

// solo admin
if ($_SESSION["rol"] !== "admin") {
    die("Acceso no autorizado");
}

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
        $sql = "INSERT INTO empleados
        (nombre_apellido, cuil, funcion, situacion, categoria, legajo, fecha_ingreso)
        VALUES (?,?,?,?,?,?,?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre,
            $cuil,
            $funcion,
            $situacion,
            $categoria,
            $legajo,
            $fecha_ingreso
        ]);

        $empleado_id = (int)$pdo->lastInsertId();
        registrarHistorial(
            $pdo,
            $empleado_id,
            $_SESSION["usuario"],
            "alta",
            "Se creo el empleado " . $nombre
        );

        header("Location: empleados.php");
        exit;
    } catch (PDOException $e) {
        $error = "Error al guardar (CUIL duplicado u otro problema)";
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo empleado</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container">

    <h2>Agregar empleado</h2>

    <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
        <input type="text" name="nombre" placeholder="Nombre y apellido" required><br><br>

        <input type="text" name="cuil" placeholder="CUIL" required><br><br>

        <input type="text" name="funcion" placeholder="Funcion"><br><br>

        <select name="situacion" required>
            <option value="">Situacion</option>
            <option>Permanente</option>
            <option>Contratado</option>
            <option>Plan</option>
        </select><br><br>

        <input type="text" name="categoria" placeholder="Categoria"><br><br>

        <input type="text" name="legajo" placeholder="Nro. Legajo"><br><br>

        <input type="date" name="fecha_ingreso" required><br><br>

        <button>Guardar</button>
    </form>

    <br>
    <a href="empleados.php" class="btn btn-back">&larr; Volver</a>
</div>
</body>
</html>
