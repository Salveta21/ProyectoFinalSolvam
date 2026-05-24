<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

// Validamos que viene un cliente por GET, si no redirigimos
$cliente_id = (int)($_GET['cliente'] ?? 0);
if (!$cliente_id) { header("Location: clientes.php"); exit; }

$cliente = $conn->query("SELECT * FROM clientes WHERE id=$cliente_id")->fetch_assoc();
// Leemos qué dieta tiene fijada el cliente (puede ser NULL si está en modo auto)
$dieta_activa_id = $cliente['dieta_activa_id'] ? (int)$cliente['dieta_activa_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    if ($accion === 'eliminar') {
        /* Al eliminar una dieta hay que borrar también todos sus datos relacionados
           en las tablas asociadas, de lo contrario quedarían registros huérfanos */
        $dieta_id = (int)$_POST['id'];
        $conn->query("DELETE FROM dieta_ingredientes WHERE dieta_id=$dieta_id");
        $conn->query("DELETE FROM dieta_no_permitidos WHERE dieta_id=$dieta_id");
        $conn->query("DELETE FROM dieta_semanal_comidas WHERE dieta_id=$dieta_id");
        $conn->query("DELETE FROM dieta_semanal_combinaciones WHERE dieta_id=$dieta_id");
        $conn->query("DELETE FROM dietas WHERE id=$dieta_id AND cliente_id=$cliente_id");
        // Si la dieta eliminada era la activa, reseteamos a modo automático (NULL)
        if ($dieta_activa_id === $dieta_id) {
            $stmt = $conn->prepare("UPDATE clientes SET dieta_activa_id=NULL WHERE id=?");
            $stmt->bind_param('i', $cliente_id);
            $stmt->execute();
            $stmt->close();
            $dieta_activa_id = null;
        }
        $msg = '<div class="alert alert-success">Dieta eliminada correctamente.</div>';

    } elseif ($accion === 'activar') {
        // Fijar manualmente qué dieta ve el paciente en la app
        $dieta_id = (int)$_POST['dieta_id'];
        $stmt = $conn->prepare("UPDATE clientes SET dieta_activa_id=? WHERE id=?");
        $stmt->bind_param('ii', $dieta_id, $cliente_id);
        $stmt->execute();
        $stmt->close();
        $dieta_activa_id = $dieta_id;
        $msg = '<div class="alert alert-success">Dieta fijada en la app correctamente.</div>';

    } elseif ($accion === 'desactivar') {
        // Volver al modo automático: la app mostrará la dieta más reciente
        $stmt = $conn->prepare("UPDATE clientes SET dieta_activa_id=NULL WHERE id=?");
        $stmt->bind_param('i', $cliente_id);
        $stmt->execute();
        $stmt->close();
        $dieta_activa_id = null;
        $msg = '<div class="alert alert-success">La app vuelve a mostrar la dieta más reciente.</div>';
    }
}

/* Cargamos las dietas en un array PHP (no dejamos el mysqli_result abierto)
   porque necesitamos hacer dos pasadas: una para detectar qué dieta lleva el badge
   y otra para renderizar la tabla. */
$dietas_result = $conn->query("SELECT * FROM dietas WHERE cliente_id=$cliente_id ORDER BY fecha DESC");
$dietas = [];
while ($row = $dietas_result->fetch_assoc()) {
    $dietas[] = $row;
}

// Buscamos el nombre de la dieta fijada para mostrarlo en el banner superior
$nombre_activa_fijada = null;
if ($dieta_activa_id !== null) {
    foreach ($dietas as $d) {
        if ((int)$d['id'] === $dieta_activa_id) {
            $nombre_activa_fijada = $d['nombre'];
            break;
        }
    }
    // Si la dieta fijada fue eliminada y ya no existe en la lista, volvemos a auto
    if ($nombre_activa_fijada === null) {
        $dieta_activa_id = null;
    }
}

/* Calculamos qué dieta lleva el badge "✓ En app":
   - Si hay una dieta fijada, el badge va en esa.
   - Si está en modo auto (NULL), el badge va en la primera fila (la más reciente). */
