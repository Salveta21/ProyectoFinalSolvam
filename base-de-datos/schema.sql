-- ============================================================
-- Proyecto Dieta – Schema completo
-- Seguro para ejecutar sobre BD existente (IF NOT EXISTS)
--
-- Uso (instalación limpia o actualización):
--   docker exec -i proyectosolvam-db mariadb -uroot -proot21. clinica_dietas < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS clinica_dietas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_spanish_ci;
USE clinica_dietas;
SET NAMES utf8mb4;

-- ── clientes ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clientes (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100)        NOT NULL,
    apellidos        VARCHAR(150),
    fecha_nacimiento DATE,
    sexo             ENUM('M','F')       DEFAULT 'M',
    peso_kg          DECIMAL(5,2),
    altura_cm        DECIMAL(5,1),
    objetivo         TEXT,
    telefono         VARCHAR(20),
    email            VARCHAR(100),
    observaciones    TEXT,
    fecha_alta       TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    codigo           VARCHAR(10)         UNIQUE DEFAULT NULL
        COMMENT 'Identificador único del paciente para el frontend Angular'
);

-- Generar código para clientes existentes que no lo tengan
UPDATE clientes
    SET codigo = UPPER(SUBSTR(MD5(CONCAT(id, UNIX_TIMESTAMP(), RAND())), 1, 8))
    WHERE codigo IS NULL;

-- ── categorias_ingredientes ───────────────────────────────────
-- Tabla existente en BD pero las categorías se gestionan desde categorias.json
CREATE TABLE IF NOT EXISTS categorias_ingredientes (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50)  NOT NULL UNIQUE,
    emoji  VARCHAR(10),
    label  VARCHAR(50)  NOT NULL,
    orden  INT          DEFAULT 0
);

-- ── ingredientes ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ingredientes (
    id                    INT AUTO_INCREMENT PRIMARY KEY,
    nombre                VARCHAR(150) NOT NULL,
    categoria             VARCHAR(50)  NOT NULL DEFAULT 'verdura',
    calorias_por_100      DECIMAL(7,2) DEFAULT 0,
    proteinas_por_100     DECIMAL(7,2) DEFAULT 0,
    carbohidratos_por_100 DECIMAL(7,2) DEFAULT 0,
    grasas_por_100        DECIMAL(7,2) DEFAULT 0,
    no_permitido_base     TINYINT(1)   DEFAULT 0,
    activo                TINYINT(1)   DEFAULT 1
);

-- ── dietas ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dietas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id     INT          NOT NULL,
    fecha          DATE         NOT NULL,
    nombre         VARCHAR(150)          DEFAULT 'Protocolo alimentario',
    tipo           ENUM('general','semanal') DEFAULT 'general',
    nota_huevos    VARCHAR(255)          DEFAULT '2 por comida / 6 a la semana',
    condimentos    TEXT,
    observaciones  TEXT,
    tomas_activas  TEXT         DEFAULT NULL
        COMMENT 'JSON array con las tomas visibles en el informe semanal. NULL = todas activas.',
    fecha_creacion TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- ── dieta_ingredientes ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS dieta_ingredientes (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    dieta_id       INT NOT NULL,
    ingrediente_id INT NOT NULL,
    UNIQUE KEY uk_dieta_ing (dieta_id, ingrediente_id),
    FOREIGN KEY (dieta_id)       REFERENCES dietas(id)       ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id)
);

-- ── no_permitidos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS no_permitidos (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1)   DEFAULT 1
);

-- ── dieta_no_permitidos ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS dieta_no_permitidos (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    dieta_id       INT NOT NULL,
    ingrediente_id INT NOT NULL,
    UNIQUE KEY uk_dieta_noperm (dieta_id, ingrediente_id),
    FOREIGN KEY (dieta_id)       REFERENCES dietas(id)       ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id)
);

-- ── dieta_semanal_comidas ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS dieta_semanal_comidas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    dieta_id       INT         NOT NULL,
    dia            TINYINT     NOT NULL,  -- 1=Lunes … 7=Domingo
    toma           VARCHAR(30) NOT NULL,  -- desayuno | media_manana | almuerzo | comida | merienda | cena
    ingrediente_id INT         NOT NULL,
    FOREIGN KEY (dieta_id)       REFERENCES dietas(id)       ON DELETE CASCADE,
    FOREIGN KEY (ingrediente_id) REFERENCES ingredientes(id)
);
CREATE INDEX IF NOT EXISTS idx_semanal_comidas ON dieta_semanal_comidas (dieta_id, dia, toma);

