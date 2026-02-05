<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($id <= 0) {
    header("Location: empleados.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    die("Empleado no encontrado");
}

$solo_ver = ($_GET["view"] ?? "") === "1";
$can_edit = $_SESSION["rol"] === "admin" && !$solo_ver;

$error = "";
$upload_error = "";
$photo_error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "incidente") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $error = "Solicitud invalida";
    } elseif (!$can_edit) {
        $error = "No tienes permisos para registrar incidentes";
    } else {
        $tipo = normalizarTipoIncidente($_POST["tipo"] ?? "");
        $fecha_inicio = trim($_POST["fecha_inicio"] ?? "");
        $fecha_fin = trim($_POST["fecha_fin"] ?? "");
        $motivo = trim($_POST["motivo"] ?? "");
        $dni = obtenerDniDesdeCuil($emp["cuil"]);
        $legajo = (string)($emp["legajo"] ?? "");

        if (!$tipo) {
            $error = "Tipo de incidente invalido";
        } elseif (!$fecha_inicio || !$fecha_fin || $fecha_inicio > $fecha_fin) {
            $error = "Rango de fechas invalido";
        } elseif (!$motivo) {
            $error = "El motivo es obligatorio";
        } else {
            $sql = "INSERT INTO empleado_incidentes
                    (empleado_id, dni, legajo, tipo, fecha_inicio, fecha_fin, motivo)
                    VALUES (?,?,?,?,?,?,?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id, $dni, $legajo, $tipo, $fecha_inicio, $fecha_fin, $motivo]);

            registrarHistorial(
                $pdo,
                $id,
                $_SESSION["usuario"],
                "incidente",
                "Se registro " . $tipo . " (" . $fecha_inicio . " a " . $fecha_fin . "): " . $motivo
            );

            header("Location: empleado_detalle.php?id=" . $id);
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "foto") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $photo_error = "Solicitud invalida";
    } elseif (!$can_edit) {
        $photo_error = "No tienes permisos para actualizar la foto";
    } elseif (!isset($_FILES["foto"]) || $_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
        $photo_error = "No se pudo subir la foto";
    } else {
        $ext = strtolower(pathinfo($_FILES["foto"]["name"], PATHINFO_EXTENSION));
        $permitidas = ["jpg", "jpeg", "png"];
        if (!in_array($ext, $permitidas, true)) {
            $photo_error = "Formato de foto invalido";
        } else {
            $dir = __DIR__ . "/uploads/empleados/" . $id;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filename = "foto_" . time() . "." . $ext;
            $destino = $dir . "/" . $filename;
            if (move_uploaded_file($_FILES["foto"]["tmp_name"], $destino)) {
                $ruta_publica = "uploads/empleados/" . $id . "/" . $filename;
                $stmt = $pdo->prepare("UPDATE empleados SET foto = ? WHERE id = ?");
                $stmt->execute([$ruta_publica, $id]);
                registrarHistorial(
                    $pdo,
                    $id,
                    $_SESSION["usuario"],
                    "foto",
                    "Se actualizo la foto del empleado"
                );
                header("Location: empleado_detalle.php?id=" . $id);
                exit;
            } else {
                $photo_error = "No se pudo guardar la foto";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "adjunto") {
    $csrf = $_POST["csrf"] ?? "";
    if (!hash_equals($_SESSION["csrf"], $csrf)) {
        $upload_error = "Solicitud invalida";
    } elseif (!$can_edit) {
        $upload_error = "No tienes permisos para subir adjuntos";
    } elseif (!isset($_FILES["adjunto"]) || $_FILES["adjunto"]["error"] !== UPLOAD_ERR_OK) {
        $upload_error = "No se pudo subir el archivo";
    } else {
        $permitidas = ["pdf", "jpg", "jpeg", "png", "docx"];
        $ext = strtolower(pathinfo($_FILES["adjunto"]["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidas, true)) {
            $upload_error = "Tipo de archivo no permitido";
        } else {
            $dir = __DIR__ . "/uploads/empleados/" . $id . "/adjuntos";
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $original = $_FILES["adjunto"]["name"];
            $safe = nombreSeguro(pathinfo($original, PATHINFO_FILENAME));
            $filename = $safe . "_" . time() . "." . $ext;
            $destino = $dir . "/" . $filename;
            if (move_uploaded_file($_FILES["adjunto"]["tmp_name"], $destino)) {
                $ruta_publica = "uploads/empleados/" . $id . "/adjuntos/" . $filename;
                $stmt = $pdo->prepare("INSERT INTO empleado_adjuntos
                    (empleado_id, nombre_original, ruta, tipo, tamano)
                    VALUES (?,?,?,?,?)");
                $stmt->execute([
                    $id,
                    $original,
                    $ruta_publica,
                    $_FILES["adjunto"]["type"],
                    (int)$_FILES["adjunto"]["size"]
                ]);
                registrarHistorial(
                    $pdo,
                    $id,
                    $_SESSION["usuario"],
                    "adjunto",
                    "Se subio un adjunto: " . $original
                );
                header("Location: empleado_detalle.php?id=" . $id);
                exit;
            } else {
                $upload_error = "No se pudo guardar el adjunto";
            }
        }
    }
}

$ant = calcularAntiguedad($emp["fecha_ingreso"]);
$total = calcularVacaciones($ant["anios"]);
$usadas = vacacionesUsadas($pdo, $id);
$saldo = $total - $usadas;
$dni = obtenerDniDesdeCuil($emp["cuil"]);

$tipo_filtro = normalizarTipoIncidente($_GET["tipo"] ?? "");
$desde_filtro = trim($_GET["desde"] ?? "");
$hasta_filtro = trim($_GET["hasta"] ?? "");

$historial = obtenerHistorial($pdo, $id, 50);
$incidentes = obtenerIncidentes($pdo, $id, $tipo_filtro, $desde_filtro, $hasta_filtro);
$adjuntos = obtenerAdjuntos($pdo, $id);

$campos_export = [
    "nombre_apellido" => "Nombre y apellido",
    "cuil" => "CUIL",
    "dni" => "DNI",
    "legajo" => "Legajo",
    "funcion" => "Funcion",
    "situacion" => "Situacion",
    "categoria" => "Categoria",
    "fecha_ingreso" => "Fecha de ingreso",
    "antiguedad" => "Antiguedad",
    "vacaciones_totales" => "Vacaciones totales",
    "vacaciones_usadas" => "Vacaciones usadas",
    "vacaciones_saldo" => "Vacaciones saldo",
    "incidentes" => "Incidentes"
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de empleado</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container">
    <div class="page-header">
        <h2>Detalle del empleado</h2>
        <a href="empleados.php" class="btn btn-back">&larr; Volver al listado</a>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <p class="summary-label">Empleado</p>
            <p class="summary-value"><?= htmlspecialchars($emp["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></p>
            <p class="muted">Legajo: <?= htmlspecialchars($emp["legajo"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Estado</p>
            <p class="summary-value"><?= htmlspecialchars($emp["situacion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
            <p class="muted">Funcion: <?= htmlspecialchars($emp["funcion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
        </div>
        <div class="summary-card">
            <p class="summary-label">Vacaciones</p>
            <p class="summary-value"><?= (int)$saldo ?> dias disponibles</p>
            <p class="muted">Total: <?= (int)$total ?> · Usadas: <?= (int)$usadas ?></p>
        </div>
        <div class="summary-card summary-actions">
            <p class="summary-label">Acciones rapidas</p>
            <div class="actions">
                <a class="btn btn-add" href="empleado_detalle.php?id=<?= $id ?>&view=1">Ver perfil</a>
                <?php if ($can_edit): ?>
                    <a class="btn btn-edit" href="vacaciones_nueva.php?empleado_id=<?= $id ?>">Vacaciones</a>
                    <a class="btn btn-edit" href="empleado_detalle.php?id=<?= $id ?>&tab=incidentes">Incidentes</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tabs">
        <button class="tab-btn active" data-tab="resumen">Resumen</button>
        <button class="tab-btn" data-tab="incidentes">Incidentes</button>
        <button class="tab-btn" data-tab="adjuntos">Adjuntos</button>
        <button class="tab-btn" data-tab="historial">Historial</button>
        <button class="tab-btn" data-tab="exportar">Exportar</button>
    </div>

    <div class="tab-panel active" id="tab-resumen">
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="avatar">
                        <?php if (!empty($emp["foto"])): ?>
                            <img src="<?= htmlspecialchars($emp["foto"], ENT_QUOTES, "UTF-8") ?>" alt="Foto">
                        <?php else: ?>
                            <span><?= htmlspecialchars(substr($emp["nombre_apellido"], 0, 1), ENT_QUOTES, "UTF-8") ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($emp["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></h3>
                        <p class="muted">Legajo: <?= htmlspecialchars($emp["legajo"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                    </div>
                </div>
                <p><strong>CUIL:</strong> <?= htmlspecialchars($emp["cuil"], ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>DNI:</strong> <?= htmlspecialchars($dni, ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Funcion:</strong> <?= htmlspecialchars($emp["funcion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Situacion:</strong> <?= htmlspecialchars($emp["situacion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Ingreso:</strong> <?= htmlspecialchars($emp["fecha_ingreso"], ENT_QUOTES, "UTF-8") ?></p>

                <?php if ($can_edit): ?>
                <div class="section">
                    <h3>Foto</h3>
                    <?php if ($photo_error) echo "<p class='error'>$photo_error</p>"; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="foto">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <input type="file" name="foto" accept=".jpg,.jpeg,.png" required>
                        <button type="submit">Actualizar foto</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Vacaciones</h3>
                <p><strong>Antiguedad:</strong> <?= (int)$ant["anios"] ?> a&ntilde;os <?= (int)$ant["meses"] ?> meses</p>
                <p><strong>Total:</strong> <?= (int)$total ?> dias</p>
                <p><strong>Usadas:</strong> <?= (int)$usadas ?> dias</p>
                <p><strong>Saldo:</strong> <?= (int)$saldo ?> dias</p>
                <?php if ($can_edit): ?>
                    <a class="btn btn-add" href="vacaciones_nueva.php?empleado_id=<?= $id ?>">Registrar vacaciones</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="tab-panel" id="tab-incidentes">
        <div class="section">
            <h3>Incidentes</h3>

            <?php if ($error) echo "<p class='error'>$error</p>"; ?>

            <?php if ($can_edit): ?>
                <form method="post">
                    <input type="hidden" name="accion" value="incidente">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">

                    <label>Tipo</label>
                    <select name="tipo" required>
                        <option value="">Seleccionar</option>
                        <option value="accidente">Accidente</option>
                        <option value="reposo">Reposo</option>
                        <option value="amonestacion">Amonestacion</option>
                        <option value="suspencion">Suspencion</option>
                    </select>

                    <label>Fecha inicio</label>
                    <input type="date" name="fecha_inicio" required>

                    <label>Fecha fin</label>
                    <input type="date" name="fecha_fin" required>

                    <label>Motivo</label>
                    <input type="text" name="motivo" required>

                    <button type="submit">Agregar incidente</button>
                </form>
            <?php endif; ?>

            <div class="section">
                <h3>Filtrar</h3>
                <form method="get">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="accidente" <?= $tipo_filtro === "accidente" ? "selected" : "" ?>>Accidente</option>
                        <option value="reposo" <?= $tipo_filtro === "reposo" ? "selected" : "" ?>>Reposo</option>
                        <option value="amonestacion" <?= $tipo_filtro === "amonestacion" ? "selected" : "" ?>>Amonestacion</option>
                        <option value="suspencion" <?= $tipo_filtro === "suspencion" ? "selected" : "" ?>>Suspencion</option>
                    </select>

                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= htmlspecialchars($desde_filtro, ENT_QUOTES, "UTF-8") ?>">

                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= htmlspecialchars($hasta_filtro, ENT_QUOTES, "UTF-8") ?>">

                    <button type="submit">Aplicar filtros</button>
                </form>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($incidentes)): ?>
                    <tr><td colspan="4">Sin incidentes registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($incidentes as $inc): ?>
                    <tr>
                        <td><?= htmlspecialchars($inc["tipo"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($inc["fecha_inicio"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($inc["fecha_fin"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($inc["motivo"], ENT_QUOTES, "UTF-8") ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-panel" id="tab-adjuntos">
        <div class="section">
            <h3>Adjuntos</h3>

            <?php if ($upload_error) echo "<p class='error'>$upload_error</p>"; ?>

            <?php if ($can_edit): ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="adjunto">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                <input type="file" name="adjunto" required>
                <button type="submit">Subir adjunto</button>
            </form>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Tamano</th>
                        <th>Fecha</th>
                        <th>Descarga</th>
                        <?php if ($can_edit): ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($adjuntos)): ?>
                    <tr><td colspan="<?= $can_edit ? 6 : 5 ?>">Sin adjuntos.</td></tr>
                <?php else: ?>
                    <?php foreach ($adjuntos as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a["nombre_original"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($a["tipo"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= (int)$a["tamano"] ?> bytes</td>
                        <td><?= htmlspecialchars($a["creado_en"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <a class="btn btn-add" href="descargar_adjunto.php?id=<?= (int)$a["id"] ?>">Descargar</a>
                        </td>
                        <?php if ($can_edit): ?>
                        <td>
                            <form class="inline-form" method="post" action="adjunto_eliminar.php" onsubmit="return confirm('Eliminar adjunto?')">
                                <input type="hidden" name="id" value="<?= (int)$a["id"] ?>">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                                <button type="submit" class="btn btn-delete">Eliminar</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-panel" id="tab-historial">
        <div class="section">
            <h3>Historial reciente</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Accion</th>
                        <th>Descripcion</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="4">Sin movimientos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h["fecha"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($h["usuario"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($h["accion"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($h["descripcion"], ENT_QUOTES, "UTF-8") ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-panel" id="tab-exportar">
        <div class="section">
            <h3>Exportar</h3>
        <form method="post" action="exportar_empleado.php">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
            <input type="hidden" name="empleado_id" value="<?= $id ?>">

            <div class="checkbox-grid">
                <?php foreach ($campos_export as $key => $label): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="fields[]" value="<?= $key ?>" checked>
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <label class="inline">
                    <input type="radio" name="formato" value="xlsx" checked>
                    XLSX
                </label>
                <label class="inline">
                    <input type="radio" name="formato" value="pdf">
                    PDF
                </label>
                <label class="inline">
                    <input type="radio" name="formato" value="csv">
                    CSV
                </label>
            </div>

            <button type="submit">Descargar</button>
        </form>
    </div>

</div>
<script>
const tabs = document.querySelectorAll(".tab-btn");
const panels = document.querySelectorAll(".tab-panel");

function activarTab(nombre) {
    tabs.forEach(b => b.classList.remove("active"));
    panels.forEach(p => p.classList.remove("active"));
    const btn = Array.from(tabs).find(t => t.dataset.tab === nombre);
    const panel = document.getElementById("tab-" + nombre);
    if (btn && panel) {
        btn.classList.add("active");
        panel.classList.add("active");
    }
}

tabs.forEach(btn => {
    btn.addEventListener("click", () => {
        activarTab(btn.dataset.tab);
    });
});

const params = new URLSearchParams(window.location.search);
const initial = params.get("tab");
if (initial) {
    activarTab(initial);
}
</script>
</body>
</html>
