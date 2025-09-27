<?php
// manage_users.php
// Start session
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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

$message = '';

// Handle role update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_role'])) {
    $userId = $_POST['user_id'];
    $newRole = $_POST['role'];
    
    // Prepare and bind
    $stmt = $conn->prepare("UPDATE usuarios SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $newRole, $userId);
    
    // Execute the statement
    if ($stmt->execute()) {
        $message = '<div class="alert alert-success">Rol actualizado correctamente</div>';
    } else {
        $message = '<div class="alert alert-danger">Error al actualizar el rol: ' . $stmt->error . '</div>';
    }
    
    $stmt->close();
}

// Get all users
$sql = "SELECT id, nombre, email, role, created_at FROM usuarios ORDER BY id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
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
                            <a class="nav-link" href="#">
                                <i class="bi bi-file-text"></i> Exámenes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_exam.php">
                                <i class="bi bi-plus-circle"></i> Crear Examen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_users.php">
                                <i class="bi bi-people"></i> Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
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
                    <h1 class="h2">Gestión de Usuarios</h1>
                </div>
                
                <?php echo $message; ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol Actual</th>
                                <th>Fecha de Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row["id"] . "</td>";
                                    echo "<td>" . htmlspecialchars($row["nombre"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                    echo "<td>" . htmlspecialchars($row["role"]) . "</td>";
                                    echo "<td>" . date('d/m/Y H:i', strtotime($row["created_at"])) . "</td>";
                                    echo "<td>";
                                    echo "<button type='button' class='btn btn-sm btn-primary' data-bs-toggle='modal' data-bs-target='#editRoleModal" . $row["id"] . "'>";
                                    echo "<i class='bi bi-pencil-square'></i> Cambiar Rol";
                                    echo "</button>";
                                    echo "</td>";
                                    echo "</tr>";
                                    
                                    // Modal for each user
                                    echo "<div class='modal fade' id='editRoleModal" . $row["id"] . "' tabindex='-1' aria-labelledby='editRoleModalLabel" . $row["id"] . "' aria-hidden='true'>";
                                    echo "<div class='modal-dialog'>";
                                    echo "<div class='modal-content'>";
                                    echo "<div class='modal-header'>";
                                    echo "<h5 class='modal-title' id='editRoleModalLabel" . $row["id"] . "'>Cambiar Rol de Usuario</h5>";
                                    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
                                    echo "</div>";
                                    echo "<div class='modal-body'>";
                                    echo "<form method='post' action='" . htmlspecialchars($_SERVER["PHP_SELF"]) . "'>";
                                    echo "<input type='hidden' name='user_id' value='" . $row["id"] . "'>";
                                    echo "<div class='mb-3'>";
                                    echo "<label for='role' class='form-label'>Rol</label>";
                                    echo "<select class='form-select' id='role' name='role'>";
                                    echo "<option value='estudiante'" . ($row["role"] === 'estudiante' ? ' selected' : '') . ">Estudiante</option>";
                                    echo "<option value='profesor'" . ($row["role"] === 'profesor' ? ' selected' : '') . ">Profesor</option>";
                                    echo "<option value='admin'" . ($row["role"] === 'admin' ? ' selected' : '') . ">Administrador</option>";
                                    echo "</select>";
                                    echo "</div>";
                                    echo "<div class='d-grid'>";
                                    echo "<button type='submit' name='update_role' class='btn btn-primary'>Actualizar Rol</button>";
                                    echo "</div>";
                                    echo "</form>";
                                    echo "</div>";
                                    echo "</div>";
                                    echo "</div>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center'>No hay usuarios registrados</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'views/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    // Close connection
    $conn->close();
    ?>
</body>
</html>