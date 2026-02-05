<?php
include "includes/auth.php";
include "includes/conexion.php";
include "includes/funciones.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: empleados.php");
    exit;
}

$csrf = $_POST["csrf"] ?? "";
if (!hash_equals($_SESSION["csrf"], $csrf)) {
    die("Solicitud invalida");
}

$id = isset($_POST["empleado_id"]) ? (int)$_POST["empleado_id"] : 0;
$formato = $_POST["formato"] ?? "xlsx";
$fields = $_POST["fields"] ?? [];

if ($id <= 0) {
    die("Empleado invalido");
}

if (empty($fields)) {
    die("Selecciona al menos un campo");
}

$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    die("Empleado no encontrado");
}

$ant = calcularAntiguedad($emp["fecha_ingreso"]);
$total = calcularVacaciones($ant["anios"]);
$usadas = vacacionesUsadas($pdo, $id);
$saldo = $total - $usadas;
$dni = obtenerDniDesdeCuil($emp["cuil"]);

$map = [
    "nombre_apellido" => ["Nombre y apellido", $emp["nombre_apellido"]],
    "cuil" => ["CUIL", $emp["cuil"]],
    "dni" => ["DNI", $dni],
    "legajo" => ["Legajo", $emp["legajo"]],
    "funcion" => ["Funcion", $emp["funcion"]],
    "situacion" => ["Situacion", $emp["situacion"]],
    "categoria" => ["Categoria", $emp["categoria"]],
    "fecha_ingreso" => ["Fecha de ingreso", $emp["fecha_ingreso"]],
    "antiguedad" => ["Antiguedad", $ant["anios"] . " anos " . $ant["meses"] . " meses"],
    "vacaciones_totales" => ["Vacaciones totales", $total],
    "vacaciones_usadas" => ["Vacaciones usadas", $usadas],
    "vacaciones_saldo" => ["Vacaciones saldo", $saldo],
    "incidentes" => ["Incidentes", ""]
];

$descripcion_export = "Exportacion " . $formato . " con campos: " . implode(", ", $fields);
registrarHistorial(
    $pdo,
    $id,
    $_SESSION["usuario"],
    "exportacion",
    $descripcion_export
);

if ($formato === "csv") {
    $filename = "empleado_" . $id . ".csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    $out = fopen("php://output", "w");
    fputcsv($out, ["Campo", "Valor"]);
    foreach ($fields as $f) {
        if (!isset($map[$f]) || $f === "incidentes") {
            continue;
        }
        fputcsv($out, [$map[$f][0], $map[$f][1]]);
    }
    if (in_array("incidentes", $fields, true)) {
        fputcsv($out, []);
        fputcsv($out, ["Incidentes"]);
        fputcsv($out, ["Tipo", "Inicio", "Fin", "Motivo"]);
        $incidentes = obtenerIncidentes($pdo, $id);
        foreach ($incidentes as $inc) {
            fputcsv($out, [$inc["tipo"], $inc["fecha_inicio"], $inc["fecha_fin"], $inc["motivo"]]);
        }
    }
    fclose($out);
    exit;
}

if ($formato === "xlsx") {
    $autoload = __DIR__ . "/vendor/autoload.php";
    if (!file_exists($autoload)) {
        die("Falta instalar dependencias para XLSX/PDF. Ejecuta: composer require phpoffice/phpspreadsheet dompdf/dompdf");
    }
    require_once $autoload;

    if (!class_exists("\\PhpOffice\\PhpSpreadsheet\\Spreadsheet")) {
        die("PhpSpreadsheet no esta disponible");
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle("Empleado");

    $sheet->setCellValue("A1", "Campo");
    $sheet->setCellValue("B1", "Valor");
    $row = 2;

    foreach ($fields as $f) {
        if (!isset($map[$f]) || $f === "incidentes") {
            continue;
        }
        $sheet->setCellValue("A" . $row, $map[$f][0]);
        $sheet->setCellValue("B" . $row, $map[$f][1]);
        $row++;
    }

    if (in_array("incidentes", $fields, true)) {
        $incidentes = obtenerIncidentes($pdo, $id);
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle("Incidentes");
        $sheet2->setCellValue("A1", "Tipo");
        $sheet2->setCellValue("B1", "Inicio");
        $sheet2->setCellValue("C1", "Fin");
        $sheet2->setCellValue("D1", "Motivo");
        $r = 2;
        foreach ($incidentes as $inc) {
            $sheet2->setCellValue("A" . $r, $inc["tipo"]);
            $sheet2->setCellValue("B" . $r, $inc["fecha_inicio"]);
            $sheet2->setCellValue("C" . $r, $inc["fecha_fin"]);
            $sheet2->setCellValue("D" . $r, $inc["motivo"]);
            $r++;
        }
    }

    $filename = "empleado_" . $id . ".xlsx";
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}

if ($formato === "pdf") {
    $autoload = __DIR__ . "/vendor/autoload.php";
    if (!file_exists($autoload)) {
        die("Falta instalar dependencias para XLSX/PDF. Ejecuta: composer require phpoffice/phpspreadsheet dompdf/dompdf");
    }
    require_once $autoload;

    if (!class_exists("\\Dompdf\\Dompdf")) {
        die("Dompdf no esta disponible");
    }

    $html = "<h2>Empleado</h2><table border='1' cellpadding='6' cellspacing='0' width='100%'>";
    foreach ($fields as $f) {
        if (!isset($map[$f]) || $f === "incidentes") {
            continue;
        }
        $label = htmlspecialchars($map[$f][0]);
        $value = htmlspecialchars((string)$map[$f][1]);
        $html .= "<tr><td><strong>{$label}</strong></td><td>{$value}</td></tr>";
    }
    $html .= "</table>";

    if (in_array("incidentes", $fields, true)) {
        $incidentes = obtenerIncidentes($pdo, $id);
        $html .= "<h3>Incidentes</h3><table border='1' cellpadding='6' cellspacing='0' width='100%'>";
        $html .= "<tr><th>Tipo</th><th>Inicio</th><th>Fin</th><th>Motivo</th></tr>";
        foreach ($incidentes as $inc) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($inc["tipo"]) . "</td>";
            $html .= "<td>" . htmlspecialchars($inc["fecha_inicio"]) . "</td>";
            $html .= "<td>" . htmlspecialchars($inc["fecha_fin"]) . "</td>";
            $html .= "<td>" . htmlspecialchars($inc["motivo"]) . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
    }

    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();
    $filename = "empleado_" . $id . ".pdf";
    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    echo $dompdf->output();
    exit;
}

die("Formato invalido");
