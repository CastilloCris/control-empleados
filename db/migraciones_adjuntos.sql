-- Migraciones para fotos y adjuntos de empleados

ALTER TABLE empleados
ADD COLUMN foto VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS empleado_adjuntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    tamano INT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_adjuntos_empleado (empleado_id),
    CONSTRAINT fk_adjuntos_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        ON DELETE CASCADE
);
