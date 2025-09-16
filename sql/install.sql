-- Crear base de datos
CREATE DATABASE IF NOT EXISTS servicio_social_ita 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE servicio_social_ita;

-- Tabla usuarios (sistema de autenticación)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    tipo_usuario ENUM('estudiante', 'jefe_departamento', 'jefe_laboratorio') NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    email_verificado BOOLEAN DEFAULT FALSE,
    token_verificacion VARCHAR(100) NULL,
    reset_token VARCHAR(100) NULL,
    reset_token_expires DATETIME NULL,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_tipo (tipo_usuario),
    INDEX idx_activo (activo)
);

-- Tabla estudiantes
CREATE TABLE estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    numero_control VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido_paterno VARCHAR(100) NOT NULL,
    apellido_materno VARCHAR(100) NOT NULL,
    carrera VARCHAR(150) NOT NULL,
    semestre INT NOT NULL,
    creditos_cursados INT NOT NULL,
    telefono VARCHAR(15),
    estado_servicio ENUM('sin_solicitud', 'solicitud_pendiente', 'aprobado', 'en_proceso', 'concluido', 'cancelado') DEFAULT 'sin_solicitud',
    fecha_inicio_servicio DATE NULL,
    fecha_fin_servicio DATE NULL,
    horas_completadas INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_numero_control (numero_control),
    INDEX idx_estado (estado_servicio),
    INDEX idx_carrera (carrera)
);

-- Tabla jefes de departamento
CREATE TABLE jefes_departamento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    departamento VARCHAR(150) NOT NULL,
    telefono VARCHAR(15),
    extension VARCHAR(10),
    puede_evaluar_laboratorio BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla jefes de laboratorio
CREATE TABLE jefes_laboratorio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    jefe_departamento_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    laboratorio VARCHAR(150) NOT NULL,
    especialidad VARCHAR(150),
    telefono VARCHAR(15),
    extension VARCHAR(10),
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_departamento_id) REFERENCES jefes_departamento(id) ON DELETE CASCADE,
    INDEX idx_departamento (jefe_departamento_id),
    INDEX idx_activo (activo)
);

-- Tabla proyectos/actividades de laboratorio
CREATE TABLE proyectos_laboratorio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jefe_departamento_id INT NOT NULL,
    jefe_laboratorio_id INT NULL,
    nombre_proyecto VARCHAR(250) NOT NULL,
    descripcion TEXT,
    laboratorio_asignado VARCHAR(150),
    tipo_actividades TEXT,
    objetivos TEXT,
    horas_requeridas INT DEFAULT 500,
    cupo_disponible INT DEFAULT 3,
    cupo_ocupado INT DEFAULT 0,
    requisitos TEXT,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (jefe_departamento_id) REFERENCES jefes_departamento(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_laboratorio_id) REFERENCES jefes_laboratorio(id) ON DELETE SET NULL,
    INDEX idx_activo (activo),
    INDEX idx_departamento (jefe_departamento_id)
);

-- Tabla solicitudes de servicio social
CREATE TABLE solicitudes_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    jefe_departamento_id INT NOT NULL,
    jefe_laboratorio_id INT NULL,
    fecha_solicitud DATE NOT NULL,
    fecha_inicio_propuesta DATE NOT NULL,
    fecha_fin_propuesta DATE NOT NULL,
    estado ENUM('pendiente', 'aprobada', 'rechazada', 'en_proceso', 'concluida', 'cancelada') DEFAULT 'pendiente',
    motivo_solicitud TEXT,
    observaciones_estudiante TEXT,
    observaciones_jefe TEXT,
    motivo_rechazo TEXT NULL,
    aprobada_por INT NULL,
    fecha_aprobacion DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos_laboratorio(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_departamento_id) REFERENCES jefes_departamento(id),
    FOREIGN KEY (jefe_laboratorio_id) REFERENCES jefes_laboratorio(id) ON DELETE SET NULL,
    FOREIGN KEY (aprobada_por) REFERENCES usuarios(id),
    INDEX idx_estado (estado),
    INDEX idx_estudiante (estudiante_id),
    INDEX idx_fechas (fecha_inicio_propuesta, fecha_fin_propuesta)
);

-- Tabla oficios de presentación
CREATE TABLE oficios_presentacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    solicitud_id INT NOT NULL,
    numero_oficio VARCHAR(50) UNIQUE NOT NULL,
    fecha_emision DATE NOT NULL,
    archivo_path VARCHAR(255),
    generado_por INT NOT NULL,
    estado ENUM('generado', 'entregado', 'recibido') DEFAULT 'generado',
    fecha_entrega DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id),
    INDEX idx_numero_oficio (numero_oficio),
    INDEX idx_estado (estado)
);

