<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'examenes_db';
$username = 'root';
$password = '123456789';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Obtener información del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit();
}

// Función para obtener estadísticas según el rol
function getStatistics($pdo, $user_id, $role) {
    $stats = [];
    
    switch($role) {  
        case 'profesor':
            // Exámenes creados
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_examenes FROM examenes WHERE admin_id = ?");
            $stmt->execute([$user_id]);
            $stats['examenes_creados'] = $stmt->fetch()['total_examenes'];
            
            // Total de preguntas creadas
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_preguntas FROM preguntas p JOIN examenes e ON p.examen_id = e.id WHERE e.admin_id = ?");
            $stmt->execute([$user_id]);
            $stats['preguntas_creadas'] = $stmt->fetch()['total_preguntas'];
            
            // Estudiantes que han tomado sus exámenes
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT estudiante_id) as total_estudiantes FROM resultados r JOIN examenes e ON r.examen_id = e.id WHERE e.admin_id = ?");
            $stmt->execute([$user_id]);
            $stats['estudiantes_activos'] = $stmt->fetch()['total_estudiantes'];
            
            // Últimos exámenes creados
            $stmt = $pdo->prepare("SELECT titulo, descripcion, created_at FROM examenes WHERE admin_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$user_id]);
            $stats['ultimos_examenes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            break;
            case 'admin':
            // Total de usuarios    
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_usuarios FROM usuarios");
            $stmt->execute();
            $stats['total_usuarios'] = $stmt->fetch()['total_usuarios'];
            // Total de exámenes
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_examenes FROM examenes");
            $stmt->execute();
            $stats['total_examenes'] = $stmt->fetch()['total_examenes'];
            // Total de resultados
            $stmt = $pdo->prepare("SELECT COUNT(*) as total_resultados FROM resultados");
            $stmt->execute();
            $stats['total_resultados'] = $stmt->fetch()['total_resultados'];
            // Usuarios por rol
            $stmt = $pdo->prepare("SELECT role, COUNT(*) as cantidad FROM usuarios GROUP BY role");
            $stmt->execute();
            $stats['usuarios_por_rol'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Actividad reciente
            $stmt = $pdo->prepare("SELECT u.nombre, e.titulo, r.puntaje, r.fecha FROM resultados r JOIN usuarios u ON r.estudiante_id = u.id JOIN examenes e ON r.examen_id = e.id ORDER BY r.fecha DESC LIMIT 10");
            $stmt->execute();
            $stats['actividad_reciente'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
            
    }
    
    return $stats;
}

$stats = getStatistics($pdo, $user_id, $user['role']);

// Procesar actualización de perfil
if ($_POST && isset($_POST['update_profile'])) {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    
    if (!empty($nombre) && !empty($email)) {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $user_id]);
            $success_message = "Perfil actualizado correctamente";
            
            // Actualizar datos del usuario en la variable
            $user['nombre'] = $nombre;
            $user['email'] = $email;
        } catch(PDOException $e) {
            $error_message = "Error al actualizar: " . $e->getMessage();
        }
    }
}


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Sistema de Exámenes</title>
    <link rel="stylesheet" href="perfil.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mi Perfil</h1>
            <div class="role-badge"><?php echo ucfirst($user['role']); ?></div>
        </div>

        <div class="content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <div class="profile-info">
                    <h3>Información Personal</h3>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($user['nombre']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Rol:</strong> <?php echo ucfirst($user['role']); ?></p>
                    <p><strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                </div>

                <div>
                    <h3>Estadísticas</h3>
                    <div class="stats-grid">
                        <?php if ($user['role'] == 'student'): ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['examenes_realizados']; ?></div>
                                <div class="stat-label">Exámenes Realizados</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['promedio'] ?: '0'; ?></div>
                                <div class="stat-label">Promedio General</div>
                            </div>
                        <?php elseif ($user['role'] == 'profesor'): ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['examenes_creados']; ?></div>
                                <div class="stat-label">Exámenes Creados</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['preguntas_creadas']; ?></div>
                                <div class="stat-label">Preguntas Creadas</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['estudiantes_activos']; ?></div>
                                <div class="stat-label">Estudiantes Activos</div>
                            </div>
                        <?php elseif ($user['role'] == 'admin'): ?>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_usuarios']; ?></div>
                                <div class="stat-label">Total Usuarios</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_examenes']; ?></div>
                                <div class="stat-label">Total Exámenes</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number"><?php echo $stats['total_resultados']; ?></div>
                                <div class="stat-label">Resultados Registrados</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sección específica por rol -->
            <?php if ($user['role'] == 'student' && !empty($stats['historial'])): ?>
                <div class="section">
                    <div class="section-header">
                        <h3>Historial de Exámenes</h3>
                    </div>
                    <div class="section-content">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Examen</th>
                                    <th>Puntaje</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['historial'] as $examen): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($examen['titulo']); ?></td>
                                        <td><?php echo $examen['puntaje']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($examen['fecha'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user['role'] == 'profesor' && !empty($stats['ultimos_examenes'])): ?>
                <div class="section">
                    <div class="section-header">
                        <h3>Mis Últimos Exámenes</h3>
                    </div>
                    <div class="section-content">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Descripción</th>
                                    <th>Fecha de Creación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['ultimos_examenes'] as $examen): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($examen['titulo']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($examen['descripcion'], 0, 50)) . '...'; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($examen['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($user['role'] == 'admin'): ?>
                <div class="section">
                    <div class="section-header">
                        <h3>Usuarios por Rol</h3>
                    </div>
                    <div class="section-content">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rol</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['usuarios_por_rol'] as $rol): ?>
                                    <tr>
                                        <td><?php echo ucfirst($rol['role']); ?></td>
                                        <td><?php echo $rol['cantidad']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($stats['actividad_reciente'])): ?>
                    <div class="section">
                        <div class="section-header">
                            <h3>Actividad Reciente</h3>
                        </div>
                        <div class="section-content">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Estudiante</th>
                                        <th>Examen</th>
                                        <th>Puntaje</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['actividad_reciente'] as $actividad): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($actividad['nombre']); ?></td>
                                            <td><?php echo htmlspecialchars($actividad['titulo']); ?></td>
                                            <td><?php echo $actividad['puntaje']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($actividad['fecha'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Formulario para actualizar perfil -->
            <div class="section">
                <div class="section-header">
                    <h3>Actualizar Información Personal</h3>
                </div>
                <div class="section-content">
                    <form method="POST">
                        <div class="form-group">
                            <label for="nombre">Nombre:</label>
                            <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($user['nombre']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn">Actualizar Perfil</button>
                    </form>
                </div>
            </div>

                </div>
            </div>

            <!-- Enlaces de navegación -->
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <?php if ($user['role'] == 'profesor' || $user['role'] == 'admin'): ?>
                    <a href="crear_examen.php">Crear Examen</a>
                <?php endif; ?>
                <?php if ($user['role'] == 'student'): ?>
                    <a href="examenes_disponibles.php">Exámenes Disponibles</a>
                <?php endif; ?>
                <a href="logout.php">Cerrar Sesión</a>
                
            </div>
        </div>
    </div>
</body>
</html> 