# 🐾 PataHogar — Sistema de Adopción de Perros

> **Arquitectura Modular PHP 8 · MySQL · Tailwind CSS · JavaScript Fetch API**
> Desarrollado bajo principios de Ingeniería Aumentada

---

## 📁 Estructura del Proyecto

```
adopcion-perros/
│
├── config/
│   ├── database.php          # Conexión PDO (Singleton)
│   └── schema.sql            # Estructura de BD + datos de ejemplo
│
├── models/
│   ├── Perro.php             # Modelo + Repositorio de perros
│   └── Solicitud.php         # Modelo + lógica de transacciones
│
├── controllers/
│   └── AdopcionController.php # Orquestador del flujo de adopción
│
├── public/
│   └── js/
│       └── app.js            # Frontend: Fetch API + UI dinámica
│
├── logs/                     # Directorio para logs de errores
├── index.php                 # Vista principal (HTML + PHP)
├── api.php                   # Endpoint API (recibe peticiones Fetch)
└── README.md
```

---

## 🚀 Instalación (Laragon / XAMPP)

### 1. Clonar / copiar el proyecto

```bash
# En Laragon:
C:\laragon\www\adopcion-perros\

# En XAMPP:
C:\xampp\htdocs\adopcion-perros\
```

### 2. Crear la base de datos

1. Abre **phpMyAdmin** (http://localhost/phpmyadmin)
2. Ejecuta el contenido de `config/schema.sql`
3. Esto crea la BD `adopcion_perros` con tablas y datos de ejemplo

### 3. Verificar configuración de BD

Abre `config/database.php` y ajusta si tu setup difiere:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'adopcion_perros');
define('DB_USER', 'root');
define('DB_PASS', '');          // Tu password de MySQL
```

### 4. Acceder al sistema

```
http://localhost/adopcion-perros/
```

### 5. Credenciales de demo

| Email                | Password   | Rol     |
|----------------------|------------|---------|
| admin@adopcion.com   | secret     | Admin   |
| maria@ejemplo.com    | secret     | Usuario |
| carlos@ejemplo.com   | secret     | Usuario |

> **Nota:** La sesión de demo se inicia automáticamente al acceder a `index.php`.
> En producción, reemplazar por un sistema de login real con bcrypt.

---

## 🏗️ Arquitectura del Sistema

### Patrón MVC Modular

```
Browser (Fetch API)
        │
        ▼
    api.php  ←── Router / API Gateway
        │
        ▼
AdopcionController  ←── Orquestador (Capa de Control)
     │         │
     ▼         ▼
 Perro.php  Solicitud.php  ←── Modelos de Dominio (Repository Pattern)
     │         │
     └────┬────┘
          ▼
     database.php  ←── Capa de Infraestructura (Singleton PDO)
          │
          ▼
       MySQL
```

### Responsabilidades por capa

| Archivo                    | Responsabilidad                                         |
|----------------------------|---------------------------------------------------------|
| `config/database.php`      | Gestionar conexión PDO (Singleton)                      |
| `models/Perro.php`         | CRUD de perros, formateo de datos                       |
| `models/Solicitud.php`     | Lógica de solicitudes, transacciones SQL                |
| `controllers/AdopcionController.php` | Validar, orquestar, responder JSON           |
| `api.php`                  | Router de acciones, cabeceras de seguridad              |
| `index.php`                | Vista HTML, inicio de sesión PHP                        |
| `public/js/app.js`         | Fetch API, DOM dinámico, UX                             |

---

## 🔒 Medidas de Seguridad Implementadas

### 1. PDO con Prepared Statements
```php
// ✅ Correcto — parámetro ligado, no interpolado
$stmt = $db->prepare('SELECT * FROM perros WHERE id = :id');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);

