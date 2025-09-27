-- DEV ONLY: Semilla mínima de sedes (no ejecutar en prod)
INSERT INTO sedes (id, nombre)
VALUES (1, 'Sucursal Principal')
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre);

