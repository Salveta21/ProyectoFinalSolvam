/* API REST del proyecto — Node.js + Express
   Expone los datos de la app al frontend Angular (Ionic).
   Solo tiene dos endpoints: /api/cliente y /api/dieta. */
const express = require('express');
const mysql   = require('mysql2/promise');

const app  = express();
const PORT = process.env.PORT || 3001;

/* Middleware CORS: permitimos peticiones desde cualquier origen para que la app
   móvil (que corre en el teléfono) pueda llamar a la API sin problemas.
   Las peticiones OPTIONS son preflight de CORS y las respondemos con 204. */
app.use((req, res, next) => {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    if (req.method === 'OPTIONS') return res.sendStatus(204);
    next();
});

/* Pool de conexiones a MariaDB: reutilizamos conexiones en lugar de abrir
   una nueva por cada petición. Los datos de conexión vienen de variables de entorno
   definidas en el docker-compose del stack Portainer. */
const pool = mysql.createPool({
    host:            process.env.DB_HOST || 'db',
    user:            process.env.DB_USER || 'root',
    password:        process.env.DB_PASS || 'root21.',
    database:        process.env.DB_NAME || 'clinica_dietas',
    waitForConnections: true,
    connectionLimit: 10,
});

/* ── GET /api/config ─────────────────────────────────────────────
   Devuelve la configuración pública del sistema (nombre de la empresa).
   La app la usa en la pantalla de entrada de código. */
app.get('/api/config', async (req, res) => {
    try {
        const [rows] = await pool.query("SELECT valor FROM configuracion WHERE clave = 'empresa'");
        res.json({ empresa: rows.length ? rows[0].valor : 'Proyecto Dieta' });
    } catch (e) {
        res.json({ empresa: 'Proyecto Dieta' });
    }
});

/* ── GET /api/cliente?codigo=XXXXXXXX ────────────────────────────
   Devuelve los datos básicos del paciente identificado por su código único.
   El código de 8 caracteres es el identificador que tiene el paciente en la app. */
