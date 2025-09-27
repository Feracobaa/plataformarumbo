
<?php
// crear_examen.php
/**
 * crear_examen.php
 *
 * Página para la creación y edición de exámenes y preguntas en la plataforma.
 * Permite a usuarios con rol 'admin' o 'profesor' crear nuevos exámenes, agregar preguntas,
 * editar preguntas existentes y visualizar la lista de exámenes creados.
 *
 * Funcionalidades principales:
 * - Verificación de autenticación y permisos de usuario.
 * - Creación de exámenes con título y descripción.
 * - Agregado, edición y eliminación de preguntas para un examen específico.
 * - Visualización de preguntas existentes para cada examen.
 * - Listado de todos los exámenes (para admin) o exámenes propios (para profesor), con conteo de preguntas.
 * - Mensajes de éxito y error para las operaciones realizadas.
 * - Formularios validados tanto en frontend (JavaScript) como en backend (PHP).
 * - Uso de Bootstrap para la interfaz y experiencia de usuario.
 *
 * Variables principales:
 * - $conn: Conexión a la base de datos.
 * - $userId, $userRole: Identificadores y rol del usuario autenticado.
 * - $examId: ID del examen en edición o creación de preguntas.
 * - $editingQuestionId: ID de la pregunta en edición.
 * - $examDetails: Detalles del examen actual.
 * - $editingQuestion: Detalles de la pregunta en edición.
 * - $message: Mensajes de alerta para mostrar al usuario.
 *
 * Seguridad:
 * - Validación de permisos para edición/eliminación de exámenes y preguntas.
 * - Uso de sentencias preparadas para prevenir SQL Injection.
 * - Escape de salidas para prevenir XSS.
 *
 * Requiere:
 * - Bootstrap 5 y Bootstrap Icons para el frontend.
 * - Archivos parciales 'header.php' y 'footer.php' para la estructura de la página.
 * - Archivos auxiliares para eliminación de preguntas y exámenes.
 *
 * @author  Plataforma Rumbo al Saber
 * @version 1.0
 * @package Examenes
 */
session_start();
// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = null;
function getDbConnection()
{
    global $conn;
    if ($conn === null) {
        $conn = new mysqli('localhost', 'root', '123456789', 'examenes_db');
        if ($conn->connect_error) {
            die("Error de conexión: " . $conn->connect_error);
        }
        $conn->set_charset("utf8mb4");
    }
    return $conn;
}
$conn = getDbConnection();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Helper function for alerts
function displayAlert($message, $type = 'info')
{
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>";
}

// Initialize variables
$message = '';
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : null;
$editingQuestionId = isset($_GET['edit_question']) ? (int)$_GET['edit_question'] : null;
$examDetails = null;
$editingQuestion = null;

// Create exam
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_exam'])) {
    $titulo = htmlspecialchars(trim($_POST['titulo']));
    $descripcion = htmlspecialchars(trim($_POST['descripcion']));

    if (empty($titulo) || empty($descripcion)) {
        $message = displayAlert('Por favor, complete todos los campos obligatorios.', 'danger');
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO examenes (titulo, descripcion, admin_id, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("ssi", $titulo, $descripcion, $userId);

            if ($stmt->execute()) {
                $examId = $conn->insert_id;
                $conn->commit();
                header("Location: crear_examen.php?exam_id=$examId");
                exit;
            } else {
                throw new Exception("Error al crear el examen: " . $stmt->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = displayAlert($e->getMessage(), 'danger');
        }
    }
}

// Add question
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_question'])) {
    $examId = (int)$_POST['exam_id'];
    $enunciado = htmlspecialchars(trim($_POST['enunciado']));
    $opciones = [
        'A' => htmlspecialchars(trim($_POST['opcion_a'])),
        'B' => htmlspecialchars(trim($_POST['opcion_b'])),
        'C' => htmlspecialchars(trim($_POST['opcion_c'])),
        'D' => htmlspecialchars(trim($_POST['opcion_d']))
    ];
    $respuestaCorrecta = $_POST['respuesta_correcta'];

    if (empty($enunciado) || in_array('', $opciones, true)) {
        $message = displayAlert('Por favor, complete todos los campos de la pregunta.', 'danger');
    } else {
        // Verify exam exists and user has permission (admin can edit all exams)
        $whereClause = $userRole === 'admin' ? "WHERE id = ?" : "WHERE id = ? AND admin_id = ?";
        $stmt = $conn->prepare("SELECT id FROM examenes $whereClause");

        if ($userRole === 'admin') {
            $stmt->bind_param("i", $examId);
        } else {
            $stmt->bind_param("ii", $examId, $userId);
        }

        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $message = displayAlert('No tiene permisos para modificar este examen.', 'danger');
        } else {
            // Add question
            $stmt = $conn->prepare("INSERT INTO preguntas (examen_id, enunciado, opcion_a, opcion_b, opcion_c, opcion_d, respuesta_correcta, created_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("issssss", $examId, $enunciado, $opciones['A'], $opciones['B'], $opciones['C'], $opciones['D'], $respuestaCorrecta);

            if ($stmt->execute()) {
                header("Location: crear_examen.php?exam_id=$examId&success=1");
                exit;
            } else {
                $message = displayAlert('Error al agregar la pregunta: ' . $stmt->error, 'danger');
            }
        }
    }
}

