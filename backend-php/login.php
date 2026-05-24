<?php
/**
 * login.php
 * Página de autenticación del backend. Standalone: sin nav ni header.
 * Si ya hay sesión activa redirige directamente al dashboard.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

// Si ya está autenticado no tiene sentido mostrar el login
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        $conn  = getConnection();
        $stmt  = $conn->prepare("SELECT id, nombre, password_hash FROM usuarios WHERE username=? AND activo=1 LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Credenciales válidas: regenerar ID de sesión para prevenir fixation
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $user['id'];
            $_SESSION['usuario_nombre'] = $user['nombre'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Por favor, introduce usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceder – <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="css/style.css?v=1">
<script src="js/theme-init.js"></script>
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-color)">
    <div style="background:var(--card-bg);border:1px solid var(--border-color);border-radius:16px;padding:2.5rem 2rem;width:100%;max-width:360px;box-shadow:0 4px 24px rgba(0,0,0,.10)">

        <!-- Logo / título -->
        <div style="text-align:center;margin-bottom:2rem">
            <div style="font-size:2rem;margin-bottom:.5rem">🥗</div>
            <h1 style="font-size:1.4rem;font-weight:800;color:var(--text-color);margin:0 0 .25rem;letter-spacing:-.5px"><?= htmlspecialchars(APP_NAME) ?></h1>
            <p style="font-size:.85rem;color:var(--text-muted);margin:0">Introduce tus credenciales para acceder</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1.25rem"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div style="margin-bottom:1rem">
                <label for="username" style="display:block;font-size:.85rem;font-weight:600;color:var(--text-color);margin-bottom:.4rem">Usuario</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    autofocus
                    required
                    style="width:100%;box-sizing:border-box"
                >
            </div>

            <div style="margin-bottom:1.5rem">
                <label for="password" style="display:block;font-size:.85rem;font-weight:600;color:var(--text-color);margin-bottom:.4rem">Contraseña</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    style="width:100%;box-sizing:border-box"
                >
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;padding:.65rem;font-size:1rem">
                Acceder
            </button>
        </form>
    </div>
</div>
<script src="js/theme.js"></script>
</body>
</html>
