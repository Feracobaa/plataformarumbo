<?php
// register.php
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

$registerMessage = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Secure password hashing
    $role = "estudiante"; // Rol de estudiante por defecto

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        $registerMessage = '<div class="alert alert-danger">El email ya está registrado</div>';
    } else {
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $nombre, $email, $password, $role);

        // Execute the statement
        if ($stmt->execute()) {
            $registerMessage = '<div class="alert alert-success">Registro exitoso. <a href="login.php">Iniciar sesión</a></div>';
        } else {
            $registerMessage = '<div class="alert alert-danger">Error al registrar: ' . $stmt->error . '</div>';
        }

        $stmt->close();
    }

    $checkEmail->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Rumbo al Saber</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }

        .register-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            margin: auto;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header i {
            font-size: 2.5rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }

        .form-control {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-register {
            background: var(--secondary-color);
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 5px;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .input-group-text {
            background: transparent;
            border-right: none;
        }

        .input-group .form-control {
            border-left: none;
        }

        .alert {
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <i class="bi bi-book"></i>
                <h2>Rumbo al Saber</h2>
                <p class="text-muted">Crear nueva cuenta</p>
            </div>

            <?php echo $registerMessage; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre completo" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Correo electrónico" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        Al registrarte, serás dado de alta como estudiante.
                    </div>
                </div>

                <button type="submit" class="btn-register mb-3">
                    Crear cuenta
                </button>

                <div class="text-center">
                    <p class="mb-0">¿Ya tienes una cuenta? <a href="login.php" class="text-decoration-none">Iniciar sesión</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>