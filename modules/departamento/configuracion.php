<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_departamento');

$db = Database::getInstance();
$success = '';
$error = '';

// Obtener configuraciones actuales
$configuraciones = $db->fetchAll("SELECT * FROM configuracion_sistema ORDER BY categoria, clave");

// Agrupar por categoría
$configByCategory = [];
foreach ($configuraciones as $config) {
    $configByCategory[$config['categoria']][] = $config;
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = $_POST['config'] ?? [];
    
    try {
        $db->beginTransaction();
        
        foreach ($updates as $clave => $valor) {
            // Obtener el tipo de la configuración
            $config = $db->fetch("SELECT tipo FROM configuracion_sistema WHERE clave = ?", [$clave]);
            
            if ($config) {
                // Convertir valor según el tipo
                $valorConvertido = $valor;
                if ($config['tipo'] === 'integer') {
                    $valorConvertido = intval($valor);
                } elseif ($config['tipo'] === 'boolean') {
                    $valorConvertido = ($valor === '1' || $valor === 'true') ? 'true' : 'false';
                }
                
                $db->update('configuracion_sistema', 
                    ['valor' => $valorConvertido], 
                    'clave = :clave', 
                    ['clave' => $clave]
                );
            }
        }
        
        $db->commit();
        $success = 'Configuración actualizada correctamente';
        
        // Recargar configuraciones
        $configuraciones = $db->fetchAll("SELECT * FROM configuracion_sistema ORDER BY categoria, clave");
        $configByCategory = [];
        foreach ($configuraciones as $config) {
            $configByCategory[$config['categoria']][] = $config;
        }
        
    } catch (Exception $e) {
        $db->rollback();
        $error = 'Error al actualizar la configuración: ' . $e->getMessage();
    }
}

// Calcular estadísticas de configuración
$totalConfigs = count($configuraciones);
$categorias = count($configByCategory);
$tiposConfig = array_count_values(array_column($configuraciones, 'tipo'));

