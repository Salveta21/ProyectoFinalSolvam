<?php
require_once 'config/auth.php';
require_once 'config/database.php';
$conn = getConnection();

// Recogemos el id del cliente que viene por GET; si no existe redirigimos
$cliente_id = (int)($_GET['cliente'] ?? 0);
if (!$cliente_id) { header("Location: clientes.php"); exit; }

$cliente = $conn->query("SELECT * FROM clientes WHERE id=$cliente_id")->fetch_assoc();
if (!$cliente) { header("Location: clientes.php"); exit; }

// Texto de condimentos por defecto que se asigna a todas las dietas nuevas
$condimentos_default = 'Limón, Vinagre de manzana/con hierbas/de vino, Mostaza en grano sin azúcar (Maille Dijon), sal, sal rosa de Himalaya, pimienta, hierbas aromáticas, estevia o eritritol. Caldo vegetal.';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'crear_dieta') {
    // Validamos el tipo de dieta para que solo pueda ser 'general' o 'semanal'
    $tipo = in_array($_POST['tipo'] ?? '', ['general','semanal']) ? $_POST['tipo'] : 'general';

    /* Usamos una transacción para que si algo falla a mitad (por ejemplo al insertar
       los ingredientes), la dieta entera se cancela y no queda a medias en la BD */
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO dietas (cliente_id, fecha, nombre, tipo, nota_huevos, condimentos, observaciones) VALUES (?,?,?,?,?,?,?)");
        $nota_h = '2 por comida / 6 a la semana';
        $stmt->bind_param("issssss", $cliente_id, $_POST['fecha'], $_POST['nombre'], $tipo, $nota_h, $condimentos_default, $_POST['observaciones']);
        $stmt->execute();
        $dieta_id = $conn->insert_id;

        /* Pre-selección automática de ingredientes solo para dieta general.
           Insertamos todos los ingredientes activos que no son "no_permitido_base".
           INSERT IGNORE evita duplicados si se ejecuta varias veces. */
        if ($tipo === 'general') {
            $ings = $conn->query("SELECT id FROM ingredientes WHERE activo=1 AND no_permitido_base=0");
            $stmt2 = $conn->prepare("INSERT IGNORE INTO dieta_ingredientes (dieta_id, ingrediente_id) VALUES (?,?)");
            while ($ing = $ings->fetch_assoc()) {
                $stmt2->bind_param("ii", $dieta_id, $ing['id']);
                $stmt2->execute();
            }
        }

        // Los ingredientes marcados como no_permitido_base se añaden automáticamente a "no comer"
        $base_no = $conn->query("SELECT id FROM ingredientes WHERE activo=1 AND no_permitido_base=1");
        $stmt3 = $conn->prepare("INSERT IGNORE INTO dieta_no_permitidos (dieta_id, ingrediente_id) VALUES (?,?)");
        while ($b = $base_no->fetch_assoc()) {
            $stmt3->bind_param("ii", $dieta_id, $b['id']);
            $stmt3->execute();
        }

        $conn->commit();
        // Redirigimos a la pantalla de edición correspondiente según el tipo de dieta
        if ($tipo === 'semanal') {
            header("Location: dieta_semanal_ver.php?id=$dieta_id");
        } else {
            header("Location: dieta_ver.php?id=$dieta_id&nuevo=1");
        }
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = htmlspecialchars($e->getMessage());
    }
}

