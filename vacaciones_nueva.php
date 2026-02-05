<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

if ($_SESSION["rol"] !== "admin") {
    die("Acceso no autorizado");
}

$empleado_id = isset($_GET["empleado_id"]) ? (int)$_GET["empleado_id"] : 0;
if ($empleado_id <= 0) {
    header("Location: empleados.php");
    exit;
}

// obtener empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$empleado_id]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die("Empleado no encontrado");
}

// calculos
$ant = calcularAntiguedad($emp["fecha_ingreso"]);
$total = calcularVacaciones($ant["anios"]);
$usadas = vacacionesUsadas($pdo, $empleado_id);
$saldo = $total - $usadas;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $error = "Solicitud invalida";
    } else {
        $desde = trim($_POST["desde"] ?? "");
        $hasta = trim($_POST["hasta"] ?? "");
        $obs = trim($_POST["obs"] ?? "");
        $dias = calcularDiasTomados($desde, $hasta);

        if ($dias <= 0) {
            $error = "Los dias deben ser mayores a cero";
        } elseif ($desde && $hasta && $desde > $hasta) {
            $error = "La fecha desde no puede ser mayor que la fecha hasta";
        } elseif ($dias > $saldo) {
            $error = "No tiene dias suficientes disponibles";
        } else {
            $sql = "INSERT INTO vacaciones
                    (empleado_id, fecha_desde, fecha_hasta, dias_tomados, observaciones)
                    VALUES (?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$empleado_id, $desde, $hasta, $dias, $obs]);

            registrarHistorial(
                $pdo,
                $empleado_id,
                $_SESSION["usuario"],
                "vacaciones",
                "Registro de vacaciones: " . $dias . " dias (" . $desde . " a " . $hasta . ")"
            );

            header("Location: empleados.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar vacaciones</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container">

<h2>Vacaciones - <?= htmlspecialchars($emp["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></h2>

<p>Total: <?= (int)$total ?> dias</p>
<p>Usadas: <?= (int)$usadas ?> dias</p>
<p><strong>Disponibles: <?= (int)$saldo ?> dias</strong></p>

<?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
    <label>Desde</label>
    <input type="date" name="desde" required>

    <label>Hasta</label>
    <input type="date" name="hasta" required>

    <label>Dias tomados</label>
    <input type="number" name="dias" min="1" readonly>

    <label>Observaciones</label>
    <input type="text" name="obs">

    <button>Guardar</button>
</form>

<br>
<a href="empleados.php" class="btn btn-back">&larr; Volver</a>

</div>
<script>
const desde = document.querySelector('input[name="desde"]');
const hasta = document.querySelector('input[name="hasta"]');
const dias = document.querySelector('input[name="dias"]');

function calcDias() {
    if (!desde.value || !hasta.value) {
        dias.value = "";
        return;
    }
    const d1 = new Date(desde.value);
    const d2 = new Date(hasta.value);
    const ms = d2 - d1;
    if (ms < 0) {
        dias.value = "";
        return;
    }
    const diff = Math.floor(ms / 86400000) + 1;
    dias.value = diff > 0 ? diff : "";
}

desde.addEventListener("change", calcDias);
hasta.addEventListener("change", calcDias);
</script>
</body>
</html>
