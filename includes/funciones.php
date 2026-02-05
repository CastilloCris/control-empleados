<?php

function calcularAntiguedad($fechaIngreso) {
    $ingreso = new DateTime($fechaIngreso);
    $hoy = new DateTime();
    $diff = $ingreso->diff($hoy);

    return [
        'anios' => $diff->y,
        'meses' => $diff->m
    ];
}

function calcularVacaciones($anios) {
    if ($anios < 5) return 14;
    if ($anios < 10) return 21;
    if ($anios < 20) return 28;
    return 35;
}

function vacacionesUsadas($pdo, $empleado_id) {
    $sql = "SELECT SUM(dias_tomados) AS total
            FROM vacaciones
            WHERE empleado_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empleado_id]);
    $row = $stmt->fetch();

    return $row["total"] ?? 0;
}
function vacacionesRestantes($pdo, $empleado_id, $fecha_ingreso) {
    $antiguedad = calcularAntiguedad($fecha_ingreso);
    $dias_totales = calcularVacaciones($antiguedad['anios']);
    $dias_usados = vacacionesUsadas($pdo, $empleado_id);

    return $dias_totales - $dias_usados;
}

function calcularDiasTomados($desde, $hasta) {
    if (!$desde || !$hasta) {
        return 0;
    }

    $inicio = new DateTime($desde);
    $fin = new DateTime($hasta);

    if ($inicio > $fin) {
        return 0;
    }

    $diff = $inicio->diff($fin);
    return $diff->days + 1;
}

function obtenerDniDesdeCuil($cuil) {
    $digits = preg_replace("/\D+/", "", (string)$cuil);
    if (strlen($digits) < 11) {
        return "";
    }
    return substr($digits, 2, 8);
}

function registrarHistorial($pdo, $empleado_id, $usuario, $accion, $descripcion) {
    $sql = "INSERT INTO empleado_historial
            (empleado_id, usuario, accion, descripcion, fecha)
            VALUES (?,?,?,?,NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empleado_id, $usuario, $accion, $descripcion]);
}

function obtenerHistorial($pdo, $empleado_id, $limite = 50) {
    $sql = "SELECT * FROM empleado_historial
            WHERE empleado_id = ?
            ORDER BY fecha DESC
            LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $empleado_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limite, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function normalizarTipoIncidente($tipo) {
    $tipo = strtolower(trim((string)$tipo));
    $map = [
        "accidente" => "accidente",
        "reposo" => "reposo",
        "amonestacion" => "amonestacion",
        "amonestación" => "amonestacion",
        "suspencion" => "suspencion",
        "suspensión" => "suspencion"
    ];
    return $map[$tipo] ?? "";
}

function obtenerIncidentes($pdo, $empleado_id, $tipo = "", $desde = "", $hasta = "") {
    $where = ["empleado_id = ?"];
    $params = [$empleado_id];

    if ($tipo) {
        $where[] = "tipo = ?";
        $params[] = $tipo;
    }
    if ($desde) {
        $where[] = "fecha_inicio >= ?";
        $params[] = $desde;
    }
    if ($hasta) {
        $where[] = "fecha_fin <= ?";
        $params[] = $hasta;
    }

    $sql = "SELECT * FROM empleado_incidentes
            WHERE " . implode(" AND ", $where) . "
            ORDER BY fecha_inicio DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function obtenerAdjuntos($pdo, $empleado_id) {
    $sql = "SELECT * FROM empleado_adjuntos
            WHERE empleado_id = ?
            ORDER BY creado_en DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empleado_id]);
    return $stmt->fetchAll();
}

function nombreSeguro($nombre) {
    $nombre = preg_replace("/[^a-zA-Z0-9\.\-_]+/", "_", $nombre);
    return trim($nombre, "_");
}

function fotoEmpleadoUrl($emp) {
    if (!empty($emp["foto"])) {
        return $emp["foto"];
    }
    return "";
}
