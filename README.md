# Proyecto Dieta — Sans Clinique

Sistema de gestión de protocolos alimentarios formado por tres componentes:
panel web PHP, API REST Node.js y app móvil Ionic + Angular.

---

## Arquitectura

| Componente | Tecnología | Puerto |
|---|---|---|
| Panel de gestión | PHP 8.2 + Apache | 8080 |
| Base de datos | MariaDB 10.11 | interno |
| phpMyAdmin | phpMyAdmin | 8081 |
| API REST | Node.js 20 + Express | 3002 |
| App del paciente | Ionic + Angular (compilada) | 8100 |

---

## Puesta en marcha

### Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) instalado y en ejecución

### Levantar los contenedores

```bash
docker compose up -d
```

La primera vez tarda varios minutos (descarga imágenes, construye PHP, importa la BD).

### Verificar estado

```bash
docker compose ps
```

Los 5 contenedores deben aparecer como `running`.

---

## URLs y credenciales

| Servicio | URL | Credenciales |
|---|---|---|
| Panel de gestión | http://localhost:8080 | admin / admin123 |
| phpMyAdmin | http://localhost:8081 | root / root21. |
| API REST | http://localhost:3002 | — |
| App del paciente | http://localhost:8100 | código del cliente |

---

## Flujo básico

1. Acceder al panel en http://localhost:8080
2. **Clientes** → crear un cliente (genera un código de 8 caracteres)
3. **Nueva dieta** → crear dieta general o semanal
4. **Ver dietas** → seleccionar la dieta activa en la app
5. El paciente introduce el código en http://localhost:8100

---

## Documentación

- [INSTRUCCIONES.md](INSTRUCCIONES.md) — guía técnica de despliegue
- [GUIA_PROFESOR.md](GUIA_PROFESOR.md) — guía completa con descripción de funcionalidades

---

## Estructura del repositorio

```
├── base-de-datos/        # schema.sql (importado automáticamente por Docker)
├── backend-php/          # Panel de gestión PHP + Apache
├── api-rest/             # API REST Node.js + Express
├── app-movil/            # App Ionic + Angular (código fuente + build compilado en www/)
├── docker/               # Dockerfile PHP y configuración nginx
├── docker-compose.yml    # Orquestación de los 5 contenedores
├── INSTRUCCIONES.md      # Guía de despliegue
└── GUIA_PROFESOR.md      # Guía completa de uso
```
