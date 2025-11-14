<?php
// ==================== LOGIN.PHP ====================
// login.php - Página de inicio de sesión (Sin hash, texto plano)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';

if ($_POST) {
    $usuario = $_POST['usuario'];
    $clave = $_POST['clave'];
    
    $sql = "SELECT IdUsuario, Usuario, Clave, Nombre, Rol, IdVendedor, Activo 
            FROM UsuariosCRM 
            WHERE Usuario = ? AND Activo = 1";
    
    $params = array($usuario);
    $stmt = sqlsrv_query($conn, $sql, $params);
    
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }
    
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    
    // Cambio principal: comparación directa sin hash
    if ($user && $clave === $user['Clave']) {
        $_SESSION['usuario_id'] = $user['IdUsuario'];
        $_SESSION['usuario'] = $user['Usuario'];
        $_SESSION['nombre'] = $user['Nombre'];
        $_SESSION['rol'] = $user['Rol'];
        $_SESSION['id_vendedor'] = $user['IdVendedor'];
        $_SESSION['logueado'] = true;
        
        sqlsrv_free_stmt($stmt);
        header("Location: index.php");
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
    
    sqlsrv_free_stmt($stmt);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - CRM</title>
    <style>
    body { 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: linear-gradient(135deg, #f2f4f8 0%, #e5e9f2 100%);
        margin: 0;
        padding: 0;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-container { 
        max-width: 400px; 
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(8px);
        padding: 40px; 
        border-radius: 20px; 
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }

    .login-container h2 {
        text-align: center;
        margin-bottom: 30px;
        color: #444;
        font-size: 28px;
        font-weight: 600;
    }

    .form-group { 
        margin-bottom: 20px; 
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-weight: 500;
    }

    input[type="text"], input[type="password"] { 
        width: 100%; 
        padding: 15px; 
        border: 2px solid #dfe6e9;
        border-radius: 10px; 
        font-size: 16px;
        transition: all 0.3s ease;
        box-sizing: border-box;
        background-color: #fafafa;
    }

    input[type="text"]:focus, input[type="password"]:focus {
        outline: none;
        border-color: #a5b1c2;
        box-shadow: 0 0 0 3px rgba(165, 177, 194, 0.2);
    }

    .btn { 
        width: 100%; 
        padding: 15px; 
        background: linear-gradient(135deg, #cfd9df 0%, #e2ebf0 100%);
        color: #333; 
        border: none; 
        border-radius: 10px; 
        cursor: pointer; 
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(165, 177, 194, 0.3);
    }

    .error { 
        color: #c0392b; 
        margin-bottom: 15px; 
        padding: 10px;
        background: rgba(192, 57, 43, 0.1);
        border-radius: 8px;
        text-align: center;
    }

    .logo {
        text-align: center;
        margin-bottom: 20px;
        font-size: 48px;
        color: #5d6d7e;
    }
</style>

</head>
<body>
    <div class="login-container">
        <div class="logo">📈</div>
        <h2>CRM - Sistema de Ventas</h2>
        
        <?php if (isset($error)): ?>
            <div class='error'><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="usuario">Usuario:</label>
                <input type="text" id="usuario" name="usuario" placeholder="Ingresa tu usuario" required>
            </div>
            <div class="form-group">
                <label for="clave">Contraseña:</label>
                <input type="password" id="clave" name="clave" placeholder="Ingresa tu contraseña" required>
            </div>
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
            <p>¿Olvidaste tu contraseña? <a href="mailto:admin@empresa.com" style="color: #667eea;">Contacta al administrador</a></p>
        </div>
    </div>
</body>
</html>