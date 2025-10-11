<?php
// Configuración de la página
define('APP_NAME', 'Sistema de Servicio Social ITA');
$pageTitle = 'Términos y Condiciones - ' . APP_NAME;

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
    /* Estilos específicos para la página de términos y condiciones */
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

    .warning-box {
        background: #fef3e2;
        border-left: 4px solid var(--warning);
        padding: 1.25rem;
        margin: 1.5rem 0;
        border-radius: 4px;
    }

    .warning-box p {
        margin: 0;
        color: var(--text-primary);
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

    .acceptance-section {
        background: #f0f9ff;
        border: 2px solid var(--primary);
        padding: 2rem;
        border-radius: var(--radius);
        margin-top: 3rem;
        text-align: center;
    }

    .acceptance-section h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .acceptance-section p {
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
    }
</style>

<div class="legal-page">
    <div class="legal-header">
        <h1><i class="fas fa-file-contract"></i> Términos y Condiciones de Uso</h1>
        <p class="last-updated">Última actualización: 11 de octubre de 2025</p>
    </div>

    <div class="legal-content">
        <p>
            Bienvenido al Sistema de Servicio Social del Instituto Tecnológico de Aguascalientes (en adelante, "el Sistema"). 
            Estos Términos y Condiciones (en adelante, "Términos") regulan el acceso y uso del Sistema por parte de estudiantes, 
            personal académico y administrativo del ITA.
        </p>

        <div class="info-box">
            <p>
                <strong><i class="fas fa-info-circle"></i> Aceptación de Términos:</strong> 
                Al acceder y utilizar el Sistema, usted acepta cumplir con estos Términos y Condiciones en su totalidad. 
                Si no está de acuerdo con alguno de estos términos, por favor no utilice el Sistema.
            </p>
        </div>

        <h2>1. Definiciones</h2>
        
        <p>
            Para los efectos de estos Términos, se entenderá por:
        </p>
        <ul>
            <li><strong>"Sistema":</strong> El Sistema de Servicio Social ITA, incluyendo todas sus funcionalidades, módulos y componentes</li>
            <li><strong>"Usuario":</strong> Cualquier persona que acceda y utilice el Sistema (estudiantes, jefes de departamento, jefes de laboratorio, personal administrativo)</li>
            <li><strong>"Contenido":</strong> Toda la información, datos, documentos, reportes y materiales cargados al Sistema</li>
            <li><strong>"Servicio Social":</strong> Las actividades académicas obligatorias que los estudiantes deben realizar según el plan de estudios</li>
            <li><strong>"Proyecto":</strong> Las actividades de servicio social registradas en el Sistema</li>
        </ul>

        <h2>2. Registro y Cuenta de Usuario</h2>
        
        <h3>2.1 Elegibilidad</h3>
        <p>
            Para utilizar el Sistema, debe ser:
        </p>
        <ul>
            <li>Estudiante activo del ITA con al menos 70% de créditos aprobados</li>
            <li>Personal académico o administrativo del ITA debidamente autorizado</li>
            <li>Persona autorizada por el Departamento de Servicio Social del ITA</li>
        </ul>

        <h3>2.2 Creación de Cuenta</h3>
        <p>
            Los estudiantes se registran utilizando su número de control y correo institucional. El personal académico y 
            administrativo será registrado por el administrador del sistema.
        </p>

        <h3>2.3 Responsabilidad de la Cuenta</h3>
        <p>
            Usted es responsable de:
        </p>
        <ul>
            <li>Mantener la confidencialidad de sus credenciales de acceso</li>
            <li>Todas las actividades que ocurran bajo su cuenta</li>
            <li>Notificar inmediatamente al administrador del sistema sobre cualquier uso no autorizado de su cuenta</li>
            <li>Proporcionar información veraz y actualizada en su perfil</li>
        </ul>

        <div class="warning-box">
            <p>
                <strong><i class="fas fa-exclamation-triangle"></i> Advertencia:</strong> 
                El ITA se reserva el derecho de suspender o cancelar cuentas que violen estos Términos o que sean utilizadas 
                de manera fraudulenta o inapropiada.
            </p>
        </div>

        <h2>3. Uso Aceptable del Sistema</h2>
        
        <p>
            Usted se compromete a utilizar el Sistema únicamente para los fines autorizados relacionados con la gestión del 
            servicio social, incluyendo:
        </p>
        <ul>
            <li>Registro y seguimiento de actividades de servicio social</li>
            <li>Gestión de proyectos de servicio social</li>
            <li>Entrega de reportes y documentación requerida</li>
            <li>Comunicación oficial entre estudiantes y supervisores</li>
            <li>Generación de documentación oficial (oficios, constancias, cartas de terminación)</li>
        </ul>

        <h3>3.1 Conductas Prohibidas</h3>
        <p>
            Está estrictamente prohibido:
        </p>
        <ul>
            <li>Utilizar el Sistema para fines comerciales o no autorizados</li>
            <li>Suplantar la identidad de otros usuarios</li>
            <li>Manipular o alterar información del Sistema</li>
            <li>Cargar contenido malicioso, virus o código dañino</li>
            <li>Realizar actividades que afecten el rendimiento o seguridad del Sistema</li>
            <li>Compartir credenciales de acceso con terceros</li>
            <li>Utilizar bots, scripts automatizados o métodos no autorizados para acceder al Sistema</li>
            <li>Violar derechos de propiedad intelectual o de privacidad de otros usuarios</li>
        </ul>

        <h2>4. Propiedad Intelectual</h2>
        
        <h3>4.1 Propiedad del ITA</h3>
        <p>
            El ITA es propietario de todos los derechos de propiedad intelectual relacionados con el Sistema, incluyendo pero 
            no limitándose a:
        </p>
        <ul>
            <li>El software, código fuente y base de datos del Sistema</li>
            <li>La interfaz gráfica, diseño y experiencia de usuario</li>
            <li>La documentación, manuales y materiales de ayuda</li>
            <li>Los formatos, plantillas y documentos oficiales</li>
        </ul>

        <h3>4.2 Contenido del Usuario</h3>
        <p>
            Usted conserva los derechos de propiedad intelectual sobre el contenido que carga al Sistema (reportes, documentos, 
            etc.). Sin embargo, al cargar contenido al Sistema, usted otorga al ITA una licencia no exclusiva, libre de regalías, 
            para utilizar, almacenar, reproducir y distribuir dicho contenido con fines académicos y administrativos relacionados 
            con el servicio social.
        </p>

        <h2>5. Responsabilidades del Usuario</h2>
        
        <h3>5.1 Estudiantes</h3>
        <p>
            Los estudiantes son responsables de:
        </p>
        <ul>
            <li>Completar y mantener actualizada su información personal y académica</li>
            <li>Cumplir con los plazos establecidos para la entrega de reportes y documentación</li>
            <li>Realizar las 500 horas de servicio social según el plan aprobado</li>
            <li>Mantener comunicación regular con su supervisor asignado</li>
            <li>Cumplir con las normas éticas y de conducta durante la realización del servicio social</li>
            <li>Notificar cualquier cambio en su situación académica que afecte su servicio social</li>
        </ul>

        <h3>5.2 Personal Académico</h3>
        <p>
            El personal académico (jefes de departamento, jefes de laboratorio) es responsable de:
        </p>
        <ul>
            <li>Supervisar y evaluar el progreso de los estudiantes asignados</li>
            <li>Revisar y aprobar reportes bimestrales dentro de los plazos establecidos</li>
            <li>Proporcionar retroalimentación constructiva a los estudiantes</li>
            <li>Garantizar que los proyectos de servicio social cumplan con los objetivos académicos</li>
            <li>Notificar cualquier irregularidad o problema relacionado con el servicio social</li>
        </ul>

        <h2>6. Limitación de Responsabilidad</h2>
        
        <p>
            El ITA proporciona el Sistema "tal cual" y "según disponibilidad". Si bien nos esforzamos por mantener el Sistema 
            operativo y seguro, no garantizamos que:
        </p>
        <ul>
            <li>El Sistema esté siempre disponible sin interrupciones</li>
            <li>El Sistema esté libre de errores o vulnerabilidades</li>
            <li>Los resultados obtenidos a través del Sistema sean exactos o confiables en todo momento</li>
            <li>El Sistema sea compatible con todos los dispositivos y navegadores</li>
        </ul>

        <p>
            En la medida máxima permitida por la ley, el ITA no será responsable por:
        </p>
        <ul>
            <li>Daños directos, indirectos, incidentales o consecuentes resultantes del uso del Sistema</li>
            <li>Pérdida de datos, información o contenido del usuario</li>
            <li>Interrupciones del servicio por causas fuera de nuestro control</li>
            <li>Actuaciones o omisiones de otros usuarios del Sistema</li>
        </ul>

        <h2>7. Suspensión y Terminación</h2>
        
        <p>
            El ITA se reserva el derecho de suspender o terminar el acceso al Sistema en los siguientes casos:
        </p>
        <ul>
            <li>Violación de estos Términos y Condiciones</li>
            <li>Uso fraudulento o no autorizado del Sistema</li>
            <li>Actividades que pongan en riesgo la seguridad o integridad del Sistema</li>
            <li>Finalización de la relación académica o laboral con el ITA</li>
            <li>Por requerimiento de autoridades competentes</li>
        </ul>

        <h2>8. Modificaciones a los Términos</h2>
        
        <p>
            El ITA se reserva el derecho de modificar estos Términos en cualquier momento. Las modificaciones significativas 
            serán notificadas a través del Sistema o por correo electrónico. El uso continuado del Sistema después de dichas 
            modificaciones constituye la aceptación de los Términos revisados.
        </p>

        <h2>9. Ley Aplicable y Jurisdicción</h2>
        
        <p>
            Estos Términos se rigen por las leyes de los Estados Unidos Mexicanos, específicamente por la legislación del 
            estado de Aguascalientes. Cualquier disputa relacionada con estos Términos o el uso del Sistema será resuelta 
            en los tribunales competentes de Aguascalientes, Aguascalientes, renunciando expresamente a cualquier otra 
            jurisdicción que pudiera corresponderles.
        </p>

        <h2>10. Disposiciones Generales</h2>
        
        <h3>10.1 Integridad del Acuerdo</h3>
        <p>
            Estos Términos constituyen el acuerdo completo entre usted y el ITA respecto al uso del Sistema y reemplazan 
            cualquier acuerdo anterior.
        </p>

        <h3>10.2 Divisibilidad</h3>
        <p>
            Si cualquier disposición de estos Términos es considerada inválida o inaplicable, las disposiciones restantes 
            mantendrán su validez y efecto.
        </p>

        <h3>10.3 Tolerancia</h3>
        <p>
            La falta de ejercicio o exigencia por parte del ITA de cualquier derecho o disposición de estos Términos no 
            constituirá una renuncia a dicho derecho o disposición.
        </p>

        <div class="acceptance-section">
            <h3><i class="fas fa-check-circle"></i> Aceptación de Términos</h3>
            <p>
                Al utilizar el Sistema de Servicio Social ITA, usted reconoce haber leído, entendido y aceptado 
                estos Términos y Condiciones en su totalidad.
            </p>
            <p>
                <strong>Fecha de entrada en vigor:</strong> 11 de octubre de 2025
            </p>
        </div>

        <div class="contact-section">
            <h3><i class="fas fa-envelope"></i> Contacto</h3>
            <p>
                Si tiene preguntas o comentarios sobre estos Términos y Condiciones, puede contactarnos a través de:
            </p>
            <p>
                <strong>Coordinación de Servicio Social:</strong> <a href="mailto:servicio.social@ita.mx">servicio.social@ita.mx</a><br>
                <strong>Teléfono:</strong> <a href="tel:+524499102020">(449) 910-2020 Ext. 2150</a><br>
                <strong>Dirección:</strong> Av. Adolfo López Mateos #1801 Ote., Fracc. Bona Gens, C.P. 20256, Aguascalientes, Ags.
            </p>
        </div>
    </div>
</div>

<?php
// Incluir el footer
include '../includes/footer.php';
?>