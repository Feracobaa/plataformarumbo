<?php
// take_exam_fixed.php
session_start();
session_regenerate_id(true);

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'estudiante', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = new mysqli("localhost", "root", "123456789", "examenes_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8");

$userId = intval($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['user_role'] ?? '';
$examTimeLimit = 60; // minutes

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';
$takingExam = false;
$examCompleted = false;
$currentQuestion = 1;
$examData = null;
$questions = [];
$totalQuestions = 0;
$examId = 0;

// Function to calculate remaining time
function getRemainingTime($startTime, $timeLimitMinutes) {
    $elapsed = time() - $startTime;
    $totalSeconds = $timeLimitMinutes * 60;
    return max(0, $totalSeconds - $elapsed);
}

// Function to save answer via AJAX
if (isset($_POST['ajax_save_answer'])) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    
    $examId = intval($_POST['exam_id']);
    $questionNum = intval($_POST['question_num']);
    $answer = $_POST['answer'] ?? '';
    
    if (!isset($_SESSION['exam_answers_' . $examId])) {
        $_SESSION['exam_answers_' . $examId] = [];
    }
    
    $_SESSION['exam_answers_' . $examId][$questionNum] = $answer;
    $_SESSION['current_question_' . $examId] = $questionNum;
    
    echo json_encode(['success' => true]);
    exit;
}