// Update question
// Update question
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_question'])) {
    $questionId = (int)$_POST['question_id'];
    $examId = (int)$_POST['exam_id'];
    $enunciado = trim($_POST['enunciado']);
    $opciones = [
        'A' => trim($_POST['opcion_a']),
        'B' => trim($_POST['opcion_b']),
        'C' => trim($_POST['opcion_c']),
        'D' => trim($_POST['opcion_d'])
    ];
    $respuestaCorrecta = $_POST['respuesta_correcta'];

    // Enhanced validation
    $errors = [];
    if (empty($enunciado)) {
        $errors[] = "El enunciado es requerido";
    }
    foreach ($opciones as $key => $opcion) {
        if (empty($opcion)) {
            $errors[] = "La opción $key es requerida";
        }
    }
    if (empty($respuestaCorrecta)) {
        $errors[] = "Debe seleccionar la respuesta correcta";
    }

    if (empty($errors)) {
        // Simplified permission check
        $permissionQuery = $userRole === 'admin' ?
            "SELECT p.id FROM preguntas p JOIN examenes e ON p.examen_id = e.id WHERE p.id = ? AND p.examen_id = ?" :
            "SELECT p.id FROM preguntas p JOIN examenes e ON p.examen_id = e.id WHERE p.id = ? AND p.examen_id = ? AND e.admin_id = ?";

        $stmt = $conn->prepare($permissionQuery);
        if ($userRole === 'admin') {
            $stmt->bind_param("ii", $questionId, $examId);
        } else {
            $stmt->bind_param("iii", $questionId, $examId, $userId);
        }

        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $message = displayAlert('No tiene permisos para modificar esta pregunta.', 'danger');
        } else {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE preguntas SET 
                                       enunciado = ?, opcion_a = ?, opcion_b = ?, opcion_c = ?, 
                                       opcion_d = ?, respuesta_correcta = ?, updated_at = NOW()
                                       WHERE id = ?");
                $stmt->bind_param(
                    "ssssssi",
                    $enunciado,
                    $opciones['A'],
                    $opciones['B'],
                    $opciones['C'],
                    $opciones['D'],
                    $respuestaCorrecta,
                    $questionId
                );

                if ($stmt->execute()) {
                    $conn->commit();
                    header("Location: crear_examen.php?exam_id=$examId&success=2");
                    exit;
                } else {
                    throw new Exception("Error al actualizar la pregunta: " . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = displayAlert($e->getMessage(), 'danger');
            }
        }
    } else {
        $message = displayAlert(implode(", ", $errors), 'danger');
    }
}

// Success and error messages after redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] == 1) {
        $message = displayAlert('Pregunta agregada correctamente.', 'success');
    } elseif ($_GET['success'] == 2) {
        $message = displayAlert('Pregunta actualizada correctamente.', 'success');
    } elseif ($_GET['success'] == 3) {
        $message = displayAlert('Pregunta eliminada correctamente.', 'success');
    }
}

