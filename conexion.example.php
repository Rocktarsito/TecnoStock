
<?php

function obtenerConexion(): PDO
{
    $host = '127.0.0.1';
    $puerto = '3306';
    $baseDatos = 'tecnostock';
    $usuario = 'USUARIO_MYSQL';
    $clave = 'CONTRASENA_MYSQL';

    $dsn = "mysql:host=$host;port=$puerto;dbname=$baseDatos;charset=utf8mb4";

    return new PDO($dsn, $usuario, $clave, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}
