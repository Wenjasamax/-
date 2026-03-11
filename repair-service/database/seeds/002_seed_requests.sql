INSERT INTO requests (id, client_name, phone, address, problem_text, status, assigned_to)
VALUES
  (1, 'Анна Романова', '+79990000001', 'ул. Ленина, 1', 'Не включается стиральная машина', 'new', NULL),
  (2, 'Сергей Павлов', '+79990000002', 'пр-т Мира, 10', 'Течет кран на кухне', 'assigned', 2),
  (3, 'Марина Иванова', '+79990000003', 'ул. Гагарина, 5', 'Шумит кондиционер', 'in_progress', 3)
ON DUPLICATE KEY UPDATE
  client_name = VALUES(client_name),
  phone = VALUES(phone),
  address = VALUES(address),
  problem_text = VALUES(problem_text),
  status = VALUES(status),
  assigned_to = VALUES(assigned_to);
