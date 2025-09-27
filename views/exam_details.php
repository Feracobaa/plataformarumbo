<?php
// exam_details.php
session_start();

// Check if user is logged in and is an admin or profesor
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Check if exam ID is provided
if (!isset($_GET['exam_id']) || !is_numeric($_GET['exam_id'])) {
    header("Location: dashboard.php");
    exit;
}

$examId = intval($_GET['exam_id']);

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

// Get exam details
$stmt = $conn->prepare("
    SELECT e.titulo, e.descripcion, e.created_at, u.nombre as creator
    FROM examenes e
    JOIN usuarios u ON e.admin_id = u.id
    WHERE e.id = ?
");
$stmt->bind_param("i", $examId);
$stmt->execute();
$examInfo = $stmt->get_result()->fetch_assoc();

if (!$examInfo) {
    header("Location: dashboard.php");
    exit;
}

// Get exam questions
$stmt = $conn->prepare("
    SELECT id, enunciado, opcion_a, opcion_b, opcion_c, opcion_d, respuesta_correcta, imagen,
           imagen_opcion_a, imagen_opcion_b, imagen_opcion_c, imagen_opcion_d
    FROM preguntas 
    WHERE examen_id = ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $examId);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get exam results statistics
$stmt = $conn->prepare("
    SELECT 
        r.puntaje,
        r.fecha,
        u.nombre as estudiante_nombre,
        u.email as estudiante_email,
        u.id as estudiante_id
    FROM resultados r
    JOIN usuarios u ON r.estudiante_id = u.id
    WHERE r.examen_id = ?
    ORDER BY r.fecha DESC
");
$stmt->bind_param("i", $examId);
$stmt->execute();
$examResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$totalQuestions = count($questions);
$totalResults = count($examResults);
$totalScore = 0;
$passedResults = 0;
$highestScore = 0;
$lowestScore = $totalResults > 0 ? 100 : 0;

foreach ($examResults as $result) {
    $totalScore += $result['puntaje'];
    if ($result['puntaje'] >= 60) {
        $passedResults++;
    }
    $highestScore = max($highestScore, $result['puntaje']);
    $lowestScore = min($lowestScore, $result['puntaje']);
}

$averageScore = $totalResults > 0 ? round($totalScore / $totalResults, 1) : 0;
$passRate = $totalResults > 0 ? round(($passedResults / $totalResults) * 100, 1) : 0;

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Examen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
        .question-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .correct-answer {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .option-image {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .question-image {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin: 10px 0;
        }
        .result-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .result-row:hover {
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
                        <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'profesor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="create_exam.php">
                                <i class="bi bi-plus-circle"></i> Crear Examen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_exams.php">
                                <i class="bi bi-gear"></i> Gestionar Exámenes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="bi bi-graph-up"></i> Estadísticas de Estudiantes
                            </a>
                        </li>
                        <?php endif; ?>
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
                    <h1 class="h2">Detalles del Examen</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="edit_exam.php?id=<?php echo $examId; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Editar
                            </a>
                            <a href="add_question.php?exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Agregar Pregunta
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Exam Information Card -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><i class="bi bi-clipboard-check"></i> Información del Examen</h5>
                                <p><strong>Título:</strong> <?php echo htmlspecialchars($examInfo['titulo']); ?></p>
                                <p><strong>Creador:</strong> <?php echo htmlspecialchars($examInfo['creator']); ?></p>
                                <p><strong>Fecha de Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($examInfo['created_at'])); ?></p>
                                <p><strong>Descripción:</strong></p>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($examInfo['descripcion'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exam Statistics -->
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card bg-primary text-white">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-question-circle"></i> Total Preguntas</h6>
                                        <p class="card-text display-6"><?php echo $totalQuestions; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card bg-info text-white">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-people"></i> Estudiantes</h6>
                                        <p class="card-text display-6"><?php echo $totalResults; ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-graph-up"></i> Promedio</h6>
                                        <p class="card-text display-6"><?php echo $averageScore; ?>%</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card stat-card bg-warning text-dark">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-trophy"></i> Mejor Nota</h6>
                                        <p class="card-text display-6"><?php echo $highestScore; ?>%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Questions Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Preguntas del Examen</h3>
                        <span class="badge bg-primary"><?php echo count($questions); ?> preguntas</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($questions)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> Este examen aún no tiene preguntas.
                                <a href="add_question.php?exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-primary ms-2">
                                    Agregar Primera Pregunta
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-card card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="text-primary">Pregunta <?php echo $index + 1; ?></h6>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_question.php?id=<?php echo $question['id']; ?>" class="btn btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" onclick="deleteQuestion(<?php echo $question['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <p class="mb-3"><strong><?php echo htmlspecialchars($question['enunciado']); ?></strong></p>
                                        
                                        <?php if (!empty($question['imagen'])): ?>
                                            <img src="<?php echo htmlspecialchars($question['imagen']); ?>" class="question-image mb-3" alt="Imagen de la pregunta">
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <?php
                                            $options = ['A' => $question['opcion_a'], 'B' => $question['opcion_b'], 'C' => $question['opcion_c'], 'D' => $question['opcion_d']];
                                $option_images = ['A' => $question['imagen_opcion_a'], 'B' => $question['imagen_opcion_b'], 'C' => $question['imagen_opcion_c'], 'D' => $question['imagen_opcion_d']];

                                foreach ($options as $letter => $text):
                                    $isCorrect = $question['respuesta_correcta'] == $letter;
                                    ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="p-2 border rounded <?php echo $isCorrect ? 'correct-answer' : ''; ?>">
                                                        <strong><?php echo $letter; ?>)</strong> <?php echo htmlspecialchars($text); ?>
                                                        <?php if ($isCorrect): ?>
                                                            <i class="bi bi-check-circle text-success float-end"></i>
                                                        <?php endif; ?>
                                                        <?php if (!empty($option_images[$letter])): ?>
                                                            <br><img src="<?php echo htmlspecialchars($option_images[$letter]); ?>" class="option-image mt-1" alt="Imagen opción <?php echo $letter; ?>">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Results Section -->
                <?php if (!empty($examResults)): ?>
                    <div class="card">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Resultados de Estudiantes</h3>
                            <span class="badge bg-info"><?php echo $totalResults; ?> intentos</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Estudiante</th>
                                            <th>Email</th>
                                            <th>Fecha</th>
                                            <th>Puntuación</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($examResults as $result): ?>
                                            <tr class="result-row">
                                                <td><?php echo htmlspecialchars($result['estudiante_nombre']); ?></td>
                                                <td><?php echo htmlspecialchars($result['estudiante_email']); ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($result['fecha'])); ?></td>
                                                <td class="<?php
                                            if ($result['puntaje'] >= 80) {
                                                echo 'score-high';
                                            } elseif ($result['puntaje'] >= 60) {
                                                echo 'score-medium';
                                            } else {
                                                echo 'score-low';
                                            }
                                            ?>">
                                                    <?php echo $result['puntaje']; ?>%
                                                </td>
                                                <td>
                                                    <?php if ($result['puntaje'] >= 60): ?>
                                                        <span class="badge bg-success">Aprobado</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">No Aprobado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="estudiante_details.php?estudiante_id=<?php echo $result['estudiante_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-person"></i> Ver Estudiante
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Aún no hay estudiantes que hayan completado este examen.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteQuestion(questionId) {
            if (confirm('¿Estás seguro de que quieres eliminar esta pregunta?')) {
                // Crear un formulario para enviar la petición de eliminación
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_question.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'question_id';
                input.value = questionId;
                
                const examInput = document.createElement('input');
                examInput.type = 'hidden';
                examInput.name = 'exam_id';
                examInput.value = <?php echo $examId; ?>;
                
                form.appendChild(input);
                form.appendChild(examInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>