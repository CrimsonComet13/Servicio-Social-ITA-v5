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

$pageTitle = "Configuración del Sistema - " . APP_NAME;
include '../../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Configuración del Sistema</h1>
        <p>Gestión de parámetros y configuraciones del sistema</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="config-form">
        <?php foreach ($configByCategory as $categoria => $configs): ?>
        <div class="config-category">
            <h2><?= ucfirst($categoria) ?></h2>
            
            <div class="config-grid">
                <?php foreach ($configs as $config): ?>
                <div class="config-item">
                    <label for="config_<?= $config['clave'] ?>">
                        <strong><?= $config['clave'] ?></strong>
                        <small><?= $config['descripcion'] ?></small>
                    </label>
                    
                    <?php if ($config['tipo'] === 'boolean'): ?>
                        <select id="config_<?= $config['clave'] ?>" name="config[<?= $config['clave'] ?>]">
                            <option value="true" <?= $config['valor'] === 'true' ? 'selected' : '' ?>>Sí</option>
                            <option value="false" <?= $config['valor'] === 'false' ? 'selected' : '' ?>>No</option>
                        </select>
                    <?php elseif ($config['tipo'] === 'integer'): ?>
                        <input type="number" id="config_<?= $config['clave'] ?>" 
                               name="config[<?= $config['clave'] ?>]" value="<?= htmlspecialchars($config['valor']) ?>">
                    <?php elseif ($config['tipo'] === 'json'): ?>
                        <textarea id="config_<?= $config['clave'] ?>" 
                                  name="config[<?= $config['clave'] ?>]" 
                                  rows="3"><?= htmlspecialchars($config['valor']) ?></textarea>
                    <?php else: ?>
                        <input type="text" id="config_<?= $config['clave'] ?>" 
                               name="config[<?= $config['clave'] ?>]" value="<?= htmlspecialchars($config['valor']) ?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar Configuración</button>
            <button type="reset" class="btn btn-secondary">Restablecer</button>
        </div>
    </form>
</div>

<style>
.config-category {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.config-category h2 {
    margin: 0 0 1.5rem 0;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-color);
    color: var(--secondary-color);
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.config-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.config-item label {
    font-weight: 500;
}

.config-item label small {
    display: block;
    font-weight: normal;
    color: #666;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.config-item input,
.config-item select,
.config-item textarea {
    padding: 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    font-size: 1rem;
}

.config-item input:focus,
.config-item select:focus,
.config-item textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(29, 185, 84, 0.1);
}

.form-actions {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
    text-align: center;
}
</style>

<?php include '../../includes/footer.php'; ?>