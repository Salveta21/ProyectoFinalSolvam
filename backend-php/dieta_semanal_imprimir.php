<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

$dieta_id = (int)($_GET['id'] ?? 0);
if (!$dieta_id) { header("Location: index.php"); exit; }

$dieta = $conn->query("SELECT d.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos, c.intolerancias
    FROM dietas d JOIN clientes c ON c.id=d.cliente_id WHERE d.id=$dieta_id")->fetch_assoc();
if (!$dieta) { header("Location: index.php"); exit; }

$dias = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];
$tomas_info = [
    'desayuno'     => ['emoji'=>'🌅', 'label'=>'Desayuno'],
    'media_manana' => ['emoji'=>'🍎', 'label'=>'Media mañana'],
    'almuerzo'     => ['emoji'=>'🍽️', 'label'=>'Almuerzo'],
    'comida'       => ['emoji'=>'🍲', 'label'=>'Comida'],
    'merienda'     => ['emoji'=>'🍊', 'label'=>'Merienda'],
    'cena'         => ['emoji'=>'🌙', 'label'=>'Cena'],
];

// Ingredientes sueltos: grid[$dia][$toma] = [nombres]
$grid = [];
$res = $conn->query("
    SELECT sc.dia, sc.toma, i.nombre
    FROM dieta_semanal_comidas sc
    JOIN ingredientes i ON i.id = sc.ingrediente_id
    WHERE sc.dieta_id = $dieta_id
    ORDER BY sc.dia, sc.toma, i.nombre
");
while ($r = $res->fetch_assoc()) {
    $grid[$r['dia']][$r['toma']][] = $r['nombre'];
}

// Combinaciones: combos[$dia][$toma] = [...]
$combos = [];
$res_c = $conn->query("
    SELECT c.dia, c.toma,
           ip.nombre as pnombre, c.cantidad_principal,
           c.complemento_id, IFNULL(ic.nombre,'') as cnombre, c.cantidad_complemento
    FROM dieta_semanal_combinaciones c
    JOIN ingredientes ip ON ip.id = c.principal_id
    LEFT JOIN ingredientes ic ON ic.id = c.complemento_id
    WHERE c.dieta_id = $dieta_id
    ORDER BY c.dia, c.toma, c.id
");
if ($res_c) {
    while ($r = $res_c->fetch_assoc()) {
        $combos[$r['dia']][$r['toma']][] = $r;
    }
}

function fmt_qty(string $q): string {
    return ($q !== '' && is_numeric($q)) ? $q . 'g' : $q;
}

// Filtrar tomas según configuración guardada (NULL = todas activas)
$tomas_activas = !empty($dieta['tomas_activas'])
    ? json_decode($dieta['tomas_activas'], true)
    : array_keys($tomas_info);
$tomas_info = array_filter(
    $tomas_info,
    fn($tk) => in_array($tk, $tomas_activas),
    ARRAY_FILTER_USE_KEY
);

// No comer
$nc_res = $conn->query("SELECT i.nombre FROM dieta_no_permitidos dn JOIN ingredientes i ON i.id=dn.ingrediente_id WHERE dn.dieta_id=$dieta_id ORDER BY i.nombre");
$no_comer = $nc_res ? array_column($nc_res->fetch_all(MYSQLI_ASSOC), 'nombre') : [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<script src="js/theme-init.js"></script>
<title>Dieta Semanal – <?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?></title>
<style>
@page { size: A4 landscape; margin: 12mm; }

* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #1a1a1a;
    background: #fff;
}

/* ── Barra de acción (solo pantalla) ── */
.no-print {
    background: #1d1d1f;
    padding: 10px 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}
.no-print a, .no-print button {
    padding: 8px 18px; border-radius: 8px; font-size: .85rem;
    font-weight: 600; cursor: pointer; text-decoration: none;
    border: none; font-family: inherit;
}
.no-print .btn-print { background: #0071e3; color: #fff; }
.no-print .btn-back  { background: #f5f5f7; color: #333; }

/* ── Documento ── */
.doc { padding: 14px 16px; }

.doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 2px solid #1d1d1f;
}
.doc-header .brand { font-size: 13pt; font-weight: 800; letter-spacing: -.3px; }
.doc-header .patient { text-align: right; }
.doc-header .patient strong { font-size: 11pt; }
.doc-header .patient .meta { font-size: 8pt; color: #555; margin-top: 2px; }

.no-comer-line {
    font-size: 8pt;
    background: #fff2f2;
    border: 1px solid #ffcdd2;
    border-radius: 4px;
    padding: 4px 10px;
    margin-bottom: 10px;
    color: #c62828;
}

/* ── Tabla semanal ── */
.week-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.week-table th, .week-table td {
    border: 1px solid #d0d0d5;
    padding: 5px 6px;
    vertical-align: top;
}

/* Columna de tomas */
.week-table th.col-toma,
.week-table td.col-toma {
    width: 78px;
    background: #1d1d1f;
    color: #fff;
    font-size: 8pt;
    font-weight: 700;
    text-align: center;
}

/* Cabecera días */
.week-table thead th {
    background: #2c2c2e;
    color: #fff;
    font-size: 8.5pt;
    font-weight: 700;
    text-align: center;
    padding: 6px 4px;
}
.week-table thead th.col-toma { background: #111; }

/* Celdas de ingredientes */
.week-table td.ing-cell {
    font-size: 7.8pt;
    line-height: 1.5;
    background: #fff;
}
.week-table td.ing-cell:empty::after { content: '—'; color: #ccc; }
.week-table tbody tr:nth-child(odd) td.ing-cell { background: #fafafa; }

/* Toma label dentro de columna lateral */
.toma-label { font-size: 7pt; opacity: .7; display: block; margin-top: 2px; }

/* Combinaciones en la celda de impresión */
.combo-block {
    margin-bottom: 4px;
    line-height: 1.45;
    border-left: 2px solid #0071e3;
    padding-left: 4px;
}
.combo-row-line { display: block; }
.combo-lbl {
    font-size: 6.5pt;
    font-weight: 700;
    color: #0071e3;
    text-transform: uppercase;
    letter-spacing: .3px;
    margin-right: 2px;
}
.combo-name-p { font-weight: 700; color: #1a1a1a; font-size: 7.8pt; }
.combo-name-c { color: #333; font-size: 7.8pt; }
.combo-qty    { color: #888; font-size: 7pt; font-style: italic; margin-left: 2px; }

.loose-items { margin-top: 2px; }
.loose-after-combo { margin-top: 5px; padding-top: 4px; border-top: 1px dashed #d0d0d5; }

/* ── Pie ── */
.doc-footer {
    margin-top: 10px;
    font-size: 7.5pt;
    color: #888;
    text-align: center;
    border-top: 1px solid #e0e0e0;
    padding-top: 6px;
}

@media print {
    .no-print { display: none !important; }
    body { font-size: 8.5pt; }
}
</style>
</head>
<body>

<!-- Barra superior (solo pantalla) -->
<div class="no-print">
    <a href="dieta_semanal_ver.php?id=<?= $dieta_id ?>" class="btn-back">← Volver a editar</a>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    <a href="logout.php" class="btn-back" style="color:var(--text-muted)">Salir</a>
</div>

<div class="doc">

    <!-- Cabecera -->
    <div class="doc-header">
        <div>
            <div class="brand">🥗 <?= htmlspecialchars(APP_NAME) ?></div>
            <div style="font-size:10pt;font-weight:700;margin-top:4px">
                <?= htmlspecialchars($dieta['nombre']) ?>
            </div>
        </div>
        <div class="patient">
            <strong><?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?></strong>
            <div class="meta">Fecha: <?= $dieta['fecha'] ?> &nbsp;·&nbsp; Tipo: Dieta semanal</div>
        </div>
    </div>

    <?php if ($no_comer): ?>
    <div class="no-comer-line">
        🚫 <strong>No comer:</strong> <?= htmlspecialchars(implode(', ', $no_comer)) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($dieta['intolerancias'])): ?>
    <div class="no-comer-line" style="background:#fff8e1;border-color:#ffe082;color:#5a4000">
        ⚠️ <strong>Intolerancias:</strong> <?= htmlspecialchars($dieta['intolerancias']) ?>
    </div>
    <?php endif; ?>

    <!-- Tabla semanal -->
    <table class="week-table">
        <thead>
            <tr>
                <th class="col-toma"></th>
                <?php foreach ($dias as $dia_label): ?>
                <th><?= $dia_label ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tomas_info as $toma_key => $toma_info): ?>
        <tr>
            <td class="col-toma">
                <?= $toma_info['emoji'] ?>
                <span class="toma-label"><?= $toma_info['label'] ?></span>
            </td>
            <?php foreach (array_keys($dias) as $dia_num): ?>
            <td class="ing-cell">
                <?php
                $cell_combos = $combos[$dia_num][$toma_key] ?? [];
                $loose_items = $grid[$dia_num][$toma_key] ?? [];

                foreach ($cell_combos as $c):
                    $pn = htmlspecialchars($c['pnombre']);
                    $qp = htmlspecialchars(fmt_qty($c['cantidad_principal']));
                    $has_comp = !empty($c['complemento_id']);
                    $cn = $has_comp ? htmlspecialchars($c['cnombre']) : '';
                    $qc = $has_comp ? htmlspecialchars(fmt_qty($c['cantidad_complemento'])) : '';
                ?>
                <div class="combo-block">
                    <span class="combo-row-line">
                        <span class="combo-lbl">Plato principal</span>
                        <span class="combo-name-p"><?= $pn ?></span>
                        <?php if ($qp): ?><span class="combo-qty">(<?= $qp ?>)</span><?php endif; ?>
                    </span>
                    <?php if ($has_comp): ?>
                    <span class="combo-row-line">
                        <span class="combo-lbl">Complemento</span>
                        <span class="combo-name-c"><?= $cn ?></span>
                        <?php if ($qc): ?><span class="combo-qty">(<?= $qc ?>)</span><?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <?php if (!empty($loose_items)): ?>
                <div class="loose-items<?= !empty($cell_combos) ? ' loose-after-combo' : '' ?>">
                <?php foreach ($loose_items as $nombre): ?>
                    • <?= htmlspecialchars($nombre) ?><br>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($dieta['observaciones']): ?>
    <div style="margin-top:10px;font-size:8pt;color:#555;border-top:1px solid #e0e0e0;padding-top:6px">
        <strong>Observaciones:</strong> <?= htmlspecialchars($dieta['observaciones']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($dieta['agua']) || !empty($dieta['complementos'])): ?>
    <div style="margin-top:8px;font-size:8pt;color:#555;padding-top:4px">
        <?php if (!empty($dieta['agua'])): ?>💧 <strong>Agua:</strong> <?= htmlspecialchars($dieta['agua']) ?><?php endif; ?>
        <?php if (!empty($dieta['agua']) && !empty($dieta['complementos'])): ?> &nbsp;·&nbsp; <?php endif; ?>
        <?php if (!empty($dieta['complementos'])): ?>💊 <strong>Complementos:</strong> <?= htmlspecialchars($dieta['complementos']) ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="doc-footer">
        <?= htmlspecialchars(APP_NAME) ?> · Protocolo generado el <?= date('d/m/Y') ?>
    </div>

</div>
<script src="js/theme.js"></script>
</body>
</html>