-- ── dieta_semanal_combinaciones ───────────────────────────────
CREATE TABLE IF NOT EXISTS dieta_semanal_combinaciones (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    dieta_id             INT         NOT NULL,
    dia                  TINYINT     NOT NULL,  -- 1=Lunes … 7=Domingo
    toma                 VARCHAR(30) NOT NULL,  -- desayuno | media_manana | almuerzo | comida | merienda | cena
    principal_id         INT         NOT NULL,
    cantidad_principal   VARCHAR(50) DEFAULT '',
    complemento_id       INT         DEFAULT NULL,
    cantidad_complemento VARCHAR(50) DEFAULT '',
    FOREIGN KEY (dieta_id)        REFERENCES dietas(id)       ON DELETE CASCADE,
    FOREIGN KEY (principal_id)    REFERENCES ingredientes(id),
    FOREIGN KEY (complemento_id)  REFERENCES ingredientes(id)
);
CREATE INDEX IF NOT EXISTS idx_semanal_combos ON dieta_semanal_combinaciones (dieta_id, dia, toma);

-- ── cliente_medidas ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cliente_medidas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id    INT          NOT NULL,
    fecha         DATE         NOT NULL,
    peso_kg       DECIMAL(5,2) DEFAULT NULL,
    grasa_pct     DECIMAL(5,2) DEFAULT NULL,
    musculo_pct   DECIMAL(5,2) DEFAULT NULL,
    observaciones TEXT         DEFAULT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_medidas_cliente ON cliente_medidas (cliente_id, fecha);

-- ============================================================
-- DATOS INICIALES (se insertan solo si las tablas están vacías)
-- ============================================================

INSERT INTO categorias_ingredientes (nombre, emoji, label, orden)
SELECT nombre, emoji, label, orden FROM (
    SELECT 'verdura'    AS nombre, '🍅' AS emoji, 'Verduras'    AS label, 1 AS orden
    UNION ALL SELECT 'carne',      '🍗', 'Carne',       2
    UNION ALL SELECT 'pescado',    '🐟', 'Pescado',     3
    UNION ALL SELECT 'fruta',      '🍏', 'Fruta',       4
    UNION ALL SELECT 'condimento', '🧂', 'Condimentos', 5
) AS nuevas
WHERE NOT EXISTS (SELECT 1 FROM categorias_ingredientes);

