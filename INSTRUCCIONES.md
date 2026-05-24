# Proyecto Dieta — Guia de despliegue en local

Sistema de gestion de protocolos alimentarios formado por tres componentes:
panel web PHP, API REST Node.js y app movil Ionic + Angular.

---

## Indice

1. [Arquitectura del sistema](#arquitectura-del-sistema)
2. [Requisitos previos](#requisitos-previos)
3. [Puesta en marcha con Docker](#puesta-en-marcha-con-docker)
4. [Primer acceso al panel de gestion](#primer-acceso-al-panel-de-gestion)
5. [App del paciente (Ionic)](#app-del-paciente-ionic)
6. [Configuracion SMTP (opcional)](#configuracion-smtp-opcional)
7. [Solucion de problemas](#solucion-de-problemas)
8. [Comandos utiles](#comandos-utiles)
9. [URLs y credenciales](#urls-y-credenciales)

---

## Arquitectura del sistema

| Componente | Tecnologia | Puerto | Descripcion |
|---|---|---|---|
| Panel de gestion | PHP 8.2 + Apache | 8080 | Interfaz de administracion |
| Base de datos | MariaDB 10.11 | interno | Almacenamiento de datos |
| phpMyAdmin | phpMyAdmin | 8081 | Visor de la base de datos |
| API REST | Node.js 20 + Express | 3002 | Puente entre la app y la BD |
| App del paciente | Ionic + Angular | 8100 | Frontend del paciente |

Los cuatro primeros arrancan con Docker. La app Ionic se lanza aparte con `ionic serve`.

Estructura de la carpeta entregada:

```
entrega/
├── base-de-datos/        # schema.sql (se importa automaticamente al levantar Docker)
├── backend-php/          # Panel PHP (Apache + PHP 8.2)
├── api-rest/             # API REST Node.js
├── app-movil/            # App Ionic + Angular
├── docker/               # Dockerfile para el servidor PHP
├── docker-compose.yml    # Orquestacion de los 4 contenedores
└── INSTRUCCIONES.md      # Este archivo
```

---

## Requisitos previos

### Para el backend (obligatorio)
- **Docker Desktop** instalado y ejecutandose
  - Windows: https://www.docker.com/products/docker-desktop/
  - Verificar: `docker --version`

### Para la app del paciente
- **Node.js 20 LTS**: https://nodejs.org/
- **Ionic CLI**: `npm install -g @ionic/cli`

---

## Puesta en marcha con Docker

### 1. Abrir terminal en la raiz de la carpeta entregada

```bash
cd ruta/a/la/carpeta/entrega
```

### 2. Levantar los contenedores

```bash
docker compose up -d
```

La primera vez tarda varios minutos (descarga imagenes, construye PHP, importa la BD).
Cuando termine deben aparecer los 4 contenedores en estado `Started`:

```
[+] Running 4/4
 ✔ Container proyectosolvam-db          Started
 ✔ Container proyectosolvam-web         Started
 ✔ Container proyectosolvam-phpmyadmin  Started
 ✔ Container proyectosolvam-api         Started
```

### 3. Verificar estado

```bash
docker compose ps
```

Los 4 contenedores deben aparecer como `running` o `Up`.
Si alguno aparece como `Exit` ver la seccion "Solucion de problemas".

---

## Primer acceso al panel de gestion

**URL:** http://localhost:8080  
**Usuario:** `admin`  
**Contrasena:** `admin123`

Se puede cambiar la contrasena desde Configuracion → Gestion de usuarios.

### Configurar franjas horarias

Antes de usar la app del paciente es recomendable revisar las franjas horarias
en **Configuracion → Franjas horarias**. Definen en que rango de horas corresponde
cada toma (desayuno, media mañana, almuerzo, comida, merienda, cena). La app
las usa para determinar automaticamente que toma mostrar al paciente segun la hora.

Ejemplo de configuracion:

| Toma | Inicio | Fin |
|---|---|---|
| Desayuno | 07:00 | 09:30 |
| Media mañana | 10:00 | 11:30 |
| Almuerzo | 12:00 | 13:30 |
| Comida | 14:00 | 15:30 |
| Merienda | 17:00 | 18:30 |
| Cena | 20:00 | 22:00 |

### Flujo basico de uso

1. **Clientes** → crear un cliente: genera un codigo de 8 caracteres automaticamente
2. **Boton "Nueva dieta"** del cliente → crear dieta general o semanal
3. **Boton "Ver dietas"** → seleccionar que dieta ve el paciente en la app
4. El paciente introduce el codigo en la app y accede a su protocolo

---

## App del paciente (Ionic)

La app del paciente es una aplicacion web progresiva (PWA) desarrollada con
Ionic + Angular. Se ejecuta en el navegador y se comunica con la API REST.

Hay dos formas de abrirla, segun lo que haya disponible en el equipo:

---

### Opcion A — Con Docker (sin instalar nada extra)

La app compilada se sirve automaticamente junto con el resto de contenedores.
Al hacer `docker compose up -d` ya queda disponible en **http://localhost:8100**.

No requiere Node.js ni Ionic CLI. Es la opcion mas rapida para el profesor.

> Esta opcion usa la version de la app ya compilada incluida en la carpeta `app-movil/www/`.

---

### Opcion B — Con ionic serve (modo desarrollo)

Permite ver cambios en tiempo real si se modifica el codigo fuente.
Requiere Node.js 20 y la Ionic CLI instalada.

> Antes de arrancar la app, asegurarse de que los contenedores Docker estan
> en marcha (`docker compose up -d`), ya que la app necesita la API en el puerto 3002.

### Paso 1 — Verificar que Node.js esta instalado

```bash
node --version
```

Debe mostrar `v20.x.x` o superior. Si no esta instalado:
https://nodejs.org/ → descargar la version **20 LTS**.

### Paso 2 — Instalar la Ionic CLI (solo una vez)

```bash
npm install -g @ionic/cli
```

Verificar que se ha instalado correctamente:

```bash
ionic --version
```

### Paso 3 — Abrir una terminal en la carpeta app-movil

Desde la raiz de la carpeta entregada:

```bash
cd app-movil
```

### Paso 4 — Instalar las dependencias del proyecto (solo la primera vez)

```bash
npm install
```

Descarga todos los paquetes necesarios en la carpeta `node_modules/`.
Puede tardar entre 1 y 3 minutos segun la conexion. Solo hay que hacerlo
la primera vez; las siguientes veces se puede saltar directamente al paso 5.

### Paso 5 — Arrancar el servidor de desarrollo

```bash
ionic serve
```

La terminal mostrara algo como:

```
> ng run app:serve ...

✔ Browser application bundle generation complete.

Local: http://localhost:8100
```

El navegador se abre automaticamente en **http://localhost:8100**.
Si no se abre solo, escribir esa URL manualmente en el navegador.

> La URL de la API ya esta preconfigurada a `http://localhost:3002`.
> No hay que modificar ningun archivo.

### Paso 6 — Introducir el codigo del paciente

Al abrir la app por primera vez aparece una pantalla con un campo de texto
pidiendo el **codigo de 8 caracteres** del paciente.

Este codigo se obtiene desde el panel PHP:
1. Ir a http://localhost:8080 → **Clientes**
2. El codigo aparece en la columna "Codigo" de cada cliente
3. Usar el boton de copiar (icono junto al codigo) para copiarlo

Introducir el codigo en la app y pulsar **Entrar**.  
El codigo queda guardado en el navegador; no se vuelve a pedir.

### Las tres pestañas de la app

| Pestaña | Contenido |
|---|---|
| **Hoy** | Toma activa en este momento segun las franjas horarias configuradas |
| **Semana** | Vista completa de los 7 dias con selector de dia |
| **Perfil** | Datos del paciente, toggle modo oscuro, boton para cambiar codigo |

Para probar con un paciente diferente: pestaña **Perfil** → "Cambiar codigo de paciente".

### Parar el servidor de desarrollo

Pulsar `Ctrl + C` en la terminal donde corre `ionic serve`.

---

## Configuracion SMTP (opcional)

Permite enviar un correo de bienvenida al registrar un paciente, con su codigo
de acceso e instrucciones de uso de la app.

Editar `backend-php/config/settings.json`:

```json
{
  "smtp": {
    "host":      "smtp.gmail.com",
    "port":      587,
    "username":  "tu_correo@gmail.com",
    "password":  "tu_app_password",
    "from":      "tu_correo@gmail.com",
    "from_name": "Sans Clinique"
  }
}
```

Para Gmail se necesita una **Contrasena de aplicacion** (no la contrasena de cuenta):
Google Account → Seguridad → Verificacion en dos pasos → Contrasenas de aplicacion

Tambien se puede configurar desde el panel: Configuracion → SMTP.
Hay un boton para enviar un correo de prueba y verificar que funciona.

Si el SMTP falla, el cliente se crea igualmente; solo no se envia el correo.

---

## Solucion de problemas

### La API falla al arrancar (error de conexion a la BD)

La API Node.js puede intentar conectarse a MariaDB antes de que esta haya
terminado de inicializarse (puede tardar hasta 30 segundos la primera vez).

```bash
docker restart proyectosolvam-api
```

### Las tablas no existen o la BD esta vacia

El volumen de Docker ya existia de una ejecucion anterior sin el schema.

```bash
docker compose down -v   # borra el volumen (se pierden los datos)
docker compose up -d     # vuelve a crear todo desde cero
```

> `down -v` borra todos los datos de la BD. Solo usar para reset completo.

### Puerto 8080, 8081 o 3002 ya en uso

Editar `docker-compose.yml` y cambiar el puerto externo.  
Ejemplo: `"8080:80"` → `"8888:80"`. Acceder entonces a http://localhost:8888.

### `ionic: command not found`

```bash
npm install -g @ionic/cli
```

### La app no carga la dieta (pantalla en blanco o error de red)

```bash
docker compose ps                      # verificar que proyectosolvam-api esta Up
curl http://localhost:3002/api/dieta   # debe responder (aunque sea con error de parametro)
docker restart proyectosolvam-api      # reiniciar si no responde
```

### Ver logs de un contenedor

```bash
docker logs proyectosolvam-web         # PHP / Apache
docker logs proyectosolvam-db          # MariaDB
docker logs proyectosolvam-api         # API Node.js
docker logs proyectosolvam-phpmyadmin  # phpMyAdmin
```

---

## Comandos utiles

```bash
# Levantar todos los contenedores
docker compose up -d

# Ver estado de los contenedores
docker compose ps

# Parar (conserva los datos)
docker compose down

# Volver a arrancar tras un down
docker compose up -d

# Reset completo (borra los datos de la BD)
docker compose down -v && docker compose up -d

# Logs en tiempo real de todos los contenedores
docker compose logs -f
```

---

## URLs y credenciales

| Servicio | URL | Credenciales |
|---|---|---|
| Panel de gestion | http://localhost:8080 | admin / admin123 |
| phpMyAdmin | http://localhost:8081 | root / root21. |
| API REST | http://localhost:3002 | — |
| App del paciente | http://localhost:8100 | codigo del cliente |