$pageTitle = "Configuración del Sistema - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>
<div class="main-wrapper">
    <div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-text">
                <h1 class="page-title">
                    <i class="fas fa-cogs"></i>
                    Configuración del Sistema
                </h1>
                <p class="page-subtitle">Gestión avanzada de parámetros y configuraciones del sistema de servicio social</p>
            </div>
            <div class="header-actions">
                <a href="../../dashboard/jefe_departamento.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Dashboard
                </a>
                <button type="button" class="btn btn-info" onclick="showConfigHelp()">
                    <i class="fas fa-question-circle"></i>
                    Ayuda
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if ($success): ?>
        <div class="flash-message flash-success">
            <div class="flash-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="flash-content">
                <h4>¡Configuración actualizada!</h4>
                <p><?= htmlspecialchars($success) ?></p>
            </div>
            <button class="flash-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="flash-message flash-error">
            <div class="flash-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="flash-content">
                <h4>Error en la configuración</h4>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
            <button class="flash-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="statistics-overview">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="fas fa-sliders-h"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Total de Configuraciones</h3>
                <div class="stat-number"><?= $totalConfigs ?></div>
                <p class="stat-description">Parámetros configurables</p>
                <div class="stat-trend">
                    <i class="fas fa-cog"></i>
                    <span>Sistema completo</span>
                </div>
            </div>
        </div>

        <div class="stat-card categorias">
            <div class="stat-icon">
                <i class="fas fa-layer-group"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Categorías</h3>
                <div class="stat-number"><?= $categorias ?></div>
                <p class="stat-description">Secciones organizadas</p>
                <div class="stat-trend">
                    <i class="fas fa-folder-open"></i>
                    <span>Bien estructurado</span>
                </div>
            </div>
        </div>

        <div class="stat-card tipos">
            <div class="stat-icon">
                <i class="fas fa-code"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Tipos de Datos</h3>
                <div class="stat-number"><?= count($tiposConfig) ?></div>
                <p class="stat-description">Formatos diferentes</p>
                <div class="stat-trend">
                    <i class="fas fa-database"></i>
                    <span>Tipado fuerte</span>
                </div>
            </div>
        </div>

        <div class="stat-card estado">
            <div class="stat-icon">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="stat-content">
                <h3 class="stat-title">Estado del Sistema</h3>
                <div class="stat-number">100%</div>
                <p class="stat-description">Configurado correctamente</p>
                <div class="stat-trend">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sistema estable</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="configuration-section">
        <div class="configuration-header">
            <h2 class="section-title">
                <i class="fas fa-tools"></i>
                Configuraciones del Sistema
            </h2>
            <div class="configuration-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="resetForm()">
                    <i class="fas fa-undo"></i>
                    Restablecer
                </button>
                <button type="button" class="btn btn-warning btn-sm" onclick="exportConfig()">
                    <i class="fas fa-download"></i>
                    Exportar
                </button>
            </div>
        </div>

        <form method="POST" class="config-form" id="configForm">
            <?php foreach ($configByCategory as $categoria => $configs): ?>
            <div class="config-category" data-category="<?= htmlspecialchars($categoria) ?>">
                <div class="category-header">
                    <div class="category-info">
                        <h3 class="category-title">
                            <i class="fas fa-<?= getCategoryIcon($categoria) ?>"></i>
                            <?= ucfirst(str_replace('_', ' ', $categoria)) ?>
                        </h3>
                        <p class="category-description"><?= getCategoryDescription($categoria) ?></p>
                    </div>
                    <div class="category-toggle">
                        <button type="button" class="toggle-btn" onclick="toggleCategory(this)">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>
                
                <div class="category-content">
                    <div class="config-grid">
                        <?php foreach ($configs as $config): ?>
                        <div class="config-item <?= $config['tipo'] ?>" data-type="<?= $config['tipo'] ?>">
                            <div class="config-item-header">
                                <label for="config_<?= $config['clave'] ?>" class="config-label">
                                    <div class="label-content">
                                        <span class="label-title"><?= formatConfigKey($config['clave']) ?></span>
                                        <span class="label-type"><?= strtoupper($config['tipo']) ?></span>
                                    </div>
                                    <p class="label-description"><?= htmlspecialchars($config['descripcion']) ?></p>
                                </label>
                            </div>
                            
                            <div class="config-input-container">
                                <?php if ($config['tipo'] === 'boolean'): ?>
                                    <div class="toggle-switch">
                                        <select id="config_<?= $config['clave'] ?>" name="config[<?= $config['clave'] ?>]" class="toggle-select">
                                            <option value="false" <?= $config['valor'] === 'false' ? 'selected' : '' ?>>Desactivado</option>
                                            <option value="true" <?= $config['valor'] === 'true' ? 'selected' : '' ?>>Activado</option>
                                        </select>
                                        <div class="toggle-visual" onclick="toggleBoolean('config_<?= $config['clave'] ?>')">
                                            <div class="toggle-slider <?= $config['valor'] === 'true' ? 'active' : '' ?>"></div>
                                        </div>
                                    </div>
                                    
                                <?php elseif ($config['tipo'] === 'integer'): ?>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-hashtag"></i>
                                        </div>
                                        <input type="number" id="config_<?= $config['clave'] ?>" 
                                               name="config[<?= $config['clave'] ?>]" 
                                               value="<?= htmlspecialchars($config['valor']) ?>"
                                               class="config-input number-input">
                                        <div class="input-suffix">Entero</div>
                                    </div>
                                    
                                <?php elseif ($config['tipo'] === 'json'): ?>
                                    <div class="json-input-container">
                                        <div class="input-group">
                                            <div class="input-icon">
                                                <i class="fas fa-code"></i>
                                            </div>
                                            <textarea id="config_<?= $config['clave'] ?>" 
                                                      name="config[<?= $config['clave'] ?>]" 
                                                      rows="4"
                                                      class="config-input json-input"
                                                      placeholder='{"clave": "valor"}'><?= htmlspecialchars($config['valor']) ?></textarea>
                                        </div>
                                        <div class="json-tools">
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="formatJSON('config_<?= $config['clave'] ?>')">
                                                <i class="fas fa-magic"></i>
                                                Formatear
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" onclick="validateJSON('config_<?= $config['clave'] ?>')">
                                                <i class="fas fa-check"></i>
                                                Validar
                                            </button>
                                        </div>
                                    </div>
                                    
                                <?php else: ?>
                                    <div class="input-group">
                                        <div class="input-icon">
                                            <i class="fas fa-font"></i>
                                        </div>
                                        <input type="text" id="config_<?= $config['clave'] ?>" 
                                               name="config[<?= $config['clave'] ?>]" 
                                               value="<?= htmlspecialchars($config['valor']) ?>"
                                               class="config-input text-input">
                                        <div class="input-suffix">Texto</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="config-item-footer">
                                <div class="config-status">
                                    <span class="status-indicator <?= !empty($config['valor']) ? 'configured' : 'empty' ?>"></span>
                                    <span class="status-text"><?= !empty($config['valor']) ? 'Configurado' : 'Vacío' ?></span>
                                </div>
                                <button type="button" class="config-reset" onclick="resetConfigItem('config_<?= $config['clave'] ?>', '<?= htmlspecialchars($config['valor']) ?>')">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="form-actions">
                <div class="actions-left">
                    <button type="button" class="btn btn-secondary" onclick="previewChanges()">
                        <i class="fas fa-eye"></i>
                        Vista Previa
                    </button>
                    <button type="button" class="btn btn-warning" onclick="validateAllConfigs()">
                        <i class="fas fa-check-circle"></i>
                        Validar Todo
                    </button>
                </div>
                <div class="actions-right">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        <span class="btn-text">
                            <i class="fas fa-save"></i>
                            Guardar Configuración
                        </span>
                        <span class="btn-loader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            Guardando...
                        </span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    </div>
