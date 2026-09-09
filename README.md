# DeUna — Sistema de Recepción, Custodia y Entrega de Paquetes

Aplicación web MVC para la gestión de paquetería en puntos DeUna. Permite registrar paquetes con código visible (M-N) y código QR, gestionar vendedores, compradores y autorizados, aplicar la regla de 7 días con comisión por atraso, procesar pagos y entregas, y generar reportes.

---

## Tabla de Contenidos

- [Cómo correrlo (Quick Start)](#cómo-correrlo-quick-start)
- [Tecnologías Usadas y Por Qué](#tecnologías-usadas-y-por-qué)
- [Arquitectura MVC](#arquitectura-mvc)
- [Estructura de Directorios](#estructura-de-directorios)
- [Base de Datos](#base-de-datos)
- [Reglas de Negocio](#reglas-de-negocio)
- [Flujo de Involucrados](#flujo-de-involucrados)
- [Guía de Uso](#guía-de-uso)
- [QR Codes](#qr-codes)
- [API Endpoints](#api-endpoints)
- [Configuración](#configuración)
- [Despliegue a Producción](#despliegue-a-producción)
  - [Domcloud (Hosting Compartido)](#opción-d-domcloud-hosting-compartido)
- [Desarrollo](#desarrollo)

---

## Cómo correrlo (Quick Start)

### Prerrequisitos

- PHP 8.5+ con extensiones: `gd`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `sodium`, `intl`
- Python 3 con librerías: `qrcode`, `Pillow`, `pyzbar`
- No se requiere Composer ni internet (todo funciona offline)

### Arrancar el servidor

```bash
cd /home/ddr-3/Proyectos/deUna
php -S localhost:8000 -t public public/router.php
```

> **Importante:** Use `-S` en **mayúscula**. El flag `-s` en minúscula activa el modo de resaltado de sintaxis.

### Credenciales por defecto

| Usuario | Password   | Rol      |
|---------|------------|----------|
| admin   | admin123   | admin    |

La base de datos se crea automáticamente en `database/deuna.db` con migraciones y datos semilla en el primer request. Abre `http://localhost:8000` y haz login.

---

## Tecnologías Usadas y Por Qué

| Tecnología | Capa | Por qué se usó |
|------------|------|----------------|
| **PHP 8.5** | Backend / lenguaje principal | Lenguaje de programación del SRS. PHP 8.5 soporta `readonly` properties, enums, match expressions. Corre sin framework externo. |
| **SQLite + PDO** | Base de datos | Sin necesidad de instalar MySQL/PostgreSQL. Archivo único (`database/deuna.db`). PDO con prepared statements previene inyección SQL. Ideal para despliegue sin infraestructura adicional. |
| **Python 3** | Generación de QR (server-side) | La librería `qrcode` de Python genera códigos QR verdaderamente escanables (verificados con `pyzbar`). Composer no tenía acceso a Packagist, y las implementaciones puras de PHP tenían bugs de sintaxis en PHP 8.5. |
| **librería `qrcode` (Python)** | QR generation | Genera matrices QR conforme a la especificación. Usada por `scripts/generate_qr.py`. |
| **`pyzbar` (Python)** | Verificación QR | Decodifica y verifica que los QR son escanables. |
| **`Pillow` (Python)** | Manipulación de imágenes | Backend de `qrcode` para renderizar el PNG. |
| **PHP GD** | QR fallback (server-side) | Implementación pura PHP usando la extensión `gd` (instalada). Se usa como respaldo si Python falla. |
| **HTML5** | Frontend / markup | Estructura semántica de todas las vistas. No se usa framework frontend. |
| **CSS3 (sin framework)** | Estilos | `public/css/style.css` (690 líneas). Variables CSS personalizadas, grid/flexbox, responsive. Sin Bootstrap ni Tailwind — cero dependencias externas. |
| **JavaScript (sin framework)** | Interactividad | `public/js/main.js`, `app.js`, `validations.js`. DOM nativo, fetch API, validaciones en tiempo real. |
| **qrcode.min.js (42KB)** | QR cliente | Biblioteca `qrcode` empaquetada vía browserify+terser. Permite renderizar QR en el navegador sin requests al servidor. Cero dependencias. |
| **PHP built-in server** | Servidor de desarrollo | `php -S localhost:8000 -t public public/router.php`. No necesita nginx/apache. |
| **password_hash() / password_verify()** | Seguridad auth | Bcrypt/Argon2 para hashing de contraseñas. |
| **bin2hex(random_bytes(16))** | Tokens seguros | Genera tokens criptográficamente seguros para QR (32 hex chars). |

### Tecnologías NO usadas y por qué

| Tecnología | Razón |
|------------|-------|
| **Composer / librerías PHP externas** | No hay acceso a Packagist (sin internet). `composer require chillerlan/qr-code` falló con "Could not find matching version". |
| **CDN (jsdelivr, etc.)** | Sin acceso a internet. `curl` a jsDelivr devolvió 404. |
| **Bootstrap / Tailwind** | Para mantener cero dependencias y control total del CSS. El stylesheet es de 690 líneas hecho a mano. |
| **React/Vue/Angular** | No se necesita reactividad compleja. El UI es suficientemente simple para JavaScript nativo. |
| **MySQL / PostgreSQL** | Requeriría instalación y configuración adicional. SQLite es suficiente y más portátil. |

---

## Arquitectura MVC

La aplicación sigue el patrón **Model-View-Controller** sin frameworks externos de PHP.

### Núcleo (`app/core/`)

| Clase | Descripción |
|-------|-------------|
| `App` | Router frontal. Parsea la URL, resuelve el controlador/método, verifica autenticación. |
| `Controller` | Controlador base. Proporciona `model()`, `view()`, `redirect()`. |
| `Model` | Modelo base. CRUD genérico (`all`, `find`, `where`, `create`, `update`, `delete`, `query`). |
| `Database` | Singleton PDO/SQLite. `migrate()` crea tablas desde `sql/init.sql` (solo si no existen). `seed()` inserta admin y configuración por defecto. |
| `Auth` | Gestión de autenticación. Login con `password_verify()`, roles (admin/operador), `canAccess()`. |
| `Session` | Manejo de sesiones con nombre personalizado (`deuna_session`), timeout de 2 horas, flash messages. |
| `Helper` | Funciones auxiliares: `config()`, `generateToken()`, `generateVisibleCode()`, `calculateLateCommission()`, `formatMoney()`, `getPackageUrl()`, `csrfToken()`. |
| `QRGen` | Generador de códigos QR. Usa Python (`scripts/generate_qr.py`) como backend y GD como fallback. Produce PNG binario, data URI, o archivos. |
| `Autoloader` | `spl_autoload_register` para cargar automáticamente clases de core, models, y controllers. |

### Mapa de rutas

```
/login          → AuthController@login
/logout         → AuthController@logout
/register       → AuthController@register
/dashboard      → DashboardController@index
/paquetes       → PaqueteController@index
/paquetes/crear → PaqueteController@crear
/paquetes/guardar → PaqueteController@guardar
/paquetes/detalle/{id} → PaqueteController@detalle
/paquetes/entrega → PaqueteController@entrega
/paquetes/entregar/{id} → PaqueteController@entregar
/paquetes/pago/{id} → PaqueteController@pago
/paquetes/imprimir/{id} → PaqueteController@imprimir
/paquetes/confirmacion/{id} → PaqueteController@confirmacion
/qr-code/{token} → PaqueteController@qrCode (público)
/entrega/{token} → PaqueteController@entregaPublica (público)
/api/paquete    → PaqueteController@apiBuscar (público)
/api/buscar     → PaqueteController@apiBuscarLista (público)
/usuarios       → UsuarioController@index
/usuarios/editar/{id} → UsuarioController@editar
/configuracion  → ConfiguracionController@index
/reportes       → ReporteController@index
```

**Rutas públicas** (sin autenticación): `qrCode`, `entregaPublica`, `apiBuscar`, `apiBuscarLista`. Todas las demás requieren autenticación.

---

## Estructura de Directorios

```
deUna/
├── app/
│   ├── core/              # Framework MVC
│   │   ├── App.php        # Router
│   │   ├── Auth.php       # Autenticación
│   │   ├── Autoloader.php
│   │   ├── Controller.php # Controlador base
│   │   ├── Database.php   # Singleton PDO/SQLite
│   │   ├── Helper.php     # Utilidades
│   │   ├── Model.php      # Modelo base
│   │   ├── QRGen.php      # Generador QR (Python + GD)
│   │   ├── Session.php    # Sesiones
│   │   └── QRCode.php     # Clase auxiliar QR (no usada principalmente)
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── ConfiguracionController.php
│   │   ├── DashboardController.php
│   │   ├── PaqueteController.php
│   │   ├── ReporteController.php
│   │   └── UsuarioController.php
│   ├── models/
│   │   ├── Autorizado.php
│   │   ├── Comprador.php
│   │   ├── Configuracion.php
│   │   ├── Historial.php
│   │   ├── Pago.php
│   │   ├── Paquete.php
│   │   ├── Usuario.php
│   │   └── Vendedor.php
│   └── views/
│       ├── templates/     # header.php, footer.php
│       ├── auth/          # login.php, register.php
│       ├── dashboard/     # index.php
│       ├── paquetes/      # index, create, confirm, show, entrega, pago, print
│       ├── reportes/      # index.php
│       ├── configuracion/ # index.php
│       └── usuarios/      # index.php, editar.php
├── config/
│   └── config.php         # Constantes de configuración
├── database/
│   └── deuna.db           # SQLite (auto-creada)
├── public/
│   ├── css/style.css      # Estilos
│   ├── js/
│   │   ├── main.js        # Funcionalidad principal
│   │   ├── app.js         # Lógica de la aplicación
│   │   ├── validations.js # Validaciones del lado cliente
│   │   ├── qrcode.min.js  # Biblioteca QR cliente (42KB)
│   │   └── qrcode-bundle.js
│   ├── assets/
│   ├── index.php          # Front controller
│   ├── router.php         # Router para PHP built-in server
│   └── uploads/
├── scripts/
│   └── generate_qr.py     # Generador QR en Python
├── sql/
│   └── init.sql           # Esquema de BD
├── composer.json
├── DeUna.txt              # Análisis del PDF de requisitos (SRS)
├── DeUna.pdf              # Documento de requisitos original
└── vendor/                # Dependencias (vacío, no usado)
```

---

## Base de Datos

### Motores y conexión

| Propiedad | Valor |
|-----------|-------|
| Motor | SQLite 3 (via PDO) |
| Ubicación | `database/deuna.db` (desde la raíz del proyecto) |
| Path absoluto | `dirname(__DIR__) . '/database/deuna.db'` definido en `config/config.php` |
| Driver PDO | `sqlite:` prefix |
| Persistencia | Singleton (una sola instancia por request) |
| FK constraints | Habilitadas (`PRAGMA foreign_keys = ON`) |

### Ruta de conexión

La ruta a la base de datos se define mediante la constante `DB_PATH` en `config/config.php`:

```php
// config/config.php
define('DB_PATH', dirname(__DIR__) . '/database/deuna.db');
```

`dirname(__DIR__)` desde `config/` resuelve a la raíz del proyecto (`/home/ddr-3/Proyectos/deUna`), por lo que la base de datos se encuentra en:

```
/home/ddr-3/Proyectos/deUna/database/deuna.db
```

### Mecánica de conexión (`Database::getInstance()`)

```php
// app/core/Database.php

self::$instance = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Errores como excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Resultados como arrays asociativos
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Prepared statements reales
]);

self::$instance->exec('PRAGMA foreign_keys = ON'); // Habilitar FK constraints
```

- **Singleton**: La primera llamada crea la conexión; llamadas subsecuentes devuelven la misma instancia.
- **Auto-creación del directorio**: Si `database/` no existe, se crea con `mkdir()`.
- **Foreign keys**: Habilitadas por defecto (`PRAGMA foreign_keys = ON`), deshabilitadas temporalmente solo durante migración.
- **Prepared statements**: Siempre se usan para todas las consultas (prevención de inyección SQL).

### Ciclo de vida por request

```
1. public/router.php
   → requiere public/index.php
2. public/index.php
   → require_once config/config.php        (define DB_PATH)
   → require_once app/core/Database.php
   → Database::getInstance()                (abre conexión PDO)
   → new App()
3. app/core/App.php (constructor)
   → parseUrl()                             (parsea $_GET['url'])
   → dispatch()
       → ensureDatabase()                  (auto-migración)
           → Database::migrate()
           → Database::seed()
       → Auth::canAccess()                 (verifica sesión)
       → new Controller()                  (abre conexión modelo)
       → controller->method()              (ejecuta caso de uso)
4. Model::__construct()
   → $this->db = Database::getInstance()   (reutiliza la misma conexión)
5. Conexión se cierra al final del script (PDO no persistente)
```

### Migración automática

```php
// App::ensureDatabase() → Database::migrate()
public static function migrate(): void
{
    $pdo = self::getInstance();
    
    // Verificar si las tablas ya existen (solo migrar una vez)
    $stmt = $pdo->query(
        "SELECT name FROM sqlite_master 
         WHERE type='table' AND name='usuarios' LIMIT 1"
    );
    if ($stmt->fetchColumn()) {
        return; // Saltar migración si ya existen
    }
    
    // Deshabilitar FK para DROP TABLE en orden arbitrario
    $pdo->exec('PRAGMA foreign_keys = OFF');
    
    // Leer sql/init.sql y ejecutar cada statement
    $sql = file_get_contents(dirname(__DIR__, 2) . '/sql/init.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    
    $pdo->exec('PRAGMA foreign_keys = ON');
}
```

### Sembrado de datos (seed)

```php
// Database::seed() — idempotente (solo inserta si no existen)
```

**Usuario admin por defecto:**
- Usuario: `admin`
- Password: `admin123` (almacenado como `password_hash()` con bcrypt)
- Nombre: `Administrador DeUna`
- Rol: `admin`

**Configuración por defecto (10 items):**

| Clave | Valor | Descripción |
|-------|-------|-------------|
| tarifa_base | 10.00 | Tarifa base por paquete |
| tipo_comision | fijo | Tipo de comisión (fijo/porcentaje) |
| monto_comision | 5.00 | Comisión fija por atraso |
| porcentaje_comision | 0.00 | Comisión porcentual |
| plazo_dias | 7 | Días de gracia |
| numeracion_tipo | continua | Tipo de numeración |
| nombre_negocio | DeUna | Nombre del negocio |
| direccion_negocio | (vacío) | Dirección |
| telefono_negocio | (vacío) | Teléfono |
| email_negocio | (vacío) | Email |

### Tablas

| Tabla | Descripción |
|-------|-------------|
| `configuracion` | Parámetros: tarifa_base, tipo_comision, monto_comision, porcentaje_comision, plazo_dias, numeracion_tipo, nombre_negocio, direccion_negocio, telefono_negocio, email_negocio |
| `usuarios` | admin/operador — nombre, usuario, password_hash, rol, estado |
| `vendedores` | Nombre, celular, observaciones, fecha_registro |
| `compradores` | Nombre completo, celular, observaciones |
| `autorizados` | Persona autorizada para recoger paquetes, relación con comprador |
| `paquetes` | Núcleo: código visible, qr_token, FK a vendedor/comprador/autorizado, fechas, estado, monto |
| `pagos` | Pago al momento de entrega: monto_base, comision_atraso, total, medio_pago, referencia |
| `historial` | Auditoría: paquete_id, accion, estado_anterior, estado_nuevo, detalle, usuario_id |

### Modelo base (`app/core/Model.php`)

Todos los modelos extienden de `Model`, que proporciona:

| Método | Descripción | Uso |
|--------|-------------|-----|
| `all($orderBy)` | Obtener todos los registros | `$model->all('fecha_recepcion DESC')` |
| `find($id)` | Buscar por ID | `$model->find(1)` |
| `where($col, $val)` | Buscar por columna (único) | `$model->where('codigo_visible', 'M-1')` |
| `whereMultiple($col, $val)` | Buscar por columna (múltiple) | `$model->whereMultiple('estado', 'Pendiente')` |
| `create($data)` | Insertar registro | `$model->create([...])` → devuelve ID |
| `update($id, $data)` | Actualizar registro | `$model->update(1, ['estado' => 'Entregado'])` |
| `delete($id)` | Eliminar registro | `$model->delete(1)` |
| `count($conditions)` | Contar registros | `$model->count(['estado' => 'Pendiente'])` |
| `query($sql, $params)` | Consulta raw con prepared statements | `$model->query("SELECT...", [$param])` |
| `scalar($sql, $params)` | Un valor escalar | `$model->scalar("SELECT COUNT(*)...")` |

### Cómo acceder desde código

```php
// Obtener conexión directamente
$db = Database::getInstance();

// Usar desde un modelo
class Paquete extends Model {
    protected string $table = 'paquetes';
}
$model = new Paquete();
$model->where('codigo_visible', 'M-1');  // Usa la misma conexión singleton

// Consulta raw con parámetros
$result = $model->query(
    "SELECT p.*, c.nombre_completo as comprador 
     FROM paquetes p 
     JOIN compradores c ON c.id = p.comprador_id 
     WHERE p.estado = ?",
    ['Pendiente']
);
```

### Verificación y mantenimiento

```bash
# Ver esquema de la base de datos
sqlite3 database/deuna.db ".schema"

# Ver datos (ej: paquetes)
sqlite3 database/deuna.db "SELECT codigo_visible, estado, fecha_recepcion FROM paquetes;"

# Verificar FK constraints
sqlite3 database/deuna.db "PRAGMA foreign_keys;"

# Forzar recreación (borrar y migrar de nuevo)
rm database/deuna.db
```

---

## Reglas de Negocio

### RN-07 — Generación de código visible (M-N)

El código visible se genera según el **anexo B** del SRS:

```
Formato: {inicial_del_comprador}-{número_secuencia}
```

- La **inicial** es la primera letra del nombre del comprador en mayúscula
- La **secuencia** se reinicia por comprador (el primer paquete de "Maria Caceres" es "M-1", el segundo "M-2")
- Ejemplo: "Maria Caceres" + 1 → **M-1**, "Carlos Ruiz" + 1 → **C-1**

### RN-08 — Regla de 7 días

- El **plazo** por defecto es de **7 días** (`plazo_dias` en configuración).
- `fecha_vencimiento = fecha_recepcion + 7 días`
- Un paquete está **vencido** si `dias_transcurridos > 7` y sigue en estado `Pendiente`.
- **Comisión por atraso:**
  - Si `tipo_comision = fijo`: se cobra `monto_comision` fijo (ej: $5.00)
  - Si `tipo_comision = porcentaje`: se cobra `porcentaje_comision` del `monto_base`
- **Total a cobrar** = `monto_base + comision_atraso`

### Estados de paquete

| Estado | Descripción |
|--------|-------------|
| `Pendiente` | Recibido, esperando entrega |
| `Entregado` | Entregado con pago registrado |
| `Anulado` | Cancelado |
| `Incidencia` | Problema reportado |

### Métodos de pago

`Efectivo`, `Tarjeta`, `Transferencia`, `Otro`

---

## Flujo de Involucrados

Los involucrados en el sistema son: **Administrador**, **Vendedor**, **Comprador**, **Autorizado** y **Paquete**. A continuación se describe el flujo de cada uno en cada caso de uso.

### Roles y responsabilidades

| Rol | Responsabilidades |
|-----|-------------------|
| **Administrador** | Gestiona usuarios, configura tarifas/plazos, ve todos los paquetes y reportes, registra y entrega paquetes. |
| **Vendedor** | Entidad que deja un paquete en el punto DeUna. Se identifica por nombre y celular. |
| **Comprador** | Destinatario del paquete. Se identifica por nombre completo y celular. El código visible (M-N) se deriva del nombre del comprador. |
| **Autorizado** | Persona autorizada por el comprador para recoger el paquete. Tiene una relación con el comprador (Familiar, Amigo, Trabajo, Comprador, Otro). |
| **Paquete** | Entidad central. Tiene un código visible (M-N), un token QR, estado, fechas, monto y ubicación. |

### Caso 1: Registro de paquete (recepción)

**Involucrados:** Administrador → Vendedor, Comprador, Autorizado → Paquete

```
1. Admin accede al formulario "Registrar Paquete"
2. Ingresa datos del vendedor:
   • Si el vendedor ya existe (mismo celular), se reutiliza
   • Si no existe, se crea automáticamente con fecha_registro
3. Ingresa datos del comprador:
   • Si el comprador ya existe (mismo celular), se reutiliza
   • Si no existe, se crea automáticamente
   • El sistema toma la inicial del nombre → va de código visible
4. Ingresa datos del autorizado:
   • Nombre, celular y relación con el comprador
   • Se crea/enlaza automáticamente
5. Ingresa descripción, monto base y observaciones
6. Sistema genera:
   • Código visible: {inicial}-{secuencia} (ej: M-1)
   • Token QR: bin2hex(random_bytes(16))
   • fecha_recepcion = datetime('now')
   • fecha_vencimiento = fecha_recepcion + 7 días
   • estado = 'Pendiente'
   • fecha_actualizacion = datetime('now')
7. Sistema registra en historial: accion='recepcion', estado_nuevo='Pendiente'
8. Sistema muestra página de confirmación con código y QR
```

### Caso 2: Búsqueda y detalle de paquete

**Involucrados:** Administrador → Paquete, Vendedor, Comprador, Autorizado, Historial

```
1. Admin navega a /paquetes (lista con filtros)
2. Busca por código visible (ej: M-1)
3. Clic en detalle → se muestra:
   • Información del paquete (código, estado, fechas, monto, descripción)
   • Información del vendedor (nombre, celular)
   • Información del comprador (nombre, celular)
   • Información del autorizado (nombre, celular, relación)
   • Tiempo transcurrido desde recepción
   • Si vencido: alerta de "¡Vencido!" + cálculo de comisión
   • Historial de auditoría (recepcion, entrega, anulacion)
```

### Caso 3: Entrega rápida por código (caja)

**Involucrados:** Administrador → Paquete → Pago, Historial

```
1. Admin navega a /paquetes/entrega
2. Ingresa el código visible del paquete (ej: M-1)
3. Sistema busca el paquete y muestra:
   • Datos del comprador y vendedor
   • Estado actual (Pendiente)
   • Si vencido: comisión por atraso calculada automáticamente
   • Formulario de pago con:
     - Monto base (precargado)
     - Comisión por atraso (si aplica)
     - Total a pagar (base + comisión)
     - Medio de pago (Efectivo, Tarjeta, Transferencia, Otro)
     - Referencia (opcional)
4. Admin confirma y registra la entrega
5. Sistema:
   • Crea un registro en pagos
   • Cambia estado del paquete a 'Entregado'
   • Actualiza fecha_actualizacion
   • Registra en historial: accion='entrega', estado_anterior='Pendiente', estado_nuevo='Entregado'
   • Muestra mensaje de confirmación con total cobrado
```

### Caso 4: Entrega de paquete vencido (con comisión)

**Involucrados:** Administrador → Paquete → Pago, Historial, Configuración

```
Prerrequisito: Paquete con fecha_recepcion > 7 días atrás

1. Admin busca el paquete por código (ej: L-1)
2. Sistema detecta que está vencido:
   • Calcula días transcurridos (ej: 8 días)
   • Muestra alerta "¡Vencido!"
   • Calcula comisión por atraso:
     - Si tipo_comision='fijo': comision = monto_comision (ej: $5.00)
     - Si tipo_comision='porcentaje': comision = monto_base * porcentaje_comision
   • Total = monto_base + comision (ej: $10.00 + $5.00 = $15.00)
3. Admin procesa el pago con el total ajustado
4. El resto del flujo es idéntico al Caso 3
```

### Caso 5: Registro de pago y entrega desde detalle

**Involucrados:** Administrador → Paquete → Pago, Historial

```
1. Admin accede a /paquetes/detalle/{id}
2. Clic en "Procesar Pago y Entrega"
3. Se muestra el formulario de pago (misma lógica que Caso 3)
4. Admin registra el pago y confirma la entrega
5. El paquete pasa de 'Pendiente' → 'Entregado'
```

### Caso 6: Imprimir etiqueta

**Involucrados:** Administrador → Paquete → Vendedor, Comprador, Autorizado

```
1. Admin accede a /paquetes/imprimir/{id}
2. Sistema genera una página de impresión con:
   • Código de barras/numérico del paquete (código visible)
   • Código QR (escaneable)
   • Nombre del comprador
   • Información del vendedor y autorizado
   • Fecha de recepción
   • Botón "Imprimir" que ejecuta window.print()
3. La etiqueta se imprime y se adjunta al paquete físico
```

### Caso 7: Acceso público vía código QR (entrega en pozo)

**Involucrados:** Comprador/Autorizado (sin login) → Paquete, Vendedor, Comprador, Autorizado

```
1. El comprador/autorizado escanea el código QR del paquete físico
2. El QR contiene: https://{host}/entrega/{qr_token}
3. Accede a /entrega/{token} sin necesidad de login
4. Sistema muestra:
   • Código visible del paquete
   • Estado actual (Pendiente/Entregado)
   • Nombre del comprador
   • Nombre del autorizado
   • Fecha de recepción y vencimiento
   • Si está pendiente: mensaje indicando que está en proceso
   • Si está entregado: datos del pago (monto, medio de pago)
```

### Caso 8: Generación de código QR para impresión

**Involucrados:** Administrador → Paquete → QRGen → Python/GD

```
1. Admin accede a /paquetes/detalle/{id}
2. La página usa QRGen::toDataURI() para generar el QR:
   • URL = {scheme}://{host}/entrega/{qr_token}
   • Se llama a Python (scripts/generate_qr.py) para generar el PNG
   • Si Python falla, usa fallback PHP GD
   • El PNG se convierte a base64 data URI para renderizado en <img>
3. El QR se muestra en la página de detalle y confirmación
4. Admin puede hacer clic en el QR para abrir la URL pública
5. También disponible en /qr-code/{token} (devuelve PNG binario)
```

### Caso 9: Reportes y estadísticas

**Involucrados:** Administrador → Paquetes, Pagos, Compradores

```
1. Admin navega a /reportes (con filtros de fecha opcionales)
2. El sistema muestra:
   • Total de ingresos (suma de pagos.total en el rango)
   • Total de comisiones (suma de pagos.comision_atraso)
   • Paquetes entregados (count en el rango)
   • Pagos agrupados por medio de pago (Efectivo, Tarjeta, Transferencia, Otro)
   • Top 10 compradores por cantidad de paquetes entregados
3. Admin puede exportar o filtrar por rango de fechas
```

### Caso 10: Gestión de usuarios (admin)

**Involucrados:** Administrador → Usuarios

```
1. Admin navega a /usuarios
2. Ve la lista de usuarios con: nombre, usuario, rol, estado
3. Admin puede:
   • Editar: cambiar nombre, rol (admin/operador), estado (activo/inactivo)
   • Cambiar password (opcional, se deja en blanco para no cambiar)
4. El seed crea el usuario admin con password_hash
```

### Caso 11: Configuración del sistema

**Involucrados:** Administrador → Configuración

```
1. Admin navega a /configuracion
2. Ve el formulario con los siguientes parámetros:
   • nombre_negocio, direccion_negocio, email_negocio, telefono_negocio
   • tarifa_base (monto base para paquetes)
   • tipo_comision (fijo o porcentaje)
   • monto_comision (comisión fija por atraso)
   • porcentaje_comision (comisión porcentual)
   • plazo_dias (días de gracia)
   • numeracion_tipo (continua, por comprador)
3. Admin modifica y guarda
4. Los cambios afectan inmediatamente:
   • Nuevas recepciones usan la nueva tarifa_base
   • Entregas posteriores usan la nueva comisión
```

### Caso 12: Autenticación y autorización

**Involucrados:** Usuario → Auth, Session

```
Login:
1. Admin ingresa usuario y password
2. Auth::login() verifica con password_verify()
3. Si válido: Session::set('user_id'), Session::set('user_role')
4. Redirect a /dashboard

Logout:
1. Auth::logout() elimina la sesión
2. Redirect a /login

Autorización:
• Todos los controladores requieren login EXCEPT
• AuthController (login, logout, register) — público
• PaqueteController métodos: qrCode, entregaPublica, apiBuscar, apiBuscarLista — públicos
• Role check: admin ve todo; operador tiene acceso limitado (TODO)
```

### Diagrama de relaciones

```
Administrador (admin)
    │
    ├───▶ Registra paquete
    │       ├───▶ Vendedor (findOrCreate)
    │       ├───▶ Comprador (findOrCreate) → inicial → código M-N
    │       ├───▶ Autorizado (findOrCreate, relación con Comprador)
    │       └───▶ Paquete (código_visible, qr_token, estado=Pendiente)
    │               ├───▶ Historial (recepcion)
    │               └───▶ QR (entrega/{token})
    │
    ├───▶ Busca paquete (por código M-N o QR)
    │       ├───▶ Ve detalle (vendedor, comprador, autorizado, historial)
    │       └───▶ Procesa entrega
    │               ├───▶ Pago (monto_base, comision_atraso, total, medio_pago)
    │               ├───▶ Paquete (estado=Entregado, fecha_actualizacion)
    │               └───▶ Historial (entrega, con detalle de pago)
    │
    ├───▶ Ve reportes
    │       ├───▶ Pagos (filtrados por fecha)
    │       ├───▶ Paquetes entregados
    │       └───▶ Top compradores
    │
    └───▶ Administra
            ├───▶ Usuarios
            └───▶ Configuración (tarifas, plazos, comisiones)

Comprador/Autorizado (público)
    │
    └───▶ Escanea QR
            ├───▶ URL: /entrega/{qr_token}
            └───▶ Ve estado del paquete (Pendiente/Entregado + datos)
```

---

## Guía de Uso

### 2. Registrar un paquete

1. Navegar a **Paquetes → Registrar Paquete**
2. Seleccionar o crear vendedor (nombre + celular)
3. Ingresar comprador (nombre + celular)
4. Ingresar autorizado (nombre + celular + relación)
5. Agregar descripción, monto base y observaciones
6. Confirmar → se genera código M-N y QR automáticamente

### 3. Buscar y gestionar paquetes

- **Lista de paquetes**: `/paquetes` — filtra por código, busca por estado
- **Detalle**: clic en cualquier paquete → muestra información completa, historial de auditoría
- **Entrega rápida**: ingresar código visible (ej: M-1) → mostrar formulario de pago

### 4. Procesar entrega

1. Ingresar código visible del paquete
2. Verificar cálculo: monto base, comisión (si aplica), total
3. Seleccionar medio de pago (Efectivo, Tarjeta, Transferencia, Otro)
4. Ingresar referencia (opcional)
5. Confirmar → estado cambia a "Entregado", pago registrado, entrada en historial

### 5. Dashboard

El dashboard muestra estadísticas en tiempo real:
- Paquetes pendientes
- Paquetes atrasados (vencidos)
- Paquetes entregados hoy
- Paquetes recibidos hoy
- Monto cobrado hoy

### 6. Reportes

Filtros por fecha (desde/hasta):
- Total de ingresos
- Total de comisiones
- Paquetes entregados
- Pagos agrupados por medio de pago
- Top compradores

### 7. Configuración (admin)

| Parámetro | Valor por defecto | Descripción |
|-----------|-------------------|-------------|
| nombre_negocio | DeUna | Nombre del negocio |
| tarifa_base | 10.00 | Tarifa base por paquete |
| tipo_comision | fijo | Tipo de comisión (fijo/porcentaje) |
| monto_comision | 5.00 | Comisión fija por atraso |
| porcentaje_comision | 0.00 | Comisión porcentual |
| plazo_dias | 7 | Días de gracia |
| numeracion_tipo | continua | Tipo de numeración |

### 8. Gestión de usuarios (admin)

- Listar, buscar y editar usuarios
- Cambiar rol (admin/operador) y estado (activo/inactivo)
- Actualizar información personal

### 9. Imprimir etiqueta

- Genera una etiqueta con código QR y código visible
- Incluye información del paquete (vendedor, comprador, autorizado)
- Página lista para imprimir (`window.print()`)

---

## QR Codes

### Arquitectura dual

| Capa | Herramienta | Descripción |
|------|-------------|-------------|
| **Server-side** | Python 3 (`scripts/generate_qr.py`) | Genera PNG binario usando la librería `qrcode`. Llamado desde `QRGen.php` via `shell_exec()`. |
| **Server-side fallback** | PHP GD (`QRGen::generateWithGD()`) | Implementación pura PHP usando GD. Se usa si Python falla. |
| **Client-side** | `public/js/qrcode.min.js` (42KB) | Biblioteca `qrcode` empaquetada via browserify+terser. Expone `QRCode.toCanvas()`. |

### Token QR

- Generado con `bin2hex(random_bytes(16))` → 32 caracteres hexadecimales
- URL embebida: `{scheme}://{host}/entrega/{qr_token}`
- La URL pública `/entrega/{token}` muestra los detalles del paquete sin requerir autenticación

### Verificación de escaneo

```bash
# Verificar que un QR generado es escanable
python3 -c "
from pyzbar import pyzbar
from PIL import Image
img = Image.open('/tmp/qr_test.png')
print(pyzbar.decode(img))
"
```

---

## API Endpoints

### `POST /api/paquete`

Buscar paquete por código visible.

**Request:**
```
POST /api/paquete
Content-Type: application/x-www-form-urlencoded
csrf_token=xxx&codigo=M-1
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "codigo_visible": "M-1",
    "qr_token": "abc123...",
    "estado": "Pendiente",
    "comprador_nombre": "Maria Caceres",
    "vendedor_nombre": "Tienda ABC",
    "vendedor_celular": "70000000",
    "autorizado_nombre": "Pedro Caceres",
    "fecha_recepcion": "2026-09-09 00:49:56",
    "dias_transcurridos": 0,
    "monto_base": 10,
    "comision_atraso": 0,
    "total_pagar": 10
  }
}
```

### `GET /api/buscar?q=<query>`

Autocomplete para búsqueda de paquetes pendientes.

**Response:**
```json
{
  "success": true,
  "data": [
    {"id": 2, "codigo_visible": "M-2", "comprador_nombre": "Maria Caceres"}
  ]
}
```

---

## Configuración

### Archivo `config/config.php`

Define las siguientes constantes:

| Constante | Descripción |
|-----------|-------------|
| `APP_NAME` | Nombre de la aplicación ("DeUna") |
| `APP_VERSION` | Versión ("1.0.0") |
| `DB_PATH` | Ruta a la base de datos SQLite |
| `BASE_URL` | Base URL ("/") |
| `SESSION_NAME` | Nombre de la sesión ("deuna_session") |
| `SESSION_TIMEOUT` | Timeout de sesión (7200 segundos = 2 horas) |
| `DEFAULT_ADMIN_USER` | Usuario admin por defecto |
| `DEFAULT_ADMIN_PASS` | Password admin por defecto |
| `ITEMS_PER_PAGE` | Paginación (20) |

### Configuración de sesión

- Las sesiones se guardan en `sys_get_temp_dir()` (compatible con todos los sistemas)
- Nombre de sesión personalizado: `deuna_session`
- Cookie con timeout de 2 horas

---

## Desarrollo

### Comandos útiles

```bash
# Iniciar servidor de desarrollo
php -S localhost:8000 -t public public/router.php

# Regenerar base de datos (borrar y recrear)
rm database/deuna.db

# Generar QR de prueba
python3 scripts/generate_qr.py "https://localhost:8000/entrega/test123token"

# Verificar QR escanable
python3 -c "from pyzbar import pyzbar; from PIL import Image; print(pyzbar.decode(Image.open('/tmp/qr_test.png')))"

# Ver esquema de la base de datos
sqlite3 database/deuna.db ".schema"

# Ver datos
sqlite3 database/deuna.db "SELECT codigo_visible, estado, fecha_recepcion FROM paquetes;"

# Lint de PHP
php -l app/core/App.php
```

### Notas técnicas

- El router (`public/router.php`) sirve archivos estáticos directamente y reescribe todo lo demás a `index.php`
- La migración de base de datos es **idempotente**: solo crea tablas si no existen
- El login admin es idempotente: solo inserta los datos semilla si no existen
- Las contraseñas se almacenan con `password_hash()` (bcrypt/argon2)
- CSRF tokens se renderizan en todos los formularios via `Helper::csrfToken()`

### Script de despliegue automático (Domcloud)

```bash
# Generar paquete listo para Domcloud
python3 scripts/deploy_domcloud.py

# Ver vista previa sin crear archivos
python3 scripts/deploy_domcloud.py --dry-run

# Especificar directorio de destino
python3 scripts/deploy_domcloud.py --target /home/tu_usuario
```

Genera automáticamente:
- `public_html/` — document root con archivos públicos adaptados
- `deuna_app/` — código de aplicación con paths configurados
- `deploy_checklist.txt` — lista de verificación

Ver documentación completa en `docs/DESpliegue_Domcloud.md`.

---

## Despliegue a Producción

Esta sección describe los pasos para desplegar la aplicación en un servidor de producción con acceso público a Internet.

### 1. Preparar el servidor

#### Opción A: Apache (recomendado)

```bash
# Instalar Apache y PHP con extensiones
sudo apt update
sudo apt install apache2 libapache2-mod-php8.5 php8.5 php8.5-gd php8.5-sqlite3 \
  php8.5-mbstring php8.5-xml php8.5-curl php8.5-intl php8.5-sodium

# Verificar extensiones
php -m | grep -E "gd|pdo_sqlite|sqlite3|mbstring|openssl|sodium|intl"
```

#### Opción B: Nginx + PHP-FPM

```bash
sudo apt update
sudo apt install nginx php8.5-fpm php8.5-gd php8.5-sqlite3 \
  php8.5-mbstring php8.5-xml php8.5-curl php8.5-intl php8.5-sodium

# Iniciar servicios
sudo systemctl start php8.5-fpm nginx
```

#### Opción C: PHP Built-in Server (no recomendado para producción)

> ⚠️ El servidor built-in de PHP (`php -S`) no es adecuado para producción. Usar solo para pruebas rápidas.

### 2. Instalar Python 3 y dependencias

```bash
# Instalar Python 3
sudo apt install python3 python3-pip python3-pil

# Instalar librerías QR
pip3 install qrcode[pil] pyzbar

# Verificar
python3 -c "import qrcode; import pyzbar; from PIL import Image; print('OK')"
```

### 3. Transferir archivos al servidor

```bash
# Opción 1: rsync
rsync -avz --exclude 'database/' --exclude '.git' --exclude '*.pdf' \
  /home/ddr-3/Proyectos/deUna/ usuario@servidor:/var/www/deuna/

# Opción 2: git clone + pull
cd /var/www/deuna
git clone /ruta/al/repo local .
```

### 4. Configurar permisos

```bash
cd /var/www/deuna

# Crear directorio de base de datos con permisos de escritura
mkdir -p database uploads
chmod 755 database uploads
chown www-data:www-data database uploads

# El archivo de BD necesita permisos de lectura/escritura
# (se crea automáticamente en el primer request)

# Archivos estáticos
chmod -R 755 public/
```

### 5. Configurar el servidor web

#### Apache (`.htaccess` o virtual host)

```apache
<VirtualHost *:80>
    ServerName deuna.tu-dominio.com
    DocumentRoot /var/www/deuna/public
    
    <Directory /var/www/deuna/public>
        AllowOverride All
        Require all granted
        
        # El router.php maneja todas las rutas
        # Si mod_rewrite no está disponible, funciona con index.php también
    </Directory>
    
    # Forzar HTTPS
    Redirect permanent / https://deuna.tu-dominio.com/
</VirtualHost>
```

Crear `/var/www/deuna/public/.htaccess`:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

---

#### Nginx

```nginx
server {
    listen 80;
    server_name deuna.tu-dominio.com;
    root /var/www/deuna/public;
    index index.php;
    
    # Forzar HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name deuna.tu-dominio.com;
    root /var/www/deuna/public;
    index index.php;
    
    ssl_certificate /ruta/a/cert.pem;
    ssl_certificate_key /ruta/a/key.pem;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Negociar acceso a directorios sensibles
    location ~ ^/(sql|database|app|scripts|config|\.env) {
        deny all;
        return 404;
    }
    
    # Assets estáticos
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }
}
```

---

#### Domcloud (Hosting Compartido)

Domcloud es un proveedor de hosting compartido. El despliegue requiere adaptar la estructura de directorios ya que el document root típicamente apunta a `public_html/` y no `public/`.

#### Paso 1: Crear la estructura de directorios

Domcloud usa `public_html/` como raíz pública. La estructura recomendada:

```
/home/tu_usuario/
├── public_html/          ← Document root (solo archivos públicos)
│   ├── css/
│   ├── js/
│   ├── assets/
│   ├── index.php         ← Front controller
│   └── router.php        ← Router (para servir archivos estáticos)
├── deuna_app/            ← Aplicación (fuera del alcance público)
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── scripts/
│   ├── sql/
│   └── vendor/
```

#### Paso 2: Configurar PHP

1. Accede al **cPanel** de Domcloud
2. Ve a **Select PHP Version** (o **MultiPHP Manager**)
3. Selecciona **PHP 8.1 o superior** (8.5 ideal, mínimo 8.1)
4. Activa extensiones necesarias:
   - `gd`
   - `pdo_sqlite`
   - `sqlite3`
   - `mbstring`
   - `openssl`
   - `sodium`
   - `intl`
5. Aumenta el `memory_limit` a `256M` y `max_execution_time` a `60`

#### Paso 3: Configurar Python

Domcloud incluye Python 3. Verifica con:

```bash
# En un terminal SSH o vía cPanel → Terminal
python3 --version
python3 -c "import qrcode; print('qrcode OK')"
```

Si `qrcode` no está instalado, instálalo vía pip:

```bash
pip3 install --user qrcode[pil] pyzbar
```

> **Nota:** Si Python no está disponible o `qrcode` no se puede instalar, el sistema usará el fallback PHP GD (funciona pero los QR pueden ser menos detallados).

#### Paso 4: Subir archivos

**Opción A: vía FTP**

1. Conéctate vía FTP (FileZilla, WinSCP)
2. Crea el directorio `deuna_app/` en la raíz
3. Sube todo el proyecto a `deuna_app/` **excepto** `public/`
4. Sube el contenido de `public/` a `public_html/`

**Opción B: vía File Manager del cPanel**

1. Accede al **File Manager** del cPanel
2. Sube el archivo `.zip` del proyecto a la raíz
3. Extrae el zip
4. Mueve todo el contenido de `public/` a `public_html/`
5. Mueve `app/`, `config/`, `database/`, `scripts/`, `sql/` a `deuna_app/`

#### Paso 5: Configurar paths

Modifica `config/config.php` para ajustar las rutas:

```php
<?php
// config/config.php (versión Domcloud)

define('APP_NAME', 'DeUna');
define('APP_VERSION', '1.0.0');

// Ruta de base de datos (desde config/ hacia deuna_app/database/)
define('DB_PATH', dirname(__DIR__) . '/database/deuna.db');

// Base URL
define('BASE_URL', '/');

// Configuración de sesión
define('SESSION_NAME', 'deuna_session');
define('SESSION_TIMEOUT', 7200);

// Credenciales admin (CAMBIAR después del primer login)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'cambiar_esta_contraseña_2024');

// Paginación
define('ITEMS_PER_PAGE', 20);
```

Modifica `public/index.php` para apuntar a las rutas correctas:

```php
<?php
// public/index.php (versión Domcloud)

// Cargar configuración desde deuna_app/
require_once dirname(__DIR__) . '/deuna_app/config/config.php';

// Cargar clases del framework
$basePath = dirname(__DIR__) . '/deuna_app/';
require_once $basePath . 'app/core/Database.php';
require_once $basePath . 'app/core/Session.php';
require_once $basePath . 'app/core/Helper.php';
require_once $basePath . 'app/core/Auth.php';
require_once $basePath . 'app/core/Model.php';
require_once $basePath . 'app/core/Controller.php';
require_once $basePath . 'app/core/QRGen.php';
require_once $basePath . 'app/core/App.php';
require_once $basePath . 'app/core/Autoloader.php';

Session::start();
Database::getInstance();
new App();
```

Modifica `public/router.php` para apuntar al front controller:

```php
<?php
// public/router.php (versión Domcloud)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ext = pathinfo($uri, PATHINFO_EXTENSION);

// Servir archivos estáticos directamente
if ($ext !== '' && file_exists(__DIR__ . '/' . $uri)) {
    return false;
}

// Parsear URL y establecer $_GET['url']
$uri = ltrim($uri, '/');
if ($uri !== '') {
    $_GET['url'] = $uri;
}

// Cargar front controller desde deuna_app/
require dirname(__DIR__) . '/deuna_app/public/index.php';
```

Modifica `app/core/Autoloader.php` para resolver las rutas correctas:

```php
// app/core/Autoloader.php — versión Domcloud
$root = dirname(__DIR__, 2); // De app/core/ a deuna_app/
```

Modifica `app/core/QRGen.php` para apuntar al script de QR:

```php
// app/core/QRGen.php — versión Domcloud
$script = dirname(__DIR__, 2) . '/scripts/generate_qr.py';
// dirname(__DIR__, 2) desde app/core/ = deuna_app/ ✓
```

#### Paso 6: Configurar .htaccess

En `public_html/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Negociar acceso a archivos sensibles
<FilesMatch "\.(env|htaccess|ini|log|sql)$">
    Require all denied
</FilesMatch>
```

#### Paso 7: Permisos

```bash
# Vía SSH o Terminal del cPanel
cd /home/tu_usuario

# Permisos de directorio
chmod 755 deuna_app/database
chmod 755 deuna_app/scripts
chmod 755 public_html

# Permisos de archivos
chmod 644 public_html/index.php
chmod 644 public_html/router.php
chmod 644 deuna_app/config/config.php

# La base de datos se crea automáticamente
# Asegurar que database/ es escribible
chmod 775 deuna_app/database
```

#### Paso 8: Iniciar la base de datos

```bash
# Vía Terminal del cPanel o SSH
cd /home/tu_usuario/deuna_app
php -r "
require 'config/config.php';
require 'app/core/Database.php';
Database::getInstance();
Database::migrate();
Database::seed();
echo 'Base de datos lista.' . PHP_EOL;
"
```

#### Paso 9: Configurar HTTPS

Domcloud ofrece certificados SSL gratuitos:

1. En cPanel, ve a **SSL/TLS Status**
2. Marca tu dominio y haz clic en **Autorepair SSL**
3. El certificado se instala automáticamente

#### Paso 10: Verificación

1. Accede a `https://tudominio.com`
2. Haz login con `admin` / `admin123`
3. Verifica que el QR se genera en la página de confirmación
4. Prueba el acceso público: `https://tudominio.com/entrega/{token}`

#### Problemas comunes en Domcloud

| Problema | Causa | Solución |
|----------|-------|----------|
| "500 Internal Server Error" | `.htaccess` mal configurado | Verificar RewriteEngine y RewriteRule |
| "Class not found" | Paths incorrectos | Verificar `dirname(__DIR__)` en index.php y Autoloader.php |
| QR no se genera | Python o qrcode no disponible | Usar PHP GD fallback (activa `extension=gd` en PHP) |
| DB error "no such column" | Base de datos antigua | Borrar `database/deuna.db` y dejar que se recree |
| Permisos denegados en DB | `database/` no escribible | `chmod 775 database/` |
| Sesión no persiste | Directorio tmp no configurable | PHP usa `/tmp` por defecto en Domcloud |
| QR público no carga | `REQUEST_SCHEME` no disponible | Ya corregido en `Helper::getPackageUrl()` |

#### Checklist de despliegue en Domcloud

- [ ] PHP 8.1+ con extensiones (gd, pdo_sqlite, sqlite3, mbstring, openssl, sodium, intl)
- [ ] Python 3 con `qrcode`, `Pillow`, `pyzbar`
- [ ] Estructura `public_html/` + `deuna_app/` configurada
- [ ] `config/config.php` con rutas correctas
- [ ] `public/index.php` y `public/router.php` con paths de `deuna_app/`
- [ ] `.htaccess` con RewriteEngine configurado
- [ ] Permisos: `database/` escribible, archivos estáticos en `public_html/`
- [ ] HTTPS activado vía SSL/TLS Status
- [ ] Credenciales admin cambiadas después del primer login
- [ ] Verificado login, registro de paquete, generación de QR, entrega

### 6. Configurar HTTPS (Let's Encrypt)

```bash
# Obtener certificado SSL gratuito
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d deuna.tu-dominio.com

# Auto-renovación
sudo crontab -e
# Añadir: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 7. Configurar variables de entorno

Crear `/var/www/deuna/.env` (o modificar `config/config.php`):

```php
<?php
// config/config.php

define('APP_NAME', 'DeUna');
define('APP_VERSION', '1.0.0');

// Ruta de base de datos (usar /var/www/deuna/database/)
define('DB_PATH', dirname(__DIR__) . '/database/deuna.db');

// Configuración de sesión
define('SESSION_NAME', 'deuna_session');
define('SESSION_TIMEOUT', 7200);

// Credenciales admin (CAMBIAR EN PRODUCCIÓN)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'cambiar_esta_contraseña_segura_2024');

// Base URL (para generación de QR URLs)
define('BASE_URL', '/');
define('ITEMS_PER_PAGE', 20);
```

> **Importante:** Cambia las credenciales de admin después del primer login:
> 1. Navega a **Usuarios → Editar**
> 2. Cambia la contraseña del usuario admin

### 8. Iniciar la base de datos

```bash
# La base de datos se crea automáticamente en el primer request
# Para crear manualmente:
php -r "
require 'config/config.php';
require 'app/core/Database.php';
Database::migrate();
Database::seed();
echo 'Base de datos lista.' . PHP_EOL;
"
```

### 9. Configurar el cron (opcional)

Para tareas programadas (limpieza de sesiones, backup):

```bash
# Backup diario de la base de datos
0 2 * * * cp /var/www/deuna/database/deuna.db /var/backups/deuna_$(date +\%Y\%m\%d).db

# Limpiar sesiones expiradas
0 3 * * * find /tmp -name "sess_deuna*" -mmin +1440 -delete
```

### 10. Verificación post-despliegue

```bash
# Verificar que la aplicación responde
curl -I https://deuna.tu-dominio.com/login

# Verificar QR funciona
TOKEN="test123"
python3 /var/www/deuna/scripts/generate_qr.py "https://deuna.tu-dominio.com/entrega/$TOKEN"

# Verificar permisos
ls -la /var/www/deuna/database/
ls -la /var/www/deuna/public/
```

### Checklist de despliegue

- [ ] Servidor Linux con PHP 8.5+ y extensiones
- [ ] Python 3 con `qrcode`, `pyzbar`, `Pillow`
- [ ] Apache/Nginx configurado (document root = `public/`)
- [ ] HTTPS con Let's Encrypt
- [ ] Permisos de escritura en `database/`
- [ ] `.htaccess` o `try_files` configurado
- [ ] Credenciales admin modificadas
- [ ] Firewall configurado (puerto 80 y 443)
- [ ] Backup automático configurado
- [ ] Cron para limpieza de sesiones

### Notas de seguridad

- ✅ Las contraseñas se almacenan con `password_hash()` (bcrypt)
- ✅ Prepared statements en todas las consultas (PDO)
- ✅ Tokens QR son criptográficamente seguros (`random_bytes(16)`)
- ✅ Sesiones con nombre personalizado y timeout de 2 horas
- ✅ Directorios sensibles bloqueados en configuración web
- ✅ CSRF tokens en todos los formularios POST
- ⚠️ Cambiar la contraseña admin después del despliegue
- ⚠️ Mantener `database/deuna.db` fuera del alcance público
- ⚠️ Configurar `open_basedir` en php.ini para limitar acceso a archivos
