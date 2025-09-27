<?php
/**
 * sidebar.php
 * 
 * Descripción: Barra lateral de navegación según rol de usuario
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$rol = $_SESSION['usuario']['rol'];
?>

<div class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse" id="sidebarMenu">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link<?= $currentPage == "$rol.php" ? ' active' : '' ?>" href="<?= $rol ?>.php">
                    <i class="fas fa-home me-2"></i>
                    Inicio
                </a>
            </li>
            
            <?php if($rol === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'usuarios.php' ? ' active' : '' ?>" href="usuarios.php">
                        <i class="fas fa-users me-2"></i>
                        Gestión de Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'invitaciones.php' ? ' active' : '' ?>" href="invitaciones.php">
                        <i class="fas fa-envelope me-2"></i>
                        Invitaciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'configuracion.php' ? ' active' : '' ?>" href="configuracion.php">
                        <i class="fas fa-cog me-2"></i>
                        Configuración
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if($rol === 'profesor'): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'examenes.php' ? ' active' : '' ?>" href="examenes.php">
                        <i class="fas fa-file-alt me-2"></i>
                        Mis Exámenes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'preguntas.php' ? ' active' : '' ?>" href="preguntas.php">
                        <i class="fas fa-question-circle me-2"></i>
                        Banco de Preguntas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'calificaciones.php' ? ' active' : '' ?>" href="calificaciones.php">
                        <i class="fas fa-chart-bar me-2"></i>
                        Calificaciones
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if($rol === 'estudiante'): ?>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'mis_examenes.php' ? ' active' : '' ?>" href="mis_examenes.php">
                        <i class="fas fa-tasks me-2"></i>
                        Mis Exámenes
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'historial.php' ? ' active' : '' ?>" href="historial.php">
                        <i class="fas fa-history me-2"></i>
                        Historial
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $currentPage == 'resultados.php' ? ' active' : '' ?>" href="resultados.php">
                        <i class="fas fa-award me-2"></i>
                        Resultados
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <hr class="text-light">
        
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Cerrar Sesión
                </a>
            </li>
        </ul>
    </div>
</div>