-- Tabla reportes bimestrales
CREATE TABLE reportes_bimestrales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    solicitud_id INT NOT NULL,
    jefe_laboratorio_id INT NOT NULL,
    numero_reporte ENUM('1', '2', '3') NOT NULL,
    periodo_inicio DATE NOT NULL,
    periodo_fin DATE NOT NULL,
    fecha_entrega DATE NOT NULL,
    horas_reportadas INT NOT NULL,
    horas_acumuladas INT NOT NULL,
    actividades_realizadas TEXT NOT NULL,
    logros_obtenidos TEXT,
    dificultades_encontradas TEXT,
    archivo_path VARCHAR(255),
    estado ENUM('pendiente_evaluacion', 'evaluado', 'aprobado', 'rechazado', 'revision') DEFAULT 'pendiente_evaluacion',
    calificacion DECIMAL(3,1) NULL,
    observaciones_evaluador TEXT,
    fortalezas TEXT,
    areas_mejora TEXT,
    evaluado_por INT NULL,
    fecha_evaluacion DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (jefe_laboratorio_id) REFERENCES jefes_laboratorio(id),
    FOREIGN KEY (evaluado_por) REFERENCES usuarios(id),
    UNIQUE KEY unique_reporte_estudiante (estudiante_id, solicitud_id, numero_reporte),
    INDEX idx_estado (estado),
    INDEX idx_fecha_entrega (fecha_entrega),
    INDEX idx_evaluacion (evaluado_por, fecha_evaluacion)
);

-- Tabla reportes finales
CREATE TABLE reportes_finales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    solicitud_id INT NOT NULL,
    fecha_entrega DATE NOT NULL,
    horas_totales_cumplidas INT NOT NULL,
    resumen_general TEXT NOT NULL,
    objetivos_alcanzados TEXT,
    competencias_desarrolladas TEXT,
    aprendizajes_significativos TEXT,
    recomendaciones TEXT,
    archivo_path VARCHAR(255),
    estado ENUM('pendiente_evaluacion', 'evaluado', 'aprobado', 'rechazado') DEFAULT 'pendiente_evaluacion',
    calificacion_final DECIMAL(3,1) NULL,
    observaciones_finales TEXT,
    evaluado_por INT NULL,
    fecha_evaluacion DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluado_por) REFERENCES usuarios(id),
    INDEX idx_estado (estado),
    INDEX idx_evaluacion (evaluado_por, fecha_evaluacion)
);

-- Tabla cartas de terminación
CREATE TABLE cartas_terminacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    solicitud_id INT NOT NULL,
    numero_carta VARCHAR(50) UNIQUE NOT NULL,
    fecha_terminacion DATE NOT NULL,
    horas_cumplidas INT NOT NULL,
    periodo_servicio VARCHAR(100) NOT NULL,
    actividades_principales TEXT NOT NULL,
    nivel_desempeno ENUM('Excelente', 'Muy Bueno', 'Bueno', 'Satisfactorio') NOT NULL,
    observaciones TEXT,
    archivo_path VARCHAR(255),
    generado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (solicitud_id) REFERENCES solicitudes_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id),
    INDEX idx_numero_carta (numero_carta)
);

-- Tabla constancias finales
CREATE TABLE constancias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    numero_constancia VARCHAR(50) UNIQUE NOT NULL,
    fecha_emision DATE NOT NULL,
    calificacion_final DECIMAL(3,1) NOT NULL,
    horas_cumplidas INT NOT NULL,
    periodo_completo VARCHAR(100) NOT NULL,
    nivel_desempeno VARCHAR(50) NOT NULL,
    archivo_path VARCHAR(255),
    generado_por INT NOT NULL,
    enviado_servicios_escolares BOOLEAN DEFAULT FALSE,
    fecha_envio_escolares DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    FOREIGN KEY (generado_por) REFERENCES usuarios(id),
    INDEX idx_numero_constancia (numero_constancia),
    INDEX idx_enviado (enviado_servicios_escolares)
);

-- Tabla notificaciones
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    leida BOOLEAN DEFAULT FALSE,
    url_accion VARCHAR(255) NULL,
    fecha_evento DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida),
    INDEX idx_fecha (fecha_evento)
);

-- Tabla log de actividades
CREATE TABLE log_actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    registro_afectado_id INT NULL,
    detalles JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_fecha (usuario_id, created_at),
    INDEX idx_accion (accion),
    INDEX idx_modulo (modulo)
);

-- Tabla configuración del sistema
CREATE TABLE configuracion_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT,
    descripcion TEXT,
    tipo ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    categoria VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clave (clave),
    INDEX idx_categoria (categoria)
);