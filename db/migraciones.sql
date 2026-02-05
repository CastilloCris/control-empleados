-- Migraciones para historial e incidentes de empleados
-- Ejecutar en la base control_empleados

CREATE TABLE IF NOT EXISTS empleado_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    usuario VARCHAR(100) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    INDEX idx_historial_empleado (empleado_id),
    CONSTRAINT fk_historial_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS empleado_incidentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    dni VARCHAR(20) NOT NULL,
    legajo VARCHAR(50) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incidentes_empleado (empleado_id),
    INDEX idx_incidentes_tipo (tipo),
    CONSTRAINT fk_incidentes_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        ON DELETE CASCADE
);
