<?php
// logout_success.php
// No session operations here, just display the confirmation page
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Cerrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'views/partials/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="bi bi-check-circle-fill me-2"></i> Sesión Cerrada</h3>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="bi bi-box-arrow-right" style="font-size: 4rem; color: #6c757d;"></i>
                        </div>
                        
                        <h4 class="mb-3">Has cerrado sesión correctamente</h4>
                        <p class="mb-4">Gracias por usar nuestro sistema de exámenes.</p>
                        
                        <div class="d-grid gap-2 col-md-8 mx-auto">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Iniciar sesión nuevamente
                            </a>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-door me-2"></i> Ir a la página principal
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-center text-muted">
                        <small>Si deseas registrarte con una nueva cuenta, haz clic <a href="register.php">aquí</a>.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>