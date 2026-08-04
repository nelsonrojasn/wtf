-- 1. EMPRESAS Y SEGURIDAD
CREATE TABLE empresas (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,    
    nit VARCHAR(50) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    telefono VARCHAR(255) NOT NULL,
    correo VARCHAR(255) NOT NULL,
    prefijo VARCHAR(50) NOT NULL UNIQUE,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_vigencia TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1
);

CREATE TABLE usuarios (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    correo VARCHAR(255) NOT NULL UNIQUE,
    pass VARCHAR(255) NOT NULL,
    id_empresa INT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1
);

CREATE TABLE usuarios_roles (
    id_usuario INT NOT NULL,
    id_rol INT NOT NULL,
    PRIMARY KEY (id_usuario, id_rol),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    FOREIGN KEY (id_rol) REFERENCES roles(id)
);

CREATE TABLE perfiles (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    id_rol INT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_rol) REFERENCES roles(id)
);

CREATE TABLE recursos (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    url_recurso VARCHAR(255) NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1
);

CREATE TABLE perfiles_recursos (
    id_perfil INT NOT NULL,
    id_recurso INT NOT NULL,
    PRIMARY KEY (id_perfil, id_recurso),
    FOREIGN KEY (id_perfil) REFERENCES perfiles(id),
    FOREIGN KEY (id_recurso) REFERENCES recursos(id)
);

-- 2. MOTOR DE PROCESOS (DEFINICIÓN)
CREATE TABLE entidades (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT uk_entidades_empresa_slug UNIQUE (id_empresa, slug)
);

CREATE TABLE flujos (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT uk_flujos_empresa_slug UNIQUE (id_empresa, slug)
);

CREATE TABLE etapas (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    id_flujo INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    FOREIGN KEY (id_flujo) REFERENCES flujos(id),
    CONSTRAINT uk_etapas_flujo_slug UNIQUE (id_flujo, slug)
);

CREATE TABLE reglas (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    contenido TEXT, -- Agregado para el script FSP
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    CONSTRAINT uk_reglas_empresa_slug UNIQUE (id_empresa, slug)
);

CREATE TABLE rutas (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    id_etapa_inicial INT NOT NULL,
    id_etapa_final INT NOT NULL,
    id_regla INT, -- Puede ser opcional
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    FOREIGN KEY (id_etapa_inicial) REFERENCES etapas(id),
    FOREIGN KEY (id_etapa_final) REFERENCES etapas(id),
    FOREIGN KEY (id_regla) REFERENCES reglas(id)
);

CREATE TABLE param_sql (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    contenido TEXT,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

-- 3. PLANTILLAS Y UI
CREATE TABLE plantillas (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    html TEXT NOT NULL,
    js TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE TABLE plantillas_ajustes (
    id SERIAL PRIMARY KEY,
    id_plantilla INT NOT NULL,
    id_etapa_actual INT NOT NULL,
    serializador TEXT,
    validaciones TEXT,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_plantilla) REFERENCES plantillas(id),
    FOREIGN KEY (id_etapa_actual) REFERENCES etapas(id)
);

-- 4. EJECUCIÓN (SOLICITUDES Y TRAZABILIDAD)
CREATE TABLE solicitudes (
    id SERIAL PRIMARY KEY,
    referencia VARCHAR(255) NOT NULL UNIQUE,
    slug VARCHAR(255) NOT NULL UNIQUE,
    id_empresa INT NOT NULL,
    id_solicitante INT NOT NULL,
    id_entidad INT NOT NULL,
    id_flujo INT NOT NULL,
    id_etapa_actual INT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id),
    FOREIGN KEY (id_solicitante) REFERENCES usuarios(id),
    FOREIGN KEY (id_entidad) REFERENCES entidades(id),
    FOREIGN KEY (id_flujo) REFERENCES flujos(id),
    FOREIGN KEY (id_etapa_actual) REFERENCES etapas(id)
);

CREATE TABLE traza (
    id SERIAL PRIMARY KEY,
    id_solicitud INT NOT NULL,
    id_etapa_origen INT NOT NULL,
    id_etapa_destino INT NOT NULL,
    id_usuario INT NOT NULL,
    observacion TEXT,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id),
    FOREIGN KEY (id_etapa_origen) REFERENCES etapas(id),
    FOREIGN KEY (id_etapa_destino) REFERENCES etapas(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE adjuntos (
    id SERIAL PRIMARY KEY,
    id_solicitud INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(255) NOT NULL,
    tamano_bytes BIGINT NOT NULL,
    descripcion TEXT NOT NULL,
    privado INT DEFAULT 0,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    version INT DEFAULT 1,
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id)
);

CREATE TABLE observaciones (
    id SERIAL PRIMARY KEY,
    id_solicitud INT NOT NULL,
    id_usuario INT NOT NULL,
    descripcion TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

-- 5. HOOKS, NOTIFICACIONES Y LOGS
CREATE TABLE hooks (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    comando TEXT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);

CREATE TABLE hooks_calls (
    id SERIAL PRIMARY KEY,
    id_hook INT NOT NULL,
    id_solicitud INT NOT NULL, -- ¡Agregado!
    id_usuario INT NOT NULL,
    payload TEXT,
    respuesta TEXT,
    ejecutado INT DEFAULT 0,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_hook) REFERENCES hooks(id),
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE notificaciones (
    id SERIAL PRIMARY KEY,
    id_solicitud INT NOT NULL,
    destinatario VARCHAR(255),
    titulo VARCHAR(255) NOT NULL,
    cuerpo TEXT NOT NULL,
    enviado INT DEFAULT 0,
    intentos INT DEFAULT 0,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    f_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo INT DEFAULT 1,
    FOREIGN KEY (id_solicitud) REFERENCES solicitudes(id)
);

CREATE TABLE logs (
    id SERIAL PRIMARY KEY,
    id_empresa INT NOT NULL,
    descripcion TEXT NOT NULL,
    origen VARCHAR(255) NOT NULL,
    origen_id INT NOT NULL,
    f_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_empresa) REFERENCES empresas(id)
);