if (isset($_GET['error'])) {
    if ($_GET['error'] == 1) {
        $message = displayAlert('Error al eliminar la pregunta.', 'danger');
    } elseif ($_GET['error'] == 2) {
        $message = displayAlert('No tiene permisos para eliminar esta pregunta.', 'danger');
    } elseif ($_GET['error'] == 3) {
        $message = displayAlert('Error al eliminar el examen.', 'danger');
    } elseif ($_GET['error'] == 4) {
        $message = displayAlert('No tiene permisos para eliminar este examen.', 'danger');
    }
}

if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $deletedTitle = isset($_GET['title']) ? htmlspecialchars(urldecode($_GET['title'])) : 'el examen';
    $message = displayAlert("El examen \"$deletedTitle\" ha sido eliminado correctamente.", 'success');
}

// Get exam details
if ($examId) {
    $whereClause = $userRole === 'admin' ? "WHERE id = ?" : "WHERE id = ? AND admin_id = ?";
    $stmt = $conn->prepare("SELECT titulo, descripcion, admin_id FROM examenes $whereClause");

    if ($userRole === 'admin') {
        $stmt->bind_param("i", $examId);
    } else {
        $stmt->bind_param("ii", $examId, $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $examDetails = $result->fetch_assoc();
    } else {
        $examId = null;
        $message = displayAlert('El examen solicitado no existe o no tiene permisos para editarlo.', 'warning');
    }
}

// Get question to edit
if ($editingQuestionId && $examId) {
    $whereClause = $userRole === 'admin' ?
        "p.id = ? AND p.examen_id = ?" :
        "p.id = ? AND p.examen_id = ? AND e.admin_id = ?";

    $stmt = $conn->prepare("SELECT p.* FROM preguntas p 
                           JOIN examenes e ON p.examen_id = e.id 
                           WHERE $whereClause");

    if ($userRole === 'admin') {
        $stmt->bind_param("ii", $editingQuestionId, $examId);
    } else {
        $stmt->bind_param("iii", $editingQuestionId, $examId, $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $editingQuestion = $result->fetch_assoc();
    } else {
        $editingQuestionId = null;
        $message = displayAlert('La pregunta solicitada no existe o no tiene permisos para editarla.', 'warning');
    }
}

// Get existing exams with question count
$whereClause = $userRole === 'admin' ? "" : "WHERE e.admin_id = ?";
$stmt = $conn->prepare("SELECT e.id, e.titulo, e.descripcion, e.created_at, e.admin_id, COUNT(p.id) as num_preguntas,
                       u.nombre as creator_name
                       FROM examenes e 
                       LEFT JOIN preguntas p ON e.id = p.examen_id 
                       LEFT JOIN usuarios u ON e.admin_id = u.id
                       $whereClause
                       GROUP BY e.id 
                       ORDER BY e.created_at DESC");

if ($userRole === 'admin') {
    $stmt->execute();
} else {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
}

$examsResult = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Examen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="exam-system.css">
</head>
<body>
    <?php include 'views/partials/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky sidebar-sticky">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-house-door"></i> Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="available_exams.php"><i class="bi bi-file-text"></i> Exámenes Disponibles</a></li>
                        <li class="nav-item"><a class="nav-link active" href="crear_examen.php"><i class="bi bi-plus-circle"></i> Crear Examen</a></li>
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="bi bi-people"></i> Usuarios</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link" href="perfil.php"><i class="bi bi-person"></i> Mi Perfil</a></li>
                        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <?php if ($editingQuestionId): ?>
                            Editar Pregunta
                        <?php elseif ($examId): ?>
                            Editar Examen: <?= htmlspecialchars($examDetails['titulo']) ?>
                        <?php else: ?>
                            Crear Examen
                        <?php endif; ?>
                    </h1>
                    <?php if ($examId): ?>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="crear_examen.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Crear Nuevo Examen
                        </a>
                        <?php if ($editingQuestionId): ?>
                        <a href="crear_examen.php?exam_id=<?= $examId ?>" class="btn btn-sm btn-secondary ms-2">
                            <i class="bi bi-arrow-left"></i> Volver al Examen
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?= $message; ?>
                
                <!-- Create Exam Form -->
                <?php if (!$examId): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">Nuevo Examen</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-3">
                                <label for="titulo" class="form-label form-required">Título del Examen</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100">
                                <div class="form-text">Máximo 100 caracteres</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label form-required">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required maxlength="500"></textarea>
                                <div class="form-text">Describa el propósito y contenido del examen. Máximo 500 caracteres.</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="create_exam" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Crear Examen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php elseif ($editingQuestionId && $editingQuestion): ?>
                <!-- Edit Question Form -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h3 class="mb-0">Editar Pregunta</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="questionForm">
                            <input type="hidden" name="exam_id" value="<?= $examId ?>">
                            <input type="hidden" name="question_id" value="<?= $editingQuestionId ?>">
                            
                            <div class="mb-3">
                                <label for="enunciado" class="form-label form-required">Enunciado de la Pregunta</label>
                                <textarea class="form-control" id="enunciado" name="enunciado" rows="2" required maxlength="500"><?= htmlspecialchars($editingQuestion['enunciado']) ?></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="opcion_a" class="form-label form-required">Opción A</label>
                                    <input type="text" class="form-control" id="opcion_a" name="opcion_a" required maxlength="250" value="<?= htmlspecialchars($editingQuestion['opcion_a']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="opcion_b" class="form-label form-required">Opción B</label>
                                    <input type="text" class="form-control" id="opcion_b" name="opcion_b" required maxlength="250" value="<?= htmlspecialchars($editingQuestion['opcion_b']) ?>">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="opcion_c" class="form-label form-required">Opción C</label>
                                    <input type="text" class="form-control" id="opcion_c" name="opcion_c" required maxlength="250" value="<?= htmlspecialchars($editingQuestion['opcion_c']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="opcion_d" class="form-label form-required">Opción D</label>
                                    <input type="text" class="form-control" id="opcion_d" name="opcion_d" required maxlength="250" value="<?= htmlspecialchars($editingQuestion['opcion_d']) ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="respuesta_correcta" class="form-label form-required">Respuesta Correcta</label>
                                <select class="form-select" id="respuesta_correcta" name="respuesta_correcta" required>
                                    <option value="" disabled>Seleccione la respuesta correcta</option>
                                    <option value="A" <?= $editingQuestion['respuesta_correcta'] === 'A' ? 'selected' : '' ?>>A</option>
                                    <option value="B" <?= $editingQuestion['respuesta_correcta'] === 'B' ? 'selected' : '' ?>>B</option>
                                    <option value="C" <?= $editingQuestion['respuesta_correcta'] === 'C' ? 'selected' : '' ?>>C</option>
                                    <option value="D" <?= $editingQuestion['respuesta_correcta'] === 'D' ? 'selected' : '' ?>>D</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_question" class="btn btn-warning">
                                    <i class="bi bi-save"></i> Actualizar Pregunta
                                </button>
                                <a href="crear_examen.php?exam_id=<?= $examId ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php else: ?>
                <!-- Add Questions Form -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">Agregar Preguntas al Examen</h3>
                    </div>
                    <div class="card-body">
                        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="questionForm">
                            <input type="hidden" name="exam_id" value="<?= $examId; ?>">
                            
                            <div class="mb-3">
                                <label for="enunciado" class="form-label form-required">Enunciado de la Pregunta</label>
                                <textarea class="form-control" id="enunciado" name="enunciado" rows="2" required maxlength="500"></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="opcion_a" class="form-label form-required">Opción A</label>
                                    <input type="text" class="form-control" id="opcion_a" name="opcion_a" required maxlength="250">
                                </div>
                                <div class="col-md-6">
                                    <label for="opcion_b" class="form-label form-required">Opción B</label>
                                    <input type="text" class="form-control" id="opcion_b" name="opcion_b" required maxlength="250">
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="opcion_c" class="form-label form-required">Opción C</label>
                                    <input type="text" class="form-control" id="opcion_c" name="opcion_c" required maxlength="250">
                                </div>
                                <div class="col-md-6">
                                    <label for="opcion_d" class="form-label form-required">Opción D</label>
                                    <input type="text" class="form-control" id="opcion_d" name="opcion_d" required maxlength="250">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="respuesta_correcta" class="form-label form-required">Respuesta Correcta</label>
                                <select class="form-select" id="respuesta_correcta" name="respuesta_correcta" required>
                                    <option value="" selected disabled>Seleccione la respuesta correcta</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="add_question" class="btn btn-success">
                                    <i class="bi bi-plus-circle"></i> Agregar Pregunta
                                </button>
                                <a href="crear_examen.php" class="btn btn-outline-primary">
                                    <i class="bi bi-plus"></i> Finalizar y Crear Nuevo Examen
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Existing Questions for Current Exam -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Preguntas del Examen Actual</h3>
                        <small class="text-white-50">
                            <?php if ($userRole === 'admin' && $examDetails['admin_id'] != $userId): ?>
                                <i class="bi bi-shield-check"></i> Editando como Administrador
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmtQuestions = $conn->prepare("SELECT * FROM preguntas WHERE examen_id = ? ORDER BY id");
$stmtQuestions->bind_param("i", $examId);
$stmtQuestions->execute();
$questionsResult = $stmtQuestions->get_result();

if ($questionsResult->num_rows > 0): ?>
                            <div class="row row-cols-1 row-cols-md-2 g-4">
                            <?php $questionNumber = 1;
    while ($q = $questionsResult->fetch_assoc()): ?>
                                <div class="col">
                                    <div class="card h-100 question-preview">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <span>Pregunta <?= $questionNumber++ ?></span>
                                            <div class="btn-group">
                                                <a href="crear_examen.php?exam_id=<?= $examId ?>&edit_question=<?= $q['id'] ?>" class="btn btn-sm btn-warning" title="Editar pregunta">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form method="post" action="eliminar_pregunta.php" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar esta pregunta?');">
                                                    <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                    <input type="hidden" name="exam_id" value="<?= $examId ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar pregunta">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($q['enunciado']) ?></h5>
                                            <ul class="list-group mt-3">
                                                <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                                <li class="list-group-item<?= $q['respuesta_correcta'] === $option ? ' list-group-item-success' : '' ?>">
                                                    <strong><?= $option ?>:</strong> <?= htmlspecialchars($q["opcion_" . strtolower($option)]) ?>
                                                    <?= $q['respuesta_correcta'] === $option ? ' <i class="bi bi-check-circle-fill text-success"></i>' : '' ?>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div class="card-footer text-muted">
                                            <small>Añadida: <?= date('d/m/Y H:i', strtotime($q['created_at'])) ?></small>
                                            <?php if (isset($q['updated_at']) && $q['updated_at']): ?>
                                            <br><small>Modificada: <?= date('d/m/Y H:i', strtotime($q['updated_at'])) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Este examen aún no tiene preguntas. Usa el formulario superior para añadir preguntas.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- List of Existing Exams -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h3 class="mb-0">
                            <?= $userRole === 'admin' ? 'Todos los Exámenes' : 'Mis Exámenes Creados' ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($examsResult->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Descripción</th>
                                        <?php if ($userRole === 'admin'): ?>
                                        <th>Creador</th>
                                        <?php endif; ?>
                                        <th>Preguntas</th>
                                        <th>Fecha</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $examsResult->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td>
                                            <?= htmlspecialchars($row['titulo']) ?>
                                            <?php if ($userRole === 'admin' && $row['admin_id'] != $userId): ?>
                                                <small class="text-muted"><br><i class="bi bi-shield-check"></i> Admin access</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(substr($row['descripcion'], 0, 50)) . (strlen($row['descripcion']) > 50 ? '...' : '') ?></td>
                                        <?php if ($userRole === 'admin'): ?>
                                        <td><?= htmlspecialchars($row['creator_name']) ?></td>
                                        <?php endif; ?>
                                        <td><span class="badge bg-<?= $row['num_preguntas'] > 0 ? 'success' : 'warning' ?>"><?= $row['num_preguntas'] ?></span></td>
                                        <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="crear_examen.php?exam_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Editar
                                                </a>
                                                <a href="vista_previa_examen.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> Vista
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteExamModal<?= $row['id'] ?>">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="deleteExamModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Confirmar Eliminación</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>¿Está seguro que desea eliminar el examen <strong>"<?= htmlspecialchars($row['titulo']) ?>"</strong>?</p>
                                                    <p class="text-danger"><strong>Advertencia:</strong> Esta acción eliminará todas las preguntas asociadas y no se puede deshacer.</p>
                                                    <?php if ($userRole === 'admin' && $row['admin_id'] != $userId): ?>
                                                    <p class="text-warning"><strong>Nota:</strong> Este examen fue creado por <?= htmlspecialchars($row['creator_name']) ?>.</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <form action="eliminar_examen.php" method="post">
                                                        <input type="hidden" name="exam_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" class="btn btn-danger">Eliminar Examen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <?= $userRole === 'admin' ? 'No hay exámenes en el sistema.' : 'No has creado ningún examen todavía.' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form validation
        const questionForm = document.getElementById('questionForm');
        if (questionForm) {
            questionForm.addEventListener('submit', function(event) {
                const fields = ['enunciado', 'opcion_a', 'opcion_b', 'opcion_c', 'opcion_d', 'respuesta_correcta'];
                let valid = true;
                
                fields.forEach(field => {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        valid = false;
                        element.classList.add('is-invalid');
                    } else {
                        element.classList.remove('is-invalid');
                    }
                });
                
                if (!valid) {
                    event.preventDefault();
                    alert('Por favor, complete todos los campos requeridos.');
                }
            });
        }
        
        // Character counter for textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            const maxLength = textarea.getAttribute('maxlength');
            if (maxLength) {
                const counterDiv = document.createElement('div');
                counterDiv.className = 'form-text text-end';
                counterDiv.innerHTML = `${textarea.value.length}/${maxLength}`;
                textarea.parentNode.appendChild(counterDiv);
                
                textarea.addEventListener('input', function() {
                    const currentLength = this.value.length;
                    counterDiv.innerHTML = `${currentLength}/${maxLength}`;
                    counterDiv.classList.toggle('text-danger', currentLength >= maxLength);
                });
            }
        });
        
        // Real-time validation feedback
        document.querySelectorAll('input[required], textarea[required], select[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
            
            field.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
        
        // Highlight correct answer in question preview
        document.querySelectorAll('.question-preview').forEach(card => {
            const correctItems = card.querySelectorAll('.list-group-item-success');
            correctItems.forEach(item => {
                item.style.fontWeight = 'bold';
            });
        });
        
        // Auto-save draft functionality (optional enhancement)
        let autoSaveTimeout;
        function autoSaveDraft() {
            if (questionForm) {
                const formData = new FormData(questionForm);
                const draftData = {};
                
                for (let [key, value] of formData.entries()) {
                    if (key !== 'add_question' && key !== 'update_question') {
                        draftData[key] = value;
                    }
                }
                
                // Store in sessionStorage (you might want to use a server endpoint instead)
                sessionStorage.setItem('questionDraft', JSON.stringify(draftData));
            }
        }
        
        // Load draft on page load
        function loadDraft() {
            const draft = sessionStorage.getItem('questionDraft');
            if (draft && questionForm) {
                const draftData = JSON.parse(draft);
                
                Object.keys(draftData).forEach(key => {
                    const field = document.getElementById(key);
                    if (field && !field.value) {
                        field.value = draftData[key];
                    }
                });
            }
        }
        
        // Set up auto-save for form fields
        if (questionForm) {
            questionForm.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('input', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(autoSaveDraft, 2000); // Auto-save after 2 seconds of inactivity
                });
            });
            
            // Load draft when page loads
            loadDraft();
            
            // Clear draft when form is successfully submitted
            questionForm.addEventListener('submit', function() {
                sessionStorage.removeItem('questionDraft');
            });
        }
    });
    </script>
</body>
</html>