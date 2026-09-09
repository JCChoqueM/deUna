# Despliegue de DeUna en Domcloud (Hosting Compartido)

Esta guía detalla los pasos para desplegar la aplicación DeUna en un hosting compartido de Domcloud. Domcloud utiliza cPanel como panel de control y `public_html/` como directorio público, lo que requiere adaptar la estructura de la aplicación que normalmente usa `public/` como raíz pública.

## Requisitos previos

| Requisito | Versión mínima | Verificación |
|-----------|----------------|-------------|
| PHP | 8.1+ (ideal 8.5) | `php -v` |
| Extensiones PHP | `gd`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `sodium`, `intl` | `php -m` |
| Python 3 | 3.6+ | `python3 --version` |
| Librerías Python | `qrcode`, `Pillow`, `pyzbar` | `python3 -c "import qrcode; import pyzbar; from PIL import Image"` |
| Acceso | cPanel, FTP/SFTP o File Manager | — |

## Paso 1: Crear la estructura de directorios

Domcloud usa `public_html/` como document root. La estructura recomendada separa los archivos públicos de los archivos de aplicación:

```
/home/tu_usuario/
├── public_html/          ← Document root (solo archivos públicos)
│   ├── css/
│   ├── js/
│   ├── assets/
│   ├── index.php         ← Front controller modificado
│   └── router.php        ← Router modificado
├── deuna_app/            ← Aplicación (fuera del alcance público)
│   ├── app/
│   │   ├── core/
│   │   ├── controllers/
│   │   ├── models/
│   │   └── views/
│   ├── config/
│   │   └── config.php    ← Configuración
│   ├── database/         ← SQLite (auto-creada)
│   ├── scripts/
│   │   └── generate_qr.py
│   ├── sql/
│   │   └── init.sql
│   └── vendor/           ← Dependencias (vacío, no usado)
```

> **Importante:** La aplicación debe estar dividida entre `public_html/` (lo que el servidor web puede servir directamente) y `deuna_app/` (código PHP, base de datos, scripts).

## Paso 2: Configurar PHP

1. Accede al **cPanel** de Domcloud
2. Busca el ícono **Select PHP Version** (o **MultiPHP Manager**)
3. Selecciona **PHP 8.1 o superior** (8.5 ideal, mínimo 8.1)
4. Activa las siguientes extensiones:
   - `gd` — Generación de imágenes QR (fallback PHP)
   - `pdo_sqlite` — Conexión a la base de datos SQLite
   - `sqlite3` — Funciones SQLite adicionales
   - `mbstring` — Manipulación de strings multibyte
   - `openssl` — Funciones criptográficas
   - `sodium` — Funciones de seguridad
   - `intl` — Funciones internacionalizadas
5. Aumenta los siguientes valores en **Select PHP Options**:
   - `memory_limit` = `256M`
   - `max_execution_time` = `60`
   - `upload_max_filesize` = `10M` (opcional, para uploads)
   - `post_max_size` = `10M`

## Paso 3: Configurar Python 3 y dependencias QR

Domcloud incluye Python 3. Verifica con:

```bash
# Accede vía SSH o usa la Terminal del cPanel
python3 --version
python3 -c "import qrcode; print('qrcode OK')"
python3 -c "import pyzbar; print('pyzbar OK')"
python3 -c "from PIL import Image; print('Pillow OK')"
```

### Instalar dependencias si faltan

```bash
# Instalar pip3 si no está disponible
python3 -m ensurepip --user

# Instalar librerías QR
pip3 install --user qrcode[pil] pyzbar
```

> **Nota:** Si Python o `qrcode` no están disponibles, el sistema automáticamente usará el fallback PHP GD (activar `extension=gd` en PHP). Los QR funcionarán pero el server-side generará PNG con GD en lugar de Python.

## Paso 4: Subir archivos al servidor

### Opción A: vía FTP (FileZilla / WinSCP)

1. Conéctate al servidor vía **FTP** con tus credenciales de Domcloud
2. Navega a `/public_html/` — este es tu document root
3. Sube el **contenido de la carpeta `public/`** a `public_html/`
4. Vuelve al directorio raíz (`/`) o `/home/tu_usuario/`
5. Crea la carpeta `deuna_app/`
6. Sube todo el proyecto **excepto la carpeta `public/`** a `deuna_app/`

### Opción B: vía File Manager del cPanel

1. Accede al **File Manager** del cPanel → `public_html`
2. Sube el archivo `.zip` del proyecto a la raíz (`/home/tu_usuario/`)
3. Extrae el zip desde el File Manager
4. Mueve los archivos de `public/` a `public_html/`:
   ```
   cp -r proyecto/public/* public_html/
   ```
