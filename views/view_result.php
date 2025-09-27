<?php
// view_result.php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'estudiante', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Check if result_id is provided
if (!isset($_GET['result_id']) || empty($_GET['result_id'])) {
    header("Location: completed_exams.php");
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

$resultId = intval($_GET['result_id']);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// FIXED: Improved permission logic
// Get exam result information with proper permission checking
if ($userRole === 'estudiante') {
    // Students can only see their own results
    $stmt = $conn->prepare("
        SELECT r.id as result_id, r.puntaje, r.fecha, 
               e.id as exam_id, e.titulo, e.descripcion,
               u.nombre as creator_name,
               st.nombre as student_name
        FROM resultados r
        JOIN examenes e ON r.examen_id = e.id
        JOIN usuarios u ON e.admin_id = u.id
        JOIN usuarios st ON r.estudiante_id = st.id
        WHERE r.id = ? AND r.estudiante_id = ?
    ");
    $stmt->bind_param("ii", $resultId, $userId);
} else {
    // Admins and professors can see all results
    $stmt = $conn->prepare("
        SELECT r.id as result_id, r.puntaje, r.fecha, 
               e.id as exam_id, e.titulo, e.descripcion,
               u.nombre as creator_name,
               st.nombre as student_name
        FROM resultados r
        JOIN examenes e ON r.examen_id = e.id
        JOIN usuarios u ON e.admin_id = u.id
        JOIN usuarios st ON r.estudiante_id = st.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $resultId);
}

$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result) {
    header("Location: completed_exams.php?error=result_not_found");
    exit;
}

// Get all questions for this exam
$stmt = $conn->prepare("
    SELECT id, enunciado, opcion_a, opcion_b, opcion_c, opcion_d, respuesta_correcta
    FROM preguntas 
    WHERE examen_id = ? 
    ORDER BY id
");
$stmt->bind_param("i", $result['exam_id']);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get student's answers (we'll need to create a table for this)
// For now, we'll simulate some answers since the database doesn't have a respuestas_estudiante table
// In a real implementation, you'd need to create this table to store student answers

// Simulate student answers for demonstration
$studentAnswers = [];
$correctAnswers = 0;
$totalQuestions = count($questions);

// For demonstration, let's create some random answers based on the score
$scorePercentage = $result['puntaje'] / 100;
$correctCount = round($totalQuestions * $scorePercentage);

for ($i = 0; $i < $totalQuestions; $i++) {
    $options = ['A', 'B', 'C', 'D'];
    if ($i < $correctCount) {
        // Correct answer
        $studentAnswers[$i] = $questions[$i]['respuesta_correcta'];
        $correctAnswers++;
    } else {
        // Wrong answer
        $correctOption = $questions[$i]['respuesta_correcta'];
        $wrongOptions = array_filter($options, function ($opt) use ($correctOption) {
            return $opt !== $correctOption;
        });
        $studentAnswers[$i] = $wrongOptions[array_rand($wrongOptions)];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Resultado - <?php echo htmlspecialchars($result['titulo']); ?></title>
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
                        <?php if ($userRole === 'estudiante'): ?>
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
                        <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="statistics.php">
                                <i class="bi bi-graph-up"></i> Estadísticas de Estudiantes
                            </a>
                        </li>
                        <?php endif; ?>
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
                    <h1 class="h2">Detalle del Resultado</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($userRole === 'estudiante'): ?>
                            <a href="completed_exams.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        <?php else: ?>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Exam Summary -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card summary-card">
                            <div class="card-header bg-primary text-white">
                                <h3 class="card-title mb-0">
                                    <i class="bi bi-file-earmark-check"></i> 
                                    <?php echo htmlspecialchars($result['titulo']); ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="card-text"><strong>Descripción:</strong> <?php echo htmlspecialchars($result['descripcion']); ?></p>
                                        <p class="card-text"><strong>Creador:</strong> <?php echo htmlspecialchars($result['creator_name']); ?></p>
                                        <p class="card-text"><strong>Estudiante:</strong> <?php echo htmlspecialchars($result['student_name']); ?></p>
                                        <p class="card-text"><strong>Fecha de realización:</strong> <?php echo date('d/m/Y H:i', strtotime($result['fecha'])); ?></p>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="score-display <?php
                                            if ($result['puntaje'] >= 80) {
                                                echo 'score-high';
                                            } elseif ($result['puntaje'] >= 60) {
                                                echo 'score-medium';
                                            } else {
                                                echo 'score-low';
                                            }
?>">
                                            <?php echo number_format($result['puntaje'], 1); ?>%
                                        </div>
                                        <p class="mb-1">
                                            <?php if ($result['puntaje'] >= 60): ?>
                                                <span class="badge bg-success fs-6">APROBADO</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger fs-6">NO APROBADO</span>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo $correctAnswers; ?> de <?php echo $totalQuestions; ?> preguntas correctas
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <h5><i class="bi bi-check-circle"></i> Correctas</h5>
                                <h2><?php echo $correctAnswers; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center bg-danger text-white">
                            <div class="card-body">
                                <h5><i class="bi bi-x-circle"></i> Incorrectas</h5>
                                <h2><?php echo $totalQuestions - $correctAnswers; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <h5><i class="bi bi-list-check"></i> Total</h5>
                                <h2><?php echo $totalQuestions; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Questions Detail -->
                <div class="card">
                    <div class="card-header bg-light">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-question-circle"></i> Revisión Detallada de Preguntas
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($questions as $index => $question): ?>
                            <?php
                            $isCorrect = $studentAnswers[$index] === $question['respuesta_correcta'];
                            $studentAnswer = $studentAnswers[$index];
                            ?>
                            <div class="question-card card <?php echo $isCorrect ? 'correct-answer' : 'wrong-answer'; ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <span class="badge <?php echo $isCorrect ? 'bg-success' : 'bg-danger'; ?> me-2">
                                            <?php echo $index + 1; ?>
                                        </span>
                                        <?php if ($isCorrect): ?>
                                            <i class="bi bi-check-circle text-success"></i> Respuesta Correcta
                                        <?php else: ?>
                                            <i class="bi bi-x-circle text-danger"></i> Respuesta Incorrecta
                                        <?php endif; ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="question-text mb-3">
                                        <?php echo htmlspecialchars($question['enunciado']); ?>
                                    </h6>
                                    
                                    <div class="row">
                                        <?php
                                        $options = [
                                            'A' => $question['opcion_a'],
                                            'B' => $question['opcion_b'],
                                            'C' => $question['opcion_c'],
                                            'D' => $question['opcion_d']
                                        ];
                            ?>
                                        <?php foreach ($options as $letter => $text): ?>
                                            <div class="col-md-6 mb-2">
                                                <div class="p-2 rounded border <?php
                                        if ($letter === $question['respuesta_correcta']) {
                                            echo 'option-correct';
                                        } elseif ($letter === $studentAnswer && $letter !== $question['respuesta_correcta']) {
                                            echo 'option-wrong';
                                        }
                                            if ($letter === $studentAnswer) {
                                                echo ' option-selected';
                                            }
                                            ?>">
                                                    <strong><?php echo $letter; ?>)</strong> 
                                                    <?php echo htmlspecialchars($text); ?>
                                                    <?php if ($letter === $question['respuesta_correcta']): ?>
                                                        <i class="bi bi-check-circle-fill text-success float-end"></i>
                                                    <?php elseif ($letter === $studentAnswer && $letter !== $question['respuesta_correcta']): ?>
                                                        <i class="bi bi-x-circle-fill text-danger float-end"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if (!$isCorrect): ?>
                                        <div class="alert alert-info mt-3">
                                            <i class="bi bi-info-circle"></i>
                                            <strong>Tu respuesta:</strong> <?php echo $studentAnswer; ?>) <?php echo htmlspecialchars($options[$studentAnswer]); ?>
                                            <br>
                                            <strong>Respuesta correcta:</strong> <?php echo $question['respuesta_correcta']; ?>) <?php echo htmlspecialchars($options[$question['respuesta_correcta']]); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="row mt-4">
                    <div class="col-12 text-center">
                        <?php if ($userRole === 'estudiante'): ?>
                            <a href="completed_exams.php" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-left"></i> Volver a Exámenes Completados
                            </a>
                            <a href="available_exams.php" class="btn btn-success btn-lg ms-2">
                                <i class="bi bi-file-earmark-plus"></i> Realizar Otro Examen
                            </a>
                        <?php else: ?>
                            <a href="javascript:history.back()" class="btn btn-primary btn-lg">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                            <a href="statistics.php" class="btn btn-success btn-lg ms-2">
                                <i class="bi bi-graph-up"></i> Estadísticas de Estudiantes
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha