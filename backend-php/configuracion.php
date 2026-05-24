<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . "/config/database.php";
$conn = getConnection();
$settings_file = __DIR__ . '/config/settings.json';
$msg = '';
$usuarios_msg = '';

$defaults = [
    'general' => [
        'empresa' => 'Proyecto Dieta',
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com', 'port' => 587,
        'username' => '', 'password' => '', 'from' => '', 'from_name' => APP_NAME,
    ],
    'email_bienvenida' => [
        'asunto'          => 'Bienvenido/a a ' . APP_NAME . ' – Tu código de acceso',
        'saludo'          => '¡Bienvenido/a, {nombre}!',
        'intro'           => 'Ya formas parte de ' . APP_NAME . '. A continuación encontrarás tu código personal para acceder a tu dieta desde la app.',
        'etiqueta_codigo' => 'Tu código de acceso',
        'instrucciones'   => 'Introduce este código la primera vez que abras la app para ver tu dieta personalizada. Solo necesitarás hacerlo una vez.',
        'pie'             => 'Este mensaje es automático, por favor no respondas a este correo.',
    ],
];

/* Carga la configuración desde el JSON y la fusiona con los defaults.
   Así si en el futuro añadimos nuevas claves, las páginas no se rompen
   aunque el JSON antiguo no las tenga. */
function loadSettings(string $file, array $defaults): array {
    if (!file_exists($file)) return $defaults;
    $saved = json_decode(file_get_contents($file), true) ?? [];
    return [
        'general'          => array_merge($defaults['general'],          $saved['general']          ?? []),
        'smtp'             => array_merge($defaults['smtp'],             $saved['smtp']             ?? []),
        'email_bienvenida' => array_merge($defaults['email_bienvenida'], $saved['email_bienvenida'] ?? []),
    ];
}

