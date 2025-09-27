<?php

// eliminar_pregunta.php
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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['question_id']) && isset($_POST['exam_id'])) {
    $questionId = (int)$_POST['question_id'];
    $examId = (int)$_POST['exam_id'];

    // Verify the question exists and user has permission to delete it
    if ($userRole === 'admin') {
        // Admin can delete any question
        $stmt = $conn->prepare("SELECT p.id FROM preguntas p 
                               JOIN examenes e ON p.examen_id = e.id 
                               WHERE p.id = ? AND p.examen_id = ?");
        $stmt->bind_param("ii", $questionId, $examId);
    } else {
        // Regular users can only delete questions from their own exams
        $stmt = $conn->prepare("SELECT p.id FROM preguntas p 
                               JOIN examenes e ON p.examen_id = e.id 
                               WHERE p.id = ? AND p.examen_id = ? AND e.admin_id = ?");
        $stmt->bind_param("iii", $questionId, $examId, $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Delete the question
        $deleteStmt = $conn->prepare("DELETE FROM preguntas WHERE id = ?");
        $deleteStmt->bind_param("i", $questionId);

        if ($deleteStmt->execute()) {
            header("Location: crear_examen.php?exam_id=$examId&success=3");
        } else {
            header("Location: crear_examen.php?exam_id=$examId&error=1");
        }
    } else {
        header("Location: crear_examen.php?exam_id=$examId&error=2");
    }
} else {
    header("Location: crear_examen.php");
}

$conn->close();
exit;
