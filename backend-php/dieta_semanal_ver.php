<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

/* Si viene el parámetro csv_plantilla_semanal, generamos y enviamos la plantilla CSV
   directamente sin cargar ningún HTML — el navegador la descarga automáticamente */
if (isset($_GET['csv_plantilla_semanal'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_dieta_semanal.csv"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['dia','toma','tipo','ingrediente_principal','cantidad_principal','ingrediente_complemento','cantidad_complemento']);
    fputcsv($out, ['lunes','desayuno','suelto','Acelgas','','','']);
    fputcsv($out, ['lunes','comida','combo','Pechuga de pollo','150g','Brócoli','200g']);
    fputcsv($out, ['lunes','comida','combo','Merluza','200g','','']);
    fputcsv($out, ['martes','desayuno','suelto','Espinacas','','','']);
    fputcsv($out, ['martes','comida','combo','Ternera','150g','Judías verdes','150g']);
    fclose($out);
    exit;
}

$dieta_id = (int)($_GET['id'] ?? 0);
if (!$dieta_id) { header("Location: index.php"); exit; }

$dieta = $conn->query("SELECT d.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.intolerancias
    FROM dietas d JOIN clientes c ON c.id=d.cliente_id WHERE d.id=$dieta_id")->fetch_assoc();
if (!$dieta) { header("Location: index.php"); exit; }

$msg = '';
$dias = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
$tomas_info = [
    'desayuno'     => ['emoji'=>'🌅', 'label'=>'Desayuno'],
    'media_manana' => ['emoji'=>'🍎', 'label'=>'Media mañana'],
    'almuerzo'     => ['emoji'=>'🍽️', 'label'=>'Almuerzo'],
    'comida'       => ['emoji'=>'🍲', 'label'=>'Comida'],
    'merienda'     => ['emoji'=>'🍊', 'label'=>'Merienda'],
    'cena'         => ['emoji'=>'🌙', 'label'=>'Cena'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_no_comer' && !empty($_POST['ing_id'])) {
        // Añadir alimento a la lista de "no comer" de esta dieta
        $ing_id = (int)$_POST['ing_id'];
        $stmt = $conn->prepare("INSERT IGNORE INTO dieta_no_permitidos (dieta_id, ingrediente_id) VALUES (?,?)");
        $stmt->bind_param("ii", $dieta_id, $ing_id);
        $stmt->execute();
        header("Location: dieta_semanal_ver.php?id=$dieta_id#no-comer"); exit;

    } elseif ($accion === 'quitar_no_comer' && !empty($_POST['nc_id'])) {
        // Eliminar alimento de la lista "no comer"
        $conn->query("DELETE FROM dieta_no_permitidos WHERE id=".(int)$_POST['nc_id']." AND dieta_id=$dieta_id");
        header("Location: dieta_semanal_ver.php?id=$dieta_id#no-comer"); exit;

    } elseif ($accion === 'agregar_combo') {
        /* Añadir una combinación plato+complemento para un día y toma concretos.
           Saneamos el día (rango 1-7) y la toma (solo letras y guiones bajos)
           para no meter datos raros en la BD. El complemento es opcional (puede ser null). */
        $dia  = max(1, min(7, (int)$_POST['dia']));
        $toma = preg_replace('/[^a-z_]/', '', $_POST['toma'] ?? '');
        $pid  = (int)$_POST['principal_id'];
        $qp   = trim($_POST['cantidad_principal'] ?? '');
        $cid_raw = (int)($_POST['complemento_id'] ?? 0);
        $cid  = $cid_raw > 0 ? $cid_raw : null;
        $qc   = trim($_POST['cantidad_complemento'] ?? '');

        if ($pid > 0 && $toma) {
            $stmt = $conn->prepare("INSERT INTO dieta_semanal_combinaciones
                (dieta_id, dia, toma, principal_id, cantidad_principal, complemento_id, cantidad_complemento)
                VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("iisisis", $dieta_id, $dia, $toma, $pid, $qp, $cid, $qc);
            $stmt->execute();
        }
        // Redirigimos con ?dia=N para que la página vuelva al tab del día correcto
        header("Location: dieta_semanal_ver.php?id=$dieta_id&dia=$dia"); exit;

    } elseif ($accion === 'quitar_combo') {
        // Quitar una combinación por su id. También pasamos el día para volver al tab correcto.
        $combo_id  = (int)$_POST['combo_id'];
        $dia_redir = max(1, min(7, (int)($_POST['dia'] ?? 1)));
        $conn->query("DELETE FROM dieta_semanal_combinaciones WHERE id=$combo_id AND dieta_id=$dieta_id");
        header("Location: dieta_semanal_ver.php?id=$dieta_id&dia=$dia_redir"); exit;

    } elseif ($accion === 'vaciar_semanal') {
        // Vaciar toda la dieta semanal: borramos comidas sueltas y combinaciones de todos los días
        $conn->query("DELETE FROM dieta_semanal_comidas WHERE dieta_id=$dieta_id");
        $conn->query("DELETE FROM dieta_semanal_combinaciones WHERE dieta_id=$dieta_id");
        header("Location: dieta_semanal_ver.php?id=$dieta_id&vaciado=1"); exit;

    } elseif ($accion === 'importar_semanal_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            // Mapa de nombres de días en español a números (1=Lunes...7=Domingo)
            $dia_map = [
                'lunes'=>1,'martes'=>2,'miercoles'=>3,'miércoles'=>3,
                'jueves'=>4,'viernes'=>5,'sabado'=>6,'sábado'=>6,'domingo'=>7
            ];
            /* Cargamos todos los ingredientes activos en un array asociativo
               nombre_en_minusculas => id, para buscar rápidamente sin hacer
               una consulta por cada fila del CSV */
            $all_ings = [];
            $res_ings = $conn->query("SELECT id, nombre FROM ingredientes WHERE activo=1");
            while ($r = $res_ings->fetch_assoc()) {
                $all_ings[mb_strtolower($r['nombre'])] = (int)$r['id'];
            }
            $toma_validas = array_keys($tomas_info);
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            // Saltamos el BOM UTF-8 si existe (Excel lo añade al guardar como CSV)
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") fseek($handle, 0);
            fgetcsv($handle); // saltamos la fila de cabecera
            $ok = 0; $errores = [];
            $stmt_suelto = $conn->prepare("INSERT IGNORE INTO dieta_semanal_comidas (dieta_id, dia, toma, ingrediente_id) VALUES (?,?,?,?)");
            $stmt_combo  = $conn->prepare("INSERT INTO dieta_semanal_combinaciones (dieta_id, dia, toma, principal_id, cantidad_principal, complemento_id, cantidad_complemento) VALUES (?,?,?,?,?,?,?)");
            $fila = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $fila++;
                if (empty(trim($row[0] ?? ''))) continue;
                $dia_str   = mb_strtolower(trim($row[0]));
                $toma_str  = mb_strtolower(trim($row[1] ?? ''));
                $tipo      = mb_strtolower(trim($row[2] ?? ''));
                $ing1_name = trim($row[3] ?? '');
                $qty1      = trim($row[4] ?? '');
                $ing2_name = trim($row[5] ?? '');
                $qty2      = trim($row[6] ?? '');
                // El día puede venir como número o como nombre (lunes, martes...)
                $dia_num = is_numeric($dia_str) ? (int)$dia_str : ($dia_map[$dia_str] ?? null);
                if (!$dia_num || $dia_num < 1 || $dia_num > 7) {
                    $errores[] = "Fila $fila: día inválido '$dia_str'"; continue;
                }
                if (!in_array($toma_str, $toma_validas)) {
                    $errores[] = "Fila $fila: toma inválida '$toma_str'"; continue;
                }
                if (empty($ing1_name)) {
                    $errores[] = "Fila $fila: ingrediente principal vacío"; continue;
                }
                // Buscamos el id del ingrediente por nombre (case-insensitive)
                $pid = $all_ings[mb_strtolower($ing1_name)] ?? null;
                if (!$pid) { $errores[] = "Fila $fila: ingrediente no encontrado '$ing1_name'"; continue; }
                if ($tipo === 'suelto') {
                    $stmt_suelto->bind_param("iisi", $dieta_id, $dia_num, $toma_str, $pid);
                    $stmt_suelto->execute();
                    $ok++;
                } elseif ($tipo === 'combo') {
                    $cid = null;
                    if ($ing2_name !== '') {
                        $cid = $all_ings[mb_strtolower($ing2_name)] ?? null;
                        if (!$cid) { $errores[] = "Fila $fila: complemento no encontrado '$ing2_name'"; continue; }
                    }
                    $stmt_combo->bind_param("iisisis", $dieta_id, $dia_num, $toma_str, $pid, $qty1, $cid, $qty2);
                    $stmt_combo->execute();
                    $ok++;
                } else {
                    $errores[] = "Fila $fila: tipo inválido '$tipo' (usa 'suelto' o 'combo')";
                }
            }
            fclose($handle);
            $msg = "<div class='alert alert-success'>Importación completada: <strong>$ok</strong> filas procesadas.</div>";
            if ($errores) {
                $primeros = implode('<br>', array_map('htmlspecialchars', array_slice($errores, 0, 5)));
                $extra = count($errores) > 5 ? '<br>…y ' . (count($errores) - 5) . ' más' : '';
                $msg .= "<div class='alert alert-error' style='margin-top:8px'><strong>" . count($errores) . " errores:</strong><br>$primeros$extra</div>";
            }
        } else {
            $msg = "<div class='alert alert-error'>Error al subir el archivo CSV.</div>";
        }
        // No redirigimos para poder mostrar el mensaje de resultado con el panel abierto

    } else {
        /* Acción de guardar: guardamos los ingredientes sueltos de todos los días/tomas,
           las tomas activas (cuáles aparecen en el informe), el agua y los complementos */
        $tomas_sel = [];
        foreach (array_keys($tomas_info) as $tk) {
            if (!empty($_POST['toma_activa'][$tk])) $tomas_sel[] = $tk;
        }
        // Si no se seleccionó ninguna toma, activamos todas por defecto
        if (empty($tomas_sel)) $tomas_sel = array_keys($tomas_info);
        $json_ta   = json_encode($tomas_sel);
        $agua_v    = trim($_POST['agua'] ?? '');
        $comp_v    = trim($_POST['complementos'] ?? '');
        $stmt_ta   = $conn->prepare("UPDATE dietas SET tomas_activas=?, agua=?, complementos=? WHERE id=?");
        $stmt_ta->bind_param("sssi", $json_ta, $agua_v, $comp_v, $dieta_id);
        $stmt_ta->execute();

        // Borramos todos los ingredientes sueltos y volvemos a insertar los del formulario
        $conn->query("DELETE FROM dieta_semanal_comidas WHERE dieta_id=$dieta_id");
        $stmt = $conn->prepare("INSERT IGNORE INTO dieta_semanal_comidas (dieta_id, dia, toma, ingrediente_id) VALUES (?,?,?,?)");
        foreach (array_keys($dias) as $dia_num) {
            foreach (array_keys($tomas_info) as $toma) {
                foreach (($_POST['ing'][$dia_num][$toma] ?? []) as $ing_id) {
                    $ing_id = (int)$ing_id;
                    if ($ing_id > 0) {
                        $stmt->bind_param("iisi", $dieta_id, $dia_num, $toma, $ing_id);
                        $stmt->execute();
                    }
                }
            }
        }
        if ($accion === 'guardar_imprimir') {
            header("Location: dieta_semanal_imprimir.php?id=$dieta_id"); exit;
        }
        // Redirigimos para evitar que al refrescar se reenvíe el formulario
        header("Location: dieta_semanal_ver.php?id=$dieta_id&ok=1"); exit;
    }
}

if (isset($_GET['ok']))      $msg = '<div class="alert alert-success">Dieta semanal guardada correctamente.</div>';
if (isset($_GET['vaciado'])) $msg = '<div class="alert alert-success">Dieta semanal vaciada. Puedes importar o añadir de nuevo.</div>';
$show_import_semanal = (isset($_POST['accion']) && $_POST['accion'] === 'importar_semanal_csv');

// Ingredientes permitidos (no_permitido_base=0) para pasarlos al generador de prompt de IA
$ings_ia = [];
$res_ia = $conn->query("SELECT nombre, categoria FROM ingredientes WHERE activo=1 AND no_permitido_base=0 ORDER BY categoria, nombre");
while ($r = $res_ia->fetch_assoc()) $ings_ia[] = $r;

/* fmt_qty: pequeña función helper para formatear cantidades.
   Si la cantidad es un número puro (ej. "150") le añade "g".
   Si ya viene con unidad (ej. "150g" o "al gusto") lo deja como está. */
function fmt_qty(string $q): string {
    return ($q !== '' && is_numeric($q)) ? $q . 'g' : $q;
}

// Cargamos todos los ingredientes activos y construimos también un mapa id => datos
$res = $conn->query("SELECT * FROM ingredientes WHERE activo=1 ORDER BY categoria, nombre");
$todos_ing = [];
$ing_map = [];
while ($r = $res->fetch_assoc()) {
    $todos_ing[] = $r;
    $ing_map[$r['id']] = $r;
}

/* Selecciones existentes: construimos un array [dia][toma] => [ing_ids...]
   para saber qué chips mostrar en cada tarjeta de toma */
$seleccionados = [];
$res2 = $conn->query("SELECT * FROM dieta_semanal_comidas WHERE dieta_id=$dieta_id");
while ($r = $res2->fetch_assoc()) {
    $seleccionados[$r['dia']][$r['toma']][] = (int)$r['ingrediente_id'];
}

/* Combinaciones agrupadas igual que los sueltos: [dia][toma] => [...]
   JOIN para traer los nombres del principal y del complemento en la misma consulta */
$combos = [];
$res_c = $conn->query("
    SELECT c.*, ip.nombre as pnombre, IFNULL(ic.nombre,'') as cnombre
    FROM dieta_semanal_combinaciones c
    JOIN ingredientes ip ON ip.id = c.principal_id
    LEFT JOIN ingredientes ic ON ic.id = c.complemento_id
    WHERE c.dieta_id = $dieta_id
    ORDER BY c.dia, c.toma, c.id");
if ($res_c) {
    while ($r = $res_c->fetch_assoc()) {
        $combos[$r['dia']][$r['toma']][] = $r;
    }
}

/* Tomas activas: si el campo es NULL en BD significa que todas están activas.
   Usamos !empty() para no intentar decodificar un NULL como JSON. */
$tomas_activas = !empty($dieta['tomas_activas'])
    ? json_decode($dieta['tomas_activas'], true)
    : array_keys($tomas_info);

// Cat labels
$cat_file = __DIR__ . '/categorias.json';
$cat_labels = file_exists($cat_file) ? json_decode(file_get_contents($cat_file), true) : [
    'verdura'=>'🍅 Verduras','carne'=>'🍗 Carne','pescado'=>'🐟 Pescado','fruta'=>'🍏 Fruta','condimento'=>'🧂 Condimentos'
];

// No comer
$nc_res = $conn->query("SELECT dn.id, i.id as ingrediente_id, i.nombre FROM dieta_no_permitidos dn JOIN ingredientes i ON i.id=dn.ingrediente_id WHERE dn.dieta_id=$dieta_id ORDER BY i.nombre");
$no_comer_items = $nc_res ? $nc_res->fetch_all(MYSQLI_ASSOC) : [];

$todos_nc_res = $conn->query("SELECT i.id, i.nombre, i.categoria, IF(dn.id IS NOT NULL,1,0) as ya_en_lista
    FROM ingredientes i
    LEFT JOIN dieta_no_permitidos dn ON dn.ingrediente_id=i.id AND dn.dieta_id=$dieta_id
    WHERE i.activo=1 ORDER BY i.categoria, i.nombre");
$todos_nc = $todos_nc_res ? $todos_nc_res->fetch_all(MYSQLI_ASSOC) : [];

// Helper: optgroups para un select de ingredientes
function ingOptgroups(array $todos_ing, array $cat_labels, string $empty_label = '— Seleccionar —'): string {
    $html = '<option value="">' . htmlspecialchars($empty_label) . '</option>';
    $last = '';
    foreach ($todos_ing as $ing) {
        if ($ing['categoria'] !== $last) {
            if ($last) $html .= '</optgroup>';
            $html .= '<optgroup label="' . htmlspecialchars($cat_labels[$ing['categoria']] ?? $ing['categoria']) . '">';
            $last = $ing['categoria'];
        }
        $html .= '<option value="' . $ing['id'] . '">' . htmlspecialchars($ing['nombre']) . '</option>';
    }
    if ($last) $html .= '</optgroup>';
    return $html;
}
$ing_opts_req  = ingOptgroups($todos_ing, $cat_labels, '— Seleccionar —');
$ing_opts_opt  = ingOptgroups($todos_ing, $cat_labels, '— Ninguno —');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dieta semanal – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477223">
<script src="js/theme-init.js"></script>
<style>
.page-header {
    background: var(--card-bg); padding:12px 16px; border-bottom:1px solid var(--border-color);
    display:flex; gap:10px; align-items:center;
    margin-bottom:16px; border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
    position:sticky; top:60px; z-index:90; flex-wrap:wrap;
}
.page-header .ph-title { font-size:.9rem; color: var(--text-muted); font-weight:500; }
.page-header .ph-title strong { color: var(--text-color); }
.ph-actions { margin-left:auto; display:flex; gap:8px; flex-wrap:wrap; }

.nc-card {
    background: var(--card-bg); border:2px solid rgba(255,69,58,.4); border-radius:12px;
    padding:18px 20px; margin-bottom:16px;
}
.nc-card h3 { font-size:1rem; font-weight:700; color: var(--danger); margin-bottom:8px; }
.nc-card p { font-size:.82rem; color: var(--text-muted); margin-bottom:12px; }
.chips-row { display:flex; flex-wrap:wrap; gap:7px; min-height:32px; margin-bottom:12px; }
.chip-red { background:#ffebee; color:#c62828; border:1px solid #ffcdd2; }
.chip-red button { background:none; border:none; cursor:pointer; font-size:.9rem; padding:0; line-height:1; color:#c62828; opacity:.7; }
.chip-red button:hover { opacity:1; }
[data-theme="dark"] .chip-red { background:rgba(255,69,58,.15); color:#ff453a; border-color:rgba(255,69,58,.3); }
[data-theme="dark"] .chip-red button { color:#ff453a; }
.add-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.add-row select { flex:1; min-width:200px; font-family:inherit; }

.day-tabs {
    display:flex; gap:4px; margin-bottom:0; overflow-x:auto;
    background: var(--card-bg); border-radius:12px 12px 0 0;
    padding:12px 12px 0; border:1px solid var(--border-color); border-bottom:none;
}
.day-tab {
    padding:9px 18px; border:2px solid transparent; border-radius:8px 8px 0 0;
    background: var(--bg-color); cursor:pointer; font-weight:600; font-size:.85rem;
    white-space:nowrap; transition:all .15s; border-bottom:none; color: var(--text-color);
    font-family:inherit;
}
.day-tab.active { background: var(--primary); color:#fff; border-color: var(--primary); }
.day-tab:hover:not(.active) { background: var(--input-bg); }

.day-panel { display:none; background: var(--card-bg); border:1px solid var(--border-color); border-radius:0 0 12px 12px; padding:20px; }
.day-panel.active { display:block; }

.meals-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(260px,1fr));
    gap:14px;
}

.meal-card {
    background: var(--bg-color); border:1px solid var(--border-color);
    border-radius:12px; padding:14px;
}
.meal-header {
    display:flex; align-items:center; gap:8px;
    margin-bottom:10px; padding-bottom:10px;
    border-bottom:1px solid var(--border-light);
}
.meal-header h3 { font-size:.9rem; font-weight:700; margin:0; flex:1; color: var(--text-color); }
.meal-count { font-size:.72rem; color: var(--text-muted); white-space:nowrap; background: var(--input-bg); padding:2px 8px; border-radius:10px; }

.meal-chips { display:flex; flex-wrap:wrap; gap:5px; min-height:28px; margin-bottom:10px; }
.chip { display:inline-flex; align-items:center; gap:5px; border-radius:16px; padding:3px 9px; font-size:.78rem; font-weight:600; }
.chip-green { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
.chip-green button { background:none; border:none; cursor:pointer; font-size:.9rem; padding:0; line-height:1; color:#2e7d32; opacity:.7; }
.chip-green button:hover { opacity:1; }
[data-theme="dark"] .chip-green { background:rgba(52,199,89,.15); color:#34c759; border-color:rgba(52,199,89,.3); }
[data-theme="dark"] .chip-green button { color:#34c759; }

.meal-select {
    width:100%; border-radius:8px; font-size:.85rem;
    cursor:pointer; font-family:inherit; margin-bottom:10px;
}

/* Combinaciones en el meal-card */
.combos-section { border-top:1px solid var(--border-light); padding-top:10px; margin-top:2px; }
.combos-label { font-size:.72rem; font-weight:700; color:#5c7fb8; letter-spacing:.4px; text-transform:uppercase; margin-bottom:6px; }

.combo-chip {
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
    background:#eef2ff; border:1px solid #c7d7f9;
    border-radius:8px; padding:5px 8px; margin-bottom:5px;
    font-size:.8rem;
}
.combo-principal { font-weight:700; color:#1a3a6e; }
.combo-qty { color:#6b89c0; font-style:italic; }
.combo-sep { color:#5c7fb8; font-weight:800; font-size:.85rem; padding:0 2px; }
.combo-complemento { color:#2d5a9e; }
[data-theme="dark"] .combo-chip { background:rgba(94,92,230,.15); border-color:rgba(94,92,230,.35); }
[data-theme="dark"] .combo-principal { color:#a5b4fc; }
[data-theme="dark"] .combo-qty { color:#818cf8; }
[data-theme="dark"] .combo-complemento { color:#93c5fd; }
.combo-del {
    background:none; border:none; cursor:pointer; color:#8ea8dc;
    font-size:.85rem; padding:0; line-height:1; margin-left:auto;
    transition:color .15s;
}
.combo-del:hover { color: var(--danger); }

.btn-add-combo {
    width:100%; font-size:.75rem; color:#5c7fb8; background:none;
    border:1px dashed #b3c8f0; border-radius:7px; padding:5px 0;
    cursor:pointer; font-family:inherit; font-weight:600;
    transition:all .15s; text-align:center; margin-top:4px;
}
.btn-add-combo:hover { background:#eef2ff; border-color:#5c7fb8; }
[data-theme="dark"] .btn-add-combo { color:#818cf8; border-color:rgba(94,92,230,.4); }
[data-theme="dark"] .btn-add-combo:hover { background:rgba(94,92,230,.15); border-color:#818cf8; }

/* Modal */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.55); z-index:500;
    align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal-box {
    background: var(--card-bg); border-radius:16px; padding:24px;
    max-width:500px; width:92%;
    box-shadow:0 12px 40px rgba(0,0,0,.3);
    max-height:90vh; overflow-y:auto;
}
.modal-title { font-size:1rem; font-weight:700; margin-bottom:18px; color: var(--text-color); }
.modal-field { margin-bottom:14px; }
.modal-field label { font-size:.82rem; font-weight:600; color: var(--text-muted); display:block; margin-bottom:5px; }
.modal-select { width:100%; border-radius:9px; font-size:.88rem; font-family:inherit; }
.modal-qty { width:100%; border-radius:9px; font-size:.88rem; font-family:inherit; margin-top:6px; }
.modal-divider { border:none; border-top:1px solid var(--border-light); margin:16px 0; }
.modal-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }

/* Panel tomas activas */
.tomas-panel {
    background: var(--card-bg); border:1px solid var(--border-color); border-radius:12px;
    padding:12px 16px; margin-bottom:10px;
    display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}
.tomas-panel-lbl {
    font-size:.78rem; font-weight:700; color: var(--text-muted);
    white-space:nowrap; margin-right:4px;
}
.toma-toggle {
    display:inline-flex; align-items:center; gap:4px;
    padding:4px 11px; border-radius:20px;
    border:1.5px solid var(--border-color); background: var(--bg-color);
    cursor:pointer; font-size:.78rem; font-weight:600;
    color: var(--text-muted); transition:all .15s; user-select:none;
    text-decoration:line-through;
}
.toma-toggle input { display:none; }
.toma-toggle.active {
    background:rgba(52,199,89,.12); border-color:rgba(52,199,89,.4);
    color:#2e7d32; text-decoration:none;
}
[data-theme="dark"] .toma-toggle.active { color:#34c759; }
.toma-toggle:hover { border-color: var(--primary); }

@media (max-width:600px) {
    .meals-grid { grid-template-columns:1fr; }
    .page-header { position:static; }
    .day-tabs { border-radius:12px; }
    .day-panel { border-radius:12px; margin-top:4px; }
    .agua-comp-grid { grid-template-columns:1fr !important; }
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

<div class="page-header">
    <a href="dieta_lista.php?cliente=<?= $dieta['cliente_id'] ?>" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">← Volver</a>
    <span class="ph-title">
        <strong><?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?></strong>
        — <?= htmlspecialchars($dieta['nombre']) ?> · <span style="color:#0071e3">📅 Semanal</span>
    </span>
    <div class="ph-actions">
        <button type="submit" form="semanal-form" name="accion" value="guardar" class="btn btn-primary btn-sm">💾 Guardar</button>
        <button type="submit" form="semanal-form" name="accion" value="guardar_imprimir" class="btn btn-info btn-sm">🖨️ Guardar e Imprimir</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('¿Vaciar toda la dieta semanal?\n\nSe eliminarán todos los ingredientes y combinaciones de todos los días. Esta acción no se puede deshacer.')) document.getElementById('vaciar-semanal-form').submit()">🗑️ Vaciar todo</button>
    </div>
</div>

<?php if (!empty($dieta['intolerancias'])): ?>
<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.88rem;color:#5a4000">
    ⚠️ <strong>Intolerancias del paciente:</strong> <?= htmlspecialchars($dieta['intolerancias']) ?>
</div>
<?php endif; ?>

<!-- GENERAR DIETA CON IA -->
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleGenIA()">
        <h2 style="margin:0;font-size:.95rem">🤖 Generar dieta con IA</h2>
        <span id="gen-ia-arrow" style="font-size:1.2rem;transition:transform .2s">▼</span>
    </div>
    <div id="gen-ia-panel" style="display:none;margin-top:20px">
        <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:16px">
            Configura los parámetros, copia el prompt y pégalo en tu IA favorita. Los ingredientes disponibles ya están incluidos — no necesitas adjuntar ningún archivo.
        </p>
        <!-- Opciones -->
        <div style="background:var(--input-bg);border:1px solid var(--border-color);border-radius:10px;padding:16px;margin-bottom:16px">
            <h3 style="font-size:.88rem;font-weight:700;margin:0 0 14px">⚙️ Parámetros</h3>
            <div class="grid-2" style="gap:14px">
                <div>
                    <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:4px">Objetivo</label>
                    <select id="ia-objetivo" onchange="generarPromptIA()" style="width:100%;padding:7px 10px;border:1.5px solid var(--border-color);border-radius:7px;font-size:.85rem;font-family:inherit;background:var(--card-bg);color:var(--text-color)">
                        <option value="pérdida de peso y déficit calórico">Pérdida de peso (déficit calórico)</option>
                        <option value="mantenimiento de peso">Mantenimiento</option>
                        <option value="ganancia muscular e hipertrofia">Ganancia muscular</option>
                        <option value="definición muscular">Definición muscular</option>
                        <option value="alimentación saludable y equilibrada">Alimentación equilibrada</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:4px">Días</label>
                    <select id="ia-dias" onchange="generarPromptIA()" style="width:100%;padding:7px 10px;border:1.5px solid var(--border-color);border-radius:7px;font-size:.85rem;font-family:inherit;background:var(--card-bg);color:var(--text-color)">
                        <option value="lunes,martes,miercoles,jueves,viernes,sabado,domingo">Semana completa (7 días)</option>
                        <option value="lunes,martes,miercoles,jueves,viernes">Solo laborables (5 días)</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:14px">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:8px">Tomas a incluir</label>
                <div style="display:flex;flex-wrap:wrap;gap:10px">
                    <?php foreach ($tomas_info as $val => $ti): ?>
                    <label style="display:flex;align-items:center;gap:5px;font-size:.83rem;cursor:pointer;background:var(--card-bg);border:1.5px solid var(--border-color);border-radius:20px;padding:4px 12px">
                        <input type="checkbox" value="<?= $val ?>" checked onchange="generarPromptIA()" class="ia-toma-check" style="width:auto;margin:0">
                        <?= $ti['label'] ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="margin-top:14px">
                <label style="font-size:.82rem;font-weight:600;display:block;margin-bottom:4px">Indicaciones adicionales <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <input type="text" id="ia-extra" oninput="generarPromptIA()" placeholder="ej: sin gluten, máximo 1800 kcal/día, paciente diabético…"
                    style="width:100%;box-sizing:border-box;padding:7px 10px;border:1.5px solid var(--border-color);border-radius:7px;font-size:.85rem;font-family:inherit;background:var(--card-bg);color:var(--text-color)">
            </div>
        </div>
        <!-- Prompt generado -->
        <div style="background:var(--input-bg);border:1px solid var(--border-color);border-radius:10px;padding:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                <span style="font-size:.85rem;font-weight:700;color:var(--text-color)">📋 Prompt generado</span>
                <button id="btn-copiar-ia" onclick="copiarPromptIA()" class="btn btn-primary btn-sm">Copiar prompt</button>
            </div>
            <textarea id="prompt-ia" readonly rows="14" style="width:100%;box-sizing:border-box;font-size:.76rem;font-family:'Courier New',monospace;background:var(--card-bg);color:var(--text-color);border:1px solid var(--border-color);border-radius:6px;padding:10px;resize:vertical;line-height:1.6"></textarea>
        </div>
        <p style="font-size:.78rem;color:var(--text-muted);margin-top:10px">
            Copia el prompt → pégalo en ChatGPT, Claude o similar → guarda la respuesta como <code>.csv</code> → impórtala con el panel de abajo.
        </p>
    </div>
</div>

<!-- IMPORTAR CSV SEMANAL -->
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleImportSemanal()">
        <h2 style="margin:0;font-size:.95rem">📥 Importar dieta semanal por CSV</h2>
        <span id="import-sem-arrow" style="font-size:1.2rem;transition:transform .2s;<?= $show_import_semanal ? 'transform:rotate(180deg)' : '' ?>">▼</span>
    </div>
    <div id="import-sem-panel" style="display:<?= $show_import_semanal ? 'block' : 'none' ?>;margin-top:16px">
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:12px">
            Importa ingredientes sueltos y combinaciones para los 7 días desde un CSV. Los ingredientes se añaden a lo que ya hay (no se borran los existentes).
        </p>
        <div style="margin-bottom:14px">
            <a href="dieta_semanal_ver.php?id=<?= $dieta_id ?>&csv_plantilla_semanal=1" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">⬇ Descargar plantilla CSV</a>
        </div>
        <form method="POST" action="dieta_semanal_ver.php?id=<?= $dieta_id ?>" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="importar_semanal_csv">
            <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
                <div>
                    <label style="font-size:.85rem;font-weight:600;display:block;margin-bottom:4px">Archivo CSV</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" required style="font-size:.88rem">
                </div>
                <button type="submit" class="btn btn-primary">Importar</button>
            </div>
        </form>
        <details style="margin-top:14px">
            <summary style="font-size:.82rem;cursor:pointer;color:var(--text-muted)">Ver formato esperado</summary>
            <pre style="font-size:.78rem;background:var(--input-bg);border:1px solid var(--border-color);border-radius:6px;padding:10px;margin-top:8px;overflow-x:auto">dia,toma,tipo,ingrediente_principal,cantidad_principal,ingrediente_complemento,cantidad_complemento
lunes,desayuno,suelto,Acelgas,,,
lunes,comida,combo,Pechuga de pollo,150g,Brócoli,200g
lunes,comida,combo,Merluza,200g,,
martes,desayuno,suelto,Espinacas,,,</pre>
            <p style="font-size:.78rem;color:var(--text-muted);margin-top:6px">
                <strong>dia</strong>: lunes/martes/miercoles/jueves/viernes/sabado/domingo (o 1–7)<br>
                <strong>toma</strong>: desayuno / media_manana / almuerzo / comida / merienda / cena<br>
                <strong>tipo</strong>: suelto (ingrediente suelto) o combo (combinación plato+complemento)<br>
                El complemento es opcional en los combos. Los nombres deben coincidir exactamente con los ingredientes de la base de datos.
            </p>
        </details>
    </div>
</div>

<!-- NO COMER -->
<div class="nc-card" id="no-comer">
    <h3>🚫 No comer (este paciente)</h3>
    <p>Estos alimentos aparecerán en la línea <strong>"No comer:"</strong> del protocolo impreso.</p>
    <div class="chips-row">
        <?php if (empty($no_comer_items)): ?>
        <span style="color:#999;font-size:.85rem;align-self:center">Sin alimentos restringidos.</span>
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
    <form method="POST" class="add-row">
        <input type="hidden" name="accion" value="agregar_no_comer">
        <select name="ing_id" required>
            <option value="">— Seleccionar alimento a restringir —</option>
            <?php
            $prev_cat = '';
            foreach ($todos_nc as $ing):
                if ($ing['categoria'] !== $prev_cat) {
                    if ($prev_cat) echo '</optgroup>';
                    echo '<optgroup label="'.htmlspecialchars($cat_labels[$ing['categoria']] ?? $ing['categoria']).'">';
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

<form method="POST" id="semanal-form">

<!-- Panel: tomas visibles en el informe -->
<div class="tomas-panel">
    <span class="tomas-panel-lbl">Mostrar en informe:</span>
    <?php foreach ($tomas_info as $tk => $ti):
        $activa = in_array($tk, $tomas_activas);
    ?>
    <label class="toma-toggle <?= $activa ? 'active' : '' ?>">
        <input type="checkbox" name="toma_activa[<?= $tk ?>]" value="1"
               <?= $activa ? 'checked' : '' ?>
               onchange="this.closest('label').classList.toggle('active', this.checked)">
        <?= $ti['emoji'] ?> <?= $ti['label'] ?>
    </label>
    <?php endforeach; ?>
</div>

<!-- Tabs -->
<div class="day-tabs">
<?php foreach ($dias as $dia_num => $dia_label): ?>
    <button type="button" class="day-tab" onclick="showDay(<?= $dia_num ?>)" id="tab-<?= $dia_num ?>">
        <?= $dia_label ?>
    </button>
<?php endforeach; ?>
</div>

<?php foreach ($dias as $dia_num => $dia_label): ?>
<div class="day-panel" id="day-<?= $dia_num ?>">
    <div class="meals-grid">
    <?php foreach ($tomas_info as $toma_key => $toma_info):
        $sel_ids     = $seleccionados[$dia_num][$toma_key] ?? [];
        $meal_combos = $combos[$dia_num][$toma_key] ?? [];
    ?>
    <div class="meal-card">
        <div class="meal-header">
            <span><?= $toma_info['emoji'] ?></span>
            <h3><?= $toma_info['label'] ?></h3>
            <span class="meal-count" id="cnt-<?= $dia_num ?>-<?= $toma_key ?>"><?= count($sel_ids) ?></span>
        </div>

        <!-- Ingredientes sueltos -->
        <div class="meal-chips" id="chips-<?= $dia_num ?>-<?= $toma_key ?>">
            <?php foreach ($sel_ids as $sid):
                if (!isset($ing_map[$sid])) continue;
                $ing_name = htmlspecialchars($ing_map[$sid]['nombre']);
            ?>
            <span class="chip chip-green" id="chip-<?= $dia_num ?>-<?= $toma_key ?>-<?= $sid ?>">
                <?= $ing_name ?>
                <button type="button" onclick="removeIng(<?= $dia_num ?>,'<?= $toma_key ?>',<?= $sid ?>)">✕</button>
            </span>
            <input type="hidden" name="ing[<?= $dia_num ?>][<?= $toma_key ?>][]" value="<?= $sid ?>" id="inp-<?= $dia_num ?>-<?= $toma_key ?>-<?= $sid ?>">
            <?php endforeach; ?>
        </div>
        <select id="sel-<?= $dia_num ?>-<?= $toma_key ?>" class="meal-select"
                onchange="addIng(this,<?= $dia_num ?>,'<?= $toma_key ?>')">
            <option value="">+ Añadir ingrediente...</option>
            <?php
            $last_cat = '';
            foreach ($todos_ing as $ing):
                if ($ing['categoria'] !== $last_cat):
                    if ($last_cat) echo '</optgroup>';
                    $cat_label = htmlspecialchars($cat_labels[$ing['categoria']] ?? $ing['categoria']);
                    echo "<optgroup label=\"$cat_label\">";
                    $last_cat = $ing['categoria'];
                endif;
                $disabled = in_array($ing['id'], $sel_ids) ? ' disabled' : '';
                echo '<option value="'.$ing['id'].'"'.$disabled.'>'.htmlspecialchars($ing['nombre']).'</option>';
            endforeach;
            if ($last_cat) echo '</optgroup>';
            ?>
        </select>

        <!-- Combinaciones -->
        <div class="combos-section">
            <?php if (!empty($meal_combos)): ?>
            <div class="combos-label">Combinaciones</div>
            <?php foreach ($meal_combos as $c): ?>
            <div class="combo-chip">
                <span class="combo-principal"><?= htmlspecialchars($c['pnombre']) ?></span>
                <?php if ($c['cantidad_principal'] !== ''): ?>
                <span class="combo-qty">(<?= htmlspecialchars(fmt_qty($c['cantidad_principal'])) ?>)</span>
                <?php endif; ?>
                <?php if ($c['complemento_id']): ?>
                <span class="combo-sep">+</span>
                <span class="combo-complemento"><?= htmlspecialchars($c['cnombre']) ?></span>
                <?php if ($c['cantidad_complemento'] !== ''): ?>
                <span class="combo-qty">(<?= htmlspecialchars(fmt_qty($c['cantidad_complemento'])) ?>)</span>
                <?php endif; ?>
                <?php endif; ?>
                <button type="button" class="combo-del" title="Quitar combinación"
                    onclick="quitarCombo(<?= $c['id'] ?>, <?= $dia_num ?>)">✕</button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <button type="button" class="btn-add-combo"
                onclick="openComboModal(<?= $dia_num ?>,'<?= $toma_key ?>','<?= $dia_label ?>','<?= addslashes($toma_info['label']) ?>')">
                ⊕ Añadir combinación
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- AGUA Y COMPLEMENTOS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px" class="agua-comp-grid">
    <div class="card" style="margin:0;padding:18px 20px">
        <h3 style="font-size:.9rem;font-weight:700;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border-light)">💧 Cantidad de agua diaria</h3>
        <input type="text" name="agua" value="<?= htmlspecialchars($dieta['agua'] ?? '') ?>" placeholder="Ej: 2 litros/día">
    </div>
    <div class="card" style="margin:0;padding:18px 20px">
        <h3 style="font-size:.9rem;font-weight:700;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid var(--border-light)">💊 Complementos</h3>
        <textarea name="complementos" rows="2" placeholder="Ej: Omega-3 1g, Vitamina D 2000 UI" style="width:100%;resize:vertical"><?= htmlspecialchars($dieta['complementos'] ?? '') ?></textarea>
    </div>
</div>

</form>

<!-- Form global para vaciar toda la dieta semanal -->
<form method="POST" id="vaciar-semanal-form" style="display:none" action="dieta_semanal_ver.php?id=<?= $dieta_id ?>">
    <input type="hidden" name="accion" value="vaciar_semanal">
</form>

<!-- Form global para quitar combos — fuera de semanal-form para evitar anidamiento -->
<form method="POST" id="quitar-combo-form" style="display:none">
    <input type="hidden" name="accion" value="quitar_combo">
    <input type="hidden" name="combo_id" id="qcf-combo-id">
    <input type="hidden" name="dia"      id="qcf-dia">
</form>

</div>

<!-- MODAL: Añadir combinación -->
<div class="modal-overlay" id="combo-modal" onclick="if(event.target===this)closeComboModal()">
    <div class="modal-box">
        <div class="modal-title" id="combo-modal-title">Añadir combinación</div>
        <form method="POST" id="combo-form">
            <input type="hidden" name="accion" value="agregar_combo">
            <input type="hidden" name="dia"  id="combo-dia">
            <input type="hidden" name="toma" id="combo-toma">

            <div class="modal-field">
                <label>🍽️ Plato principal *</label>
                <select name="principal_id" id="combo-pid" class="modal-select" required>
                    <?= $ing_opts_req ?>
                </select>
                <input type="text" name="cantidad_principal" id="combo-qp" class="modal-qty"
                       placeholder="Cantidad (ej. 150g)">
            </div>

            <hr class="modal-divider">

            <div class="modal-field">
                <label>➕ Complemento <span style="font-weight:400;color:#888">(opcional)</span></label>
                <select name="complemento_id" id="combo-cid" class="modal-select">
                    <?= $ing_opts_opt ?>
                </select>
                <input type="text" name="cantidad_complemento" id="combo-qc" class="modal-qty"
                       placeholder="Cantidad (ej. 200g)">
            </div>

            <div class="modal-actions">
                <button type="button" onclick="closeComboModal()"
                    class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #e0e0e5">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Añadir combinación</button>
            </div>
        </form>
    </div>
</div>

<script>
// Al cargar la página activamos el tab del día que viene en ?dia=N (por defecto Lunes)
// Esto es necesario porque tras añadir/quitar un combo redirigimos con ?dia=N
(function() {
    const dia = parseInt(new URLSearchParams(window.location.search).get('dia')) || 1;
    showDay(dia);
})();

function showDay(dia) {
    document.querySelectorAll('.day-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.day-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('day-' + dia).classList.add('active');
    document.getElementById('tab-' + dia).classList.add('active');
}

/* Añade un ingrediente suelto a una toma: crea el chip verde y el input hidden
   para que se envíe en el formulario. También desactiva la opción del select
   para que no se pueda añadir dos veces el mismo ingrediente. */
function addIng(select, dia, toma) {
    const id = select.value;
    if (!id) return;
    const name = select.options[select.selectedIndex].text;
    select.options[select.selectedIndex].disabled = true;
    select.value = '';

    const key = dia + '-' + toma;
    const chipsEl = document.getElementById('chips-' + key);

    const chip = document.createElement('span');
    chip.className = 'chip chip-green';
    chip.id = 'chip-' + key + '-' + id;
    chip.innerHTML = name + ' <button type="button" onclick="removeIng(' + dia + ',\'' + toma + '\',' + id + ')">✕</button>';
    chipsEl.appendChild(chip);

    // El input hidden es el que realmente manda el dato al servidor al guardar
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = 'ing[' + dia + '][' + toma + '][]';
    inp.value = id;
    inp.id = 'inp-' + key + '-' + id;
    chipsEl.appendChild(inp);

    updateCount(dia, toma);
}

function removeIng(dia, toma, id) {
    const key = dia + '-' + toma;
    document.getElementById('chip-' + key + '-' + id)?.remove();
    document.getElementById('inp-' + key + '-' + id)?.remove();

    const sel = document.getElementById('sel-' + key);
    for (const opt of sel.options) {
        if (opt.value == id) { opt.disabled = false; break; }
    }
    updateCount(dia, toma);
}

function updateCount(dia, toma) {
    const cnt = document.getElementById('chips-' + dia + '-' + toma).querySelectorAll('.chip').length;
    document.getElementById('cnt-' + dia + '-' + toma).textContent = cnt;
}

function openComboModal(dia, toma, diaLabel, tomaLabel) {
    document.getElementById('combo-dia').value   = dia;
    document.getElementById('combo-toma').value  = toma;
    document.getElementById('combo-modal-title').textContent = 'Combinación · ' + diaLabel + ' · ' + tomaLabel;
    document.getElementById('combo-pid').value   = '';
    document.getElementById('combo-cid').value   = '';
    document.getElementById('combo-qp').value    = '';
    document.getElementById('combo-qc').value    = '';
    document.getElementById('combo-modal').classList.add('open');
    document.getElementById('combo-pid').focus();
}

function closeComboModal() {
    document.getElementById('combo-modal').classList.remove('open');
}

/* quitarCombo: usamos el formulario global #quitar-combo-form que está FUERA de
   #semanal-form para evitar anidar forms (HTML no permite forms anidados).
   Rellenamos sus campos hidden y lo enviamos por JS. */
function quitarCombo(comboId, dia) {
    document.getElementById('qcf-combo-id').value = comboId;
    document.getElementById('qcf-dia').value = dia;
    document.getElementById('quitar-combo-form').submit();
}

// Lista de ingredientes que PHP pasó a JS para poder generar el prompt sin pedir datos al servidor
const ING_IA = <?= json_encode($ings_ia, JSON_UNESCAPED_UNICODE) ?>;
const PACIENTE_INTOLERANCIAS = <?= json_encode($dieta['intolerancias'] ?? '') ?>;

/* Generador de prompt para IA: construye un texto con todos los ingredientes disponibles
   y las instrucciones de formato CSV para que ChatGPT o Claude devuelvan algo que luego
   podemos importar directamente con el panel de abajo */
function generarPromptIA() {
    var objetivo = document.getElementById('ia-objetivo').value;
    var diasVal  = document.getElementById('ia-dias').value;
    var extra    = document.getElementById('ia-extra').value.trim();
    var tomas    = Array.from(document.querySelectorAll('.ia-toma-check:checked')).map(c => c.value);
    var dias     = diasVal.split(',');

    if (tomas.length === 0) {
        document.getElementById('prompt-ia').value = '⚠ Selecciona al menos una toma.';
        return;
    }

    var ingLines = 'nombre,categoria\n';
    ING_IA.forEach(function(i) { ingLines += i.nombre + ',' + i.categoria + '\n'; });

    var tomasStr = tomas.join(' / ');
    var diasStr  = dias.join(' / ');
    var extraLine = extra ? '\nINDICACIONES ADICIONALES: ' + extra : '';
    var intolerLine = PACIENTE_INTOLERANCIAS ? '\nINTOLERANCIAS / ALERGIAS DEL PACIENTE: ' + PACIENTE_INTOLERANCIAS + ' — no uses estos alimentos bajo ningún concepto.' : '';

    var prompt =
'Eres un nutricionista experto. Genera una dieta semanal usando ÚNICAMENTE los ingredientes de la lista que aparece más abajo.\n\n' +
'OBJETIVO: ' + objetivo + '\n' +
'DÍAS: ' + diasStr + '\n' +
'TOMAS: ' + tomasStr +
intolerLine +
extraLine + '\n\n' +
'INGREDIENTES DISPONIBLES (usa los nombres exactamente como aparecen aquí):\n' +
ingLines + '\n' +
'FORMATO DE SALIDA — devuelve SOLO un CSV con estas columnas exactas, sin texto adicional:\n' +
'dia,toma,tipo,ingrediente_principal,cantidad_principal,ingrediente_complemento,cantidad_complemento\n\n' +
'REGLAS DEL CSV:\n' +
'- "dia": ' + diasStr + '\n' +
'- "toma": ' + tomasStr + '\n' +
'- "tipo": suelto (un ingrediente solo) o combo (principal + complemento opcional)\n' +
'- Cantidades en gramos, ej. 150g — o vacío si no aplica\n' +
'- En un combo sin complemento, deja las últimas dos columnas vacías\n' +
'- No repitas el mismo ingrediente principal dos días seguidos en la misma toma\n' +
'- Varía entre proteínas, verduras y frutas según la toma\n\n' +
'EJEMPLO de las tres variantes:\n' +
'dia,toma,tipo,ingrediente_principal,cantidad_principal,ingrediente_complemento,cantidad_complemento\n' +
'lunes,desayuno,suelto,Acelgas,,,\n' +
'lunes,comida,combo,Pechuga de pollo,150g,Brócoli,200g\n' +
'lunes,cena,combo,Merluza,180g,,\n\n' +
'Devuelve ÚNICAMENTE el CSV. Nada más.';

    document.getElementById('prompt-ia').value = prompt;
}

function toggleGenIA() {
    var panel = document.getElementById('gen-ia-panel');
    var arrow = document.getElementById('gen-ia-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
        generarPromptIA();
    } else {
        panel.style.display = 'none';
        arrow.style.transform = '';
    }
}

function copiarPromptIA() {
    var ta = document.getElementById('prompt-ia');
    // Copiamos el prompt al portapapeles y damos feedback visual durante 2 segundos
    navigator.clipboard.writeText(ta.value).then(function() {
        var btn = document.getElementById('btn-copiar-ia');
        var orig = btn.textContent;
        btn.textContent = '✓ Copiado';
        btn.style.background = '#34c759';
        setTimeout(function() { btn.textContent = orig; btn.style.background = ''; }, 2000);
    });
}

function toggleImportSemanal() {
    var panel = document.getElementById('import-sem-panel');
    var arrow = document.getElementById('import-sem-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        arrow.style.transform = 'rotate(180deg)';
    } else {
        panel.style.display = 'none';
        arrow.style.transform = '';
    }
}
</script>
<script src="js/theme.js"></script>
</body>
</html>