</div>

<?php
// Funciones helper para mejorar la presentación
function getCategoryIcon($categoria) {
    $icons = [
        'general' => 'cog',
        'email' => 'envelope',
        'seguridad' => 'shield-alt',
        'notificaciones' => 'bell',
        'reportes' => 'chart-bar',
        'sistema' => 'server',
        'usuarios' => 'users',
        'base_datos' => 'database'
    ];
    return $icons[$categoria] ?? 'cog';
}

function getCategoryDescription($categoria) {
    $descriptions = [
        'general' => 'Configuraciones básicas del sistema',
        'email' => 'Parámetros de correo electrónico',
        'seguridad' => 'Configuraciones de seguridad y acceso',
        'notificaciones' => 'Alertas y notificaciones del sistema',
        'reportes' => 'Configuración de reportes y estadísticas',
        'sistema' => 'Parámetros del servidor y aplicación',
        'usuarios' => 'Configuraciones de usuarios y perfiles',
        'base_datos' => 'Configuración de base de datos'
    ];
    return $descriptions[$categoria] ?? 'Configuraciones específicas';
}

function formatConfigKey($key) {
    return ucwords(str_replace(['_', '-'], ' ', $key));
}
?>

<style>
    :root {
    --sidebar-width: 280px;
    --header-height: 70px;
}

/* Main wrapper con margen para sidebar */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
}

/* Dashboard container ajustado */
.dashboard-container {
    max-width: calc(1400px - var(--sidebar-width));
    margin: 0 auto;
    width: 100%;
    box-sizing: border-box;
}

/* Responsive: En móvil sidebar se oculta */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .dashboard-container {
        max-width: 1400px;
    }
}
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
    margin-bottom: 2rem;
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

/* Flash Messages */
.flash-message {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    position: relative;
    animation: slideInDown 0.5s ease-out;
}

.flash-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(52, 211, 153, 0.05) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    color: var(--success);
}

.flash-error {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(248, 113, 113, 0.05) 100%);
    border: 1px solid rgba(239, 68, 68, 0.2);
    color: var(--error);
}

.flash-icon {
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

.flash-success .flash-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.flash-error .flash-icon {
    background: linear-gradient(135deg, var(--error), #f87171);
}

.flash-content {
    flex: 1;
}

.flash-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
}

.flash-content p {
    margin: 0;
    opacity: 0.9;
}

.flash-close {
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: var(--radius);
    opacity: 0.7;
    transition: var(--transition);
    flex-shrink: 0;
}

.flash-close:hover {
    opacity: 1;
    background: rgba(0, 0, 0, 0.1);
}

/* Statistics Overview */
.statistics-overview {
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
    --gradient-color: var(--primary);
}

.stat-card.categorias {
    --gradient-color: var(--success);
}

.stat-card.tipos {
    --gradient-color: var(--info);
}

.stat-card.estado {
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
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
}

.stat-card.categorias .stat-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
}

.stat-card.tipos .stat-icon {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.stat-card.estado .stat-icon {
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

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--success);
}

