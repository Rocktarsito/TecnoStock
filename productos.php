
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
$categorias = [];
$productos = [];
$editar = null;

$datos = [
    'codigo' => '',
    'nombre' => '',
    'descripcion' => '',
    'precio' => '',
    'stock_minimo' => '0',
    'id_categoria' => ''
];

$idEditar = filter_var(
    $_GET['editar'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

try {
    $conexion = obtenerConexion();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($accion) || !is_string($token) ||
            !hash_equals($_SESSION['csrf_token'], $token)) {
            $mensaje = 'Solicitud no válida.';

        } elseif ($accion === 'estado') {
            $id = filter_var(
                $_POST['id_producto'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($id === false || $id === null) {
                $mensaje = 'Producto no válido.';
            } else {
                $sql = "UPDATE productos
                        SET estado = CASE
                            WHEN estado = 1 THEN 0
                            ELSE 1
                        END
                        WHERE id_producto = :id";

                $consulta = $conexion->prepare($sql);
                $consulta->execute([':id' => $id]);

                if ($consulta->rowCount() > 0) {
                    header('Location: productos.php?ok=estado');
                    exit;
                }

                $mensaje = 'El producto no existe.';
            }

        } elseif ($accion === 'crear' || $accion === 'editar') {
            $validos = true;

        foreach ($datos as $campo => $valor) {
            $valor = $_POST[$campo] ?? '';

        if (!is_string($valor)) {
            $validos = false;
        break;
        }

    $datos[$campo] = trim($valor);
}


            if (!$validos) {
                $mensaje = 'Datos no válidos.';
            } else {
                $idCategoria = filter_var(
                    $datos['id_categoria'],
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                $stockMinimo = filter_var(
                    $datos['stock_minimo'],
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0]]
                );

                $precioValido = preg_match(
                    '/^\d{1,10}$/D',
                    $datos['precio']
                );

                $id = null;

                if ($accion === 'editar') {
                    $id = filter_var(
                        $_POST['id_producto'] ?? null,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );
                }

                if (
                    $datos['codigo'] === '' ||
                    mb_strlen($datos['codigo']) > 50 ||
                    $datos['nombre'] === '' ||
                    mb_strlen($datos['nombre']) > 150 ||
                    mb_strlen($datos['descripcion']) > 1000 ||
                    !$precioValido ||
                    $stockMinimo === false ||
                    $idCategoria === false ||
                    $idCategoria === null ||
                    ($accion === 'editar' && !$id)
                ) {
                    $mensaje = 'Revisa los datos del producto.';
                } else {
                    $categoriaAnterior = null;

                    if ($accion === 'editar') {
                        $consulta = $conexion->prepare(
                            "SELECT id_categoria FROM productos
                             WHERE id_producto = ?"
                        );
                        $consulta->execute([$id]);
                        $categoriaAnterior = $consulta->fetchColumn();
                    }

                    if ($accion === 'editar' &&
                        $categoriaAnterior === false) {
                        $mensaje = 'El producto no existe.';
                    } else {
                        $consulta = $conexion->prepare(
                            "SELECT estado FROM categorias
                             WHERE id_categoria = ?"
                        );
                        $consulta->execute([$idCategoria]);
                        $estadoCategoria = $consulta->fetchColumn();

                        if (
                            $estadoCategoria === false ||
                            ((int)$estadoCategoria !== 1 &&
                             (int)$categoriaAnterior !== $idCategoria)
                        ) {
                            $mensaje = 'Selecciona una categoría activa.';
                        } else {
                            $parametros = [
                                ':codigo' => $datos['codigo'],
                                ':nombre' => $datos['nombre'],
                                ':descripcion' => $datos['descripcion'],
                                ':precio' => str_replace(',', '.', $datos['precio']),
                                ':stock_minimo' => $stockMinimo,
                                ':id_categoria' => $idCategoria
                            ];

                            if ($accion === 'crear') {
                                $sql = "INSERT INTO productos
                                        (codigo, nombre, descripcion,
                                         precio, stock_minimo, id_categoria)
                                        VALUES
                                        (:codigo, :nombre, :descripcion,
                                         :precio, :stock_minimo, :id_categoria)";
                            } else {
                                $sql = "UPDATE productos
                                        SET codigo = :codigo,
                                            nombre = :nombre,
                                            descripcion = :descripcion,
                                            precio = :precio,
                                            stock_minimo = :stock_minimo,
                                            id_categoria = :id_categoria
                                        WHERE id_producto = :id";

                                $parametros[':id'] = $id;
                            }

                            $consulta = $conexion->prepare($sql);
                            $consulta->execute($parametros);

                            $ok = $accion === 'crear' ? 'creado' : 'editado';

                            header('Location: productos.php?ok=' . $ok);
                            exit;
                        }
                    }
                }
            }

        } else {
            $mensaje = 'Acción no válida.';
        }
    }

} catch (PDOException $e) {
    $mensaje = $e->getCode() === '23000'
        ? 'Ya existe un producto con ese código.'
        : 'Ocurrió un error al procesar el producto.';
}

$buscar = $_GET['buscar'] ?? '';
$buscar = is_string($buscar) ? trim($buscar) : '';
$buscar = mb_substr($buscar, 0, 100);

try {
    $conexion = obtenerConexion();

    if ($idEditar) {
        $consulta = $conexion->prepare(
            "SELECT * FROM productos WHERE id_producto = ?"
        );
        $consulta->execute([$idEditar]);
        $editar = $consulta->fetch();

        if ($editar) {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                foreach ($datos as $campo => $valor) {
                    $datos[$campo] = (string)$editar[$campo];
                }
            }
        } else {
            $mensaje = 'Producto no encontrado.';
        }
    }

    if ($editar) {
        $consulta = $conexion->prepare(
            "SELECT id_categoria, nombre, estado
             FROM categorias
             WHERE estado = 1 OR id_categoria = ?
             ORDER BY nombre"
        );
        $consulta->execute([$editar['id_categoria']]);
        $categorias = $consulta->fetchAll();
    } else {
        $categorias = $conexion->query(
            "SELECT id_categoria, nombre, estado
             FROM categorias
             WHERE estado = 1
             ORDER BY nombre"
        )->fetchAll();
    }

    $sql = "SELECT p.*, c.nombre AS categoria
            FROM productos p
            INNER JOIN categorias c
            ON p.id_categoria = c.id_categoria";

    $parametros = [];

    if ($buscar !== '') {
        $sql .= " WHERE p.codigo LIKE :codigo
                  OR p.nombre LIKE :nombre
                  OR c.nombre LIKE :categoria";
        $parametros = [
            ':codigo' => "%$buscar%",
            ':nombre' => "%$buscar%",
            ':categoria' => "%$buscar%"
        ];
    }

    $sql .= " ORDER BY p.nombre";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);
    $productos = $consulta->fetchAll();

} catch (PDOException $e) {
    $mensaje = 'No se pudieron cargar los productos.';
}

