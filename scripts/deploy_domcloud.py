#!/usr/bin/env python3
"""
DeUna - Domcloud Deployment Script

Creates a Domcloud-ready deployment package from the project files.
Domcloud uses public_html/ as document root and requires the application
code to be in a separate directory outside the web root.

Usage:
    python3 scripts/deploy_domcloud.py [--target /home/user] [--dry-run]

Output structure:
    <target>/
    ├── public_html/          ← Document root (solo archivos públicos)
    │   ├── css/
    │   ├── js/
    │   ├── assets/
    │   ├── index.php         ← Front controller modificado
    │   └── router.php        ← Router modificado
    ├── deuna_app/            ← Aplicación (fuera del alcance público)
    │   ├── app/
    │   ├── config/
    │   ├── database/
    │   ├── scripts/
    │   └── sql/
    └── deploy_checklist.txt  ← Verification checklist
"""

import os
import sys
import shutil
import argparse
from pathlib import Path


def main():
    parser = argparse.ArgumentParser(description='Deploy DeUna for Domcloud')
    parser.add_argument('--target', default=None,
                        help='Target directory (default: ./domcloud_deploy)')
    parser.add_argument('--source', default=None,
                        help='Source project root (default: project root)')
    parser.add_argument('--dry-run', action='store_true',
                        help='Show what would be done without making changes')
    args = parser.parse_args()

    # Determine paths
    script_path = Path(__file__).resolve()
    project_root = Path(args.source) if args.source else script_path.parent.parent
    target_root = Path(args.target) if args.target else project_root / 'domcloud_deploy'

    print(f"📁 Proyecto origen: {project_root}")
    print(f"📁 Destino Domcloud: {target_root}")
    print(f"🔍 Dry run: {args.dry_run}")
    print()

    # Define source paths
    public_src = project_root / 'public'
    app_src = project_root / 'app'
    config_src = project_root / 'config'
    scripts_src = project_root / 'scripts'
    sql_src = project_root / 'sql'
    database_src = project_root / 'database'

    # Define destination paths
    public_html = target_root / 'public_html'
    deuna_app = target_root / 'deuna_app'

    # Verify source exists
    if not public_src.exists():
        print(f"❌ ERROR: No se encuentra {public_src}")
        sys.exit(1)
    if not app_src.exists():
        print(f"❌ ERROR: No se encuentra {app_src}")
        sys.exit(1)

    files_copied = 0

    # === Paso 1: Estructura de directorios ===
    print("📋 Paso 1: Creando estructura de directorios...")

    dirs_to_create = [
        public_html,
        public_html / 'css',
        public_html / 'js',
        public_html / 'assets',
        public_html / 'uploads',
        deuna_app,
        deuna_app / 'app' / 'core',
        deuna_app / 'app' / 'controllers',
        deuna_app / 'app' / 'models',
        deuna_app / 'app' / 'views',
        deuna_app / 'config',
        deuna_app / 'database',
        deuna_app / 'scripts',
        deuna_app / 'sql',
    ]

    for d in dirs_to_create:
        if args.dry_run:
            print(f"  [+] Crear: {d.relative_to(target_root)}")
        else:
            d.mkdir(parents=True, exist_ok=True)
            print(f"  [+] Creado: {d.relative_to(target_root)}")

    # === Paso 2: Copiar archivos públicos a public_html/ ===
    print("\n📂 Paso 2: Copiando archivos públicos a public_html/")

    # Copy entire public/ directory contents to public_html/
    for item in os.scandir(public_src):
        src = Path(item.path)
        dst = public_html / item.name

        if item.name in ('index.php', 'router.php', 'test.php', 'debug.php'):
            # These will be generated separately (index.php and router.php with modified paths)
            # Skip test.php and debug.php in production
            if item.name in ('test.php', 'debug.php'):
                print(f"  [✗] Saltar: {item.name} (no producción)")
                continue
            # index.php and router.php will be generated in Paso 4/5
            continue

        if args.dry_run:
            print(f"  [→] Copiar: {item.name}")
        else:
            if item.is_dir():
                if dst.exists():
                    shutil.rmtree(dst)
                shutil.copytree(src, dst)
            else:
                shutil.copy2(src, dst)
            files_copied += 1
            print(f"  [→] Copiado: {item.name}")

    # === Paso 3: Copiar código de aplicación a deuna_app/ ===
    print("\n📂 Paso 3: Copiando código de aplicación a deuna_app/")

    # Copy app/ directory
    if args.dry_run:
        for root, dirs, files in os.walk(app_src):
            for f in files:
                rel = Path(root).relative_to(app_src)
                dst = deuna_app / 'app' / rel / f
                print(f"  [→] Copiar: app/{'/' if str(rel) else ''}{rel}/{f}")
    else:
        # Clean destination
        if (deuna_app / 'app').exists():
            shutil.rmtree(deuna_app / 'app')
        shutil.copytree(app_src, deuna_app / 'app')
        for root, dirs, files in os.walk(deuna_app / 'app'):
            files_copied += len(files)
        print(f"  [→] Copiado: app/ ({files_copied} archivos)")

    # Copy config/
    if config_src.exists():
        if args.dry_run:
            print("  [→] Copiar: config/")
        else:
            if (deuna_app / 'config').exists():
                shutil.rmtree(deuna_app / 'config')
            shutil.copytree(config_src, deuna_app / 'config')
            files_copied += len(os.listdir(deuna_app / 'config'))
            print("  [→] Copiado: config/")

    # Copy scripts/
    if scripts_src.exists():
        if args.dry_run:
            print("  [→] Copiar: scripts/")
        else:
            if (deuna_app / 'scripts').exists():
                shutil.rmtree(deuna_app / 'scripts')
            shutil.copytree(scripts_src, deuna_app / 'scripts')
            print("  [→] Copiado: scripts/")

    # Copy sql/
    if sql_src.exists():
        if args.dry_run:
            print("  [→] Copiar: sql/")
        else:
            if (deuna_app / 'sql').exists():
                shutil.rmtree(deuna_app / 'sql')
            shutil.copytree(sql_src, deuna_app / 'sql')
            print("  [→] Copiado: sql/")

    # Copy database/ if exists (usually empty or with .gitkeep)
    if database_src.exists():
        if args.dry_run:
            print("  [→] Copiar: database/")
        else:
            if (deuna_app / 'database').exists():
                shutil.rmtree(deuna_app / 'database')
            shutil.copytree(database_src, deuna_app / 'database')
            print("  [→] Copiado: database/")

    # === Paso 4: Generar public_html/index.php (modificado) ===
    print("\n📝 Paso 4: Generando public_html/index.php (paths adaptados)")

    index_content = '''<?php
/**
 * DeUna - Front Controller (versión Domcloud)
 * Punto de entrada principal de la aplicación
 *
 * Esta versión está adaptada para Domcloud (hosting compartido).
 * El código de la aplicación reside en ../deuna_app/ y los archivos
 * públicos en public_html/ (document root).
 */

// Cargar configuración
require_once dirname(__DIR__) . '/deuna_app/config/config.php';

// Rutas base de la aplicación
$basePath = dirname(__DIR__) . '/deuna_app/';

// Cargar clases del framework en orden de dependencia
require_once $basePath . 'app/core/Database.php';
require_once $basePath . 'app/core/Session.php';
require_once $basePath . 'app/core/Helper.php';
require_once $basePath . 'app/core/Auth.php';
require_once $basePath . 'app/core/Controller.php';
require_once $basePath . 'app/core/Model.php';
require_once $basePath . 'app/core/QRGen.php';
require_once $basePath . 'app/core/App.php';
require_once $basePath . 'app/core/Autoloader.php';

// Iniciar sesión (con nombre de sesión personalizado)
Session::start();

// Inicializar base de datos (auto-migración en primer request)
Database::getInstance();

// Despachar la petición
$app = new App();
'''

    if args.dry_run:
        print("  [+] Generar: public_html/index.php")
    else:
        (public_html / 'index.php').write_text(index_content)
        print("  [+] Generado: public_html/index.php")

    # === Paso 5: Generar public_html/router.php (modificado) ===
    print("\n📝 Paso 5: Generando public_html/router.php (adaptado)")

    router_content = '''<?php
/**
 * DeUna - Router para PHP built-in server (versión Domcloud)
 * Reescribe todas las URLs no estáticas a index.php
 *
 * En Domcloud, el document root es public_html/.
 * En desarrollo local, el document root es public/.
 * Este router funciona en ambos entornos.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ext = pathinfo($uri, PATHINFO_EXTENSION);

// Servir archivos estáticos directamente
if ($ext !== '' && file_exists(__DIR__ . '/' . $uri)) {
    return false;
}

// Parsear URL path y establecer $_GET['url']
// ej: /paquetes/crear/123 → $_GET['url'] = 'paquetes/crear/123'
$uri = ltrim($uri, '/');
if ($uri !== '') {
    $_GET['url'] = $uri;
}

// Cargar el front controller
// En Domcloud: ../deuna_app/public/index.php (NOTA: el index.php original está en deuna_app/public/)
// Si deuna_app/public/index.php no existe, cargar index.php desde el mismo directorio
$indexPaths = [
    dirname(__DIR__) . '/deuna_app/public/index.php',  // Domcloud
    __DIR__ . '/index.php',                              // Alternativo (si index.php está en public_html/)
];

$indexFound = false;
foreach ($indexPaths as $indexPath) {
    if (file_exists($indexPath)) {
        require $indexPath;
        $indexFound = true;
        break;
    }
}

if (!$indexFound) {
    http_response_code(500);
    echo 'Error: No se encontró el front controller';
    exit;
}
'''

    if args.dry_run:
        print("  [+] Generar: public_html/router.php")
    else:
        (public_html / 'router.php').write_text(router_content)
        print("  [+] Generado: public_html/router.php")

    # === Paso 6: Generar deuna_app/public/index.php ===
    print("\n📝 Paso 6: Generando deuna_app/public/index.php (para router)")

    # The original index.php content (copy from project)
    original_index = project_root / 'public' / 'index.php'
    if original_index.exists():
        index_content_orig = original_index.read_text()
    else:
        # Fallback: use our modified version
        index_content_orig = index_content

    if args.dry_run:
        print("  [+] Generar: deuna_app/public/index.php (copia original)")
    else:
        (deuna_app / 'public').mkdir(parents=True, exist_ok=True)
        (deuna_app / 'public' / 'index.php').write_text(index_content_orig)
        print("  [+] Generado: deuna_app/public/index.php")

    # === Paso 7: Generar .htaccess ===
    print("\n📝 Paso 7: Generando archivos .htaccess")

    # public_html/.htaccess
    htaccess_public = '''# Activar rewrite engine
RewriteEngine On

# Si el archivo o directorio existe, servirlo directamente
RewriteCond %{REQUEST_FILENAME} -s [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^.*$ - [L]

# Reescribir todo lo demás a index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Negociar acceso a archivos sensibles
<FilesMatch "\\.(env|htaccess|ini|log|sql|db)$">
    Require all denied
</FilesMatch>

# Headers de seguridad
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Cache para assets estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>
'''

    if args.dry_run:
        print("  [+] Generar: public_html/.htaccess")
    else:
        (public_html / '.htaccess').write_text(htaccess_public)
        print("  [+] Generado: public_html/.htaccess")

    # deuna_app/.htaccess (block access)
    htaccess_app = '''# Bloquear acceso directo al directorio de aplicación
Require all denied
'''
    if args.dry_run:
        print("  [+] Generar: deuna_app/.htaccess")
    else:
        (deuna_app / '.htaccess').write_text(htaccess_app)
        print("  [+] Generado: deuna_app/.htaccess")

    # === Paso 8: Checklist de verificación ===
    print("\n📋 Paso 8: Generando checklist de verificación")

    checklist = f"""# Checklist de Despliegue en Domcloud

Ruta de despliegue: {target_root}

## Estructura de archivos
- [ ] public_html/ contiene: index.php, router.php, .htaccess, css/, js/, assets/
- [ ] deuna_app/ contiene: app/, config/, database/, scripts/, sql/, .htaccess
- [ ] database/ está vacío (se crea automáticamente)

## Permisos
- [ ] deuna_app/database/ → chmod 775 (escribible)
- [ ] public_html/ → chmod 755
- [ ] public_html/* archivos → chmod 644
- [ ] deuna_app/config/config.php → chmod 644
- [ ] deuna_app/.htaccess bloquea acceso público
- [ ] public_html/.htaccess tiene RewriteEngine On

## Configuración
- [ ] deuna_app/config/config.php con DB_PATH correcta
- [ ] public_html/index.php referencia a deuna_app/config/config.php
- [ ] public_html/router.php referencia a deuna_app/public/index.php

## Verificación
- [ ] php -v (8.1+)
- [ ] php -m | grep pdo_sqlite
- [ ] php -m | grep gd
- [ ] python3 -c "import qrcode"
- [ ] https://tudominio.com/login carga
- [ ] Login: admin / admin123 funciona
- [ ] Registrar paquete genera código M-N + QR
- [ ] /entrega/{{token}} accesible sin login
- [ ] API /api/buscar?q=M-1 devuelve JSON

## Seguridad
- [ ] HTTPS activado (SSL/TLS en cPanel)
- [ ] Credenciales admin cambiadas
- [ ] deuna_app/ bloqueado vía .htaccess
- [ ] SQL files bloqueados vía .htaccess
"""

    if args.dry_run:
        print("  [+] Generar: deploy_checklist.txt")
    else:
        (target_root / 'deploy_checklist.txt').write_text(checklist)
        print("  [+] Generado: deploy_checklist.txt")

    # === Resumen ===
    print(f"\n{'='*60}")
    print(f"✅ Despliegue para Domcloud listo en: {target_root}")
    print(f"{'='*60}")
    print(f"📁 Estructura generada:")
    print(f"   public_html/  ← Document root (subir al servidor)")
    print(f"   deuna_app/    ← Código de aplicación (fuera de public_html)")
    print(f"   deploy_checklist.txt ← Lista de verificación")
    print(f"\n📤 Próximos pasos:")
    print(f"   1. Subir todo el contenido a tu hosting Domcloud")
    print(f"   2. public_html/ → subir a /public_html/ del servidor")
    print(f"   3. deuna_app/ → subir a /home/tu_usuario/deuna_app/")
    print(f"   4. chmod 775 deuna_app/database/")
    print(f"   5. Acceder a https://tudominio.com/login")
    print(f"   6. Revisar docs/DESpliegue_Domcloud.md para detalles")


if __name__ == '__main__':
    main()
