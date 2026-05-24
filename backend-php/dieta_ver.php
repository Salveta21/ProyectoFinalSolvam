<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

// Recogemos el id de la dieta de la URL y verificamos que existe
$dieta_id = (int)($_GET['id'] ?? 0);
if (!$dieta_id) { header("Location: index.php"); exit; }

// JOIN con clientes para tener el nombre y las intolerancias disponibles en la misma consulta
$dieta = $conn->query("SELECT d.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.intolerancias
    FROM dietas d JOIN clientes c ON c.id=d.cliente_id WHERE d.id=$dieta_id")->fetch_assoc();
if (!$dieta) { header("Location: index.php"); exit; }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_no_comer' && !empty($_POST['ing_id'])) {
        // Añadir un alimento a "no comer": lo insertamos en dieta_no_permitidos
        // y lo quitamos de los ingredientes permitidos de esta dieta
        $ing_id = (int)$_POST['ing_id'];
        $stmt = $conn->prepare("INSERT IGNORE INTO dieta_no_permitidos (dieta_id, ingrediente_id) VALUES (?,?)");
        $stmt->bind_param("ii", $dieta_id, $ing_id);
        $stmt->execute();
        $conn->query("DELETE FROM dieta_ingredientes WHERE dieta_id=$dieta_id AND ingrediente_id=$ing_id");
        header("Location: dieta_ver.php?id=$dieta_id#no-comer"); exit;

    } elseif ($accion === 'quitar_no_comer' && !empty($_POST['nc_id'])) {
        // Quitar un alimento de la lista "no comer"
        $conn->query("DELETE FROM dieta_no_permitidos WHERE id=".(int)$_POST['nc_id']." AND dieta_id=$dieta_id");
        header("Location: dieta_ver.php?id=$dieta_id#no-comer"); exit;

    } elseif ($accion === 'guardar' || $accion === 'guardar_imprimir') {
        /* Guardado principal: usamos transacción para que si algo falla
           no queden los ingredientes a medias. Primero borramos todos los
           ingredientes de esta dieta y luego insertamos solo los que vienen
           marcados en el formulario. */
        $conn->begin_transaction();
        try {
            $agua_v  = trim($_POST['agua'] ?? '');
            $comp_v  = trim($_POST['complementos'] ?? '');
            $stmt = $conn->prepare("UPDATE dietas SET nota_huevos=?, observaciones=?, agua=?, complementos=? WHERE id=?");
            $stmt->bind_param("ssssi", $_POST['nota_huevos'], $_POST['observaciones'], $agua_v, $comp_v, $dieta_id);
            $stmt->execute();

            // Borramos todos los ingredientes actuales y volvemos a insertar los seleccionados
            $conn->query("DELETE FROM dieta_ingredientes WHERE dieta_id=$dieta_id");
            if (!empty($_POST['ingredientes'])) {
                $stmt2 = $conn->prepare("INSERT IGNORE INTO dieta_ingredientes (dieta_id, ingrediente_id) VALUES (?,?)");
                foreach ($_POST['ingredientes'] as $ing_id) {
                    $ing_id = (int)$ing_id;
                    $stmt2->bind_param("ii", $dieta_id, $ing_id);
                    $stmt2->execute();
                }
            }

            $conn->commit();

            // Si el botón pulsado fue "Guardar e Imprimir", vamos directamente al PDF
            if ($accion === 'guardar_imprimir') {
                header("Location: dieta_imprimir.php?id=$dieta_id");
                exit;
            }

            // Recargamos los datos de la dieta para que la página muestre los valores actualizados
            $dieta = $conn->query("SELECT d.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.intolerancias
                FROM dietas d JOIN clientes c ON c.id=d.cliente_id WHERE d.id=$dieta_id")->fetch_assoc();
            $msg = '<div class="alert alert-success">Protocolo guardado correctamente.</div>';
        } catch (Exception $e) {
            $conn->rollback();
            $msg = '<div class="alert alert-error">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

/* Cargamos todos los ingredientes activos con un flag que indica si están
   seleccionados en esta dieta concreta (LEFT JOIN con dieta_ingredientes).
   Los agrupamos por categoría para renderizar cada sección. */
$result = $conn->query("
    SELECT i.*, IF(di.id IS NOT NULL, 1, 0) as seleccionado
    FROM ingredientes i
    LEFT JOIN dieta_ingredientes di ON di.ingrediente_id=i.id AND di.dieta_id=$dieta_id
    WHERE i.activo=1
    ORDER BY i.categoria, i.nombre
");
$por_categoria = [];
while ($row = $result->fetch_assoc()) {
    $por_categoria[$row['categoria']][] = $row;
}

// Lista de alimentos restringidos para este paciente (sección "No comer")
$nc_res = $conn->query("
    SELECT dn.id, i.id as ingrediente_id, i.nombre, i.categoria
    FROM dieta_no_permitidos dn
    JOIN ingredientes i ON i.id=dn.ingrediente_id
    WHERE dn.dieta_id=$dieta_id ORDER BY i.categoria, i.nombre
");
$no_comer_items = $nc_res ? $nc_res->fetch_all(MYSQLI_ASSOC) : [];

// Para el select de añadir "no comer": todos los ingredientes con flag si ya están en la lista
$todos_res = $conn->query("
    SELECT i.id, i.nombre, i.categoria, IF(dn.id IS NOT NULL,1,0) as ya_en_lista
    FROM ingredientes i
    LEFT JOIN dieta_no_permitidos dn ON dn.ingrediente_id=i.id AND dn.dieta_id=$dieta_id
    WHERE i.activo=1 ORDER BY i.categoria, i.nombre
");
$todos_ingredientes = $todos_res ? $todos_res->fetch_all(MYSQLI_ASSOC) : [];

$categorias_info = [
    'verdura'    => ['icono'=>'🍅', 'titulo'=>'VERDURAS'],
    'carne'      => ['icono'=>'🍗', 'titulo'=>'CARNE'],
    'pescado'    => ['icono'=>'🐟', 'titulo'=>'PESCADO'],
    'fruta'      => ['icono'=>'🍏', 'titulo'=>'FRUTA'],
    'condimento' => ['icono'=>'🧂', 'titulo'=>'CONDIMENTOS'],
];

$cat_file = __DIR__ . '/categorias.json';
$cat_nombres = file_exists($cat_file) ? json_decode(file_get_contents($cat_file), true) : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editar protocolo – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477236">
<script src="js/theme-init.js"></script>
<style>
.cat-section { margin-bottom: 10px; border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; background: var(--card-bg); }
.cat-header-edit { background: var(--bg-color); padding: 14px 16px; display: flex; align-items: center; gap: 12px; cursor: pointer; user-select: none; transition: background .2s; }
.cat-header-edit:hover { background: var(--input-bg); }
.cat-header-edit span { font-weight: 700; font-size: .9rem; color: var(--text-color); letter-spacing: -.2px; }
.cat-icon { font-size: 1.4rem; }
.cat-badge { margin-left: auto; background: var(--primary); color: white; border-radius: 20px; padding: 3px 10px; font-size: .75rem; font-weight: 600; }
.cat-body-edit { padding: 16px; }
.checks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 6px; }
.check-item { display: flex; align-items: center; gap: 8px; font-size: .9rem; padding: 8px 10px; border-radius: 8px; cursor: pointer; transition: background .15s; }
.check-item:hover { background: var(--bg-color); }
.check-item input { cursor: pointer; width: 18px; height: 18px; accent-color: var(--primary); }
.seleccionar-todos { font-size: .8rem; color: var(--primary); cursor: pointer; text-decoration: none; font-weight: 600; margin-right: 12px; }
.seleccionar-todos:hover { text-decoration: underline; }

.page-header { background: var(--card-bg); padding: 12px 16px; border-bottom: 1px solid var(--border-color); display: flex; gap: 10px; align-items: center; margin-bottom: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.04); position: sticky; top: 60px; z-index: 90; }
.page-header span { margin-left:auto; color: var(--text-muted); font-size:.85rem; font-weight: 500; }
.page-header span strong { color: var(--text-color); }

/* Chips No comer */
.chips-row { display: flex; flex-wrap: wrap; gap: 8px; min-height: 36px; margin-bottom: 14px; }
.chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 20px; padding: 5px 12px; font-size: .85rem; font-weight: 600; }
.chip button { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 0; line-height: 1; font-weight: 700; }
.chip-red { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }
.chip-red button { color:#c62828; }
.chip-red button:hover { color:#7f0000; }
[data-theme="dark"] .chip-red { background:rgba(255,69,58,.15); color:#ff453a; border-color:rgba(255,69,58,.3); }
[data-theme="dark"] .chip-red button { color:#ff453a; }
.add-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.add-row select { flex: 1; min-width: 200px; padding: 10px 12px; font-size: .9rem; }

.action-bar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; position: sticky; bottom: 0; background: var(--bg-color); padding: 16px; border-radius: 12px; margin-top: 20px; border-top: 1px solid var(--border-light); }

/* Card styles for sections */
.card-huevos, .card-obs {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
    padding: 20px;
    margin-top: 16px;
}
.card-huevos h2, .card-obs h2 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-light);
}
.card-obs textarea {
    width: 100%;
    min-height: 120px;
    resize: vertical;
}
#no-comer { margin-top: 24px; }

@media (max-width: 600px) {
    .checks-grid { grid-template-columns: 1fr 1fr; }
    .page-header { position: static; }
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

<!-- Cabecera de página -->
<div class="page-header">
    <a href="dieta_lista.php?cliente=<?= $dieta['cliente_id'] ?>" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">← Volver</a>
    <span>
        <strong><?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?></strong>
        — <?= htmlspecialchars($dieta['nombre']) ?> (<?= $dieta['fecha'] ?>)
    </span>
</div>

<?php if (!empty($dieta['intolerancias'])): ?>
<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.88rem;color:#5a4000">
    ⚠️ <strong>Intolerancias del paciente:</strong> <?= htmlspecialchars($dieta['intolerancias']) ?>
</div>
<?php endif; ?>

<form method="POST" id="diet-form">

<!-- CATEGORÍAS (incluye condimentos) -->
<?php foreach ($categorias_info as $cat => $info):
    $items   = $por_categoria[$cat] ?? [];
    $num_sel = count(array_filter($items, fn($i) => $i['seleccionado']));
?>
<div class="cat-section">
    <div class="cat-header-edit" onclick="toggleCat('<?= $cat ?>')">
        <span class="cat-icon"><?= $info['icono'] ?></span>
        <span><?= $info['titulo'] ?></span>
        <span class="cat-badge"><?= $num_sel ?>/<?= count($items) ?></span>
    </div>
    <div class="cat-body-edit" id="cat-<?= $cat ?>">
        <span class="seleccionar-todos" onclick="toggleAll('<?= $cat ?>', true)">Seleccionar todos</span>
        <span class="seleccionar-todos" onclick="toggleAll('<?= $cat ?>', false)">Deseleccionar todos</span>
        <div class="checks-grid cat-checks-<?= $cat ?>" style="margin-top:12px">
            <?php foreach ($items as $ing): ?>
            <label class="check-item">
                <input type="checkbox" name="ingredientes[]" value="<?= $ing['id'] ?>" <?= $ing['seleccionado'] ? 'checked' : '' ?>>
                <?= htmlspecialchars($ing['nombre']) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- HUEVOS -->
<div class="card-huevos">
    <h2>🥚 HUEVOS</h2>
    <label>Indicación</label>
    <input type="text" name="nota_huevos" value="<?= htmlspecialchars($dieta['nota_huevos']) ?>">
</div>

</form>

<!-- NO COMER -->
<div class="card" id="no-comer" style="border:2px solid #ffcdd2">
    <h2 style="color:#c62828">🚫 No comer (este paciente)</h2>
    <p style="font-size:.85rem;color:#666;margin-bottom:12px">
        Estos alimentos aparecerán en la línea <strong>"No comer:"</strong> del protocolo impreso.
    </p>
    <div class="chips-row">
        <?php if (empty($no_comer_items)): ?>
        <span style="color:#999;font-size:.88rem;align-self:center">Sin alimentos restringidos.</span>
        <?php else: foreach ($no_comer_items as $nc): ?>
        <div class="chip chip-red">
            <?= htmlspecialchars($nc['nombre']) ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="accion" value="quitar_no_comer">
                <input type="hidden" name="nc_id" value="<?= $nc['id'] ?>">
                <button type="submit" title="Quitar">✕</button>
            </form>
        </div>
        <?php endforeach; endif; ?>
    </div>
    <form method="POST" class="add-row" style="margin-top:14px">
        <input type="hidden" name="accion" value="agregar_no_comer">
        <select name="ing_id" required>
            <option value="">— Seleccionar alimento a restringir —</option>
            <?php
            $prev_cat = '';
            foreach ($todos_ingredientes as $ing):
                if ($ing['categoria'] !== $prev_cat) {
                    if ($prev_cat) echo '</optgroup>';
                    echo '<optgroup label="'.htmlspecialchars($cat_nombres[$ing['categoria']] ?? $ing['categoria']).'">';
                    $prev_cat = $ing['categoria'];
                }
                $dis  = $ing['ya_en_lista'] ? ' disabled' : '';
                $tick = $ing['ya_en_lista'] ? ' ✓' : '';
                echo '<option value="'.$ing['id'].'"'.$dis.'>'.htmlspecialchars($ing['nombre']).$tick.'</option>';
            endforeach;
            if ($prev_cat) echo '</optgroup>';
            ?>
        </select>
        <button type="submit" class="btn btn-danger btn-sm">+ Añadir</button>
    </form>
</div>

<!-- AGUA Y COMPLEMENTOS -->
<div class="card-obs" style="margin-top:16px">
    <h2>💧 Cantidad de agua diaria</h2>
    <input type="text" name="agua" form="diet-form" value="<?= htmlspecialchars($dieta['agua'] ?? '') ?>" placeholder="Ej: 2 litros/día">
</div>

<div class="card-obs" style="margin-top:16px">
    <h2>💊 Complementos</h2>
    <textarea name="complementos" form="diet-form" rows="2" placeholder="Ej: Omega-3 1g, Vitamina D 2000 UI"><?= htmlspecialchars($dieta['complementos'] ?? '') ?></textarea>
</div>

<!-- OBSERVACIONES -->
<div class="card-obs">
    <h2>Observaciones internas</h2>
    <textarea name="observaciones" form="diet-form" rows="2"><?= htmlspecialchars($dieta['observaciones'] ?? '') ?></textarea>
</div>

<div class="action-bar">
    <button type="submit" form="diet-form" name="accion" value="guardar" class="btn btn-primary">💾 Guardar protocolo</button>
    <button type="submit" form="diet-form" name="accion" value="guardar_imprimir" class="btn btn-info">🖨 Guardar e Imprimir</button>
</div>
</div>

<script>
function toggleCat(cat) {
    const el = document.getElementById('cat-' + cat);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
function toggleAll(cat, check) {
    document.querySelectorAll('.cat-checks-' + cat + ' input[type=checkbox]').forEach(cb => cb.checked = check);
}
</script>
<script src="js/theme.js"></script>
</body>
</html>
