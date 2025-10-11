<?php
require_once '../config/config.php';
require_once '../config/session.php';
require_once '../config/functions.php';

$session = SecureSession::getInstance();

// Verificar autenticación (opcional para la página de ayuda)
$isLoggedIn = $session->isLoggedIn();
$userRole = $isLoggedIn ? $session->getUserRole() : null;

// Inicializar variables sin usar base de datos
$usuario = null;
$estudiante = null;

if ($isLoggedIn) {
    // Crear usuario básico usando información de la sesión
    $usuario = [
        'nombre' => $session->getUser()['nombre'] ?? 'Usuario',
        'email' => $session->getUser()['email'] ?? 'usuario@ita.mx',
        'avatar' => null,
        'horas_completadas' => 0 // Valor por defecto
    ];
    
    // Para estudiantes, agregar información adicional
    if ($userRole === 'estudiante') {
        $estudiante = $usuario;
        $estudiante['horas_completadas'] = 0; // Se puede obtener de otra fuente después
    }
}

$pageTitle = "Centro de Ayuda - " . APP_NAME;
include '../includes/header.php';
if ($isLoggedIn) {
    include '../includes/sidebar.php';
}
?>

<div class="help-container">
    <!-- Header Section -->
    <div class="help-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Centro de Ayuda</h1>
                <p class="header-subtitle">Encuentra respuestas a tus preguntas sobre el servicio social</p>
            </div>
        </div>
        <?php if ($isLoggedIn): ?>
        <div class="header-actions">
            <a href="dashboard/<?= $userRole ?>.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Search -->
    <div class="search-section">
        <div class="search-container">
            <div class="search-input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="helpSearch" placeholder="Busca ayuda sobre cualquier tema..." class="search-input">
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="help-content">
        <!-- Navigation Tabs -->
        <div class="help-navigation">
            <button class="nav-tab active" data-target="getting-started">
                <i class="fas fa-rocket"></i>
                <span>Comenzar</span>
            </button>
            <button class="nav-tab" data-target="process-guide">
                <i class="fas fa-route"></i>
                <span>Proceso</span>
            </button>
            <button class="nav-tab" data-target="faq">
                <i class="fas fa-question-circle"></i>
                <span>Preguntas Frecuentes</span>
            </button>
            <button class="nav-tab" data-target="documents">
                <i class="fas fa-file-alt"></i>
                <span>Documentos</span>
            </button>
            <button class="nav-tab" data-target="contact">
                <i class="fas fa-phone"></i>
                <span>Contacto</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Getting Started -->
            <div id="getting-started" class="tab-panel active">
                <div class="content-header">
                    <h2>¿Cómo empezar con tu Servicio Social?</h2>
                    <p>Sigue estos pasos para iniciar correctamente tu proceso de servicio social en el ITA.</p>
                </div>

                <div class="steps-container">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h3>Registro en el Sistema</h3>
                            <p>Regístrate con tu número de control y email institucional. Asegúrate de tener al menos 70% de créditos aprobados.</p>
                            <div class="step-requirements">
                                <h4>Requisitos:</h4>
                                <ul>
                                    <li>Número de control válido</li>
                                    <li>Email institucional activo</li>
                                    <li>Mínimo 70% de créditos aprobados</li>
                                    <li>No tener adeudos académicos</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="step-card">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h3>Completar tu Perfil</h3>
                            <p>Actualiza toda tu información personal y académica en tu perfil de usuario.</p>
                            <div class="step-actions">
                                <?php if ($isLoggedIn && $userRole === 'estudiante'): ?>
                                <a href="modules/estudiantes/perfil.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-user-edit"></i>
                                    Editar Perfil
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="step-card">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h3>Crear Solicitud</h3>
                            <p>Selecciona un proyecto disponible y envía tu solicitud de servicio social.</p>
                            <div class="step-actions">
                                <?php if ($isLoggedIn && $userRole === 'estudiante'): ?>
                                <a href="modules/estudiantes/solicitud.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-paper-plane"></i>
                                    Nueva Solicitud
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="step-card">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h3>Seguimiento</h3>
                            <p>Monitorea el estado de tu solicitud y mantente al día con los reportes y documentos.</p>
                            <div class="step-actions">
                                <?php if ($isLoggedIn && $userRole === 'estudiante'): ?>
                                <a href="modules/estudiantes/solicitud-estado.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i>
                                    Ver Estado
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Process Guide -->
            <div id="process-guide" class="tab-panel">
                <div class="content-header">
                    <h2>Guía del Proceso Completo</h2>
                    <p>Comprende cada etapa del proceso de servicio social desde la solicitud hasta la conclusión.</p>
                </div>

                <div class="process-timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker solicitud">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Envío de Solicitud</h3>
                            <p>Selecciona un proyecto y envía tu solicitud. Esta será revisada por el jefe de departamento correspondiente.</p>
                            <div class="timeline-duration">Duración estimada: 1-3 días hábiles</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker revision">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Revisión y Aprobación</h3>
                            <p>Tu solicitud será evaluada considerando tu perfil académico y la disponibilidad del proyecto.</p>
                            <div class="timeline-duration">Duración estimada: 3-5 días hábiles</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker oficio">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Generación de Oficio</h3>
                            <p>Una vez aprobada, se genera automáticamente tu oficio de presentación para comenzar actividades.</p>
                            <div class="timeline-duration">Duración: Inmediato</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker desarrollo">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Desarrollo de Actividades</h3>
                            <p>Realiza tus 500 horas de servicio social bajo la supervisión del responsable del proyecto.</p>
                            <div class="timeline-duration">Duración: 4-6 meses</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker reportes">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Reportes Bimestrales</h3>
                            <p>Entrega reportes cada 2 meses detallando tus actividades, logros y horas acumuladas.</p>
                            <div class="timeline-duration">Cada 60 días</div>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-marker finalizacion">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="timeline-content">
                            <h3>Finalización</h3>
                            <p>Al completar las 500 horas, se genera tu carta de terminación y constancia final.</p>
                            <div class="timeline-duration">1-2 días hábiles</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ -->
            <div id="faq" class="tab-panel">
                <div class="content-header">
                    <h2>Preguntas Frecuentes</h2>
                    <p>Encuentra respuestas rápidas a las dudas más comunes sobre el servicio social.</p>
                </div>

                <div class="faq-container">
                    <div class="faq-category">
                        <h3>General</h3>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Cuántas horas debo completar para mi servicio social?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Debes completar un total de <strong>500 horas</strong> de servicio social. Estas horas deben distribuirse a lo largo de 4-6 meses, realizando aproximadamente 20-25 horas semanales.</p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Cuándo puedo comenzar mi servicio social?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Puedes iniciar tu servicio social cuando hayas cubierto al menos el <strong>70% de los créditos</strong> de tu carrera y no tengas adeudos académicos pendientes.</p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Qué pasa si no entrego un reporte a tiempo?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Los reportes bimestrales son obligatorios. Si no entregas un reporte en fecha, tu servicio social podría ser <strong>suspendido temporalmente</strong> hasta regularizar la situación. Contacta inmediatamente a tu supervisor.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-category">
                        <h3>Solicitudes</h3>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Puedo cambiar de proyecto después de ser aprobado?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Los cambios de proyecto son excepcionales y deben justificarse adecuadamente. Debes solicitar el cambio a través del sistema y esperar la aprobación del jefe de departamento.</p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Qué información necesito para crear mi solicitud?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Necesitas tener tu perfil completo con: datos personales actualizados, información académica, porcentaje de avance, y seleccionar un proyecto disponible que se ajuste a tu carrera.</p>
                            </div>
                        </div>
                    </div>

                    <div class="faq-category">
                        <h3>Técnico</h3>
                        
                        <div class="faq-item">
                            <button class="faq-question">
                                <span>No puedo acceder al sistema, ¿qué hago?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Verifica que estés usando tu número de control correctamente. Si olvidaste tu contraseña, usa la opción "Recuperar Contraseña". Si el problema persiste, contacta al soporte técnico.</p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <button class="faq-question">
                                <span>¿Cómo subo documentos al sistema?</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="faq-answer">
                                <p>Los documentos deben estar en formato PDF, con un tamaño máximo de 5MB. Usa la sección correspondiente en tu dashboard y asegúrate de que el archivo esté legible y completo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div id="documents" class="tab-panel">
                <div class="content-header">
                    <h2>Documentos y Formatos</h2>
                    <p>Descarga los formatos oficiales y conoce los documentos que necesitarás durante tu servicio social.</p>
                </div>

                <div class="documents-grid">
                    <div class="document-category">
                        <h3>
                            <i class="fas fa-file-contract"></i>
                            Formatos Oficiales
                        </h3>
                        <div class="document-list">
                            <div class="document-item">
                                <div class="doc-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="doc-info">
                                    <h4>Formato de Reporte Bimestral</h4>
                                    <p>Plantilla oficial para entregar tus reportes bimestrales</p>
                                </div>
                                <a href="assets/docs/formato-reporte-bimestral.pdf" class="btn btn-sm btn-primary" download>
                                    <i class="fas fa-download"></i>
                                    Descargar
                                </a>
                            </div>

                            <div class="document-item">
                                <div class="doc-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="doc-info">
                                    <h4>Carta de Aceptación</h4>
                                    <p>Formato para solicitudes en dependencias externas</p>
                                </div>
                                <a href="assets/docs/formato-carta-aceptacion.pdf" class="btn btn-sm btn-primary" download>
                                    <i class="fas fa-download"></i>
                                    Descargar
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="document-category">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Documentos Informativos
                        </h3>
                        <div class="document-list">
                            <div class="document-item">
                                <div class="doc-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="doc-info">
                                    <h4>Manual del Estudiante</h4>
                                    <p>Guía completa sobre el proceso de servicio social</p>
                                </div>
                                <a href="assets/docs/manual-estudiante.pdf" class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                            </div>

                            <div class="document-item">
                                <div class="doc-icon">
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <div class="doc-info">
                                    <h4>Reglamento de Servicio Social</h4>
                                    <p>Normativa oficial del ITA para el servicio social</p>
                                </div>
                                <a href="assets/docs/reglamento-servicio-social.pdf" class="btn btn-sm btn-secondary" target="_blank">
                                    <i class="fas fa-eye"></i>
                                    Ver
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="document-category">
                        <h3>
                            <i class="fas fa-clipboard-check"></i>
                            Requisitos
                        </h3>
                        <div class="requirements-list">
                            <div class="requirement-item">
                                <div class="req-icon success">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="req-content">
                                    <h4>Avance Académico</h4>
                                    <p>Mínimo 70% de créditos aprobados</p>
                                </div>
                            </div>

                            <div class="requirement-item">
                                <div class="req-icon warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="req-content">
                                    <h4>Sin Adeudos</h4>
                                    <p>No tener materias reprobadas pendientes</p>
                                </div>
                            </div>

                            <div class="requirement-item">
                                <div class="req-icon info">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="req-content">
                                    <h4>Disponibilidad</h4>
                                    <p>20-25 horas semanales disponibles</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div id="contact" class="tab-panel">
                <div class="content-header">
                    <h2>Información de Contacto</h2>
                    <p>¿Necesitas ayuda adicional? Contacta directamente con el personal responsable.</p>
                </div>

                <div class="contact-grid">
                    <div class="contact-card primary">
                        <div class="contact-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="contact-info">
                            <h3>Coordinación de Servicio Social</h3>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <span>servicio.social@ita.mx</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span>(449) 910-2020 Ext. 2150</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Edificio A, Oficina 101</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="contact-info">
                            <h3>Soporte Técnico</h3>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <span>soporte.sistemas@ita.mx</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span>(449) 910-2020 Ext. 2300</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-clock"></i>
                                    <span>Lun-Vie: 8:00 AM - 6:00 PM</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="contact-info">
                            <h3>Servicios Escolares</h3>
                            <div class="contact-details">
                                <div class="contact-detail">
                                    <i class="fas fa-envelope"></i>
                                    <span>escolares@ita.mx</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-phone"></i>
                                    <span>(449) 910-2020 Ext. 2100</span>
                                </div>
                                <div class="contact-detail">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Edificio Administrativo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="emergency-contact">
                    <div class="emergency-header">
                        <i class="fas fa-exclamation-circle"></i>
                        <h3>¿Tienes un problema urgente?</h3>
                    </div>
                    <p>Si tienes una situación urgente que requiere atención inmediata, comunícate directamente con la coordinación durante horario de oficina o envía un correo detallando tu situación.</p>
                    <div class="emergency-actions">
                        <a href="tel:4499102020" class="btn btn-error">
                            <i class="fas fa-phone"></i>
                            Llamar Ahora
                        </a>
                        <a href="mailto:servicio.social@ita.mx?subject=Situación Urgente" class="btn btn-secondary">
                            <i class="fas fa-envelope"></i>
                            Enviar Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --info: #3b82f6;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    --border: #e5e7eb;
    --border-light: #f3f4f6;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
}

