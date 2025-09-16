<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeLabId = $usuario['id'];

// Procesar evaluación de reporte
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reporteId = $_POST['reporte_id'] ?? 0;
    $calificacion = $_POST['calificacion'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';
    $estado = $_POST['estado'] ?? '';
    
    if ($reporteId && in_array($estado, ['aprobado', 'rechazado', 'revision'])) {
        try {
            $db->update('reportes_bimestrales', [
                'calificacion' => $calificacion,
                'observaciones_evaluador' => $observaciones,
                'estado' => $estado,
                'evaluado_por' => $jefeLabId,
                'fecha_evaluacion' => date('Y-m-d H:i:s')
            ], 'id = :id AND jefe_laboratorio_id = :jefe_id', [
                'id' => $reporteId,
                'jefe_id' => $jefeLabId
            ]);
            
            // Si es aprobado, actualizar horas del estudiante
            if ($estado === 'aprobado') {
                $reporte = $db->fetch("SELECT estudiante_id, horas_reportadas FROM reportes_bimestrales WHERE id = ?", [$reporteId]);
                if ($reporte) {
                    $db->query("
                        UPDATE estudiantes 
                        SET horas_completadas = horas_completadas + ? 
                        WHERE id = ?
                    ", [$reporte['horas_reportadas'], $reporte['estudiante_id']]);
                }
            }
            
            flashMessage('Reporte evaluado correctamente', 'success');
        } catch (Exception $e) {
            flashMessage('Error al evaluar el reporte: ' . $e->getMessage(), 'error');
        }
    }
    
    redirectTo('/modules/laboratorio/evaluaciones.php');
}

// Obtener reportes pendientes de evaluación
$estado = $_GET['estado'] ?? 'pendientes';
$whereConditions = ["r.jefe_laboratorio_id = :jefe_id"];
$params = ['jefe_id' => $jefeLabId];

if ($estado === 'pendientes') {
    $whereConditions[] = "r.estado = 'pendiente_evaluacion'";
} elseif ($estado === 'evaluados') {
    $whereConditions[] = "r.estado IN ('aprobado', 'rechazado')";
} elseif ($estado === 'todos') {
    // No additional condition
}

$whereClause = implode(' AND ', $whereConditions);

$reportes = $db->fetchAll("
    SELECT r.*, e.nombre as estudiante_nombre, e.numero_control,
           p.nombre_proyecto, s.fecha_inicio_propuesta
    FROM reportes_bimestrales r
    JOIN estudiantes e ON r.estudiante_id = e.id
    JOIN solicitudes_servicio s ON r.solicitud_id = s.id
    JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
    WHERE $whereClause
    ORDER BY r.fecha_entrega DESC
", $params);

$pageTitle = "Evaluación de Reportes - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Evaluación de Reportes Bimestrales</h1>
        <p>Revisión y calificación de reportes de servicio social</p>
    </div>

    <!-- Filtros -->
    <div class="filters">
        <div class="filter-tabs">
            <a href="?estado=pendientes" class="filter-tab <?= $estado === 'pendientes' ? 'active' : '' ?>">
                Pendientes (<?= count(array_filter($reportes, fn($r) => $r['estado'] === 'pendiente_evaluacion')) ?>)
            </a>
            <a href="?estado=evaluados" class="filter-tab <?= $estado === 'evaluados' ? 'active' : '' ?>">
                Evaluados (<?= count(array_filter($reportes, fn($r) => in_array($r['estado'], ['aprobado', 'rechazado']))) ?>)
            </a>
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                Todos
            </a>
        </div>
    </div>

    <?php if ($reportes): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>No. Control</th>
                        <th>Reporte</th>
                        <th>Periodo</th>
                        <th>Horas Reportadas</th>
                        <th>Fecha Entrega</th>
                        <th>Estado</th>
                        <th>Calificación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $reporte): ?>
                    <tr>
                        <td><?= htmlspecialchars($reporte['estudiante_nombre']) ?></td>
                        <td><?= htmlspecialchars($reporte['numero_control']) ?></td>
                        <td>Reporte <?= $reporte['numero_reporte'] ?></td>
                        <td><?= formatDate($reporte['periodo_inicio']) ?> - <?= formatDate($reporte['periodo_fin']) ?></td>
                        <td><?= $reporte['horas_reportadas'] ?></td>
                        <td><?= formatDate($reporte['fecha_entrega']) ?></td>
                        <td>
                            <span class="badge <?= getEstadoBadgeClass($reporte['estado']) ?>">
                                <?= getEstadoText($reporte['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($reporte['calificacion']): ?>
                                <span class="calificacion"><?= $reporte['calificacion'] ?></span>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="#evaluar-<?= $reporte['id'] ?>" class="btn btn-sm btn-success" 
                                   onclick="openEvaluationModal(<?= $reporte['id'] ?>)">
                                    <i class="fas fa-check"></i> Evaluar
                                </a>
                                <a href="/modules/laboratorio/reporte-detalle.php?id=<?= $reporte['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>No hay reportes para evaluar</p>
            <p>Los estudiantes deben subir sus reportes bimestrales para que aparezcan aquí</p>
        </div>
    <?php endif; ?>

    <!-- Modal de evaluación -->
    <div id="evaluationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Evaluar Reporte</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="evaluationForm" method="POST">
                    <input type="hidden" name="reporte_id" id="reporte_id">
                    
                    <div class="form-group">
                        <label for="calificacion">Calificación (0-10)</label>
                        <input type="number" id="calificacion" name="calificacion" min="0" max="10" step="0.1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" required>
                            <option value="aprobado">Aprobado</option>
                            <option value="rechazado">Rechazado</option>
                            <option value="revision">Requiere Revisión</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" rows="4" placeholder="Comentarios sobre el reporte..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Guardar Evaluación</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: var(--radius);
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.close {
    font-size: 1.5rem;
    cursor: pointer;
}

.modal-body {
    padding: 1.5rem;
}

.calificacion {
    font-weight: bold;
    color: var(--success-color);
}
</style>

<script>
function openEvaluationModal(reporteId) {
    document.getElementById('reporte_id').value = reporteId;
    document.getElementById('evaluationModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('evaluationModal').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('evaluationModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Cerrar con la X
document.querySelector('.close').onclick = closeModal;
</script>

<?php include '../../includes/footer.php'; ?>