$settings = loadSettings($settings_file, $defaults);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'general') {
        // Guardar el nombre de la empresa en settings.json
        $settings['general']['empresa'] = trim($_POST['empresa'] ?? 'Proyecto Dieta');
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        // También en BD para que la API lo exponga al frontend Angular
        $stmt = $conn->prepare("INSERT INTO configuracion (clave, valor) VALUES ('empresa', ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->bind_param('ss', $settings['general']['empresa'], $settings['general']['empresa']);
        $stmt->execute();
        $stmt->close();
        $msg = '<div class="alert alert-success">Configuración general guardada.</div>';

    } elseif ($accion === 'smtp') {
        /* Guardamos la configuración del servidor de correo.
           La contraseña solo se actualiza si el campo viene relleno;
           si está vacío mantenemos la que ya había guardada. */
        $settings['smtp']['host']      = trim($_POST['host']      ?? 'smtp.gmail.com');
        $settings['smtp']['port']      = (int)($_POST['port']     ?? 587);
        $settings['smtp']['username']  = trim($_POST['username']  ?? '');
        $settings['smtp']['from']      = trim($_POST['from']      ?? '');
        $settings['smtp']['from_name'] = trim($_POST['from_name'] ?? 'Proyecto Dieta');
        if (!empty(trim($_POST['password'] ?? ''))) {
            $settings['smtp']['password'] = trim($_POST['password']);
        }
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = '<div class="alert alert-success">Configuración SMTP guardada.</div>';


    } elseif ($accion === 'franjas') {
        /* Actualizamos las franjas horarias de cada toma en la BD.
           Usamos sentencias preparadas para evitar inyección SQL. */
        foreach ($_POST['hora_inicio'] as $toma => $inicio) {
            $fin = $_POST['hora_fin'][$toma];
            $stmt = $conn->prepare("UPDATE franjas_horarias SET hora_inicio=?, hora_fin=? WHERE toma=?");
            $stmt->bind_param("sss", $inicio, $fin, $toma);
            $stmt->execute();
        }
        $msg = '<div class="alert alert-success">Franjas horarias guardadas.</div>';

    } elseif ($accion === 'plantilla') {
        // Guardamos los 6 campos de la plantilla de correo de bienvenida
        $settings['email_bienvenida']['asunto']          = trim($_POST['asunto']          ?? '');
        $settings['email_bienvenida']['saludo']          = trim($_POST['saludo']          ?? '');
        $settings['email_bienvenida']['intro']           = trim($_POST['intro']           ?? '');
        $settings['email_bienvenida']['etiqueta_codigo'] = trim($_POST['etiqueta_codigo'] ?? '');
        $settings['email_bienvenida']['instrucciones']   = trim($_POST['instrucciones']   ?? '');
        $settings['email_bienvenida']['pie']             = trim($_POST['pie']             ?? '');
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = '<div class="alert alert-success">Plantilla guardada.</div>';

    } elseif ($accion === 'test') {
        /* Envío de correo de prueba: mandamos un correo de bienvenida de ejemplo
           a la dirección indicada para verificar que el SMTP funciona bien. */
        $dest = trim($_POST['test_email'] ?? $settings['smtp']['from']);
        if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
            require_once __DIR__ . '/config/mailer.php';
            $ok = enviarBienvenida($dest, 'Paciente Ejemplo', 'A3F9C12B');
            $msg = $ok
                ? '<div class="alert alert-success">Correo de prueba enviado a <strong>' . htmlspecialchars($dest) . '</strong>.</div>'
                : '<div class="alert alert-danger">Error al enviar. Revisa las credenciales SMTP.</div>';
        } else {
            $msg = '<div class="alert alert-danger">Email de destino no válido.</div>';
        }

    } elseif ($accion === 'crear_usuario') {
        $u_nombre   = trim($_POST['u_nombre']   ?? '');
        $u_username = trim($_POST['u_username'] ?? '');
        $u_password = $_POST['u_password'] ?? '';
        if ($u_nombre === '' || $u_username === '' || $u_password === '') {
            $usuarios_msg = '<div class="alert alert-danger">Todos los campos son obligatorios al crear un usuario.</div>';
        } else {
            // Comprobar si el username ya existe
            $st = $conn->prepare("SELECT id FROM usuarios WHERE username=? LIMIT 1");
            $st->bind_param("s", $u_username);
            $st->execute();
            $st->store_result();
            if ($st->num_rows > 0) {
                $usuarios_msg = '<div class="alert alert-danger">El nombre de usuario <strong>' . htmlspecialchars($u_username) . '</strong> ya existe.</div>';
            } else {
                $hash = password_hash($u_password, PASSWORD_DEFAULT);
                $st2  = $conn->prepare("INSERT INTO usuarios (nombre, username, password_hash) VALUES (?, ?, ?)");
                $st2->bind_param("sss", $u_nombre, $u_username, $hash);
                $st2->execute();
                $st2->close();
                $usuarios_msg = '<div class="alert alert-success">Usuario <strong>' . htmlspecialchars($u_username) . '</strong> creado correctamente.</div>';
            }
            $st->close();
        }

    } elseif ($accion === 'editar_usuario') {
        $u_id       = (int)($_POST['u_id'] ?? 0);
        $u_nombre   = trim($_POST['u_nombre']   ?? '');
        $u_username = trim($_POST['u_username'] ?? '');
        $u_password = $_POST['u_password'] ?? '';
        if (!$u_id || $u_nombre === '' || $u_username === '') {
            $usuarios_msg = '<div class="alert alert-danger">Nombre y usuario son obligatorios.</div>';
        } else {
            // Comprobar duplicado de username excluyendo al propio usuario
            $st = $conn->prepare("SELECT id FROM usuarios WHERE username=? AND id != ? LIMIT 1");
            $st->bind_param("si", $u_username, $u_id);
            $st->execute();
            $st->store_result();
            if ($st->num_rows > 0) {
                $usuarios_msg = '<div class="alert alert-danger">El nombre de usuario <strong>' . htmlspecialchars($u_username) . '</strong> ya está en uso.</div>';
            } else {
                if ($u_password !== '') {
                    $hash = password_hash($u_password, PASSWORD_DEFAULT);
                    $st2  = $conn->prepare("UPDATE usuarios SET nombre=?, username=?, password_hash=? WHERE id=?");
                    $st2->bind_param("sssi", $u_nombre, $u_username, $hash, $u_id);
                } else {
                    $st2 = $conn->prepare("UPDATE usuarios SET nombre=?, username=? WHERE id=?");
                    $st2->bind_param("ssi", $u_nombre, $u_username, $u_id);
                }
                $st2->execute();
                $st2->close();
                $usuarios_msg = '<div class="alert alert-success">Usuario actualizado correctamente.</div>';
            }
            $st->close();
        }

    } elseif ($accion === 'eliminar_usuario') {
        $u_id = (int)($_POST['u_id'] ?? 0);
        if ($u_id === (int)$_SESSION['usuario_id']) {
            $usuarios_msg = '<div class="alert alert-danger">No puedes eliminar tu propio usuario.</div>';
        } else {
            // Verificar que no es el último usuario activo
            $n_activos = (int)$conn->query("SELECT COUNT(*) as n FROM usuarios WHERE activo=1")->fetch_assoc()['n'];
            $es_activo = (int)$conn->query("SELECT activo FROM usuarios WHERE id=$u_id")->fetch_assoc()['activo'];
            if ($es_activo && $n_activos <= 1) {
                $usuarios_msg = '<div class="alert alert-danger">No se puede eliminar el único usuario activo.</div>';
            } else {
                $st = $conn->prepare("DELETE FROM usuarios WHERE id=?");
                $st->bind_param("i", $u_id);
                $st->execute();
                $st->close();
                $usuarios_msg = '<div class="alert alert-success">Usuario eliminado.</div>';
            }
        }

    } elseif ($accion === 'toggle_usuario') {
        $u_id = (int)($_POST['u_id'] ?? 0);
        if ($u_id === (int)$_SESSION['usuario_id']) {
            $usuarios_msg = '<div class="alert alert-danger">No puedes desactivarte a ti mismo.</div>';
        } else {
            $u_activo = (int)$conn->query("SELECT activo FROM usuarios WHERE id=$u_id")->fetch_assoc()['activo'];
            if ($u_activo) {
                // Desactivar: verificar que no es el último activo
                $n_activos = (int)$conn->query("SELECT COUNT(*) as n FROM usuarios WHERE activo=1")->fetch_assoc()['n'];
                if ($n_activos <= 1) {
                    $usuarios_msg = '<div class="alert alert-danger">No se puede desactivar el único usuario activo.</div>';
                } else {
                    $conn->query("UPDATE usuarios SET activo=0 WHERE id=$u_id");
                    $usuarios_msg = '<div class="alert alert-success">Usuario desactivado.</div>';
                }
            } else {
                $conn->query("UPDATE usuarios SET activo=1 WHERE id=$u_id");
                $usuarios_msg = '<div class="alert alert-success">Usuario activado.</div>';
            }
        }
    }
}

