<?php
// available_exams.php
// Start session
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'estudiante','profesor'])) {
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

// Get available exams (that the student hasn't taken yet)
$stmt = $conn->prepare("
    SELECT e.id, e.titulo, e.descripcion, u.nombre as creator, e.created_at
    FROM examenes e
    JOIN usuarios u ON e.admin_id = u.id
    WHERE e.id NOT IN (
        SELECT examen_id FROM resultados WHERE estudiante_id = ?
    )
    ORDER BY e.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$availableExams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get the count of questions for each exam
foreach ($availableExams as &$exam) {
    $stmt = $conn->prepare("SELECT COUNT(*) as question_count FROM preguntas WHERE examen_id = ?");
    $stmt->bind_param("i", $exam['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $exam['question_count'] = $result->fetch_assoc()['question_count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exámenes Disponibles</title>
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
                            <a class="nav-link active" href="available_exams.php">
                                <i class="bi bi-file-earmark-text"></i> Exámenes Disponibles
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link" href="completed_exams.php">
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
                    <h1 class="h2">Exámenes Disponibles</h1>
                </div>
                
                <?php if (empty($availableExams)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No hay exámenes disponibles para ti en este momento. Vuelve más tarde.
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($availableExams as $exam): ?>
                            <?php 
                            // Check if exam is new (less than 3 days old)
                            $examDate = new DateTime($exam['created_at']);
                            $now = new DateTime();
                            $isNew = $examDate->diff($now)->days < 3;
                            ?>
                            <div class="col">
                                <div class="card exam-card <?php echo $isNew ? 'new-exam' : ''; ?>">
                                    <?php if($isNew): ?>
                                        <span class="badge bg-success badge-corner">Nuevo</span>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($exam['titulo']); ?></h5>
                                        <p class="card-text"><?php echo htmlspecialchars($exam['descripcion']); ?></p>
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-primary">
                                                <i class="bi bi-question-circle"></i> <?php echo $exam['question_count']; ?> preguntas
                                            </span>
                                            <span class="text-muted small">
                                                <i class="bi bi-calendar-date"></i> <?php echo date('d/m/Y', strtotime($exam['created_at'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <p class="card-text small text-muted mb-2">Creado por: <?php echo htmlspecialchars($exam['creator']); ?></p>
                                        <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" class="btn btn-primary w-100">
                                            <i class="bi bi-pencil-square"></i> Realizar Examen
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>