<?php
// vista_previa_examen.php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Define constants for database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change in production
define('DB_PASS', '123456789'); // Change in production
define('DB_NAME', 'examenes_db');

// Database connection function
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }
        
        // Set charset
        $conn->set_charset("utf8mb4");
    }
    
    return $conn;
}

// Get database connection
$conn = getDbConnection();

/**
 * Display alert message
 * @param string $message The message to display
 * @param string $type The type of alert (success, danger, warning, info)
 * @return string HTML for the alert
 */
function displayAlert($message, $type = 'info') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

// Initialize variables
$message = '';
$examId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// If exam ID is not provided, redirect to exams list
if ($examId === 0) {
    header("Location: examenes.php");
    exit;
}

// Get exam details
$examDetails = null;
$canEdit = false;
$stmt = $conn->prepare("
    SELECT e.*, u.nombre AS creator_name 
    FROM examenes e 
    LEFT JOIN usuarios u ON e.admin_id = u.id 
    WHERE e.id = ?
");
$stmt->bind_param("i", $examId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $examDetails = $result->fetch_assoc();
    
    // Check if user can edit this exam (admin or creator)
    $canEdit = ($userRole === 'admin' || $examDetails['admin_id'] == $userId);
    
    // If user is not admin and not the creator, check if they have access
    if (!$canEdit && $userRole === 'profesor') {
        // Check if the exam has been shared with this professor
        // This would require an additional table in your database schema
        // For example: exam_permissions, exam_sharing, etc.
        $permissionStmt = $conn->prepare("
            SELECT id FROM exam_permissions 
            WHERE exam_id = ? AND user_id = ?
        ");
        $permissionStmt->bind_param("ii", $examId, $userId);
        $permissionStmt->execute();
        $permissionResult = $permissionStmt->get_result();
        
        if ($permissionResult->num_rows > 0) {
            $canEdit = true;
        }
        $permissionStmt->close();
    }
    
    // If student, check if they're allowed to view this exam
    if ($userRole === 'estudiante') {
        // Check if exam is published and available to students
        if (!isset($examDetails['published']) || $examDetails['published'] != 1) {
            $message = displayAlert('No tienes permiso para ver este examen.', 'danger');
            $examDetails = null;
        }
    }
} else {
    $message = displayAlert('El examen solicitado no existe.', 'danger');
}
$stmt->close();

// Get all questions for this exam if user has access
$questions = [];
if ($examDetails) {
    $stmtQuestions = $conn->prepare("
        SELECT * FROM preguntas 
        WHERE examen_id = ? 
        ORDER BY id
    ");
    $stmtQuestions->bind_param("i", $examId);
    $stmtQuestions->execute();
    $questionsResult = $stmtQuestions->get_result();
    
    while ($question = $questionsResult->fetch_assoc()) {
        $questions[] = $question;
    }
    $stmtQuestions->close();
}

// Handle preview mode
$previewMode = isset($_GET['mode']) && $_GET['mode'] === 'student';

// Handle print mode
$printMode = isset($_GET['print']) && $_GET['print'] === '1';


// Count correct answers (for printable answer key)
$totalCorrectA = 0;
$totalCorrectB = 0;
$totalCorrectC = 0;
$totalCorrectD = 0;

foreach ($questions as $question) {
    switch ($question['respuesta_correcta']) {
        case 'A': $totalCorrectA++; break;
        case 'B': $totalCorrectB++; break;
        case 'C': $totalCorrectC++; break;
        case 'D': $totalCorrectD++; break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $examDetails ? htmlspecialchars($examDetails['titulo']) : 'Vista Previa de Examen'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        main {
            padding-top: 20px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .print-full-width {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
                margin-left: 0 !important;
            }
            .print-break-after {
                page-break-after: always;
            }
            .print-border {
                border: 1px solid #000 !important;
                padding: 10px !important;
                margin-bottom: 15px !important;
            }
        }
        .exam-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .question-number {
            font-weight: bold;
            margin-right: 8px;
        }
        .student-mode .correct-answer {
            /* Hide correct answer highlight in student mode */
            background-color: transparent !important;
        }
        .option-circle {
            display: inline-block;
            width: 24px;
            height: 24px;
            line-height: 22px;
            text-align: center;
            border-radius: 50%;
            border: 1px solid #333;
            margin-right: 10px;
            font-weight: bold;
        }
        .answer-key {
            font-size: 12px;
            border-collapse: collapse;
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
        }
        .answer-key th, .answer-key td {
            border: 1px solid #333;
            padding: 5px;
            text-align: center;
        }
        .answer-distribution {
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
    <?php if ($printMode): ?>
    <style>
        body {
            font-size: 12pt;
        }
        .container-fluid {
            width: 100%;
            padding: 0;
            margin: 0;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .card-body {
            padding: 0 !important;
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if (!$printMode): ?>
    <?php include 'views/partials/header.php'; ?>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php if (!$printMode): ?>
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse no-print">
                <div class="position-sticky sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door"></i> Inicio
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="examenes.php">
                                <i class="bi bi-file-text"></i> Exámenes
                            </a>
                        </li>
                        <?php if ($userRole === 'admin' || $userRole === 'profesor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="crear_examen.php">
                                <i class="bi bi-plus-circle"></i> Crear Examen
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if ($userRole === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="gestionar_usuarios.php">
                                <i class="bi bi-people"></i> Usuarios
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
            </nav>
            <?php endif; ?>
            
            <!-- Main content -->
            <main class="<?php echo $printMode ? 'col-12' : 'col-md-9 ms-sm-auto col-lg-10'; ?> px-md-4 <?php echo $printMode ? 'print-full-width' : ''; ?>">
                <?php if (!$printMode): ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom no-print">
                    <h1 class="h2">Vista Previa del Examen</h1>
                    <?php if ($examDetails && $canEdit): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <?php if (!$previewMode): ?>
                            <a href="?id=<?php echo $examId; ?>&mode=student" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Vista de Estudiante
                            </a>
                            <?php else: ?>
                            <a href="?id=<?php echo $examId; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Vista de Profesor
                            </a>
                            <?php endif; ?>
                            <a href="?id=<?php echo $examId; ?>&print=1" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-printer"></i> Versión Imprimible
                            </a>
                            
                        </div>
                        
                        <?php if ($canEdit): ?>
                        <a href="crear_examen.php?exam_id=<?php echo $examId; ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i> Editar Examen
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php echo $message; ?>
                
                <?php if ($examDetails): ?>
                <div class="exam-content <?php echo $previewMode ? 'student-mode' : ''; ?>">
                    <!-- Exam Header -->
                    <div class="exam-header print-border">
                        <h1 class="h3"><?php echo htmlspecialchars($examDetails['titulo']); ?></h1>
                        <?php if (!$previewMode || $printMode): ?>
                        <p class="mb-1"><strong>Creado por:</strong> <?php echo htmlspecialchars($examDetails['creator_name']); ?></p>
                        <p class="mb-1"><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($examDetails['created_at'])); ?></p>
                        <?php endif; ?>
                        <p class="mb-0"><strong>Descripción:</strong> <?php echo htmlspecialchars($examDetails['descripcion']); ?></p>
                    </div>
                    
                    <?php if ($printMode && !$previewMode): ?>
                    <!-- Answer Key (only in print mode for teachers) -->
                    <div class="print-break-after">
                        <h3 class="text-center mb-4">Hoja de Respuestas</h3>
                        <table class="answer-key">
                            <thead>
                                <tr>
                                    <th>Pregunta</th>
                                    <th>Respuesta</th>
                                    <th>Pregunta</th>
                                    <th>Respuesta</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $halfCount = ceil(count($questions) / 2);
                                for ($i = 0; $i < $halfCount; $i++): 
                                    $secondIndex = $i + $halfCount;
                                ?>
                                <tr>
                                    <td><?php echo ($i + 1); ?></td>
                                    <td><?php echo $questions[$i]['respuesta_correcta']; ?></td>
                                    <td><?php echo isset($questions[$secondIndex]) ? ($secondIndex + 1) : ''; ?></td>
                                    <td><?php echo isset($questions[$secondIndex]) ? $questions[$secondIndex]['respuesta_correcta'] : ''; ?></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                        
                        <!-- Distribution of answers -->
                        <div class="answer-distribution mt-4">
                            <h4 class="text-center mb-3">Distribución de Respuestas</h4>
                            <div class="row">
                                <div class="col-3 text-center">
                                    <p class="mb-0"><strong>A:</strong> <?php echo $totalCorrectA; ?></p>
                                </div>
                                <div class="col-3 text-center">
                                    <p class="mb-0"><strong>B:</strong> <?php echo $totalCorrectB; ?></p>
                                </div>
                                <div class="col-3 text-center">
                                    <p class="mb-0"><strong>C:</strong> <?php echo $totalCorrectC; ?></p>
                                </div>
                                <div class="col-3 text-center">
                                    <p class="mb-0"><strong>D:</strong> <?php echo $totalCorrectD; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Student Information (only in printable student mode) -->
                    <?php if ($printMode && $previewMode): ?>
                    <div class="student-info print-border mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nombre:</strong> ____________________________</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Fecha:</strong> ____________________________</p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ID Estudiante:</strong> ____________________</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Grupo:</strong> ____________________________</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Questions -->
                    <?php if (count($questions) > 0): ?>
                        <div class="questions-container">
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="card mb-4 print-border">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <span class="question-number"><?php echo ($index + 1); ?>.</span>
                                            <?php echo htmlspecialchars($question['enunciado']); ?>
                                        </h5>
                                        
                                        <div class="options mt-3">
                                            <div class="option-item mb-2 <?php echo (!$previewMode && $question['respuesta_correcta'] === 'A') ? 'correct-answer bg-success bg-opacity-25' : ''; ?>">
                                                <span class="option-circle">A</span>
                                                <?php echo htmlspecialchars($question['opcion_a']); ?>
                                            </div>
                                            
                                            <div class="option-item mb-2 <?php echo (!$previewMode && $question['respuesta_correcta'] === 'B') ? 'correct-answer bg-success bg-opacity-25' : ''; ?>">
                                                <span class="option-circle">B</span>
                                                <?php echo htmlspecialchars($question['opcion_b']); ?>
                                            </div>
                                            
                                            <div class="option-item mb-2 <?php echo (!$previewMode && $question['respuesta_correcta'] === 'C') ? 'correct-answer bg-success bg-opacity-25' : ''; ?>">
                                                <span class="option-circle">C</span>
                                                <?php echo htmlspecialchars($question['opcion_c']); ?>
                                            </div>
                                            
                                            <div class="option-item mb-2 <?php echo (!$previewMode && $question['respuesta_correcta'] === 'D') ? 'correct-answer bg-success bg-opacity-25' : ''; ?>">
                                                <span class="option-circle">D</span>
                                                <?php echo htmlspecialchars($question['opcion_d']); ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!$previewMode && !$printMode): ?>
                                        <div class="question-meta mt-3 no-print">
                                            <small class="text-muted">
                                                <strong>Respuesta correcta:</strong> <?php echo $question['respuesta_correcta']; ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Este examen aún no tiene preguntas.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($printMode): ?>
                    <!-- Answer submission area for students -->
                    <div class="answer-submission-area print-border mt-4">
                        <h3 class="text-center mb-3">Hoja de Respuestas</h3>
                        <div class="row">
                            <?php foreach ($questions as $index => $question): 
                                // Create a new row every 10 questions
                                if ($index % 10 === 0 && $index > 0): ?>
                                </div><div class="row">
                                <?php endif; ?>
                                <div class="col-md-3 col-6 mb-3">
                                    <div class="answer-group">
                                        <span class="fw-bold"><?php echo ($index + 1); ?>.</span>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">A ○</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">B ○</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">C ○</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <label class="form-check-label">D ○</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!$printMode): ?>
                    <!-- Back to exams button -->
                    <div class="text-center mt-4 mb-5 no-print">
                        <a href="examenes.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver a Exámenes
                        </a>
                        
                        <?php if ($canEdit): ?>
                        <a href="crear_examen.php?exam_id=<?php echo $examId; ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Editar Examen
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <!-- Show error message if exam not found or not accessible -->
                <div class="alert alert-danger">
                    <?php echo $message ? $message : 'No se encontró el examen solicitado o no tienes permisos para acceder a él.'; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="examenes.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Volver a Exámenes
                    </a>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php if (!$printMode): ?>
    <?php include 'views/partials/footer.php'; ?>
    <?php endif; ?>
    
    <?php if (!$printMode): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto print when in print mode via URL parameter
        <?php if ($printMode): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    });
    </script>
    <?php endif; ?>

    <?php
    // Close connection
    $conn->close();
    ?>
</body>
</html>