5. Mueve el código de la aplicación a `deuna_app/`:
   ```
   mkdir -p deuna_app
   mv proyecto/app/ deuna_app/
   mv proyecto/config/ deuna_app/
   mv proyecto/database/ deuna_app/  (o créalo si no existe)
   mv proyecto/scripts/ deuna_app/
   mv proyecto/sql/ deuna_app/
   ```
6. Limpia los archivos temporales:
   ```
   rm -rf proyecto/
   ```

### Opción C: vía Terminal del cPanel (SSH)

```bash
cd /home/tu_usuario

# Crear directo el zip desde tu máquina local:
# zip -r deuna.zip . -x "database/*" ".git/*" "vendor/*"

# Subir y extraer
unzip deuna.zip -d deuna_temp/

# Mover archivos públicos a public_html
cp -r deuna_temp/public/* public_html/

# Mover aplicación a deuna_app
mkdir -p deuna_app
cp -r deuna_temp/app/ deuna_app/
cp -r deuna_temp/config/ deuna_app/
cp -r deuna_temp/scripts/ deuna_app/
cp -r deuna_temp/sql/ deuna_app/
mkdir -p deuna_app/database

# Limpiar
rm -rf deuna_temp/ deuna.zip
```

## Paso 5: Configurar paths (archivos de conexión)

Debido a que la estructura se divide entre `public_html/` (público) y `deuna_app/` (aplicación), es necesario ajustar las rutas en varios archivos.

### 5.1: `deuna_app/config/config.php`

```php
<?php
/**
 * DeUna - Configuration (versión Domcloud)
 */

define('APP_NAME', 'DeUna');
define('APP_VERSION', '1.0.0');

// Database path: deuna_app/database/deuna.db
// dirname(__DIR__) desde config/ = deuna_app/
define('DB_PATH', dirname(__DIR__) . '/database/deuna.db');

// Application base URL
define('BASE_URL', '/');

// Upload path (para archivos subidos)
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');

// Session configuration
define('SESSION_NAME', 'deuna_session');
define('SESSION_TIMEOUT', 7200);

// Default admin credentials (CAMBIAR después del primer login)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'cambiar_esta_contraseña_segura');

// Pagination
define('ITEMS_PER_PAGE', 20);
```

### 5.2: `public_html/index.php`

```php
<?php
/**
 * DeUna - Front Controller (versión Domcloud)
 * Carga el index.php original desde deuna_app/
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
require_once $basePath . 'app/core/QRGen.php';
require_once $basePath . 'app/core/Controller.php';
require_once $basePath . 'app/core/Model.php';
require_once $basePath . 'app/core/App.php';
require_once $basePath . 'app/core/Autoloader.php';

// Iniciar sesión
Session::start();

// Inicializar base de datos (auto-migración)
Database::getInstance();

// Despachar la petición
$app = new App();
```

### 5.3: `public_html/router.php`

```php
<?php
/**
 * DeUna - Router para Domcloud
 * Sirve archivos estáticos y reescribe dinámicas a index.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ext = pathinfo($uri, PATHINFO_EXTENSION);

// Servir archivos estáticos directamente si existen
if ($ext !== '' && file_exists(__DIR__ . '/' . $uri)) {
    return false;
}

// Parsear URL path y establecer $_GET['url']
// ej: /paquetes/crear/123 → $_GET['url'] = 'paquetes/crear/123'
$uri = ltrim($uri, '/');
if ($uri !== '') {
    $_GET['url'] = $uri;
}

// Cargar front controller desde deuna_app/
require dirname(__DIR__) . '/deuna_app/public/index.php';
```

### 5.4: `deuna_app/app/core/App.php`

Verifica que las rutas usen `require` (no `require_once`) y que la ruta al directorio de core sea correcta:

```php
// app/core/App.php — no necesita cambios si Autoloader carga bien
// El Autoloader resuelve desde app/core/ hacia el proyecto root
// con dirname(__DIR__, 2) = deuna_app/
```

### 5.5: `deuna_app/app/core/QRGen.php`

El script de generación de QR se busca usando `dirname(__DIR__, 2)`:

```php
// QRGen.php — resuelve desde app/core/ hacia deuna_app/
$script = dirname(__DIR__, 2) . '/scripts/generate_qr.py';
// dirname(__DIR__, 2) desde app/core/ = deuna_app/ ✓
```

### 5.6: `deuna_app/app/core/Autoloader.php`

```php
// Autoloader.php — root path
$root = dirname(__DIR__, 2); // De app/core/ a deuna_app/ ✓
```

## Paso 6: Configurar .htaccess

Crea el archivo `public_html/.htaccess`:

```apache
# Activar rewrite engine
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
<FilesMatch "\.(env|htaccess|ini|log|sql|db)$">
    Require all denied
</FilesMatch>

# Headers de seguridad básicos
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
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
</IfModule>
```

### .htaccess en directorios sensibles

Crea `deuna_app/.htaccess` para bloquear acceso directo:

```apache
# Bloquear acceso directo a la aplicación
Require all denied
```

## Paso 7: Configurar permisos

Domcloud ejecuta PHP bajo el usuario de la cuenta (no `www-data`). Los permisos deben ajustarse:

```bash
# Vía SSH o Terminal del cPanel
cd /home/tu_usuario

# Directorio de base de datos — necesita ser escribible
chmod 775 deuna_app/database
# Si tienes problemas: chmod 777 deuna_app/database (solo temporal)

# Directorio de uploads
mkdir -p public_html/uploads
chmod 775 public_html/uploads

# Directorio de scripts (Python)
chmod 755 deuna_app/scripts

# Archivos públicos
chmod 644 public_html/index.php
chmod 644 public_html/router.php
find public_html/css -exec chmod 644 {} \;
find public_html/js -exec chmod 644 {} \;

# Configuración (debe ser legible pero no modificable vía web)
chmod 644 deuna_app/config/config.php

# SQL (no debe ser accesible vía web — bloqueado por .htaccess)
chmod 644 deuna_app/sql/init.sql
```

> **Solución de problemas de permisos:** Si recibes "500 Internal Server Error", verifica que el archivo `deuna_app/config/config.php` sea legible y que `deuna_app/database/` sea escribible.

## Paso 8: Iniciar la base de datos

La base de datos SQLite se crea automáticamente en el primer request. Pero también puedes crearla manualmente:

```bash
# Vía Terminal del cPanel o SSH
cd /home/tu_usuario/deuna_app

php -r "
require 'config/config.php';
require 'app/core/Database.php';
Database::getInstance();
Database::migrate();
Database::seed();
echo 'Base de datos creada correctamente.' . PHP_EOL;
"

# Verificar
ls -la database/deuna.db
```

> **Nota:** Si la base de datos se crea manualmente, asegúrate de que pertenezca al usuario correcto:
> ```bash
> chown tu_usuario:tu_usuario database/deuna.db
> chmod 644 database/deuna.db
> ```

## Paso 9: Configurar HTTPS

