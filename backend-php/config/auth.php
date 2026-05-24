<?php
/**
 * config/auth.php
 * Middleware de autenticación por sesión PHP.
 * Incluir al inicio de cada página protegida del backend.
 * Redirige a login.php si no hay sesión activa.
 * Si no existe ningún usuario activo en BD crea admin/admin123 por defecto.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/database.php';

$_auth_conn = getConnection();

// Si no hay ningún usuario activo, crear el usuario administrador por defecto
$_auth_result = $_auth_conn->query("SELECT COUNT(*) as n FROM usuarios WHERE activo=1");
$_auth_count  = $_auth_result ? (int)$_auth_result->fetch_assoc()['n'] : 0;

if ($_auth_count === 0) {
    $_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $_stmt = $_auth_conn->prepare("INSERT INTO usuarios (nombre, username, password_hash) VALUES (?, ?, ?)");
    $_nv = 'Administrador';
    $_uv = 'admin';
    $_stmt->bind_param("sss", $_nv, $_uv, $_hash);
    $_stmt->execute();
    $_stmt->close();
}

unset($_auth_count, $_auth_result, $_auth_conn);

// Si no hay sesión iniciada, redirigir a la página de login
if (empty($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
