<?php
require_once '../../config/config.php';
require_once '../../config/session.php';
require_once '../../config/functions.php';

$session = SecureSession::getInstance();
$session->requireRole('jefe_laboratorio');

$db = Database::getInstance();
$usuario = $session->getUser();
$usuarioId = $usuario['id'];

// ✅ SOLUCIÓN CORRECTA con parámetros nombrados
$jefeLab = $db->fetch("
    SELECT jl.id, jl.nombre, jl.laboratorio, jl.especialidad, jl.telefono, jl.extension
    FROM jefes_laboratorio jl
    WHERE jl.usuario_id = :usuario_id
    AND jl.activo = 1
", ['usuario_id' => $usuarioId]);

if (!$jefeLab) {
    flashMessage('Error: No se encontró tu perfil de jefe de laboratorio', 'error');
    redirectTo('/dashboard/jefe_laboratorio.php');
    exit;
}

$jefeLabId = $jefeLab['id']; // Será 1
$nombreLaboratorio = $jefeLab['laboratorio']; // Será "Laboratorio de Redes"

// 🎯 A partir de aquí usa $jefeLabId en todas las consultas

// Variables para el formulario
$errors = [];
$formData = [
    'nombre_proyecto' => '',
    'descripcion' => '',
    'laboratorio_asignado' => $nombreLaboratorio,
    'cupo_disponible' => '',
    'requisitos' => '',
    'objetivos' => '',
    'duracion_estimada' => '',
    'area_conocimiento' => '',
    'modalidad' => 'presencial',
    'horario' => '',
    'responsable_directo' => '',
    'contacto_responsable' => ''
];

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoger datos del formulario
    $formData = [
        'nombre_proyecto' => trim($_POST['nombre_proyecto'] ?? ''),
        'descripcion' => trim($_POST['descripcion'] ?? ''),
        'laboratorio_asignado' => trim($_POST['laboratorio_asignado'] ?? $nombreLaboratorio),
        'cupo_disponible' => (int)($_POST['cupo_disponible'] ?? 0),
        'requisitos' => trim($_POST['requisitos'] ?? ''),
        'objetivos' => trim($_POST['objetivos'] ?? ''),
        'duracion_estimada' => trim($_POST['duracion_estimada'] ?? ''),
        'area_conocimiento' => trim($_POST['area_conocimiento'] ?? ''),
        'modalidad' => trim($_POST['modalidad'] ?? 'presencial'),
        'horario' => trim($_POST['horario'] ?? ''),
        'responsable_directo' => trim($_POST['responsable_directo'] ?? ''),
        'contacto_responsable' => trim($_POST['contacto_responsable'] ?? '')
    ];
    
    // Validaciones
    if (empty($formData['nombre_proyecto'])) {
        $errors['nombre_proyecto'] = 'El nombre del proyecto es obligatorio';
    } elseif (strlen($formData['nombre_proyecto']) < 5) {
        $errors['nombre_proyecto'] = 'El nombre debe tener al menos 5 caracteres';
    } elseif (strlen($formData['nombre_proyecto']) > 200) {
        $errors['nombre_proyecto'] = 'El nombre no puede exceder 200 caracteres';
    }
    
    if (empty($formData['descripcion'])) {
        $errors['descripcion'] = 'La descripción es obligatoria';
    } elseif (strlen($formData['descripcion']) < 50) {
        $errors['descripcion'] = 'La descripción debe tener al menos 50 caracteres';
    }
    
    if (empty($formData['laboratorio_asignado'])) {
        $errors['laboratorio_asignado'] = 'El laboratorio asignado es obligatorio';
    }
    
    if ($formData['cupo_disponible'] < 1) {
        $errors['cupo_disponible'] = 'El cupo disponible debe ser al menos 1';
    } elseif ($formData['cupo_disponible'] > 100) {
        $errors['cupo_disponible'] = 'El cupo disponible no puede exceder 100';
    }
    
    if (empty($formData['objetivos'])) {
        $errors['objetivos'] = 'Los objetivos son obligatorios';
    }
    
    if (empty($formData['duracion_estimada'])) {
        $errors['duracion_estimada'] = 'La duración estimada es obligatoria';
    }
    
    if (empty($formData['area_conocimiento'])) {
        $errors['area_conocimiento'] = 'El área de conocimiento es obligatoria';
    }
    
    // Si no hay errores, guardar en la base de datos
    if (empty($errors)) {
        try {
            $proyectoId = $db->insert('proyectos_laboratorio', [
                'jefe_laboratorio_id' => $jefeLabId,
                'nombre_proyecto' => $formData['nombre_proyecto'],
                'descripcion' => $formData['descripcion'],
                'laboratorio_asignado' => $formData['laboratorio_asignado'],
                'cupo_disponible' => $formData['cupo_disponible'],
                'cupo_ocupado' => 0,
                'requisitos' => $formData['requisitos'],
                'objetivos' => $formData['objetivos'],
                'duracion_estimada' => $formData['duracion_estimada'],
                'area_conocimiento' => $formData['area_conocimiento'],
                'modalidad' => $formData['modalidad'],
                'horario' => $formData['horario'],
                'responsable_directo' => $formData['responsable_directo'],
                'contacto_responsable' => $formData['contacto_responsable'],
                'activo' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($proyectoId) {
                flashMessage('Proyecto creado exitosamente', 'success');
                redirectTo('/modules/laboratorio/proyectos.php');
            } else {
                $errors['general'] = 'Error al crear el proyecto. Por favor, inténtelo de nuevo.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'Error al crear el proyecto: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Crear Nuevo Proyecto - " . APP_NAME;
include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="form-container">
        <!-- Header Section -->
        <div class="form-header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="header-text">
                    <h1 class="form-title">Crear Nuevo Proyecto</h1>
                    <p class="form-subtitle">Complete el formulario para crear un nuevo proyecto de servicio social</p>
                </div>
            </div>
            <div class="header-actions">
                <a href="proyectos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Proyectos
                </a>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($errors['general']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="form-card">
            <form method="POST" action="" id="proyectoForm" novalidate>
                
                <!-- Información Básica -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="section-text">
                            <h2 class="section-title">Información Básica</h2>
                            <p class="section-description">Datos generales del proyecto</p>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="nombre_proyecto" class="required">
                                <i class="fas fa-project-diagram"></i>
                                Nombre del Proyecto
                            </label>
                            <input type="text" 
                                   id="nombre_proyecto" 
                                   name="nombre_proyecto" 
                                   class="form-control <?= isset($errors['nombre_proyecto']) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($formData['nombre_proyecto']) ?>"
                                   placeholder="Ej: Desarrollo de Sistema de Control de Inventario"
                                   maxlength="200"
                                   required>
                            <div class="char-counter">
                                <span id="nombre-counter">0</span>/200 caracteres
                            </div>
                            <?php if (isset($errors['nombre_proyecto'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['nombre_proyecto']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group full-width">
                            <label for="descripcion" class="required">
                                <i class="fas fa-align-left"></i>
                                Descripción del Proyecto
                            </label>
                            <textarea id="descripcion" 
                                      name="descripcion" 
                                      class="form-control <?= isset($errors['descripcion']) ? 'error' : '' ?>"
                                      rows="6"
                                      placeholder="Describa detalladamente el proyecto, sus alcances y las actividades que realizará el estudiante..."
                                      required><?= htmlspecialchars($formData['descripcion']) ?></textarea>
                            <div class="char-counter">
                                <span id="descripcion-counter">0</span> caracteres (mínimo 50)
                            </div>
                            <?php if (isset($errors['descripcion'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['descripcion']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="laboratorio_asignado" class="required">
                                <i class="fas fa-flask"></i>
                                Laboratorio Asignado
                            </label>
                            <input type="text" 
                                   id="laboratorio_asignado" 
                                   name="laboratorio_asignado" 
                                   class="form-control <?= isset($errors['laboratorio_asignado']) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($formData['laboratorio_asignado']) ?>"
                                   required>
                            <?php if (isset($errors['laboratorio_asignado'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['laboratorio_asignado']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="cupo_disponible" class="required">
                                <i class="fas fa-users"></i>
                                Cupo Disponible
                            </label>
                            <input type="number" 
                                   id="cupo_disponible" 
                                   name="cupo_disponible" 
                                   class="form-control <?= isset($errors['cupo_disponible']) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($formData['cupo_disponible']) ?>"
                                   min="1"
                                   max="100"
                                   placeholder="Número de estudiantes"
                                   required>
                            <?php if (isset($errors['cupo_disponible'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['cupo_disponible']) ?>
                                </span>
                            <?php endif; ?>
                            <small class="form-help">Número de estudiantes que pueden realizar servicio social en este proyecto</small>
                        </div>

                        <div class="form-group">
                            <label for="area_conocimiento" class="required">
                                <i class="fas fa-book"></i>
                                Área de Conocimiento
                            </label>
                            <select id="area_conocimiento" 
                                    name="area_conocimiento" 
                                    class="form-control <?= isset($errors['area_conocimiento']) ? 'error' : '' ?>"
                                    required>
                                <option value="">Seleccione un área</option>
                                <option value="Sistemas y Computación" <?= $formData['area_conocimiento'] === 'Sistemas y Computación' ? 'selected' : '' ?>>Sistemas y Computación</option>
                                <option value="Electrónica" <?= $formData['area_conocimiento'] === 'Electrónica' ? 'selected' : '' ?>>Electrónica</option>
                                <option value="Mecánica" <?= $formData['area_conocimiento'] === 'Mecánica' ? 'selected' : '' ?>>Mecánica</option>
                                <option value="Industrial" <?= $formData['area_conocimiento'] === 'Industrial' ? 'selected' : '' ?>>Industrial</option>
                                <option value="Química" <?= $formData['area_conocimiento'] === 'Química' ? 'selected' : '' ?>>Química</option>
                                <option value="Bioquímica" <?= $formData['area_conocimiento'] === 'Bioquímica' ? 'selected' : '' ?>>Bioquímica</option>
                                <option value="Gestión Empresarial" <?= $formData['area_conocimiento'] === 'Gestión Empresarial' ? 'selected' : '' ?>>Gestión Empresarial</option>
                                <option value="Multidisciplinario" <?= $formData['area_conocimiento'] === 'Multidisciplinario' ? 'selected' : '' ?>>Multidisciplinario</option>
                                <option value="Otro" <?= $formData['area_conocimiento'] === 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                            <?php if (isset($errors['area_conocimiento'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['area_conocimiento']) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="duracion_estimada" class="required">
                                <i class="fas fa-clock"></i>
                                Duración Estimada
                            </label>
                            <select id="duracion_estimada" 
                                    name="duracion_estimada" 
                                    class="form-control <?= isset($errors['duracion_estimada']) ? 'error' : '' ?>"
                                    required>
                                <option value="">Seleccione duración</option>
                                <option value="3 meses" <?= $formData['duracion_estimada'] === '3 meses' ? 'selected' : '' ?>>3 meses</option>
                                <option value="4 meses" <?= $formData['duracion_estimada'] === '4 meses' ? 'selected' : '' ?>>4 meses</option>
                                <option value="5 meses" <?= $formData['duracion_estimada'] === '5 meses' ? 'selected' : '' ?>>5 meses</option>
                                <option value="6 meses" <?= $formData['duracion_estimada'] === '6 meses' ? 'selected' : '' ?>>6 meses</option>
                                <option value="Flexible" <?= $formData['duracion_estimada'] === 'Flexible' ? 'selected' : '' ?>>Flexible</option>
                            </select>
                            <?php if (isset($errors['duracion_estimada'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['duracion_estimada']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detalles del Proyecto -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="section-text">
                            <h2 class="section-title">Detalles del Proyecto</h2>
                            <p class="section-description">Requisitos, objetivos y modalidad</p>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="objetivos" class="required">
                                <i class="fas fa-bullseye"></i>
                                Objetivos del Proyecto
                            </label>
                            <textarea id="objetivos" 
                                      name="objetivos" 
                                      class="form-control <?= isset($errors['objetivos']) ? 'error' : '' ?>"
                                      rows="5"
                                      placeholder="Liste los objetivos principales que se buscan alcanzar con este proyecto..."
                                      required><?= htmlspecialchars($formData['objetivos']) ?></textarea>
                            <?php if (isset($errors['objetivos'])): ?>
                                <span class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($errors['objetivos']) ?>
                                </span>
                            <?php endif; ?>
                            <small class="form-help">Describa los objetivos que el estudiante debe cumplir</small>
                        </div>

                        <div class="form-group full-width">
                            <label for="requisitos">
                                <i class="fas fa-check-square"></i>
                                Requisitos para los Estudiantes
                            </label>
                            <textarea id="requisitos" 
                                      name="requisitos" 
                                      class="form-control"
                                      rows="5"
                                      placeholder="Liste los requisitos, conocimientos previos o habilidades necesarias..."><?= htmlspecialchars($formData['requisitos']) ?></textarea>
                            <small class="form-help">Conocimientos, habilidades o materias que debe dominar el estudiante</small>
                        </div>

                        <div class="form-group">
                            <label for="modalidad" class="required">
                                <i class="fas fa-laptop-house"></i>
                                Modalidad
                            </label>
                            <select id="modalidad" 
                                    name="modalidad" 
                                    class="form-control"
                                    required>
                                <option value="presencial" <?= $formData['modalidad'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
                                <option value="remota" <?= $formData['modalidad'] === 'remota' ? 'selected' : '' ?>>Remota</option>
                                <option value="hibrida" <?= $formData['modalidad'] === 'hibrida' ? 'selected' : '' ?>>Híbrida</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="horario">
                                <i class="fas fa-calendar-alt"></i>
                                Horario
                            </label>
                            <input type="text" 
                                   id="horario" 
                                   name="horario" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($formData['horario']) ?>"
                                   placeholder="Ej: Lunes a Viernes 9:00 AM - 2:00 PM">
                            <small class="form-help">Horario en el que se realizarán las actividades</small>
                        </div>
                    </div>
                </div>

                <!-- Información de Contacto -->
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="section-text">
                            <h2 class="section-title">Responsable del Proyecto</h2>
                            <p class="section-description">Información del responsable directo</p>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="responsable_directo">
                                <i class="fas fa-user"></i>
                                Nombre del Responsable
                            </label>
                            <input type="text" 
                                   id="responsable_directo" 
                                   name="responsable_directo" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($formData['responsable_directo']) ?>"
                                   placeholder="Nombre completo del responsable">
                            <small class="form-help">Persona encargada de supervisar el proyecto</small>
                        </div>

                        <div class="form-group">
                            <label for="contacto_responsable">
                                <i class="fas fa-envelope"></i>
                                Contacto del Responsable
                            </label>
                            <input type="text" 
                                   id="contacto_responsable" 
                                   name="contacto_responsable" 
                                   class="form-control"
                                   value="<?= htmlspecialchars($formData['contacto_responsable']) ?>"
                                   placeholder="Email o teléfono">
                            <small class="form-help">Email o teléfono de contacto</small>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="reset" class="btn btn-secondary" onclick="return confirm('¿Está seguro de que desea limpiar el formulario?')">
                        <i class="fas fa-redo"></i>
                        Limpiar Formulario
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Crear Proyecto
                    </button>
                </div>

            </form>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="info-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="info-content">
                <h3 class="info-title">Información Importante</h3>
                <ul class="info-list">
                    <li><i class="fas fa-check"></i> Todos los campos marcados con <span class="required-mark">*</span> son obligatorios</li>
                    <li><i class="fas fa-check"></i> La descripción debe ser clara y detallada (mínimo 50 caracteres)</li>
                    <li><i class="fas fa-check"></i> El cupo disponible puede modificarse posteriormente</li>
                    <li><i class="fas fa-check"></i> Asegúrese de especificar claramente los requisitos y objetivos</li>
                    <li><i class="fas fa-check"></i> El proyecto se creará en estado activo por defecto</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
/* Variables */
:root {
    --sidebar-width: 280px;
    --header-height: 70px;
    --primary: #6366f1;
    --primary-light: #818cf8;
    --secondary: #8b5cf6;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --text-light: #9ca3af;
    --bg-white: #ffffff;
    --bg-light: #f9fafb;
    --bg-gray: #f3f4f6;
    --border: #e5e7eb;
    --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --transition: all 0.3s ease;
}

/* Main wrapper con margen para sidebar */
.main-wrapper {
    margin-left: var(--sidebar-width);
    min-height: calc(100vh - var(--header-height));
    transition: margin-left 0.3s ease;
    background: var(--bg-light);
}

/* Form Container */
.form-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

/* Form Header */
.form-header {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex: 1;
}

.header-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.header-text {
    flex: 1;
}

.form-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

.form-subtitle {
    font-size: 1.1rem;
    color: var(--text-secondary);
    margin: 0;
}

.header-actions {
    flex-shrink: 0;
}

/* Alert */
.alert {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 2rem;
    font-weight: 500;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border-left: 4px solid var(--error);
}

.alert i {
    font-size: 1.5rem;
}

/* Form Card */
.form-card {
    background: var(--bg-white);
    border-radius: var(--radius-lg);
    padding: 2.5rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

/* Form Section */
.form-section {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 2px solid var(--bg-gray);
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 2rem;
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.section-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.section-description {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0.25rem 0 0 0;
}

/* Form Grid */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
}

.form-group label i {
    color: var(--primary);
}

.form-group label.required::after {
    content: '*';
    color: var(--error);
    margin-left: 0.25rem;
}

.form-control {
    width: 100%;
    padding: 0.875rem;
    border: 2px solid var(--border);
    border-radius: var(--radius);
    font-size: 1rem;
    transition: var(--transition);
    font-family: inherit;
    background: var(--bg-white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
}

.form-control.error {
    border-color: var(--error);
}

.form-control.error:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 120px;
}

.char-counter {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
    text-align: right;
}

.error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--error);
    font-size: 0.85rem;
    margin-top: 0.5rem;
    font-weight: 500;
}

.form-help {
    display: block;
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    padding-top: 2rem;
    border-top: 2px solid var(--bg-gray);
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
    font-weight: 500;
    font-size: 0.95rem;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    white-space: nowrap;
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
    border: 2px solid var(--border);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(33, 150, 243, 0.1));
    border-radius: var(--radius-lg);
    padding: 2rem;
    border: 2px solid var(--primary);
    display: flex;
    gap: 1.5rem;
}

.info-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.info-content {
    flex: 1;
}

.info-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.info-list li:last-child {
    margin-bottom: 0;
}

.info-list li i {
    color: var(--primary);
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.required-mark {
    color: var(--error);
    font-weight: bold;
}

/* Responsive */
@media (max-width: 1024px) {
    .main-wrapper {
        margin-left: 0;
    }
    
    .form-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        width: 100%;
    }
}

@media (max-width: 768px) {
    .form-container {
        padding: 1rem;
    }
    
    .form-header {
        padding: 1.5rem;
    }
    
    .header-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .form-card {
        padding: 1.5rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
    
    .info-card {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .form-container {
        padding: 0.75rem;
    }
    
    .form-title {
        font-size: 1.5rem;
    }
    
    .section-title {
        font-size: 1.25rem;
    }
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

.form-card {
    animation: slideIn 0.5s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Character counters
    const nombreInput = document.getElementById('nombre_proyecto');
    const nombreCounter = document.getElementById('nombre-counter');
    
    const descripcionInput = document.getElementById('descripcion');
    const descripcionCounter = document.getElementById('descripcion-counter');
    
    if (nombreInput && nombreCounter) {
        function updateNombreCounter() {
            const length = nombreInput.value.length;
            nombreCounter.textContent = length;
            nombreCounter.style.color = length > 200 ? 'var(--error)' : 'var(--text-secondary)';
        }
        
        nombreInput.addEventListener('input', updateNombreCounter);
        updateNombreCounter();
    }
    
    if (descripcionInput && descripcionCounter) {
        function updateDescripcionCounter() {
            const length = descripcionInput.value.length;
            descripcionCounter.textContent = length;
            
            if (length < 50) {
                descripcionCounter.style.color = 'var(--error)';
            } else {
                descripcionCounter.style.color = 'var(--success)';
            }
        }
        
        descripcionInput.addEventListener('input', updateDescripcionCounter);
        updateDescripcionCounter();
    }
    
    // Form validation
    const form = document.getElementById('proyectoForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Add shake animation
                    field.style.animation = 'shake 0.5s';
                    setTimeout(() => {
                        field.style.animation = '';
                    }, 500);
                } else {
                    field.classList.remove('error');
                }
            });
            
            // Validate description length
            if (descripcionInput.value.length < 50) {
                isValid = false;
                descripcionInput.classList.add('error');
                alert('La descripción debe tener al menos 50 caracteres.');
            }
            
            // Validate nombre length
            if (nombreInput.value.length < 5) {
                isValid = false;
                nombreInput.classList.add('error');
                alert('El nombre del proyecto debe tener al menos 5 caracteres.');
            }
            
            // Validate cupo
            const cupoInput = document.getElementById('cupo_disponible');
            const cupoValue = parseInt(cupoInput.value);
            if (cupoValue < 1 || cupoValue > 100) {
                isValid = false;
                cupoInput.classList.add('error');
                alert('El cupo disponible debe estar entre 1 y 100.');
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
        
        // Remove error class on input
        form.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('input', function() {
                this.classList.remove('error');
            });
        });
    }
    
    // Confirm before leaving if form has data
    let formModified = false;
    
    form.addEventListener('input', function() {
        formModified = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formModified) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    form.addEventListener('submit', function() {
        formModified = false;
    });
});

// Shake animation for validation errors
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);
</script>

<?php include '../../includes/footer.php'; ?>