$nombre_cliente = htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']);
$iniciales = strtoupper(mb_substr($cliente['nombre'], 0, 1) . mb_substr($cliente['apellidos'], 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nuevo protocolo – <?= $nombre_cliente ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="js/theme-init.js"></script>
<style>
  :root {
    --text-muted: #6e6e73;
    --text-color: #1d1d1f;
    --border-light: #f0f0f2;
  }
  [data-theme="dark"] {
    --text-muted: #98989d;
    --text-color: #f5f5f7;
    --border-light: #2c2c2e;
  }

  * { -webkit-font-smoothing: antialiased; }

  body {
    background: #f5f5f7;
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ── Navbar ── */
  .apple-nav {
    background: rgba(255,255,255,0.82);
    backdrop-filter: saturate(180%) blur(20px);
    -webkit-backdrop-filter: saturate(180%) blur(20px);
    border-bottom: 1px solid rgba(0,0,0,.08);
    position: sticky;
    top: 0;
    z-index: 100;
    padding: 0 32px;
    height: 52px;
    display: flex;
    align-items: center;
    gap: 32px;
  }
  .apple-nav .brand {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1d1d1f;
    text-decoration: none;
    letter-spacing: -.3px;
  }
  .apple-nav a {
    font-size: .88rem;
    color: #444;
    text-decoration: none;
    transition: color .15s;
  }
  .apple-nav a:hover { color: #000; }
  .apple-nav .nav-links { display: flex; gap: 24px; margin-left: auto; }

  /* ── Main layout ── */
  .page-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 32px;
  }

  /* ── Card ── */
  .form-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,.04), 0 20px 40px rgba(0,0,0,.08);
    width: 100%;
    max-width: 960px;
    overflow: hidden;
    display: grid;
    grid-template-columns: 380px 1fr;
  }

  @media (max-width: 700px) {
    .form-card { grid-template-columns: 1fr; }
    .page-wrap { padding: 24px 16px; }
  }

  /* ── Card header ── */
  .card-hero {
    background: linear-gradient(155deg, #1c1c1e 0%, #2c2c2e 100%);
    padding: 48px 40px 48px;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .card-hero .label-new {
    display: inline-block;
    background: rgba(255,255,255,.12);
    color: rgba(255,255,255,.75);
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    border-radius: 20px;
    padding: 3px 12px;
    margin-bottom: 14px;
  }
  .card-hero h1 {
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    margin: 0 0 4px;
    letter-spacing: -.4px;
    line-height: 1.2;
  }
  .card-hero .subtitle {
    font-size: .9rem;
    color: rgba(255,255,255,.55);
  }

  /* Avatar iniciales */
  .patient-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 20px;
    background: rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 10px 14px;
  }
  .avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #30d158, #25a244);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .85rem;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
  }
  .patient-name {
    font-size: .95rem;
    font-weight: 600;
    color: #fff;
  }
  .patient-sub {
    font-size: .78rem;
    color: rgba(255,255,255,.5);
  }

  /* ── Form body ── */
  .card-body-inner {
    padding: 48px 44px 48px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .form-label {
    font-size: .8rem;
    font-weight: 600;
    color: #6e6e73;
    letter-spacing: .3px;
    text-transform: uppercase;
    margin-bottom: 6px;
  }

  .form-control, .form-select {
    border: 1.5px solid #e0e0e5;
    border-radius: 10px;
    padding: 12px 14px;
    font-size: .95rem;
    font-family: inherit;
    color: #1d1d1f;
    background: #fafafa;
    transition: border-color .2s, box-shadow .2s, background .2s;
  }
  .form-control:focus, .form-select:focus {
    border-color: #0071e3;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(0,113,227,.12);
    outline: none;
  }
  .form-control::placeholder { color: #b0b0b8; }

  textarea.form-control { resize: none; }

  /* ── Divider ── */
  .form-divider {
    border: none;
    border-top: 1px solid #f0f0f2;
    margin: 28px 0;
  }

  /* ── Buttons ── */
  .btn-apple-primary {
    background: #0071e3;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 13px 0;
    font-size: .97rem;
    font-weight: 600;
    font-family: inherit;
    width: 100%;
    letter-spacing: -.1px;
    transition: background .2s, transform .1s;
    cursor: pointer;
  }
  .btn-apple-primary:hover { background: #0077ed; }
  .btn-apple-primary:active { transform: scale(.98); }

  .btn-apple-ghost {
    background: transparent;
    color: #0071e3;
    border: 1.5px solid #d0d0d8;
    border-radius: 10px;
    padding: 12px 0;
    font-size: .9rem;
    font-weight: 500;
    font-family: inherit;
    width: 100%;
    text-align: center;
    display: block;
    text-decoration: none;
    transition: background .2s, border-color .2s;
  }
  .btn-apple-ghost:hover {
    background: #f5f5f7;
    border-color: #b0b0b8;
    color: #0071e3;
  }

  /* ── Error ── */
  .apple-error {
    background: #fff2f2;
    border: 1px solid #ffd0d0;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: .88rem;
    color: #c0392b;
    margin-bottom: 24px;
  }

  /* ── Tipo selector ── */
  .tipo-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; }
  .tipo-card { cursor: pointer; }
  .tipo-card input[type=radio] { display: none; }
  .tipo-card-body { border: 2px solid #e0e0e5; border-radius: 12px; padding: 18px 14px; text-align: center; transition: all .2s; background: #fafafa; }
  .tipo-card input:checked + .tipo-card-body { border-color: #0071e3; background: #f0f7ff; }
  .tipo-card-body:hover { border-color: #b0c8f0; }
  .tipo-icon { font-size: 2rem; display: block; margin-bottom: 6px; }
  .tipo-name { display: block; font-weight: 700; font-size: .95rem; color: #1d1d1f; }
  .tipo-desc { display: block; font-size: .75rem; color: #6e6e73; margin-top: 4px; }

  /* ── Info pill ── */
  .info-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0f7ff;
    border: 1px solid #d0e8ff;
    border-radius: 8px;
    padding: 8px 12px;
    font-size: .8rem;
    color: #0071e3;
    margin-bottom: 28px;
  }
  .info-pill svg { flex-shrink: 0; }

  /* ── Dark mode overrides ── */
  [data-theme="dark"] {
    color-scheme: dark;
  }
  [data-theme="dark"] body {
    background: #000000;
    color: #f5f5f7;
  }
  [data-theme="dark"] .apple-nav {
    background: rgba(28,28,30,0.82);
    border-bottom-color: rgba(255,255,255,.08);
  }
  [data-theme="dark"] .apple-nav .brand {
    color: #f5f5f7;
  }
  [data-theme="dark"] .apple-nav a {
    color: #98989d;
  }
  [data-theme="dark"] .apple-nav a:hover {
    color: #f5f5f7;
  }
  [data-theme="dark"] .form-card {
    background: #1c1c1e;
    box-shadow: 0 4px 6px rgba(0,0,0,.3), 0 20px 40px rgba(0,0,0,.5);
  }
  [data-theme="dark"] .card-body-inner {
    background: #1c1c1e;
  }
  [data-theme="dark"] .form-label {
    color: #98989d;
  }
  [data-theme="dark"] .form-control,
  [data-theme="dark"] .form-select {
    border-color: #38383a;
    background: #2c2c2e;
    color: #f5f5f7;
  }
  [data-theme="dark"] .form-control:focus,
  [data-theme="dark"] .form-select:focus {
    border-color: #0071e3;
    background: #2c2c2e;
    box-shadow: 0 0 0 3px rgba(0,113,227,.25);
  }
  [data-theme="dark"] .form-control::placeholder {
    color: #48484a;
  }
  [data-theme="dark"] .form-divider {
    border-top-color: #38383a;
  }
  [data-theme="dark"] .tipo-card-body {
    border-color: #38383a;
    background: #2c2c2e;
    color: #f5f5f7;
  }
  [data-theme="dark"] .tipo-card input:checked + .tipo-card-body {
    border-color: #0071e3;
    background: #0a2547;
  }
  [data-theme="dark"] .tipo-card-body:hover {
    border-color: #5a7abf;
  }
  [data-theme="dark"] .tipo-name {
    color: #f5f5f7;
  }
  [data-theme="dark"] .tipo-desc {
    color: #98989d;
  }
  [data-theme="dark"] .btn-apple-ghost {
    border-color: #38383a;
    color: #0071e3;
  }
  [data-theme="dark"] .btn-apple-ghost:hover {
    background: #2c2c2e;
    border-color: #48484a;
    color: #0071e3;
  }
  [data-theme="dark"] .apple-error {
    background: #3a1010;
    border-color: #7a2020;
    color: #ff6b6b;
  }
  [data-theme="dark"] .info-pill {
    background: #0a2547;
    border-color: #1a4a80;
    color: #5ac8fa;
  }
  [data-theme="dark"] [style*="background:#fff8e1"] {
    background: #3a2800 !important;
    border-color: #7a5500 !important;
    color: #f0c060 !important;
  }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="apple-nav">
  <a href="index.php" class="brand">🥗 <?= htmlspecialchars(APP_NAME) ?></a>
  <div class="nav-links">
    <a href="index.php">Inicio</a>
    <a href="clientes.php">Clientes</a>
    <a href="ingredientes.php">Ingredientes</a>
    <a href="configuracion.php">Configuración</a>
    <a href="guia.php">Guía</a>
    <a href="logout.php" style="color:var(--text-muted)">Salir</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="form-card">

    <!-- Hero header -->
    <div class="card-hero">
      <div class="label-new">Nuevo protocolo</div>
      <h1>Protocolo alimentario</h1>
      <p class="subtitle">Configura los datos iniciales del plan.</p>
      <div class="patient-row">
        <div class="avatar"><?= $iniciales ?></div>
        <div>
          <div class="patient-name"><?= $nombre_cliente ?></div>
          <div class="patient-sub">Paciente</div>
        </div>
      </div>
    </div>

    <!-- Form body -->
    <div class="card-body-inner">

      <?php if ($error): ?>
      <div class="apple-error">⚠️ <?= $error ?></div>
      <?php endif; ?>

      <div class="info-pill">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Los ingredientes se pre-seleccionarán automáticamente al crear.
      </div>

      <?php if (!empty($cliente['intolerancias'])): ?>
      <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:10px;padding:10px 14px;margin-bottom:20px;font-size:.85rem;color:#5a4000">
        ⚠️ <strong>Intolerancias del paciente:</strong> <?= htmlspecialchars($cliente['intolerancias']) ?>
      </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="accion" value="crear_dieta">

        <!-- Tipo -->
        <div class="tipo-selector" style="margin-bottom:24px">
          <label class="tipo-card">
            <input type="radio" name="tipo" value="general" checked>
            <div class="tipo-card-body">
              <span class="tipo-icon">📋</span>
              <span class="tipo-name">General</span>
              <span class="tipo-desc">Lista única de alimentos permitidos</span>
            </div>
          </label>
          <label class="tipo-card">
            <input type="radio" name="tipo" value="semanal">
            <div class="tipo-card-body">
              <span class="tipo-icon">📅</span>
              <span class="tipo-name">Semanal</span>
              <span class="tipo-desc">Planifica cada comida día a día</span>
            </div>
          </label>
        </div>

        <!-- Nombre -->
        <div class="mb-4">
          <label class="form-label">Nombre del protocolo</label>
          <input
            type="text"
            name="nombre"
            class="form-control"
            value="Protocolo alimentario"
            placeholder="Ej: Protocolo alimentario"
            required
          >
        </div>

        <!-- Fecha -->
        <div class="mb-4">
          <label class="form-label">Fecha</label>
          <input
            type="date"
            name="fecha"
            class="form-control"
            value="<?= date('Y-m-d') ?>"
            required
          >
        </div>

        <!-- Observaciones -->
        <div class="mb-4">
          <label class="form-label">Observaciones internas <span style="font-weight:400;text-transform:none;font-size:.78rem;color:#b0b0b8">(opcional)</span></label>
          <textarea
            name="observaciones"
            class="form-control"
            rows="3"
            placeholder="Notas privadas del profesional…"
          ></textarea>
        </div>

        <hr class="form-divider">

        <!-- Actions -->
        <button type="submit" class="btn-apple-primary mb-3">
          Crear y configurar →
        </button>
        <a href="clientes.php" class="btn-apple-ghost">Cancelar</a>

      </form>
    </div>

  </div>
</div>

<script src="js/theme.js"></script>
</body>
</html>