/* Help Container */
.help-container {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Header Section */
.help-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--info), #60a5fa);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.header-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.header-subtitle {
    font-size: 1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Search Section */
.search-section {
    margin-bottom: 2rem;
}

.search-container {
    max-width: 600px;
    margin: 0 auto;
}

.search-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.search-input-wrapper i {
    position: absolute;
    left: 1rem;
    color: var(--text-light);
    font-size: 1rem;
    z-index: 2;
}

.search-input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-lg);
    font-size: 1rem;
    background: var(--bg-white);
    transition: var(--transition);
}

.search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Help Content */
.help-content {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
}

/* Navigation Tabs */
.help-navigation {
    display: flex;
    background: var(--bg-light);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}

.nav-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    white-space: nowrap;
    border-bottom: 3px solid transparent;
}

.nav-tab:hover {
    background: var(--bg-white);
    color: var(--text-primary);
}

.nav-tab.active {
    background: var(--bg-white);
    color: var(--primary);
    border-bottom-color: var(--primary);
}

/* Tab Content */
.tab-content {
    min-height: 600px;
}

.tab-panel {
    display: none;
    padding: 2rem;
    animation: fadeIn 0.3s ease-in-out;
}

.tab-panel.active {
    display: block;
}

.content-header {
    margin-bottom: 2rem;
    text-align: center;
}

