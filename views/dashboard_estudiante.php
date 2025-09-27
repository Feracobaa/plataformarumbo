<?php
// dashboard_estudiante.php
// Dashboard específico para estudiantes con estilos mejorados

// Incluir archivo común
require_once 'dashboard_common.php';

// Verificar que el usuario sea estudiante
if ($userRole !== 'estudiante') {
    redirectToDashboard($userRole);
}

// Get completed exams count for student
function getCompletedExamsCount($studentId) {
    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM resultados WHERE estudiante_id = ?");
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $conn->close();
        
        return $row ? (int)$row['count'] : 0;
    } catch (Exception $e) {
        error_log("Error getting completed exams count: " . $e->getMessage());
        return 0;
    }
}

// Get available exams for student (excluding completed ones)
function getAvailableExamsCount($studentId) {
    try {
        $conn = getDbConnection();
        
        // Debug: First let's see what we have
        error_log("DEBUG: Getting available exams for student ID: " . $studentId);
        
        // Usar la misma lógica que available_exams.php
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM examenes e
            JOIN usuarios u ON e.admin_id = u.id
            WHERE e.id NOT IN (
                SELECT COALESCE(examen_id, 0) FROM resultados WHERE estudiante_id = ?
            )
        ");
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $count = $row ? (int)$row['count'] : 0;
        
        // Debug log
        error_log("DEBUG: Available exams count for student $studentId: " . $count);
        
        $conn->close();
        return $count;
        
    } catch (Exception $e) {
        error_log("Error getting available exams count: " . $e->getMessage());
        return 0;
    }
}

// Get total exams count (for percentage calculation)
function getTotalExamsCount() {
    try {
        $conn = getDbConnection();
        
        // Usar la misma lógica que available_exams.php para consistencia
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM examenes e
            JOIN usuarios u ON e.admin_id = u.id
        ");
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $count = $row ? (int)$row['count'] : 0;
        
        // Debug log
        error_log("DEBUG: Total exams count: " . $count);
        
        $conn->close();
        return $count;
        
    } catch (Exception $e) {
        error_log("Error getting total exams count: " . $e->getMessage());
        return 0;
    }
}

// Get student data with error handling
try {
    $formattedDate = getUserData($userId);
    $completedExamsCount = getCompletedExamsCount($userId);
    $availableExamsCount = getAvailableExamsCount($userId);
    $totalExamsCount = getTotalExamsCount();
} catch (Exception $e) {
    // Set default values if there's an error
    $formattedDate = date('d/m/Y H:i');
    $completedExamsCount = 0;
    $availableExamsCount = 0;
    $totalExamsCount = 0;
    error_log("Dashboard error: " . $e->getMessage());
}

// Display header
includeHeader('Dashboard Estudiante');
?>

<!-- Agregar el CSS personalizado -->
<link href="dashboard_styles_estudiante.css" rel="stylesheet">

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-speedometer2 me-2"></i>
        Estudiante 
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-calendar3"></i>
                <?php echo date('d/m/Y'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Mensaje de bienvenida -->
<div class="welcome-banner mb-4">
    <div class="alert alert-primary border-0" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); border-left: 4px solid var(--primary-color) !important;">
        <h4 class="alert-heading">
            <i class="bi bi-emoji-smile me-2"></i>
            ¡Bienvenido de vuelta, <?php echo htmlspecialchars($userName); ?>!
        </h4>
        <p class="mb-0">Aquí tienes un resumen de tu progreso académico. ¡Sigue así!</p>
    </div>
</div>

