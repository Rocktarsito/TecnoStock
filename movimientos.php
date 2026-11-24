
<?php
session_start();

if (empty($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

require_once dirname(__DIR__, 2) . '/tecnostock_private/conexion.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escapar($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

$mensaje = '';
$productos = [];
$movimientos = [];

$datos = [
    'id_producto' => '',
    'tipo' => 'entrada',
    'cantidad' => '',
    'observacion' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $validos = true;

    foreach ($datos as $campo => $valor) {
        $valor = $_POST[$campo] ?? '';

        if (!is_string($valor)) {
            $validos = false;
            break;
        }

        $datos[$campo] = trim($valor);
    }

    if (!is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        $mensaje = 'Solicitud no válida.';

    } elseif (!$validos) {
        $mensaje = 'Datos no válidos.';

    } else {
        $idProducto = filter_var(
            $datos['id_producto'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $cantidad = filter_var(
            $datos['cantidad'],
            FILTER_VALIDATE_INT,
            ['options' => [
                'min_range' => 1,
                'max_range' => 2147483647
            ]]
        );

        $tipo = $datos['tipo'];
        $observacion = $datos['observacion'];

        if (
            !$idProducto ||
            $cantidad === false ||
            !in_array($tipo, ['entrada', 'salida'], true) ||
            mb_strlen($observacion) > 255
        ) {
            $mensaje = 'Revisa los datos del movimiento.';
        } else {
            try {
                $conexion = obtenerConexion();
                $conexion->beginTransaction();

                $sql = "SELECT id_producto, nombre, stock_actual, estado
                        FROM productos
                        WHERE id_producto = ?
                        FOR UPDATE";

                $consulta = $conexion->prepare($sql);
                $consulta->execute([$idProducto]);
                $producto = $consulta->fetch();

                if (!$producto) {
                    throw new RuntimeException(
                        'El producto no existe.'
                    );
                }

                if ((int)$producto['estado'] !== 1) {
                    throw new RuntimeException(
                        'El producto está inactivo.'
                    );
                }

                $stockActual = (int)$producto['stock_actual'];

                if ($tipo === 'entrada') {
                    if ($cantidad > 2147483647 - $stockActual) {
                        throw new RuntimeException(
                            'La cantidad supera el stock permitido.'
                        );
                    }

                    $nuevoStock = $stockActual + $cantidad;
                } else {
                    if ($cantidad > $stockActual) {
                        throw new RuntimeException(
                            'Stock insuficiente para realizar la salida.'
                        );
                    }

                    $nuevoStock = $stockActual - $cantidad;
                }

                $sql = "UPDATE productos
                        SET stock_actual = ?
                        WHERE id_producto = ?";

                $consulta = $conexion->prepare($sql);
                $consulta->execute([
                    $nuevoStock,
                    $idProducto
                ]);

                $sql = "INSERT INTO movimientos
                        (tipo, cantidad, observacion,
                         id_producto, id_usuario)
                        VALUES (?, ?, ?, ?, ?)";

                $consulta = $conexion->prepare($sql);
                $consulta->execute([
                    $tipo,
                    $cantidad,
                    $observacion === '' ? null : $observacion,
                    $idProducto,
                    $_SESSION['id_usuario']
                ]);

                $conexion->commit();

                header('Location: movimientos.php?ok=' . $tipo);
                exit;

            } catch (RuntimeException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                $mensaje = $e->getMessage();

            } catch (PDOException $e) {
                if (isset($conexion) && $conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                $mensaje = 'No se pudo registrar el movimiento.';
            }
        }
    }
}

try {
    $conexion = obtenerConexion();

    $productos = $conexion->query(
        "SELECT id_producto, codigo, nombre, stock_actual
         FROM productos
         WHERE estado = 1
         ORDER BY nombre"
    )->fetchAll();

    $movimientos = $conexion->query(
        "SELECT m.id_movimiento, m.fecha_hora,
                m.tipo, m.cantidad, m.observacion,
                p.codigo, p.nombre AS producto,
                u.nombre AS usuario
         FROM movimientos m
         INNER JOIN productos p
             ON m.id_producto = p.id_producto
         INNER JOIN usuarios u
             ON m.id_usuario = u.id_usuario
         ORDER BY m.id_movimiento DESC
         LIMIT 50"
    )->fetchAll();

} catch (PDOException $e) {
    $mensaje = 'No se pudieron cargar los datos.';
}

$ok = $_GET['ok'] ?? '';
$mensajesOk = [
    'entrada' => 'Entrada registrada correctamente.',
    'salida' => 'Salida registrada correctamente.'
];

$mensajeExito = is_string($ok) ? ($mensajesOk[$ok] ?? '') : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - TecnoStock</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand fw-bold">TecnoStock</span>
        <a href="panel.php" class="btn btn-outline-light">
            Volver al panel
        </a>
    </div>
</nav>

<main class="container mt-5 mb-5">

    <h2>Movimientos de inventario</h2>
    <p class="text-muted">
        Registra entradas y salidas de productos.
    </p>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alert alert-success">
            <?= escapar($mensajeExito) ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-danger">
            <?= escapar($mensaje) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <h5>Nuevo movimiento</h5>

            <form method="POST" action="movimientos.php">

                <input type="hidden" name="csrf_token"
                       value="<?= escapar($_SESSION['csrf_token']) ?>">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Producto</label>

                        <select name="id_producto"
                                class="form-select" required>
                            <option value="">Seleccionar producto</option>

                            <?php foreach ($productos as $producto): ?>
                                <option value="<?= (int)$producto['id_producto'] ?>"
                                    <?= (string)$datos['id_producto'] ===
                                        (string)$producto['id_producto']
                                        ? 'selected' : '' ?>>
                                    <?= escapar($producto['codigo']) ?> -
                                    <?= escapar($producto['nombre']) ?>
                                    (Stock: <?= (int)$producto['stock_actual'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipo de movimiento</label>

                        <select name="tipo" class="form-select" required>
                            <option value="entrada"
                                <?= $datos['tipo'] === 'entrada' ? 'selected' : '' ?>>
                                Entrada
                            </option>

                            <option value="salida"
                                <?= $datos['tipo'] === 'salida' ? 'selected' : '' ?>>
                                Salida
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Cantidad</label>

                        <input type="number" name="cantidad"
                               class="form-control"
                               min="1" max="2147483647" step="1"
                               value="<?= escapar($datos['cantidad']) ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Observación</label>

                        <input type="text" name="observacion"
                               class="form-control" maxlength="255"
                               placeholder="Motivo del movimiento"
                               value="<?= escapar($datos['observacion']) ?>">
                    </div>

                </div>

                <button type="submit" class="btn btn-primary"
                    <?= empty($productos) ? 'disabled' : '' ?>>
                    Registrar movimiento
                </button>
            </form>

        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <h5>Historial de movimientos</h5>
            <p class="text-muted small">
                Últimos 50 movimientos registrados.
            </p>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha y hora</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Usuario</th>
                            <th>Observación</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($movimientos as $movimiento): ?>
                            <tr>
                                <td><?= (int)$movimiento['id_movimiento'] ?></td>

                                <td>
                                    <?= escapar($movimiento['fecha_hora']) ?>
                                </td>

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

                                <td><?= escapar($movimiento['usuario']) ?></td>

                                <td>
                                    <?= escapar($movimiento['observacion'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($movimientos)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    No hay movimientos registrados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</main>

<script>
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alerta => {
            alerta.remove();
        });

        const url = new URL(window.location.href);
        url.searchParams.delete('ok');
        history.replaceState(null, '', url);
    }, 4000);
</script>

</body>
</html>
