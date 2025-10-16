<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
if (!$session->isLoggedIn() || $session->getUserRole() !== 'estudiante') {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$db = Database::getInstance();
$estudiante_id = $session->getUser()['id'];
$pageTitle = "Entregar y Autoevaluar Reporte - " . APP_NAME;

// Función para obtener los reportes pendientes de autoevaluación
function getReportesPendientes($db, $estudiante_id) {
    // Se asume que el reporte bimestral/final ya existe y solo falta la autoevaluación
    $sql = "
        (SELECT 
            rb.id, 'bimestral' as tipo, CONCAT('Bimestre #', rb.id) as periodo_label, rb.estado_evaluacion_estudiante
        FROM reportes_bimestrales rb
        JOIN solicitudes_servicio ss ON rb.solicitud_id = ss.id
        WHERE ss.estudiante_id = :estudiante_id_bimestral AND rb.estado_evaluacion_estudiante = 'pendiente')
        UNION
        (SELECT 
            rf.id, 'final' as tipo, 'Reporte Final' as periodo_label, rf.estado_evaluacion_estudiante
        FROM reportes_finales rf
        JOIN solicitudes_servicio ss ON rf.solicitud_id = ss.id
        WHERE ss.estudiante_id = :estudiante_id_final AND rf.estado_evaluacion_estudiante = 'pendiente')
        ORDER BY tipo DESC, id ASC
    ";
    return $db->fetchAll($sql, [
        'estudiante_id_bimestral' => $estudiante_id,
        'estudiante_id_final' => $estudiante_id
    ]);
}

// Función para obtener los criterios de autoevaluación
function getCriteriosAutoevaluacion($db) {
    $sql = "SELECT id, descripcion FROM criterios_evaluacion WHERE tipo_evaluacion = 'estudiante' AND activo = 1 ORDER BY id ASC";
    return $db->fetchAll($sql);
}

$reportes_pendientes = getReportesPendientes($db, $estudiante_id);
$criterios = getCriteriosAutoevaluacion($db);

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<!-- ⭐ ESTRUCTURA ACTUALIZADA CON DISEÑO COHERENTE -->
<div class="dashboard-container">
    
    <!-- Page Header Actualizado -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1 class="welcome-title">
                <span class="welcome-text">Entregar y Autoevaluar Reporte</span>
                <span class="welcome-emoji">📝</span>
            </h1>
            <p class="welcome-subtitle">Completa tu reporte periódico y realiza tu autoevaluación</p>
        </div>
        <div class="date-section">
            <div class="current-date">
                <i class="fas fa-calendar-alt"></i>
                <span><?= formatDate(date('Y-m-d')) ?></span>
            </div>
            <div class="current-time">
                <i class="fas fa-clock"></i>
                <span id="currentTime"><?= date('H:i') ?></span>
            </div>
        </div>
    </div>

    <!-- Alert Message -->
    <div id="alert-message" class="alert-message d-none" role="alert"></div>

    <?php if (empty($reportes_pendientes)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="empty-content">
                <h3>¡No tienes reportes pendientes!</h3>
                <p>No hay reportes que requieran autoevaluación en este momento. Todos tus reportes están actualizados.</p>
                <div class="empty-actions">
                    <a href="reportes.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        Ver Mis Reportes
                    </a>
                    <a href="horas.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i>
                        Ver Mi Progreso
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>

    <!-- Main Content -->
    <div class="content-section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-file-upload"></i>
                Formulario de Entrega
            </h2>
            <div class="section-subtitle">
                Completa la información de tu reporte y realiza tu autoevaluación
            </div>
        </div>

        <form id="reporte-form" class="form-modern">
            <!-- Selección de Reporte -->
            <div class="form-group-modern">
                <label for="reporte_seleccionado" class="form-label-modern">
                    <i class="fas fa-file-alt"></i>
                    Seleccionar Reporte Pendiente
                </label>
                <select class="form-select-modern" id="reporte_seleccionado" name="reporte_seleccionado" required>
                    <option value="">-- Seleccione un Reporte --</option>
                    <?php foreach ($reportes_pendientes as $reporte): ?>
                        <option value="<?= $reporte['id'] ?>|<?= $reporte['tipo'] ?>">
                            <?= ucfirst($reporte['tipo']) ?> (<?= $reporte['periodo_label'] ?>) - ID: <?= $reporte['id'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-help">Selecciona el reporte que deseas entregar y autoevaluar</div>
            </div>

            <!-- Actividades Realizadas -->
            <div class="form-group-modern">
                <label for="actividades_realizadas" class="form-label-modern">
                    <i class="fas fa-tasks"></i>
                    Actividades Realizadas en el Periodo
                </label>
                <textarea class="form-control-modern" id="actividades_realizadas" name="actividades_realizadas" rows="6" required placeholder="Detalle las actividades realizadas, el tiempo dedicado y los logros obtenidos..."></textarea>
                <div class="form-help">Este campo se almacenará en el reporte bimestral/final como evidencia de tus actividades</div>
            </div>

            <!-- Sección de Autoevaluación -->
            <div class="evaluation-section">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="fas fa-star-half-alt"></i>
                        Autoevaluación del Estudiante
                    </h3>
                    <div class="rating-guide">
                        <span class="rating-scale">Escala: 0 (Insuficiente) - 4 (Excelente)</span>
                    </div>
                </div>

                <input type="hidden" name="es_responsable" value="0">

                <!-- Criterios de Evaluación -->
                <div class="criteria-grid">
                    <?php $i = 1; foreach ($criterios as $criterio): ?>
                    <div class="criterion-card">
                        <div class="criterion-header">
                            <span class="criterion-number"><?= $i++ ?></span>
                            <h4 class="criterion-title"><?= htmlspecialchars($criterio['descripcion']) ?></h4>
                        </div>
                        <div class="criterion-rating">
                            <select class="rating-select" name="calificaciones[<?= $criterio['id'] ?>]" required>
                                <option value="">Seleccionar calificación</option>
                                <option value="4">4 - Excelente</option>
                                <option value="3">3 - Notable</option>
                                <option value="2">2 - Bueno</option>
                                <option value="1">1 - Suficiente</option>
                                <option value="0">0 - Insuficiente</option>
                            </select>
                            <div class="rating-labels">
                                <span>Insuficiente</span>
                                <span>Excelente</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Observaciones -->
                <div class="form-group-modern">
                    <label for="observaciones_estudiante" class="form-label-modern">
                        <i class="fas fa-comment-dots"></i>
                        Observaciones Generales
                    </label>
                    <textarea class="form-control-modern" id="observaciones_estudiante" name="observaciones_estudiante" rows="3" placeholder="Comentarios adicionales sobre tu desempeño y la experiencia en el periodo..."></textarea>
                    <div class="form-help">Comparte tus reflexiones, aprendizajes y sugerencias de mejora</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="form-actions">
                <button type="submit" class="btn btn-success btn-lg" id="submit-btn">
                    <i class="fas fa-check-circle"></i>
                    Entregar Reporte y Autoevaluación
                </button>
                <a href="reportes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Reportes
                </a>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

<!-- ⭐ CSS ACTUALIZADO COHERENTE CON EL SISTEMA -->
<style>
/* Reutilizar variables y estilos base del dashboard */
.dashboard-container {
    padding: 1.5rem;
    max-width: none;
    margin: 0;
    width: 100%;
}

/* Header Section */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.welcome-section .welcome-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.welcome-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.date-section {
    display: flex;
    gap: 1rem;
}

.current-date, .current-time {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* Alert Message */
.alert-message {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    border: 1px solid transparent;
}

.alert-message.success {
    background: rgba(16, 185, 129, 0.1);
    border-color: rgba(16, 185, 129, 0.2);
    color: var(--success);
}

.alert-message.error {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
    color: var(--error);
}

.alert-message.info {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.2);
    color: var(--info);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
}

.empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    background: linear-gradient(135deg, var(--success), #34d399);
    margin: 0 auto 1.5rem;
}

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    font-size: 1rem;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* Content Section */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.section-header {
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.section-subtitle {
    color: var(--text-secondary);
    font-size: 0.95rem;
}

.rating-guide {
    margin-top: 0.5rem;
}

.rating-scale {
    font-size: 0.85rem;
    color: var(--text-light);
    font-style: italic;
}

/* Modern Form Styles */
.form-modern {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.form-group-modern {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-label-modern {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 1rem;
}

.form-select-modern,
.form-control-modern {
    padding: 0.75rem 1rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.form-select-modern:focus,
.form-control-modern:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-control-modern {
    resize: vertical;
    min-height: 120px;
}

.form-help {
    font-size: 0.85rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

/* Evaluation Section */
.evaluation-section {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-top: 1rem;
}

/* Criteria Grid */
.criteria-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin: 2rem 0;
}

.criterion-card {
    background: var(--bg-white);
    border-radius: var(--radius);
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    border-left: 4px solid var(--primary);
    transition: var(--transition);
}

.criterion-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.criterion-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
}

.criterion-number {
    width: 32px;
    height: 32px;
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 600;
    flex-shrink: 0;
}

.criterion-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.4;
}

.criterion-rating {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.rating-select {
    padding: 0.75rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 0.95rem;
    background: var(--bg-white);
    transition: var(--transition);
    width: 100%;
}

.rating-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.rating-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-light);
    margin-top: 0.25rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    align-items: center;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-light);
    flex-wrap: wrap;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
    font-weight: 600;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--bg-white);
    color: var(--text-primary);
    border: 1px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .date-section {
        width: 100%;
        justify-content: space-between;
    }
    
    .content-section {
        padding: 1.5rem;
    }
    
    .evaluation-section {
        padding: 1.5rem;
    }
    
    .criteria-grid {
        gap: 1rem;
    }
    
    .criterion-card {
        padding: 1rem;
    }
    
    .criterion-header {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 1rem;
    }
    
    .welcome-title {
        font-size: 1.5rem;
    }
    
    .empty-state {
        padding: 2rem 1rem;
    }
    
    .empty-actions {
        flex-direction: column;
    }
}

/* Loading States */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Animation for form elements */
.form-group-modern,
.criterion-card {
    animation: slideInUp 0.5s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update current time
    function updateTime() {
        const now = new Date();
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = now.toLocaleTimeString('es-MX', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
        }
    }
    
    updateTime();
    setInterval(updateTime, 60000);

    // Form submission
    const reporteForm = document.getElementById('reporte-form');
    if (reporteForm) {
        reporteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const alertMessage = document.getElementById('alert-message');
            const submitBtn = document.getElementById('submit-btn');

            // Deshabilitar botón
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';

            // Obtener id y tipo de reporte
            const [id_reporte, tipo_reporte] = formData.get('reporte_seleccionado').split('|');

            // Construir el objeto de calificaciones
            const calificaciones = {};
            form.querySelectorAll('.rating-select').forEach(select => {
                calificaciones[select.name.match(/\[(\d+)\]/)[1]] = select.value;
            });

            const data = {
                id_reporte: parseInt(id_reporte),
                tipo_reporte: tipo_reporte,
                es_responsable: formData.get('es_responsable') === '1',
                observaciones: formData.get('observaciones_estudiante'),
                calificaciones: calificaciones
            };

            // 1. Actualizar Actividades Realizadas (asumiendo que hay una función para esto en el backend)
            const actividades = formData.get('actividades_realizadas');
            const updateReportData = {
                actividades_realizadas: actividades,
                id: id_reporte,
                tipo: tipo_reporte
            };

            // 2. Guardar Evaluación
            fetch('<?= BASE_URL ?>api/evaluacion/guardar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alertMessage.className = 'alert-message success';
                    alertMessage.innerHTML = `
                        <strong>¡Éxito!</strong> 
                        Reporte ${tipo_reporte} (ID: ${id_reporte}) entregado y autoevaluación guardada con éxito. 
                        ${result.calificacion_final ? `Calificación final: ${result.calificacion_final}.` : ''}
                    `;
                    
                    // Recargar la lista de reportes pendientes
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);

                } else {
                    alertMessage.className = 'alert-message error';
                    alertMessage.innerHTML = `<strong>Error:</strong> ${result.message || 'Error desconocido al guardar la autoevaluación.'}`;
                }
            })
            .catch(error => {
                alertMessage.className = 'alert-message error';
                alertMessage.innerHTML = `<strong>Error de conexión:</strong> ${error.message}`;
            })
            .finally(() => {
                alertMessage.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Entregar Reporte y Autoevaluación';
                
                // Scroll to alert message
                alertMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        });
    }

    // Add hover effects to criterion cards
    const criterionCards = document.querySelectorAll('.criterion-card');
    criterionCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });

    console.log('✅ Página de entrega de reportes actualizada con diseño moderno');
});
</script>

<?php include '../../includes/footer.php'; ?>