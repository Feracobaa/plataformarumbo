<?php
// exam_statistics.php
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

// Get exam statistics
$examStats = [];
$stmt = $conn->prepare("
    SELECT 
        e.id AS exam_id,
        e.titulo AS exam_title,
        COUNT(r.id) AS total_attempts,
        SUM(CASE WHEN r.puntaje >= 60 THEN 1 ELSE 0 END) AS passed_count,
        SUM(CASE WHEN r.puntaje < 60 THEN 1 ELSE 0 END) AS failed_count,
        ROUND(AVG(r.puntaje), 1) AS average_score,
        MAX(r.puntaje) AS highest_score,
        MIN(r.puntaje) AS lowest_score
    FROM 
        examenes e
    LEFT JOIN 
        resultados r ON e.id = r.examen_id
    GROUP BY 
        e.id, e.titulo
    ORDER BY 
        total_attempts DESC
");

if ($stmt) {
    $stmt->execute();
    $examStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get top 5 most failed exams
$mostFailedExams = [];
$stmt = $conn->prepare("
    SELECT 
        e.id AS exam_id,
        e.titulo AS exam_title,
        COUNT(r.id) AS total_attempts,
        SUM(CASE WHEN r.puntaje < 60 THEN 1 ELSE 0 END) AS failed_count,
        ROUND((SUM(CASE WHEN r.puntaje < 60 THEN 1 ELSE 0 END) / COUNT(r.id)) * 100, 1) AS failure_rate
    FROM 
        examenes e
    JOIN 
        resultados r ON e.id = r.examen_id
    GROUP BY 
        e.id, e.titulo
    HAVING 
        total_attempts >= 3
    ORDER BY 
        failure_rate DESC, total_attempts DESC
    LIMIT 5
");

if ($stmt) {
    $stmt->execute();
    $mostFailedExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get top 5 most passed exams
$mostPassedExams = [];
$stmt = $conn->prepare("
    SELECT 
        e.id AS exam_id,
        e.titulo AS exam_title,
        COUNT(r.id) AS total_attempts,
        SUM(CASE WHEN r.puntaje >= 60 THEN 1 ELSE 0 END) AS passed_count,
        ROUND((SUM(CASE WHEN r.puntaje >= 60 THEN 1 ELSE 0 END) / COUNT(r.id)) * 100, 1) AS success_rate
    FROM 
        examenes e
    JOIN 
        resultados r ON e.id = r.examen_id
    GROUP BY 
        e.id, e.titulo
    HAVING 
        total_attempts >= 3
    ORDER BY 
        success_rate DESC, total_attempts DESC
    LIMIT 5
");

if ($stmt) {
    $stmt->execute();
    $mostPassedExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get student performance data per subject/exam category
// This assumes you might want to add a category/subject field to the examenes table in the future
$studentPerformance = [];
$stmt = $conn->prepare("
    SELECT 
        e.titulo AS exam_name,
        COUNT(DISTINCT r.estudiante_id) AS student_count,
        ROUND(AVG(r.puntaje), 1) AS average_score
    FROM 
        resultados r
    JOIN 
        examenes e ON r.examen_id = e.id
    GROUP BY 
        e.id, e.titulo
    ORDER BY 
        average_score DESC
");

if ($stmt) {
    $stmt->execute();
    $studentPerformance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Prepare data for charts
$examLabels = [];
$passRates = [];
$failRates = [];
$avgScores = [];

foreach ($examStats as $exam) {
    if ($exam['total_attempts'] > 0) {
        $examLabels[] = $exam['exam_title'];
        $passRate = ($exam['passed_count'] / $exam['total_attempts']) * 100;
        $failRate = ($exam['failed_count'] / $exam['total_attempts']) * 100;

        $passRates[] = round($passRate, 1);
        $failRates[] = round($failRate, 1);
        $avgScores[] = $exam['average_score'];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas de Exámenes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 30px;
        }
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .table-container {
            margin-top: 30px;
            margin-bottom: 30px;
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
                            <a class="nav-link" href="create_exam.php">
                                <i class="bi bi-file-earmark-plus"></i> Crear Examen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="bi bi-person-lines-fill"></i> Estadísticas de Estudiantes
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
                    <h1 class="h2">Estadísticas de Exámenes</h1>
                </div>
                
                <?php if (empty($examStats)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay datos de exámenes disponibles.
                    </div>
                <?php else: ?>
                    <!-- Overview Charts -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card stat-card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-bar-chart"></i> Tasas de Aprobación/Reprobación</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="passFailChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card stat-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-graph-up"></i> Puntuaciones Promedio</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="avgScoreChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Failed vs Passed Exams -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card stat-card">
                                <div class="card-header bg-danger text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-x-circle"></i> Exámenes con Mayor Tasa de Reprobación</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($mostFailedExams)): ?>
                                        <p class="text-muted">No hay suficientes datos para mostrar esta estadística.</p>
                                    <?php else: ?>
                                        <div class="chart-container">
                                            <canvas id="mostFailedChart"></canvas>
                                        </div>
                                        <div class="table-container">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Examen</th>
                                                        <th>Intentos</th>
                                                        <th>Tasa de Reprobación</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($mostFailedExams as $exam): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                                            <td><?php echo $exam['total_attempts']; ?></td>
                                                            <td class="score-low"><?php echo $exam['failure_rate']; ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card stat-card">
                                <div class="card-header bg-success text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-check-circle"></i> Exámenes con Mayor Tasa de Aprobación</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($mostPassedExams)): ?>
                                        <p class="text-muted">No hay suficientes datos para mostrar esta estadística.</p>
                                    <?php else: ?>
                                        <div class="chart-container">
                                            <canvas id="mostPassedChart"></canvas>
                                        </div>
                                        <div class="table-container">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Examen</th>
                                                        <th>Intentos</th>
                                                        <th>Tasa de Aprobación</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($mostPassedExams as $exam): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                                            <td><?php echo $exam['total_attempts']; ?></td>
                                                            <td class="score-high"><?php echo $exam['success_rate']; ?>%</td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Student Performance -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card stat-card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="card-title mb-0"><i class="bi bi-people"></i> Rendimiento de Estudiantes por Examen</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($studentPerformance)): ?>
                                        <p class="text-muted">No hay suficientes datos para mostrar esta estadística.</p>
                                    <?php else: ?>
                                        <div class="chart-container" style="height: 400px;">
                                            <canvas id="studentPerformanceChart"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed Exam Statistics Table -->
                    <div class="card stat-card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title">Detalle de Exámenes</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Examen</th>
                                            <th>Intentos</th>
                                            <th>Aprobados</th>
                                            <th>Reprobados</th>
                                            <th>Puntaje Promedio</th>
                                            <th>Nota Más Alta</th>
                                            <th>Nota Más Baja</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($examStats as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['exam_title']); ?></td>
                                                <td><?php echo $exam['total_attempts']; ?></td>
                                                <td>
                                                    <?php echo $exam['passed_count']; ?> 
                                                    (<?php echo $exam['total_attempts'] > 0 ? round(($exam['passed_count'] / $exam['total_attempts']) * 100, 1) : 0; ?>%)
                                                </td>
                                                <td>
                                                    <?php echo $exam['failed_count']; ?> 
                                                    (<?php echo $exam['total_attempts'] > 0 ? round(($exam['failed_count'] / $exam['total_attempts']) * 100, 1) : 0; ?>%)
                                                </td>
                                                <td class="<?php
                                                    if ($exam['average_score'] >= 80) {
                                                        echo 'score-high';
                                                    } elseif ($exam['average_score'] >= 60) {
                                                        echo 'score-medium';
                                                    } else {
                                                        echo 'score-low';
                                                    }
                                            ?>">
                                                    <?php echo $exam['average_score']; ?>%
                                                </td>
                                                <td class="score-high"><?php echo $exam['highest_score']; ?>%</td>
                                                <td class="<?php echo $exam['lowest_score'] < 60 ? 'score-low' : 'score-medium'; ?>">
                                                    <?php echo $exam['lowest_score']; ?>%
                                                </td>
                                                <td>
                                                    <a href="exam_details.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-primary">
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
    <script>
        // Initialize charts only if we have data
        <?php if (!empty($examLabels)): ?>
            // Pass/Fail Rate Chart
            const passFailCtx = document.getElementById('passFailChart').getContext('2d');
            const passFailChart = new Chart(passFailCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($examLabels); ?>,
                    datasets: [
                        {
                            label: 'Tasa de Aprobación (%)',
                            data: <?php echo json_encode($passRates); ?>,
                            backgroundColor: 'rgba(40, 167, 69, 0.7)',
                            borderColor: 'rgba(40, 167, 69, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Tasa de Reprobación (%)',
                            data: <?php echo json_encode($failRates); ?>,
                            backgroundColor: 'rgba(220, 53, 69, 0.7)',
                            borderColor: 'rgba(220, 53, 69, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Porcentaje (%)'
                            }
                        }
                    }
                }
            });
            
            // Average Score Chart
            const avgScoreCtx = document.getElementById('avgScoreChart').getContext('2d');
            const avgScoreChart = new Chart(avgScoreCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($examLabels); ?>,
                    datasets: [{
                        label: 'Puntaje Promedio (%)',
                        data: <?php echo json_encode($avgScores); ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.2)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(255, 193, 7, 1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Puntaje (%)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if (!empty($mostFailedExams)): ?>
            // Most Failed Exams Chart
            const mostFailedCtx = document.getElementById('mostFailedChart').getContext('2d');
            const mostFailedChart = new Chart(mostFailedCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($mostFailedExams, 'exam_title')); ?>,
                    datasets: [{
                        label: 'Tasa de Reprobación (%)',
                        data: <?php echo json_encode(array_column($mostFailedExams, 'failure_rate')); ?>,
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Porcentaje (%)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if (!empty($mostPassedExams)): ?>
            // Most Passed Exams Chart
            const mostPassedCtx = document.getElementById('mostPassedChart').getContext('2d');
            const mostPassedChart = new Chart(mostPassedCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($mostPassedExams, 'exam_title')); ?>,
                    datasets: [{
                        label: 'Tasa de Aprobación (%)',
                        data: <?php echo json_encode(array_column($mostPassedExams, 'success_rate')); ?>,
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Porcentaje (%)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
        
        <?php if (!empty($studentPerformance)): ?>
            // Student Performance Chart
            const studentPerfCtx = document.getElementById('studentPerformanceChart').getContext('2d');
            const studentPerfChart = new Chart(studentPerfCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($studentPerformance, 'exam_name')); ?>,
                    datasets: [
                        {
                            label: 'Número de Estudiantes',
                            data: <?php echo json_encode(array_column($studentPerformance, 'student_count')); ?>,
                            backgroundColor: 'rgba(13, 110, 253, 0.7)',
                            borderColor: 'rgba(13, 110, 253, 1)',
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Puntaje Promedio (%)',
                            data: <?php echo json_encode(array_column($studentPerformance, 'average_score')); ?>,
                            backgroundColor: 'rgba(255, 193, 7, 0.2)',
                            borderColor: 'rgba(255, 193, 7, 1)',
                            borderWidth: 2,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Número de Estudiantes'
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            max: 100,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Puntaje Promedio (%)'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>