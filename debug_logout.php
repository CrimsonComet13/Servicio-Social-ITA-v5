<?php
/**
 * Script de Debug para SERVICIO_SOCIAL_ITA
 * Coloca este archivo en la raíz, dashboard, o auth para probar rutas
 */

echo "<!DOCTYPE html><html><head><title>Debug Logout System</title>";
echo "<style>body{font-family:monospace;padding:20px;line-height:1.6;background:#f5f5f5;}";
echo "h2,h3{color:#333;border-bottom:2px solid #007bff;padding-bottom:5px;}";
echo ".ok{color:green;} .error{color:red;} .warning{color:orange;}";
echo ".code{background:#e9ecef;padding:10px;border-radius:5px;margin:10px 0;}";
echo "button{background:#007bff;color:white;padding:10px 15px;border:none;border-radius:5px;cursor:pointer;}";
echo "</style></head><body>";

echo "<h2>🔍 Debug Logout System - SERVICIO_SOCIAL_ITA</h2>";

// Información básica
echo "<h3>📍 Ubicación Actual</h3>";
echo "<strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "<br>";
echo "<strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "<br>";
echo "<strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "<strong>Current Directory:</strong> " . __DIR__ . "<br>";

// Análisis de ubicación
$currentPath = $_SERVER['REQUEST_URI'];
echo "<h3>🗂️  Análisis de Rutas</h3>";

if (strpos($currentPath, '/dashboard/') !== false) {
    echo "<div class='ok'>✅ DETECTADO: Estás en DASHBOARD</div>";
    echo "→ Logout URL debería ser: <code>../auth/logout.php</code><br>";
    echo "→ Redirect URL debería ser: <code>../index.php</code><br>";
} elseif (strpos($currentPath, '/modules/') !== false) {
    echo "<div class='ok'>✅ DETECTADO: Estás en MODULES</div>";
    echo "→ Logout URL debería ser: <code>../auth/logout.php</code><br>";
    echo "→ Redirect URL debería ser: <code>../index.php</code><br>";
} elseif (strpos($currentPath, '/auth/') !== false) {
    echo "<div class='ok'>✅ DETECTADO: Estás en AUTH</div>";
    echo "→ Logout URL debería ser: <code>./logout.php</code><br>";
    echo "→ Redirect URL debería ser: <code>../index.php</code><br>";
} elseif (strpos($currentPath, '/includes/') !== false) {
    echo "<div class='ok'>✅ DETECTADO: Estás en INCLUDES</div>";
    echo "→ Logout URL debería ser: <code>../auth/logout.php</code><br>";
    echo "→ Redirect URL debería ser: <code>../index.php</code><br>";
} else {
    echo "<div class='ok'>✅ DETECTADO: Estás en ROOT</div>";
    echo "→ Logout URL debería ser: <code>./auth/logout.php</code><br>";
    echo "→ Redirect URL debería ser: <code>./index.php</code><br>";
}

// Verificación de archivos críticos
echo "<h3>📁 Verificación de Archivos</h3>";

$filesToCheck = [
    // Desde raíz
    './auth/logout.php' => 'Logout PHP (desde raíz)',
    './index.php' => 'Index PHP (desde raíz)', 
    './assets/js/logout.js' => 'Logout JS (desde raíz)',
    
    // Desde dashboard
    '../auth/logout.php' => 'Logout PHP (desde dashboard/modules)',
    '../index.php' => 'Index PHP (desde dashboard/modules)',
    '../assets/js/logout.js' => 'Logout JS (desde dashboard/modules)',
    
    // Desde auth
    './logout.php' => 'Logout PHP (desde auth)',
    '../index.php' => 'Index PHP (desde auth)',
];

foreach ($filesToCheck as $file => $desc) {
    if (file_exists($file)) {
        echo "<div class='ok'>✅ $desc: <code>$file</code></div>";
    } else {
        echo "<div class='error'>❌ $desc: <code>$file</code> (NO EXISTE)</div>";
    }
}

// Verificar estructura de carpetas
echo "<h3>📂 Estructura de Carpetas</h3>";
$expectedFolders = ['auth', 'dashboard', 'assets', 'includes', 'modules'];

