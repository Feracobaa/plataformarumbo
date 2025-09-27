
<?php
// completed_exams.php
// Start session
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'estudiante', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$servername = "localhost";
$username = "root"; // Change as needed
$password = "123456789"; // Change as needed
$dbname = "examenes_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Get completed exams with creator information
$stmt = $conn->prepare("
    SELECT r.id as result_id, e.id as exam_id, e.titulo, u.nombre as creator, 
           r.puntaje, r.fecha, 
           (SELECT COUNT(*) FROM preguntas WHERE examen_id = e.id) as total_questions
    FROM resultados r
    JOIN examenes e ON r.examen_id = e.id
    JOIN usuarios u ON e.admin_id = u.id
    WHERE r.estudiante_id = ?
    ORDER BY r.fecha DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$completedExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalExams = count($completedExams);
$totalScore = 0;
$passedExams = 0;
$failedExams = 0;

foreach ($completedExams as $exam) {
    $totalScore += $exam['puntaje'];
    if ($exam['puntaje'] >= 60) {
        $passedExams++;
    } else {
        $failedExams++;
    }
}

$averageScore = $totalExams > 0 ? $totalScore / $totalExams : 0;
$passRate = $totalExams > 0 ? ($passedExams / $totalExams) * 100 : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exámenes Completados</title>
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
                            <a class="nav-link" href="available_exams.php">
                                <i class="bi bi-file-earmark-text"></i> Exámenes Disponibles
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="completed_exams.php">
                                <i class="bi bi-check-circle"></i> Exámenes Completados
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="perfil.php">
                                <i class="bi bi-person"></i> Mi Perfil
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
                    <h1 class="h2">Exámenes Completados</h1>
                </div>
                
                <?php if (empty($completedExams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No has completado ningún examen todavía. 
                        <a href="available_exams.php" class="alert-link">Ve a Exámenes Disponibles</a> para empezar.
                    </div>
                <?php else: ?>
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-clipboard-check"></i> Total de Exámenes</h5>
                                    <p class="card-text display-4"><?php echo $totalExams; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-graph-up"></i> Promedio</h5>
                                    <p class="card-text display-4"><?php echo number_format($averageScore, 1); ?>%</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-award"></i> Aprobados</h5>
                                    <p class="card-text display-4"><?php echo $passedExams; ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card bg-warning text-dark">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="bi bi-x-circle"></i> No Aprobados</h5>
                                    <p class="card-text display-4"><?php echo $failedExams; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exams Table -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h3 class="card-title">Historial de Exámenes</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Título</th>
                                            <th>Creador</th>
                                            <th>Fecha</th>
                                            <th>Puntuación</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($completedExams as $exam): ?>
                                            <tr class="result-row" onclick="window.location='view_result.php?result_id=<?php echo $exam['result_id']; ?>'">
                                                <td><?php echo htmlspecialchars($exam['titulo']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['creator']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($exam['fecha'])); ?></td>
                                                <td class="<?php 
                                                    if($exam['puntaje'] >= 80) echo 'score-high';
                                                    elseif($exam['puntaje'] >= 60) echo 'score-medium';
                                                    else echo 'score-low';
                                                ?>">
                                                    <?php echo number_format($exam['puntaje'], 1); ?>%
                                                </td>
                                                <td>
                                                    <?php if($exam['puntaje'] >= 60): ?>
                                                        <span class="badge bg-success">Aprobado</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">No Aprobado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_result.php?result_id=<?php echo $exam['result_id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> Ver Detalles
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