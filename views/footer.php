<!-- Bootstrap JS Bundle with Popper -->
<script src="<?= isset($dashboard) ? '../../assets/bootstrap.bundle.min.js' : '../assets/bootstrap.bundle.min.js' ?>"></script>
    <script>
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>