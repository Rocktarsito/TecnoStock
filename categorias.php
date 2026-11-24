
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

$mensaje = '';
$categorias = [];
$editar = null;

try {
    $conexion = obtenerConexion();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) ||
            !hash_equals($_SESSION['csrf_token'], $token)) {
            $mensaje = 'Solicitud no válida.';

        } elseif ($accion === 'crear' || $accion === 'editar') {
            $nombre = $_POST['nombre'] ?? '';
            $descripcion = $_POST['descripcion'] ?? '';

            if (!is_string($nombre) || !is_string($descripcion)) {
                $mensaje = 'Datos no válidos.';
            } else {
                $nombre = trim($nombre);
                $descripcion = trim($descripcion);

                if ($nombre === '' || mb_strlen($nombre) > 100 ||
                    mb_strlen($descripcion) > 255) {
                    $mensaje = 'Revisa los datos ingresados.';
                } else {
                    if ($accion === 'crear') {
                        $sql = "INSERT INTO categorias (nombre, descripcion)
                                VALUES (:nombre, :descripcion)";

                        $consulta = $conexion->prepare($sql);
                        $consulta->execute([
                            ':nombre' => $nombre,
                            ':descripcion' => $descripcion
                        ]);

                        header('Location: categorias.php?ok=creada');
                        exit;
                    }

                    $id = filter_var(
                        $_POST['id_categoria'] ?? null,
                        FILTER_VALIDATE_INT,
                        ['options' => ['min_range' => 1]]
                    );

                    if (!$id) {
                        $mensaje = 'Categoría no válida.';
                    } else {
                        $sql = "UPDATE categorias
                                SET nombre = :nombre,
                                    descripcion = :descripcion
                                WHERE id_categoria = :id";

                        $consulta = $conexion->prepare($sql);
                        $consulta->execute([
                            ':nombre' => $nombre,
                            ':descripcion' => $descripcion,
                            ':id' => $id
                        ]);

                        header('Location: categorias.php?ok=editada');
                        exit;
                    }
                }
            }

        } elseif ($accion === 'estado') {
            $id = filter_var(
                $_POST['id_categoria'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                $mensaje = 'Categoría no válida.';
            } else {
                $sql = "UPDATE categorias
                        SET estado = CASE
                            WHEN estado = 1 THEN 0
                            ELSE 1
                        END
                        WHERE id_categoria = :id";

                $consulta = $conexion->prepare($sql);
                $consulta->execute([':id' => $id]);

                if ($consulta->rowCount() > 0) {
                    header('Location: categorias.php?ok=estado');
                    exit;
                }

                $mensaje = 'La categoría no existe.';
            }
        } else {
            $mensaje = 'Acción no válida.';
        }
    }

} catch (PDOException $e) {
    $mensaje = $e->getCode() === '23000'
        ? 'Ya existe una categoría con ese nombre.'
        : 'Ocurrió un error al procesar la solicitud.';
}

try {
    $conexion = obtenerConexion();

    $consulta = $conexion->query(
        "SELECT * FROM categorias ORDER BY nombre"
    );

    $categorias = $consulta->fetchAll();

    if (isset($_GET['editar'])) {
        $idEditar = filter_var(
            $_GET['editar'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($idEditar) {
            $consulta = $conexion->prepare(
                "SELECT * FROM categorias WHERE id_categoria = :id"
            );

            $consulta->execute([':id' => $idEditar]);
            $editar = $consulta->fetch();
        }

        if (!$editar) {
            $mensaje = 'Categoría no encontrada.';
        }
    }

} catch (PDOException $e) {
    $mensaje = 'No se pudieron cargar las categorías.';
}

$mensajesOk = [
    'creada' => 'Categoría registrada correctamente.',
    'editada' => 'Categoría actualizada correctamente.',
    'estado' => 'Estado de la categoría actualizado.'
];

$ok = $_GET['ok'] ?? '';
$mensajeExito = is_string($ok) ? ($mensajesOk[$ok] ?? '') : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorías - TecnoStock</title>
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

<main class="container mt-5">

    <h2>Administración de categorías</h2>
    <p class="text-muted">
        Registra y administra las categorías de productos.
    </p>

    <?php if ($mensajeExito !== ''): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">

            <h5>
                <?= $editar ? 'Editar categoría' : 'Nueva categoría' ?>
            </h5>

            <form method="POST" action="categorias.php">

                <input type="hidden" name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <input type="hidden" name="accion"
                    value="<?= $editar ? 'editar' : 'crear' ?>">

                <?php if ($editar): ?>
                    <input type="hidden" name="id_categoria"
                        value="<?= (int)$editar['id_categoria'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Nombre</label>

                    <input type="text" name="nombre"
                        class="form-control"
                        maxlength="100"
                        value="<?= htmlspecialchars($editar['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>

                    <textarea name="descripcion"
                        class="form-control"
                        maxlength="255"><?= htmlspecialchars($editar['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button class="btn btn-primary" type="submit">
                    <?= $editar ? 'Guardar cambios' : 'Guardar categoría' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="categorias.php" class="btn btn-secondary">
                        Cancelar
                    </a>
                <?php endif; ?>

            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">

            <h5>Categorías registradas</h5>

            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($categorias as $categoria): ?>
                            <tr>
                                <td>
                                    <?= (int)$categoria['id_categoria'] ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($categoria['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td>
                                    <?php if ($categoria['estado'] == 1): ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactiva</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <div class="d-flex gap-2">

                                        <a href="categorias.php?editar=<?= (int)$categoria['id_categoria'] ?>"
                                           class="btn btn-sm btn-primary">
                                            Editar
                                        </a>

                                        <form method="POST"
                                              action="categorias.php"
                                              onsubmit="return confirm('¿Cambiar el estado de esta categoría?')">

                                            <input type="hidden"
                                                name="csrf_token"
                                                value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                                            <input type="hidden"
                                                name="accion"
                                                value="estado">

                                            <input type="hidden"
                                                name="id_categoria"
                                                value="<?= (int)$categoria['id_categoria'] ?>">

                                            <button type="submit"
                                                class="btn btn-sm <?= $categoria['estado'] == 1 ? 'btn-warning' : 'btn-success' ?>">

                                                <?= $categoria['estado'] == 1 ? 'Desactivar' : 'Reactivar' ?>

                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($categorias)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">
                                    No hay categorías registradas.
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
