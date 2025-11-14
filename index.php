<?php
// ==================== INDEX.PHP ====================
// index.php - Página principal del sistema
require_once 'auth.php';
verificarSesion();
$usuario = obtenerDatosUsuario();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Sistema de Ventas</title>
   <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #f2f4f8 0%, #e5e9f2 100%);
        height: 100vh;
        overflow: hidden;
    }

    .contenedor {
        display: flex;
        height: 100vh;
    }

    .menu-lateral {
        width: 280px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        box-shadow: 2px 0 12px rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        z-index: 1000;
    }

    .menu-lateral h2 {
        background: linear-gradient(135deg, #cfd9df 0%, #e2ebf0 100%);
        color: #333;
        padding: 25px 20px;
        font-size: 24px;
        font-weight: 600;
        text-align: center;
        letter-spacing: 1px;
        text-shadow: none;
    }

    .usuario-info {
        background: rgba(245,245,245,0.7); 
        padding: 10px; 
        margin: 10px; 
        border-radius: 8px; 
        color: #444; 
        text-align: center;
    }

    .menu-lateral ul {
        list-style: none;
        padding: 20px 0;
        flex-grow: 1;
    }

    .menu-lateral li {
        margin: 5px 15px;
    }

    .menu-lateral a {
        display: block;
        padding: 15px 20px;
        text-decoration: none;
        color: #444;
        font-size: 16px;
        font-weight: 500;
        border-radius: 12px;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .menu-lateral a:hover {
        background: linear-gradient(135deg, #dfe6e9 0%, #b2bec3 100%);
        color: #222;
        transform: translateX(5px);
        border-left: 4px solid #a5b1c2;
        box-shadow: 0 4px 12px rgba(165, 177, 194, 0.3);
    }

    .menu-lateral a.activo {
        background: linear-gradient(135deg, #a3c4dc 0%, #dce3e8 100%);
        color: #222;
        border-left: 4px solid #5d8aa8;
        box-shadow: 0 4px 12px rgba(163, 196, 220, 0.4);
    }

    .contenido {
        flex: 1;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(8px);
        margin: 20px;
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .bienvenida {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        text-align: center;
        padding: 40px;
    }

    .bienvenida h1 {
        font-size: 3em;
        color: #333;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #a5b1c2 0%, #dcdde1 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .bienvenida p {
        font-size: 1.3em;
        color: #666;
        margin-bottom: 40px;
        max-width: 600px;
        line-height: 1.6;
    }

    .imagen-crm {
        width: 400px;
        height: 300px;
        background: linear-gradient(135deg, #cfd9df 0%, #e2ebf0 100%);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 12px 28px rgba(165, 177, 194, 0.3);
        position: relative;
        overflow: hidden;
    }

    .icono-crm {
        font-size: 80px;
        color: #5d6d7e;
        z-index: 1;
    }

    .frame-contenido {
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 20px;
        display: none;
    }

    .menu-deshabilitado {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }
</style>

</head>
<body>
    <div class="contenedor">
        <nav class="menu-lateral">
            <h2>CRM Ventas</h2>
            <div class="usuario-info">
                <small>Bienvenido: <strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong></small><br>
                <small>Rol: <?php echo ucfirst($usuario['rol']); ?></small>
            </div>
            <ul>
                <li><a href="#" onclick="mostrarInicio('dashboard.php')" >🏠 Inicio</a></li>
                <li><a href="#" onclick="cargarPagina('seguimientos.php')">📞 Seguimientos</a></li>
                <li><a href="#" onclick="cargarPagina('repvendedor.php')">📊 Reportes</a></li>

                <?php if (esAdmin()): ?>
                <li><a href="#" onclick="cargarPagina('clientes.php')">👥 Clientes</a></li>
                <li><a href="#" onclick="cargarPagina('vendedores.php')">🤝 Vendedores</a></li>
                <li><a href="#" onclick="cargarPagina('asignar.php')">📋 Asignar Clientes</a></li>
                <li><a href="#" onclick="cargarPagina('reportes.php')">📊 Reportes</a></li>
                <li><a href="#" onclick="cargarPagina('usuarios.php')">👤 Usuarios</a></li>
                <?php endif; ?>
                
                <li><a href="logout.php">🚪 Cerrar Sesión</a></li>
            </ul>
        </nav>

        <main class="contenido">
            <div class="bienvenida" id="bienvenida">
                <h1>Bienvenido <?php echo esAdmin() ? 'Administrador' : 'Vendedor'; ?></h1>
                <p>
                    <?php if (esAdmin()): ?>
                        Gestiona eficientemente todo el sistema CRM. Controla vendedores, clientes y genera reportes completos.
                    <?php else: ?>
                        Gestiona tus seguimientos con clientes. Registra visitas y llamadas de manera eficiente.
                    <?php endif; ?>
                </p>
                
                <div class="imagen-crm">
                    <div class="icono-crm">📈</div>
                </div>
            </div>

            <iframe id="contenidoFrame" class="frame-contenido" src=""></iframe>
        </main>
    </div>

    <script>
        const datosUsuario = {
            id: <?php echo $usuario['id_usuario']; ?>,
            nombre: '<?php echo addslashes($usuario['nombre']); ?>',
            rol: '<?php echo $usuario['rol']; ?>',
            idVendedor: <?php echo $usuario['id_vendedor'] ? $usuario['id_vendedor'] : 'null'; ?>
        };

        function mostrarInicio() {
            document.getElementById('bienvenida').style.display = 'flex';
            document.getElementById('contenidoFrame').style.display = 'none';
            actualizarMenuActivo(0);
        }

        function cargarPagina(pagina) {
            const frame = document.getElementById('contenidoFrame');
            const bienvenida = document.getElementById('bienvenida');
            
            if (!verificarPermisos(pagina)) {
                alert('No tienes permisos para acceder a esta sección.');
                return;
            }
            
            bienvenida.style.display = 'none';
            frame.style.display = 'block';
            
            if (datosUsuario.rol === 'vendedor') {
                const params = new URLSearchParams();
                params.append('usuario_id', datosUsuario.id);
                if (datosUsuario.idVendedor) {
                    params.append('vendedor_id', datosUsuario.idVendedor);
                }
                frame.src = pagina + '?' + params.toString();
            } else {
                frame.src = pagina;
            }
            
            const enlaces = document.querySelectorAll('.menu-lateral a');
            enlaces.forEach(enlace => enlace.classList.remove('activo'));
            event.target.classList.add('activo');
        }

        function verificarPermisos(pagina) {
            if (datosUsuario.rol === 'admin') {
                return true;
            }
            
            if (datosUsuario.rol === 'vendedor') {
                const paginasPermitidas = ['seguimientos.php','repvendedor.php'];
                return paginasPermitidas.includes(pagina);
            }
            
            return false;
        }

        function actualizarMenuActivo(indice) {
            const enlaces = document.querySelectorAll('.menu-lateral a');
            enlaces.forEach(enlace => enlace.classList.remove('activo'));
            if (enlaces[indice]) {
                enlaces[indice].classList.add('activo');
            }
        }
    </script>
</body>
</html>

