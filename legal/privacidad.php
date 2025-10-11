<?php
// Configuración de la página
define('APP_NAME', 'Sistema de Servicio Social ITA');
$pageTitle = 'Política de Privacidad - ' . APP_NAME;

// Incluir archivos de configuración
require_once '../config/config.php';
require_once '../config/session.php';

// Iniciar sesión si está disponible
$session = null;
if (class_exists('SecureSession')) {
    $session = SecureSession::getInstance();
}

// Incluir el header
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<style>
    /* Estilos específicos para la página de privacidad */
    .legal-page {
        max-width: 900px;
        margin: 0 auto;
        padding: 2rem;
        background: var(--bg-white);
    }

    .legal-header {
        text-align: center;
        margin-bottom: 3rem;
        padding-bottom: 2rem;
        border-bottom: 2px solid var(--border);
    }

    .legal-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .legal-header .last-updated {
        font-size: 0.95rem;
        color: var(--text-secondary);
        font-style: italic;
    }

    .legal-content {
        line-height: 1.8;
        color: var(--text-primary);
    }

    .legal-content h2 {
        font-size: 1.75rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-top: 2.5rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-light);
    }

    .legal-content h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .legal-content p {
        margin-bottom: 1rem;
        text-align: justify;
    }

    .legal-content ul, .legal-content ol {
        margin-bottom: 1rem;
        padding-left: 2rem;
    }

    .legal-content li {
        margin-bottom: 0.5rem;
    }

    .legal-content strong {
        color: var(--text-primary);
        font-weight: 600;
    }

    .info-box {
        background: var(--bg-light);
        border-left: 4px solid var(--primary);
        padding: 1.25rem;
        margin: 1.5rem 0;
        border-radius: 4px;
    }

    .info-box p {
        margin: 0;
        color: var(--text-secondary);
    }

    .contact-section {
        background: var(--bg-light);
        padding: 2rem;
        border-radius: var(--radius);
        margin-top: 3rem;
        text-align: center;
    }

    .contact-section h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .contact-section p {
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .contact-section a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }

    .contact-section a:hover {
        text-decoration: underline;
    }
</style>

