<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

// solo admin
if ($_SESSION["rol"] !== "admin") {
    die("Acceso no autorizado");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: empleados.php");
    exit;
}

$csrf = $_POST["csrf"] ?? "";
if (!hash_equals($_SESSION["csrf"], $csrf)) {
    die("Solicitud invalida");
}

// validar ID
$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
if ($id <= 0) {
    header("Location: empleados.php");
    exit;
}

// obtener datos para historial
$stmt = $pdo->prepare("SELECT nombre_apellido FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

// eliminar
$sql = "DELETE FROM empleados WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);

if ($emp) {
    registrarHistorial(
        $pdo,
        $id,
        $_SESSION["usuario"],
        "baja",
        "Se elimino el empleado " . $emp["nombre_apellido"]
    );
}

header("Location: empleados.php");
exit;