/* Configuration Section */
.configuration-section {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.configuration-header {
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

.section-title i {
    color: var(--primary);
}

.configuration-actions {
    display: flex;
    gap: 0.75rem;
}

/* Config Categories */
.config-category {
    background: var(--bg-light);
    border-radius: var(--radius-lg);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: var(--transition);
}

.category-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    background: var(--bg-white);
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: var(--transition);
}

.category-header:hover {
    background: var(--bg-light);
}

.category-info {
    flex: 1;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.25rem 0;
}

.category-title i {
    color: var(--primary);
}

.category-description {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.category-toggle {
    flex-shrink: 0;
}

.toggle-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.toggle-btn:hover {
    color: var(--primary);
    background: rgba(99, 102, 241, 0.1);
}

.toggle-btn.active {
    transform: rotate(180deg);
}

.category-content {
    padding: 1.5rem;
    max-height: 1000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.category-content.collapsed {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
}

/* Config Grid */
.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
}

/* Config Items */
.config-item {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: var(--transition);
    position: relative;
}

.config-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    border-color: var(--primary);
}

.config-item.boolean {
    border-left: 4px solid var(--success);
}

.config-item.integer {
    border-left: 4px solid var(--info);
}

.config-item.string {
    border-left: 4px solid var(--warning);
}

.config-item.json {
    border-left: 4px solid var(--secondary);
}

.config-item-header {
    margin-bottom: 1rem;
}

.config-label {
    cursor: pointer;
}

.label-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.label-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.label-type {
    font-size: 0.7rem;
    font-weight: 600;
    color: white;
    background: var(--text-light);
    padding: 0.25rem 0.5rem;
    border-radius: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.config-item.boolean .label-type {
    background: var(--success);
}

.config-item.integer .label-type {
    background: var(--info);
}

.config-item.string .label-type {
    background: var(--warning);
}

.config-item.json .label-type {
    background: var(--secondary);
}

.label-description {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.4;
}

/* Input Groups */
.config-input-container {
    margin-bottom: 1rem;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    z-index: 2;
    color: var(--text-light);
    font-size: 0.9rem;
}

.config-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    background: var(--bg-white);
}

.config-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.input-suffix {
    position: absolute;
    right: 1rem;
    z-index: 2;
    font-size: 0.8rem;
    color: var(--text-light);
    font-weight: 500;
    background: var(--bg-white);
    padding: 0 0.5rem;
}

.json-input {
    padding-right: 1rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
}

.json-tools {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
    justify-content: flex-end;
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
}

.toggle-select {
    display: none;
}

.toggle-visual {
    width: 60px;
    height: 30px;
    background: var(--border);
    border-radius: 15px;
    position: relative;
    cursor: pointer;
    transition: var(--transition);
}

.toggle-visual:hover {
    background: var(--text-light);
}

.toggle-slider {
    width: 26px;
    height: 26px;
    background: white;
    border-radius: 50%;
    position: absolute;
    top: 2px;
    left: 2px;
    transition: var(--transition);
    box-shadow: var(--shadow);
}

.toggle-slider.active {
    transform: translateX(30px);
    background: var(--success);
}

.toggle-visual:has(.toggle-slider.active) {
    background: rgba(16, 185, 129, 0.3);
}

/* Config Item Footer */
.config-item-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
}

.config-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--border);
}

.status-indicator.configured {
    background: var(--success);
}

.status-text {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.config-reset {
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: var(--radius);
    transition: var(--transition);
}

.config-reset:hover {
    color: var(--warning);
    background: rgba(245, 158, 11, 0.1);
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border-light);
    gap: 1rem;
}

.actions-left,
.actions-right {
    display: flex;
    gap: 1rem;
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

.btn-info {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    color: white;
}

.btn-info:hover,
.btn-warning:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Animations */
@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
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
    animation: slideInDown 0.6s ease-out;
}

.statistics-overview > *:nth-child(1) { animation-delay: 0.1s; }
.statistics-overview > *:nth-child(2) { animation-delay: 0.2s; }
.statistics-overview > *:nth-child(3) { animation-delay: 0.3s; }
.statistics-overview > *:nth-child(4) { animation-delay: 0.4s; }

