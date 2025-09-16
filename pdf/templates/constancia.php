<?php
// Plantilla para Constancia de Liberación
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
    .performance {
        margin: 20px 0;
        padding: 15px;
        background-color: #f8f9fa;
        border-left: 4px solid #1db954;
    }
</style>

<div class="document-number">
    Número: <?= $numeroConstancia ?><br>
    Fecha: <?= $currentDate ?>
</div>

<div class="header">
    <h1><?= $institutionName ?></h1>
    <h2><?= $departmentName ?></h2>
</div>

<div class="content">
    <p><strong>CONSTANCIA DE LIBERACIÓN DE SERVICIO SOCIAL</strong></p>
    
    <div class="student-info">
        <p>El que suscribe, <strong><?= htmlspecialchars($solicitud['jefe_nombre']) ?></strong>, 
        <?= htmlspecialchars($solicitud['departamento']) ?> del <?= $institutionName ?>,</p>
        
        <p>HACE CONSTAR que el(la) C. <strong><?= htmlspecialchars($solicitud['nombre'] . ' ' . $solicitud['apellido_paterno'] . ' ' . $solicitud['apellido_materno']) ?></strong>, 
        con número de control <strong><?= htmlspecialchars($solicitud['numero_control']) ?></strong>, 
        estudiante de la carrera de <strong><?= htmlspecialchars($solicitud['carrera']) ?></strong>, 
        ha cubierto satisfactoriamente el Servicio Social reglamentario.</p>
    </div>
    
    <div class="performance">
        <p><strong>Periodo de servicio:</strong> <?= $solicitud['periodo_completo'] ?></p>
        <p><strong>Total de horas cumplidas:</strong> <?= $solicitud['horas_cumplidas'] ?> horas</p>
        <p><strong>Calificación final:</strong> <?= $solicitud['calificacion_final'] ?></p>
        <p><strong>Nivel de desempeño:</strong> <?= htmlspecialchars($solicitud['nivel_desempeno']) ?></p>
        <p><strong>Laboratorio:</strong> <?= htmlspecialchars($solicitud['laboratorio']) ?></p>
        <p><strong>Proyecto:</strong> <?= htmlspecialchars($solicitud['nombre_proyecto']) ?></p>
    </div>
    
    <p>Durante el desarrollo de sus actividades, el(la) estudiante demostró commitment, responsabilidad 
    y aptitudes sobresalientes en las funciones encomendadas, contribuyendo al cumplimiento de los 
    objetivos del proyecto asignado.</p>
    
    <p>Se extiende la presente constancia para los fines que al interesado(a) convengan, particularmente 
    para los trámites de liberación de servicio social ante la Dirección de Servicios Escolares.</p>
</div>

<div class="signature">
    <p>ATENTAMENTE</p>
    <p>"TECNOLOGÍA PROPIA E INDEPENDENCIA ECONÓMICA"</p>
    
    <div class="signature-line">
        <strong><?= htmlspecialchars($solicitud['jefe_nombre']) ?></strong><br>
        <?= htmlspecialchars($solicitud['departamento']) ?><br>
        <?= $institutionName ?>
    </div>
    
    <div style="margin-top: 30px;">
        <p><strong>Vo.Bo.</strong></p>
        <div class="signature-line">
            <strong>Dirección de Servicios Escolares</strong><br>
            <?= $institutionName ?>
        </div>
    </div>
</div>