<?php
// Iniciar sesión
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Conectar a la base de datos
$servername = "localhost";
$username = "root"; // Ajusta según tu configuración
$password = "123456789"; // Ajusta según tu configuración
$dbname = "examenes_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Obtener información del usuario actual
$user_id = $_SESSION['user_id'];
$sql = "SELECT id, nombre, email, role, created_at FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Directorio para almacenar fotos de perfil
$upload_dir = "plataforma-rumbo-al-saber\views";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Manejar carga de foto de perfil
$mensaje = "";
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    
    // Verificar si hay errores
    if ($file['error'] === UPLOAD_ERR_OK) {
        $temp_name = $file['tmp_name'];
        $name = basename($file['name']);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        
        // Verificar la extensión
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            // Crear un nombre único para el archivo
            $new_filename = "user_" . $user_id . "_" . time() . "." . $extension;
            $destination = $upload_dir . $new_filename;
            
            // Mover el archivo cargado
            if (move_uploaded_file($temp_name, $destination)) {
                // Actualizar la ruta de la foto en la base de datos (agregar columna si no existe)
                // Este código asume que ya tienes una columna 'foto_perfil' en tu tabla usuarios
                $sql = "UPDATE usuarios SET foto_perfil = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                
                
                
                
                $stmt->bind_param("si", $destination, $user_id);
                $stmt->execute();
                $stmt->close();
                
                $mensaje = "Foto de perfil actualizada correctamente.";
                
                // Refrescar los datos del usuario
                $sql = "SELECT id, nombre, email, role, created_at, foto_perfil FROM usuarios WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
            } else {
                $mensaje = "Error al mover el archivo cargado.";
            }
        } else {
            $mensaje = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
        }
    } else {
        $mensaje = "Error al cargar el archivo.";
    }
}

