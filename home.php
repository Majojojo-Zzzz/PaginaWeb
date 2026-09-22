<?php
// Incluimos tu conexión global
require_once 'config/conexion.php';

// Llamamos al header (se encarga automáticamente de la seguridad, sesión y estructura visual)
include 'includes/header.php';
?>

            <!-- CUERPO PRINCIPAL DE INICIO / HOME -->
            <div class="p-8">
                <!-- Tarjeta contenedora de bienvenida -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 mb-1">¡Bienvenido al Sistema HotelSys, <?php echo htmlspecialchars($_SESSION['nombre_completo']); ?>!</h2>
                    <p class="text-sm text-gray-500">Espacio de trabajo listo para integrar tus indicadores y tablas operativas.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Script universal para desplegables -->
    <script>
        function toggleDropdown(menuId, iconId) {
            const menu = document.getElementById(menuId);
            const icon = document.getElementById(iconId ? iconId : null);
            
            menu.classList.toggle('hidden');
            if (icon) {
                icon.classList.toggle('rotate-180');
            }
        }

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('profile-dropdown');
            const button = dropdown ? dropdown.previousElementSibling : null;
            if (button && dropdown && !button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
                const icon = document.getElementById('profile-chevron');
                if (icon) icon.classList.remove('rotate-180');
            }
        });
    </script>
</body>
</html>