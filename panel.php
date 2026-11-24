
<?php
session_start();

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/tecnostock_private/conexion.php';

function escapar($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$nombre = $_SESSION['nombre'] ?? 'Usuario';
$error = '';
$alertas = [];
$movimientos = [];

$totales = [
    'productos' => 0,
    'categorias' => 0,
    'unidades' => 0,
    'bajo_stock' => 0
];

try {
    $conexion = obtenerConexion();

    $consulta = $conexion->query(
        "SELECT COUNT(*) FROM categorias WHERE estado = 1"
    );
    $totales['categorias'] = (int)$consulta->fetchColumn();

    $consulta = $conexion->query(
        "SELECT
            COUNT(*) AS productos,
            COALESCE(SUM(stock_actual), 0) AS unidades,
            COALESCE(SUM(
                CASE WHEN stock_actual < stock_minimo
                THEN 1 ELSE 0 END
            ), 0) AS bajo_stock
         FROM productos
         WHERE estado = 1"
    );

    $resumen = $consulta->fetch();

    $totales['productos'] = (int)$resumen['productos'];
    $totales['unidades'] = (int)$resumen['unidades'];
    $totales['bajo_stock'] = (int)$resumen['bajo_stock'];

    $alertas = $conexion->query(
        "SELECT codigo, nombre, stock_actual, stock_minimo
         FROM productos
         WHERE estado = 1 AND stock_actual < stock_minimo
         ORDER BY stock_actual ASC
         LIMIT 10"
    )->fetchAll();

    $movimientos = $conexion->query(
        "SELECT m.fecha_hora, m.tipo, m.cantidad,
                p.codigo, p.nombre AS producto
         FROM movimientos m
         INNER JOIN productos p
             ON m.id_producto = p.id_producto
         ORDER BY m.id_movimiento DESC
         LIMIT 5"
    )->fetchAll();

} catch (PDOException $e) {
    $error = 'No se pudo cargar el resumen del inventario.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel principal - TecnoStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand fw-bold">TecnoStock</span>
        <a href="logout.php" class="btn btn-outline-light">
            Cerrar sesión
        </a>
    </div>
</nav>

<main class="container mt-5 mb-5">

    <h2>Bienvenido, <?= escapar($nombre) ?></h2>
    <p class="text-muted">Sistema de gestión de inventario</p>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= escapar($error) ?></div>
    <?php else: ?>

    <div class="row g-3 mt-2 mb-4">

        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Productos activos</p>
                    <h2 class="fw-bold text-primary">
                        <?= $totales['productos'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Categorías activas</p>
                    <h2 class="fw-bold text-primary">
                        <?= $totales['categorias'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Unidades disponibles</p>
                    <h2 class="fw-bold text-success">
                        <?= $totales['unidades'] ?>
                    </h2>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">Productos con bajo stock</p>
                    <h2 class="fw-bold text-danger">
                        <?= $totales['bajo_stock'] ?>
                    </h2>
                </div>
            </div>
        </div>

    </div>

    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h4>Administración de inventario</h4>

            <p class="text-muted">
                Selecciona una sección para administrar TecnoStock.
            </p>

            <div class="d-flex flex-wrap gap-2">
                <a href="productos.php" class="btn btn-primary">
                    Administrar productos
                </a>

                <a href="categorias.php" class="btn btn-outline-primary">
                    Administrar categorías
                </a>

                <a href="movimientos.php" class="btn btn-outline-primary">
                    Administrar movimientos
                </a>
            </div>
        </div>
    </div>

    <?php if ($error === ''): ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <h5>Alertas de stock</h5>

            <?php if (empty($alertas)): ?>
                <div class="alert alert-success mb-0">
                    No hay productos activos bajo el stock mínimo.
                </div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Stock actual</th>
                                <th>Stock mínimo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($alertas as $producto): ?>
                                <tr>
                                    <td><?= escapar($producto['codigo']) ?></td>
                                    <td><?= escapar($producto['nombre']) ?></td>
                                    <td><?= (int)$producto['stock_actual'] ?></td>
                                    <td><?= (int)$producto['stock_minimo'] ?></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            Bajo stock
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h5>Últimos movimientos</h5>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                            <tr>
                                <td><?= escapar($movimiento['fecha_hora']) ?></td>
                                <td>
                                    <?= escapar($movimiento['codigo']) ?> -
                                    <?= escapar($movimiento['producto']) ?>
                                </td>
                                <td>
                                    <?php if ($movimiento['tipo'] === 'entrada'): ?>
                                        <span class="badge bg-success">
                                            Entrada
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            Salida
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$movimiento['cantidad'] ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    No hay movimientos registrados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="movimientos.php" class="btn btn-outline-primary mt-2">
                Ver movimientos
            </a>
        </div>
    </div>

    <?php endif; ?>

</main>

</body>
</html>
