<?php
// Plantilla para Carta de Terminación
$institutionName = getConfig('institution_name', 'Instituto Tecnológico de Aguascalientes');
$departmentName = getConfig('department_name', 'Departamento de Gestión Tecnológica y Vinculación');
$currentDate = date('d/m/Y');
?>

<style>
    body {
        font-family: helvetica;
        font-size: 12pt;
        line-height: 1.5;
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
    }
    .header h1 {
        font-size: 16pt;
        font-weight: bold;
        margin: 0;
    }
    .header h2 {
        font-size: 14pt;
        font-weight: normal;
        margin: 0;
    }
    .content {
        text-align: justify;
    }
    .signature {
        margin-top: 100px;
        text-align: center;
    }
    .signature-line {
        border-top: 1px solid #000;
        width: 300px;
        margin: 0 auto;
        padding-top: 5px;
    }
    .document-number {
        text-align: right;
        font-weight: bold;
        margin-bottom: 20px;
    }
    .student-info {
        margin-bottom: 20px;
    }
    .activities {
        margin: 20px 0;
    }
</style>

<div class="document-number">
    Número: <?= $numeroCarta ?><br>
    Fecha: <?= $currentDate ?>
</div>

<div class="header">
    <h1><?= $institutionName ?></h1>
    <h2><?= $departmentName ?></h2>
</div>

<div class="content">
    <p><strong>CARTA DE TERMINACIÓN DE SERVICIO SOCIAL</strong></p>
    
    <div class="student-info">
        <p>Por medio de la presente se hace constar que el(la) C. <strong><?= htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?></strong>, 
        con número de control <strong><?= htmlspecialchars($solicitud['numero_control']) ?></strong>, 
        estudiante de la carrera de <strong><?= htmlspecialchars($solicitud['carrera']) ?></strong>, 
        ha cumplido satisfactoriamente con su servicio social en esta institución.</p>
    </div>
    
    <div class="activities">
        <p><strong>Periodo de servicio:</strong> <?= $solicitud['periodo_servicio'] ?></p>
        <p><strong>Horas cumplidas:</strong> <?= $solicitud['horas_cumplidas'] ?> horas</p>
        <p><strong>Laboratorio:</strong> <?= htmlspecialchars($solicitud['laboratorio']) ?></p>
        <p><strong>Proyecto:</strong> <?= htmlspecialchars($solicitud['nombre_proyecto']) ?></p>
        
        <p><strong>Actividades principales realizadas:</strong></p>
        <p><?= nl2br(htmlspecialchars($solicitud['actividades_principales'])) ?></p>
    </div>
    
    <p>Durante el desarrollo de sus actividades, el(la) estudiante demostró un nivel de desempeño 
    <strong><?= htmlspecialchars($solicitud['nivel_desempeno']) ?></strong>.</p>
    
    <?php if (!empty($solicitud['observaciones'])): ?>
    <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($solicitud['observaciones'])) ?></p>
    <?php endif; ?>
    
    <p>Se extiende la presente para los fines que al interesado(a) convengan.</p>
</div>

<div class="signature">
    <p>ATENTAMENTE</p>
    <p>"TECNOLOGÍA PROPIA E INDEPENDENCIA ECONÓMICA"</p>
    
    <div class="signature-line">
        <strong><?= htmlspecialchars($solicitud['jefe_nombre']) ?></strong><br>
        <?= htmlspecialchars($solicitud['departamento']) ?><br>
        <?= $institutionName ?>
    </div>
</div>