$s  = $settings['smtp'];
$tpl = $settings['email_bienvenida'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configuración – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477185">
<script src="js/theme-init.js"></script>
<style>
.config-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; }
.preview-frame {
    border: 1.5px solid #e0e0e5; border-radius: 12px;
    overflow: hidden; width: 100%; height: 580px;
    background: #f5f5f7;
}
.section-title {
    font-size: .78rem; font-weight: 700; color: #6e6e73;
    text-transform: uppercase; letter-spacing: .6px;
    margin: 0 0 16px; padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e5;
}
.var-badge {
    display: inline-block; background: #eef2ff; color: #3730a3;
    font-size: .72rem; font-weight: 600; font-family: monospace;
    padding: 1px 6px; border-radius: 4px; margin: 2px;
}
@media (max-width: 860px) { .config-grid { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<header>
    <h1>🥗 <?= htmlspecialchars(APP_NAME) ?></h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="clientes.php">Clientes</a>
        <a href="ingredientes.php">Ingredientes</a>
        <a href="configuracion.php" style="color:#0071e3;font-weight:600">Configuración</a>
        <a href="guia.php">Guía</a>
        <a href="logout.php" style="color:var(--text-muted)">Salir</a>
    </nav>
</header>
<div class="container">
<?= $msg ?>


<!-- ── GENERAL ── -->
<div class="card" style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleConfig('panel-general', 'arrow-general')">
        <p class="section-title" style="margin:0;border:none;padding-bottom:0">⚙️ General</p>
        <span id="arrow-general" style="font-size:1.2rem;transition:transform .2s;transform:rotate(180deg)">▼</span>
    </div>
    <div id="panel-general" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">
        <form method="POST">
            <input type="hidden" name="accion" value="general">
            <div class="grid-2">
                <div><label>Nombre de la Empresa</label><input type="text" name="empresa" value="<?= htmlspecialchars($settings['general']['empresa']) ?>" required></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:16px">Guardar Ajustes</button>
        </form>
    </div>
</div>

<!-- ── FRANJAS HORARIAS ── -->
<?php
// Cargamos las franjas horarias actuales desde la BD para rellenar el formulario
$resFranjas = $conn->query("SELECT * FROM franjas_horarias");
$franjas = [];
if ($resFranjas) {
    while($row = $resFranjas->fetch_assoc()) {
        $franjas[$row['toma']] = $row;
    }
}
$tomasList = ['desayuno' => 'Desayuno', 'media_manana' => 'Media mañana', 'almuerzo' => 'Almuerzo', 'comida' => 'Comida', 'merienda' => 'Merienda', 'cena' => 'Cena'];
if(!empty($franjas)):
?>
<div class="card" style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleConfig('panel-franjas', 'arrow-franjas')">
        <p class="section-title" style="margin:0;border:none;padding-bottom:0">⏱️ Franjas Horarias</p>
        <span id="arrow-franjas" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="panel-franjas" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">
        <form method="POST">
            <input type="hidden" name="accion" value="franjas">
            <div class="grid-3">
                <?php foreach($tomasList as $key => $label): 
                    $inicio = isset($franjas[$key]) ? substr($franjas[$key]['hora_inicio'], 0, 5) : '';
                    $fin = isset($franjas[$key]) ? substr($franjas[$key]['hora_fin'], 0, 5) : '';
                ?>
                <div style="background:var(--input-bg); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color)">
                    <label style="margin-bottom:8px; display:block; color:var(--text-color); font-weight:bold;"><?= $label ?></label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="time" name="hora_inicio[<?= $key ?>]" value="<?= $inicio ?>" style="margin-bottom:0">
                        <span style="color:var(--text-muted)">-</span>
                        <input type="time" name="hora_fin[<?= $key ?>]" value="<?= $fin ?>" style="margin-bottom:0">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:16px">Guardar Franjas</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── SMTP ── -->
<div class="card" style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleConfig('panel-smtp', 'arrow-smtp')">
        <p class="section-title" style="margin:0;border:none;padding-bottom:0">⚙️ Configuración SMTP</p>
        <span id="arrow-smtp" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="panel-smtp" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">
        <form method="POST">
            <input type="hidden" name="accion" value="smtp">
            <div class="grid-2">
                <div><label>Host SMTP</label><input type="text" name="host" value="<?= htmlspecialchars($s['host']) ?>"></div>
                <div><label>Puerto</label><input type="number" name="port" value="<?= (int)$s['port'] ?>"></div>
                <div><label>Usuario (email)</label><input type="email" name="username" value="<?= htmlspecialchars($s['username']) ?>"></div>
                <div>
                    <label>Contraseña de aplicación</label>
                    <input type="password" name="password" placeholder="Dejar vacío para no cambiar" autocomplete="new-password">
                </div>
                <div><label>Email remitente</label><input type="email" name="from" value="<?= htmlspecialchars($s['from']) ?>"></div>
                <div><label>Nombre remitente</label><input type="text" name="from_name" value="<?= htmlspecialchars($s['from_name']) ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:4px">Guardar SMTP</button>
        </form>

        <hr style="margin:20px 0;border:none;border-top:1px solid var(--border-color)">

        <p class="section-title" style="margin-bottom:12px;border:none;padding-bottom:0">🧪 Enviar correo de prueba</p>
        <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="accion" value="test">
            <div style="flex:1;min-width:220px">
                <label>Destinatario</label>
                <input type="email" name="test_email" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($s['from']) ?>">
            </div>
            <button type="submit" class="btn btn-secondary" style="margin-bottom:1px">Enviar prueba</button>
        </form>
    </div>
</div>

<!-- ── PLANTILLA ── -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleConfig('panel-plantilla', 'arrow-plantilla')">
        <p class="section-title" style="margin:0;border:none;padding-bottom:0">✉️ Plantilla correo de bienvenida</p>
        <span id="arrow-plantilla" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="panel-plantilla" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px">
            Puedes usar las variables <span class="var-badge">{nombre}</span> <span class="var-badge">{codigo}</span> en cualquier campo. El preview se actualiza en tiempo real.
        </p>
        <div class="config-grid">
            <!-- Formulario -->
            <form method="POST" id="tpl-form">
                <input type="hidden" name="accion" value="plantilla">
                <label>Asunto del correo</label>
                <input type="text" name="asunto" id="f-asunto" value="<?= htmlspecialchars($tpl['asunto']) ?>">

                <label>Saludo</label>
                <input type="text" name="saludo" id="f-saludo" value="<?= htmlspecialchars($tpl['saludo']) ?>">

                <label>Texto de introducción</label>
                <textarea name="intro" id="f-intro" rows="3"><?= htmlspecialchars($tpl['intro']) ?></textarea>

                <label>Etiqueta del código</label>
                <input type="text" name="etiqueta_codigo" id="f-etiqueta" value="<?= htmlspecialchars($tpl['etiqueta_codigo']) ?>">

                <label>Instrucciones bajo el código</label>
                <textarea name="instrucciones" id="f-instruc" rows="2"><?= htmlspecialchars($tpl['instrucciones']) ?></textarea>

                <label>Texto pie del correo</label>
                <input type="text" name="pie" id="f-pie" value="<?= htmlspecialchars($tpl['pie']) ?>">

                <button type="submit" class="btn btn-primary" style="margin-top:8px">Guardar plantilla</button>
            </form>

            <!-- Preview -->
            <div>
                <label style="display:block;margin-bottom:8px">Vista previa</label>
                <iframe id="preview-iframe" class="preview-frame" srcdoc=""></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ── GESTIÓN DE USUARIOS ── -->
<?php
// Cargamos la lista de usuarios para mostrar en la tabla
$usuarios_lista = [];
$res_u = $conn->query("SELECT id, nombre, username, activo, created_at FROM usuarios ORDER BY id ASC");
if ($res_u) {
    while ($row_u = $res_u->fetch_assoc()) {
        $usuarios_lista[] = $row_u;
    }
}
// Si viene ?editar_usuario=ID cargamos ese usuario para el formulario de edición
$editar_usuario = null;
if (!empty($_GET['editar_usuario'])) {
    $eu_id = (int)$_GET['editar_usuario'];
    $res_eu = $conn->query("SELECT id, nombre, username FROM usuarios WHERE id=$eu_id LIMIT 1");
    if ($res_eu) $editar_usuario = $res_eu->fetch_assoc();
}
?>
<div class="card" style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleConfig('panel-usuarios', 'arrow-usuarios')">
        <p class="section-title" style="margin:0;border:none;padding-bottom:0">👤 Gestión de usuarios</p>
        <span id="arrow-usuarios" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="panel-usuarios" style="display:none;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">

        <?= $usuarios_msg ?>

        <!-- Tabla de usuarios -->
        <div class="table-responsive" style="margin-bottom:24px">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios_lista as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><code><?= htmlspecialchars($u['username']) ?></code><?php if ((int)$u['id'] === (int)$_SESSION['usuario_id']): ?> <span style="font-size:.72rem;background:#eef2ff;color:#3730a3;padding:1px 6px;border-radius:4px;font-weight:600">Tú</span><?php endif; ?></td>
                    <td><?= $u['activo'] ? '<span style="color:#34c759;font-weight:600">Activo</span>' : '<span style="color:#999">Inactivo</span>' ?></td>
                    <td style="font-size:.82rem;color:var(--text-muted)"><?= substr($u['created_at'], 0, 10) ?></td>
                    <td>
                        <a href="?editar_usuario=<?= $u['id'] ?>#panel-usuarios-anchor" class="btn btn-sm" style="background:#f5f5f7;color:#1d1d1f">Editar</a>
                        <!-- Toggle activo/inactivo -->
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="accion" value="toggle_usuario">
                            <input type="hidden" name="u_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm" style="background:<?= $u['activo'] ? '#ff9500' : '#34c759' ?>;color:#fff">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <!-- Eliminar -->
                        <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar usuario <?= htmlspecialchars(addslashes($u['username'])) ?>?')">
                            <input type="hidden" name="accion" value="eliminar_usuario">
                            <input type="hidden" name="u_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a name="panel-usuarios-anchor"></a>

        <!-- Formulario añadir / editar usuario -->
        <p class="section-title" style="margin-bottom:12px;border:none;padding-bottom:0">
            <?= $editar_usuario ? 'Editar usuario' : 'Añadir usuario' ?>
        </p>
        <form method="POST">
            <input type="hidden" name="accion" value="<?= $editar_usuario ? 'editar_usuario' : 'crear_usuario' ?>">
            <?php if ($editar_usuario): ?>
            <input type="hidden" name="u_id" value="<?= $editar_usuario['id'] ?>">
            <?php endif; ?>
            <div class="grid-2" style="margin-bottom:12px">
                <div>
                    <label>Nombre completo</label>
                    <input type="text" name="u_nombre" value="<?= htmlspecialchars($editar_usuario['nombre'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Nombre de usuario</label>
                    <input type="text" name="u_username" value="<?= htmlspecialchars($editar_usuario['username'] ?? '') ?>" required autocomplete="off">
                </div>
                <div>
                    <label>Contraseña<?= $editar_usuario ? ' <span style="font-weight:400;color:var(--text-muted)">(dejar vacío para no cambiar)</span>' : '' ?></label>
                    <input type="password" name="u_password" <?= $editar_usuario ? '' : 'required' ?> autocomplete="new-password">
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" class="btn btn-primary"><?= $editar_usuario ? 'Guardar cambios' : 'Crear usuario' ?></button>
                <?php if ($editar_usuario): ?>
                <a href="configuracion.php#panel-usuarios-anchor" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

</div>

<script>
// Colapsa o expande cada sección de configuración al hacer clic en la cabecera
function toggleConfig(panelId, arrowId) {
    const panel = document.getElementById(panelId);
    const arrow = document.getElementById(arrowId);
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        if (arrow) arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        if (arrow) arrow.style.transform = '';
    }
}

// Datos de ejemplo para el preview del correo de bienvenida
const NOMBRE_DEMO = 'María García';
const CODIGO_DEMO = 'A3F9C12B';

function val(id) { return document.getElementById(id)?.value ?? ''; }

// Sustituye las variables {nombre} y {codigo} del template por los valores de demo
function repl(str) {
    return str.replace(/\{nombre\}/g, NOMBRE_DEMO).replace(/\{codigo\}/g, CODIGO_DEMO);
}

// Construye el HTML del correo de preview usando los valores actuales del formulario
function buildPreview() {
    const saludo   = repl(val('f-saludo'));
    const intro    = repl(val('f-intro'));
    const etiqueta = val('f-etiqueta');
    const instruc  = repl(val('f-instruc'));
    const pie      = val('f-pie');

    return `<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7;padding:24px 12px">
<tr><td align="center">
<table width="100%" style="max-width:480px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
  <tr><td style="background:linear-gradient(135deg,#1c1c1e 0%,#2c2c2e 100%);padding:28px 32px;text-align:center">
    <div style="font-size:20px;font-weight:800;color:#fff;letter-spacing:3px"><?= htmlspecialchars(APP_NAME) ?></div>
    <div style="font-size:12px;color:rgba(255,255,255,.6);margin-top:4px;letter-spacing:1px">NUTRICIÓN · BIENESTAR</div>
  </td></tr>
  <tr><td style="padding:28px 32px">
    <p style="font-size:17px;font-weight:700;color:#1d1d1f;margin:0 0 8px">${saludo}</p>
    <p style="font-size:14px;color:#444;line-height:1.6;margin:0 0 24px">${intro}</p>
    <div style="background:#f5f5f7;border-radius:12px;padding:20px;text-align:center;margin-bottom:24px">
      <div style="font-size:11px;font-weight:600;color:#6e6e73;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px">${etiqueta}</div>
      <div style="font-size:30px;font-weight:800;color:#1d1d1f;letter-spacing:6px;font-family:monospace">${CODIGO_DEMO}</div>
    </div>
    <p style="font-size:13px;color:#6e6e73;line-height:1.6;margin:0">${instruc}</p>
  </td></tr>
  <tr><td style="background:#fafafa;border-top:1px solid #e0e0e5;padding:16px 32px;text-align:center">
    <p style="font-size:11px;color:#aaa;margin:0">${pie}</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>`;
}

function updatePreview() {
    document.getElementById('preview-iframe').srcdoc = buildPreview();
}

// Escuchamos los cambios en cada campo del formulario para actualizar el preview en tiempo real
['f-saludo','f-intro','f-etiqueta','f-instruc','f-pie'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
});

// Mostramos el preview inicial al cargar la página
updatePreview();

// Abrir el panel de usuarios automáticamente si hay un mensaje o se está editando
<?php if ($usuarios_msg || $editar_usuario): ?>
(function() {
    const panel = document.getElementById('panel-usuarios');
    const arrow  = document.getElementById('arrow-usuarios');
    if (panel) { panel.style.display = 'block'; }
    if (arrow)  { arrow.style.transform = 'rotate(180deg)'; }
    // Desplazar hasta el anchor si viene de una acción
    <?php if ($usuarios_msg || $editar_usuario): ?>
    const anchor = document.querySelector('a[name="panel-usuarios-anchor"]');
    if (anchor) setTimeout(() => anchor.scrollIntoView({behavior:'smooth', block:'start'}), 100);
    <?php endif; ?>
})();
<?php endif; ?>
</script>
<script src="js/theme.js"></script>
</body>
</html>
