# Proyecto Dieta

Aplicacion web para gestion de protocolos alimentarios en clinicas de nutricion.
Desarrollada con PHP 8.2 + MariaDB (panel de gestion), Node.js + Express (API REST)
e Ionic + Angular (app del paciente).

---

## Indice

1. [Que es y como funciona](#que-es-y-como-funciona)
2. [Arquitectura del sistema](#arquitectura-del-sistema)
3. [Requisitos previos](#requisitos-previos)
4. [Puesta en marcha con Docker](#puesta-en-marcha-con-docker)
5. [Primer acceso al panel de gestion](#primer-acceso-al-panel-de-gestion)
6. [Recorrido completo del flujo de uso](#recorrido-completo-del-flujo-de-uso)
7. [App del paciente](#app-del-paciente)
8. [Funcionalidades del panel PHP](#funcionalidades-del-panel-php)
9. [Configuracion opcional SMTP](#configuracion-opcional-smtp)
10. [Estructura de la base de datos](#estructura-de-la-base-de-datos)
11. [Solucion de problemas](#solucion-de-problemas)
12. [Comandos utiles](#comandos-utiles)
13. [URLs y credenciales](#urls-y-credenciales)

---

## Que es y como funciona

Proyecto Dieta es una herramienta para profesionales de la nutricion que necesitan
gestionar protocolos alimentarios personalizados para sus pacientes.

**El profesional (nutricionista)** usa el panel web PHP para:
- Registrar clientes (pacientes) con sus datos fisicos
- Crear dietas personalizadas: general (lista de alimentos por categoria)
  o semanal (grid dia x toma con combinaciones plato principal + complemento)
- Controlar el historial de medidas corporales de cada paciente
- Configurar los horarios de cada toma (desayuno, almuerzo, cena...)

**El paciente** usa la app movil (Ionic) para:
- Entrar con un codigo de 8 caracteres que le da el profesional
- Ver la toma que le toca en este momento segun la hora del dia
- Consultar toda la semana y sus datos de perfil

---

## Arquitectura del sistema

| Componente | Tecnologia | Puerto | Descripcion |
|---|---|---|---|
| Panel de gestion | PHP 8.2 + Apache | 8080 | Interfaz del profesional |
| Base de datos | MariaDB 10.11 | interno | Almacena todos los datos |
| phpMyAdmin | phpMyAdmin | 8081 | Visor de la BD (opcional) |
| API REST | Node.js 20 + Express | 3002 | Conecta la app con la BD |
| App del paciente | Ionic + Angular | 8100 | Frontend del paciente |

Los 5 componentes arrancan automaticamente con `docker compose up -d`.

---

## Requisitos previos

- **Docker Desktop** instalado y ejecutandose
  - Windows: https://www.docker.com/products/docker-desktop/
  - Verificar: `docker --version`

Para la **Opcion B** (modo desarrollo de la app):
- **Node.js 20 LTS**: https://nodejs.org/
- **Ionic CLI**: `npm install -g @ionic/cli`

---

## Puesta en marcha con Docker

### 1. Abrir una terminal en la raiz del repositorio

```
ProyectoFinalSolvam/
├── base-de-datos/
├── backend-php/
├── api-rest/
├── app-movil/
├── docker/
└── docker-compose.yml   <-- este archivo es el que usa Docker
```

### 2. Levantar los contenedores

```bash
docker compose up -d
```

La primera vez tarda varios minutos (descarga imagenes, construye PHP, importa la BD).
Cuando termine deben aparecer los 5 contenedores en estado `Started`:

```
[+] Running 5/5
 ✔ Container proyectosolvam-db          Started
 ✔ Container proyectosolvam-web         Started
 ✔ Container proyectosolvam-phpmyadmin  Started
 ✔ Container proyectosolvam-api         Started
 ✔ Container proyectosolvam-app         Started
```

### 3. Verificar estado

```bash
docker compose ps
```

Los 5 contenedores deben aparecer como `running` o `Up`.
Si alguno aparece como `Exit`, ver la seccion [Solucion de problemas](#solucion-de-problemas).

---

## Primer acceso al panel de gestion

**URL:** http://localhost:8080
**Usuario:** `admin`
**Contrasena:** `admin123`

> Se puede cambiar la contrasena desde Configuracion → Gestion de usuarios.

Al entrar se muestra el **Dashboard** con estadisticas generales y las ultimas dietas.

La navegacion principal esta en la barra superior:
- **Clientes** — lista y gestion de pacientes
- **Ingredientes** — catalogo de alimentos
- **Configuracion** — ajustes del sistema

---

## Recorrido completo del flujo de uso

### 1. Registrar un cliente (paciente)

Ir a **Clientes → Nuevo cliente** (boton verde superior derecho).

Rellenar los campos: nombre, apellidos, fecha de nacimiento, sexo, peso (kg),
altura (cm), objetivo, email (opcional) e intolerancias (opcional).

Al guardar, el sistema genera un **codigo de 8 caracteres** (ej. `A2B4C6D8`)
que el profesional entrega al paciente para acceder a su dieta desde la app.
El boton de copiar junto al codigo lo copia directamente al portapapeles.

---

### 2. Seguimiento corporal del cliente

Desde la lista de clientes, boton **verde "Seguimiento"**.

Permite:
- Añadir mediciones periodicas (peso, % grasa corporal, % musculo, observaciones)
- Ver el historial completo con tendencias (flecha verde = mejora, roja = empeora)

---

### 3. Crear una dieta general

Boton **azul "Nueva dieta"** desde la lista de clientes. Rellenar nombre, fecha y
observaciones. El sistema pre-carga automaticamente todos los ingredientes permitidos.

**En la pantalla de edicion:**
- **Ingredientes por categoria:** chips verdes agrupados. Se pueden quitar y añadir con el selector de cada categoria.
- **Huevos:** nota de texto libre (ej. "2 por comida / max 6 a la semana")
- **No comer:** alimentos restringidos para este paciente, con su propio formulario
- **Agua diaria:** campo de texto libre (ej. "2 litros/dia")
- **Suplementos:** campo de texto libre (ej. "Omega-3 1g, Vitamina D 2000 UI")
- **Observaciones:** notas generales del protocolo

Pulsar **Guardar** (barra inferior) para confirmar.
Pulsar **Guardar e Imprimir** para abrir la vista de impresion A4.

---

### 4. Crear una dieta semanal

Al crear una dieta nueva, seleccionar el tipo **Semanal**.

La pantalla de edicion tiene:
- **Tabs por dia** (Lunes a Domingo)
- **6 tarjetas de toma** por dia: Desayuno, Media mañana, Almuerzo, Comida, Merienda, Cena

En cada tarjeta:

**Ingredientes sueltos** (chips verdes): seleccionar del desplegable y pulsar +. Se guardan al pulsar **Guardar**.

**Combinaciones** (chips azules): pulsar **"+ Añadir combinacion"**, elegir el plato principal (obligatorio) y su cantidad, y opcionalmente un complemento. Se guardan de forma inmediata.

**Panel "Mostrar en informe":** toggles para activar/desactivar que tomas aparecen en la version imprimible.

---

### 5. Seleccionar que dieta ve el paciente en la app

Boton **morado "Ver dietas"** desde la lista de clientes.

- **Modo automatico (por defecto):** el paciente ve la dieta mas reciente. Banner azul informativo.
- **Fijar una dieta concreta:** pulsar **"Usar en app"** en esa fila. Badge verde "✓ En app".
- **Volver al modo automatico:** boton **"Usar mas reciente"** en el banner verde.

---

### 6. Ver la dieta desde el lado del paciente

Con el codigo del cliente, acceder a http://localhost:8100.
Ver la seccion [App del paciente](#app-del-paciente).

---

## App del paciente

La app es la interfaz que usa el paciente en su movil o navegador.
Es una PWA (Progressive Web App) desarrollada con Ionic + Angular.

### Opcion A — Con Docker (sin instalar nada extra)

La app compilada se sirve automaticamente al levantar los contenedores.
Disponible en **http://localhost:8100** despues de `docker compose up -d`.

### Opcion B — Con ionic serve (modo desarrollo)

Permite ver cambios en tiempo real si se modifica el codigo fuente.
Requiere Node.js 20 y la Ionic CLI instalada.

> Los contenedores Docker deben estar en marcha antes de arrancar la app.

**Paso 1** — Verificar Node.js (en cualquier terminal):
```bash
node --version   # debe mostrar v20.x.x o superior
```

**Paso 2** — Instalar Ionic CLI (solo la primera vez, en cualquier terminal):
```bash
npm install -g @ionic/cli
```

**Paso 3** — Abrir una terminal dentro de la carpeta `app-movil`.

Desde la raiz del repositorio:
```bash
cd app-movil
```
A partir de aqui todos los comandos siguientes se ejecutan dentro de esa carpeta.

**Paso 4** — Instalar dependencias (solo la primera vez):
```bash
npm install
```
Descarga todos los paquetes necesarios en `node_modules/`. Puede tardar 1-3 minutos.
Las siguientes veces se puede saltar directamente al paso 5.

**Paso 5** — Arrancar el servidor de desarrollo:
```bash
ionic serve
```

El navegador se abre automaticamente en http://localhost:8100.
Pulsar `Ctrl + C` en esa misma terminal para parar.

### Introducir el codigo del paciente

Al abrir la app por primera vez aparece un campo para introducir el
**codigo de 8 caracteres** del paciente.

Obtenerlo desde el panel: http://localhost:8080 → **Clientes** → columna "Codigo".

Introducir el codigo y pulsar **Entrar**. Queda guardado en el navegador.
Para cambiarlo: pestaña **Perfil** → "Cambiar codigo de paciente".

### Las tres pestañas

| Pestaña | Contenido |
|---|---|
| **Hoy** | Toma activa en este momento segun las franjas horarias configuradas |
| **Semana** | Vista completa de los 7 dias con selector de dia |
| **Perfil** | Datos del paciente, toggle modo oscuro, boton para cambiar codigo |

---

## Funcionalidades del panel PHP

### Catalogo de ingredientes

- Lista filtrable por categoria
- **Edicion inline:** clicar en cualquier fila para editar sin recargar la pagina
- **Gestionar categorias:** panel desplegable para crear o eliminar categorias
- **Exportar ingredientes:** descarga CSV o copia la lista al portapapeles

### Configuracion

**Franjas horarias:** definen a que hora corresponde cada toma. La app las usa para
mostrar la toma correcta al paciente segun la hora actual.

**SMTP:** configuracion del correo de bienvenida (ver seccion siguiente).

**Gestion de usuarios:** crear, editar y gestionar cuentas de acceso al panel.

---

## Configuracion opcional SMTP

El sistema puede enviar un correo de bienvenida al paciente al registrarlo,
con su codigo de acceso e instrucciones para usar la app.

Editar `backend-php/config/settings.json` o configurarlo desde el panel
(Configuracion → SMTP):

```json
{
  "smtp": {
    "host":      "smtp.gmail.com",
    "port":      587,
    "username":  "tu_correo@gmail.com",
    "password":  "tu_app_password",
    "from":      "tu_correo@gmail.com",
    "from_name": "Proyecto Dieta"
  }
}
```

Para Gmail se necesita una **Contrasena de aplicacion**:
Google Account → Seguridad → Verificacion en dos pasos → Contrasenas de aplicacion

Si el SMTP no esta configurado o falla, el cliente se crea igualmente.

---

## Estructura de la base de datos

Explorable visualmente en phpMyAdmin: http://localhost:8081

| Tabla | Descripcion |
|---|---|
| `clientes` | Datos del paciente: nombre, peso, talla, objetivo, codigo unico de acceso |
| `ingredientes` | Catalogo de alimentos con valores nutricionales y categoria |
| `dietas` | Protocolo asignado a un cliente (tipo, nombre, fecha, agua, suplementos) |
| `dieta_ingredientes` | Alimentos de una dieta general |
| `dieta_semanal_comidas` | Ingredientes sueltos de la dieta semanal por dia y toma |
| `dieta_semanal_combinaciones` | Combinaciones principal + complemento por dia y toma |
| `dieta_no_permitidos` | Alimentos restringidos especificos del paciente |
| `no_permitidos` | Lista base de alimentos no recomendados |
| `cliente_medidas` | Historial corporal: peso, % grasa, % musculo por fecha |
| `franjas_horarias` | Horario de cada toma (configurable desde el panel) |
| `usuarios` | Cuentas de acceso al panel (hash bcrypt) |

---

## Solucion de problemas

### La API falla al arrancar (error de conexion a la BD)

La API puede intentar conectarse a MariaDB antes de que esta haya terminado de
inicializarse (puede tardar hasta 30 segundos la primera vez).

```bash
docker restart proyectosolvam-api
```

### Las tablas no existen o la BD esta vacia

El volumen de Docker ya existia de una ejecucion anterior sin el schema.

```bash
docker compose down -v   # borra el volumen (se pierden los datos)
docker compose up -d
```

### Puerto 8080, 8081, 3002 o 8100 ya en uso

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
docker logs proyectosolvam-web
docker logs proyectosolvam-db
docker logs proyectosolvam-api
docker logs proyectosolvam-app
```

---

## Comandos utiles

```bash
# Levantar todos los contenedores
docker compose up -d

# Ver estado
docker compose ps

# Parar (conserva los datos)
docker compose down

# Reset completo (borra los datos de la BD)
docker compose down -v && docker compose up -d

# Logs en tiempo real
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
