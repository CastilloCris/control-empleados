<?php
$pagina_actual = basename($_SERVER["PHP_SELF"] ?? "");
$usuario = $_SESSION["usuario"] ?? "";
$rol = $_SESSION["rol"] ?? "";
$is_master = !empty($_SESSION["master_ok"]);
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
            <?php if ($is_master): ?>
                <a class="nav-link <?= $pagina_actual === "admin_usuarios.php" ? "active" : "" ?>" href="admin_usuarios.php">Usuarios</a>
            <?php endif; ?>
        </div>
        <div class="nav-user">
            <?php if ($is_master): ?>
                <span class="user-pill master-pill">Modo maestro</span>
            <?php endif; ?>
            <span class="user-pill">
                <?= htmlspecialchars($usuario, ENT_QUOTES, "UTF-8") ?>
                ·
                <?= htmlspecialchars($rol, ENT_QUOTES, "UTF-8") ?>
            </span>
            <a class="btn btn-ghost" href="logout.php">Salir</a>
        </div>
    </div>
</nav>
