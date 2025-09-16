<?php
// Plantilla para Oficio de Presentación
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
        margin-bottom: 20px;
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
        margin-top: 50px;
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
</style>

<div class="document-number">
    Número: <?= $numeroOficio ?><br>
    Fecha: <?= $currentDate ?>
</div>

<div class="header">
    <h1><?= $institutionName ?></h1>
    <h2><?= $departmentName ?></h2>
</div>

<div class="content">
    <p><strong>OFICIO DE PRESENTACIÓN</strong></p>
    
    <p>Por medio del presente, se hace constar que el(la) C. <strong><?= htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?></strong>, 
    estudiante de la carrera de <strong><?= htmlspecialchars($solicitud['carrera']) ?></strong> con número de control <strong><?= htmlspecialchars($solicitud['numero_control']) ?></strong>, 
    ha sido aceptado(a) para realizar su servicio social en este Instituto.</p>
    
    <p>El servicio social se llevará a cabo en el <strong><?= htmlspecialchars($solicitud['laboratorio']) ?></strong> 
    bajo la supervisión del <strong><?= htmlspecialchars($solicitud['jefe_lab_nombre']) ?></strong>, 
    desarrollando el proyecto: <strong>"<?= htmlspecialchars($solicitud['nombre_proyecto']) ?>"</strong>.</p>
    
    <p>El periodo de servicio social será del <strong><?= formatDate($solicitud['fecha_inicio_propuesta']) ?></strong> 
    al <strong><?= formatDate($solicitud['fecha_fin_propuesta']) ?></strong>, con una duración total de 
    <strong><?= getConfig('horas_servicio_social', 500) ?> horas</strong>.</p>
    
    <p>Se extiende el presente documento para los fines que al interesado(a) convengan.</p>
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