<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Incluir TCPDF
require_once __DIR__ . '/lib/tcpdf/tcpdf.php';

class ITAPDFGenerator {
    private $db;
    private $pdf;
    
    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4') {
        $this->db = Database::getInstance();
        
        // Crear instancia de TCPDF
        $this->pdf = new TCPDF($orientation, $unit, $format, true, 'UTF-8', false);
        
        // Configurar información del documento
        $this->pdf->SetCreator(APP_NAME);
        $this->pdf->SetAuthor(APP_NAME);
        $this->pdf->SetTitle('Documento Oficial ITA');
        $this->pdf->SetSubject('Documento Generado por el Sistema de Servicio Social');
        
        // Configurar márgenes
        $this->pdf->SetMargins(15, 25, 15);
        $this->pdf->SetHeaderMargin(10);
        $this->pdf->SetFooterMargin(10);
        
        // Configurar auto page breaks
        $this->pdf->SetAutoPageBreak(true, 25);
        
        // Configurar fuente por defecto
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    public function generarOficioPresentacion($solicitudId) {
        // Obtener datos de la solicitud
        $solicitud = $this->db->fetch("
            SELECT s.*, e.*, p.nombre_proyecto, 
                   jd.nombre as jefe_nombre, jd.departamento,
                   jl.nombre as jefe_lab_nombre, jl.laboratorio
            FROM solicitudes_servicio s
            JOIN estudiantes e ON s.estudiante_id = e.id
            JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
            JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
            LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
            WHERE s.id = :id
        ", ['id' => $solicitudId]);
        
        if (!$solicitud) {
            throw new Exception('Solicitud no encontrada');
        }
        
        // Generar número de oficio
        $numeroOficio = generateNumeroOficio();
        
        // Crear página
        $this->pdf->AddPage();
        
        // Cargar plantilla
        ob_start();
        include 'templates/oficio_presentacion.php';
        $html = ob_get_clean();
        
        // Escribir contenido
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        // Guardar archivo
        $filename = 'oficios/' . $numeroOficio . '.pdf';
        $fullPath = UPLOAD_PATH . $filename;
        
        // Crear directorio si no existe
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->pdf->Output($fullPath, 'F');
        
        // Guardar registro en BD
        $this->db->insert('oficios_presentacion', [
            'solicitud_id' => $solicitudId,
            'numero_oficio' => $numeroOficio,
            'fecha_emision' => date('Y-m-d'),
            'archivo_path' => $filename,
            'generado_por' => $_SESSION['usuario']['id'] ?? null
        ]);
        
        return [
            'path' => $filename,
            'numero' => $numeroOficio,
            'full_path' => $fullPath
        ];
    }
    
    public function generarCartaTerminacion($solicitudId) {
        // Obtener datos de la solicitud y estudiante
        $solicitud = $this->db->fetch("
            SELECT s.*, e.*, p.nombre_proyecto, 
                   jd.nombre as jefe_nombre, jd.departamento,
                   jl.nombre as jefe_lab_nombre, jl.laboratorio,
                   SUM(rb.horas_reportadas) as horas_totales
            FROM solicitudes_servicio s
            JOIN estudiantes e ON s.estudiante_id = e.id
            JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
            JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
            LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
            LEFT JOIN reportes_bimestrales rb ON s.id = rb.solicitud_id
            WHERE s.id = :id
            GROUP BY s.id
        ", ['id' => $solicitudId]);
        
        if (!$solicitud) {
            throw new Exception('Solicitud no encontrada');
        }
        
        // Generar número de carta
        $numeroCarta = 'CTA-' . date('Y') . '-' . str_pad($solicitudId, 4, '0', STR_PAD_LEFT);
        
        // Crear página
        $this->pdf->AddPage();
        
        // Cargar plantilla
        ob_start();
        include 'templates/carta_terminacion.php';
        $html = ob_get_clean();
        
        // Escribir contenido
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        // Guardar archivo
        $filename = 'cartas/' . $numeroCarta . '.pdf';
        $fullPath = UPLOAD_PATH . $filename;
        
        // Crear directorio si no existe
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->pdf->Output($fullPath, 'F');
        
        // Guardar registro en BD
        $this->db->insert('cartas_terminacion', [
            'estudiante_id' => $solicitud['estudiante_id'],
            'solicitud_id' => $solicitudId,
            'numero_carta' => $numeroCarta,
            'fecha_terminacion' => date('Y-m-d'),
            'horas_cumplidas' => $solicitud['horas_totales'] ?? $solicitud['horas_completadas'],
            'periodo_servicio' => formatDate($solicitud['fecha_inicio_propuesta']) . ' - ' . formatDate($solicitud['fecha_fin_propuesta']),
            'actividades_principales' => 'Servicio social en ' . $solicitud['laboratorio'] . ' - ' . $solicitud['nombre_proyecto'],
            'nivel_desempeno' => 'Bueno', // Esto debería calcularse basado en evaluaciones
            'archivo_path' => $filename,
            'generado_por' => $_SESSION['usuario']['id'] ?? null
        ]);
        
        return [
            'path' => $filename,
            'numero' => $numeroCarta,
            'full_path' => $fullPath
        ];
    }
    
    public function generarConstancia($estudianteId) {
        // Obtener datos del estudiante y su servicio social
        $estudiante = $this->db->fetch("
            SELECT e.*, s.*, p.nombre_proyecto, 
                   jd.nombre as jefe_nombre, jd.departamento,
                   jl.nombre as jefe_lab_nombre, jl.laboratorio,
                   AVG(rb.calificacion) as calificacion_promedio,
                   SUM(rb.horas_reportadas) as horas_totales
            FROM estudiantes e
            JOIN solicitudes_servicio s ON e.id = s.estudiante_id
            JOIN proyectos_laboratorio p ON s.proyecto_id = p.id
            JOIN jefes_departamento jd ON s.jefe_departamento_id = jd.id
            LEFT JOIN jefes_laboratorio jl ON s.jefe_laboratorio_id = jl.id
            LEFT JOIN reportes_bimestrales rb ON s.id = rb.solicitud_id
            WHERE e.id = :id AND s.estado = 'concluida'
            GROUP BY e.id
        ", ['id' => $estudianteId]);
        
        if (!$estudiante) {
            throw new Exception('Estudiante no encontrado o servicio social no concluido');
        }
        
        // Generar número de constancia
        $numeroConstancia = generateNumeroConstancia();
        
        // Crear página
        $this->pdf->AddPage();
        
        // Cargar plantilla
        ob_start();
        include 'templates/constancia.php';
        $html = ob_get_clean();
        
        // Escribir contenido
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        // Guardar archivo
        $filename = 'constancias/' . $numeroConstancia . '.pdf';
        $fullPath = UPLOAD_PATH . $filename;
        
        // Crear directorio si no existe
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->pdf->Output($fullPath, 'F');
        
        // Determinar nivel de desempeño basado en calificación
        $calificacion = $estudiante['calificacion_promedio'] ?? 8.0;
        $nivelDesempeno = 'Satisfactorio';
        
        if ($calificacion >= 9.0) $nivelDesempeno = 'Excelente';
        elseif ($calificacion >= 8.0) $nivelDesempeno = 'Muy Bueno';
        elseif ($calificacion >= 7.0) $nivelDesempeno = 'Bueno';
        
        // Guardar registro en BD
        $this->db->insert('constancias', [
            'estudiante_id' => $estudianteId,
            'numero_constancia' => $numeroConstancia,
            'fecha_emision' => date('Y-m-d'),
            'calificacion_final' => $calificacion,
            'horas_cumplidas' => $estudiante['horas_totales'] ?? $estudiante['horas_completadas'],
            'periodo_completo' => formatDate($estudiante['fecha_inicio_propuesta']) . ' - ' . formatDate($estudiante['fecha_fin_propuesta']),
            'nivel_desempeno' => $nivelDesempeno,
            'archivo_path' => $filename,
            'generado_por' => $_SESSION['usuario']['id'] ?? null
        ]);
        
        return [
            'path' => $filename,
            'numero' => $numeroConstancia,
            'full_path' => $fullPath
        ];
    }
    
    public function outputInline($filename = 'documento.pdf') {
        $this->pdf->Output($filename, 'I');
    }
    
    public function outputDownload($filename = 'documento.pdf') {
        $this->pdf->Output($filename, 'D');
    }
    
    public function getPDF() {
        return $this->pdf;
    }
}

// Función helper para generar documentos
function generarDocumento($tipo, $id) {
    $generator = new ITAPDFGenerator();
    
    try {
        switch ($tipo) {
            case 'oficio':
                return $generator->generarOficioPresentacion($id);
            case 'carta':
                return $generator->generarCartaTerminacion($id);
            case 'constancia':
                return $generator->generarConstancia($id);
            default:
                throw new Exception('Tipo de documento no válido');
        }
    } catch (Exception $e) {
        error_log('Error generando documento: ' . $e->getMessage());
        return false;
    }
}
?>