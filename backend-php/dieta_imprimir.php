<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

$dieta_id = (int)($_GET['id'] ?? 0);
if (!$dieta_id) { header("Location: index.php"); exit; }

$dieta = $conn->query("SELECT d.*, c.nombre as cli_nombre, c.apellidos as cli_apellidos,
    c.fecha_nacimiento, c.sexo, c.peso_kg, c.altura_cm, c.objetivo, c.intolerancias
    FROM dietas d JOIN clientes c ON c.id=d.cliente_id WHERE d.id=$dieta_id")->fetch_assoc();
if (!$dieta) { header("Location: index.php"); exit; }

// Ingredientes PERMITIDOS para esta dieta (los seleccionados con checkboxes)
$sel_res = $conn->query("
    SELECT i.nombre, i.categoria
    FROM dieta_ingredientes di
    JOIN ingredientes i ON i.id=di.ingrediente_id
    WHERE di.dieta_id=$dieta_id
    ORDER BY i.categoria, i.id
");
$perm_por_cat = [];
while ($row = $sel_res->fetch_assoc()) {
    $perm_por_cat[$row['categoria']][] = $row['nombre'];
}

// Alimentos "No comer" específicos de esta dieta
$nc_res = $conn->query("
    SELECT i.nombre, i.categoria
    FROM dieta_no_permitidos dn
    JOIN ingredientes i ON i.id=dn.ingrediente_id
    WHERE dn.dieta_id=$dieta_id
    ORDER BY i.categoria, i.nombre
");
$nc_por_cat = [];
while ($row = $nc_res->fetch_assoc()) {
    $nc_por_cat[$row['categoria']][] = $row['nombre'];
}

$verd_ok  = $perm_por_cat['verdura']  ?? [];
$verd_no  = $nc_por_cat['verdura']    ?? [];
$carne_ok = $perm_por_cat['carne']    ?? [];
$carne_no = $nc_por_cat['carne']      ?? [];
$pesc_ok  = $perm_por_cat['pescado']  ?? [];
$pesc_no  = $nc_por_cat['pescado']    ?? [];
$fruta_ok = $perm_por_cat['fruta']    ?? [];
$fruta_no = $nc_por_cat['fruta']      ?? [];

// Condimentos seleccionados para esta dieta
$cond_res = $conn->query("
    SELECT i.nombre
    FROM dieta_ingredientes di
    JOIN ingredientes i ON i.id=di.ingrediente_id
    WHERE di.dieta_id=$dieta_id AND i.categoria='condimento'
    ORDER BY i.nombre
");
$condimentos_lista = [];
while ($c = $cond_res->fetch_assoc()) { $condimentos_lista[] = $c['nombre']; }

$no_permitidos_res = $conn->query("SELECT nombre FROM no_permitidos WHERE activo=1 ORDER BY id");
$no_perm_list = [];
while ($np = $no_permitidos_res->fetch_assoc()) {
    $no_perm_list[] = $np['nombre'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="js/theme-init.js"></script>
<title>Protocolo – <?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }

body {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
    background: #f5f5f7;
    color: #1d1d1f;
    min-height: 100vh;
}

.no-print {
    text-align: center;
    padding: 14px;
    background: rgba(28,28,30,0.98);
    color: white;
    position: sticky;
    top: 0;
    z-index: 100;
}
.no-print button {
    padding: 9px 20px;
    background: #0071e3;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    margin: 0 6px;
    transition: background .2s;
}
.no-print button:hover { background: #0077ed; }
.no-print a {
    color: rgba(255,255,255,0.8);
    font-size: 13px;
    text-decoration: none;
    margin: 0 10px;
    font-weight: 500;
}
.no-print a:hover { color: white; }

.page {
    background: white;
    max-width: 760px;
    margin: 24px auto;
    padding: 32px 40px;
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    border-radius: 16px;
}

/* ---- DATOS PACIENTE ---- */
.datos-paciente {
    font-size: 9.5pt;
    color: #6e6e73;
    margin-bottom: 20px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e0e0e5;
}
.datos-paciente strong { color: #1d1d1f; font-weight: 600; }

/* ---- SECCIÓN CATEGORÍA ---- */
.seccion { margin-bottom: 16px; }

.cat-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.cat-emoji {
    font-size: 2rem;
    line-height: 1;
    flex-shrink: 0;
}
.cat-nombre {
    font-size: 11pt;
    font-weight: 800;
    letter-spacing: 0.5px;
    color: #1d1d1f;
}

.cat-body { margin-left: 48px; }

/* Items en columnas */
.items-cols {
    column-gap: 12px;
    margin-bottom: 6px;
    font-size: 9.5pt;
    line-height: 1.7;
}
.items-cols-3 { columns: 3; }
.items-cols-1 { columns: 1; }

.items-inline {
    font-size: 9.5pt;
    line-height: 1.7;
}

.no-comer {
    font-size: 9pt;
    font-weight: 600;
    font-style: italic;
    color: #c62828;
    margin-top: 4px;
}

/* Huevos */
.huevos-linea {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    padding: 14px 16px;
    background: #fafafa;
    border-radius: 10px;
}
.huevos-nota {
    font-size: 10pt;
    font-weight: 500;
    color: #333;
}

/* Condimentos */
.condimentos-bloque {
    font-size: 9.5pt;
    margin-top: 12px;
    line-height: 1.6;
    padding: 14px 16px;
    background: #fafafa;
    border-radius: 10px;
}
.condimentos-bloque strong {
    font-weight: 700;
    font-size: 9.5pt;
    color: #1d1d1f;
}

/* Separador */
hr.sep {
    border: none;
    border-top: 1.5px solid #e0e0e5;
    margin: 20px 0;
}

/* ---- NO PERMITIDOS ---- */
.no-perm-box {
    background: linear-gradient(135deg, #1c1c1e 0%, #2c2c2e 100%);
    color: white;
    padding: 18px 20px;
    border-radius: 12px;
    margin-top: 8px;
}
.no-perm-titulo {
    text-align: center;
    font-size: 10pt;
    font-weight: 800;
    letter-spacing: 2px;
    margin-bottom: 14px;
    color: rgba(255,255,255,0.9);
}
.no-perm-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 0;
    max-width: 400px;
    margin: 0 auto;
    text-align: center;
    font-size: 9.5pt;
    color: rgba(255,255,255,0.85);
}

/* ---- BRANDING ---- */
.branding {
    text-align: center;
    margin-top: 26px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e5;
}
.branding-text {
    font-size: 16pt;
    font-weight: 900;
    letter-spacing: 4px;
    color: #1d1d1f;
}
.branding-small {
    display: block;
    font-size: 7.5pt;
    letter-spacing: 6px;
    font-weight: 500;
    margin-top: 2px;
    color: #6e6e73;
}

@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .page {
        margin: 0;
        padding: 20px 28px;
        box-shadow: none;
        max-width: 100%;
        border-radius: 0;
    }
}

@media (max-width: 600px) {
    .page {
        margin: 16px;
        padding: 24px 18px;
    }
    .cat-body { margin-left: 40px; }
    .items-cols-3 { columns: 2; }
    .no-perm-grid { grid-template-columns: 1fr; }
    .huevos-linea, .condimentos-bloque { padding: 12px 14px; }
}
</style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <a href="dieta_ver.php?id=<?= $dieta_id ?>">← Editar</a>
    <a href="dieta_lista.php?cliente=<?= $dieta['cliente_id'] ?>">Ver dietas</a>
    <a href="logout.php" style="color:var(--text-muted)">Salir</a>
</div>

<div class="page">

    <!-- DATOS PACIENTE -->
    <?php if ($dieta['cli_nombre']): ?>
    <div class="datos-paciente">
        <strong>Paciente:</strong> <?= htmlspecialchars($dieta['cli_nombre'].' '.$dieta['cli_apellidos']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($dieta['intolerancias'])): ?>
    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:8px 14px;margin-bottom:14px;font-size:9pt;color:#5a4000">
        ⚠️ <strong>Intolerancias:</strong> <?= htmlspecialchars($dieta['intolerancias']) ?>
    </div>
    <?php endif; ?>

    <!-- ===== VERDURAS ===== -->
    <?php if (!empty($verd_ok) || !empty($verd_no)): ?>
    <div class="seccion">
        <div class="cat-header">
            <div class="cat-emoji">🍅</div>
            <div class="cat-nombre">VERDURAS</div>
        </div>
        <?php if (!empty($verd_ok)): ?>
        <div class="cat-body">
            <div class="items-cols items-cols-3">
                <?php foreach ($verd_ok as $v): ?>
                <div><?= htmlspecialchars($v) ?></div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($verd_no)): ?>
            <div class="no-comer">No comer: <?= htmlspecialchars(implode(', ', array_map('strtolower', $verd_no))) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== CARNE ===== -->
    <?php if (!empty($carne_ok)): ?>
    <div class="seccion">
        <div class="cat-header">
            <div class="cat-emoji">🍗</div>
            <div class="cat-nombre">CARNE</div>
        </div>
        <div class="cat-body">
            <div class="items-cols items-cols-1">
                <?php foreach ($carne_ok as $c): ?>
                <div><?= htmlspecialchars($c) ?></div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($carne_no)): ?>
            <div class="no-comer">No comer: <?= htmlspecialchars(implode(', ', $carne_no)) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== HUEVOS ===== -->
    <?php if (!empty($dieta['nota_huevos'])): ?>
    <div class="huevos-linea">
        <div class="cat-emoji">🥚</div>
        <div class="cat-nombre">HUEVOS</div>
        <div class="huevos-nota"><?= htmlspecialchars($dieta['nota_huevos']) ?></div>
    </div>
    <?php endif; ?>

    <!-- ===== PESCADO ===== -->
    <?php if (!empty($pesc_ok) || !empty($pesc_no)): ?>
    <div class="seccion">
        <div class="cat-header">
            <div class="cat-emoji">🐟</div>
            <div class="cat-nombre">PESCADO</div>
        </div>
        <?php if (!empty($pesc_ok)): ?>
        <div class="cat-body">
            <div class="items-cols items-cols-3">
                <?php foreach ($pesc_ok as $p): ?>
                <div><?= htmlspecialchars($p) ?></div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($pesc_no)): ?>
            <div class="no-comer">No comer: <?= htmlspecialchars(implode(', ', $pesc_no)) ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ===== FRUTA ===== -->
    <?php if (!empty($fruta_ok)): ?>
    <div class="seccion" style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div class="cat-emoji">🍏</div>
        <div>
            <div class="cat-nombre" style="display:inline;margin-right:8px">FRUTA</div>
            <span class="items-inline"><?= htmlspecialchars(implode(', ', $fruta_ok)) ?></span>
            <?php if (!empty($fruta_no)): ?>
            <div class="no-comer">No comer: <?= htmlspecialchars(implode(', ', $fruta_no)) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== CONDIMENTOS ===== -->
    <?php if (!empty($condimentos_lista)): ?>
    <div class="condimentos-bloque">
        <strong>CONDIMENTOS:</strong> <?= htmlspecialchars(implode(', ', $condimentos_lista).'.') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($dieta['agua'])): ?>
    <div class="condimentos-bloque" style="margin-top:8px">
        💧 <strong>Agua recomendada:</strong> <?= htmlspecialchars($dieta['agua']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($dieta['complementos'])): ?>
    <div class="condimentos-bloque" style="margin-top:8px">
        <strong>💊 COMPLEMENTOS:</strong> <?= htmlspecialchars($dieta['complementos']) ?>
    </div>
    <?php endif; ?>

    <!-- SEPARADOR -->
    <hr class="sep">

    <!-- ===== NO PERMITIDOS ===== -->
    <?php if (!empty($no_perm_list)): ?>
    <div class="no-perm-box">
        <div class="no-perm-titulo">NO PERMITIDOS</div>
        <div class="no-perm-grid">
            <?php foreach ($no_perm_list as $np): ?>
            <div><?= htmlspecialchars($np) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- BRANDING -->
    <div class="branding">
        <div class="branding-text">SANS</div>
        <div class="branding-small">CLINIQUE</div>
    </div>

</div><!-- /page -->
<script src="js/theme.js"></script>
</body>
</html>
