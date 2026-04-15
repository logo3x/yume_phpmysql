<?php
/**
 * Diagnóstico de API
 * Accede: http://localhost/yume-main/api-diagnostic.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>API Diagnostic</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 40px auto; padding: 20px; }
        .ok { background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .err { background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 4px; }
        .info { background: #d1ecf1; padding: 10px; margin: 5px 0; border-radius: 4px; }
        pre { background: #f4f4f4; padding: 10px; overflow: auto; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>API Diagnostic</h1>
    
    <h2>Prueba de rutas</h2>
    <button onclick="testAPI('/api/health')">Test /api/health</button>
    <button onclick="testAPI('/api/auth/status')">Test /api/auth/status</button>
    <button onclick="testAPI('/api/products')">Test /api/products</button>
    <button onclick="testAPI('/api/settings')">Test /api/settings</button>
    
    <div id="results"></div>
    
    <script>
        async function testAPI(url) {
            const results = document.getElementById('results');
            const div = document.createElement('div');
            div.className = 'info';
            div.innerHTML = `<strong>Probando:</strong> ${url}<br><em>Cargando...</em>`;
            results.appendChild(div);
            
            try {
                const res = await fetch(url);
                const data = await res.json();
                div.className = res.ok ? 'ok' : 'err';
                div.innerHTML = `<strong>${url}</strong><br>Status: ${res.status}<br><pre>${JSON.stringify(data, null, 2)}</pre>`;
            } catch (e) {
                div.className = 'err';
                div.innerHTML = `<strong>${url}</strong><br>Error: ${e.message}`;
            }
        }
    </script>
</body>
</html>
