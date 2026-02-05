<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

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

$id = isset($_POST["id"]) ? (int)$_POST["id"] : 0;
if ($id <= 0) {
    die("Adjunto invalido");
}

$stmt = $pdo->prepare("SELECT * FROM empleado_adjuntos WHERE id = ?");
$stmt->execute([$id]);
$adj = $stmt->fetch();

if (!$adj) {
    die("Adjunto no encontrado");
}

$empleado_id = (int)$adj["empleado_id"];
$path = __DIR__ . "/" . $adj["ruta"];

if (file_exists($path)) {
    @unlink($path);
}

$del = $pdo->prepare("DELETE FROM empleado_adjuntos WHERE id = ?");
$del->execute([$id]);

registrarHistorial(
    $pdo,
    $empleado_id,
    $_SESSION["usuario"],
    "adjunto_eliminado",
    "Se elimino el adjunto: " . $adj["nombre_original"]
);

header("Location: empleado_detalle.php?id=" . $empleado_id . "&tab=adjuntos");
exit;