$mensajesOk = [
    'creado' => 'Producto registrado correctamente.',
    'editado' => 'Producto actualizado correctamente.',
    'estado' => 'Estado del producto actualizado.'
];

$ok = $_GET['ok'] ?? '';
$mensajeExito = is_string($ok) ? ($mensajesOk[$ok] ?? '') : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - TecnoStock</title>
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

    <h2>Administración de productos</h2>
    <p class="text-muted">
        Registra y administra los productos del inventario.
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

            <h5><?= $editar ? 'Editar producto' : 'Nuevo producto' ?></h5>

            <form method="POST"
                  action="productos.php<?= $editar ? '?editar=' . (int)$editar['id_producto'] : '' ?>">

                <input type="hidden" name="csrf_token"
                       value="<?= escapar($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="accion"
                       value="<?= $editar ? 'editar' : 'crear' ?>">

                <?php if ($editar): ?>
                    <input type="hidden" name="id_producto"
                           value="<?= (int)$editar['id_producto'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo"
                               class="form-control" maxlength="50"
                               value="<?= escapar($datos['codigo']) ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre"
                               class="form-control" maxlength="150"
                               value="<?= escapar($datos['nombre']) ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Categoría</label>
                        <select name="id_categoria" class="form-select" required>
                            <option value="">Seleccionar categoría</option>

                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?= (int)$categoria['id_categoria'] ?>"
                                    <?= (string)$datos['id_categoria'] ===
                                        (string)$categoria['id_categoria']
                                        ? 'selected' : '' ?>>
                                    <?= escapar($categoria['nombre']) ?>
                                    <?= $categoria['estado'] == 0 ? ' (Inactiva)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Precio</label>
                        <input type="number" name="precio"
                               class="form-control" min="0" step="1"
                               value="<?= escapar($datos['precio']) ?>"
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Stock mínimo</label>
                        <input type="number" name="stock_minimo"
                               class="form-control" min="0" step="1"
                               value="<?= escapar($datos['stock_minimo']) ?>"
                               required>
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control"
                                  maxlength="1000"><?= escapar($datos['descripcion']) ?></textarea>
                    </div>
                </div>

                <p class="text-muted small">
                    El stock actual se administra únicamente mediante
                    movimientos de inventario.
                </p>

                <button type="submit" class="btn btn-primary">
                    <?= $editar ? 'Guardar cambios' : 'Guardar producto' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="productos.php" class="btn btn-secondary">
                        Cancelar
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5>Productos registrados</h5>

            <form method="GET" class="d-flex gap-2 mb-3">
                <input type="search" name="buscar" class="form-control"
                       placeholder="Buscar por código o nombre"
                       value="<?= escapar($buscar) ?>">

                <button class="btn btn-primary" type="submit">
                    Buscar
                </button>

                <a href="productos.php" class="btn btn-secondary">
                    Limpiar
                </a>
            </form>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Precio</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Estado</th>
                            <th>Alerta</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <td><?= escapar($producto['codigo']) ?></td>
                                <td><?= escapar($producto['nombre']) ?></td>
                                <td><?= escapar($producto['categoria']) ?></td>
                                <td>
                                    $<?= number_format((float)$producto['precio'], 0, ',', '.') ?>
                                </td>
                                <td><?= (int)$producto['stock_actual'] ?></td>
                                <td><?= (int)$producto['stock_minimo'] ?></td>

                                <td>
                                    <?php if ($producto['estado'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($producto['stock_actual'] < $producto['stock_minimo']): ?>
                                        <span class="badge bg-danger">Bajo stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Normal</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex gap-2">

                                        <a href="productos.php?editar=<?= (int)$producto['id_producto'] ?>"
                                           class="btn btn-sm btn-primary">
                                            Editar
                                        </a>

                                        <form method="POST"
                                              action="productos.php"
                                              onsubmit="return confirm('¿Cambiar el estado de este producto?')">

                                            <input type="hidden" name="csrf_token"
                                                   value="<?= escapar($_SESSION['csrf_token']) ?>">

                                            <input type="hidden" name="accion"
                                                   value="estado">

                                            <input type="hidden" name="id_producto"
                                                   value="<?= (int)$producto['id_producto'] ?>">

                                            <button type="submit"
                                                    class="btn btn-sm <?= $producto['estado'] == 1 ? 'btn-warning' : 'btn-success' ?>">
                                                <?= $producto['estado'] == 1 ? 'Desactivar' : 'Reactivar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    No se encontraron productos.
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

<script src="sin_saltos.js"></script>

</body>
</html>