// Manejar actualización de perfil (solo para administradores)
if (isset($_POST['update_profile']) && $_SESSION['role'] === 'admin') {
    $target_user_id = $_POST['user_id'];
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    
    // Actualizar la información del usuario
    $sql = "UPDATE usuarios SET nombre = ?, email = ?, role = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssi", $nombre, $email, $role, $target_user_id);
    
    if ($stmt->execute()) {
        $mensaje = "Perfil de usuario actualizado correctamente.";
        
        // Si se está actualizando el usuario actual, actualizar también los datos de la sesión
        if ($target_user_id == $user_id) {
            $_SESSION['nombre'] = $nombre;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $role;
        }
    } else {
        $mensaje = "Error al actualizar el perfil: " . $stmt->error;
    }
    $stmt->close();
    
    // Si también se actualiza la contraseña
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "UPDATE usuarios SET password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $password, $target_user_id);
        
        if ($stmt->execute()) {
            $mensaje .= " Contraseña actualizada correctamente.";
        } else {
            $mensaje .= " Error al actualizar la contraseña: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Obtener lista de usuarios (solo para administradores)
$usuarios = [];
if (isset($user['role']) && $user['role'] === 'admin') {
    $sql = "SELECT id, nombre, email, role, created_at, foto_perfil FROM usuarios";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Usuario</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos generales */
        :root {
            --color-primary: #3498db;
            --color-secondary: #2980b9;
            --color-accent: #e74c3c;
            --color-background: #f5f7fa;
            --color-text: #333;
            --color-text-light: #777;
            --color-white: #fff;
            --color-border: #ddd;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--color-background);
            color: var(--color-text);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Encabezado */
        header {
            background-color: var(--color-primary);
            color: var(--color-white);
            padding: 15px 0;
            box-shadow: var(--shadow);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .nav-menu ul {
            display: flex;
            list-style: none;
        }
        
        .nav-menu li {
            margin-left: 20px;
        }
        
        .nav-menu a {
            color: var(--color-white);
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-menu a:hover {
            opacity: 0.8;
        }
        
        /* Perfil principal */
        .profile-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 30px;
        }
        
        .profile-sidebar {
            flex: 1;
            min-width: 300px;
            background-color: var(--color-white);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .profile-main {
            flex: 2;
            min-width: 500px;
            background-color: var(--color-white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 5px solid var(--color-background);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .profile-name {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .profile-role {
            display: inline-block;
            background-color: var(--color-primary);
            color: var(--color-white);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 20px;
        }
        
        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--color-primary);
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--color-text-light);
        }
        
        /* Formulario de carga de foto */
        .photo-upload-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }
        
        .custom-file-upload {
            display: block;
            width: 100%;
            margin: 15px 0;
            position: relative;
        }
        
        .custom-file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            cursor: pointer;
        }
        
        .file-label {
            display: block;
            padding: 10px;
            background-color: var(--color-background);
            border: 1px dashed var(--color-primary);
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-label:hover {
            background-color: #e8f4fd;
        }
        
        /* Estilos para pestañas */
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-color: var(--color-primary);
            color: var(--color-primary);
            font-weight: bold;
        }
        
        .tab:hover:not(.active) {
            border-color: var(--color-text-light);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Formularios */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        select:focus {
            border-color: var(--color-primary);
            outline: none;
        }
        
        button, .btn {
            background-color: var(--color-primary);
            color: var(--color-white);
            border: none;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        button:hover, .btn:hover {
            background-color: var(--color-secondary);
        }
        
        .btn-danger {
            background-color: var(--color-accent);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        /* Tabla de usuarios */
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--color-border);
            text-align: left;
        }
        
        .table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f5f5f5;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Alerta de mensajes */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            color: var(--color-white);
        }
        
        .alert-success {
            background-color: #2ecc71;
        }
        
        .alert-danger {
            background-color: var(--color-accent);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background-color: var(--color-white);
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
            }
            
            .profile-main, .profile-sidebar {
                min-width: 100%;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .nav-menu ul {
                margin-top: 15px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <div class="logo">Sistema de Exámenes</div>
            <nav class="nav-menu">
                <ul>
                    <li><a href="dashboard_admin_profesor.php"><i class="fas fa-home"></i> Inicio</a></li>
                    <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Perfil</a></li>
                    <?php if (isset($user['role']) && ($user['role'] === 'admin' || $user['role'] === 'profesor')): ?>
                    <li><a href="available_exams.php"><i class="fas fa-file-alt"></i> Exámenes</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="profile-sidebar">
                <?php
                // Mostrar foto de perfil si existe, de lo contrario mostrar avatar predeterminado
                $profile_image = "https://via.placeholder.com/150";
                if (isset($user['foto_perfil']) && file_exists($user['foto_perfil'])) {
                    $profile_image = $user['foto_perfil'];
                }
                ?>
                <img src="<?php echo $profile_image; ?>" alt="Foto de perfil" class="profile-photo">
                <h2 class="profile-name"><?php echo htmlspecialchars($user['nombre']); ?></h2>
                <span class="profile-role"><?php echo ucfirst(htmlspecialchars($user['role'] ?: 'Estudiante')); ?></span>
                
                <div class="profile-stats">
                    <div class="stat">
                        <div class="stat-number">
                            <?php
                            // Contar exámenes si es profesor o admin
                            if ($user['role'] === 'admin' || $user['role'] === 'profesor') {
                                $sql = "SELECT COUNT(*) as total FROM examenes WHERE admin_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                $stmt->close();
                            } else {
                                // Contar exámenes realizados si es estudiante
                                $sql = "SELECT COUNT(*) as total FROM resultados WHERE estudiante_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                $stmt->close();
                            }
                            ?>
                        </div>
                        <div class="stat-label">
                            <?php echo ($user['role'] === 'admin' || $user['role'] === 'profesor') ? 'Exámenes Creados' : 'Exámenes Realizados'; ?>
                        </div>
                    </div>
                    
                    <div class="stat">
                        <div class="stat-number">
                            <?php
                            // Mostrar la fecha de registro formateada
                            $created_date = new DateTime($user['created_at']);
                            echo $created_date->format('d/m/Y');
                            ?>
                        </div>
                        <div class="stat-label">Miembro desde</div>
                    </div>
                </div>
                
                <div class="photo-upload-form">
                    <h3>Cambiar foto de perfil</h3>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="custom-file-upload">
                            <label class="file-label" for="profile_photo">
                                <i class="fas fa-cloud-upload-alt"></i> Selecciona una imagen
                            </label>
                            <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
                        </div>
                        <button type="submit" name="upload_photo" class="btn">
                            <i class="fas fa-upload"></i> Subir foto
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="profile-main">
                <div class="tabs">
                    <div class="tab active" onclick="openTab(event, 'info')">Información Personal</div>
                    <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <div class="tab" onclick="openTab(event, 'users')">Gestionar Usuarios</div>
                    <?php endif; ?>
                </div>
                
                <div id="info" class="tab-content active">
                    <h2>Información Personal</h2>
                    <p>Revisa y actualiza tu información de perfil.</p>
                    
                    <form action="" method="POST">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        
                        <div class="form-group">
                            <label for="nombre">Nombre Completo</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" <?php echo ($user['role'] !== 'admin') ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Correo Electrónico</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" <?php echo ($user['role'] !== 'admin') ? 'readonly' : ''; ?>>
                        </div>
                        
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="form-group">
                            <label for="role">Rol</label>
                            <select id="role" name="role">
                                <option value="student" <?php echo ($user['role'] === 'student') ? 'selected' : ''; ?>>Estudiante</option>
                                <option value="profesor" <?php echo ($user['role'] === 'profesor') ? 'selected' : ''; ?>>Profesor</option>
                                <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Administrador</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Nueva Contraseña (dejar en blanco para mantener la actual)</label>
                            <input type="password" id="password" name="password">
                        </div>
                        
                        <button type="submit" name="update_profile">Actualizar Información</button>
                        <?php endif; ?>
                    </form>
                </div>
                
                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                <div id="users" class="tab-content">
                    <h2>Gestión de Usuarios</h2>
                    <p>Administra los usuarios del sistema.</p>
                    
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Fecha de Registro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($usuario['role'] ?: 'Estudiante')); ?></td>
                                    <td>
                                        <?php 
                                        $date = new DateTime($usuario['created_at']);
                                        echo $date->format('d/m/Y H:i'); 
                                        ?>
                                    </td>
                                    <td class="user-actions">
                                        <button onclick="openEditModal(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>', '<?php echo htmlspecialchars($usuario['email']); ?>', '<?php echo htmlspecialchars($usuario['role'] ?: 'student'); ?>')" class="btn">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal para editar usuario -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Editar Usuario</h2>
            
            <form action="" method="POST">
                <input type="hidden" id="modal_user_id" name="user_id" value="">
                
                <div class="form-group">
                    <label for="modal_nombre">Nombre</label>
                    <input type="text" id="modal_nombre" name="nombre" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_email">Correo Electrónico</label>
                    <input type="email" id="modal_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="modal_role">Rol</label>
                    <select id="modal_role" name="role">
                        <option value="student">Estudiante</option>
                        <option value="profesor">Profesor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_password">Nueva Contraseña (dejar en blanco para mantener la actual)</label>
                    <input type="password" id="modal_password" name="password">
                </div>
                
                <button type="submit" name="update_profile">Guardar Cambios</button>
            </form>
        </div>
    </div>
    
    <script>
        // Función para cambiar entre pestañas
        function openTab(evt, tabName) {
            const tabContents = document.getElementsByClassName("tab-content");
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            const tabs = document.getElementsByClassName("tab");
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
        
        // Función para mostrar el modal de edición
        function openEditModal(id, nombre, email, role) {
            document.getElementById("modal_user_id").value = id;
            document.getElementById("modal_nombre").value = nombre;
            document.getElementById("modal_email").value = email;
            document.getElementById("modal_role").value = role;
            document.getElementById("modal_password").value = "";
            
            document.getElementById("editModal").style.display = "block";
        }
        
        // Función para cerrar el modal
        function closeModal() {
            document.getElementById("editModal").style.display = "none";
        }
        
        // Cerrar el modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById("editModal");
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }
        
        // Previsualización de la imagen antes de subirla
        document.getElementById("profile_photo").onchange = function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector(".profile-photo").src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
            }
        };
        
        // Ocultar mensaje de alerta después de 5 segundos
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.display = 'none';
            }
        }, 5000);
    </script>
</body>
</html>