Domcloud ofrece certificados SSL gratuitos (Let's Encrypt):

1. En el cPanel, busca **SSL/TLS Status** (o **Manage SSL Sites**)
2. Marca tu dominio/subdominio
3. Haz clic en **Autorepair SSL** (o **Install SSL**)
4. El certificado se instala automáticamente

### Verificar HTTPS

```bash
# Acceder via HTTPS
curl -I https://tudominio.com/login

# Verificar certificado
openssl s_client -connect tudominio.com:443 -servername tudominio.com
```

> **Importante:** Si accedes por HTTP, el sistema funcionará pero los QR generarán URLs `http://` en lugar de `https://`. Configura HTTPS para que los QR usen `https://`.

## Paso 10: Verificación del despliegue

1. **Accede a la aplicación:**
   ```
   https://tudominio.com/login
   ```

2. **Haz login:**
   - Usuario: `admin`
   - Password: `admin123`

3. **Verifica funcionalidades clave:**
   - ✅ Dashboard carga y muestra estadísticas
   - ✅ Registrar paquete → genera código M-N + QR
   - ✅ Lista de paquetes muestra el paquete nuevo
   - ✅ Detalle del paquete → muestra vendedor, comprador, autorizado, historial
   - ✅ Entrega rápida → busca por código, registra pago y entrega
   - ✅ Reportes → muestra ingresos y estadísticas
   - ✅ Configuración → modifica tarifas y guarda
   - ✅ Imprimir etiqueta → pagina con QR imprimible
   - ✅ QR público → accede `/entrega/{token}` sin login
   - ✅ API → `/api/buscar?q=M-` devuelve JSON

4. **Verifica QR escanable:**
   ```bash
   curl -o /tmp/qr.png "https://tudominio.com/qr-code/{token}"
   # Verificar que es un PNG válido:
   file /tmp/qr.png
   ```

---

## Problemas comunes en Domcloud

| Problema | Causa | Solución |
|----------|-------|----------|
| `500 Internal Server Error` | `.htaccess` mal configurado | Verifica RewriteEngine On y RewriteRule |
| `Class 'App' not found` | Paths incorrectos en `index.php` | Verifica `dirname(__DIR__) . '/deuna_app/'` |
| `Class not found` en Autoloader | `$root` apunta a lugar incorrecto | `dirname(__DIR__, 2)` debe = `deuna_app/` |
| QR no se genera | Python 3 o `qrcode` no disponible | Usa fallback GD: activa `extension=gd` en PHP |
| `no such column: fecha_actualizacion` | BD con esquema antiguo | Borra `database/deuna.db` y deja que se recompile |
| `Permission denied` en BD | `database/` no escribible | `chmod 775 deuna_app/database/` o `chmod 777` (temporal) |
| Sesión no persiste | `/tmp` no configurable en shared hosting | PHP usa `/tmp` por defecto — funciona bien en Domcloud |
| `could not open database` | Ruta DB_PATH incorrecta | Verifica `dirname(__DIR__) . '/database/deuna.db'` en config |
| QR público no carga | `REQUEST_SCHEME` no definido en shared hosting | Ya corregido en `Helper::getPackageUrl()` con fallback `'http'` |
| Archivos estáticos no cargan | RewriteRule atenúa assets | Asegúrate de que CSS/JS están en `public_html/css/` y `/js/` |
| `Call to undefined function mb_strtoupper()` | Extensión `mbstring` no activa | Actívala en Select PHP Version → Extensions |

## Checklist de despliegue en Domcloud

- [ ] **PHP 8.1+** con extensiones (gd, pdo_sqlite, sqlite3, mbstring, openssl, sodium, intl) configuradas en cPanel
- [ ] **Python 3** con `qrcode`, `Pillow`, `pyzbar` verificadas (o fallback GD listo)
- [ ] **Estructura de directorios**: `public_html/` con archivos públicos + `deuna_app/` con código aplicación
- [ ] **`config/config.php`** actualizado con rutas correctas (`dirname(__DIR__) . '/database/deuna.db'`)
- [ ] **`public_html/index.php`** con `require_once dirname(__DIR__) . '/deuna_app/config/config.php'`
- [ ] **`public_html/router.php`** con `require dirname(__DIR__) . '/deuna_app/public/index.php'`
- [ ] **`public_html/.htaccess`** con RewriteEngine + bloqueo de archivos sensibles
- [ ] **`deuna_app/.htaccess`** bloqueando acceso directo al directorio
- [ ] **Permisos**: `database/` = 775 (escribible), archivos estáticos = 644, PHP = 644
- [ ] **Base de datos** creada y migrada (verifica con `sqlite3 database/deuna.db ".schema"`)
- [ ] **HTTPS** activado vía SSL/TLS Status del cPanel
- [ ] **Credenciales admin** cambiadas después del primer login (admin → Usuarios → Editar)
- [ ] **Verificado**: login, registro de paquete, generación de QR, búsqueda, pago, entrega, reportes
- [ ] **QR público** accesible sin login: `https://tudominio.com/entrega/{token}`

## Consideraciones de rendimiento en Domcloud

### Límites de recursos compartidos

Domcloud es hosting compartido, por lo que tiene límites:

| Límite | Valor típico | Impacto |
|--------|-------------|---------|
| `max_execution_time` | 60s | Regeneración de QR o reportes grandes |
| `memory_limit` | 256M | Procesamiento de imágenes |
| `max_input_vars` | 1000 | Formularios con muchos campos |
| `post_max_size` | 10M | Uploads de archivos |

### Cacheo

- **Assets estáticos**: El `.htaccess` configura cache de 1 año para CSS/JS/imágenes
- **SQLite**: No hay cacheo de consultas, pero las consultas son simples y rápidas
- **QR codes**: El PNG se genera por request, pero el tiempo es ~50-100ms

### Backup

Domcloud incluye backup automático diario. Recomienda también:

```bash
# Backup manual de la base de datos (vía cPanel Terminal o cron)
cp /home/tu_usuario/deuna_app/database/deuna.db /home/tu_usuario/backups/deuna_$(date +%Y%m%d).db

# O vía PHP:
php -r "copy('/home/tu_usuario/deuna_app/database/deuna.db', '/home/tu_usuario/backups/deuna_' . date('Ymd') . '.db');"
```

## Seguridad en Domcloud

- ✅ Contraseñas con `password_hash()` (bcrypt)
- ✅ Prepared statements en todas las consultas (PDO)
- ✅ Tokens QR criptográficamente seguros (`random_bytes(16)`)
- ✅ Sesiones con nombre personalizado y timeout de 2 horas
- ✅ `.htaccess` bloqueando acceso a `deuna_app/`, SQL, `.env`
- ✅ CSRF tokens en todos los formularios POST
- ✅ Headers de seguridad en `.htaccess` (nosniff, X-Frame-Options, XSS-Protection)
- ⚠️ **Cambiar la contraseña admin** después del primer login
- ⚠️ **Verificar backups** periódicos de `database/deuna.db`
