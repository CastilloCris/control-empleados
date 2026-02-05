<?php
$pagina_actual = basename($_SERVER["PHP_SELF"] ?? "");
$usuario = $_SESSION["usuario"] ?? "";
$rol = $_SESSION["rol"] ?? "";
?>
<nav class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark"></span>
            <span class="brand-text">Control Empleados</span>
        </a>
        <div class="nav-links">
            <a class="nav-link <?= $pagina_actual === "dashboard.php" ? "active" : "" ?>" href="dashboard.php">Panel</a>
            <a class="nav-link <?= $pagina_actual === "empleados.php" ? "active" : "" ?>" href="empleados.php">Empleados</a>
            <?php if ($rol === "admin"): ?>
                <a class="nav-link <?= $pagina_actual === "empleado_nuevo.php" ? "active" : "" ?>" href="empleado_nuevo.php">Nuevo</a>
            <?php endif; ?>
            <a class="nav-link" href="dashboard.php#vacaciones-curso">Vacaciones</a>
            <a class="nav-link" href="dashboard.php#incidentes-mes">Incidentes</a>
        </div>
        <div class="nav-user">
            <span class="user-pill">
                <?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>
                ·
                <?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>
            </span>
            <a class="btn btn-ghost" href="logout.php">Salir</a>
        </div>
    </div>
</nav>
