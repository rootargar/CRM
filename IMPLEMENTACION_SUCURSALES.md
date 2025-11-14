# Implementación del Sistema de Sucursales en CRM

## Resumen de Cambios

Se ha agregado el concepto de **Sucursales** al sistema CRM, permitiendo filtrar y gestionar usuarios, vendedores, clientes y seguimientos por sucursal.

### Sucursales del Catálogo:
1. Matriz
2. Mazatlan
3. Mochis
4. Guasave
5. Guamuchil
6. TRP Mazatlan

---

## Pasos de Implementación

### 1. Ejecutar Script SQL

**IMPORTANTE:** Ejecutar el archivo `agregar_sucursales.sql` en tu base de datos SQL Server.

```sql
-- Este script realiza lo siguiente:
-- 1. Crea la tabla SucursalesCRM con las 6 sucursales
-- 2. Agrega columna IdSucursal a UsuariosCRM
-- 3. Agrega columna IdSucursal a VendedoresCRM
-- 4. Crea las relaciones de llaves foráneas
```

**Ejecutar:**
```bash
sqlcmd -S KWSERVIFACT -U sa -P f4cturAs -d CotizaKW -i agregar_sucursales.sql
```

O desde SQL Server Management Studio:
1. Abrir el archivo `agregar_sucursales.sql`
2. Conectarse a la base de datos `CotizaKW`
3. Ejecutar el script completo

---

## 2. Archivos Modificados

Los siguientes archivos han sido modificados para incluir el sistema de sucursales:

### Archivos Principales:
- ✅ **login.php** - Agregado selector de sucursal en el formulario
- ✅ **auth.php** - Actualizada función `obtenerDatosUsuario()` para incluir datos de sucursal
- ✅ **usuarios.php** - Agregado campo de sucursal en CRUD
- ✅ **vendedores.php** - Agregado campo de sucursal en CRUD
- ✅ **seguimientos.php** - Agregados filtros por sucursal según rol
- ✅ **clientes.php** - Agregados filtros por sucursal según rol

### Archivos Nuevos:
- 📄 **agregar_sucursales.sql** - Script SQL para crear tablas y modificar esquema
- 📄 **IMPLEMENTACION_SUCURSALES.md** - Este documento

---

## 3. Lógica de Funcionamiento

### Sistema de Login:
1. El usuario selecciona una **Sucursal** del dropdown
2. Ingresa su **Usuario** y **Contraseña**
3. El sistema valida:
   - Si el usuario tiene `IdSucursal = NULL`: puede entrar en cualquier sucursal
   - Si el usuario tiene `IdSucursal` asignada: debe coincidir con la sucursal seleccionada
4. Si la validación falla, muestra mensaje: *"Usuario no autorizado para la sucursal seleccionada. Tu sucursal asignada es: [Nombre]"*

### Datos en Sesión:
Después del login exitoso, se guardan en sesión:
```php
$_SESSION['id_sucursal']      // ID de la sucursal seleccionada
$_SESSION['sucursal_nombre']  // Nombre de la sucursal
```

### Control de Acceso por Rol:

#### **Vendedores:**
- Ven solo sus propios seguimientos y clientes
- El filtro por sucursal se aplica indirectamente a través del vendedor

#### **Administradores:**
- **Con sucursal asignada (`IdSucursal` != NULL):**
  - Ven seguimientos y clientes de vendedores de su sucursal
  - Ven vendedores de su sucursal
  - Filtran datos por sucursal automáticamente

- **Sin sucursal asignada (`IdSucursal` = NULL):**
  - Ven TODOS los seguimientos, clientes y vendedores
  - Tienen acceso completo al sistema

---

## 4. Ejemplos de Uso

### Crear Usuario con Sucursal:
1. Ir a **Usuarios** → **Agregar Usuario**
2. Llenar datos del usuario
3. En **Asignar Sucursal**, seleccionar:
   - Una sucursal específica (ej: "Mazatlan") → solo podrá entrar en esa sucursal
   - "Todas las sucursales" → podrá entrar en cualquier sucursal

### Crear Vendedor con Sucursal:
1. Ir a **Vendedores** → **Nuevo Vendedor**
2. Llenar nombre del vendedor
3. En **Sucursal**, seleccionar:
   - Una sucursal específica → sus clientes y seguimientos se filtrarán por esa sucursal
   - "Todas las sucursales" → no se aplica filtro

### Asignar Permisos Típicos:

#### Admin General (ve todo):
- Usuario: admin
- Rol: admin
- Sucursal: **Todas las sucursales** (NULL)

#### Admin de Sucursal (ve solo su sucursal):
- Usuario: admin_mazatlan
- Rol: admin
- Sucursal: **Mazatlan**

#### Vendedor:
- Usuario: vendedor1
- Rol: vendedor
- Vendedor Asociado: Seleccionar vendedor
- Sucursal: **Mazatlan** (o la que corresponda)

---

## 5. Consultas SQL Útiles

