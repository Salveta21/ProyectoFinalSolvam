<?php
require_once 'config/auth.php';
require_once 'config/database.php'; $conn = getConnection();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ProyectoC – Clínica Dietas</title>
<link rel="stylesheet" href="css/style.css?v=1777485477266">
<script src="js/theme-init.js"></script>
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

    <div class="stats-grid">
        <?php
        // Tres consultas simples para mostrar los contadores del dashboard
        // Los ingredientes solo cuentan los activos (activo=1), los desactivados no se muestran
        $clientes = $conn->query("SELECT COUNT(*) as n FROM clientes")->fetch_assoc()['n'] ?? 0;
        $ingredientes = $conn->query("SELECT COUNT(*) as n FROM ingredientes WHERE activo=1")->fetch_assoc()['n'] ?? 0;
        $dietas = $conn->query("SELECT COUNT(*) as n FROM dietas")->fetch_assoc()['n'] ?? 0;
        ?>
        <div class="stat-card">
            <div class="stat-icon">👤</div>
            <div class="stat-number"><?= $clientes ?></div>
            <div class="stat-label">Clientes</div>
            <a href="clientes.php" class="btn btn-primary btn-sm">Ver clientes</a>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🥦</div>
            <div class="stat-number"><?= $ingredientes ?></div>
            <div class="stat-label">Ingredientes</div>
            <a href="ingredientes.php" class="btn btn-primary btn-sm">Ver ingredientes</a>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📋</div>
            <div class="stat-number"><?= $dietas ?></div>
            <div class="stat-label">Dietas creadas</div>
            <a href="clientes.php" class="btn btn-info btn-sm">Crear dieta</a>
        </div>
    </div>

    <div class="card">
        <h2>Últimas dietas</h2>
        <?php
        /* Sacamos las 10 últimas dietas con un JOIN para poder mostrar
           el nombre del cliente junto a cada dieta. Ordenamos por fecha de creación. */
        $res = $conn->query("SELECT d.id, d.fecha, d.nombre, d.tipo, c.nombre as cliente, c.apellidos
                             FROM dietas d JOIN clientes c ON c.id=d.cliente_id
                             ORDER BY d.fecha_creacion DESC LIMIT 10");
        ?>
        <div class="table-responsive">
        <table>
            <thead><tr><th>#</th><th>Cliente</th><th>Dieta</th><th>Fecha</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php if ($res && $res->num_rows > 0): while($r=$res->fetch_assoc()):
                // Dependiendo del tipo de dieta, los botones apuntan a páginas distintas
                $es_semanal = ($r['tipo'] ?? 'general') === 'semanal';
                $ver_url = $es_semanal ? 'dieta_semanal_ver.php' : 'dieta_ver.php';
                $pdf_url = $es_semanal ? 'dieta_semanal_imprimir.php' : 'dieta_imprimir.php';
            ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= htmlspecialchars($r['cliente'].' '.$r['apellidos']) ?></td>
                <td><?= htmlspecialchars($r['nombre']) ?> <?= $es_semanal ? '<span style="font-size:.75rem;color:#1a73e8">📅</span>' : '' ?></td>
                <td><?= $r['fecha'] ?></td>
                <td>
                    <a href="<?= $ver_url ?>?id=<?= $r['id'] ?>" class="btn btn-info btn-sm">Ver</a>
                    <a href="<?= $pdf_url ?>?id=<?= $r['id'] ?>" class="btn btn-primary btn-sm" target="_blank">Imprimir</a>
                </td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="5" style="text-align:center;color:#999">Sin dietas todavía</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script src="js/theme.js"></script>
</body>
</html>
