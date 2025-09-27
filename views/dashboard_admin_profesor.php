<?php
// dashboard_admin_profesor.php
// Dashboard para administradores y profesores

// Incluir archivo común
require_once 'dashboard_common.php';

// Verificar que el usuario sea admin o profesor
if ($userRole !== 'admin' && $userRole !== 'profesor') {
    redirectToDashboard($userRole);
}

// Get exam count for admin or profesor
function getExamCount($userId) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM examenes WHERE admin_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    
    return $row['count'];
}

// Get student count for admin or profesor
function getStudentCount() {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios WHERE role = 'estudiante'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $conn->close();
    
    return $row['count'];
}

// Get user data
$formattedDate = getUserData($userId);
$examCount = getExamCount($userId);
$studentCount = getStudentCount();

// Display header with custom CSS
$dashboardTitle = ($userRole === 'admin') ? 'Dashboard Administrador' : 'Dashboard Profesor';

// Comenzamos el documento HTML y agregamos nuestros estilos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $dashboardTitle; ?></title>
    
    
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="dashboard-styles.css">
    
</head>
<body>
    
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="#">Sistema de Exámenes</a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item active">
                <a class="nav-link" href="dashboard_admin_profesor.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="available_exams.php"><i class="fas fa-book"></i> Exámenes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="completed_exams.php"><i class="fas fa-book"></i> Exámenes completados</a>
                </li>
                <?php if ($userRole === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i> Usuarios</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Perfil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenido principal -->
<div class="container mt-4">
    <!-- Título del Dashboard -->
    <div class="dashboard-header">
        <h1 class="dashboard-title"><?php echo $dashboardTitle; ?></h1>
    </div>

    <!-- Mensaje de bienvenida -->
    

    <div class="row">
        <!-- Tarjeta de información del usuario -->
        <div class="col-md-4">
            <div class="card user-info-card">
                <div class="card-body">
                    <div class="user-info-container">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($userName, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <h3><?php echo htmlspecialchars($userName); ?></h3>
                            <span class="user-role"><?php echo strtoupper(htmlspecialchars($userRole)); ?></span>
                            <span class="user-email"><i class="far fa-envelope mr-1"></i> <?php echo htmlspecialchars($userEmail); ?></span>
                            <span class="user-since"><i class="far fa-clock mr-1"></i> Miembro desde <?php echo $formattedDate; ?></span>
                        </div>
                    </div>
                    <a href="perfil.php" class="btn btn-outline-primary edit-profile-btn">
                        <i class="fas fa-user-edit mr-1"></i> Perfil
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de administración de exámenes -->
        <div class="col-md-4">
            <div class="card admin-card">
                <div class="card-body">
                    <h5 class="admin-card-title">
                        <i class="fas fa-book mr-2"></i> Administrar Exámenes
                    </h5>
                    <p class="card-text">Has creado <?php echo $examCount; ?> exámenes hasta el momento.</p>
                    <a href="crear_examen.php" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i> Crear Nuevo Examen
                    </a>
                    <a href="completed_exams.php" class="btn btn-outline-primary">
                        <i class="fas fa-tasks mr-1"></i> Exámenes Completados
                    </a>
                    <a href="available_exams.php" class="btn btn-outline-primary">
                        <i class="fas fa-tasks mr-1"></i> Gestionar Exámenes
                    </a>
                    
                        
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Tarjeta de administración de usuarios (solo para admin) -->
        <?php if ($userRole === 'admin'): ?>
        <div class="col-md-4">
            <div class="card admin-card">
                <div class="card-body">
                    <h5 class="admin-card-title">
                        <i class="fas fa-users mr-2"></i> Administrar Usuarios
                    </h5>
                    <p class="card-text">Hay un total de <?php echo $studentCount; ?> estudiantes registrados en el sistema.</p>
                    <a href="manage_users.php" class="btn btn-primary">
                        <i class="fas fa-user-cog mr-1"></i> Gestionar Usuarios
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tarjeta de estadísticas -->
        <div class="col-md-<?php echo ($userRole === 'admin') ? '6' : '4'; ?>">
            <div class="card admin-card">
                <div class="card-body">
                    <h5 class="admin-card-title">
                        <i class="fas fa-chart-bar mr-2"></i> Estadísticas
                    </h5>
                    <p class="card-text">Consulta estadísticas detalladas sobre los exámenes y rendimiento de los estudiantes.</p>
                    <a href="statistics.php" class="btn btn-primary">
                        <i class="fas fa-chart-line mr-1"></i> Ver Estadísticas
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Actividad reciente (solo para admin) -->
       
    </div>
</div>

<!-- Footer -->
<footer class="footer mt-auto py-3">
    <div class="container text-center">
        <span>Sistema de Exámenes &copy; <?php echo date('Y'); ?> | Todos los derechos reservados</span>
    </div>
</footer>

<!-- Scripts de Bootstrap -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>