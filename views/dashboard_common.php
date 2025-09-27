<?php
// dashboard_common.php
// Este archivo contiene funciones y código común para todos los dashboards

// Start session
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
function getDbConnection()
{
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

    return $conn;
}

// Get user information
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$userName = $_SESSION['user_name'];
$userEmail = $_SESSION['user_email'];

// Get user registration date
function getUserData($userId)
{
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT created_at FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $conn->close();

    $registrationDate = new DateTime($userData['created_at']);
    return $registrationDate->format('d/m/Y H:i');
}

// Redirect to specific dashboard based on role
function redirectToDashboard($userRole)
{
    if ($userRole === 'estudiante') {
        header("Location: dashboard_estudiante.php");
    } elseif ($userRole === 'admin' || $userRole === 'profesor') {
        header("Location: dashboard_admin_profesor.php");
    } else {
        // Default dashboard or error page for unknown roles
        header("Location: dashboard_estudiante.php");
    }
    exit;
}

function includeHeader($pageTitle)
{
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
        <link href="common.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    </head>
    <body>
            <body>
        <?php include 'views/partials/header.php'; ?>
        
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                    <div class="position-sticky pt-3">
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" href="dashboard.php">
                                    <i class="bi bi-house-door"></i> Inicio
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="available_exams.php">
                                    <i class="bi bi-file-text"></i> Exámenes
                                </a>
                            </li>
                            <?php if ($_SESSION['user_role'] === 'profesor' || $_SESSION['user_role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="create_exam.php">
                                    <i class="bi bi-plus-circle"></i> Crear Examen
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="manage_users.php">
                                    <i class="bi bi-people"></i> Gestionar Usuarios
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
    <?php
}
// Function to include the footer HTML for all dashboards
function includeFooter()
{
    ?>
                </main>
            </div>
        </div>
        
        <?php include 'footer.php'; ?>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
}

// Function to display user information card
function displayUserInfoCard($userName, $userEmail, $userRole, $registrationDate)
{
    ?>
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Mi Información</h5>
                <p class="card-text"><strong>Nombre:</strong> <?php echo htmlspecialchars($userName); ?></p>
                <p class="card-text"><strong>Email:</strong> <?php echo htmlspecialchars($userEmail); ?></p>
                <p class="card-text"><strong>Rol:</strong> <?php echo htmlspecialchars($userRole); ?></p>
                <p class="card-text"><strong>Fecha de registro:</strong> <?php echo $registrationDate; ?></p>
            </div>
        </div>
    </div>
    <?php
}
?>