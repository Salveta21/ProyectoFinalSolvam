<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

// Recogemos el cliente por GET y verificamos que existe
$cliente_id = (int)($_GET['cliente'] ?? 0);
if (!$cliente_id) { header("Location: clientes.php"); exit; }

$cliente = $conn->query("SELECT * FROM clientes WHERE id=$cliente_id")->fetch_assoc();
if (!$cliente) { header("Location: clientes.php"); exit; }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'añadir') {
        $fecha    = $_POST['fecha'] ?: date('Y-m-d');
        $peso     = $_POST['peso_kg']    !== '' ? (float)$_POST['peso_kg']    : null;
        $grasa    = $_POST['grasa_pct']  !== '' ? (float)$_POST['grasa_pct']  : null;
        $musculo  = $_POST['musculo_pct'] !== '' ? (float)$_POST['musculo_pct'] : null;
        $obs      = trim($_POST['observaciones'] ?? '');

        // Sentencia preparada para insertar la medición de forma segura
        $stmt = $conn->prepare("INSERT INTO cliente_medidas (cliente_id,fecha,peso_kg,grasa_pct,musculo_pct,observaciones) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isddds", $cliente_id, $fecha, $peso, $grasa, $musculo, $obs);
        $stmt->execute();
        // Redirigimos para evitar que al refrescar se vuelva a insertar la medición
        header("Location: cliente_seguimiento.php?cliente=$cliente_id&ok=1");
        exit;

    } elseif ($_POST['accion'] === 'eliminar' && !empty($_POST['medida_id'])) {
        $mid = (int)$_POST['medida_id'];
        $conn->query("DELETE FROM cliente_medidas WHERE id=$mid AND cliente_id=$cliente_id");
        header("Location: cliente_seguimiento.php?cliente=$cliente_id");
        exit;
    }
}

if (isset($_GET['ok'])) {
    $msg = '<div class="alert alert-success">Medida registrada correctamente.</div>';
}

// Cargamos el historial completo ordenado de más reciente a más antiguo
$res = $conn->query("SELECT * FROM cliente_medidas WHERE cliente_id=$cliente_id ORDER BY fecha DESC, id DESC");
$medidas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/* Función de tendencia: compara el valor actual con el anterior y devuelve un HTML
   con una flecha de colores. Subir = rojo (flecha arriba), bajar = verde (flecha abajo).
   Si la diferencia es menor de 0.01 consideramos que no hay cambio y mostramos →. */