<div class="row">
    <!-- Card de información del usuario -->
    <div class="col-md-4 mb-4">
        <div class="card card-info">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-person-circle me-2"></i>
                    Mi Información
                </h5>
                <div class="user-info">
                    <p class="card-text">
                        <i class="bi bi-person me-2"></i>
                        <strong>Nombre:</strong> <?php echo htmlspecialchars($userName); ?>
                    </p>
                    <p class="card-text">
                        <i class="bi bi-envelope me-2"></i>
                        <strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?>
                    </p>
                    <p class="card-text">
                        <i class="bi bi-shield-check me-2"></i>
                        <strong>Rol:</strong> 
                        <span class="badge bg-primary"><?php echo htmlspecialchars($userRole); ?></span>
                    </p>
                    <p class="card-text">
                        <i class="bi bi-calendar-plus me-2"></i>
                        <strong>Miembro desde:</strong> <?php echo $formattedDate; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card de exámenes -->
    <div class="col-md-4 mb-4">
        <div class="card card-exams">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-file-text me-2"></i>
                    Mis Exámenes
                </h5>
                <div class="exam-stats">
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Exámenes completados:</span>
                            <span class="stat-number"><?php echo $completedExamsCount; ?></span>
                        </div>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $totalExamsCount > 0 ? min(($completedExamsCount / $totalExamsCount) * 100, 100) : 0; ?>%; background: var(--success-gradient);">
                            </div>
                        </div>
                    </div>
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Exámenes disponibles:</span>
                            <span class="stat-number text-info"><?php echo $availableExamsCount; ?></span>
                        </div>
                    </div>
                    <div class="stat-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Total de exámenes:</span>
                            <span class="small text-muted"><?php echo $totalExamsCount; ?></span>
                        </div>
                    </div>
                </div>
                <a href="available_exams.php" class="btn btn-primary w-100">
                    <i class="bi bi-play-circle me-2"></i>
                    Ver Exámenes Disponibles
                    <?php if ($availableExamsCount > 0): ?>
                        <span class="badge bg-light text-primary ms-2"><?php echo $availableExamsCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- Card de resultados -->
    <div class="col-md-4 mb-4">
        <div class="card card-results">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="bi bi-trophy me-2"></i>
                    Mis Resultados
                </h5>
                <div class="results-preview">
                    <p class="card-text">Revisa tu progreso académico y calificaciones obtenidas.</p>
                    
                    <?php if ($completedExamsCount > 0): ?>
                        <div class="achievement-badge mb-3">
                            <div class="d-flex align-items-center">
                                <div class="badge-icon me-3">
                                    <i class="bi bi-award text-warning" style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Tu progreso</small>
                                    <div class="fw-bold">
                                        <?php 
                                        $percentage = $totalExamsCount > 0 ? min(($completedExamsCount / $totalExamsCount) * 100, 100) : 0;
                                        echo number_format($percentage, 1) . '% completado';
                                        ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $completedExamsCount; ?> de <?php echo $totalExamsCount; ?> exámenes
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-graph-up text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">¡Comienza tu primer examen!</p>
                            <?php if ($availableExamsCount > 0): ?>
                                <small class="text-info">Tienes <?php echo $availableExamsCount; ?> examen<?php echo $availableExamsCount > 1 ? 'es' : ''; ?> disponible<?php echo $availableExamsCount > 1 ? 's' : ''; ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <a href="completed_exams.php" class="btn btn-primary w-100">
                    <i class="bi bi-bar-chart me-2"></i>
                    Ver Mis Resultados
                    <?php if ($completedExamsCount > 0): ?>
                        <span class="badge bg-light text-primary ms-2"><?php echo $completedExamsCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Sección de accesos rápidos -->

<!-- Estilos adicionales específicos de la página -->
<style>
.welcome-banner {
    animation: slideInLeft 0.8s ease-out;
}

.quick-access-card {
    transition: var(--transition);
    border: 1px solid rgba(0,0,0,0.05);
}

.quick-access-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.quick-access-card .card-body {
    padding: 2rem 1rem;
}

.stat-item .progress-bar {
    border-radius: 3px;
}

.achievement-badge {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
    border-radius: 10px;
    padding: 15px;
    border: 1px solid rgba(255, 193, 7, 0.2);
}

.user-info p {
    transition: var(--transition);
}

.user-info p:hover {
    transform: translateX(5px);
    color: var(--primary-color);
}

/* Animación de entrada escalonada */
.row > div:nth-child(1) { animation-delay: 0.1s; }
.row > div:nth-child(2) { animation-delay: 0.2s; }
.row > div:nth-child(3) { animation-delay: 0.3s; }
.row > div:nth-child(4) { animation-delay: 0.4s; }
.row > div:nth-child(5) { animation-delay: 0.5s; }
.row > div:nth-child(6) { animation-delay: 0.6s; }

/* Estilos para badges en botones */
.btn .badge {
    font-size: 0.7em;
    padding: 0.25em 0.5em;
}
</style>

<?php
// Display footer
includeFooter();
?>