### Ver todos los usuarios con sus sucursales:
```sql
SELECT u.IdUsuario, u.Usuario, u.Nombre, u.Rol, s.Nombre as Sucursal
FROM UsuariosCRM u
LEFT JOIN SucursalesCRM s ON u.IdSucursal = s.IdSucursal
ORDER BY u.Nombre;
```

### Ver todos los vendedores con sus sucursales:
```sql
SELECT v.IdVendedor, v.Nombre, s.Nombre as Sucursal, v.Activo
FROM VendedoresCRM v
LEFT JOIN SucursalesCRM s ON v.IdSucursal = s.IdSucursal
ORDER BY v.Nombre;
```

### Ver todas las sucursales:
```sql
SELECT * FROM SucursalesCRM ORDER BY IdSucursal;
```

### Actualizar sucursal de un usuario:
```sql
-- Asignar sucursal "Mazatlan" (IdSucursal = 2) a un usuario
UPDATE UsuariosCRM SET IdSucursal = 2 WHERE Usuario = 'nombre_usuario';

-- Permitir que un usuario entre en todas las sucursales
UPDATE UsuariosCRM SET IdSucursal = NULL WHERE Usuario = 'nombre_usuario';
```

### Asignar sucursal a todos los usuarios existentes (OPCIONAL):
```sql
-- Asignar "Matriz" (IdSucursal = 1) a todos los usuarios sin sucursal
UPDATE UsuariosCRM SET IdSucursal = 1 WHERE IdSucursal IS NULL;
```

---

## 6. Verificación de la Implementación

### Checklist de Pruebas:

- [ ] **Login:**
  - [ ] Se muestra el selector de sucursales
  - [ ] Usuario con sucursal asignada solo puede entrar en su sucursal
  - [ ] Usuario sin sucursal puede entrar en cualquier sucursal
  - [ ] Mensaje de error correcto cuando sucursal incorrecta

- [ ] **Usuarios (CRUD):**
  - [ ] Se puede asignar sucursal al crear usuario
  - [ ] Se puede editar sucursal de usuario existente
  - [ ] Se muestra sucursal en el listado de usuarios

- [ ] **Vendedores (CRUD):**
  - [ ] Se puede asignar sucursal al crear vendedor
  - [ ] Se puede editar sucursal de vendedor existente
  - [ ] Se muestra sucursal en el listado de vendedores

- [ ] **Seguimientos:**
  - [ ] Admin con sucursal solo ve seguimientos de vendedores de su sucursal
  - [ ] Admin sin sucursal ve todos los seguimientos
  - [ ] Vendedor solo ve sus propios seguimientos

- [ ] **Clientes:**
  - [ ] Admin con sucursal solo ve clientes de vendedores de su sucursal
  - [ ] Admin sin sucursal ve todos los clientes
  - [ ] Vendedor solo ve sus clientes asignados

---

## 7. Soporte y Mantenimiento

### Agregar Nueva Sucursal:
```sql
INSERT INTO SucursalesCRM (Nombre, Activo) VALUES ('NuevaSucursal', 1);
```

### Desactivar Sucursal:
```sql
UPDATE SucursalesCRM SET Activo = 0 WHERE Nombre = 'NombreSucursal';
```

### Ver Estadísticas por Sucursal:
```sql
-- Usuarios por sucursal
SELECT s.Nombre as Sucursal, COUNT(u.IdUsuario) as TotalUsuarios
FROM SucursalesCRM s
LEFT JOIN UsuariosCRM u ON s.IdSucursal = u.IdSucursal
WHERE s.Activo = 1
GROUP BY s.Nombre
ORDER BY s.Nombre;

-- Vendedores por sucursal
SELECT s.Nombre as Sucursal, COUNT(v.IdVendedor) as TotalVendedores
FROM SucursalesCRM s
LEFT JOIN VendedoresCRM v ON s.IdSucursal = v.IdSucursal
WHERE s.Activo = 1 AND v.Activo = 1
GROUP BY s.Nombre
ORDER BY s.Nombre;
```

---

## 8. Notas Importantes

⚠️ **Seguridad:** El sistema actual usa contraseñas en texto plano. Se recomienda implementar hash de contraseñas (password_hash/password_verify de PHP) en producción.

⚠️ **Backup:** Antes de ejecutar el script SQL, se recomienda hacer un backup de la base de datos.

⚠️ **Usuarios Existentes:** Los usuarios existentes tendrán `IdSucursal = NULL` y podrán entrar en cualquier sucursal hasta que se les asigne una específica.

⚠️ **Vendedores Existentes:** Los vendedores existentes tendrán `IdSucursal = NULL` y sus registros serán visibles para todos los administradores.

---

## 9. Contacto y Soporte

Para cualquier duda o problema con la implementación:
- Revisar los logs de errores de PHP
- Verificar que todas las tablas se crearon correctamente
- Verificar que las columnas IdSucursal existan en UsuariosCRM y VendedoresCRM

---

**Fecha de Implementación:** 2025-11-14
**Versión del Sistema:** 1.1.0 - Sistema de Sucursales
