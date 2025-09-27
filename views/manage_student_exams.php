<!-- Sidebar -->
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house-door"></i> Inicio
                </a>
            </li>
            <?php if (in_array($_SESSION['user_role'], ['admin', 'profesor'])): ?>
            <li class="nav-item">
                <a class="nav-link" href="manage_exams.php">
                    <i class="bi bi-file-earmark-text"></i> Gestionar Exámenes
                </a>
            </li>
            <?php if ($_SESSION['user_role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="manage_users.php">
                    <i class="bi bi-people"></i> Gestionar Usuarios
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="manage_student_exams.php">
                    <i class="bi bi-arrow-repeat"></i> Reactivar Exámenes
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'available_exams.php' ? 'active' : ''; ?>" href="available_exams.php">
                    <i class="bi bi-file-earmark-text"></i> Exámenes Disponibles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'completed_exams.php' ? 'active' : ''; ?>" href="completed_exams.php">
                    <i class="bi bi-check-circle"></i> Exámenes Completados
                </a>
            </li>
            <?php endif; ?>
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