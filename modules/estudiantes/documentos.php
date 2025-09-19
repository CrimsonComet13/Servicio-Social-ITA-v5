<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('estudiante');

$db = Database::getInstance();
$usuario = $session->getUser();
$estudianteId = $usuario['id'];

// Obtener documentos del estudiante
$documentos = [];

// Oficios de presentación
$oficios = $db->fetchAll("
    SELECT op.*, s.fecha_inicio_propuesta, s.fecha_fin_propuesta
    FROM oficios_presentacion op
    JOIN solicitudes_servicio s ON op.solicitud_id = s.id
    WHERE s.estudiante_id = :estudiante_id
    ORDER BY op.fecha_emision DESC
", ['estudiante_id' => $estudianteId]);

foreach ($oficios as $oficio) {
    $documentos[] = [
        'tipo' => 'Oficio de Presentación',
        'numero' => $oficio['numero_oficio'],
        'fecha' => $oficio['fecha_emision'],
        'archivo' => $oficio['archivo_path'],
        'estado' => $oficio['estado'],
        'icono' => 'file-contract',
        'color' => 'primary',
        'descripcion' => 'Documento oficial para iniciar tu servicio social'
    ];
}

// Cartas de terminación
$cartas = $db->fetchAll("
    SELECT ct.*
    FROM cartas_terminacion ct
    WHERE ct.estudiante_id = :estudiante_id
    ORDER BY ct.fecha_terminacion DESC
", ['estudiante_id' => $estudianteId]);

foreach ($cartas as $carta) {
    $documentos[] = [
        'tipo' => 'Carta de Terminación',
        'numero' => $carta['numero_carta'],
        'fecha' => $carta['fecha_terminacion'],
        'archivo' => $carta['archivo_path'],
        'estado' => 'generado',
        'icono' => 'file-signature',
        'color' => 'success',
        'descripcion' => 'Certifica la conclusión de tu servicio social'
    ];
}

// Constancias
$constancias = $db->fetchAll("
    SELECT c.*
    FROM constancias c
    WHERE c.estudiante_id = :estudiante_id
    ORDER BY c.fecha_emision DESC
", ['estudiante_id' => $estudianteId]);

foreach ($constancias as $constancia) {
    $documentos[] = [
        'tipo' => 'Constancia de Liberación',
        'numero' => $constancia['numero_constancia'],
        'fecha' => $constancia['fecha_emision'],
        'archivo' => $constancia['archivo_path'],
        'estado' => $constancia['enviado_servicios_escolares'] ? 'enviado' : 'generado',
        'icono' => 'file-certificate',
        'color' => 'info',
        'descripcion' => 'Documento oficial de liberación del servicio social'
    ];
}

$pageTitle = "Mis Documentos - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="documents-container">
    <!-- Header Section -->
    <div class="documents-header">
        <div class="header-content">
            <div class="header-icon">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="header-info">
                <h1 class="header-title">Mis Documentos</h1>
                <p class="header-subtitle">Gestión y descarga de documentos generados durante tu servicio social</p>
            </div>
        </div>
        <div class="header-actions">
            <a href="../../dashboard/estudiante.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Volver al Dashboard
            </a>
        </div>
    </div>

    <?php if ($documentos): ?>
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Documentos</h3>
                    <span class="stat-number"><?= count($documentos) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-file-contract"></i>
                </div>
                <div class="stat-content">
                    <h3>Oficios</h3>
                    <span class="stat-number"><?= count($oficios) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon info">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stat-content">
                    <h3>Cartas</h3>
                    <span class="stat-number"><?= count($cartas) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon warning">
                    <i class="fas fa-file-certificate"></i>
                </div>
                <div class="stat-content">
                    <h3>Constancias</h3>
                    <span class="stat-number"><?= count($constancias) ?></span>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="documents-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-file-download"></i>
                    Documentos Disponibles
                </h2>
                <div class="section-filter">
                    <select id="documentFilter" class="filter-select">
                        <option value="all">Todos los documentos</option>
                        <option value="Oficio de Presentación">Oficios de Presentación</option>
                        <option value="Carta de Terminación">Cartas de Terminación</option>
                        <option value="Constancia de Liberación">Constancias</option>
                    </select>
                </div>
            </div>

            <div class="documents-grid">
                <?php foreach ($documentos as $documento): ?>
                <div class="document-card" data-type="<?= htmlspecialchars($documento['tipo']) ?>">
                    <div class="document-header">
                        <div class="document-icon <?= $documento['color'] ?>">
                            <i class="fas fa-<?= $documento['icono'] ?>"></i>
                        </div>
                        <div class="document-status">
                            <span class="status-badge <?= $documento['estado'] === 'generado' ? 'available' : 'sent' ?>">
                                <?php if ($documento['estado'] === 'generado'): ?>
                                    <i class="fas fa-check-circle"></i>
                                    Disponible
                                <?php elseif ($documento['estado'] === 'enviado'): ?>
                                    <i class="fas fa-paper-plane"></i>
                                    Enviado
                                <?php else: ?>
                                    <i class="fas fa-clock"></i>
                                    <?= ucfirst($documento['estado']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="document-content">
                        <h3 class="document-title"><?= htmlspecialchars($documento['tipo']) ?></h3>
                        <p class="document-description"><?= htmlspecialchars($documento['descripcion']) ?></p>
                        
                        <div class="document-details">
                            <div class="detail-item">
                                <i class="fas fa-hashtag"></i>
                                <span class="detail-label">Número:</span>
                                <span class="detail-value"><?= htmlspecialchars($documento['numero']) ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <i class="fas fa-calendar"></i>
                                <span class="detail-label">Fecha:</span>
                                <span class="detail-value"><?= formatDate($documento['fecha']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="document-actions">
                        <?php if ($documento['archivo']): ?>
                            <a href="<?= UPLOAD_URL . $documento['archivo'] ?>" 
                               target="_blank" 
                               class="btn btn-info btn-sm">
                                <i class="fas fa-eye"></i>
                                Visualizar
                            </a>
                            <a href="<?= UPLOAD_URL . $documento['archivo'] ?>" 
                               download 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-download"></i>
                                Descargar
                            </a>
                        <?php else: ?>
                            <div class="unavailable-notice">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Archivo no disponible</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state-card">
            <div class="empty-state-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="empty-state-content">
                <h3>No tienes documentos generados</h3>
                <p>Los documentos se generarán automáticamente durante las diferentes etapas de tu servicio social.</p>
                <div class="empty-state-info">
                    <div class="info-item">
                        <i class="fas fa-file-contract"></i>
                        <span><strong>Oficio de Presentación:</strong> Se genera al aprobar tu solicitud</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-file-signature"></i>
                        <span><strong>Carta de Terminación:</strong> Se genera al completar tus horas</span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-file-certificate"></i>
                        <span><strong>Constancia:</strong> Se genera al finalizar todo el proceso</span>
                    </div>
                </div>
                <div class="empty-state-actions">
                    <a href="../estudiantes/solicitud.php" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Crear Solicitud
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
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

/* Documents Container */
.documents-container {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Documents Header */
.documents-header {
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
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
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

/* Statistics Overview */
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
    animation: slideIn 0.6s ease-out;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.stat-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-icon.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-content {
    flex: 1;
}

.stat-content h3 {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.25rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 1.75rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

/* Documents Section */
.documents-section {
    animation: slideIn 0.6s ease-out 0.2s both;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    margin: 0;
}

.section-filter {
    position: relative;
}

.filter-select {
    padding: 0.5rem 1rem;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    background: var(--bg-white);
    color: var(--text-primary);
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Documents Grid */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

/* Document Card */
.document-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: var(--transition);
    opacity: 1;
}

.document-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
}

.document-card.hidden {
    opacity: 0;
    transform: scale(0.95);
    pointer-events: none;
}

.document-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: var(--bg-light);
    border-bottom: 1px solid var(--border-light);
}

.document-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
}

.document-icon.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.document-icon.success {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.document-icon.info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.document-icon.warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.document-status {
    display: flex;
    align-items: center;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.75rem;
    border-radius: 2rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.available {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.status-badge.sent {
    background: rgba(59, 130, 246, 0.1);
    color: var(--info);
}

.document-content {
    padding: 1.5rem;
}

.document-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.document-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
    line-height: 1.5;
}

.document-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
}

.detail-item i {
    width: 14px;
    color: var(--text-secondary);
    flex-shrink: 0;
}

.detail-label {
    color: var(--text-secondary);
}

.detail-value {
    color: var(--text-primary);
    font-weight: 500;
}

.document-actions {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-light);
}

.unavailable-notice {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-style: italic;
}

/* Empty State */
.empty-state-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    padding: 3rem;
    text-align: center;
    animation: slideIn 0.6s ease-out;
}

.empty-state-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(129, 140, 248, 0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--primary);
    margin: 0 auto 1.5rem;
}

.empty-state-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-state-content p {
    color: var(--text-secondary);
    margin-bottom: 2rem;
    font-size: 1.1rem;
}

.empty-state-info {
    background: var(--bg-light);
    border-radius: var(--radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
    text-align: left;
}

.empty-state-info .info-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.empty-state-info .info-item:last-child {
    margin-bottom: 0;
}

.empty-state-info i {
    color: var(--primary);
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.empty-state-actions {
    display: flex;
    justify-content: center;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    border-radius: var(--radius);
    text-decoration: none;
    font-weight: 600;
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
    background: rgba(99, 102, 241, 0.05);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1024px) {
    .stats-overview {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }
    
    .documents-grid {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }
}

@media (max-width: 768px) {
    .documents-container {
        padding: 1rem;
    }
    
    .documents-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .stats-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .documents-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .section-filter {
        width: 100%;
    }
    
    .filter-select {
        width: 100%;
    }
    
    .document-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .header-content {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .header-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .header-title {
        font-size: 1.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .empty-state-card {
        padding: 2rem 1rem;
    }
    
    .empty-state-info {
        text-align: center;
    }
    
    .empty-state-info .info-item {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }
}

/* Loading states */
.btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none !important;
}

/* Focus improvements for accessibility */
.btn:focus-visible,
.filter-select:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Document filter functionality
    const filterSelect = document.getElementById('documentFilter');
    const documentCards = document.querySelectorAll('.document-card');
    
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const selectedType = this.value;
            
            documentCards.forEach(card => {
                const cardType = card.getAttribute('data-type');
                
                if (selectedType === 'all' || cardType === selectedType) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
            
            // Update grid animation
            setTimeout(() => {
                const visibleCards = document.querySelectorAll('.document-card:not(.hidden)');
                visibleCards.forEach((card, index) => {
                    card.style.animationDelay = `${0.1 * index}s`;
                    card.style.animation = 'slideIn 0.4s ease-out both';
                });
            }, 100);
        });
    }
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.document-card, .stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('hidden')) {
                this.style.transform = 'translateY(-4px)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Add loading states to buttons
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Solo agregar loading si no es un enlace externo o de descarga
            if (this.getAttribute('href') && 
                (this.getAttribute('href').startsWith('#') || 
                 this.hasAttribute('download') ||
                 this.getAttribute('target') === '_blank')) {
                return; // Permitir navegación/descarga normal
            }
            
            this.classList.add('loading');
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
            
            setTimeout(() => {
                this.classList.remove('loading');
                this.innerHTML = originalText;
            }, 2000);
        });
    });
    
    // Animate document cards with stagger effect
    const documentCards2 = document.querySelectorAll('.document-card');
    documentCards2.forEach((card, index) => {
        card.style.animationDelay = `${0.1 * index}s`;
        card.style.animation = 'slideIn 0.6s ease-out both';
    });
    
    // Add status badge animations
    const badges = document.querySelectorAll('.status-badge');
    badges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // Download tracking
    const downloadButtons = document.querySelectorAll('a[download]');
    downloadButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Optional: Track downloads
            console.log('Document downloaded:', this.href);
            
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'download-success';
            successMessage.innerHTML = '<i class="fas fa-check"></i> Descarga iniciada';
            successMessage.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: var(--success);
                color: white;
                padding: 1rem;
                border-radius: var(--radius);
                z-index: 1000;
                animation: slideIn 0.3s ease-out;
            `;
            
            document.body.appendChild(successMessage);
            
            setTimeout(() => {
                successMessage.remove();
            }, 3000);
        });
    });
    
    // Add intersection observer for animations
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                }
            });
        }, {
            threshold: 0.1
        });
        
        const animateElements = document.querySelectorAll('.stats-overview, .documents-section');
        animateElements.forEach(el => observer.observe(el));
    }
    
    // Counter animation for stats
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(numberElement => {
        const finalNumber = parseInt(numberElement.textContent);
        let currentNumber = 0;
        const increment = Math.max(1, Math.floor(finalNumber / 20));
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber = Math.min(currentNumber + increment, finalNumber);
                numberElement.textContent = currentNumber;
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber;
            }
        }
        
        // Start animation with delay
        setTimeout(() => {
            animateNumber();
        }, Math.random() * 500 + 200);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>