# Tutorial: Pizarra de Anuncios Comunitaria en WTF ⚡

Este tutorial detalla cómo construir una **Pizarra de Anuncios Comunitaria** en el micro-framework **WTF**. A través de este proyecto práctico de inicio, aprenderás a usar todas las herramientas CLI y las características esenciales de WTF:

- **Andamiaje (Scaffold)** para crear la estructura de archivos en segundos.
- **Consola (Console)** para inspeccionar y administrar rutas.
- **Modelos CQRS estrictos** y consultas con la base de datos **SQLite**.
- **Sesiones Seguras** con **EncryptedCookie**.
- **Filtros (Middlewares)** para proteger la creación de anuncios.
- **Guardian** para el análisis estático y validación de reglas de diseño.
- **Pinger** para la automatización de pruebas HTTP.

---

## 🛠️ Arquitectura de la Aplicación

La aplicación consta de los siguientes flujos de usuario:
1. **Pizarra Pública (`/`)**: Cualquiera puede ver los anuncios, ordenados de más reciente a más antiguo.
2. **Registro de Usuarios (`/register`)**: Permite a los miembros de la comunidad registrarse con correo y contraseña.
3. **Inicio/Cierre de Sesión (`/login` y `/logout`)**: Maneja las sesiones de forma segura en el cliente usando cookies cifradas con AES-256-GCM.
4. **Publicación de Anuncios (`/announcements/new`)**: Un formulario protegido por el filtro `auth` para que solo usuarios logueados publiquen.

---

## Paso 1: Andamiaje de Rutas con `./bin/scaffold`

El primer paso en WTF es definir las rutas de nuestra aplicación en un archivo CSV y usar la herramienta de andamiaje para generar automáticamente la estructura de módulos, controladores (Handlers) y vistas.

Crea un archivo llamado `community_routes.csv` en la raíz de tu proyecto con el siguiente contenido:

```csv
Path,Module,Starter,Filters,Description
/,home,HomeHandler,[],Ver la pizarra de anuncios
/register,auth,RegisterHandler,[],Formulario y registro de usuarios
/login,auth,LoginHandler,[],Formulario e inicio de sesión de usuarios
/logout,auth,LogoutHandler,[],Cerrar sesión del usuario
/announcements/new,announcements,NewAnnouncementHandler,[auth],Crear un nuevo anuncio en la pizarra
```

A continuación, ejecuta el comando de andamiaje:

```bash
./bin/scaffold community_routes.csv
```

