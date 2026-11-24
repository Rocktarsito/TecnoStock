
<?php

ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
    'path' => '/'
]);

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/tecnostock_private/conexion.php';

$correo = $_POST['correo'] ?? '';
$clave = $_POST['clave'] ?? '';

if (!is_string($correo) || !is_string($clave)) {
    header('Location: login.php?error=credenciales');
    exit;
}

$correo = trim($correo);

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    strlen($correo) > 150 || $clave === '') {
    header('Location: login.php?error=credenciales');
    exit;
}

try {
    $conexion = obtenerConexion();

    $sql = "SELECT id_usuario, nombre, correo, clave, estado
            FROM usuarios
            WHERE correo = :correo
            LIMIT 1";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([':correo' => $correo]);

    $usuario = $consulta->fetch();

    // Verificar credenciales
    if ($usuario &&
        (int)$usuario['estado'] === 1 &&
        password_verify($clave, $usuario['clave'])) {

        session_regenerate_id(true);

        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['correo'] = $usuario['correo'];

        header('Location: panel.php');
        exit;
    }

    header('Location: login.php?error=credenciales');
    exit;

} catch (PDOException $e) {
    error_log('TecnoStock: error de autenticacion');
    header('Location: login.php?error=servidor');
    exit;
}
