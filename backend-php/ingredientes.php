<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();
$msg = '';

/* Si viene el parámetro exportar_ingredientes en la URL, generamos un CSV y lo enviamos
   directamente para descarga. Salimos enseguida para no mostrar HTML. */
if (isset($_GET['exportar_ingredientes'])) {
    $res_exp = $conn->query("SELECT nombre, categoria, calorias_por_100, proteinas_por_100, carbohidratos_por_100, grasas_por_100 FROM ingredientes WHERE activo=1 ORDER BY categoria, nombre");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ingredientes_disponibles.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['nombre','categoria','calorias_por_100','proteinas_por_100','carbohidratos_por_100','grasas_por_100']);
    while ($r = $res_exp->fetch_assoc()) {
        fputcsv($out, [$r['nombre'], $r['categoria'], $r['calorias_por_100'], $r['proteinas_por_100'], $r['carbohidratos_por_100'], $r['grasas_por_100']]);
    }
    fclose($out);
    exit;
}

// Descargar plantilla CSV
if (isset($_GET['csv_plantilla'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_ingredientes.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
    fputcsv($out, ['nombre','categoria','calorias_por_100','proteinas_por_100','carbohidratos_por_100','grasas_por_100','no_permitido_base']);
    fputcsv($out, ['Acelgas','verdura',17,1.8,2.5,0.2,0]);
    fputcsv($out, ['Pechuga de pollo','carne',165,31,0,3.6,0]);
    fputcsv($out, ['Merluza','pescado',73,16.8,0,0.8,0]);
    fputcsv($out, ['Manzana','fruta',52,0.3,14,0.2,0]);
    fputcsv($out, ['Aguacate','fruta',160,2,9,15,1]);
    fclose($out);
    exit;
}

// Cargar categorías desde archivo JSON
$cat_file = __DIR__ . '/categorias.json';
if (file_exists($cat_file)) {
    $cat_labels = json_decode(file_get_contents($cat_file), true);
} else {
    $cat_labels = [
        'verdura'    => '🍅 Verduras',
        'carne'      => '🍗 Carne',
        'pescado'    => '🐟 Pescado',
        'fruta'      => '🍏 Fruta',
        'condimento' => '🧂 Condimentos',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        // Insertar nuevo ingrediente con sentencia preparada para evitar SQL injection
        $stmt = $conn->prepare("INSERT INTO ingredientes (nombre, categoria, calorias_por_100, proteinas_por_100, carbohidratos_por_100, grasas_por_100, no_permitido_base) VALUES (?,?,?,?,?,?,?)");
        $no_base = isset($_POST['no_permitido_base']) ? 1 : 0;
        $stmt->bind_param("ssddddi",
            $_POST['nombre'], $_POST['categoria'],
            $_POST['calorias'], $_POST['proteinas'], $_POST['carbohidratos'], $_POST['grasas'],
            $no_base
        );
        $stmt->execute();
        $msg = '<div class="alert alert-success">Ingrediente añadido.</div>';

    } elseif ($_POST['accion'] === 'crear_categoria') {
        /* Las categorías se guardan en categorias.json, no en la base de datos.
           Verificamos que no exista ya antes de añadir y luego sobreescribimos el JSON completo. */
        $nombre_cat = strtolower(trim($_POST['cat_nombre']));
        $emoji = trim($_POST['cat_emoji']);
        $label = trim($_POST['cat_label']);
        if ($nombre_cat && !isset($cat_labels[$nombre_cat])) {
            $cat_labels[$nombre_cat] = ($emoji ? $emoji . ' ' : '') . $label;
            file_put_contents($cat_file, json_encode($cat_labels, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg = '<div class="alert alert-success">Categoría "' . htmlspecialchars($label) . '" creada. Ahora puedes asignarla a ingredientes.</div>';
        } elseif (isset($cat_labels[$nombre_cat])) {
            $msg = '<div class="alert alert-error">Ya existe una categoría con ese nombre.</div>';
        }

    } elseif ($_POST['accion'] === 'editar' && !empty($_POST['id'])) {
        // Edición inline: actualizamos el ingrediente y redirigimos para evitar reenvío del formulario
        $stmt = $conn->prepare("UPDATE ingredientes SET nombre=?, categoria=?, calorias_por_100=?, proteinas_por_100=?, carbohidratos_por_100=?, grasas_por_100=?, no_permitido_base=? WHERE id=?");
        $no_base = isset($_POST['no_permitido_base']) ? 1 : 0;
        $stmt->bind_param("ssddddii",
            $_POST['nombre'], $_POST['categoria'],
            $_POST['calorias'], $_POST['proteinas'], $_POST['carbohidratos'], $_POST['grasas'],
            $no_base, $_POST['id']
        );
        $stmt->execute();
        header("Location: ingredientes.php?cat=" . urlencode($_POST['categoria']) . "&ok=1");
        exit;

    } elseif ($_POST['accion'] === 'eliminar' && !empty($_POST['id'])) {
        /* Soft delete: ponemos activo=0 en lugar de borrar el registro.
           Así si algún ingrediente está referenciado en dietas antiguas no se rompe nada. */
        $conn->query("UPDATE ingredientes SET activo=0 WHERE id=".(int)$_POST['id']);
        $msg = '<div class="alert alert-success">Ingrediente desactivado.</div>';

    } elseif ($_POST['accion'] === 'borrar_categoria' && !empty($_POST['cat_borrar'])) {
        // Eliminar categoría del JSON (borramos la clave y sobreescribimos el archivo)
        $cat_borrar = $_POST['cat_borrar'];
        if (isset($cat_labels[$cat_borrar])) {
            unset($cat_labels[$cat_borrar]);
            file_put_contents($cat_file, json_encode($cat_labels, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg = '<div class="alert alert-success">Categoría eliminada.</div>';
        }

    } elseif ($_POST['accion'] === 'importar_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            // Saltamos el BOM UTF-8 si existe (Excel lo añade al guardar como CSV)
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") fseek($handle, 0);
            fgetcsv($handle); // saltar la fila de cabecera
            $ok = 0; $omitidos = 0;
            /* Anti-duplicado: antes de insertar cada ingrediente comprobamos si ya existe
               uno con el mismo nombre y activo=1 en la BD */
            $stmt_check = $conn->prepare("SELECT id FROM ingredientes WHERE nombre=? AND activo=1");
            $stmt_ins   = $conn->prepare("INSERT INTO ingredientes (nombre, categoria, calorias_por_100, proteinas_por_100, carbohidratos_por_100, grasas_por_100, no_permitido_base) VALUES (?,?,?,?,?,?,?)");
            while (($row = fgetcsv($handle)) !== false) {
                if (empty(trim($row[0] ?? ''))) continue;
                $nombre = trim($row[0]);
                $stmt_check->bind_param("s", $nombre);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) { $omitidos++; continue; }
                $categoria = strtolower(trim($row[1] ?? 'verdura'));
                $cal  = (float)($row[2] ?? 0);
                $prot = (float)($row[3] ?? 0);
                $carb = (float)($row[4] ?? 0);
                $gras = (float)($row[5] ?? 0);
                $no_base = (isset($row[6]) && trim($row[6]) === '1') ? 1 : 0;
                $stmt_ins->bind_param("ssddddi", $nombre, $categoria, $cal, $prot, $carb, $gras, $no_base);
                $stmt_ins->execute();
                $ok++;
            }
            fclose($handle);
            $msg = "<div class='alert alert-success'>Importación completada: <strong>$ok</strong> añadidos, <strong>$omitidos</strong> omitidos (ya existían).</div>";
        } else {
            $msg = "<div class='alert alert-error'>Error al subir el archivo CSV.</div>";
        }
    }
}

if (isset($_GET['ok'])) {
    $msg = '<div class="alert alert-success">Ingrediente actualizado correctamente.</div>';
}

$mostrarForm       = isset($_GET['nuevo']);
// Mantenemos el panel de importar abierto si acabamos de hacer una importación
$show_import_panel = (isset($_POST['accion']) && $_POST['accion'] === 'importar_csv');
$editar_id         = (int)($_GET['editar'] ?? 0);

// Cargamos todos los ingredientes activos para el botón de copiar lista (usado en el portapapeles)
$ings_exportar = [];
$res_exp_ia = $conn->query("SELECT nombre, categoria FROM ingredientes WHERE activo=1 ORDER BY categoria, nombre");
while ($r = $res_exp_ia->fetch_assoc()) $ings_exportar[] = $r;
$filtro_cat   = $_GET['cat'] ?? '';

// Si viene ?editar=ID cargamos ese ingrediente para la edición inline
$editar_ing = null;
if ($editar_id) {
    $editar_ing = $conn->query("SELECT * FROM ingredientes WHERE id=$editar_id AND activo=1")->fetch_assoc();
    if (!$editar_ing) $editar_id = 0;
}

// Filtramos por categoría si viene el parámetro ?cat=..., si no mostramos todos
if ($filtro_cat) {
    $stmt_ing = $conn->prepare("SELECT * FROM ingredientes WHERE activo=1 AND categoria=? ORDER BY categoria, no_permitido_base, nombre");
    $stmt_ing->bind_param("s", $filtro_cat);
    $stmt_ing->execute();
    $ingredientes = $stmt_ing->get_result();
} else {
    $ingredientes = $conn->query("SELECT * FROM ingredientes WHERE activo=1 ORDER BY categoria, no_permitido_base, nombre");
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingredientes – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477277">
<script src="js/theme-init.js"></script>
<style>
tr.editando td { background: var(--input-bg) !important; }
.edit-row { display: none; background: var(--input-bg); }
.edit-row td { padding: 12px 16px; }
.edit-row .grid-edit { display: grid; grid-template-columns: 2fr 1.5fr 1fr 1fr 1fr 1fr; gap: 10px; align-items: end; }
.edit-row input, .edit-row select { padding: 6px 8px; border: 1px solid var(--border-color); border-radius: 4px; font-size: .88rem; width: 100%; background: var(--card-bg); color: var(--text-color); }
.edit-row .check-perm { display: flex; align-items: center; gap: 6px; font-size: .85rem; grid-column: 1 / -1; }
.edit-row .acciones-edit { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
@media (max-width: 768px) {
    .edit-row .grid-edit { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 480px) {
    .edit-row .grid-edit { grid-template-columns: 1fr; }
}
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

<!-- FORMULARIO NUEVO INGREDIENTE -->
<?php if ($mostrarForm): ?>
<div class="card">
    <h2>Nuevo ingrediente</h2>
    <form method="POST">
        <input type="hidden" name="accion" value="crear">
        <div class="grid-2">
            <div><label>Nombre *</label><input type="text" name="nombre" required></div>
            <div><label>Categoría</label>
                <select name="categoria" required>
                    <?php foreach ($cat_labels as $val => $label): ?>
                    <option value="<?= $val ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Calorías por 100g</label><input type="number" step="0.01" name="calorias" value="0"></div>
            <div><label>Proteínas por 100g</label><input type="number" step="0.01" name="proteinas" value="0"></div>
            <div><label>Carbohidratos por 100g</label><input type="number" step="0.01" name="carbohidratos" value="0"></div>
            <div><label>Grasas por 100g</label><input type="number" step="0.01" name="grasas" value="0"></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;cursor:pointer">
            <input type="checkbox" name="no_permitido_base" value="1" style="width:auto">
            <span style="font-size:.88rem;color:var(--text-color);font-weight:500">Es un alimento <strong>NO PERMITIDO</strong> (aparece en "No comer:" del protocolo)</span>
        </label>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="ingredientes.php" class="btn" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">Cancelar</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- GESTIÓN DE CATEGORÍAS (desplegable) -->
<div class="card" style="margin-top:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleCats()">
        <h2 style="margin:0">🗂️ Gestionar categorías</h2>
        <span id="cats-arrow" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="cats-panel" style="display:none;margin-top:20px">

        <!-- Nueva categoría -->
        <h3 style="font-size:.95rem;font-weight:700;margin-bottom:12px">➕ Nueva categoría</h3>
        <form method="POST">
            <input type="hidden" name="accion" value="crear_categoria">
            <div class="grid-3">
                <div>
                    <label>Nombre interno *</label>
                    <input type="text" name="cat_nombre" placeholder="ej: lacteo" required style="text-transform:lowercase">
                    <span style="font-size:.75rem;color:#999">Sin espacios, en minúsculas</span>
                </div>
                <div>
                    <label>Emoji</label>
                    <input type="text" name="cat_emoji" placeholder="🥛" maxlength="4">
                </div>
                <div>
                    <label>Etiqueta visible *</label>
                    <input type="text" name="cat_label" placeholder="Lácteos" required>
                </div>
            </div>
            <div style="margin-top:16px">
                <button type="submit" class="btn btn-primary">Crear categoría</button>
            </div>
        </form>

        <!-- Borrar categoría -->
        <h3 style="font-size:.95rem;font-weight:700;margin:24px 0 12px">🗑️ Eliminar categoría</h3>
        <?php if (count($cat_labels) > 0): ?>
        <form method="POST" onsubmit="return confirm('¿Seguro que quieres eliminar esta categoría? Los ingredientes que la usen mantendrán el valor pero no se verá en los filtros.')">
            <input type="hidden" name="accion" value="borrar_categoria">
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                <div>
                    <label>Selecciona categoría a eliminar</label>
                    <select name="cat_borrar" required>
                        <?php foreach ($cat_labels as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-danger">Eliminar</button>
            </div>
        </form>
        <?php else: ?>
        <p style="color:#999;font-size:.88rem">No hay categorías para eliminar.</p>
        <?php endif; ?>

    </div>
</div>

<!-- EXPORTAR INGREDIENTES PARA IA -->
<div class="card" style="margin-top:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleExportIng()">
        <h2 style="margin:0">📤 Exportar ingredientes</h2>
        <span id="export-ing-arrow" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="export-ing-panel" style="display:none;margin-top:20px">
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:16px">
            Exporta la lista completa de ingredientes para pasársela a una IA y pedirle que te sugiera más.
        </p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <a href="ingredientes.php?exportar_ingredientes=1" class="btn btn-primary btn-sm">⬇ Descargar CSV</a>
            <button id="btn-copiar-ing" onclick="copiarListaIng()" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">Copiar lista</button>
        </div>
        <p style="font-size:.78rem;color:var(--text-muted);margin-top:12px">
            Pega la lista en tu IA con el mensaje: <em>"Tengo estos ingredientes en mi base de datos de nutrición, ¿qué otros me recomiendas añadir?"</em>
        </p>
    </div>
</div>
<script>
const ING_EXPORTAR = <?= json_encode($ings_exportar, JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- IMPORTAR CSV -->
<div class="card" style="margin-top:24px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleImportCSV()">
        <h2 style="margin:0">📥 Importar ingredientes por CSV</h2>
        <span id="import-ing-arrow" style="font-size:1.2rem;transition:transform .2s;<?= $show_import_panel ? 'transform:rotate(180deg)' : '' ?>">▼</span>
    </div>
    <div id="import-ing-panel" style="display:<?= $show_import_panel ? 'block' : 'none' ?>;margin-top:20px">
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:14px">
            Añade ingredientes en bloque desde un archivo CSV. Los que ya existan con el mismo nombre se omiten (no se duplican).
        </p>
        <div style="margin-bottom:16px">
            <a href="ingredientes.php?csv_plantilla=1" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">⬇ Descargar plantilla CSV</a>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="importar_csv">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <div>
                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:4px">Archivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required style="font-size:.88rem">
                </div>
                <button type="submit" class="btn btn-primary">Importar</button>
            </div>
        </form>
        <details style="margin-top:16px">
            <summary style="font-size:.82rem;cursor:pointer;color:var(--text-muted)">Ver formato esperado</summary>
            <pre style="font-size:.78rem;background:var(--input-bg);border:1px solid var(--border-color);border-radius:6px;padding:10px;margin-top:8px;overflow-x:auto">nombre,categoria,calorias_por_100,proteinas_por_100,carbohidratos_por_100,grasas_por_100,no_permitido_base
Acelgas,verdura,17,1.8,2.5,0.2,0
Pechuga de pollo,carne,165,31,0,3.6,0
Aguacate,fruta,160,2,9,15,1</pre>
            <p style="font-size:.78rem;color:var(--text-muted);margin-top:6px">
                <strong>no_permitido_base</strong>: 1 = aparece en "No comer" por defecto · 0 = permitido<br>
                La categoría debe coincidir con las categorías existentes (nombre interno, en minúsculas).
            </p>
        </details>
    </div>
</div>

<!-- TABLA -->
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <h2 style="margin:0">Ingredientes</h2>
        <a href="ingredientes.php?nuevo=1" class="btn btn-primary btn-sm">+ Añadir ingrediente</a>
    </div>

    <div style="margin-bottom:14px">
        <input type="search" id="buscador-ingredientes" placeholder="Buscar ingrediente por nombre..."
               style="width:100%;max-width:420px;padding:8px 12px;border:1.5px solid #d0d0d5;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none"
               oninput="filtrarIngredientes(this.value)">
    </div>

    <!-- Filtros -->
    <div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
        <a href="ingredientes.php" class="btn btn-sm <?= !$filtro_cat ? 'btn-primary' : '' ?>" style="<?= !$filtro_cat ? '' : 'background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5' ?>">Todos</a>
        <?php foreach ($cat_labels as $val => $label): ?>
        <a href="ingredientes.php?cat=<?= $val ?>" class="btn btn-sm <?= $filtro_cat===$val ? 'btn-primary' : '' ?>" style="<?= $filtro_cat===$val ? '' : 'background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Kcal/100</th>
                <th>Prot.</th>
                <th>Carb.</th>
                <th>Grasas</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($ingredientes && $ingredientes->num_rows > 0): while($i=$ingredientes->fetch_assoc()): ?>

        <!-- Fila normal -->
        <tr id="fila-<?= $i['id'] ?>" data-search="<?= htmlspecialchars(strtolower($i['nombre'])) ?>">
            <td><?= htmlspecialchars($i['nombre']) ?></td>
            <td><?= $cat_labels[$i['categoria']] ?? $i['categoria'] ?></td>
            <td>
                <?php if ($i['no_permitido_base']): ?>
                <span class="badge" style="background:#ffebee;color:#c62828">No permitido</span>
                <?php else: ?>
                <span class="badge badge-green">Permitido</span>
                <?php endif; ?>
            </td>
            <td><?= $i['calorias_por_100'] ?></td>
            <td><?= $i['proteinas_por_100'] ?>g</td>
            <td><?= $i['carbohidratos_por_100'] ?>g</td>
            <td><?= $i['grasas_por_100'] ?>g</td>
            <td style="white-space:nowrap">
                <button class="btn btn-info btn-sm" onclick="abrirEditar(<?= $i['id'] ?>)">Editar</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Desactivar este ingrediente?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                    <button class="btn btn-danger btn-sm">Quitar</button>
                </form>
            </td>
        </tr>

        <!-- Fila de edición inline -->
        <tr class="edit-row" id="edit-<?= $i['id'] ?>">
            <td colspan="8">
                <form method="POST">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?= $i['id'] ?>">
                    <div class="grid-edit">
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Nombre</label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($i['nombre']) ?>" required>
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Categoría</label>
                            <select name="categoria">
                                <?php foreach ($cat_labels as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $i['categoria']===$val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Kcal/100</label>
                            <input type="number" step="0.01" name="calorias" value="<?= $i['calorias_por_100'] ?>">
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Proteínas</label>
                            <input type="number" step="0.01" name="proteinas" value="<?= $i['proteinas_por_100'] ?>">
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Carb.</label>
                            <input type="number" step="0.01" name="carbohidratos" value="<?= $i['carbohidratos_por_100'] ?>">
                        </div>
                        <div>
                            <label style="font-size:.75rem;font-weight:700;text-transform:uppercase;color:#6e6e73">Grasas</label>
                            <input type="number" step="0.01" name="grasas" value="<?= $i['grasas_por_100'] ?>">
                        </div>
                    </div>
                    <label class="check-perm" style="margin-top:10px">
                        <input type="checkbox" name="no_permitido_base" value="1" style="width:auto" <?= $i['no_permitido_base'] ? 'checked' : '' ?>>
                        <span style="font-size:.85rem;color:var(--text-color)">Marcar como <strong>NO PERMITIDO</strong> (aparece en "No comer:" del protocolo)</span>
                    </label>
                    <div class="acciones-edit">
                        <button type="submit" class="btn btn-primary btn-sm">💾 Guardar cambios</button>
                        <button type="button" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5" onclick="cerrarEditar(<?= $i['id'] ?>)">Cancelar</button>
                    </div>
                </form>
            </td>
        </tr>

        <?php endwhile; ?>
        <tr id="no-resultados" style="display:none"><td colspan="8" style="text-align:center;color:#999">Sin resultados para esa búsqueda.</td></tr>
        <?php else: ?>
        <tr><td colspan="8" style="text-align:center;color:#999">Sin ingredientes. <a href="ingredientes.php?nuevo=1">Añadir el primero</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div>

<script>
function toggleCats() {
    var panel = document.getElementById('cats-panel');
    var arrow = document.getElementById('cats-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        arrow.style.transform = '';
    }
}
function toggleImportCSV() {
    var panel = document.getElementById('import-ing-panel');
    var arrow = document.getElementById('import-ing-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        arrow.style.transform = '';
    }
}
function toggleExportIng() {
    var panel = document.getElementById('export-ing-panel');
    var arrow = document.getElementById('export-ing-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        arrow.style.transform = '';
    }
}
/* Copiar lista de ingredientes al portapapeles agrupada por categoría.
   Útil para pegar en una IA y pedirle sugerencias de nuevos ingredientes. */
function copiarListaIng() {
    var grupos = {};
    ING_EXPORTAR.forEach(function(i) {
        if (!grupos[i.categoria]) grupos[i.categoria] = [];
        grupos[i.categoria].push(i.nombre);
    });
    var text = 'Ingredientes disponibles en mi base de datos:\n\n';
    Object.keys(grupos).forEach(function(cat) {
        text += cat.charAt(0).toUpperCase() + cat.slice(1) + ':\n';
        text += grupos[cat].join(', ') + '\n\n';
    });
    navigator.clipboard.writeText(text.trim()).then(function() {
        var btn = document.getElementById('btn-copiar-ing');
        var orig = btn.textContent;
        btn.textContent = '✓ Copiado';
        btn.style.background = '#34c759';
        btn.style.color = '#fff';
        setTimeout(function() { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; }, 2000);
    });
}
// Edición inline: mostramos la fila de edición y ocultamos las demás que pudieran estar abiertas
function abrirEditar(id) {
    document.querySelectorAll('.edit-row').forEach(r => r.style.display = 'none');
    document.querySelectorAll('tr[id^="fila-"]').forEach(r => r.classList.remove('editando'));
    document.getElementById('edit-' + id).style.display = 'table-row';
    document.getElementById('fila-' + id).classList.add('editando');
    document.getElementById('edit-' + id).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function cerrarEditar(id) {
    document.getElementById('edit-' + id).style.display = 'none';
    document.getElementById('fila-' + id).classList.remove('editando');
}
// Buscador client-side por nombre de ingrediente
function filtrarIngredientes(q) {
    const term = q.trim().toLowerCase();
    const filas = document.querySelectorAll('table tbody tr[data-search]');
    let visibles = 0;
    filas.forEach(tr => {
        const match = !term || tr.dataset.search.includes(term);
        tr.style.display = match ? '' : 'none';
        if (match) visibles++;
    });
    const empty = document.getElementById('no-resultados');
    if (empty) empty.style.display = (visibles === 0 && term) ? '' : 'none';
}
</script>
<script src="js/theme.js"></script>
</body>
</html>