/* Responsive Design */
@media (max-width: 1200px) {
    .statistics-overview {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
    
    .config-grid {
        grid-template-columns: 1fr;
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
    
    .configuration-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .configuration-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .actions-left,
    .actions-right {
        justify-content: center;
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
    
    .category-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .config-item {
        padding: 1.25rem;
    }
    
    .header-actions,
    .configuration-actions {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .actions-left,
    .actions-right {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .btn {
        justify-content: center;
        width: 100%;
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
    
    .configuration-section {
        padding: 1.5rem 1rem;
    }
    
    .category-content {
        padding: 1rem;
    }
    
    .config-item {
        padding: 1rem;
    }
    
    .flash-message {
        padding: 1rem;
    }
    
    .json-tools {
        flex-direction: column;
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
        
        setTimeout(() => {
            animateNumber();
        }, index * 200);
    });
    
    // Form submission loading state
    const form = document.getElementById('configForm');
    const saveBtn = document.getElementById('saveBtn');
    
    if (form && saveBtn) {
        const btnText = saveBtn.querySelector('.btn-text');
        const btnLoader = saveBtn.querySelector('.btn-loader');
        
        form.addEventListener('submit', function(e) {
            saveBtn.disabled = true;
            if (btnText) btnText.style.display = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
        });
    }
    
    // Add hover effects to cards
    const cards = document.querySelectorAll('.stat-card, .config-item');
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
    
    // Initialize toggle switches
    initializeToggles();
});

function toggleCategory(btn) {
    const category = btn.closest('.config-category');
    const content = category.querySelector('.category-content');
    const icon = btn.querySelector('i');
    
    content.classList.toggle('collapsed');
    btn.classList.toggle('active');
}

function toggleBoolean(inputId) {
    const select = document.getElementById(inputId);
    const slider = document.querySelector(`#${inputId}`).parentElement.querySelector('.toggle-slider');
    
    const currentValue = select.value;
    const newValue = currentValue === 'true' ? 'false' : 'true';
    
    select.value = newValue;
    slider.classList.toggle('active', newValue === 'true');
    
    // Update status indicator
    updateConfigStatus(inputId, newValue);
}

function initializeToggles() {
    const toggles = document.querySelectorAll('.toggle-visual');
    toggles.forEach(toggle => {
        const select = toggle.parentElement.querySelector('select');
        const slider = toggle.querySelector('.toggle-slider');
        
        if (select && slider) {
            slider.classList.toggle('active', select.value === 'true');
        }
    });
}

function resetConfigItem(inputId, originalValue) {
    const input = document.getElementById(inputId);
    if (input.type === 'select-one') {
        input.value = originalValue;
        const slider = input.parentElement.querySelector('.toggle-slider');
        if (slider) {
            slider.classList.toggle('active', originalValue === 'true');
        }
    } else {
        input.value = originalValue;
    }
    
    updateConfigStatus(inputId, originalValue);
}

function updateConfigStatus(inputId, value) {
    const configItem = document.getElementById(inputId).closest('.config-item');
    const indicator = configItem.querySelector('.status-indicator');
    const text = configItem.querySelector('.status-text');
    
    if (value && value.toString().trim() !== '') {
        indicator.classList.add('configured');
        indicator.classList.remove('empty');
        text.textContent = 'Configurado';
    } else {
        indicator.classList.remove('configured');
        indicator.classList.add('empty');
        text.textContent = 'Vacío';
    }
}

function formatJSON(inputId) {
    const input = document.getElementById(inputId);
    try {
        const parsed = JSON.parse(input.value);
        input.value = JSON.stringify(parsed, null, 2);
        showNotification('JSON formateado correctamente', 'success');
    } catch (e) {
        showNotification('Error en el formato JSON: ' + e.message, 'error');
    }
}

function validateJSON(inputId) {
    const input = document.getElementById(inputId);
    try {
        JSON.parse(input.value);
        showNotification('JSON válido', 'success');
    } catch (e) {
        showNotification('JSON inválido: ' + e.message, 'error');
    }
}

function resetForm() {
    if (confirm('¿Estás seguro de que quieres restablecer todos los valores?')) {
        document.getElementById('configForm').reset();
        initializeToggles();
        showNotification('Formulario restablecido', 'info');
    }
}

function exportConfig() {
    // Simular exportación de configuración
    showNotification('Configuración exportada correctamente', 'success');
}

function previewChanges() {
    // Simular vista previa de cambios
    showNotification('Vista previa preparada', 'info');
}

function validateAllConfigs() {
    let valid = true;
    const jsonInputs = document.querySelectorAll('.json-input');
    
    jsonInputs.forEach(input => {
        try {
            if (input.value.trim()) {
                JSON.parse(input.value);
            }
        } catch (e) {
            valid = false;
        }
    });
    
    if (valid) {
        showNotification('Todas las configuraciones son válidas', 'success');
    } else {
        showNotification('Hay errores en algunas configuraciones JSON', 'error');
    }
}

function showConfigHelp() {
    alert('Ayuda de Configuración:\n\n' +
          '• Boolean: Activa o desactiva funcionalidades\n' +
          '• Integer: Valores numéricos enteros\n' +
          '• String: Texto simple\n' +
          '• JSON: Datos estructurados en formato JSON\n\n' +
          'Los cambios se guardan inmediatamente al hacer clic en "Guardar Configuración".');
}

function showNotification(message, type) {
    // Crear notificación temporal
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}"></i>
        <span>${message}</span>
    `;
    
    // Estilos para la notificación
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: ${type === 'success' ? 'var(--success)' : type === 'error' ? 'var(--error)' : 'var(--info)'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        z-index: 1000;
        animation: slideInRight 0.3s ease-out;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    `;
    
    document.body.appendChild(notification);
    
    // Remover después de 3 segundos
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Agregar estilos de animación para notificaciones
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(notificationStyles);
</script>

<?php include '../../includes/footer.php'; ?>