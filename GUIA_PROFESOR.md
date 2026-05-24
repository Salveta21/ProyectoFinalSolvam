# Proyecto Dieta — Guia completa para el profesor

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
7. [App del paciente (Ionic)](#app-del-paciente-ionic)
8. [Funcionalidades del panel PHP](#funcionalidades-del-panel-php)
9. [Configuracion opcional (SMTP)](#configuracion-opcional-smtp)
10. [Estructura de la base de datos](#estructura-de-la-base-de-datos)
11. [Solucion de problemas habituales](#solucion-de-problemas-habituales)
12. [Parar y reiniciar el sistema](#parar-y-reiniciar-el-sistema)

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

```
[Navegador del profesional]
        |
        v
  Panel PHP (puerto 8080)
  Apache + PHP 8.2
        |
        v
  Base de datos
  MariaDB (interna)
        ^
        |
  API REST Node.js (puerto 3002)
        ^
        |
[App Ionic en el movil/navegador del paciente] (puerto 8100)
```

| Componente | Tecnologia | Puerto | Descripcion |
|---|---|---|---|
| Panel de gestion | PHP 8.2 + Apache | 8080 | Interfaz del profesional |
| Base de datos | MariaDB 10.11 | interno | Almacena todos los datos |
| phpMyAdmin | phpMyAdmin | 8081 | Visor de la BD (opcional) |
| API REST | Node.js 20 + Express | 3002 | Conecta la app con la BD |
| App del paciente | Ionic + Angular | 8100 | Frontend del paciente |

Todos los componentes excepto la app Ionic arrancan automaticamente con Docker.
La app Ionic se lanza aparte con `ionic serve` (necesita Node.js en el host).

---

## Requisitos previos

### Para el backend (obligatorio)
- **Docker Desktop** instalado y ejecutandose
  - Windows: https://www.docker.com/products/docker-desktop/
  - En el arranque tiene que aparecer el icono de Docker en la barra de tareas
  - Verificar que funciona: abrir terminal y ejecutar `docker --version`

### Para la app del paciente (si se quiere probar)
- **Node.js 20 LTS**: https://nodejs.org/
- **Ionic CLI**: `npm install -g @ionic/cli`

---

## Puesta en marcha con Docker

### Paso 1 — Abrir una terminal en la carpeta de la entrega

La carpeta entregada tiene esta estructura:
```
entrega/
├── base-de-datos/
├── backend-php/
├── api-rest/
├── app-movil/
├── docker/
├── docker-compose.yml   <-- este archivo es el que usa Docker
└── INSTRUCCIONES.md
```

Abrir una terminal (PowerShell, CMD o bash) y navegar hasta la raiz de esa carpeta:
```bash
cd ruta/a/la/carpeta/entrega
```

### Paso 2 — Levantar todos los contenedores

```bash
docker compose up -d
```

La primera vez tarda varios minutos porque Docker tiene que:
1. Descargar las imagenes base (mariadb, phpmyadmin, node)
2. Construir la imagen PHP con Apache
3. Importar el schema de la base de datos automaticamente

Cuando termine se vera algo como:
```
[+] Running 4/4
 ✔ Container proyectosolvam-db          Started
 ✔ Container proyectosolvam-web         Started
 ✔ Container proyectosolvam-phpmyadmin  Started
 ✔ Container proyectosolvam-api         Started
```

### Paso 3 — Comprobar que todo esta en marcha

```bash
docker compose ps
```

Los 4 contenedores deben aparecer con estado `running` o `Up`.

Si algun contenedor aparece como `Exit` o `Exited`, ver la seccion
"Solucion de problemas habituales" al final de este documento.

### Paso 4 — Acceder al panel

Abrir el navegador y entrar en: **http://localhost:8080**

---

## Primer acceso al panel de gestion

**URL:** http://localhost:8080  
**Usuario:** `admin`  
**Contrasena:** `admin123`

> Si se quiere cambiar la contrasena, ir a Configuracion → Gestion de usuarios.

Al entrar se muestra el **Dashboard** con estadisticas generales:
- Numero de clientes registrados
- Numero de dietas creadas
- Ultimas dietas añadidas

La navegacion principal esta en la barra superior:
- **Clientes** — lista y gestion de pacientes
- **Ingredientes** — catalogo de alimentos
- **Configuracion** — ajustes del sistema

---

## Recorrido completo del flujo de uso

A continuacion se describe como usar el sistema de principio a fin para
demostrar todas las funcionalidades.

---

### 1. Registrar un cliente (paciente)

Ir a **Clientes → Nuevo cliente** (boton verde en la esquina superior derecha).

Rellenar los campos:
- Nombre y apellidos
- Fecha de nacimiento
- Sexo
- Peso actual (kg), altura (cm)
- Objetivo (ej. "Perder 5 kg en 3 meses")
- Email (opcional, para el correo de bienvenida)
- Intolerancias conocidas (ej. "Lactosa, gluten")

Al guardar, el sistema genera automaticamente un **codigo de 8 caracteres**
(ej. `A2B4C6D8`) que el profesional entregara al paciente para que acceda
a su dieta desde la app. El codigo se puede copiar desde la tabla de clientes
con el boton de copiar que aparece junto a el.

---

### 2. Ver el seguimiento corporal de un cliente

Desde la lista de clientes, clicar en el boton **verde "Seguimiento"** del cliente.

Desde ahi se puede:
- Añadir una nueva medicion (peso, % grasa corporal, % musculo, observaciones)
- Ver el historial completo con tendencias (flecha verde = mejora, roja = empeora)
- Las tarjetas resumen muestran el ultimo valor y la variacion respecto a la medicion anterior

---

### 3. Crear una dieta general

Desde la lista de clientes, clicar en el boton **azul "Nueva dieta"**.

Rellenar:
- Nombre del protocolo (ej. "Protocolo de verano")
- Fecha de inicio
- Observaciones generales

Al crear la dieta, el sistema pre-carga automaticamente todos los ingredientes
permitidos del catalogo. Se accede entonces a la pantalla de edicion de la dieta.

**En la pantalla de edicion (`dieta_ver.php`):**

- **Ingredientes por categoria:** chips verdes agrupados por categoria
  (verduras, carnes, pescados, frutas...). Se pueden quitar chips y añadir
  nuevos con el selector de la categoria correspondiente.
- **Huevos:** nota de texto libre (ej. "2 por comida / max 6 a la semana")
- **No comer:** lista de alimentos que este paciente no puede tomar.
  Se añaden individualmente con su formulario propio.
- **Agua diaria:** campo de texto libre (ej. "2 litros/dia")
- **Suplementos:** campo de texto libre (ej. "Omega-3 1g, Vitamina D 2000 UI")
- **Observaciones:** notas generales del protocolo

Pulsar **Guardar** (barra inferior) para confirmar los cambios.  
Pulsar **Guardar e Imprimir** para abrir la vista de impresion A4.

---

### 4. Crear una dieta semanal

Las dietas semanales permiten especificar que come el paciente cada dia de la semana
y en cada toma. Son mas detalladas que las dietas generales.

Desde la pantalla de nueva dieta, seleccionar el tipo **Semanal**.

**En la pantalla de edicion semanal (`dieta_semanal_ver.php`):**

La pantalla tiene:
- **Tabs por dia** (Lunes a Domingo). Clicar en el dia para ver sus tomas.
- **6 tarjetas de toma** dentro de cada dia: Desayuno, Media mañana,
  Almuerzo, Comida, Merienda, Cena.

En cada tarjeta hay dos secciones:

**a) Ingredientes sueltos** (chips verdes):
- Seleccionar un ingrediente del desplegable y pulsar el boton +
- Para quitar un ingrediente, pulsar la X en su chip
- Estos cambios se guardan al pulsar el boton **Guardar** del formulario principal

**b) Combinaciones** (chips azules):
- Una combinacion es un "plato principal" + un "complemento opcional" con cantidades
- Pulsar **"+ Añadir combinacion"** para abrir el modal
- Seleccionar el ingrediente principal (obligatorio) y su cantidad (ej. "150g")
- Opcionalmente seleccionar un complemento (ej. "Arroz integral 80g")
- Las combinaciones se guardan inmediatamente (no requieren pulsar Guardar)

**Panel "Mostrar en informe"** (barra de toggles arriba):
- Permite activar/desactivar que tomas aparecen en la version imprimible
- Util si el paciente no desayuna o no cena, por ejemplo

**Panel de importacion CSV:**
- Permite importar una dieta semanal desde un archivo CSV
- Util para cargar dietas generadas previamente o exportadas de otro sistema

---

### 5. Seleccionar que dieta ve el paciente en la app

Desde la lista de clientes, clicar en el boton **morado "Ver dietas"**.

Se muestra la lista de todas las dietas del cliente con una columna **"App"**
que indica cual esta activa para el paciente.

- Por defecto (auto): el paciente ve la dieta mas reciente. Aparece un
  banner azul informando de esto.
- Para fijar una dieta concreta: pulsar el boton **"Usar en app"** de esa fila.
  La columna App mostrara el badge verde "✓ En app".
- Para volver al modo automatico: boton **"Usar mas reciente"** en el banner verde.

---

### 6. Ver la dieta desde el lado del paciente

Con el codigo del cliente (copiado en el paso 1), acceder a la app Ionic.
Ver seccion "App del paciente" mas abajo.

---

## App del paciente (Ionic)

La app es la interfaz que usa el paciente en su movil o navegador para
consultar su dieta. Es una aplicacion web progresiva (PWA) desarrollada con
Ionic + Angular que se comunica con la API REST.

Hay dos formas de abrirla:

---

### Opcion A — Con Docker (sin instalar nada extra)

La app compilada se sirve automaticamente junto con el resto de contenedores.
Al hacer `docker compose up -d` ya queda disponible en **http://localhost:8100**.

No requiere Node.js ni Ionic CLI.

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

### Paso 2 — Instalar la Ionic CLI (solo una vez en el sistema)

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
3. Usar el boton de copiar (icono junto al codigo) para copiarlo al portapapeles

Introducir el codigo en la app y pulsar **Entrar**.  
El codigo queda guardado en el navegador (localStorage); no se vuelve a pedir
en proximas visitas.

Para probar con un paciente diferente: pestaña **Perfil** → "Cambiar codigo de paciente".

### Las tres pestañas de la app

**Pestaña "Hoy":**
- Muestra la fecha actual y un saludo segun la hora (buenos dias / tardes / noches)
- La toma activa en este momento aparece destacada segun las franjas horarias configuradas
- Si es dieta general: ingredientes de esa toma agrupados por categoria
- Si es dieta semanal: combinaciones (borde azul) y/o ingredientes sueltos (chips verdes)
- Al final: tarjetas de agua diaria y suplementos (solo si estan rellenos en la dieta)

**Pestaña "Semana":**
- Selector de dias con scroll horizontal; el dia actual tiene un punto azul
- Por cada dia, tarjetas de cada toma con su contenido
- Badge verde "AHORA" en la toma activa del dia actual

**Pestaña "Perfil":**
- Datos del paciente: peso, altura, objetivo
- Protocolo activo: nombre, fecha, tipo
- Toggle de modo oscuro / claro
- Boton para cambiar el codigo de paciente

### Parar el servidor de desarrollo

Pulsar `Ctrl + C` en la terminal donde corre `ionic serve`.

### Dark mode

Tanto el panel PHP como la app Ionic tienen soporte para modo oscuro.
- En el panel PHP: el toggle esta en la esquina superior derecha de la barra de navegacion
- En la app Ionic: pestaña Perfil → toggle "Modo noche"
- La preferencia se guarda y se recuerda en la siguiente sesion

---

## Funcionalidades del panel PHP

### Catalogo de ingredientes (`ingredientes.php`)

- Lista completa de alimentos con sus valores nutricionales
- Filtrar por categoria usando el desplegable superior
- **Edicion inline:** clicar en cualquier fila para editar sin recargar la pagina
- **Gestionar categorias:** panel desplegable para crear o eliminar categorias
- **Exportar ingredientes:** descarga CSV o copia la lista agrupada al portapapeles
  (util para compartir con herramientas externas)

Los ingredientes marcados como `no_permitido_base` se añaden automaticamente
a la lista "No comer" al crear una nueva dieta (alimentos que por protocolo
general no se recomiendan: lacteos, azucares refinados, alcohol...).

### Configuracion (`configuracion.php`)

**Franjas horarias:**  
Define a que hora corresponde cada toma del dia. La app las usa para
determinar que toma mostrar al paciente en tiempo real.

| Toma | Ejemplo inicio | Ejemplo fin |
|---|---|---|
| Desayuno | 07:00 | 09:30 |
| Media mañana | 10:00 | 11:30 |
| Almuerzo | 12:00 | 13:30 |
| Comida | 14:00 | 15:30 |
| Merienda | 17:00 | 18:30 |
| Cena | 20:00 | 22:00 |

**SMTP (opcional):**  
Configuracion del servidor de correo para enviar bienvenidas automaticas
a los pacientes al registrarlos. Ver seccion "Configuracion opcional (SMTP)".

**Gestion de usuarios:**  
Panel collapsible para crear, editar, activar/desactivar y eliminar
cuentas de acceso al panel de gestion. Se pueden tener multiples
profesionales con cuentas propias.

---

## Configuracion opcional (SMTP)

El sistema puede enviar un correo de bienvenida al paciente cuando se le registra.
Este correo incluye su codigo de acceso y las instrucciones para usar la app.

Para activarlo, editar `backend-php/config/settings.json`:

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

Para Gmail se necesita una **Contrasena de aplicacion** (no la contrasena normal):
Google Account → Seguridad → Verificacion en dos pasos → Contrasenas de aplicacion

Tambien se puede configurar desde el propio panel: Configuracion → seccion SMTP.
Desde ahi hay un boton para enviar un **correo de prueba** y verificar que funciona.

Si el SMTP no esta configurado o falla, el paciente se crea igualmente.
Solo no se envia el correo.

---

## Estructura de la base de datos

Se puede explorar visualmente en **phpMyAdmin** (http://localhost:8081).

| Tabla | Descripcion |
|---|---|
| `clientes` | Datos del paciente: nombre, peso, talla, objetivo, codigo unico de acceso |
| `ingredientes` | Catalogo de alimentos con valores nutricionales y categoria |
| `dietas` | Protocolo asignado a un cliente (tipo, nombre, fecha, agua, suplementos) |
| `dieta_ingredientes` | Alimentos de una dieta general (relacion dieta-ingrediente) |
| `dieta_semanal_comidas` | Ingredientes sueltos de la dieta semanal por dia y toma |
| `dieta_semanal_combinaciones` | Combinaciones principal + complemento por dia y toma |
| `dieta_no_permitidos` | Alimentos restringidos especificos del paciente |
| `no_permitidos` | Lista base de alimentos no recomendados (lacteos, azucares...) |
| `cliente_medidas` | Historial corporal: peso, % grasa, % musculo por fecha |
| `franjas_horarias` | Horario de cada toma (configurable desde el panel) |
| `usuarios` | Cuentas de acceso al panel de gestion (hash bcrypt) |

---

## Solucion de problemas habituales

### El contenedor de la BD tarda en arrancar y la API falla

**Causa:** la API Node.js intenta conectarse a MariaDB antes de que la BD
haya terminado de inicializarse (puede tardar 20-30 segundos la primera vez).

**Solucion:** esperar un minuto y reiniciar solo el contenedor de la API:
```bash
docker restart proyectosolvam-api
```

---

### La base de datos no tiene datos / las tablas no existen

**Causa:** el volumen de Docker ya existia de una ejecucion anterior sin el schema.

**Solucion:** borrar el volumen y volver a crear:
```bash
docker compose down -v     # borra el volumen (se pierden los datos de la BD)
docker compose up -d       # vuelve a crear todo desde cero
```

> Atencion: `down -v` borra todos los datos de la base de datos. Solo usar si
> se quiere hacer un reset completo.

---

### El puerto 8080 ya esta en uso

**Causa:** otro servicio en el ordenador ya usa el puerto 8080 (ej. otro servidor web).

**Solucion:** editar `docker-compose.yml` y cambiar `"8080:80"` por otro puerto
libre, por ejemplo `"8888:80"`. Luego acceder a http://localhost:8888.

Lo mismo aplica para los puertos 8081 (phpMyAdmin) y 3002 (API).

---

### `ionic serve` falla con "ionic: command not found"

**Causa:** Ionic CLI no esta instalada globalmente.

**Solucion:**
```bash
npm install -g @ionic/cli
```

---

### La app Ionic no carga la dieta (error de red o pantalla en blanco)

**Causa:** el contenedor de la API no esta en marcha, o el puerto 3002 no es accesible.

**Verificacion:**
```bash
docker compose ps                     # comprobar que proyectosolvam-api esta Up
curl http://localhost:3002/api/dieta  # debe devolver un JSON o error de parametro
```

Si la API no responde, reiniciarla:
```bash
docker restart proyectosolvam-api
```

---

### Ver los logs de un contenedor para diagnosticar errores

```bash
docker logs proyectosolvam-web        # logs del servidor PHP
docker logs proyectosolvam-db         # logs de MariaDB
docker logs proyectosolvam-api        # logs de la API Node.js
docker logs proyectosolvam-phpmyadmin # logs de phpMyAdmin
```

---

## Parar y reiniciar el sistema

**Parar los contenedores** (los datos se conservan):
```bash
docker compose down
```

**Volver a arrancar** (tras un `down`):
```bash
docker compose up -d
```

**Reset completo** (para y borra todos los datos de la BD):
```bash
docker compose down -v
docker compose up -d
```

**Ver el estado de los contenedores:**
```bash
docker compose ps
```

**Ver todos los logs en tiempo real:**
```bash
docker compose logs -f
```

---

## Resumen rapido de URLs

| Servicio | URL | Credenciales |
|---|---|---|
| Panel de gestion | http://localhost:8080 | admin / admin123 |
| phpMyAdmin | http://localhost:8081 | root / root21. |
| API REST | http://localhost:3002 | — |
| App del paciente | http://localhost:8100 | codigo del cliente |
