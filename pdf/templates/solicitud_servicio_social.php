<?php
// Variables disponibles: $solicitud
$nombre_completo = $solicitud['nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno'];
$fecha_actual = date('d/m/Y');
$fecha_inicio = formatDate($solicitud['fecha_inicio_propuesta']);
$fecha_fin = formatDate($solicitud['fecha_fin_propuesta']);
$periodo = $fecha_inicio . ' al ' . $fecha_fin;
$horas_propuestas = $solicitud['horas_propuestas'];
$nombre_proyecto = $solicitud['nombre_proyecto'];
$jefe_departamento = $solicitud['jefe_nombre'];
$jefe_laboratorio = $solicitud['jefe_lab_nombre'] ?? $solicitud['jefe_nombre']; // Si no hay jefe de lab, usa el depto.
$laboratorio = $solicitud['laboratorio'] ?? $solicitud['departamento'];
$carrera = $solicitud['carrera'];
$semestre = $solicitud['semestre'];
$numero_control = $solicitud['numero_control'];
$telefono = $solicitud['telefono'];
$email = $solicitud['email']; // Asumiendo que el email está en la tabla de estudiantes o usuarios

// Estilos básicos para replicar el formato
$style = '
<style>
    body { font-family: "helvetica"; font-size: 10pt; }
    .header { text-align: center; font-size: 12pt; font-weight: bold; margin-bottom: 10px; }
    .subheader { text-align: center; font-size: 10pt; margin-bottom: 15px; }
    .table-main { width: 100%; border-collapse: collapse; }
    .table-main td { border: 1px solid #000; padding: 5px; }
    .label { font-weight: bold; background-color: #f0f0f0; }
    .title-section { background-color: #cccccc; font-weight: bold; text-align: center; padding: 8px; }
    .signature-area { width: 100%; margin-top: 40px; }
    .signature-col { width: 50%; text-align: center; padding-top: 20px; }
    .line { border-top: 1px solid #000; width: 80%; margin: 0 auto; }
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
    SOLICITUD DE SERVICIO SOCIAL
</div>

<table class="table-main" cellspacing="0" cellpadding="5">
    <tr>
        <td colspan="4" class="title-section">DATOS DEL ESTUDIANTE</td>
    </tr>
    <tr>
        <td class="label" style="width: 25%;">NOMBRE COMPLETO:</td>
        <td style="width: 75%;">' . $nombre_completo . '</td>
    </tr>
    <tr>
        <td class="label">NÚMERO DE CONTROL:</td>
        <td>' . $numero_control . '</td>
    </tr>
    <tr>
        <td class="label">CARRERA:</td>
        <td>' . $carrera . '</td>
    </tr>
    <tr>
        <td class="label">SEMESTRE:</td>
        <td>' . $semestre . '</td>
    </tr>
    <tr>
        <td class="label">TELÉFONO:</td>
        <td>' . $telefono . '</td>
    </tr>
    <tr>
        <td class="label">CORREO ELECTRÓNICO:</td>
        <td>' . $email . '</td>
    </tr>
    
    <tr>
        <td colspan="4" class="title-section">DATOS DEL PROGRAMA/PROYECTO</td>
    </tr>
    <tr>
        <td class="label">NOMBRE DEL PROYECTO:</td>
        <td colspan="3">' . $nombre_proyecto . '</td>
    </tr>
    <tr>
        <td class="label">DEPENDENCIA/LABORATORIO:</td>
        <td colspan="3">' . $laboratorio . '</td>
    </tr>
    <tr>
        <td class="label">RESPONSABLE DEL PROYECTO:</td>
        <td colspan="3">' . $jefe_laboratorio . '</td>
    </tr>
    <tr>
        <td class="label">PERIODO DE REALIZACIÓN:</td>
        <td colspan="3">' . $periodo . '</td>
    </tr>
    <tr>
        <td class="label">TOTAL DE HORAS:</td>
        <td colspan="3">' . $horas_propuestas . '</td>
    </tr>
</table>

<div style="margin-top: 20px; text-align: justify;">
    <p>
        Por medio de la presente, solicito la autorización para realizar mi Servicio Social en el proyecto 
        <strong>"' . $nombre_proyecto . '"</strong>, bajo la supervisión del 
        <strong>' . $jefe_laboratorio . '</strong>, en el periodo comprendido del 
        <strong>' . $fecha_inicio . '</strong> al <strong>' . $fecha_fin . '</strong>, 
        con un total de <strong>' . $horas_propuestas . '</strong> horas.
    </p>
    <p>
        Agradezco de antemano su atención y apoyo.
    </p>
</div>

<table class="signature-area">
    <tr>
        <td class="signature-col">
            <div class="line"></div>
            <p><strong>' . $nombre_completo . '</strong></p>
            <p>Nombre y Firma del Estudiante</p>
        </td>
        <td class="signature-col">
            <div class="line"></div>
            <p><strong>' . $jefe_departamento . '</strong></p>
            <p>Nombre y Firma del Jefe de Departamento</p>
        </td>
    </tr>
</table>

<div style="margin-top: 40px; text-align: right; font-size: 8pt;">
    ITA-VI-SS-FO-01 Rev. 1.0
</div>
';

echo $html;
?>
