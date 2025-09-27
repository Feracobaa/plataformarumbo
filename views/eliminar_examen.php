<?php
// eliminar_examen.php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'profesor'])) {
    header("Location: login.php");
    exit;
}

// Database connection
$conn = new mysqli('localhost', 'root', '123456789', 'examenes_db');
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['exam_id'])) {
    $examId = (int)$_POST['exam_id'];
    
    // Verify the exam exists and user has permission to delete it
    if ($userRole === 'admin') {
        // Admin can delete any exam
        $stmt = $conn->prepare("SELECT id, titulo FROM examenes WHERE id = ?");
        $stmt->bind_param("i", $examId);
    } else {
        // Regular users can only delete their own exams
        $stmt = $conn->prepare("SELECT id, titulo FROM examenes WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("ii", $examId, $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $exam = $result->fetch_assoc();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // First delete all questions associated with the exam
            $deleteQuestionsStmt = $conn->prepare("DELETE FROM preguntas WHERE examen_id = ?");
            $deleteQuestionsStmt->bind_param("i", $examId);
            $deleteQuestionsStmt->execute();
            
            // Then delete the exam
            $deleteExamStmt = $conn->prepare("DELETE FROM examenes WHERE id = ?");
            $deleteExamStmt->bind_param("i", $examId);
            $deleteExamStmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect with success message
            header("Location: crear_examen.php?deleted=1&title=" . urlencode($exam['titulo']));
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            header("Location: crear_examen.php?error=3");
        }
    } else {
        // Exam not found or no permission
        header("Location: crear_examen.php?error=4");
    }
} else {
    header("Location: crear_examen.php");
}

$conn->close();
exit;
?>