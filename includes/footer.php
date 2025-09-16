        </main>
        
        <footer class="main-footer">
            <div class="footer-container">
                <p>&copy; <?= date('Y') ?> Instituto Tecnológico de Aguascalientes. Todos los derechos reservados.</p>
                <p>Versión <?= APP_VERSION ?></p>
            </div>
        </footer>
        
        <!-- Scripts -->
        <script src="../assets/js/main.js"></script>
        <?php if (isset($dashboardJS)): ?>
        <script src="../assets/js/dashboard.js"></script>
        <?php endif; ?>
        <?php if (isset($formsJS)): ?>
        <script src="../assets/js/forms.js"></script>
        <?php endif; ?>
    </body>
</html>