
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión - TecnoStock</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container min-vh-100 d-flex align-items-center justify-content-center">

        <div class="card shadow border-0" style="width: 100%; max-width: 420px;">

            <div class="card-body p-4">

                <h2 class="text-center text-primary fw-bold">
                    TecnoStock
                </h2>

                <p class="text-center text-muted mb-4">
                    Sistema de gestión de inventario
                </p>

                <h5 class="mb-3">Iniciar sesión</h5>
                    <?php if (($_GET['error'] ?? '') === 'credenciales'): ?>
                         <div class="alert alert-danger">
                            Correo o contraseña incorrectos.
                        </div>
                    <?php elseif (($_GET['error'] ?? '') === 'servidor'): ?>
                        <div class="alert alert-danger">
                            No se pudo completar el inicio de sesión.
                            Inténtalo nuevamente.
                        </div>
                    <?php endif; ?>
                <form action="autenticar.php" method="POST">

                    <div class="mb-3">
                        <label for="correo" class="form-label">
                            Correo electrónico
                        </label>

                        <input
                            type="email"
                            class="form-control"
                            id="correo"
                            name="correo"
                            placeholder="ejemplo@correo.cl"
                            autocomplete="username"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="clave" class="form-label">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="clave"
                            name="clave"
                            autocomplete="current-password"
                            required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Ingresar
                    </button>

                </form>

            </div>
        </div>
    </div>

</body>
</html>