app.get('/api/cliente', async (req, res) => {
    const { codigo } = req.query;
    if (!codigo) return res.status(400).json({ error: 'codigo requerido' });

    try {
        const [rows] = await pool.query(
            `SELECT id, nombre, apellidos, sexo, peso_kg, altura_cm, objetivo
             FROM clientes WHERE codigo = ?`,
            [codigo]
        );
        if (!rows.length) return res.status(404).json({ error: 'Cliente no encontrado' });
        res.json(rows[0]);
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

/* ── GET /api/dieta?codigo=XXXXXXXX ──────────────────────────────
   Devuelve la dieta activa del paciente. Puede ser de tipo 'general' o 'semanal'.
   La lógica de selección es:
     1. Si el cliente tiene una dieta fijada (dieta_activa_id != NULL), la usamos.
     2. Si no, devolvemos la más reciente (ORDER BY fecha DESC). */
app.get('/api/dieta', async (req, res) => {
    const { codigo } = req.query;
    if (!codigo) return res.status(400).json({ error: 'codigo requerido' });

    try {
        // Primero obtenemos el cliente para saber si tiene una dieta fijada manualmente
        const [clientes] = await pool.query(
            'SELECT id, dieta_activa_id FROM clientes WHERE codigo = ?', [codigo]
        );
        if (!clientes.length) return res.status(404).json({ error: 'Cliente no encontrado' });

        const cliente_id      = clientes[0].id;
        const dieta_activa_id = clientes[0].dieta_activa_id;

        let dieta = null;

        if (dieta_activa_id) {
            // Intentamos cargar la dieta fijada; verificamos que pertenece a este cliente
            const [fijadas] = await pool.query(
                `SELECT id, nombre, fecha, tipo, nota_huevos, condimentos, tomas_activas, agua, complementos
                 FROM dietas WHERE id = ? AND cliente_id = ?`,
                [dieta_activa_id, cliente_id]
            );
            if (fijadas.length) dieta = fijadas[0];
        }

        // Fallback a la más reciente si no hay dieta fijada o la fijada fue eliminada
        if (!dieta) {
            const [recientes] = await pool.query(
                `SELECT id, nombre, fecha, tipo, nota_huevos, condimentos, tomas_activas, agua, complementos
                 FROM dietas WHERE cliente_id = ?
                 ORDER BY fecha DESC, id DESC LIMIT 1`,
                [cliente_id]
            );
            if (recientes.length) dieta = recientes[0];
        }

        if (!dieta) return res.status(404).json({ error: 'Sin dieta asignada' });

        // Lista de alimentos prohibidos para este paciente (se incluye en ambos tipos de dieta)
        const [noPermitidos] = await pool.query(
            `SELECT i.nombre
             FROM dieta_no_permitidos dn
             JOIN ingredientes i ON i.id = dn.ingrediente_id
             WHERE dn.dieta_id = ? ORDER BY i.nombre`,
            [dieta.id]
        );

        /* Franjas horarias de cada toma: la app las usa para saber qué toma
           es la activa en este momento del día */
        const [franjas] = await pool.query('SELECT toma, hora_inicio, hora_fin FROM franjas_horarias');
        const franjasMap = {};
        franjas.forEach(f => {
            franjasMap[f.toma] = { inicio: f.hora_inicio.substring(0, 5), fin: f.hora_fin.substring(0, 5) };
        });

        // ── Respuesta para dieta de tipo general ────────────────────────────
        if (dieta.tipo === 'general') {
            const [ingredientes] = await pool.query(
                `SELECT i.id, i.nombre, i.categoria
                 FROM dieta_ingredientes di
                 JOIN ingredientes i ON i.id = di.ingrediente_id
                 WHERE di.dieta_id = ? ORDER BY i.categoria, i.nombre`,
                [dieta.id]
            );

            return res.json({
                tipo:             'general',
                franjas_horarias: franjasMap,
                id:               dieta.id,
                nombre:           dieta.nombre,
                fecha:            dieta.fecha,
                nota_huevos:      dieta.nota_huevos,
                condimentos:      dieta.condimentos,
                agua:             dieta.agua || null,
                complementos:     dieta.complementos || null,
                ingredientes,
                no_permitidos:    noPermitidos.map(r => r.nombre),
            });
        }

        // ── Respuesta para dieta de tipo semanal ────────────────────────────
        if (dieta.tipo === 'semanal') {
            /* tomas_activas es un JSON en BD. null significa que todas las tomas están activas.
               La app lo usa para saber qué tomas mostrar en el informe semanal. */
            const tomasActivas = dieta.tomas_activas
                ? JSON.parse(dieta.tomas_activas)
                : null;

            // Combinaciones plato+complemento para todos los días y tomas
            const [combos] = await pool.query(
                `SELECT dsc.dia, dsc.toma,
                        ip.nombre AS principal, dsc.cantidad_principal,
                        ic.nombre AS complemento, dsc.cantidad_complemento
                 FROM dieta_semanal_combinaciones dsc
                 JOIN ingredientes ip ON ip.id = dsc.principal_id
                 LEFT JOIN ingredientes ic ON ic.id = dsc.complemento_id
                 WHERE dsc.dieta_id = ? ORDER BY dsc.dia, dsc.toma`,
                [dieta.id]
            );

            // Ingredientes sueltos (no en combo)
            const [sueltos] = await pool.query(
                `SELECT dsc.dia, dsc.toma, i.nombre, i.categoria
                 FROM dieta_semanal_comidas dsc
                 JOIN ingredientes i ON i.id = dsc.ingrediente_id
                 WHERE dsc.dieta_id = ? ORDER BY dsc.dia, dsc.toma, i.nombre`,
                [dieta.id]
            );

            /* Construimos la estructura dias[1..7][toma] = { combinaciones, sueltos }
               filtrando los arrays de combos y sueltos por día y toma */
            const dias = {};
            for (let d = 1; d <= 7; d++) {
                dias[d] = {};
                for (const toma of ['desayuno','media_manana','almuerzo','comida','merienda','cena']) {
                    dias[d][toma] = {
                        combinaciones: combos.filter(r => r.dia === d && r.toma === toma),
                        sueltos:       sueltos.filter(r => r.dia === d && r.toma === toma).map(r => r.nombre),
                    };
                }
            }

            return res.json({
                tipo:             'semanal',
                franjas_horarias: franjasMap,
                id:               dieta.id,
                nombre:           dieta.nombre,
                fecha:            dieta.fecha,
                tomas_activas:    tomasActivas,
                agua:             dieta.agua || null,
                complementos:     dieta.complementos || null,
                dias,
                no_permitidos:    noPermitidos.map(r => r.nombre),
            });
        }

        res.status(500).json({ error: 'Tipo de dieta desconocido' });

    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

app.listen(PORT, () => console.log(`Proyecto Dieta API corriendo en puerto ${PORT}`));