$badge_id = null;
if ($dieta_activa_id !== null) {
    $badge_id = $dieta_activa_id;
} elseif (!empty($dietas)) {
    $badge_id = (int)$dietas[0]['id']; // más reciente (primera fila)
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dietas del cliente – ProyectoC</title>
<link rel="stylesheet" href="css/style.css?v=1777485477203">
<script src="js/theme-init.js"></script>
<style>
.badge-app {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: #e8f5e9;
    color: #2e7d32;
    white-space: nowrap;
}
.banner-app {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
    flex-wrap: wrap;
}
.banner-app.auto {
    background: #f0f4ff;
    color: #3d5a8a;
    border: 1px solid #c7d7f9;
}
.banner-app.fijada {
    background: #e8f5e9;
    color: #1b5e20;
    border: 1px solid #a5d6a7;
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
<?= $msg ?? '' ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px">
        <h2 style="margin:0">Dietas de: <?= htmlspecialchars($cliente['nombre'].' '.$cliente['apellidos']) ?></h2>
        <a href="dieta_nueva.php?cliente=<?= $cliente_id ?>" class="btn btn-primary btn-sm">+ Nueva dieta</a>
    </div>

    <?php if ($dieta_activa_id === null): ?>
    <div class="banner-app auto">
        <span>📱 La app muestra la dieta más reciente automáticamente.</span>
    </div>
    <?php else: ?>
    <div class="banner-app fijada">
        <span>📌 La app muestra una dieta fijada manualmente: <strong><?= htmlspecialchars($nombre_activa_fijada) ?></strong></span>
        <form method="POST" style="margin:0">
            <input type="hidden" name="accion" value="desactivar">
            <button type="submit" class="btn btn-sm" style="background:#fff;border:1px solid #a5d6a7;color:#1b5e20;padding:4px 10px">Usar más reciente</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="table-responsive">
    <table>
        <thead><tr><th>Nombre</th><th>Tipo</th><th>Fecha</th><th>App</th><th>Observaciones</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php if (!empty($dietas)): foreach ($dietas as $d):
            $es_semanal = ($d['tipo'] ?? 'general') === 'semanal';
            $ver_url = $es_semanal ? 'dieta_semanal_ver.php' : 'dieta_ver.php';
            $pdf_url = $es_semanal ? 'dieta_semanal_imprimir.php' : 'dieta_imprimir.php';
            $es_badge = ($badge_id !== null && (int)$d['id'] === $badge_id);
        ?>
        <tr>
            <td><?= htmlspecialchars($d['nombre']) ?></td>
            <td>
                <?php if ($es_semanal): ?>
                <span class="badge" style="background:#e8f0fe;color:#1a73e8">📅 Semanal</span>
                <?php else: ?>
                <span class="badge" style="background:#e8f5e9;color:#2e7d32">📋 General</span>
                <?php endif; ?>
            </td>
            <td><?= $d['fecha'] ?></td>
            <td>
                <?php if ($es_badge): ?>
                <span class="badge-app">✓ En app<?= $dieta_activa_id === null ? ' (más reciente)' : '' ?></span>
                <?php else: ?>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="accion" value="activar">
                    <input type="hidden" name="dieta_id" value="<?= $d['id'] ?>">
                    <button type="submit" class="btn btn-sm" style="background:#eef2ff;border:1px solid #c7d7f9;color:#0071e3;padding:4px 10px;font-size:12px">Activar en app</button>
                </form>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars(substr($d['observaciones'] ?? '', 0, 60)) ?></td>
            <td>
                <a href="<?= $ver_url ?>?id=<?= $d['id'] ?>" class="btn btn-info btn-sm">Editar</a>
                <a href="<?= $pdf_url ?>?id=<?= $d['id'] ?>" class="btn btn-primary btn-sm" target="_blank">Ver / PDF</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta dieta? Se borrarán también los alimentos asociados.')">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                </form>
            </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" style="text-align:center;color:#999">Sin dietas. <a href="dieta_nueva.php?cliente=<?= $cliente_id ?>">Crear la primera</a></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
<script src="js/theme.js"></script>
</body>
</html>
