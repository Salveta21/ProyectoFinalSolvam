<?php
/* Datos de conexión a la base de datos MariaDB.
   El host es 'db' porque estamos dentro de Docker y ese es el nombre del contenedor de la BD. */
define('DB_HOST', 'db');
define('DB_PORT', 3306);
define('DB_USER', 'root');
define('DB_PASS', 'root21.');
define('DB_NAME', 'clinica_dietas');

// Cargamos el nombre de la empresa desde settings.json si existe, para que APP_NAME esté disponible en todas las páginas
$settings_file = __DIR__ . '/settings.json';
$app_name = 'Proyecto Dieta';
if (file_exists($settings_file)) {
    $saved = json_decode(file_get_contents($settings_file), true);
    if (!empty($saved['general']['empresa'])) {
        $app_name = $saved['general']['empresa'];
    }
}
define('APP_NAME', $app_name);

/* Función centralizada de conexión. Todas las páginas la llaman con getConnection()
   en lugar de crear la conexión a mano cada vez. Así si algún día cambia algo,
   solo hay que tocarlo aquí. */
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    // Importante: forzar UTF-8 para que los acentos y emojis no se corrompan
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
