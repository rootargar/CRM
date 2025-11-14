TABLE SeguimientosCRM ( IdSeguimiento INT PRIMARY KEY IDENTITY(1,1), IdCliente INT NOT NULL, IdVendedor INT NOT NULL, Tipo NVARCHAR(10) NOT NULL CHECK (Tipo IN ('Visita', 'Llamada')), Fecha DATETIME NOT NULL, Motivo NVARCHAR(255), Resultado NVARCHAR(255), Observaciones NVARCHAR(MAX), ProximaAccion DATETIME, Estado NVARCHAR(20) NOT NULL DEFAULT 'Pendiente', FechaRegistro DATETIME DEFAULT GETDATE(), FOREIGN KEY (IdCliente) REFERENCES ClientesCRM(IdCliente), FOREIGN KEY (IdVendedor) REFERENCES VendedoresCRM(IdVendedor) );
ClientesCRM (
    IdCliente INT PRIMARY KEY IDENTITY(1,1),
    Nombre NVARCHAR(100) NOT NULL,
    Telefono NVARCHAR(50),
    Email NVARCHAR(100),
    Activo BIT DEFAULT 1
);

 VendedoresCRM (
    IdVendedor INT PRIMARY KEY IDENTITY(1,1),
    Nombre NVARCHAR(100) NOT NULL,
    Activo BIT DEFAULT 1
);
ClientesVendedoresCRM (
    IdCliente INT NOT NULL,
    IdVendedor INT NOT NULL,
    FechaAsignacion DATETIME DEFAULT GETDATE(),
    Activo BIT DEFAULT 1,
    PRIMARY KEY (IdCliente, IdVendedor),
    FOREIGN KEY (IdCliente) REFERENCES ClientesCRM(IdCliente),
    FOREIGN KEY (IdVendedor) REFERENCES VendedoresCRM(IdVendedor)
);
 UsuariosCRM (
    IdUsuario INT PRIMARY KEY IDENTITY(1,1),
    Usuario NVARCHAR(50) UNIQUE NOT NULL,
    Clave NVARCHAR(255) NOT NULL,
    Nombre NVARCHAR(100),
    Rol NVARCHAR(50), -- 'admin', 'vendedor', etc.
	IdVendedor INT NULL,    FOREIGN KEY (IdVendedor) REFERENCES VendedoresCRM(IdVendedor),
    Activo BIT DEFAULT 1
);
ALTER TABLE UsuariosCRM
ADD IdVendedor INT NULL,
    FOREIGN KEY (IdVendedor) REFERENCES VendedoresCRM(IdVendedor);
CREATE TABLE HistorialAcciones (
    IdHistorial INT PRIMARY KEY IDENTITY(1,1),
    IdUsuario INT,
    TablaAfectada NVARCHAR(100),
    TipoAccion NVARCHAR(50), -- 'INSERT', 'UPDATE', 'DELETE'
    FechaAccion DATETIME DEFAULT GETDATE(),
    Detalles NVARCHAR(MAX),
    FOREIGN KEY (IdUsuario) REFERENCES Usuarios(IdUsuario)
);