foreach ($expectedFolders as $folder) {
    if (is_dir($folder)) {
        echo "<div class='ok'>✅ Carpeta <code>$folder/</code> existe</div>";
    } elseif (is_dir("../$folder")) {
        echo "<div class='warning'>⚠️  Carpeta <code>$folder/</code> existe un nivel arriba</div>";
    } else {
        echo "<div class='error'>❌ Carpeta <code>$folder/</code> no encontrada</div>";
    }
}

// Test de inclusión de archivos
echo "<h3>🔧 Test de Configuración</h3>";

$configFiles = [
    './config/config.php',
    '../config/config.php',
    './config/session.php', 
    '../config/session.php'
];

foreach ($configFiles as $config) {
    if (file_exists($config)) {
        echo "<div class='ok'>✅ Config encontrado: <code>$config</code></div>";
        break;
    }
}

// JavaScript Test
echo "<h3>🌐 Test JavaScript</h3>";
echo "<div class='code'>";
echo "<button onclick='testLogout()' class='logout-btn'>Test Logout Button</button>";
echo "<button onclick='debugLogout()'>Debug Logout System</button>";
echo "<button onclick='forceLogout()'>Force Logout</button>";
echo "</div>";

echo "<div id='debug-output'></div>";

?>

<script>
console.log('=== DEBUG SCRIPT LOADED ===');

// Debug información
const debugInfo = {
    currentURL: window.location.href,
    pathname: window.location.pathname,
    protocol: window.location.protocol,
    host: window.location.host,
    search: window.location.search
};

console.table(debugInfo);

function testLogout() {
    console.log('🧪 Testing logout button');
    
    const output = document.getElementById('debug-output');
    output.innerHTML = '<h4>Test Results:</h4>';
    
    // Simular la lógica del LogoutManager
    const currentPath = window.location.pathname;
    let logoutUrl = '';
    
    if (currentPath.includes('/dashboard/') || window.location.href.includes('/dashboard/')) {
        logoutUrl = '../auth/logout.php';
        output.innerHTML += '<div class="ok">✅ Detected DASHBOARD - URL: ../auth/logout.php</div>';
    } else if (currentPath.includes('/modules/') || window.location.href.includes('/modules/')) {
        logoutUrl = '../auth/logout.php';
        output.innerHTML += '<div class="ok">✅ Detected MODULES - URL: ../auth/logout.php</div>';
    } else if (currentPath.includes('/auth/') || window.location.href.includes('/auth/')) {
        logoutUrl = './logout.php';
        output.innerHTML += '<div class="ok">✅ Detected AUTH - URL: ./logout.php</div>';
    } else if (currentPath.includes('/includes/') || window.location.href.includes('/includes/')) {
        logoutUrl = '../auth/logout.php';
        output.innerHTML += '<div class="ok">✅ Detected INCLUDES - URL: ../auth/logout.php</div>';
    } else {
        logoutUrl = './auth/logout.php';
        output.innerHTML += '<div class="ok">✅ Detected ROOT - URL: ./auth/logout.php</div>';
    }
    
    output.innerHTML += `<div>Calculated logout URL: <code>${logoutUrl}</code></div>`;
    
    // Test si el archivo existe haciendo una petición HEAD
    fetch(logoutUrl, { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                output.innerHTML += '<div class="ok">✅ Logout URL is accessible</div>';
            } else {
                output.innerHTML += '<div class="error">❌ Logout URL returned: ' + response.status + '</div>';
            }
        })
        .catch(error => {
            output.innerHTML += '<div class="error">❌ Error testing logout URL: ' + error.message + '</div>';
        });
    
    console.log('Calculated logout URL:', logoutUrl);
}

function debugLogout() {
    if (typeof window.debugLogout === 'function') {
        window.debugLogout();
    } else {
        console.log('❌ Logout manager not loaded');
        alert('Logout manager not loaded. Make sure logout.js is included.');
    }
}

// Test automático al cargar
setTimeout(() => {
    testLogout();
}, 1000);

// Override console.log para mostrar en página también
const originalLog = console.log;
console.log = function(...args) {
    originalLog.apply(console, args);
    const output = document.getElementById('debug-output');
    if (output) {
        output.innerHTML += '<div style="font-size:12px;color:#666;">' + args.join(' ') + '</div>';
    }
};
</script>

<style>
button:hover { background: #0056b3; }
.code button { margin: 5px; }
#debug-output { 
    background: white; 
    padding: 15px; 
    border-radius: 5px; 
    margin-top: 20px;
    border: 1px solid #ddd;
}
</style>

</body></html>