// Validate exam_id
if (isset($_GET['exam_id']) && is_numeric($_GET['exam_id'])) {
    $examId = intval($_GET['exam_id']);
    
    // First verify exam exists
    $stmt = $conn->prepare("SELECT id, titulo, descripcion FROM examenes WHERE id = ?");
    $stmt->bind_param("i", $examId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = "El examen especificado no existe.";
        $messageType = "danger";
        $stmt->close();
    } else {
        $examData = $result->fetch_assoc();
        $stmt->close();
        
        // Check attempts BEFORE allowing access
        $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM resultados WHERE estudiante_id = ? AND examen_id = ?");
        $stmt->bind_param("ii", $userId, $examId);
        $stmt->execute();
        $attemptCount = $stmt->get_result()->fetch_assoc()['attempt_count'];
        $stmt->close();
        
        if ($attemptCount >= 2) {
            $message = "Has agotado el número máximo de intentos (2) para este examen.";
            $messageType = "warning";
        } else {
            // Get questions
            $stmt = $conn->prepare("SELECT id, enunciado, opcion_a, opcion_b, opcion_c, opcion_d, respuesta_correcta FROM preguntas WHERE examen_id = ? ORDER BY id");
            $stmt->bind_param("i", $examId);
            $stmt->execute();
            $questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            if (empty($questions)) {
                $message = "Este examen no tiene preguntas configuradas.";
                $messageType = "warning";
            } else {
                $takingExam = true;
                $totalQuestions = count($questions);
                
                // Initialize exam session if not exists
                if (!isset($_SESSION['exam_start_time_' . $examId])) {
                    $_SESSION['exam_start_time_' . $examId] = time();
                    $_SESSION['exam_answers_' . $examId] = [];
                    $_SESSION['current_question_' . $examId] = 1;
                }
                
                // Check if time has expired
                $remainingTime = getRemainingTime($_SESSION['exam_start_time_' . $examId], $examTimeLimit);
                if ($remainingTime <= 0) {
                    // Auto-submit exam
                    $userAnswers = $_SESSION['exam_answers_' . $examId] ?? [];
                    $score = 0;
                    
                    foreach ($questions as $index => $question) {
                        $questionNum = $index + 1;
                        if (isset($userAnswers[$questionNum]) && $userAnswers[$questionNum] === $question['respuesta_correcta']) {
                            $score++;
                        }
                    }
                    
                    $percentageScore = ($score / $totalQuestions) * 100;
                    
                    // Use transaction for data integrity
                    $conn->begin_transaction();
                    try {
                        $stmt = $conn->prepare("INSERT INTO resultados (estudiante_id, examen_id, puntaje, fecha) VALUES (?, ?, ?, NOW())");
                        $stmt->bind_param("iid", $userId, $examId, $percentageScore);
                        $stmt->execute();
                        $stmt->close();
                        
                        $conn->commit();
                        
                        $message = "Tiempo agotado. Examen enviado automáticamente. Puntuación: $score/$totalQuestions (" . number_format($percentageScore, 2) . "%)";
                        $messageType = "warning";
                        $takingExam = false;
                        $examCompleted = true;
                        
                        // Clear session data
                        unset($_SESSION['exam_answers_' . $examId]);
                        unset($_SESSION['current_question_' . $examId]);
                        unset($_SESSION['exam_start_time_' . $examId]);
                        
                    } catch (Exception $e) {
                        $conn->rollback();
                        $message = "Error al procesar el examen: " . htmlspecialchars($e->getMessage());
                        $messageType = "danger";
                    }
                } else {
                    // Get current question
                    if (isset($_GET['q']) && is_numeric($_GET['q'])) {
                        $currentQuestion = max(1, min(intval($_GET['q']), $totalQuestions));
                        $_SESSION['current_question_' . $examId] = $currentQuestion;
                    } else {
                        $currentQuestion = $_SESSION['current_question_' . $examId] ?? 1;
                    }
                    
                    // Handle form submissions
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_save_answer'])) {
                        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
                            die("Error de seguridad: Token CSRF inválido");
                        }
                        
                        // Save current answer
                        if (isset($_POST['answer'])) {
                            $_SESSION['exam_answers_' . $examId][$currentQuestion] = $_POST['answer'];
                        }
                        
                        // Navigation
                        if (isset($_POST['next'])) {
                            $currentQuestion = min($currentQuestion + 1, $totalQuestions);
                            $_SESSION['current_question_' . $examId] = $currentQuestion;
                            header("Location: ?exam_id=$examId&q=$currentQuestion");
                            exit;
                        } elseif (isset($_POST['previous'])) {
                            $currentQuestion = max($currentQuestion - 1, 1);
                            $_SESSION['current_question_' . $examId] = $currentQuestion;
                            header("Location: ?exam_id=$examId&q=$currentQuestion");
                            exit;
                        } elseif (isset($_POST['submit_exam'])) {
                            // Calculate score
                            $score = 0;
                            $userAnswers = $_SESSION['exam_answers_' . $examId] ?? [];
                            
                            foreach ($questions as $index => $question) {
                                $questionNum = $index + 1;
                                if (isset($userAnswers[$questionNum]) && $userAnswers[$questionNum] === $question['respuesta_correcta']) {
                                    $score++;
                                }
                            }
                            
                            $percentageScore = ($score / $totalQuestions) * 100;
                            
                            // Use transaction
                            $conn->begin_transaction();
                            try {
                                $stmt = $conn->prepare("INSERT INTO resultados (estudiante_id, examen_id, puntaje, fecha) VALUES (?, ?, ?, NOW())");
                                $stmt->bind_param("iid", $userId, $examId, $percentageScore);
                                $stmt->execute();
                                $stmt->close();
                                
                                $conn->commit();
                                
                                $message = "Examen completado exitosamente. Puntuación: $score/$totalQuestions (" . number_format($percentageScore, 2) . "%)";
                                $messageType = "success";
                                $takingExam = false;
                                $examCompleted = true;
                                
                                // Clear session data
                                unset($_SESSION['exam_answers_' . $examId]);
                                unset($_SESSION['current_question_' . $examId]);
                                unset($_SESSION['exam_start_time_' . $examId]);
                                
                            } catch (Exception $e) {
                                $conn->rollback();
                                $message = "Error al guardar los resultados: " . htmlspecialchars($e->getMessage());
                                $messageType = "danger";
                            }
                        }
                    }
                }
            }
        }
    }
}