INSERT INTO ingredientes (nombre, categoria, no_permitido_base)
SELECT nombre, categoria, no_permitido_base FROM (
    -- Verduras permitidas
    SELECT 'Acelgas' AS nombre, 'verdura' AS categoria, 0 AS no_permitido_base
    UNION ALL SELECT 'Ajo',                 'verdura', 0
    UNION ALL SELECT 'Alcachofa',           'verdura', 0
    UNION ALL SELECT 'Apio',                'verdura', 0
    UNION ALL SELECT 'Berenjena',           'verdura', 0
    UNION ALL SELECT 'Brócoli',             'verdura', 0
    UNION ALL SELECT 'Calabacín',           'verdura', 0
    UNION ALL SELECT 'Cebolla',             'verdura', 0
    UNION ALL SELECT 'Cebolleta',           'verdura', 0
    UNION ALL SELECT 'Champiñones',         'verdura', 0
    UNION ALL SELECT 'Chucrut',             'verdura', 0
    UNION ALL SELECT 'Col (de todo tipo)',  'verdura', 0
    UNION ALL SELECT 'Coliflor',            'verdura', 0
    UNION ALL SELECT 'Endivias',            'verdura', 0
    UNION ALL SELECT 'Espárragos',          'verdura', 0
    UNION ALL SELECT 'Espinacas',           'verdura', 0
    UNION ALL SELECT 'Hinojo',              'verdura', 0
    UNION ALL SELECT 'Judías verdes',       'verdura', 0
    UNION ALL SELECT 'Encurtidos',          'verdura', 0
    UNION ALL SELECT 'Konjac',              'verdura', 0
    UNION ALL SELECT 'Lechugas',            'verdura', 0
    UNION ALL SELECT 'Pepino',              'verdura', 0
    UNION ALL SELECT 'Pimientos',           'verdura', 0
    UNION ALL SELECT 'Puerro',              'verdura', 0
    UNION ALL SELECT 'Rábano',              'verdura', 0
    UNION ALL SELECT 'Rúcula',              'verdura', 0
    UNION ALL SELECT 'Setas',               'verdura', 0
    UNION ALL SELECT 'Tomate',              'verdura', 0
    -- Verduras no permitidas
    UNION ALL SELECT 'Aguacate',            'verdura', 1
    UNION ALL SELECT 'Maíz',                'verdura', 1
    UNION ALL SELECT 'Remolacha',           'verdura', 1
    UNION ALL SELECT 'Guisantes',           'verdura', 1
    UNION ALL SELECT 'Calabaza',            'verdura', 1
    UNION ALL SELECT 'Zanahoria',           'verdura', 1
    UNION ALL SELECT 'Patata',              'verdura', 1
    -- Carne
    UNION ALL SELECT 'Pechuga de pollo',            'carne', 0
    UNION ALL SELECT 'Pechuga de pavo',             'carne', 0
    UNION ALL SELECT 'Filete de ternera sin grasa', 'carne', 0
    -- Pescado
    UNION ALL SELECT 'Langostinos',             'pescado', 0
    UNION ALL SELECT 'Lenguado',                'pescado', 0
    UNION ALL SELECT 'Lubina',                  'pescado', 0
    UNION ALL SELECT 'Lucio',                   'pescado', 0
    UNION ALL SELECT 'Mejillones',              'pescado', 0
    UNION ALL SELECT 'Merluza',                 'pescado', 0
    UNION ALL SELECT 'Perca',                   'pescado', 0
    UNION ALL SELECT 'Platija',                 'pescado', 0
    UNION ALL SELECT 'Rape',                    'pescado', 0
    UNION ALL SELECT 'Sepia',                   'pescado', 0
    UNION ALL SELECT 'Almejas',                 'pescado', 0
    UNION ALL SELECT 'Atún fresco',             'pescado', 0
    UNION ALL SELECT 'Atún conserva (natural)', 'pescado', 0
    UNION ALL SELECT 'Bacalao',                 'pescado', 0
    UNION ALL SELECT 'Berberechos',             'pescado', 0
    UNION ALL SELECT 'Calamar',                 'pescado', 0
    UNION ALL SELECT 'Camarones',               'pescado', 0
    UNION ALL SELECT 'Cangrejo',                'pescado', 0
    UNION ALL SELECT 'Dorada sin piel',         'pescado', 0
    UNION ALL SELECT 'Fletán',                  'pescado', 0
    UNION ALL SELECT 'Gambas',                  'pescado', 0
    UNION ALL SELECT 'Langosta',                'pescado', 0
    UNION ALL SELECT 'Moluscos',                'pescado', 0
    UNION ALL SELECT 'Salmón',                  'pescado', 1
    -- Fruta
    UNION ALL SELECT 'Kiwi',                        'fruta', 0
    UNION ALL SELECT 'Manzana verde (Granny Smith)', 'fruta', 0
    UNION ALL SELECT 'Frutos Rojos',                'fruta', 0
    -- Condimentos
    UNION ALL SELECT 'Limón',                                      'condimento', 0
    UNION ALL SELECT 'Vinagre de manzana',                         'condimento', 0
    UNION ALL SELECT 'Vinagre con hierbas',                        'condimento', 0
    UNION ALL SELECT 'Vinagre de vino',                            'condimento', 0
    UNION ALL SELECT 'Mostaza en grano sin azúcar (Maille Dijon)', 'condimento', 0
    UNION ALL SELECT 'Sal',                                        'condimento', 0
    UNION ALL SELECT 'Sal rosa de Himalaya',                       'condimento', 0
    UNION ALL SELECT 'Pimienta',                                   'condimento', 0
    UNION ALL SELECT 'Hierbas aromáticas',                         'condimento', 0
    UNION ALL SELECT 'Estevia o eritritol',                        'condimento', 0
    UNION ALL SELECT 'Caldo vegetal',                              'condimento', 0
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM ingredientes);

INSERT INTO no_permitidos (nombre)
SELECT nombre FROM (
    SELECT 'Alcohol' AS nombre
    UNION ALL SELECT 'Lácteos'
    UNION ALL SELECT 'Refrescos'
    UNION ALL SELECT 'Azúcar'
    UNION ALL SELECT 'Bebidas vegetales'
    UNION ALL SELECT 'Carbohidratos'
    UNION ALL SELECT 'Frutos secos'
    UNION ALL SELECT 'Grasas'
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM no_permitidos);