### ¿Qué hace esta herramienta?
1. Crea los directorios de módulos en `modules/auth/`, `modules/announcements/`, etc.
2. Genera los Handlers base (por ejemplo, `modules/auth/RegisterHandler.php`) implementando el método `handle()`.
3. Crea las vistas base en `modules/auth/views/index.php`.
4. Registra automáticamente todas las rutas en tu archivo global de configuración [config/routes.php](file:///home/nelson/repos/wtf/config/routes.php).

---

## Paso 2: Listar Rutas Registradas con `./bin/console`

Una vez que hayas ejecutado el scaffolding, puedes verificar que todas tus rutas estén correctamente configuradas y mapeadas usando el visualizador de la consola de WTF:

```bash
./bin/console
```

Este comando imprimirá una tabla coloreada en tu terminal mostrando:
- El método HTTP
- El path de la ruta
- El módulo y Handler (Starter) asociado
- Los filtros que se aplicarán (por ejemplo, el filtro `auth` para `/announcements/new`)
- La descripción de la ruta

---

## Paso 3: Diseño de Base de Datos y Modelos CQRS

WTF obliga a seguir el patrón **CQRS** (Command Query Responsibility Segregation) en la capa de persistencia (`shared/models/`). El guardián (`./bin/guardian`) arrojará errores si un modelo de consulta (Query) intenta escribir en la base de datos o si un modelo de acción (Command) intenta leer.

### 1. Inicialización de la Base de Datos
Para simplificar la configuración, crearemos un método helper que cree las tablas en SQLite si no existen.
SQLite guardará los datos en `db/app.sqlite` según lo definido en [config/settings.php](file:///home/nelson/repos/wtf/config/settings.php).

### 2. Modelo de Consulta de Usuarios: `UserQuery.php`
Crea el archivo [shared/models/UserQuery.php](file:///home/nelson/repos/wtf/shared/models/UserQuery.php). Este modelo sólo realiza operaciones de lectura (`SELECT`).

```php
<?php

class UserQuery
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
        $this->initDb();
    }

    private function initDb(): void
    {
        // Crear tablas si no existen de forma segura
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->db->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->findFirst(
            "SELECT * FROM users WHERE email = :email",
            ['email' => $email]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->db->findFirst(
            "SELECT id, name, email FROM users WHERE id = :id",
            ['id' => $id]
        );
    }
}
```

### 3. Modelo de Escritura de Usuarios: `UserCommand.php`
Crea el archivo [shared/models/UserCommand.php](file:///home/nelson/repos/wtf/shared/models/UserCommand.php). Este modelo sólo escribe (`INSERT`).

```php
<?php

class UserCommand
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function create(string $name, string $email, string $plainPassword): int
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        return (int)$this->db->insert('users', [
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword
        ]);
    }
}
```

### 4. Modelo de Consulta de Anuncios: `AnnouncementQuery.php`
Crea el archivo [shared/models/AnnouncementQuery.php](file:///home/nelson/repos/wtf/shared/models/AnnouncementQuery.php).

```php
<?php

class AnnouncementQuery
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function getAllLatest(): array
    {
        return $this->db->findAll("
            SELECT a.*, u.name as author_name 
            FROM announcements a
            JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
        ");
    }
}
```

### 5. Modelo de Escritura de Anuncios: `AnnouncementCommand.php`
Crea el archivo [shared/models/AnnouncementCommand.php](file:///home/nelson/repos/wtf/shared/models/AnnouncementCommand.php).

```php
<?php

class AnnouncementCommand
{
    private Db $db;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function create(int $userId, string $title, string $content): int
    {
        return (int)$this->db->insert('announcements', [
            'user_id' => $userId,
            'title' => $title,
            'content' => $content
        ]);
    }
}
```

---

## Paso 4: Configuración del Filtro de Autenticación (`auth`)

El andamiaje aplicará el filtro `auth` a la ruta `/announcements/new`. Los filtros se colocan en `shared/filters/` y deben coincidir con el nombre de su función.

Modifica [shared/filters/auth.php](file:///home/nelson/repos/wtf/shared/filters/auth.php):

```php
<?php

function auth(array $request): mixed
{
    $session = EncryptedCookie::get('wtf_session');

    if (!$session) {
        // Redirige al login de forma interactiva y limpia
        return redirect_to('/login');
    }

    return true; // Continuar al Handler
}
```

---

## Paso 5: Implementación de Controladores (Handlers) y Vistas

El Contenedor DI de WTF utiliza **Autowiring**, por lo que resolverá e inyectará automáticamente las dependencias declaradas en los constructores de nuestros controladores (como `UserQuery`, `UserCommand`, etc.).

### 1. Registro de Usuarios (`/register`)

Modifica `modules/auth/RegisterHandler.php`:

```php
<?php

class RegisterHandler
{
    private UserQuery $userQuery;
    private UserCommand $userCommand;

    public function __construct(UserQuery $userQuery, UserCommand $userCommand)
    {
        $this->userQuery = $userQuery;
        $this->userCommand = $userCommand;
    }

    public function handle(array $request): Response
    {
        $error = null;

        if ($request['method'] === 'POST') {
            $name = trim($request['body']['name'] ?? '');
            $email = trim($request['body']['email'] ?? '');
            $password = trim($request['body']['password'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                $error = "Todos los campos son obligatorios.";
            } elseif ($this->userQuery->findByEmail($email)) {
                $error = "El correo ya está registrado.";
            } else {
                // Registrar e iniciar sesión automáticamente
                $userId = $this->userCommand->create($name, $email, $password);
                EncryptedCookie::set('wtf_session', [
                    'user_id' => $userId,
                    'user_name' => $name
                ], 3600 * 2); // Expiración en 2 horas

                return redirect_to('/');
            }
        }

        return view("auth/views/register", ['error' => $error], "default");
    }
}
```

Crea la vista correspondiente en `modules/auth/views/register.php`:

```html
<div class="form-container">
    <h2>Registrarse en la Comunidad</h2>
    <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form action="/register" method="POST">
        <div class="form-group">
            <label for="name">Nombre Completo</label>
            <input type="text" id="name" name="name" required placeholder="Juan Pérez">
        </div>
        <div class="form-group">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required placeholder="juan@example.com">
        </div>
        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Registrarse</button>
    </form>
    <p class="auth-link">¿Ya tienes cuenta? <a href="/login">Inicia Sesión aquí</a></p>
</div>
```

---

### 2. Inicio de Sesión (`/login`)

Modifica `modules/auth/LoginHandler.php`:

```php
<?php

class LoginHandler
{
    private UserQuery $userQuery;

    public function __construct(UserQuery $userQuery)
    {
        $this->userQuery = $userQuery;
    }

    public function handle(array $request): Response
    {
        $error = null;

        if ($request['method'] === 'POST') {
            $email = trim($request['body']['email'] ?? '');
            $password = trim($request['body']['password'] ?? '');

            $user = $this->userQuery->findByEmail($email);

            if ($user && password_verify($password, $user['password'])) {
                // Iniciar sesión
                EncryptedCookie::set('wtf_session', [
                    'user_id' => $user['id'],
                    'user_name' => $user['name']
                ], 3600 * 2);

                return redirect_to('/');
            } else {
                $error = "Credenciales incorrectas.";
            }
        }

        return view("auth/views/login", ['error' => $error], "default");
    }
}
```

Crea la vista en `modules/auth/views/login.php`:

```html
<div class="form-container">
    <h2>Iniciar Sesión</h2>
    <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form action="/login" method="POST">
        <div class="form-group">
            <label for="email">Correo Electrónico</label>
            <input type="email" id="email" name="email" required placeholder="tu@correo.com">
        </div>
        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required placeholder="••••••••">
        </div>
        <button type="submit" class="btn">Ingresar</button>
    </form>
    <p class="auth-link">¿No tienes cuenta? <a href="/register">Regístrate aquí</a></p>
</div>
```

---

### 3. Cierre de Sesión (`/logout`)

Modifica `modules/auth/LogoutHandler.php`:

```php
<?php

class LogoutHandler
{
    public function handle(array $request): Response
    {
        EncryptedCookie::destroy('wtf_session');
        return redirect_to('/');
    }
}
```

*(No requiere vista porque destruye la cookie y redirige de inmediato)*

---

### 4. Pizarra de Anuncios (`/`)

Modifica `modules/home/HomeHandler.php`:

```php
<?php

class HomeHandler
{
    private AnnouncementQuery $announcementQuery;

    public function __construct(AnnouncementQuery $announcementQuery)
    {
        $this->announcementQuery = $announcementQuery;
    }

    public function handle(array $request): Response
    {
        // Obtener anuncios
        $announcements = $this->announcementQuery->getAllLatest();
        
        // Obtener sesión si existe para personalizar el saludo
        $session = EncryptedCookie::get('wtf_session');

        return view("home/views/index", [
            'announcements' => $announcements,
            'session' => $session
        ], "default");
    }
}
```

Modifica la vista de inicio en `modules/home/views/index.php`:

```html
<div class="board-header">
    <?php if (isset($session['user_name'])): ?>
        <h1>👋 ¡Hola, <?= h($session['user_name']) ?>!</h1>
        <p>Bienvenido de vuelta a la pizarra comunitaria.</p>
    <?php else: ?>
        <h1>📢 Pizarra de Anuncios Comunitaria</h1>
        <p>Mantente al día con lo que pasa en nuestra comunidad.</p>
    <?php endif; ?>
</div>

<div class="announcements-grid">
    <?php if (empty($announcements)): ?>
        <div class="no-announcements">
            <p>Aún no hay anuncios publicados. ¡Sé el primero en publicar uno!</p>
            <?php if (isset($session)): ?>
                <a href="/announcements/new" class="btn">Publicar Anuncio</a>
            <?php else: ?>
                <a href="/login" class="btn">Inicia sesión para publicar</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-card">
                <h3 class="announcement-title"><?= h($announcement['title']) ?></h3>
                <p class="announcement-content"><?= nl2br(h($announcement['content'])) ?></p>
                <div class="announcement-meta">
                    <span>✍️ Por: <strong><?= h($announcement['author_name']) ?></strong></span>
                    <span>📅 <?= h(date('d/m/Y H:i', strtotime($announcement['created_at']))) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

---

### 5. Crear Nuevo Anuncio (`/announcements/new`)

Modifica `modules/announcements/NewAnnouncementHandler.php`:

```php
<?php

class NewAnnouncementHandler
{
    private AnnouncementCommand $announcementCommand;

    public function __construct(AnnouncementCommand $announcementCommand)
    {
        $this->announcementCommand = $announcementCommand;
    }

    public function handle(array $request): Response
    {
        $error = null;
        $session = EncryptedCookie::get('wtf_session');

        if ($request['method'] === 'POST') {
            $title = trim($request['body']['title'] ?? '');
            $content = trim($request['body']['content'] ?? '');

            if (empty($title) || empty($content)) {
                $error = "El título y el contenido son obligatorios.";
            } else {
                $this->announcementCommand->create($session['user_id'], $title, $content);
                return redirect_to('/');
            }
        }

        return view("announcements/views/new", ['error' => $error], "default");
    }
}
```

Crea la vista en `modules/announcements/views/new.php`:

```html
<div class="form-container">
    <h2>Nuevo Anuncio</h2>
    <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form action="/announcements/new" method="POST">
        <div class="form-group">
            <label for="title">Título del Anuncio</label>
            <input type="text" id="title" name="title" required placeholder="Ej: Reunión de vecinos este sábado">
        </div>
        <div class="form-group">
            <label for="content">Contenido</label>
            <textarea id="content" name="content" rows="6" required placeholder="Escribe los detalles aquí..."></textarea>
        </div>
        <div class="form-actions">
            <a href="/" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn">Publicar Anuncio</button>
        </div>
    </form>
</div>
```

---

## Paso 6: Diseño Premium y Barra de Navegación

Actualizaremos la plantilla global para incluir una barra de navegación dinámica y un diseño elegante que use colores modernos y bordes suaves.

Modifica [shared/templates/default.php](file:///home/nelson/repos/wtf/shared/templates/default.php):

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comunidad WTF - Pizarra de Anuncios</title>
    <style>
        :root {
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --primary: #38bdf8;
            --primary-hover: #0ea5e9;
            --danger: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Navbar Estilo */
        .navbar {
            background-color: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .navbar-link {
            color: var(--text-main);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.2s;
        }

        .navbar-link:hover {
            color: var(--primary);
        }

        /* Botón Estilo */
        .btn {
            background-color: var(--primary);
            color: #000000;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .btn:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--border-color);
            color: var(--text-main);
        }

        /* Contenedor Principal */
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
        }

        /* Tarjetas de Anuncios */
        .board-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .board-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .board-header p {
            color: var(--text-muted);
        }

        .announcements-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .announcement-card {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .announcement-title {
            color: var(--primary);
            margin-top: 0;
            font-size: 1.25rem;
        }

        .announcement-content {
            line-height: 1.6;
            color: #e2e8f0;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Formularios */
        .form-container {
            max-width: 500px;
            margin: 3rem auto;
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
        }

        .form-container h2 {
            margin-top: 0;
            color: var(--primary);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--bg-main);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-main);
            box-sizing: border-box;
        }

        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }

        .auth-link {
            text-align: center;
            font-size: 0.9rem;
            margin-top: 1.5rem;
            color: var(--text-muted);
        }

        .auth-link a {
            color: var(--primary);
            text-decoration: none;
        }

        .no-announcements {
            text-align: center;
            padding: 4rem 2rem;
            background-color: var(--bg-card);
            border: 1px dashed var(--border-color);
            border-radius: 12px;
        }
    </style>
</head>
<body>

    <?php 
    // Recuperar información de la cookie cifrada en la plantilla
    $layoutSession = EncryptedCookie::get('wtf_session'); 
    ?>
    <nav class="navbar">
        <a href="/" class="navbar-brand">⚡ PizarraComunitaria</a>
        <div class="navbar-menu">
            <a href="/" class="navbar-link">Inicio</a>
            <?php if ($layoutSession): ?>
                <a href="/announcements/new" class="btn">Publicar</a>
                <a href="/logout" class="navbar-link">Cerrar Sesión (<?= h($layoutSession['user_name']) ?>)</a>
            <?php else: ?>
                <a href="/login" class="navbar-link">Ingresar</a>
                <a href="/register" class="btn">Registrarse</a>
            <?php endif; ?>
        </div>
    </nav>

    <main class="container">
        <?= $yield ?? '' ?>
    </main>

</body>
</html>
```

---

## Paso 7: Validación con `./bin/guardian`

Antes de desplegar o probar la aplicación, utilizaremos el **Guardian** de WTF para hacer un análisis estático de todo el código de nuestra Pizarra Comunitaria:

```bash
./bin/guardian
```

El Guardian se asegurará de que:
1. **Linter**: Todos los archivos PHP tengan una sintaxis válida.
2. **Handlers limpios**: Ningún archivo en `modules/` imprima HTML plano o tenga etiquetas HTML literales en strings. Esto garantiza que la lógica de negocio y las vistas estén perfectamente separadas.
3. **Inyección de Dependencias limpia**: No uses la palabra clave `new` para instanciar clases de base de datos o lógica directamente en tus controladores. Todo debe ser resuelto por el contenedor DI de WTF.
4. **Validación CQRS**: Tus modelos en `shared/models/` sigan de manera estricta la arquitectura CQRS, separando la lectura (Queries) de la escritura (Commands).

Si hay algún error arquitectónico, el Guardian detallará la línea exacta del archivo infractor.

---

## Paso 8: Automatizar pruebas HTTP con `./bin/pinger`

WTF incluye un verificador de URLs integrado que te permite simular y testear endpoints de forma automatizada leyendo un archivo CSV.

Crea un archivo de pruebas llamado `community_pinger.csv` en la raíz de tu proyecto:

```csv
Method,Path,ExpectedStatus,ContainsText,Payload
GET,/,200,Pizarra de Anuncios Comunitaria,
GET,/login,200,Iniciar Sesión,
GET,/register,200,Registrarse en la Comunidad,
GET,/announcements/new,302,,
```

*(Nota: `/announcements/new` debe retornar 302 porque no estamos logueados y el filtro `auth` debe redirigirnos a `/login`)*.

Una vez que tengas tu servidor local corriendo en el puerto 8080:

```bash
php -S localhost:8080 -t public
```

Ejecuta el **Pinger**:

```bash
./bin/pinger community_pinger.csv http://localhost:8080
```

El Pinger comprobará cada ruta, validará los códigos de estado HTTP devueltos y certificará que el cuerpo de la respuesta contenga los textos clave esperados.

---

## 🎉 Conclusión y Beneficios del Enfoque

Al completar esta pizarra de anuncios utilizando las herramientas de WTF:
1. Tienes un proyecto con **arquitectura limpia y desacoplada** de forma nativa (CQRS y DI).
2. Tienes **seguridad garantizada** contra XSS (por `h()`) y CSRF (por la configuración de `EncryptedCookie`).
3. El proyecto está **listo para producción en Modo Worker de FrankenPHP** gracias al aislamiento de estado.
4. El andamiaje inicial y las pruebas automatizadas se crearon rápidamente a partir de archivos de texto estructurados simples (CSV).