function tendencia($actual, $anterior): string {
    if ($actual === null || $anterior === null) return '';
    $diff = $actual - $anterior;
    if (abs($diff) < 0.01) return '<span style="color:#888">→</span>';
    return $diff > 0
        ? '<span style="color:#dc2626">↑ +'.number_format(abs($diff),1).'</span>'
        : '<span style="color:#16a34a">↓ -'.number_format(abs($diff),1).'</span>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Seguimiento – <?= htmlspecialchars($cliente['nombre'].' '.$cliente['apellidos']) ?></title>
<link rel="stylesheet" href="css/style.css?v=1777485477171">
<script src="js/theme-init.js"></script>
<style>
.resumen-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.resumen-card {
    background: var(--bg-color);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 14px 16px;
    text-align: center;
}
.resumen-card .valor {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-color);
}
.resumen-card .etiqueta {
    font-size: .78rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.resumen-card .tendencia {
    font-size: .85rem;
    margin-top: 4px;
}
.form-inline-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 12px;
    align-items: end;
}
@media (max-width: 600px) {
    .resumen-cards { grid-template-columns: 1fr 1fr; }
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

<!-- Cabecera del cliente -->
<div class="card" style="margin-bottom:0;border-bottom-left-radius:0;border-bottom-right-radius:0;border-bottom:1px solid var(--border-color)">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <div>
            <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars($cliente['nombre'].' '.$cliente['apellidos']) ?></div>
            <div style="font-size:.85rem;color:var(--text-muted);margin-top:2px">
                <?php if ($cliente['peso_kg']): ?>Peso inicial: <?= $cliente['peso_kg'] ?> kg &nbsp;·&nbsp; <?php endif; ?>
                <?php if ($cliente['altura_cm']): ?>Altura: <?= $cliente['altura_cm'] ?> cm &nbsp;·&nbsp; <?php endif; ?>
                <?php if ($cliente['objetivo']): ?>Objetivo: <?= htmlspecialchars($cliente['objetivo']) ?><?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="clientes.php?editar=<?= $cliente_id ?>" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #d0d0d5">✏️ Editar datos</a>
            <a href="dieta_lista.php?cliente=<?= $cliente_id ?>" class="btn btn-info btn-sm">Ver dietas</a>
            <a href="clientes.php" class="btn btn-sm" style="background:#f5f5f7;color:#333;border:1.5px solid #d0d0d5">← Volver</a>
        </div>
    </div>
</div>

<div class="card" style="border-top-left-radius:0;border-top-right-radius:0">

    <!-- Resumen última medición: tomamos la primera fila (más reciente) y la comparamos con la segunda -->
    <?php if (!empty($medidas)):
        $ultima   = $medidas[0];
        $anterior = $medidas[1] ?? null;
    ?>
    <h3 style="margin-bottom:12px;font-size:.95rem;color:#6e6e73;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Última medición — <?= date('d/m/Y', strtotime($ultima['fecha'])) ?></h3>
    <div class="resumen-cards">
        <div class="resumen-card">
            <div class="valor"><?= $ultima['peso_kg'] !== null ? $ultima['peso_kg'].' kg' : '—' ?></div>
            <div class="etiqueta">Peso</div>
            <div class="tendencia"><?= tendencia($ultima['peso_kg'], $anterior['peso_kg'] ?? null) ?></div>
        </div>
        <div class="resumen-card">
            <div class="valor"><?= $ultima['grasa_pct'] !== null ? $ultima['grasa_pct'].' %' : '—' ?></div>
            <div class="etiqueta">Grasa corporal</div>
            <div class="tendencia"><?= tendencia($ultima['grasa_pct'], $anterior['grasa_pct'] ?? null) ?></div>
        </div>
        <div class="resumen-card">
            <div class="valor"><?= $ultima['musculo_pct'] !== null ? $ultima['musculo_pct'].' %' : '—' ?></div>
            <div class="etiqueta">Músculo</div>
            <div class="tendencia"><?= tendencia($ultima['musculo_pct'], $anterior['musculo_pct'] ?? null) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Formulario nueva medición -->
    <h3 style="margin-bottom:12px;font-size:.95rem;color:#6e6e73;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Registrar nueva medición</h3>
    <form method="POST">
        <input type="hidden" name="accion" value="añadir">
        <div class="form-inline-grid">
            <div><label>Fecha</label><input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required></div>
            <div><label>Peso (kg)</label><input type="number" step="0.01" name="peso_kg" placeholder="ej. 72.5"></div>
            <div><label>Grasa corporal (%)</label><input type="number" step="0.1" name="grasa_pct" placeholder="ej. 22.0"></div>
            <div><label>Músculo (%)</label><input type="number" step="0.1" name="musculo_pct" placeholder="ej. 38.5"></div>
            <div style="grid-column:1/-1"><label>Observaciones</label><input type="text" name="observaciones" placeholder="Opcional"></div>
        </div>
        <div style="margin-top:14px">
            <button type="submit" class="btn btn-primary">Guardar medición</button>
        </div>
    </form>

    <!-- Historial -->
    <?php if (!empty($medidas)): ?>
    <h3 style="margin:24px 0 12px;font-size:.95rem;color:#6e6e73;font-weight:600;text-transform:uppercase;letter-spacing:.5px">Historial</h3>
    <div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Peso (kg)</th>
                <th>Grasa (%)</th>
                <th>Músculo (%)</th>
                <th>Observaciones</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php $total = count($medidas); foreach ($medidas as $i => $m):
            $prev = $medidas[$i + 1] ?? null;
            // La última fila no tiene fila anterior, así que no mostramos flecha
            $tiene_prev = $i < $total - 1;
        ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
            <td>
                <?= $m['peso_kg'] !== null ? $m['peso_kg'] : '—' ?>
                <?= $tiene_prev ? tendencia($m['peso_kg'], $prev['peso_kg'] ?? null) : '' ?>
            </td>
            <td>
                <?= $m['grasa_pct'] !== null ? $m['grasa_pct'] : '—' ?>
                <?= $tiene_prev ? tendencia($m['grasa_pct'], $prev['grasa_pct'] ?? null) : '' ?>
            </td>
            <td>
                <?= $m['musculo_pct'] !== null ? $m['musculo_pct'] : '—' ?>
                <?= $tiene_prev ? tendencia($m['musculo_pct'], $prev['musculo_pct'] ?? null) : '' ?>
            </td>
            <td style="color:#555"><?= htmlspecialchars($m['observaciones'] ?? '') ?></td>
            <td>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta medición?')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="medida_id" value="<?= $m['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <p style="color:#999;margin-top:20px;text-align:center">Aún no hay mediciones registradas para este cliente.</p>
    <?php endif; ?>

</div>
</div>
<script src="js/theme.js"></script>
</body>
</html>