-- ── franjas_horarias ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS franjas_horarias (
    toma VARCHAR(30) PRIMARY KEY,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL
);

INSERT IGNORE INTO franjas_horarias (toma, hora_inicio, hora_fin) VALUES
('desayuno', '07:00:00', '10:30:00'),
('media_manana', '10:30:00', '13:00:00'),
('almuerzo', '13:00:00', '15:00:00'),
('comida', '15:00:00', '17:00:00'),
('merienda', '17:00:00', '20:00:00'),
('cena', '20:00:00', '23:58:00');

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS intolerancias    TEXT DEFAULT NULL;
ALTER TABLE dietas   ADD COLUMN IF NOT EXISTS agua             VARCHAR(100) DEFAULT NULL;
ALTER TABLE dietas   ADD COLUMN IF NOT EXISTS complementos     TEXT DEFAULT NULL;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS dieta_activa_id  INT DEFAULT NULL;
ALTER TABLE clientes ADD FOREIGN KEY IF NOT EXISTS (dieta_activa_id) REFERENCES dietas(id) ON DELETE SET NULL;

-- ── configuracion ─────────────────────────────────────────────
-- Pares clave/valor para configuración global (accesibles desde la API)
CREATE TABLE IF NOT EXISTS configuracion (
    clave   VARCHAR(50) NOT NULL PRIMARY KEY,
    valor   TEXT        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO configuracion (clave, valor)
SELECT 'empresa', 'Proyecto Dieta'
WHERE NOT EXISTS (SELECT 1 FROM configuracion WHERE clave = 'empresa');

-- ── usuarios ───────────────────────────────────────────────────
-- Tabla de usuarios del backend (autenticación por sesión PHP)
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100)  NOT NULL,
    username      VARCHAR(50)   NOT NULL UNIQUE,
    password_hash VARCHAR(255)  NOT NULL,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- DATOS DE EJEMPLO
-- Cliente demo + dieta semanal de 7 días para el profesor
-- Solo se insertan si no existe el código DEMO2024
-- ============================================================

SET @demo_codigo = 'DEMO2024';

INSERT INTO clientes (nombre, apellidos, fecha_nacimiento, sexo, peso_kg, altura_cm, objetivo, telefono, email, codigo)
SELECT 'Laura', 'Martínez Sánchez', '1988-07-22', 'F', 72.50, 168.0,
    'Perder peso y mejorar la composición corporal. Reducir grasa abdominal.',
    '612345678', 'laura@ejemplo.com', @demo_codigo
WHERE NOT EXISTS (SELECT 1 FROM clientes WHERE codigo = @demo_codigo);

SET @demo_cid = (SELECT id FROM clientes WHERE codigo = @demo_codigo);

INSERT INTO dietas (cliente_id, fecha, nombre, tipo, nota_huevos, agua, complementos, observaciones)
SELECT @demo_cid, '2026-01-15', 'Protocolo Detox — Semana 1', 'semanal',
    '2 por comida / 6 a la semana',
    '2 litros de agua al día, preferiblemente templada',
    'Omega-3 1g con el almuerzo · Vitamina D 2000 UI con la comida',
    'Cocinar al vapor o a la plancha. Sin sal añadida. Evitar alimentos procesados.'
WHERE NOT EXISTS (SELECT 1 FROM dietas WHERE cliente_id = @demo_cid);

SET @demo_did = (SELECT id FROM dietas WHERE cliente_id = @demo_cid ORDER BY id LIMIT 1);

UPDATE clientes SET dieta_activa_id = @demo_did
WHERE id = @demo_cid AND dieta_activa_id IS NULL;

-- No permitidos personalizados del paciente (ingredientes con restricción base)
INSERT IGNORE INTO dieta_no_permitidos (dieta_id, ingrediente_id)
SELECT @demo_did, id FROM ingredientes
WHERE nombre IN ('Aguacate','Maíz','Remolacha','Guisantes','Calabaza','Zanahoria','Patata','Salmón')
  AND activo = 1;

