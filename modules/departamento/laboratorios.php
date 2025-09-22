<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$usuario = $session->getUser();
$jefeId = $usuario['id'];

// Procesar acciones
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    switch ($action) {
        case 'approve':
            // Aprobar jefe de laboratorio
            $db->update('jefes_laboratorio', [
                'activo' => true
            ], 'id = :id AND jefe_departamento_id = :jefe_id', [
                'id' => $id,
                'jefe_id' => $jefeId
            ]);
            
            // Activar usuario
            $jefeLab = $db->fetch("SELECT usuario_id FROM jefes_laboratorio WHERE id = ?", [$id]);
            if ($jefeLab) {
                $db->update('usuarios', [
                    'activo' => true,
                    'email_verificado' => true
                ], 'id = :id', ['id' => $jefeLab['usuario_id']]);
            }
            
            flashMessage('Jefe de laboratorio aprobado correctamente', 'success');
            break;
            
        case 'reject':
            // Rechazar jefe de laboratorio
            $db->update('jefes_laboratorio', [
                'activo' => false
            ], 'id = :id AND jefe_departamento_id = :jefe_id', [
                'id' => $id,
                'jefe_id' => $jefeId
            ]);
            flashMessage('Jefe de laboratorio rechazado', 'success');
            break;
            
        case 'delete':
            // Eliminar jefe de laboratorio
            $db->delete('jefes_laboratorio', 
                'id = :id AND jefe_departamento_id = :jefe_id', 
                ['id' => $id, 'jefe_id' => $jefeId]
            );
            flashMessage('Jefe de laboratorio eliminado', 'success');
            break;
    }
    
    redirectTo('/modules/departamento/laboratorios.php');
}

// Obtener jefes de laboratorio
$estado = $_GET['estado'] ?? 'activos';
$whereConditions = ["jl.jefe_departamento_id = :jefe_id"];
$params = ['jefe_id' => $jefeId];

if ($estado === 'pendientes') {
    $whereConditions[] = "jl.activo = FALSE";
} elseif ($estado === 'activos') {
    $whereConditions[] = "jl.activo = TRUE";
} elseif ($estado === 'inactivos') {
    $whereConditions[] = "jl.activo = FALSE";
}

$whereClause = implode(' AND ', $whereConditions);