.content-header h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.content-header p {
    color: var(--text-secondary);
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto;
}

/* Steps Container */
.steps-container {
    display: grid;
    gap: 1.5rem;
    max-width: 800px;
    margin: 0 auto;
}

.step-card {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    border-left: 4px solid var(--primary);
    transition: var(--transition);
}

.step-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.step-number {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 700;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.step-content p {
    color: var(--text-secondary);
    margin-bottom: 1rem;
    line-height: 1.6;
}

.step-requirements {
    background: var(--bg-white);
    padding: 1rem;
    border-radius: var(--radius);
    margin-top: 1rem;
}

.step-requirements h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.step-requirements ul {
    margin: 0;
    padding-left: 1.25rem;
}

.step-requirements li {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
}

.step-actions {
    margin-top: 1rem;
}

/* Process Timeline */
.process-timeline {
    max-width: 800px;
    margin: 0 auto;
}

.timeline-item {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
}

.timeline-item::after {
    content: '';
    position: absolute;
    left: 25px;
    top: 60px;
    width: 2px;
    height: calc(100% - 20px);
    background: var(--border);
}

.timeline-item:last-child::after {
    display: none;
}

.timeline-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}

.timeline-marker.solicitud {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.timeline-marker.revision {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.timeline-marker.oficio {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.timeline-marker.desarrollo {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.timeline-marker.reportes {
    background: linear-gradient(135deg, #8b5cf6, #a78bfa);
}

.timeline-marker.finalizacion {
    background: linear-gradient(135deg, #f59e0b, #fbbf24);
}

.timeline-content {
    flex: 1;
    padding: 0.5rem 0;
}

.timeline-content h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.timeline-content p {
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.timeline-duration {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: var(--bg-light);
    border-radius: 2rem;
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* FAQ Container */
.faq-container {
    max-width: 800px;
    margin: 0 auto;
}

.faq-category {
    margin-bottom: 2rem;
}

.faq-category h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary);
}

.faq-item {
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 0.5rem;
    overflow: hidden;
}

.faq-question {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: var(--bg-white);
    border: none;
    text-align: left;
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary);
    cursor: pointer;
    transition: var(--transition);
}

.faq-question:hover {
    background: var(--bg-light);
}

.faq-question i {
    transition: transform 0.3s ease;
}

.faq-item.active .faq-question i {
    transform: rotate(180deg);
}

.faq-answer {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: all 0.3s ease;
    background: var(--bg-light);
}

.faq-item.active .faq-answer {
    max-height: 200px;
    padding: 1rem 1.5rem;
}

.faq-answer p {
    margin: 0;
    color: var(--text-secondary);
    line-height: 1.6;
}

/* Documents Grid */
.documents-grid {
    display: grid;
    gap: 2rem;
    max-width: 1000px;
    margin: 0 auto;
}

.document-category {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
}

.document-category h3 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.document-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.document-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    transition: var(--transition);
}

.document-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.doc-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--error), #f87171);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.doc-info {
    flex: 1;
}

.doc-info h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.doc-info p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Requirements List */
.requirements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.requirement-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-white);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
}