<div class="legal-page">
    <div class="legal-header">
        <h1><i class="fas fa-shield-alt"></i> Política de Privacidad</h1>
        <p class="last-updated">Última actualización: 11 de octubre de 2025</p>
    </div>

    <div class="legal-content">
        <p>
            El Instituto Tecnológico de Aguascalientes (en adelante, "ITA") se compromete a proteger la privacidad y 
            seguridad de la información personal de los usuarios del Sistema de Servicio Social ITA (en adelante, "el Sistema"). 
            Esta Política de Privacidad describe cómo recopilamos, usamos, almacenamos y protegemos su información personal.
        </p>

        <div class="info-box">
            <p>
                <strong><i class="fas fa-info-circle"></i> Nota Importante:</strong> 
                Al utilizar el Sistema, usted acepta los términos de esta Política de Privacidad. 
                Si no está de acuerdo con estos términos, por favor no utilice el Sistema.
            </p>
        </div>

        <h2>1. Información que Recopilamos</h2>
        
        <h3>1.1 Información Personal</h3>
        <p>
            Recopilamos información personal que usted nos proporciona directamente al registrarse y utilizar el Sistema, 
            incluyendo pero no limitándose a:
        </p>
        <ul>
            <li><strong>Datos de Identificación:</strong> Nombre completo, número de control (para estudiantes), correo electrónico institucional</li>
            <li><strong>Datos Académicos:</strong> Carrera, semestre, créditos cursados, historial académico relevante</li>
            <li><strong>Datos de Contacto:</strong> Teléfono, dirección de correo electrónico</li>
            <li><strong>Datos Laborales:</strong> Departamento, laboratorio, especialidad (para jefes de departamento y laboratorio)</li>
            <li><strong>Documentación:</strong> Reportes de servicio social, oficios, cartas de terminación y constancias</li>
        </ul>

        <h3>1.2 Información de Uso</h3>
        <p>
            Recopilamos automáticamente cierta información sobre su interacción con el Sistema, incluyendo:
        </p>
        <ul>
            <li>Dirección IP y datos de conexión</li>
            <li>Tipo de navegador y sistema operativo</li>
            <li>Páginas visitadas y funcionalidades utilizadas</li>
            <li>Fecha y hora de acceso</li>
            <li>Acciones realizadas en el Sistema (registro de actividades)</li>
        </ul>

        <h2>2. Uso de la Información</h2>
        
        <p>
            Utilizamos la información recopilada para los siguientes propósitos:
        </p>
        <ul>
            <li><strong>Gestión del Servicio Social:</strong> Administrar solicitudes, asignaciones, seguimiento y evaluación del servicio social</li>
            <li><strong>Comunicación:</strong> Enviar notificaciones, actualizaciones y comunicaciones relacionadas con el servicio social</li>
            <li><strong>Autenticación y Seguridad:</strong> Verificar la identidad de los usuarios y proteger el Sistema contra accesos no autorizados</li>
            <li><strong>Mejora del Sistema:</strong> Analizar el uso del Sistema para mejorar su funcionalidad y experiencia de usuario</li>
            <li><strong>Cumplimiento Legal:</strong> Cumplir con obligaciones legales y regulatorias aplicables</li>
            <li><strong>Generación de Documentos:</strong> Crear oficios, cartas de terminación y constancias oficiales</li>
            <li><strong>Reportes y Estadísticas:</strong> Generar reportes institucionales y estadísticas agregadas (sin identificación personal)</li>
        </ul>

        <h2>3. Compartir Información</h2>
        
        <p>
            El ITA no vende, alquila ni comparte su información personal con terceros, excepto en las siguientes circunstancias:
        </p>
        <ul>
            <li><strong>Dentro de la Institución:</strong> Compartimos información con personal autorizado del ITA que necesita acceso para cumplir con sus funciones (jefes de departamento, jefes de laboratorio, servicios escolares)</li>
            <li><strong>Requisitos Legales:</strong> Cuando sea requerido por ley, orden judicial o autoridad competente</li>
            <li><strong>Protección de Derechos:</strong> Para proteger los derechos, propiedad o seguridad del ITA, sus usuarios o terceros</li>
            <li><strong>Servicios Escolares:</strong> Información necesaria para la emisión de constancias oficiales y registro académico</li>
        </ul>

        <h2>4. Seguridad de la Información</h2>
        
        <p>
            Implementamos medidas de seguridad técnicas, administrativas y físicas para proteger su información personal contra 
            acceso no autorizado, pérdida, destrucción o alteración, incluyendo:
        </p>
        <ul>
            <li>Cifrado de datos sensibles mediante protocolos SSL/TLS</li>
            <li>Contraseñas encriptadas con algoritmos seguros</li>
            <li>Control de acceso basado en roles (RBAC)</li>
            <li>Registro y monitoreo de actividades del sistema</li>
            <li>Respaldos periódicos de la base de datos</li>
            <li>Servidores protegidos con firewalls y sistemas de detección de intrusos</li>
            <li>Políticas de seguridad y capacitación del personal</li>
        </ul>

        <div class="info-box">
            <p>
                <strong><i class="fas fa-exclamation-triangle"></i> Responsabilidad del Usuario:</strong> 
                Usted es responsable de mantener la confidencialidad de sus credenciales de acceso. 
                No comparta su contraseña con terceros y notifique inmediatamente cualquier uso no autorizado de su cuenta.
            </p>
        </div>

        <h2>5. Retención de Datos</h2>
        
        <p>
            Conservamos su información personal durante el tiempo necesario para cumplir con los propósitos descritos en esta 
            Política de Privacidad, a menos que la ley requiera o permita un período de retención más prolongado. Los criterios 
            para determinar el período de retención incluyen:
        </p>
        <ul>
            <li>Duración de la relación con el ITA</li>
            <li>Requisitos legales y regulatorios aplicables</li>
            <li>Necesidades de archivo histórico institucional</li>
            <li>Resolución de disputas o reclamaciones</li>
        </ul>

        <h2>6. Derechos de los Usuarios</h2>
        
        <p>
            De acuerdo con la legislación aplicable en materia de protección de datos personales, usted tiene los siguientes derechos:
        </p>
        <ul>
            <li><strong>Acceso:</strong> Solicitar información sobre los datos personales que tenemos sobre usted</li>
            <li><strong>Rectificación:</strong> Solicitar la corrección de datos inexactos o incompletos</li>
            <li><strong>Cancelación:</strong> Solicitar la eliminación de sus datos personales (sujeto a obligaciones legales)</li>
            <li><strong>Oposición:</strong> Oponerse al tratamiento de sus datos personales en determinadas circunstancias</li>
            <li><strong>Portabilidad:</strong> Solicitar una copia de sus datos en formato estructurado y legible</li>
            <li><strong>Revocación del Consentimiento:</strong> Retirar su consentimiento en cualquier momento (cuando aplique)</li>
        </ul>

        <p>
            Para ejercer estos derechos, por favor contacte a nuestro Departamento de Protección de Datos a través de los 
            medios indicados en la sección de contacto.
        </p>

        <h2>7. Cookies y Tecnologías Similares</h2>
        
        <p>
            El Sistema utiliza cookies y tecnologías similares para mejorar la experiencia del usuario y garantizar el 
            funcionamiento adecuado del sistema. Las cookies que utilizamos incluyen:
        </p>
        <ul>
            <li><strong>Cookies Esenciales:</strong> Necesarias para el funcionamiento básico del Sistema (autenticación, sesión)</li>
            <li><strong>Cookies de Funcionalidad:</strong> Permiten recordar sus preferencias y configuraciones</li>
            <li><strong>Cookies de Análisis:</strong> Nos ayudan a entender cómo se utiliza el Sistema para mejorarlo</li>
        </ul>

        <p>
            Puede configurar su navegador para rechazar cookies, pero esto puede afectar la funcionalidad del Sistema.
        </p>

        <h2>8. Menores de Edad</h2>
        
        <p>
            El Sistema está diseñado para estudiantes universitarios mayores de edad. Si identificamos que hemos recopilado 
            información de un menor de edad sin el consentimiento apropiado, tomaremos medidas para eliminar dicha información.
        </p>

        <h2>9. Cambios a esta Política</h2>
        
        <p>
            Nos reservamos el derecho de modificar esta Política de Privacidad en cualquier momento. Los cambios significativos 
            serán notificados a través del Sistema o por correo electrónico. La fecha de "Última actualización" al inicio de 
            esta política indica cuándo fue modificada por última vez.
        </p>

        <h2>10. Transferencias Internacionales</h2>
        
        <p>
            Su información personal se almacena y procesa en servidores ubicados en México. No realizamos transferencias 
            internacionales de datos personales, excepto cuando sea estrictamente necesario y con las garantías de protección 
            adecuadas.
        </p>

        <h2>11. Legislación Aplicable</h2>
        
        <p>
            Esta Política de Privacidad se rige por la Ley Federal de Protección de Datos Personales en Posesión de los 
            Particulares (LFPDPPP) y su Reglamento, así como por las disposiciones aplicables en materia de protección de 
            datos personales en México.
        </p>

        <div class="contact-section">
            <h3><i class="fas fa-envelope"></i> Contacto</h3>
            <p>
                Si tiene preguntas, comentarios o inquietudes sobre esta Política de Privacidad o sobre el tratamiento de 
                sus datos personales, puede contactarnos a través de:
            </p>
            <p>
                <strong>Correo Electrónico:</strong> <a href="mailto:privacidad@ita.mx">privacidad@ita.mx</a><br>
                <strong>Teléfono:</strong> <a href="tel:+524499105002">(449) 910-5002</a><br>
                <strong>Dirección:</strong> Av. Adolfo López Mateos #1801 Ote., Fracc. Bona Gens, C.P. 20256, Aguascalientes, Ags.
            </p>
        </div>
    </div>
</div>

<?php
// Incluir el footer
include '../includes/footer.php';
?>