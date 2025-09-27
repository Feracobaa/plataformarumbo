<?php
// estudiante_details.php
session_start();

// Check if user is logged in and is an admin or profesor
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Check if estudiante ID is provided
if (!isset($_GET['estudiante_id']) || !is_numeric($_GET['estudiante_id'])) {
    header("Location: estudiante_statistics.php");
    exit;
}

$estudianteId = intval($_GET['estudiante_id']);

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

// Get estudiante details
$stmt = $conn->prepare("
    SELECT nombre, email, created_at 
    FROM usuarios 
    WHERE id = ? AND role = 'estudiante'
");
$stmt->bind_param("i", $estudianteId);
$stmt->execute();
$estudianteInfo = $stmt->get_result()->fetch_assoc();

if (!$estudianteInfo) {
    header("Location: estudiante_statistics.php");
    exit;
}

// Get detailed exam results - FIXED: Added r.id as result_id
$stmt = $conn->prepare("
    SELECT 
        r.id as result_id,
        e.titulo, 
        r.puntaje, 
        r.fecha,
        (SELECT COUNT(*) FROM preguntas WHERE examen_id = e.id) as total_questions,
        u.nombre as creator
    FROM 
        resultados r
    JOIN 
        examenes e ON r.examen_id = e.id
    JOIN 
        usuarios u ON e.admin_id = u.id
    WHERE 
        r.estudiante_id = ?
    ORDER BY 
        r.fecha DESC
");
$stmt->bind_param("i", $estudianteId);
$stmt->execute();
$examResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalExams = count($examResults);
$totalScore = 0;
$passedExams = 0;
$highestScore = 0;
$lowestScore = $totalExams > 0 ? 100 : 0;

foreach ($examResults as $exam) {
    $totalScore += $exam['puntaje'];
    if ($exam['puntaje'] >= 60) {
        $passedExams++;
    }
    $highestScore = max($highestScore, $exam['puntaje']);
    $lowestScore = min($lowestScore, $exam['puntaje']);
}

$averageScore = $totalExams > 0 ? round($totalScore / $totalExams, 1) : 0;
$passRate = $totalExams > 0 ? round(($passedExams / $totalExams) * 100, 1) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Estudiante</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="exam-system.css">
    <style>
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .score-high {
            color: #28a745;
            font-weight: bold;
        }
        .score-medium {
            color: #fd7e14;
            font-weight: bold;
        }
        .score-low {
            color: #dc3545;
            font-weight: bold;
        }
        .exam-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .exam-row:hover {
            background-color: rgba(0, 123, 255, 0.1) !important;
        }
    </style>
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
                            <a class="nav-link" href="statistics.php">
                                <i class="bi bi-graph-up"></i> Estadísticas de Estudiantes
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
                    <h1 class="h2">Detalles de Estudiante</h1>
                </div>
                
                <!-- estudiante Profile Card -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-person"></i> Perfil del Estudiante</h5>
                                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($estudianteInfo['nombre']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($estudianteInfo['email']); ?></p>
                                <p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y', strtotime($estudianteInfo['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- estudiante Performance Statistics -->
                    <div class="col-md-8">
                        <div class="row">
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
                                        <p class="card-text display-4"><?php echo $averageScore; ?>%</p>
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
                                        <h5 class="card-title"><i class="bi bi-trophy"></i> Mejor Nota</h5>
                                        <p class="card-text display-4"><?php echo $highestScore; ?>%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($examResults)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> El estudiante aún no ha completado ningún examen.
                    </div>
                <?php else: ?>
                    <!-- Exam History Table -->
                    <div class="card">
                        <div class="card-header bg-light">
                            <h3 class="card-title">Historial de Exámenes</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Título del Examen</th>
                                            <th>Creador</th>
                                            <th>Fecha</th>
                                            <th>Puntuación</th>
                                            <th>Total Preguntas</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($examResults as $exam): ?>
                                            <tr class="exam-row">
                                                <td><?php echo htmlspecialchars($exam['titulo']); ?></td>
                                                <td><?php echo htmlspecialchars($exam['creator']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($exam['fecha'])); ?></td>
                                                <td class="<?php
                                                    if ($exam['puntaje'] >= 80) {
                                                        echo 'score-high';
                                                    } elseif ($exam['puntaje'] >= 60) {
                                                        echo 'score-medium';
                                                    } else {
                                                        echo 'score-low';
                                                    }
                                            ?>">
                                                    <?php echo $exam['puntaje']; ?>%
                                                </td>
                                                <td><?php echo $exam['total_questions']; ?></td>
                                                <td>
                                                    <?php if ($exam['puntaje'] >= 60): ?>
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