.req-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.req-icon.success {
    background: var(--success);
}

.req-icon.warning {
    background: var(--warning);
}

.req-icon.info {
    background: var(--info);
}

.req-content h4 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.req-content p {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

/* Contact Grid */
.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.contact-card {
    display: flex;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    border-left: 4px solid var(--info);
    transition: var(--transition);
}

.contact-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.contact-card.primary {
    border-left-color: var(--primary);
}

.contact-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--info), #60a5fa);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.contact-card.primary .contact-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.contact-info {
    flex: 1;
}

.contact-info h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.contact-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.contact-detail {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.contact-detail i {
    width: 16px;
    color: var(--text-light);
}

/* Emergency Contact */
.emergency-contact {
    padding: 1.5rem;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(248, 113, 113, 0.05));
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: var(--radius-lg);
    text-align: center;
}

.emergency-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.emergency-header i {
    color: var(--error);
    font-size: 1.5rem;
}

.emergency-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.emergency-contact p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.emergency-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
}

.btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
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

.btn-error {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-error:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .help-navigation {
        flex-wrap: wrap;
    }
    
    .nav-tab {
        flex: 1;
        min-width: 120px;
    }
}

@media (max-width: 768px) {
    .help-container {
        padding: 1rem;
    }
    
    .help-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .tab-panel {
        padding: 1.5rem;
    }
    
    .steps-container {
        gap: 1rem;
    }
    
    .step-card {
        flex-direction: column;
        text-align: center;
    }
    
    .timeline-item {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .timeline-item::after {
        display: none;
    }
    
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .emergency-actions {
        flex-direction: column;
    }
    
    .document-item {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
}

@media (max-width: 480px) {
    .help-navigation {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .nav-tab {
        flex: none;
        min-width: 140px;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .search-input {
        font-size: 0.9rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab Navigation
    const navTabs = document.querySelectorAll('.nav-tab');
    const tabPanels = document.querySelectorAll('.tab-panel');
    
    navTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            
            // Remove active class from all tabs and panels
            navTabs.forEach(t => t.classList.remove('active'));
            tabPanels.forEach(p => p.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding panel
            this.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });
    
    // FAQ Accordion
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const faqItem = this.parentElement;
            const wasActive = faqItem.classList.contains('active');
            
            // Close all FAQ items
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Open clicked item if it wasn't active
            if (!wasActive) {
                faqItem.classList.add('active');
            }
        });
    });
    
    // Search Functionality
    const searchInput = document.getElementById('helpSearch');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const allContent = document.querySelectorAll('.step-card, .faq-item, .document-item, .timeline-item');
        
        if (searchTerm === '') {
            // Show all content when search is empty
            allContent.forEach(item => {
                item.style.display = '';
            });
            return;
        }
        
        allContent.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                item.style.display = '';
                item.style.background = 'rgba(99, 102, 241, 0.05)';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Clear search highlight when input is cleared
    searchInput.addEventListener('blur', function() {
        if (this.value === '') {
            const allContent = document.querySelectorAll('.step-card, .faq-item, .document-item, .timeline-item');
            allContent.forEach(item => {
                item.style.background = '';
            });
        }
    });
    
    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add hover effects to interactive elements
    const interactiveElements = document.querySelectorAll('.step-card, .timeline-item, .document-item, .contact-card');
    
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        element.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Copy contact info functionality
    document.querySelectorAll('.contact-detail').forEach(detail => {
        const text = detail.querySelector('span');
        if (text && (text.textContent.includes('@') || text.textContent.includes('('))) {
            detail.addEventListener('click', function() {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text.textContent).then(() => {
                        // Show temporary success feedback
                        const originalText = text.textContent;
                        text.textContent = 'Copiado!';
                        text.style.color = 'var(--success)';
                        
                        setTimeout(() => {
                            text.textContent = originalText;
                            text.style.color = '';
                        }, 1500);
                    });
                }
            });
            
            detail.style.cursor = 'pointer';
            detail.title = 'Click para copiar';
        }
    });
    
    // Track help usage (analytics)
    function trackHelpUsage(section, action) {
        // This could be implemented to track which sections are most used
        console.log(`Help: ${section} - ${action}`);
    }
    
    // Track tab changes
    navTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            trackHelpUsage(this.getAttribute('data-target'), 'tab_opened');
        });
    });
    
    // Track FAQ interactions
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            trackHelpUsage('faq', 'question_expanded');
        });
    });
    
    // Track document downloads
    document.querySelectorAll('a[download]').forEach(link => {
        link.addEventListener('click', function() {
            trackHelpUsage('documents', 'download');
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