-- ── Ingredientes sueltos: desayunos y meriendas ───────────────
INSERT INTO dieta_semanal_comidas (dieta_id, dia, toma, ingrediente_id)
SELECT dieta_id, dia, toma, ingrediente_id FROM (
    -- Desayunos
    SELECT @demo_did AS dieta_id, 1 AS dia, 'desayuno' AS toma,
           (SELECT id FROM ingredientes WHERE nombre='Kiwi' AND activo=1 LIMIT 1) AS ingrediente_id
    UNION ALL SELECT @demo_did, 1, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Hierbas aromáticas' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 2, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Frutos Rojos' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 2, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Estevia o eritritol' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 3, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Manzana verde (Granny Smith)' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 4, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Kiwi' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 5, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Frutos Rojos' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 6, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Manzana verde (Granny Smith)' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 7, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Kiwi' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 7, 'desayuno',
           (SELECT id FROM ingredientes WHERE nombre='Hierbas aromáticas' AND activo=1 LIMIT 1)
    -- Meriendas
    UNION ALL SELECT @demo_did, 1, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Manzana verde (Granny Smith)' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 2, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Kiwi' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 3, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Frutos Rojos' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 4, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Manzana verde (Granny Smith)' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 5, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Kiwi' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 6, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Frutos Rojos' AND activo=1 LIMIT 1)
    UNION ALL SELECT @demo_did, 7, 'merienda',
           (SELECT id FROM ingredientes WHERE nombre='Manzana verde (Granny Smith)' AND activo=1 LIMIT 1)
) AS sueltos
WHERE NOT EXISTS (SELECT 1 FROM dieta_semanal_comidas WHERE dieta_id = @demo_did);

-- ── Combinaciones (plato principal + complemento) por día y toma ─
INSERT INTO dieta_semanal_combinaciones (dieta_id, dia, toma, principal_id, cantidad_principal, complemento_id, cantidad_complemento)
SELECT dieta_id, dia, toma, principal_id, cantidad_principal, complemento_id, cantidad_complemento FROM (
    -- Lunes
    SELECT @demo_did AS dieta_id, 1 AS dia, 'almuerzo' AS toma,
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pollo' AND activo=1 LIMIT 1) AS principal_id,
           '150g' AS cantidad_principal,
           (SELECT id FROM ingredientes WHERE nombre='Lechugas' AND activo=1 LIMIT 1) AS complemento_id,
           'al gusto' AS cantidad_complemento
    UNION ALL SELECT @demo_did, 1, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Merluza' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Brócoli' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 1, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pavo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Espinacas' AND activo=1 LIMIT 1), 'al gusto'
    -- Martes
    UNION ALL SELECT @demo_did, 2, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pavo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Tomate' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 2, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Dorada sin piel' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Coliflor' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 2, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Filete de ternera sin grasa' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Judías verdes' AND activo=1 LIMIT 1), 'al gusto'
    -- Miércoles
    UNION ALL SELECT @demo_did, 3, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Gambas' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Lechugas' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 3, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pollo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Calabacín' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 3, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Bacalao' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Espárragos' AND activo=1 LIMIT 1), 'al gusto'
    -- Jueves
    UNION ALL SELECT @demo_did, 4, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pavo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Pimientos' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 4, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Merluza' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Champiñones' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 4, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Sepia' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Coliflor' AND activo=1 LIMIT 1), 'al gusto'
    -- Viernes
    UNION ALL SELECT @demo_did, 5, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Filete de ternera sin grasa' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Tomate' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 5, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Dorada sin piel' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Brócoli' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 5, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pollo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Espinacas' AND activo=1 LIMIT 1), 'al gusto'
    -- Sábado
    UNION ALL SELECT @demo_did, 6, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Gambas' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Pimientos' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 6, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pavo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Judías verdes' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 6, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Bacalao' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Calabacín' AND activo=1 LIMIT 1), 'al gusto'
    -- Domingo
    UNION ALL SELECT @demo_did, 7, 'almuerzo',
           (SELECT id FROM ingredientes WHERE nombre='Pechuga de pollo' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Espinacas' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 7, 'comida',
           (SELECT id FROM ingredientes WHERE nombre='Filete de ternera sin grasa' AND activo=1 LIMIT 1), '150g',
           (SELECT id FROM ingredientes WHERE nombre='Espárragos' AND activo=1 LIMIT 1), 'al gusto'
    UNION ALL SELECT @demo_did, 7, 'cena',
           (SELECT id FROM ingredientes WHERE nombre='Merluza' AND activo=1 LIMIT 1), '200g',
           (SELECT id FROM ingredientes WHERE nombre='Brócoli' AND activo=1 LIMIT 1), 'al gusto'
) AS combos
WHERE NOT EXISTS (SELECT 1 FROM dieta_semanal_combinaciones WHERE dieta_id = @demo_did);