// Get available exams (optimized query)
$availableExams = [];
if (!$takingExam) {
    $stmt = $conn->prepare("
        SELECT e.id, e.titulo, e.descripcion, u.nombre as creator,
               COALESCE(r.attempt_count, 0) as attempt_count
        FROM examenes e
        JOIN usuarios u ON e.admin_id = u.id
        LEFT JOIN (
            SELECT examen_id, COUNT(*) as attempt_count
            FROM resultados 
            WHERE estudiante_id = ?
            GROUP BY examen_id
        ) r ON e.id = r.examen_id
        WHERE COALESCE(r.attempt_count, 0) < 2
        ORDER BY e.created_at DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $availableExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get completed exams
    $stmt = $conn->prepare("
        SELECT e.titulo, r.puntaje, r.fecha,
        ROW_NUMBER() OVER (PARTITION BY r.examen_id ORDER BY r.fecha) as attempt_number
        FROM resultados r
        JOIN examenes e ON r.examen_id = e.id
        WHERE r.estudiante_id = ?
        ORDER BY r.fecha DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $completedExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $takingExam ? 'Examen en Curso' : 'Exámenes Disponibles'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .exam-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .question-card { border: 2px solid #e9ecef; border-radius: 10px; padding: 30px; margin: 20px 0; }
        .timer { position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 8px; z-index: 1000; font-weight: bold; box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .timer.warning { background: #ffc107; color: #000; }
        .timer.danger { background: #dc3545; animation: blink 1s infinite; }
        @keyframes blink { 0%, 50% { opacity: 1; } 51%, 100% { opacity: 0.5; } }
        .progress-indicator { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .answer-option { margin: 10px 0; padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .answer-option:hover { background: #f8f9fa; border-color: #007bff; }
        .answer-option.selected { background: #e3f2fd; border-color: #007bff; }
        .navigation-buttons { display: flex; justify-content: space-between; margin-top: 30px; }
        body.exam-mode { background: #f8f9fa; }
        .exam-card { background: white; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .auto-save-indicator { position: fixed; top: 80px; right: 20px; padding: 8px 12px; border-radius: 4px; font-size: 0.9em; z-index: 999; }
        .auto-save-indicator.saving { background: #ffc107; color: #000; }
        .auto-save-indicator.saved { background: #28a745; color: white; }
        .exam-completed-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        .exam-completed-card .card-body { text-align: center; padding: 2rem; }
        .completion-message { font-size: 1.2em; margin-bottom: 1.5rem; }
        .dashboard-btn { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; transition: all 0.3s ease; }
        .dashboard-btn:hover { background: rgba(255,255,255,0.3); border-color: rgba(255,255,255,0.5); color: white; transform: translateY(-2px); }
    </style>
</head>
<body <?php echo $takingExam ? 'class="exam-mode"' : ''; ?>>

<?php if ($takingExam): ?>
    <!-- Timer -->
    <div id="exam-timer" class="timer">
        Tiempo restante: <span id="time-display">Cargando...</span>
    </div>
    
    <!-- Auto-save indicator -->
    <div id="auto-save-indicator" class="auto-save-indicator" style="display: none;"></div>

    <div class="exam-container">
        <div class="card exam-card">
            <div class="card-header bg-primary text-white text-center">
                <h3><?php echo htmlspecialchars($examData['titulo']); ?></h3>
                <p class="mb-0"><?php echo htmlspecialchars($examData['descripcion']); ?></p>
            </div>
            
            <div class="card-body">
                <!-- Progress indicator -->
                <div class="progress-indicator text-center">
                    <h5>Pregunta <?php echo $currentQuestion; ?> de <?php echo $totalQuestions; ?></h5>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?php echo ($currentQuestion / $totalQuestions) * 100; ?>%"></div>
                    </div>
                </div>

                <!-- Current question -->
                <?php 
                $question = $questions[$currentQuestion - 1];
                $savedAnswer = $_SESSION['exam_answers_' . $examId][$currentQuestion] ?? '';
                ?>
                
                <form method="post" id="question-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="question-card">
                        <h4 class="mb-4"><?php echo htmlspecialchars($question['enunciado']); ?></h4>
                        
                        <?php foreach (['A' => 'opcion_a', 'B' => 'opcion_b', 'C' => 'opcion_c', 'D' => 'opcion_d'] as $key => $option): ?>
                        <div class="answer-option <?php echo ($savedAnswer === $key) ? 'selected' : ''; ?>" onclick="selectAnswer('<?php echo $key; ?>')">
                            <input type="radio" name="answer" value="<?php echo $key; ?>" id="option_<?php echo $key; ?>" 
                                   <?php echo ($savedAnswer === $key) ? 'checked' : ''; ?> style="margin-right: 10px;">
                            <label for="option_<?php echo $key; ?>" style="cursor: pointer;">
                                <strong><?php echo $key; ?>)</strong> <?php echo htmlspecialchars($question[$option]); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="navigation-buttons">
                        <div>
                            <?php if ($currentQuestion > 1): ?>
                                <button type="submit" name="previous" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Anterior
                                </button>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <?php if ($currentQuestion < $totalQuestions): ?>
                                <button type="submit" name="next" class="btn btn-primary">
                                    Siguiente <i class="bi bi-arrow-right"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success" onclick="finishExam()">
                                    <i class="bi bi-check-circle"></i> Finalizar Examen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Question navigator -->
        <div class="card mt-3">
            <div class="card-body">
                <h6>Navegador de preguntas:</h6>
                <div class="d-flex flex-wrap gap-2">
                    <?php for ($i = 1; $i <= $totalQuestions; $i++): ?>
                        <?php 
                        $answered = isset($_SESSION['exam_answers_' . $examId][$i]);
                        $isCurrent = ($i == $currentQuestion);
                        $btnClass = $isCurrent ? 'btn-primary' : ($answered ? 'btn-success' : 'btn-outline-secondary');
                        ?>
                        <button type="button" onclick="navigateToQuestion(<?php echo $i; ?>)" 
                           class="btn <?php echo $btnClass; ?> btn-sm">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Normal exam list view -->
    <div class="container mt-4">
        <!-- Header with dashboard button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Sistema de Exámenes</h1>
            <div class="d-flex align-items-center gap-3">
                <div class="text-muted">
                    Bienvenido, <?php echo htmlspecialchars($userName); ?>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-house-door"></i> Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                
                <!-- Show dashboard button after exam completion -->
                <?php if ($examCompleted): ?>
                    <div class="mt-3">
                        <div class="card exam-completed-card">
                            <div class="card-body">
                                <div class="completion-message">
                                    <i class="bi bi-check-circle-fill" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                    <h4>¡Examen Completado!</h4>
                                    <p class="mb-0">Tu examen ha sido enviado exitosamente</p>
                                </div>
                                
                                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                                    <a href="dashboard.php" class="btn dashboard-btn btn-lg">
                                        <i class="bi bi-house-door"></i> Ir al Dashboard
                                    </a>
                                    <a href="take_exam.php" class="btn dashboard-btn btn-lg">
                                        <i class="bi bi-list-task"></i> Ver más exámenes
                                    </a>
                                    <a href="mis_resultados.php" class="btn dashboard-btn btn-lg">
                                        <i class="bi bi-graph-up"></i> Ver mis resultados
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <h3>Exámenes Disponibles</h3>
                <?php if (empty($availableExams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        No hay exámenes disponibles en este momento.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($availableExams as $exam): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($exam['titulo']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($exam['descripcion']); ?></p>
                                        <small class="text-muted">Creado por: <?php echo htmlspecialchars($exam['creator']); ?></small>
                                        <?php if ($exam['attempt_count'] > 0): ?>
                                            <br><small class="text-warning">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                Intentos realizados: <?php echo $exam['attempt_count']; ?>/2
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <a href="?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary">
                                            <i class="bi bi-play-circle"></i> Iniciar Examen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <h3>Historial de Exámenes</h3>
                <?php if (empty($completedExams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Aún no has completado ningún examen.
                    </div>
                <?php else: ?>
                    <?php foreach ($completedExams as $exam): ?>
                        <div class="card mb-2">
                            <div class="card-body p-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($exam['titulo']); ?></h6>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $exam['puntaje'] >= 70 ? 'success' : 'warning'; ?>">
                                        <?php echo number_format($exam['puntaje'], 1); ?>%
                                    </span>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($exam['fecha'])); ?>
                                    </small>
                                </div>
                                <small class="text-muted">Intento #<?php echo $exam['attempt_number']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($takingExam): ?>
<script>
// Global variables
const examId = <?php echo $examId; ?>;
const currentQuestionNum = <?php echo $currentQuestion; ?>;
const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
let initialTimeLeft = <?php echo getRemainingTime($_SESSION['exam_start_time_' . $examId], $examTimeLimit); ?>;

// Server-side timer (more secure)
let timeLeft = initialTimeLeft;
const timerElement = document.getElementById('exam-timer');
const timeDisplay = document.getElementById('time-display');
const autoSaveIndicator = document.getElementById('auto-save-indicator');

function updateTimer() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    
    timeDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    
    // Update timer appearance based on remaining time
    if (timeLeft <= 300) { // 5 minutes
        timerElement.className = 'timer danger';
    } else if (timeLeft <= 600) { // 10 minutes
        timerElement.className = 'timer warning';
    } else {
        timerElement.className = 'timer';
    }
    
    if (timeLeft <= 0) {
        alert('¡Tiempo agotado! El examen se enviará automáticamente.');
        // Reload page to trigger server-side time check
        window.location.reload();
        return;
    }
    
    timeLeft--;
}

// Start timer
setInterval(updateTimer, 1000);
updateTimer(); // Initial call

// Auto-save functionality
function saveAnswer(questionNum, answer) {
    showAutoSaveIndicator('saving');
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `ajax_save_answer=1&exam_id=${examId}&question_num=${questionNum}&answer=${answer}&csrf_token=${csrfToken}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAutoSaveIndicator('saved');
        } else {
            console.error('Error saving answer:', data.error);
        }
    })
    .catch(error => {
        console.error('Network error:', error);
    });
}

function showAutoSaveIndicator(status) {
    if (status === 'saving') {
        autoSaveIndicator.textContent = 'Guardando...';
        autoSaveIndicator.className = 'auto-save-indicator saving';
    } else if (status === 'saved') {
        autoSaveIndicator.textContent = 'Guardado ✓';
        autoSaveIndicator.className = 'auto-save-indicator saved';
    }
    
    autoSaveIndicator.style.display = 'block';
    
    setTimeout(() => {
        autoSaveIndicator.style.display = 'none';
    }, 2000);
}

// Answer selection with auto-save
function selectAnswer(option) {
    document.querySelectorAll('.answer-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('option_' + option).checked = true;
    document.getElementById('option_' + option).closest('.answer-option').classList.add('selected');
    
    // Auto-save the answer
    saveAnswer(currentQuestionNum, option);
}

// Navigate to specific question
function navigateToQuestion(questionNum) {
    // Save current answer if selected
    const selectedAnswer = document.querySelector('input[name="answer"]:checked');
    if (selectedAnswer) {
        saveAnswer(currentQuestionNum, selectedAnswer.value);
    }
    
    // Navigate after short delay to allow save
    setTimeout(() => {
        window.location.href = `?exam_id=${examId}&q=${questionNum}`;
    }, 300);
}

// Finish exam
function finishExam() {
    const answeredCount = document.querySelectorAll('.btn-success').length - 
                         (document.querySelector('.btn-primary') ? 1 : 0); // Subtract current question if it's the primary button
    
    if (answeredCount < <?php echo $totalQuestions; ?>) {
        if (!confirm(`Has respondido ${answeredCount} de <?php echo $totalQuestions; ?> preguntas. ¿Estás seguro de que quieres finalizar el examen?`)) {
            return;
        }
    }
    
    if (confirm('¿Estás seguro de que quieres finalizar y enviar el examen? Esta acción no se puede deshacer.')) {
        // Save current answer if any
        const selectedAnswer = document.querySelector('input[name="answer"]:checked');
        if (selectedAnswer) {
            saveAnswer(currentQuestionNum, selectedAnswer.value);
        }
        
        // Submit exam after short delay
        setTimeout(() => {
            const form = document.getElementById('question-form');
            const submitBtn = document.createElement('input');
            submitBtn.type = 'hidden';
            submitBtn.name = 'submit_exam';
            submitBtn.value = '1';
            form.appendChild(submitBtn);
            form.submit();
        }, 500);
    }
}

// Prevent accidental page leave
window.addEventListener('beforeunload', function(e) {
    e.preventDefault();
    e.returnValue = 'Si sales de esta página, podrías perder tu progreso. ¿Estás seguro?';
    return e.returnValue;
});

// Prevent right-click and common shortcuts (optional security measure)
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

document.addEventListener('keydown', function(e) {
    // Disable F12, Ctrl+Shift+I, Ctrl+U, etc. (optional)
    if (e.key === 'F12' || 
        (e.ctrlKey && e.shiftKey && e.key === 'I') ||
        (e.ctrlKey && e.key === 'u')) {
        e.preventDefault();
    }
});

// Heartbeat to check connection and sync time periodically
setInterval(() => {
    fetch('?exam_id=' + examId, {
        method: 'HEAD'
    }).catch(() => {
        console.warn('Connection lost - timer may be inaccurate');
    });
}, 30000); // Every 30 seconds

</script>
<?php endif; ?>

</body>
</html>