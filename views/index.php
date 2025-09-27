<?php
// index.php - Landing page
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rumbo al Saber - Sistema de Exámenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--light-bg);
        }

        .navbar {
            background: var(--primary-color) !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            color: #fff !important;
            font-weight: 600;
        }

        .hero-section {
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)),
                        url('../assets/img/education-bg.jpg') center/cover;
            min-height: 80vh;
            padding: 100px 0;
            color: white;
        }

        .hero-title {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .feature-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            color: var(--secondary-color);
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .btn-custom {
            padding: 10px 25px;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom {
            background: var(--secondary-color);
            color: white;
            border: none;
        }

        .btn-primary-custom:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .features-section {
            padding: 80px 0;
            background: white;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 3rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-book me-2"></i>Rumbo al Saber
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="hero-title">Plataforma de Evaluación Académica</h1>
                    <p class="lead mb-4">
                        Sistema integral para la gestión y aplicación de exámenes académicos
                    </p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a href="login.php" class="btn btn-custom btn-primary-custom me-sm-3">
                            Acceder al Sistema
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <h2 class="section-title">Características del Sistema</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-pencil-square feature-icon"></i>
                        <h4>Exámenes Digitales</h4>
                        <p>Realiza evaluaciones en línea de forma segura y eficiente.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-graph-up feature-icon"></i>
                        <h4>Seguimiento Académico</h4>
                        <p>Monitorea el progreso y rendimiento de los estudiantes.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <i class="bi bi-file-earmark-text feature-icon"></i>
                        <h4>Reportes Detallados</h4>
                        <p>Genera informes completos de resultados y estadísticas.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>