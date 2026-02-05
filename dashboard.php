<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

$busqueda = trim($_GET["busqueda"] ?? "");
$busqueda_error = "";
$emp_encontrado = null;
$emp_coincidencias = [];

if ($busqueda !== "") {
    $digits = preg_replace("/\D+/", "", $busqueda);
    if (strlen($digits) < 8) {
        $busqueda_error = "Ingresa un CUIL o DNI valido";
    } else {
        $stmt = $pdo->query("SELECT * FROM empleados ORDER BY nombre_apellido");
        $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($lista as $emp) {
            $cuil_digits = preg_replace("/\D+/", "", (string)$emp["cuil"]);
            $dni_emp = obtenerDniDesdeCuil($emp["cuil"]);
            if (strlen($digits) >= 11 && $digits === $cuil_digits) {
                $emp_coincidencias[] = $emp;
            } elseif (strlen($digits) === 8 && $digits === $dni_emp) {
                $emp_coincidencias[] = $emp;
            }
        }
        if (count($emp_coincidencias) === 1) {
            $emp_encontrado = $emp_coincidencias[0];
        }
        if (count($emp_coincidencias) === 0) {
            $busqueda_error = "No se encontro ningun empleado con ese CUIL o DNI";
        }
    }
}

$total_empleados = (int)$pdo->query("SELECT COUNT(*) FROM empleados")->fetchColumn();
$total_incidentes = (int)$pdo->query("SELECT COUNT(*) FROM empleado_incidentes")->fetchColumn();
$total_vacaciones = (int)$pdo->query("SELECT COUNT(*) FROM vacaciones")->fetchColumn();
$incidentes_mes = (int)$pdo->query("
    SELECT COUNT(*)
    FROM empleado_incidentes
    WHERE fecha_inicio BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
")->fetchColumn();
$vacaciones_activas = (int)$pdo->query("SELECT COUNT(*) FROM vacaciones WHERE CURDATE() BETWEEN fecha_desde AND fecha_hasta")->fetchColumn();

$inc_tipo = normalizarTipoIncidente($_GET["inc_tipo"] ?? "");
$inc_desde = trim($_GET["inc_desde"] ?? "");
$inc_hasta = trim($_GET["inc_hasta"] ?? "");

$inc_where = "ei.fecha_inicio BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())";
$inc_params = [];
if ($inc_tipo) {
    $inc_where .= " AND ei.tipo = ?";
    $inc_params[] = $inc_tipo;
}
if ($inc_desde) {
    $inc_where .= " AND ei.fecha_inicio >= ?";
    $inc_params[] = $inc_desde;
}
if ($inc_hasta) {
    $inc_where .= " AND ei.fecha_inicio <= ?";
    $inc_params[] = $inc_hasta;
}

$sql_inc = "
    SELECT ei.*, e.nombre_apellido
    FROM empleado_incidentes ei
    JOIN empleados e ON e.id = ei.empleado_id
    WHERE $inc_where
    ORDER BY ei.fecha_inicio DESC
    LIMIT 50
";
$stmt_inc = $pdo->prepare($sql_inc);
$stmt_inc->execute($inc_params);
$incidentes_lista = $stmt_inc->fetchAll(PDO::FETCH_ASSOC);

$vacaciones_lista = $pdo->query("
    SELECT v.*, e.nombre_apellido, e.legajo
    FROM vacaciones v
    JOIN empleados e ON e.id = v.empleado_id
    WHERE CURDATE() BETWEEN v.fecha_desde AND v.fecha_hasta
    ORDER BY v.fecha_desde ASC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>
<div class="container dashboard">
    <div class="dashboard-hero">
        <div>
            <h2>Bienvenido <?= htmlspecialchars($_SESSION["usuario"], ENT_QUOTES, "UTF-8") ?></h2>
            <p class="hero-meta">Rol: <?= htmlspecialchars($_SESSION["rol"], ENT_QUOTES, "UTF-8") ?></p>
        </div>
        <div class="hero-actions">
            <a href="empleados.php" class="btn">Empleados</a>
            <?php if ($_SESSION["rol"] === "admin"): ?>
                <a href="empleado_nuevo.php" class="btn">Nuevo empleado</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="system-status">
        <div>
            <p class="status-label">Fecha y hora</p>
            <p class="status-value"><?= date("d/m/Y H:i") ?></p>
        </div>
        <div>
            <p class="status-label">Total empleados</p>
            <p class="status-value"><?= $total_empleados ?></p>
        </div>
        <div>
            <p class="status-label">Total incidentes</p>
            <p class="status-value"><?= $total_incidentes ?></p>
        </div>
        <div>
            <p class="status-label">Total vacaciones</p>
            <p class="status-value"><?= $total_vacaciones ?></p>
        </div>
    </div>

<div class="section panel">
    <div class="section-head">
        <h3>Indicadores rapidos</h3>
        <p class="section-sub">Resumen general del ultimo mes.</p>
    </div>
    <div class="menu-grid">
        <a href="empleados.php" class="menu-card stat stat-primary link-card">
            <p class="menu-title">Empleados activos</p>
            <p class="menu-value large"><?= $total_empleados ?></p>
        </a>
        <a href="#incidentes-mes" class="menu-card stat stat-warning link-card">
            <p class="menu-title">Incidentes (mes)</p>
            <p class="menu-value large"><?= $incidentes_mes ?></p>
        </a>
        <a href="empleados.php?f=vacaciones" class="menu-card stat stat-success link-card">
            <p class="menu-title">Vacaciones en curso</p>
            <p class="menu-value large"><?= $vacaciones_activas ?></p>
        </a>
    </div>
</div>

<div class="section panel" id="incidentes-mes">
    <div class="section-head">
        <h3>Incidentes del mes en curso</h3>
        <p class="section-sub">Ultimos registros cargados.</p>
    </div>
    <form class="filter-bar" method="get">
        <input type="hidden" name="busqueda" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, "UTF-8") ?>">
        <label>Tipo</label>
        <select name="inc_tipo">
            <option value="">Todos</option>
            <option value="accidente" <?= $inc_tipo === "accidente" ? "selected" : "" ?>>Accidente</option>
            <option value="reposo" <?= $inc_tipo === "reposo" ? "selected" : "" ?>>Reposo</option>
            <option value="amonestacion" <?= $inc_tipo === "amonestacion" ? "selected" : "" ?>>Amonestacion</option>
            <option value="suspencion" <?= $inc_tipo === "suspencion" ? "selected" : "" ?>>Suspencion</option>
        </select>
        <label>Desde</label>
        <input type="date" name="inc_desde" value="<?= htmlspecialchars($inc_desde, ENT_QUOTES, "UTF-8") ?>">
        <label>Hasta</label>
        <input type="date" name="inc_hasta" value="<?= htmlspecialchars($inc_hasta, ENT_QUOTES, "UTF-8") ?>">
        <button type="submit">Filtrar</button>
    </form>
    <?php if (empty($incidentes_lista)): ?>
        <p class="muted">No hay incidentes registrados este mes.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Tipo</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($incidentes_lista as $inc): ?>
                <tr>
                    <td><?= htmlspecialchars($inc["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($inc["tipo"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($inc["fecha_inicio"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($inc["fecha_fin"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($inc["motivo"], ENT_QUOTES, "UTF-8") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="section panel" id="vacaciones-curso">
    <div class="section-head">
        <h3>Vacaciones en curso</h3>
        <p class="section-sub">Empleados actualmente de licencia.</p>
    </div>
    <?php if (empty($vacaciones_lista)): ?>
        <p class="muted">No hay vacaciones activas en este momento.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Legajo</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Dias</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vacaciones_lista as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($v["legajo"] ?? "", ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($v["fecha_desde"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($v["fecha_hasta"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= (int)$v["dias_tomados"] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="section panel">
    <div class="section-head">
        <h3>Acciones</h3>
        <p class="section-sub">Accesos rapidos a tareas frecuentes.</p>
    </div>
    <div class="menu-grid">
        <a href="empleados.php" class="menu-card link-card">
            <p class="menu-title">Listado de empleados</p>
            <p class="menu-desc">Ver, editar y acceder al detalle.</p>
        </a>
        <?php if ($_SESSION["rol"] === "admin"): ?>
            <a href="empleado_nuevo.php" class="menu-card link-card">
                <p class="menu-title">Cargar empleado</p>
                <p class="menu-desc">Alta de nuevo personal.</p>
            </a>
        <?php endif; ?>
        <?php if ($_SESSION["rol"] === "admin"): ?>
            <a href="empleados.php" class="menu-card link-card">
                <p class="menu-title">Registrar vacaciones</p>
                <p class="menu-desc">Selecciona un empleado para cargar vacaciones.</p>
            </a>
            <a href="empleados.php" class="menu-card link-card">
                <p class="menu-title">Registrar incidente</p>
                <p class="menu-desc">Accede al detalle y agrega accidentes o sanciones.</p>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="section panel search-panel">
    <div class="section-head">
        <h3>Buscar empleado por CUIL o DNI</h3>
        <p class="section-sub">Acceso rapido al perfil y vacaciones.</p>
    </div>
    <?php if ($busqueda_error) echo "<p class='error'>{$busqueda_error}</p>"; ?>
    <form method="get">
        <label>CUIL o DNI</label>
        <input type="text" name="busqueda" placeholder="Ej: 20-12345678-3 o 12345678" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, "UTF-8") ?>" required>
        <button type="submit">Buscar</button>
    </form>

    <?php if ($emp_encontrado): ?>
        <?php
            $ant = calcularAntiguedad($emp_encontrado["fecha_ingreso"]);
            $total = calcularVacaciones($ant["anios"]);
            $usadas = vacacionesUsadas($pdo, $emp_encontrado["id"]);
            $saldo = $total - $usadas;
            $dni = obtenerDniDesdeCuil($emp_encontrado["cuil"]);
        ?>
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="avatar">
                        <?php if (!empty($emp_encontrado["foto"])): ?>
                            <img src="<?= htmlspecialchars($emp_encontrado["foto"], ENT_QUOTES, "UTF-8") ?>" alt="Foto">
                        <?php else: ?>
                            <span><?= htmlspecialchars(substr($emp_encontrado["nombre_apellido"], 0, 1), ENT_QUOTES, "UTF-8") ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($emp_encontrado["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></h3>
                        <p class="muted">Legajo: <?= htmlspecialchars($emp_encontrado["legajo"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                    </div>
                </div>
                <p><strong>CUIL:</strong> <?= htmlspecialchars($emp_encontrado["cuil"], ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>DNI:</strong> <?= htmlspecialchars($dni, ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Funcion:</strong> <?= htmlspecialchars($emp_encontrado["funcion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Situacion:</strong> <?= htmlspecialchars($emp_encontrado["situacion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Ingreso:</strong> <?= htmlspecialchars($emp_encontrado["fecha_ingreso"], ENT_QUOTES, "UTF-8") ?></p>
            </div>
            <div class="card">
                <h3>Vacaciones</h3>
                <p><strong>Antiguedad:</strong> <?= (int)$ant["anios"] ?> a&ntilde;os <?= (int)$ant["meses"] ?> meses</p>
                <p><strong>Total:</strong> <?= (int)$total ?> dias</p>
                <p><strong>Usadas:</strong> <?= (int)$usadas ?> dias</p>
                <p><strong>Saldo:</strong> <?= (int)$saldo ?> dias</p>
            </div>
        </div>

        <div class="actions">
            <a href="empleado_detalle.php?id=<?= (int)$emp_encontrado["id"] ?>&view=1" class="btn btn-add">Ver detalle completo</a>
            <?php if ($_SESSION["rol"] === "admin"): ?>
                <a href="vacaciones_nueva.php?empleado_id=<?= (int)$emp_encontrado["id"] ?>" class="btn btn-edit">Registrar vacaciones</a>
                <a href="empleado_detalle.php?id=<?= (int)$emp_encontrado["id"] ?>&tab=incidentes" class="btn btn-edit">Registrar incidente</a>
            <?php endif; ?>
        </div>
    <?php elseif (count($emp_coincidencias) > 1): ?>
        <p class="muted">Se encontraron varios empleados:</p>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>CUIL</th>
                    <th>DNI</th>
                    <th>Accion</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($emp_coincidencias as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e["nombre_apellido"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($e["cuil"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars(obtenerDniDesdeCuil($e["cuil"]), ENT_QUOTES, "UTF-8") ?></td>
                    <td><a class="btn btn-add" href="empleado_detalle.php?id=<?= (int)$e["id"] ?>&view=1">Ver</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</div>
</body>
</html>
