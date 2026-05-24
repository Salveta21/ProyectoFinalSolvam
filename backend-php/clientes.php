<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();
$msg = '';

/* Genera un código único de 8 caracteres para el paciente.
   Usamos solo letras y números que no se confunden visualmente:
   sin O/0 ni I/1. El bucle repite si por casualidad ya existe ese código en la BD. */
function generarCodigo(mysqli $conn): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $existe = $conn->query("SELECT id FROM clientes WHERE codigo='$code'")->num_rows > 0;
    } while ($existe);
    return $code;
}

// Comprobamos que el método sea POST antes de procesar nada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'eliminar' && !empty($_POST['id'])) {
        // Borrado directo del cliente por id (cast a int para evitar inyección SQL)
        $conn->query("DELETE FROM clientes WHERE id=".(int)$_POST['id']);
        $msg = '<div class="alert alert-success">Cliente eliminado.</div>';

    } elseif (in_array($accion, ['crear', 'editar'])) {
        // Recogemos y saneamos los datos del formulario
        // Los campos numéricos (peso, altura) los convertimos a float solo si vienen rellenos
        $nombre        = trim($_POST['nombre'] ?? '');
        $apellidos     = trim($_POST['apellidos'] ?? '');
        $fecha_nac     = (($_POST['fecha_nacimiento'] ?? '') !== '') ? $_POST['fecha_nacimiento'] : null;
        $sexo          = $_POST['sexo'] ?? 'M';
        $peso          = (($_POST['peso_kg'] ?? '') !== '') ? (float)$_POST['peso_kg'] : null;
        $altura        = (($_POST['altura_cm'] ?? '') !== '') ? (float)$_POST['altura_cm'] : null;
        $objetivo      = trim($_POST['objetivo'] ?? '');
        $telefono      = trim($_POST['telefono'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $intolerancias = trim($_POST['intolerancias'] ?? '');

        if ($accion === 'crear') {
            // Comprobamos si ya existe un cliente con el mismo nombre y apellidos para evitar duplicados
            $chk = $conn->prepare("SELECT id FROM clientes WHERE nombre=? AND apellidos=?");
            $chk->bind_param("ss", $nombre, $apellidos);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $msg = '<div class="alert alert-danger">Ya existe un cliente con ese nombre y apellidos.</div>';
            } else {
                // Generamos el código único y lo insertamos con sentencia preparada para evitar SQL injection
                $codigo = generarCodigo($conn);
                $stmt = $conn->prepare("INSERT INTO clientes (nombre,apellidos,fecha_nacimiento,sexo,peso_kg,altura_cm,objetivo,telefono,email,observaciones,codigo,intolerancias) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param("ssssddssssss",
                    $nombre, $apellidos, $fecha_nac, $sexo,
                    $peso, $altura, $objetivo, $telefono, $email, $observaciones, $codigo, $intolerancias
                );
                $stmt->execute();

                /* Si el profesional marcó el checkbox de bienvenida y el email es válido,
                   intentamos enviar el correo. Si falla el SMTP el cliente ya está creado,
                   así que solo mostramos un aviso del error de correo. */
                if (!empty($_POST['enviar_bienvenida']) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    require_once 'config/mailer.php';
                    $enviado = enviarBienvenida($email, $nombre, $codigo);
                    $msg = $enviado
                        ? '<div class="alert alert-success">Cliente creado y correo de bienvenida enviado a ' . htmlspecialchars($email) . '.</div>'
                        : '<div class="alert alert-success">Cliente creado correctamente. <span style="color:#c62828">(El correo no pudo enviarse, revisa la configuración SMTP.)</span></div>';
                } else {
                    $msg = '<div class="alert alert-success">Cliente creado correctamente.</div>';
                }
            }

        } elseif ($accion === 'editar' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            // Actualizar todos los campos del cliente con sentencia preparada
            $stmt = $conn->prepare("UPDATE clientes SET nombre=?,apellidos=?,fecha_nacimiento=?,sexo=?,peso_kg=?,altura_cm=?,objetivo=?,telefono=?,email=?,observaciones=?,intolerancias=? WHERE id=?");
            $stmt->bind_param("ssssddsssssi",
                $nombre, $apellidos, $fecha_nac, $sexo,
                $peso, $altura, $objetivo, $telefono, $email, $observaciones, $intolerancias, $id
            );
            $stmt->execute();
            // Redirigimos para evitar que al refrescar la página se reenvíe el formulario
            header("Location: clientes.php?editado=1");
            exit;
        }
    }
}

if (isset($_GET['editado'])) {
    $msg = '<div class="alert alert-success">Cliente actualizado correctamente.</div>';
}

// Traemos todos los clientes para la tabla, ordenados por nombre
$clientes = $conn->query("SELECT * FROM clientes ORDER BY nombre");