$jefesLaboratorio = $db->fetchAll("
    SELECT jl.*, u.email, u.activo as usuario_activo
    FROM jefes_laboratorio jl
    JOIN usuarios u ON jl.usuario_id = u.id
    WHERE $whereClause
    ORDER BY jl.nombre
", $params);

// Calcular estadísticas
$totalJefes = count($jefesLaboratorio);
$jefesActivos = count(array_filter($jefesLaboratorio, fn($j) => $j['activo']));
$jefesPendientes = count(array_filter($jefesLaboratorio, fn($j) => !$j['activo']));

$pageTitle = "Gestión de Jefes de Laboratorio - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">
                    <i class="fas fa-flask"></i>
                    Gestión de Jefes de Laboratorio
                </h1>
                <p class="page-subtitle">Administración y supervisión de jefes de laboratorio del departamento</p>
            </div>
            <div class="header-actions">
                <a href="../../dashboard/jefe_departamento.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Dashboard
                </a>
                <a href="../../../auth/register-jefe.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Invitar Jefe
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="statistics-overview">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Total Jefes</h3>
                <div class="stat-number"><?= $totalJefes ?></div>
                <p class="stat-description">Jefes registrados</p>
                <div class="stat-trend">
                    <i class="fas fa-user-plus"></i>
                    <span>Gestionar equipo</span>
                </div>
            </div>
        </div>

        <div class="stat-card activos">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Activos</h3>
                <div class="stat-number"><?= $jefesActivos ?></div>
                <p class="stat-description">Jefes aprobados</p>
                <div class="stat-trend">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $totalJefes > 0 ? round(($jefesActivos / $totalJefes) * 100) : 0 ?>% del total</span>
                </div>
            </div>
        </div>

        <div class="stat-card pendientes">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Pendientes</h3>
                <div class="stat-number"><?= $jefesPendientes ?></div>
                <p class="stat-description">Esperando aprobación</p>
                <?php if ($jefesPendientes > 0): ?>
                <div class="stat-alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Requiere atención</span>
                </div>
                <?php else: ?>
                <div class="stat-trend">
                    <i class="fas fa-check"></i>
                    <span>Al día</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card laboratorios">
            <div class="stat-icon">
                <i class="fas fa-vials"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Laboratorios</h3>
                <div class="stat-number"><?= count(array_unique(array_column($jefesLaboratorio, 'laboratorio'))) ?></div>
                <p class="stat-description">Laboratorios únicos</p>
                <div class="stat-trend">
                    <i class="fas fa-flask"></i>
                    <span>Especializaciones</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <div class="filters-header">
            <h2 class="filters-title">
                <i class="fas fa-filter"></i>
                Filtrar Jefes de Laboratorio
            </h2>
        </div>
        
        <div class="filter-tabs">
            <a href="?estado=activos" class="filter-tab <?= $estado === 'activos' ? 'active' : '' ?>">
                <div class="tab-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="tab-content">
                    <span class="tab-title">Activos</span>
                    <span class="tab-count"><?= $jefesActivos ?></span>
                </div>
            </a>
            
            <a href="?estado=pendientes" class="filter-tab <?= $estado === 'pendientes' ? 'active' : '' ?>">
                <div class="tab-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="tab-content">
                    <span class="tab-title">Pendientes</span>
                    <span class="tab-count"><?= $jefesPendientes ?></span>
                </div>
            </a>
            
            <a href="?estado=todos" class="filter-tab <?= $estado === 'todos' ? 'active' : '' ?>">
                <div class="tab-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="tab-content">
                    <span class="tab-title">Todos</span>
                    <span class="tab-count"><?= $totalJefes ?></span>
                </div>
            </a>
        </div>
    </div>

    <!-- Content Section -->
    <div class="content-section">
        <?php if ($jefesLaboratorio): ?>
            <div class="jefes-grid">
                <?php foreach ($jefesLaboratorio as $jefe): ?>
                <div class="jefe-card <?= $jefe['activo'] ? 'active' : 'pending' ?>">
                    <div class="card-header">
                        <div class="jefe-avatar">
                            <?= strtoupper(substr($jefe['nombre'], 0, 1)) ?>
                        </div>
                        <div class="jefe-info">
                            <h3 class="jefe-name"><?= htmlspecialchars($jefe['nombre']) ?></h3>
                            <p class="jefe-email"><?= htmlspecialchars($jefe['email']) ?></p>
                        </div>
                        <div class="jefe-status">
                            <span class="status-badge <?= $jefe['activo'] ? 'status-active' : 'status-pending' ?>">
                                <i class="fas fa-<?= $jefe['activo'] ? 'check-circle' : 'clock' ?>"></i>
                                <?= $jefe['activo'] ? 'Activo' : 'Pendiente' ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="jefe-details">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-flask"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Laboratorio</span>
                                    <span class="detail-value"><?= htmlspecialchars($jefe['laboratorio']) ?></span>
                                </div>
                            </div>
                            
                            <?php if ($jefe['especialidad']): ?>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Especialidad</span>
                                    <span class="detail-value"><?= htmlspecialchars($jefe['especialidad']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($jefe['telefono']): ?>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Teléfono</span>
                                    <span class="detail-value"><?= htmlspecialchars($jefe['telefono']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($jefe['extension']): ?>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-phone-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <span class="detail-label">Extensión</span>
                                    <span class="detail-value">Ext. <?= htmlspecialchars($jefe['extension']) ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card-actions">
                        <?php if (!$jefe['activo']): ?>
                            <a href="?action=approve&id=<?= $jefe['id'] ?>" 
                               class="btn btn-success btn-sm"
                               onclick="return confirm('¿Aprobar este jefe de laboratorio?')">
                                <i class="fas fa-check"></i>
                                Aprobar
                            </a>
                            <a href="?action=reject&id=<?= $jefe['id'] ?>" 
                               class="btn btn-warning btn-sm"
                               onclick="return confirm('¿Rechazar este jefe de laboratorio?')">
                                <i class="fas fa-times"></i>
                                Rechazar
                            </a>
                        <?php else: ?>
                            <a href="jefe-editar.php?id=<?= $jefe['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i>
                                Editar
                            </a>
                            <a href="?action=delete&id=<?= $jefe['id'] ?>" 
                               class="btn btn-error btn-sm"
                               onclick="return confirm('¿Eliminar este jefe de laboratorio?')">
                                <i class="fas fa-trash"></i>
                                Eliminar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="empty-content">
                    <h3>No hay jefes de laboratorio</h3>
                    <p><?= $estado === 'pendientes' ? 'No hay solicitudes pendientes de aprobación.' : 
                         ($estado === 'activos' ? 'No hay jefes de laboratorio activos.' : 'No hay jefes de laboratorio registrados.') ?></p>
                    <p>Los jefes de laboratorio pueden registrarse desde la página de registro.</p>
                    <a href="../../../auth/register-jefe.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Invitar Jefe de Laboratorio
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Variables CSS */
:root {
    --primary: #6366f1;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
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

/* Dashboard Container */
.dashboard-container {
    padding: 1rem;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header Section */
.dashboard-header {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 2rem;
}

.header-text {
    flex: 1;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.page-title i {
    color: var(--primary);
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

.header-actions {
    display: flex;
    gap: 1rem;
    flex-shrink: 0;
}

/* Statistics Overview */
.statistics-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--gradient-color), transparent);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card.total {
    --gradient-color: var(--info);
}

.stat-card.activos {
    --gradient-color: var(--success);
}

.stat-card.pendientes {
    --gradient-color: var(--warning);
}

.stat-card.laboratorios {
    --gradient-color: var(--secondary);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    flex-shrink: 0;
}

.stat-card.total .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-card.activos .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.pendientes .stat-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.stat-card.laboratorios .stat-icon {
    background: linear-gradient(135deg, var(--secondary), #a78bfa);
}

.stat-content {
    flex: 1;
}

.stat-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0 0 0.75rem 0;
}

.stat-trend, .stat-alert {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.stat-trend {
    color: var(--success);
}

.stat-alert {
    color: var(--warning);
}

/* Filters Section */
.filters-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}

.filters-header {
    margin-bottom: 1.5rem;
}

.filters-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.filter-tabs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-tab {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: var(--bg-light);
    border-radius: var(--radius);
    text-decoration: none;
    color: inherit;
    transition: var(--transition);
    border: 2px solid transparent;
}

.filter-tab:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: var(--bg-white);
}

.filter-tab.active {
    background: var(--bg-white);
    border-color: var(--primary);
    box-shadow: var(--shadow);
}

.tab-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    flex-shrink: 0;
}

.filter-tab.active .tab-icon {
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.tab-content {
    flex: 1;
}

.tab-title {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.tab-count {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}

/* Content Section */
.content-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
}

/* Jefes Grid */
.jefes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.jefe-card {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: var(--transition);
    border: 1px solid var(--border);
}

.jefe-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    background: var(--bg-white);
}

.jefe-card.active {
    border-left: 4px solid var(--success);
}

.jefe-card.pending {
    border-left: 4px solid var(--warning);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-white);
    border-bottom: 1px solid var(--border-light);
}

.jefe-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.jefe-card.pending .jefe-avatar {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
}

.jefe-info {
    flex: 1;
}

.jefe-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.jefe-email {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.jefe-status {
    flex-shrink: 0;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-pending {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.card-body {
    padding: 1.5rem;
}

.jefe-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: var(--radius);
    transition: var(--transition);
}

.detail-item:hover {
    background: var(--bg-white);
    box-shadow: var(--shadow-sm);
}

.detail-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    font-size: 0.9rem;
    flex-shrink: 0;
}

.detail-content {
    flex: 1;
}

.detail-label {
    display: block;
    font-size: 0.8rem;
    color: var(--text-light);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.125rem;
}

.detail-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}

.card-actions {
    display: flex;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    background: rgba(255, 255, 255, 0.5);
    border-top: 1px solid var(--border-light);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--bg-gray), var(--border));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    color: var(--text-light);
    margin: 0 auto 1.5rem;
}