// ❌ Nunca hacer esto
$db->query("SELECT * FROM perros WHERE id = $id");
```

### 2. Sanitización de Output (XSS)
```php
// Toda salida HTML pasa por htmlspecialchars()
$perro['nombre'] = htmlspecialchars($perro['nombre'], ENT_QUOTES, 'UTF-8');
```

### 3. Sesión Segura
```php
session_start([
    'cookie_httponly' => true,    // JS no puede leer la cookie
    'cookie_secure'   => true,    // Solo HTTPS (en producción)
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);
```

### 4. Transacciones SQL Atómicas
```sql
-- Si INSERT solicitud falla → ROLLBACK automático
-- Si UPDATE perro falla → ROLLBACK automático
-- Nunca quedan datos inconsistentes
BEGIN TRANSACTION
  INSERT INTO solicitudes...
  UPDATE perros SET estado = 'en_proceso'...
COMMIT / ROLLBACK
```

### 5. Validación de Entradas
```php
// Whitelist de parámetros (solo acepta lo esperado)
$accion = preg_replace('/[^a-z_]/', '', strtolower($accionRaw));

// Validación de tipos con filter_var
$perroId = filter_var($body['perro_id'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
```

### 6. Logs de Errores (Sin Exposición)
```php
// Error real → log del servidor (nunca al cliente)
error_log('[DB_ERROR] ' . $e->getMessage());

// Cliente recibe mensaje genérico
throw new RuntimeException('Error interno del servidor.');
```

### 7. Respuestas JSON Estructuradas
```json
// Éxito
{ "ok": true, "data": { ... } }

// Error
{ "ok": false, "mensaje": "Descripción del error" }
```

### 8. Cabeceras de Seguridad HTTP
```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
```

---

## ⚙️ Principios SOLID Aplicados

| Principio | Aplicación en el sistema |
|-----------|--------------------------|
| **S** — Single Responsibility | `database.php` solo gestiona conexión; `Perro.php` solo gestiona perros |
| **O** — Open/Closed | Nuevas acciones en el controller sin modificar las existentes |
| **L** — Liskov Substitution | Métodos con contratos claros y tipos explícitos (PHP 8 `declare(strict_types=1)`) |
| **I** — Interface Segregation | Modelos pequeños y enfocados en su entidad |
| **D** — Dependency Inversion | PDO inyectado por constructor en modelos (no instanciado internamente) |

---

## 🤖 Ingeniería Aumentada — Cómo se aplicó

La Ingeniería Aumentada define una colaboración donde **la IA amplifica la capacidad del desarrollador humano** sin reemplazar su criterio técnico y arquitectónico.

### En este proyecto:

| Tarea | Rol de la IA | Rol del Desarrollador |
|-------|-------------|----------------------|
| Arquitectura del sistema | Propone estructura modular | Evalúa, aprueba y ajusta |
| Patrones de diseño | Sugiere Singleton, Repository | Valida su pertinencia |
| Medidas de seguridad | Implementa PDO, sanitización | Revisa y agrega capas faltantes |
| Transacciones SQL | Genera lógica atómica | Verifica integridad del flujo |
| UI/UX | Genera CSS y HTML | Ajusta a identidad del proyecto |
| Código base | Genera el scaffolding inicial | Refactoriza, adapta, mantiene |

> **Principio clave:** El desarrollador mantiene el control de las decisiones de diseño.
> La IA reduce el trabajo repetitivo, pero NO decide la arquitectura final.

---

## 📊 Flujo de Datos — Solicitud de Adopción

```
Usuario hace click en "Adoptar"
        │
        ▼
app.js bloquea el botón (prevenir doble clic)
        │
        ▼
Modal de confirmación
        │
Usuario confirma
        │
        ▼
fetch POST → api.php?accion=solicitar_adopcion
        │
        ▼
AdopcionController::solicitarAdopcion()
        ├─ ¿Sesión activa? → No → 401 Unauthorized
        ├─ ¿perro_id válido? → No → 400 Bad Request
        ├─ ¿Perro disponible? → No → 409 Conflict
        ├─ ¿Solicitud duplicada? → Sí → 409 Conflict
        └─ Sí → BEGIN TRANSACTION
                  INSERT solicitud (estado=pendiente)
                  UPDATE perro (estado=en_proceso)
                  COMMIT → 201 Created
                  (si falla → ROLLBACK → 500)
        │
        ▼
app.js recibe respuesta JSON
        ├─ ok: true  → Toast éxito + eliminar tarjeta animada
        └─ ok: false → Toast error + desbloquear botón
```

---

## 🔧 Extensiones Recomendadas

Para un sistema de producción, considerar añadir:

- **Autenticación real** — Login con bcrypt (`password_hash()`) + tokens JWT
- **Variables de entorno** — Usar `vlucas/phpdotenv` para credenciales
- **CSRF Protection** — Tokens por formulario/sesión
- **Rate limiting** — Limitar solicitudes por IP/usuario
- **Panel de administración** — Aprobar/rechazar solicitudes
- **Notificaciones por email** — PHPMailer al aprobar adopciones
- **Subida de imágenes** — Upload seguro con validación de tipo MIME real
- **Tests automatizados** — PHPUnit para modelos y controladores

---

*Desarrollado con 🐾 y Ingeniería Aumentada — PHP 8 · MySQL · Tailwind CSS*