// Si viene ?editar=ID en la URL, cargamos los datos de ese cliente para rellenar el formulario
$cliente_editar = null;
if (isset($_GET['editar'])) {
    $eid = (int)$_GET['editar'];
    $res = $conn->query("SELECT * FROM clientes WHERE id=$eid");
    $cliente_editar = $res ? $res->fetch_assoc() : null;
}
// El formulario se muestra si estamos creando (nuevo) o editando
$mostrarForm = isset($_GET['nuevo']) || $cliente_editar !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clientes – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477153">
<script src="js/theme-init.js"></script>
<style>
.acciones { display:flex; gap:4px; align-items:center; flex-wrap:nowrap; }
.btn-accion {
    padding: 4px 10px;
    font-size: .75rem;
    font-weight: 600;
    border-radius: 6px;
    border: 1.5px solid transparent;
    cursor: pointer;
    white-space: nowrap;
    text-decoration: none;
    display: inline-block;
    font-family: inherit;
    transition: opacity .15s;
}
.btn-accion:hover { opacity: .82; }
.btn-accion.gris   { background:#f0f0f2; color:#1d1d1f; border-color:#d0d0d5; }
.btn-accion.verde  { background:#f0fdf4; color:#166534; border-color:#86efac; }
.btn-accion.azul   { background:#0071e3; color:#fff;    border-color:#0071e3; }
.btn-accion.morado { background:#5e5ce6; color:#fff;    border-color:#5e5ce6; }
.btn-accion.rojo   { background:#ff3b30; color:#fff;    border-color:#ff3b30; }
[data-theme="dark"] .btn-accion.gris  { background:#3a3a3c; color:#f5f5f7; border-color:#48484a; }
[data-theme="dark"] .btn-accion.verde { background:rgba(52,199,89,.15); color:#34c759; border-color:rgba(52,199,89,.3); }
</style>
</head>
<body>
<header>
    <h1>🥗 <?= htmlspecialchars(APP_NAME) ?></h1>
    <nav>
        <a href="index.php">Inicio</a>
        <a href="clientes.php">Clientes</a>
        <a href="ingredientes.php">Ingredientes</a>
        <a href="configuracion.php">Configuración</a>
        <a href="guia.php">Guía</a>
        <a href="logout.php" style="color:var(--text-muted)">Salir</a>
    </nav>
</header>
<div class="container">
<?= $msg ?>

<?php if ($mostrarForm):
    $ce = $cliente_editar;
    $es_edicion = $ce !== null;
?>
<div class="card">
    <h2><?= $es_edicion ? 'Editar cliente' : 'Nuevo cliente' ?></h2>
    <form method="POST">
        <input type="hidden" name="accion" value="<?= $es_edicion ? 'editar' : 'crear' ?>">
        <?php if ($es_edicion): ?>
        <input type="hidden" name="id" value="<?= $ce['id'] ?>">
        <?php endif; ?>
        <div class="grid-2">
            <div><label>Nombre *</label><input type="text" name="nombre" required value="<?= htmlspecialchars($ce['nombre'] ?? '') ?>"></div>
            <div><label>Apellidos</label><input type="text" name="apellidos" value="<?= htmlspecialchars($ce['apellidos'] ?? '') ?>"></div>
            <div><label>Fecha nacimiento</label><input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($ce['fecha_nacimiento'] ?? '') ?>"></div>
            <div><label>Sexo</label>
                <select name="sexo">
                    <option value="M" <?= ($ce['sexo'] ?? 'M') === 'M' ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($ce['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
            <div><label>Peso (kg)</label><input type="number" step="0.01" name="peso_kg" value="<?= htmlspecialchars($ce['peso_kg'] ?? '') ?>"></div>
            <div><label>Altura (cm)</label><input type="number" step="0.1" name="altura_cm" value="<?= htmlspecialchars($ce['altura_cm'] ?? '') ?>"></div>
            <div><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($ce['telefono'] ?? '') ?>"></div>
            <div><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($ce['email'] ?? '') ?>"></div>
        </div>
        <label>Objetivo</label><input type="text" name="objetivo" value="<?= htmlspecialchars($ce['objetivo'] ?? '') ?>">
        <label>Intolerancias / Alergias</label>
        <textarea name="intolerancias" rows="2" placeholder="Ej: Gluten, Lactosa, Fructosa..."><?= htmlspecialchars($ce['intolerancias'] ?? '') ?></textarea>
        <label>Observaciones</label><textarea name="observaciones"><?= htmlspecialchars($ce['observaciones'] ?? '') ?></textarea>

        <?php if (!$es_edicion): ?>
        <div style="margin-top:16px;padding:14px 16px;background:var(--bg-color);border:1px solid var(--border-color);border-radius:10px;display:flex;align-items:center;gap:10px">
            <input type="checkbox" name="enviar_bienvenida" id="chk-bienvenida" value="1"
                   style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary)">
            <label for="chk-bienvenida" style="margin:0;cursor:pointer;font-size:.9rem;color:var(--text-color)">
                Enviar correo de bienvenida con el código de acceso
                <span style="font-size:.8rem;color:var(--text-muted);display:block;margin-top:1px">Solo si el cliente tiene email. El correo se envía al guardar.</span>
            </label>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="submit" class="btn btn-primary"><?= $es_edicion ? 'Guardar cambios' : 'Guardar cliente' ?></button>
            <a href="clientes.php" class="btn" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:12px">
        <h2 style="margin:0">Clientes</h2>
        <a href="clientes.php?nuevo=1" class="btn btn-primary btn-sm">+ Nuevo cliente</a>
    </div>
    <div style="margin-bottom:14px">
        <input type="search" id="buscador-clientes" placeholder="Buscar por nombre, apellidos o teléfono…"
               style="width:100%;max-width:420px;padding:8px 12px;border-radius:8px;font-size:.9rem;"
               oninput="filtrarClientes(this.value)">
    </div>
    <div class="table-responsive">
    <table>
        <thead><tr><th>Nombre</th><th>Código</th><th>Sexo</th><th>Peso</th><th>Altura</th><th>Teléfono</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if ($clientes && $clientes->num_rows > 0): while($c=$clientes->fetch_assoc()):
            $search_val = strtolower($c['nombre'].' '.$c['apellidos'].' '.($c['telefono'] ?? ''));
        ?>
        <tr data-search="<?= htmlspecialchars($search_val) ?>">
            <td><?= htmlspecialchars($c['nombre'].' '.$c['apellidos']) ?></td>
            <td>
                <?php if ($c['codigo']): ?>
                <span style="font-family:monospace;font-size:.85rem;letter-spacing:.05em;color:var(--text-color)"><?= htmlspecialchars($c['codigo']) ?></span>
                <button type="button" title="Copiar código"
                        onclick="navigator.clipboard.writeText('<?= htmlspecialchars($c['codigo']) ?>').then(()=>{this.textContent='✓';setTimeout(()=>this.textContent='⎘',1200)})"
                        style="background:none;border:none;cursor:pointer;color:#0071e3;font-size:.85rem;padding:0 4px">⎘</button>
                <?php else: ?>
                <span style="color:#aaa;font-size:.8rem">—</span>
                <?php endif; ?>
            </td>
            <td><?= $c['sexo']==='F' ? 'Femenino' : 'Masculino' ?></td>
            <td><?= $c['peso_kg'] ? $c['peso_kg'].' kg' : '–' ?></td>
            <td><?= $c['altura_cm'] ? $c['altura_cm'].' cm' : '–' ?></td>
            <td><?= htmlspecialchars($c['telefono'] ?? '–') ?></td>
            <td>
                <div class="acciones">
                    <a href="clientes.php?editar=<?= $c['id'] ?>" class="btn-accion gris">Editar</a>
                    <a href="cliente_seguimiento.php?cliente=<?= $c['id'] ?>" class="btn-accion verde">Seguimiento</a>
                    <a href="dieta_nueva.php?cliente=<?= $c['id'] ?>" class="btn-accion azul">Nueva dieta</a>
                    <a href="dieta_lista.php?cliente=<?= $c['id'] ?>" class="btn-accion morado">Ver dietas</a>
                    <form method="POST" style="display:contents" onsubmit="return confirm('¿Eliminar cliente?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="btn-accion rojo" type="submit">Eliminar</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
        <tr id="no-resultados" style="display:none"><td colspan="7" style="text-align:center;color:#999">Sin resultados para esa búsqueda.</td></tr>
        <?php else: ?>
        <tr><td colspan="7" style="text-align:center;color:#999">Sin clientes todavía. <a href="clientes.php?nuevo=1">Crear el primero</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
<script>
// Buscador client-side: filtra las filas de la tabla por nombre, apellidos o teléfono
// sin necesidad de hacer una petición al servidor
function filtrarClientes(q) {
    const term = q.trim().toLowerCase();
    const filas = document.querySelectorAll('table tbody tr[data-search]');
    let visibles = 0;
    filas.forEach(tr => {
        const match = !term || tr.dataset.search.includes(term);
        tr.style.display = match ? '' : 'none';
        if (match) visibles++;
    });
    // Mostramos la fila "Sin resultados" solo si hay texto y ninguna fila coincide
    const empty = document.getElementById('no-resultados');
    if (empty) empty.style.display = (visibles === 0 && term) ? '' : 'none';
}
</script>
<script src="js/theme.js"></script>
</body>
</html>
