INSERT INTO users (id, name, role, password_hash)
VALUES
  (1, 'Ирина Диспетчер', 'dispatcher', NULL),
  (2, 'Павел Мастер', 'master', NULL),
  (3, 'Олег Мастер', 'master', NULL)
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role);
