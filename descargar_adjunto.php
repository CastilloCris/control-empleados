<?php
include "includes/auth.php";
include "includes/conexion.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    die("Adjunto invalido");
}

$stmt = $pdo->prepare("SELECT * FROM empleado_adjuntos WHERE id = ?");
$stmt->execute([$id]);
$adj = $stmt->fetch();

if (!$adj) {
    die("Adjunto no encontrado");
}

$path = __DIR__ . "/" . $adj["ruta"];
if (!file_exists($path)) {
    die("Archivo no encontrado");
}

header("Content-Type: " . $adj["tipo"]);
header("Content-Disposition: attachment; filename=\"" . $adj["nombre_original"] . "\"");
header("Content-Length: " . filesize($path));
readfile($path);
exit;
