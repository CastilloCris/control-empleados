<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

$q = trim($_GET["q"] ?? "");
$filtro = trim($_GET["f"] ?? "");

$page = max(1, (int)($_GET["page"] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = "";
$params = [];
if ($q !== "") {
    $where = "WHERE (nombre_apellido LIKE ? OR cuil LIKE ? OR funcion LIKE ?)";
    $like = "%" . $q . "%";
    $params = [$like, $like, $like];
}
if ($filtro === "vacaciones") {
    $where .= ($where ? " AND " : "WHERE ") . "id IN (SELECT empleado_id FROM vacaciones WHERE CURDATE() BETWEEN fecha_desde AND fecha_hasta)";
}

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM empleados $where");
$total_stmt->execute($params);
$total_items = (int)$total_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_items / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM empleados $where ORDER BY nombre_apellido LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$vacaciones_activos = $pdo->query("
    SELECT DISTINCT empleado_id
    FROM vacaciones
    WHERE CURDATE() BETWEEN fecha_desde AND fecha_hasta
")->fetchAll(PDO::FETCH_COLUMN);
$vacaciones_set = array_fill_keys($vacaciones_activos, true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empleados</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include "includes/nav.php"; ?>

<div class="container">

<div class="page-header">
    <h2>Listado de empleados</h2>
    <div class="actions">
        <?php if ($_SESSION["rol"] === "admin"): ?>
            <a href="empleado_nuevo.php" class="btn btn-add">Agregar empleado</a>
        <?php endif; ?>
        <a href="dashboard.php" class="btn btn-back">&larr; Volver</a>
    </div>
</div>

<form class="filter-bar" method="get">
    <label>Buscar</label>
    <input type="text" name="q" placeholder="Nombre, CUIL o funcion" value="<?= htmlspecialchars($q, ENT_QUOTES, "UTF-8") ?>">
    <button type="submit">Buscar</button>
    <?php if ($filtro): ?>
        <input type="hidden" name="f" value="<?= htmlspecialchars($filtro, ENT_QUOTES, "UTF-8") ?>">
    <?php endif; ?>
    <?php if ($q !== "" || $filtro): ?>
        <a class="btn btn-ghost" href="empleados.php">Limpiar</a>
    <?php endif; ?>
</form>

<?php if (empty($empleados)): ?>
    <div class="card">
        <p class="muted">No hay empleados cargados.</p>
    </div>
<?php else: ?>
    <div class="employee-grid">
        <?php foreach ($empleados as $emp):
            $ant = calcularAntiguedad($emp["fecha_ingreso"]);
            $total = calcularVacaciones($ant["anios"]);
            $usadas = vacacionesUsadas($pdo, $emp["id"]);
            $saldo = $total - $usadas;
            $inicial = substr((string)$emp["nombre_apellido"], 0, 1);
        ?>
        <div class="employee-card">
            <div class="employee-header">
                <div class="avatar avatar-sm">
                    <span><?= htmlspecialchars($inicial, ENT_QUOTES, "UTF-8") ?></span>
                </div>
                <div>
                    <h3>
                        <a href="empleado_detalle.php?id=<?= (int)$emp["id"] ?>">
                            <?= htmlspecialchars($emp["nombre_apellido"] ?? "", ENT_QUOTES, "UTF-8") ?>
                        </a>
                    </h3>
                    <p class="muted">Legajo: <?= htmlspecialchars($emp["legajo"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                </div>
                <?php if (!empty($vacaciones_set[$emp["id"]])): ?>
                    <span class="badge badge-success">En vacaciones</span>
                <?php endif; ?>
            </div>

            <div class="employee-meta">
                <p><strong>CUIL:</strong> <?= htmlspecialchars($emp["cuil"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Funcion:</strong> <?= htmlspecialchars($emp["funcion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Situacion:</strong> <?= htmlspecialchars($emp["situacion"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Ingreso:</strong> <?= htmlspecialchars($emp["fecha_ingreso"] ?? "", ENT_QUOTES, "UTF-8") ?></p>
                <p><strong>Antiguedad:</strong> <?= (int)$ant["anios"] ?> a&ntilde;os <?= (int)$ant["meses"] ?> meses</p>
            </div>

            <div class="employee-vacaciones">
                <span>Total: <?= (int)$total ?> dias</span>
                <span>Usadas: <?= (int)$usadas ?> dias</span>
                <span>Saldo: <?= (int)$saldo ?> dias</span>
            </div>

            <div class="actions">
                <a class="btn btn-add" href="empleado_detalle.php?id=<?= (int)$emp["id"] ?>">Ver detalle</a>
                <?php if ($_SESSION["rol"] === "admin"): ?>
                    <a class="btn btn-edit" href="vacaciones_nueva.php?empleado_id=<?= (int)$emp["id"] ?>">Vacaciones</a>
                    <a class="btn btn-edit" href="empleado_editar.php?id=<?= (int)$emp["id"] ?>">Editar</a>
                    <form class="inline-form" method="post" action="empleado_eliminar.php" onsubmit="return confirm('Eliminar empleado?')">
                        <input type="hidden" name="id" value="<?= (int)$emp["id"] ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"], ENT_QUOTES, "UTF-8") ?>">
                        <button type="submit" class="btn btn-delete">Eliminar</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="?page=<?= $page - 1 ?><?= $q !== "" ? "&q=" . urlencode($q) : "" ?><?= $filtro ? "&f=" . urlencode($filtro) : "" ?>">&larr; Anterior</a>
            <?php endif; ?>
            <span class="muted">Pagina <?= $page ?> de <?= $total_pages ?></span>
            <?php if ($page < $total_pages): ?>
                <a class="btn btn-ghost" href="?page=<?= $page + 1 ?><?= $q !== "" ? "&q=" . urlencode($q) : "" ?><?= $filtro ? "&f=" . urlencode($filtro) : "" ?>">Siguiente &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

</div>
</body>

</html>
