<?php
// estudiante_statistics.php
// Start session
session_start();

// Check if user is logged in and is an admin or profesor
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "123456789";
$dbname = "examenes_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get estudiante statistics
$stmt = $conn->prepare("
    SELECT 
        u.id AS estudiante_id, 
        u.nombre AS estudiante_name, 
        u.email,
        COUNT(DISTINCT r.examen_id) AS total_exams,
        ROUND(AVG(r.puntaje), 1) AS average_score,
        SUM(CASE WHEN r.puntaje >= 60 THEN 1 ELSE 0 END) AS passed_exams,
        SUM(CASE WHEN r.puntaje < 60 THEN 1 ELSE 0 END) AS failed_exams,
        MAX(r.puntaje) AS highest_score,
        MIN(r.puntaje) AS lowest_score
    FROM 
        usuarios u
    LEFT JOIN 
        resultados r ON u.id = r.estudiante_id
    WHERE 
        u.role = 'estudiante'
    GROUP BY 
        u.id, u.nombre, u.email
    ORDER BY 
        average_score DESC
");
$stmt->execute();
$estudianteStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Overall statistics
$totalestudiantes = count($estudianteStats);
$overallAverageScore = 0;
$overallPassedExams = 0;
$overallTotalExams = 0;

foreach ($estudianteStats as $estudiante) {
    $overallAverageScore += $estudiante['average_score'];
    $overallPassedExams += $estudiante['passed_exams'];
    $overallTotalExams += $estudiante['total_exams'];
}

$overallAverageScore = $totalestudiantes > 0 ? round($overallAverageScore / $totalestudiantes, 1) : 0;
$overallPassRate = $overallTotalExams > 0 ? round(($overallPassedExams / $overallTotalExams) * 100, 1) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Estudiantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="exam-system.css">
</head>
<body>
    <?php include 'views/partials/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door"></i> Inicio
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="crear_examen.php">
                                <i class="bi bi-file-earmark-plus"></i> Crear Examen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="exam_statistics.php">
                                <i class="bi bi-graph-up"></i> Estadísticas de Exámenes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Estadísticas de Estudiantes</h1>
                </div>
                
                <?php if (empty($estudianteStats)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay estudiantes registrados.
                    </div>
                <?php else: ?>
                    <!-- Overall Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-people"></i> Total de Estudiantes</h5>
                                    <p class="card-text display-4"><?php echo $totalestudiantes; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-graph-up"></i> Promedio General</h5>
                                    <p class="card-text display-4"><?php echo $overallAverageScore; ?>%</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-check-circle"></i> Total de Exámenes</h5>
                                    <p class="card-text display-4"><?php echo $overallTotalExams; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-award"></i> Tasa de Aprobación</h5>
                                    <p class="card-text display-4"><?php echo $overallPassRate; ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- estudiante Statistics Table -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h3 class="card-title">Detalle de Estudiantes</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Email</th>
                                            <th>Exámenes Totales</th>
                                            <th>Promedio</th>
                                            <th>Aprobados</th>
                                            <th>No Aprobados</th>
                                            <th>Mejor Nota</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($estudianteStats as $estudiante): ?>
                                            <tr class="estudiante-row" onclick="window.location='estudiante_details.php?estudiante_id=<?php echo $estudiante['estudiante_id']; ?>'">
                                                <td><?php echo htmlspecialchars($estudiante['estudiante_name']); ?></td>
                                                <td><?php echo htmlspecialchars($estudiante['email']); ?></td>
                                                <td><?php echo $estudiante['total_exams']; ?></td>
                                                <td class="<?php
                                                    if ($estudiante['average_score'] >= 80) {
                                                        echo 'score-high';
                                                    } elseif ($estudiante['average_score'] >= 60) {
                                                        echo 'score-medium';
                                                    } else {
                                                        echo 'score-low';
                                                    }
                                            ?>">
                                                    <?php echo $estudiante['average_score']; ?>%
                                                </td>
                                                <td><?php echo $estudiante['passed_exams']; ?></td>
                                                <td><?php echo $estudiante['failed_exams']; ?></td>
                                                <td class="score-high"><?php echo $estudiante['highest_score']; ?>%</td>
                                                <td>
                                                    <a href="estudiante_details.php?estudiante_id=<?php echo $estudiante['estudiante_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> Detalles
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>