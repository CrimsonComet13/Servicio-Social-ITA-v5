<?php
// Variables disponibles: $reporte, $tipoReporte, $formato
$nombre_completo = $reporte['nombre'] . ' ' . $reporte['apellido_paterno'] . ' ' . $reporte['apellido_materno'];
$fecha_actual = date('d/m/Y');
$jefe_laboratorio = $reporte['jefe_lab_nombre'] ?? $reporte['jefe_nombre'];
$laboratorio = $reporte['laboratorio'] ?? $reporte['departamento'];
$nombre_proyecto = $reporte['nombre_proyecto'];
$periodo_reporte = $reporte['periodo_reporte'] ?? 'N/A'; // Asumiendo que existe este campo en los reportes
$horas_reportadas = $reporte['horas_reportadas'] ?? 0; // Asumiendo que existe este campo en los reportes

$titulo_documento = ($tipoReporte === 'bimestral') ? 'REPORTE BIMESTRAL DE SERVICIO SOCIAL' : 'REPORTE FINAL DE SERVICIO SOCIAL';
$seccion_evaluacion = ($tipoReporte === 'bimestral') ? 'EVALUACIÓN DEL BIMESTRE' : 'CALIFICACIÓN FINAL';

// Estilos
$style = '
<style>
    body { font-family: "helvetica"; font-size: 9pt; }
    .header { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 5px; }
    .subheader { text-align: center; font-size: 9pt; margin-bottom: 10px; }
    .table-main { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .table-main td, .table-main th { border: 1px solid #000; padding: 4px; vertical-align: top; }
    .label { font-weight: bold; background-color: #f0f0f0; }
    .title-section { background-color: #cccccc; font-weight: bold; text-align: center; padding: 6px; font-size: 10pt; }
    .signature-area { width: 100%; margin-top: 30px; }
    .signature-col { width: 50%; text-align: center; padding-top: 15px; }
    .line { border-top: 1px solid #000; width: 80%; margin: 0 auto; }
    .evaluation-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .evaluation-table th { background-color: #dddddd; font-weight: bold; text-align: center; }
    .evaluation-table td { text-align: center; }
</style>
';

$html = $style;

$html .= '
<div class="header">
    INSTITUTO TECNOLÓGICO DE AGUASCALIENTES
</div>
<div class="subheader">
    DEPARTAMENTO DE SERVICIO SOCIAL Y RESIDENCIA PROFESIONAL
</div>

<div class="title-section">
    ' . $titulo_documento . ' (' . $formato . ')
</div>

<table class="table-main" cellspacing="0" cellpadding="4">
    <tr>
        <td colspan="4" class="title-section">DATOS GENERALES</td>
    </tr>
    <tr>
        <td class="label" style="width: 25%;">NOMBRE DEL ESTUDIANTE:</td>
        <td style="width: 75%;" colspan="3">' . $nombre_completo . '</td>
    </tr>
    <tr>
        <td class="label">NÚMERO DE CONTROL:</td>
        <td style="width: 25%;">' . $reporte['numero_control'] . '</td>
        <td class="label" style="width: 25%;">PERIODO DEL REPORTE:</td>
        <td style="width: 25%;">' . $periodo_reporte . '</td>
    </tr>
    <tr>
        <td class="label">NOMBRE DEL PROYECTO:</td>
        <td colspan="3">' . $nombre_proyecto . '</td>
    </tr>
    <tr>
        <td class="label">RESPONSABLE DEL PROYECTO:</td>
        <td colspan="3">' . $jefe_laboratorio . '</td>
    </tr>
    <tr>
        <td class="label">HORAS REPORTADAS EN ESTE PERIODO:</td>
        <td colspan="3">' . $horas_reportadas . '</td>
    </tr>
</table>

<div class="title-section">
    ACTIVIDADES REALIZADAS
</div>
<div style="border: 1px solid #000; padding: 10px; min-height: 50px; margin-bottom: 15px;">
    ' . nl2br(htmlspecialchars($reporte['actividades_realizadas'] ?? 'No se registraron actividades.')) . '
</div>

<div class="title-section">
    ' . $seccion_evaluacion . '
</div>

<table class="evaluation-table" cellspacing="0" cellpadding="4">
    <tr>
        <th style="width: 5%;">No.</th>
        <th style="width: 55%;">Criterio a Evaluar</th>
        <th style="width: 10%;">Calificación (0-4)</th>
        <th style="width: 30%;">Evaluador</th>
    </tr>
    <tr>
        <td colspan="4" class="title-section" style="background-color: #e0e0e0;">EVALUACIÓN POR EL RESPONSABLE DEL PROYECTO</td>
    </tr>
';

$i = 1;
foreach ($reporte['evaluaciones']['responsable'] as $eval) {
    $html .= '
    <tr>
        <td>' . $i++ . '</td>
        <td style="text-align: left;">' . $eval['descripcion'] . '</td>
        <td>' . $eval['calificacion'] . '</td>
        <td>' . ($eval['tipo_usuario'] === 'jefe_departamento' ? 'Jefe de Depto.' : 'Jefe de Lab.') . '</td>
    </tr>
    ';
}

$html .= '
    <tr>
        <td colspan="4" class="title-section" style="background-color: #e0e0e0;">AUTOEVALUACIÓN POR EL ESTUDIANTE</td>
    </tr>
';

$i = 1;
foreach ($reporte['evaluaciones']['estudiante'] as $eval) {
    $html .= '
    <tr>
        <td>' . $i++ . '</td>
        <td style="text-align: left;">' . $eval['descripcion'] . '</td>
        <td>' . $eval['calificacion'] . '</td>
        <td>Estudiante</td>
    </tr>
    ';
}

$html .= '
</table>

<div style="margin-top: 15px;">
    <p class="label">OBSERVACIONES DEL RESPONSABLE:</p>
    <div style="border: 1px solid #000; padding: 10px; min-height: 30px; margin-bottom: 10px;">
        ' . nl2br(htmlspecialchars($reporte['observaciones_responsable'] ?? 'Sin observaciones.')) . '
    </div>
    <p class="label">OBSERVACIONES DEL ESTUDIANTE:</p>
    <div style="border: 1px solid #000; padding: 10px; min-height: 30px;">
        ' . nl2br(htmlspecialchars($reporte['observaciones_estudiante'] ?? 'Sin observaciones.')) . '
    </div>
</div>

<table class="signature-area">
    <tr>
        <td class="signature-col">
            <div class="line"></div>
            <p><strong>' . $jefe_laboratorio . '</strong></p>
            <p>Nombre y Firma del Responsable del Proyecto</p>
        </td>
        <td class="signature-col">
            <div class="line"></div>
            <p><strong>' . $nombre_completo . '</strong></p>
            <p>Nombre y Firma del Estudiante</p>
        </td>
    </tr>
</table>

<div style="margin-top: 20px; text-align: right; font-size: 8pt;">
    ' . $formato . ' Rev. 1.0
</div>
';

echo $html;
?>
