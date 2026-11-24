
<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

require_once dirname(__DIR__, 3) . '/tecnostock_private/conexion.php';



function leerDato(string $mensaje): string
{
    echo $mensaje;
    return trim((string) fgets(STDIN));
}

$nombre = leerDato('Nombre del administrador: ');
$correo = leerDato('Correo electronico: ');
$clave = leerDato('Contrasena (minimo 12 caracteres): ');

if (
    $nombre === '' ||
    strlen($nombre) > 100 ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    strlen($correo) > 150 ||
    strlen($clave) < 12
) {
    exit("Datos invalidos.\n");
}

try {
    $conexion = obtenerConexion();

    $sql = "INSERT INTO usuarios (nombre, correo, clave, estado)
            VALUES (:nombre, :correo, :clave, 1)";

    $consulta = $conexion->prepare($sql);

    $consulta->execute([
        ':nombre' => $nombre,
        ':correo' => $correo,
        ':clave' => password_hash($clave, PASSWORD_DEFAULT)
    ]);

    echo "Administrador creado correctamente.\n";

} catch (PDOException $e) {
    echo "No se pudo crear el administrador. Verifica la conexion y que el correo no exista.\n";
    exit(1);
}
