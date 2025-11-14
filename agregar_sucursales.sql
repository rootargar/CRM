-- ==================================================
-- Script para agregar el concepto de Sucursales al CRM
-- ==================================================

-- 1. Crear la tabla de Sucursales con el catálogo completo
CREATE TABLE SucursalesCRM (
    IdSucursal INT PRIMARY KEY IDENTITY(1,1),
    Nombre NVARCHAR(100) NOT NULL UNIQUE,
    Activo BIT DEFAULT 1
);

-- 2. Insertar el catálogo de sucursales
INSERT INTO SucursalesCRM (Nombre, Activo) VALUES
('Matriz', 1),
('Mazatlan', 1),
('Mochis', 1),
('Guasave', 1),
('Guamuchil', 1),
('TRP Mazatlan', 1);

-- 3. Agregar columna IdSucursal a la tabla UsuariosCRM
ALTER TABLE UsuariosCRM
ADD IdSucursal INT NULL;

-- 4. Agregar la relación de llave foránea
ALTER TABLE UsuariosCRM
ADD CONSTRAINT FK_UsuariosCRM_Sucursales
    FOREIGN KEY (IdSucursal) REFERENCES SucursalesCRM(IdSucursal);

-- 5. (OPCIONAL) Asignar una sucursal por defecto a usuarios existentes
-- Descomenta la siguiente línea si deseas asignar "Matriz" a todos los usuarios existentes
-- UPDATE UsuariosCRM SET IdSucursal = 1 WHERE IdSucursal IS NULL;

-- 6. Agregar columna IdSucursal a VendedoresCRM
-- Esto permite filtrar vendedores y sus registros por sucursal
ALTER TABLE VendedoresCRM
ADD IdSucursal INT NULL;

ALTER TABLE VendedoresCRM
ADD CONSTRAINT FK_VendedoresCRM_Sucursales
    FOREIGN KEY (IdSucursal) REFERENCES SucursalesCRM(IdSucursal);

-- 7. (OPCIONAL) Agregar columna IdSucursal a ClientesCRM
-- Si deseas que los clientes también tengan sucursal asignada:
/*
ALTER TABLE ClientesCRM
ADD IdSucursal INT NULL;

ALTER TABLE ClientesCRM
ADD CONSTRAINT FK_ClientesCRM_Sucursales
    FOREIGN KEY (IdSucursal) REFERENCES SucursalesCRM(IdSucursal);
*/

-- ==================================================
-- Consultas útiles para verificar los cambios
-- ==================================================

-- Ver todas las sucursales
-- SELECT * FROM SucursalesCRM;

-- Ver usuarios con sus sucursales asignadas
-- SELECT u.IdUsuario, u.Usuario, u.Nombre, u.Rol, s.Nombre as Sucursal
-- FROM UsuariosCRM u
-- LEFT JOIN SucursalesCRM s ON u.IdSucursal = s.IdSucursal;