.empty-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.75rem 0;
}

.empty-content p {
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    line-height: 1.6;
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
    padding: 0.5rem 0.875rem;
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

.btn-success {
    background: linear-gradient(135deg, var(--success), #34d399);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-error {
    background: linear-gradient(135deg, var(--error), #f87171);
    color: white;
}

.btn-error:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Flash Messages */
.flash-message {
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.flash-success {
    background: #f0fdf4;
    color: var(--success);
    border: 1px solid #bbf7d0;
}

.flash-error {
    background: #fef2f2;
    color: var(--error);
    border: 1px solid #fecaca;
}

/* Animaciones */
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

@keyframes countUp {
    from {
        opacity: 0;
        transform: scale(0.5);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.stat-number {
    animation: countUp 0.8s ease-out;
}

.statistics-overview > * {
    animation: slideIn 0.6s ease-out;
}

.statistics-overview > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-overview > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-overview > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-overview > *:nth-child(4) { animation-delay: 0.4s; }

.jefes-grid > * {
    animation: slideIn 0.6s ease-out;
}

.jefes-grid > *:nth-child(odd) { animation-delay: 0.1s; }
.jefes-grid > *:nth-child(even) { animation-delay: 0.2s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    
    .jefes-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    }
}

@media (max-width: 1024px) {
    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .filter-tabs {
        grid-template-columns: 1fr;
    }
    
    .jefes-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .page-title {
        font-size: 1.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .statistics-overview {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .stat-card {
        padding: 1.25rem;
    }
    
    .stat-number {
        font-size: 2rem;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        text-align: center;
    }
    
    .jefe-status {
        align-self: stretch;
    }
    
    .card-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .btn {
        justify-content: center;
        width: 100%;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .empty-state {
        padding: 3rem 1rem;
    }
    
    .empty-icon {
        width: 80px;
        height: 80px;
        font-size: 2rem;
        margin-bottom: 1rem;
    }
}

@media (max-width: 480px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        padding: 1rem;
    }
    
    .stat-icon {
        margin: 0 auto 1rem;
    }
    
    .filters-section {
        padding: 1rem;
    }
    
    .content-section {
        padding: 1rem;
    }
    
    .card-header {
        padding: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .card-actions {
        padding: 1rem;
    }
    
    .tab-icon {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .tab-count {
        font-size: 1.25rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((numberElement, index) => {
        const finalNumber = parseInt(numberElement.textContent);
        let currentNumber = 0;
        const increment = finalNumber / 30;
        
        function animateNumber() {
            if (currentNumber < finalNumber) {
                currentNumber += increment;
                numberElement.textContent = Math.floor(Math.min(currentNumber, finalNumber));
                requestAnimationFrame(animateNumber);
            } else {
                numberElement.textContent = finalNumber;
            }
        }
        
        // Stagger the animations
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.jefe-card, .filter-tab, .stat-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.style.transform) {
                this.style.transform = 'translateY(-5px)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Add loading states to action buttons
    const actionButtons = document.querySelectorAll('.btn');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Solo agregar loading si no es un enlace externo
            if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#')) {
                if (this.getAttribute('onclick')) {
                    // Para botones con confirmación, no agregar loading inmediatamente
                    return;
                }
                
                this.classList.add('loading');
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
                
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.innerHTML = originalText;
                }, 2000);
            }
        });
    });
    
    // Add ripple effect to buttons
    const rippleButtons = document.querySelectorAll('.btn');
    rippleButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.height, rect.width);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add CSS for ripple animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes ripple {
            from {
                transform: scale(0);
                opacity: 1;
            }
            to {
                transform: scale(2);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Improved detail item interactions
    const detailItems = document.querySelectorAll('.detail-item');
    detailItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
    
    // Auto-hide flash messages
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            message.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                message.remove();
            }, 300);
        }, 5000);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>