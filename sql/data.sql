-- Insertar configuraciones iniciales
INSERT INTO configuracion_sistema (clave, valor, descripcion, tipo, categoria) VALUES
('app_name', 'Sistema de Servicio Social ITA', 'Nombre de la aplicación', 'string', 'general'),
('app_version', '1.0.0', 'Versión actual del sistema', 'string', 'general'),
('horas_servicio_social', '500', 'Horas requeridas para servicio social', 'integer', 'servicio'),
('duracion_maxima_meses', '12', 'Duración máxima en meses', 'integer', 'servicio'),
('email_notifications', 'true', 'Activar notificaciones por email', 'boolean', 'notificaciones'),
('smtp_host', 'localhost', 'Servidor SMTP', 'string', 'email'),
('smtp_port', '587', 'Puerto SMTP', 'integer', 'email'),
('upload_max_size', '5242880', 'Tamaño máximo de archivo en bytes (5MB)', 'integer', 'archivos'),
('allowed_file_types', '["pdf"]', 'Tipos de archivo permitidos', 'json', 'archivos'),
('institution_name', 'Instituto Tecnológico de Aguascalientes', 'Nombre completo de la institución', 'string', 'institucion'),
('department_name', 'Departamento de Gestión Tecnológica y Vinculación', 'Nombre del departamento', 'string', 'institucion');