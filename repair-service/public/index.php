<?php

declare(strict_types=1);

use App\Auth;
use App\Database;
use App\RequestService;

require_once __DIR__ . '/../vendor/autoload.php';

session_start();

$pdo = Database::connection();
$auth = new Auth($pdo);
$requests = new RequestService($pdo);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function redirect(string $url): void
{
	header('Location: ' . $url);
	exit;
}

function flash(string $type, string $message): void
{
	$_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
	$f = $_SESSION['flash'] ?? null;
	unset($_SESSION['flash']);
	return $f;
}

function currentUser(Auth $auth): ?array
{
	$id = $_SESSION['user_id'] ?? null;
	if (!$id) {
		return null;
	}

	return $auth->userById((int) $id);
}

function requireRole(Auth $auth, string $role): array
{
	$user = currentUser($auth);
	if (!$user || $user['role'] !== $role) {
		flash('err', 'Недостаточно прав для доступа.');
		redirect('/login');
	}

	return $user;
}

function page(string $title, string $content, ?array $user = null): void
{
	$flash = getFlash();
	echo '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<title>' . htmlspecialchars($title) . '</title>';
	echo '<link rel="stylesheet" href="/style.css"></head><body><div class="container">';
	echo '<div class="card"><h1>Заявки в ремонтную службу</h1><nav>';
	echo '<a href="/request/create">Создать заявку</a>';
	echo '<a href="/dispatcher">Панель диспетчера</a>';
	echo '<a href="/master">Панель мастера</a>';
	echo '<a href="/login">Вход</a>';
	if ($user) {
		echo '<span class="small">Вы вошли как: ' . htmlspecialchars($user['name']) . ' (' . htmlspecialchars($user['role']) . ')</span> ';
		echo '<a href="/logout">Выйти</a>';
	}
	echo '</nav></div>';
	if ($flash) {
		echo '<div class="alert ' . htmlspecialchars($flash['type']) . '">' . htmlspecialchars($flash['message']) . '</div>';
	}
	echo $content;
	echo '</div></body></html>';
}

if ($path === '/style.css') {
	$css = __DIR__ . '/style.css';
	if (is_file($css)) {
		header('Content-Type: text/css; charset=UTF-8');
		readfile($css);
		exit;
	}
}

if ($path === '/logout') {
	session_destroy();
	redirect('/login');
}

if ($path === '/login' && $method === 'POST') {
	$userId = (int) ($_POST['user_id'] ?? 0);
	$user = $auth->userById($userId);
	if (!$user) {
		flash('err', 'Пользователь не найден.');
		redirect('/login');
	}

	$_SESSION['user_id'] = $userId;
	if ($user['role'] === 'dispatcher') {
		redirect('/dispatcher');
	}
	redirect('/master');
}

if ($path === '/login' || $path === '/') {
	$users = $auth->listUsers();
	ob_start();
	echo '<div class="card"><h2>Вход</h2><form method="post" action="/login">';
	echo '<label>Пользователь<select name="user_id" required><option value="">Выберите пользователя</option>';
	foreach ($users as $u) {
		echo '<option value="' . (int) $u['id'] . '">' . htmlspecialchars($u['name']) . ' (' . htmlspecialchars($u['role']) . ')</option>';
	}
	echo '</select></label><button type="submit">Войти</button></form></div>';
	$content = (string) ob_get_clean();
	page('Вход', $content, currentUser($auth));
	exit;
}

if ($path === '/request/store' && $method === 'POST') {
	$payload = [
		'clientName' => trim((string) ($_POST['clientName'] ?? '')),
		'phone' => trim((string) ($_POST['phone'] ?? '')),
		'address' => trim((string) ($_POST['address'] ?? '')),
		'problemText' => trim((string) ($_POST['problemText'] ?? '')),
	];

	foreach ($payload as $value) {
		if ($value === '') {
			flash('err', 'Все поля заявки обязательны.');
			redirect('/request/create');
		}
	}

	$id = $requests->create($payload);
	flash('ok', 'Заявка #' . $id . ' создана со статусом new.');
	redirect('/request/create');
}

if ($path === '/request/create') {
	ob_start();
	echo '<div class="card"><h2>Создание заявки</h2><form method="post" action="/request/store">';
	echo '<div class="grid">';
	echo '<label>Клиент<input name="clientName" required></label>';
	echo '<label>Телефон<input name="phone" required></label>';
	echo '<label>Адрес<input name="address" required></label>';
	echo '</div>';
	echo '<label>Описание проблемы<textarea name="problemText" required></textarea></label>';
	echo '<button type="submit">Создать заявку</button></form></div>';
	$content = (string) ob_get_clean();
	page('Создание заявки', $content, currentUser($auth));
	exit;
}

if ($path === '/dispatcher/assign' && $method === 'POST') {
	$dispatcher = requireRole($auth, 'dispatcher');
	$requestId = (int) ($_POST['request_id'] ?? 0);
	$masterId = (int) ($_POST['master_id'] ?? 0);

	if ($requestId <= 0 || $masterId <= 0) {
		flash('err', 'Некорректные параметры назначения.');
		redirect('/dispatcher');
	}

	$ok = $requests->assign($requestId, $masterId, (int) $dispatcher['id']);
	flash($ok ? 'ok' : 'err', $ok ? 'Мастер назначен.' : 'Назначение не выполнено (возможно, заявка уже обработана).');
	redirect('/dispatcher');
}

if ($path === '/dispatcher/cancel' && $method === 'POST') {
	$dispatcher = requireRole($auth, 'dispatcher');
	$requestId = (int) ($_POST['request_id'] ?? 0);
	$ok = $requests->cancel($requestId, (int) $dispatcher['id']);
	flash($ok ? 'ok' : 'err', $ok ? 'Заявка отменена.' : 'Отмена невозможна для текущего статуса.');
	redirect('/dispatcher');
}

if ($path === '/dispatcher') {
	$user = requireRole($auth, 'dispatcher');
	$status = $_GET['status'] ?? null;
	$status = is_string($status) && $status !== '' ? $status : null;
	$masters = $auth->masters();
	$items = $requests->listForDispatcher($status);

	ob_start();
	echo '<div class="card"><h2>Панель диспетчера</h2><form method="get" action="/dispatcher" class="row">';
	echo '<select name="status"><option value="">Все статусы</option>';
	foreach (['new', 'assigned', 'in_progress', 'done', 'canceled'] as $s) {
		$selected = $status === $s ? ' selected' : '';
		echo '<option value="' . $s . '"' . $selected . '>' . $s . '</option>';
	}
	echo '</select><button type="submit">Фильтровать</button></form></div>';

	echo '<div class="card table-wrap"><table><thead><tr><th>ID</th><th>Клиент</th><th>Контакты</th><th>Проблема</th><th>Статус</th><th>Мастер</th><th>Действия</th></tr></thead><tbody>';
	foreach ($items as $item) {
		echo '<tr>';
		echo '<td>' . (int) $item['id'] . '</td>';
		echo '<td>' . htmlspecialchars($item['client_name']) . '</td>';
		echo '<td>' . htmlspecialchars($item['phone']) . '<br>' . htmlspecialchars($item['address']) . '</td>';
		echo '<td>' . htmlspecialchars($item['problem_text']) . '</td>';
		echo '<td><span class="badge">' . htmlspecialchars($item['status']) . '</span></td>';
		echo '<td>' . htmlspecialchars((string) ($item['assigned_master'] ?? '')) . '</td>';
		echo '<td>';

		if ($item['status'] === 'new') {
			echo '<form method="post" action="/dispatcher/assign" class="row">';
			echo '<input type="hidden" name="request_id" value="' . (int) $item['id'] . '">';
			echo '<select name="master_id" required><option value="">Мастер</option>';
			foreach ($masters as $m) {
				echo '<option value="' . (int) $m['id'] . '">' . htmlspecialchars($m['name']) . '</option>';
			}
			echo '</select><button type="submit">Назначить</button></form>';
		}

		if (in_array($item['status'], ['new', 'assigned'], true)) {
			echo '<form method="post" action="/dispatcher/cancel" style="margin-top:8px;">';
			echo '<input type="hidden" name="request_id" value="' . (int) $item['id'] . '">';
			echo '<button type="submit" class="danger">Отменить</button></form>';
		}

		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
	$content = (string) ob_get_clean();
	page('Панель диспетчера', $content, $user);
	exit;
}

if ($path === '/master/take' && $method === 'POST') {
	$master = requireRole($auth, 'master');
	$requestId = (int) ($_POST['request_id'] ?? 0);
	$ok = $requests->takeInWork($requestId, (int) $master['id']);

	flash($ok ? 'ok' : 'err', $ok ? 'Заявка взята в работу.' : 'Заявка уже взята или недоступна.');
	redirect('/master');
}

if ($path === '/master/done' && $method === 'POST') {
	$master = requireRole($auth, 'master');
	$requestId = (int) ($_POST['request_id'] ?? 0);
	$ok = $requests->complete($requestId, (int) $master['id']);

	flash($ok ? 'ok' : 'err', $ok ? 'Заявка завершена.' : 'Завершение доступно только из in_progress.');
	redirect('/master');
}

if ($path === '/api/master/take' && $method === 'POST') {
	$master = requireRole($auth, 'master');
	$requestId = (int) ($_POST['request_id'] ?? 0);
	$ok = $requests->takeInWork($requestId, (int) $master['id']);

	if ($ok) {
		http_response_code(200);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode(['ok' => true, 'message' => 'Taken']);
		exit;
	}

	http_response_code(409);
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode(['ok' => false, 'message' => 'Already taken or unavailable']);
	exit;
}

if ($path === '/master') {
	$master = requireRole($auth, 'master');
	$items = $requests->listForMaster((int) $master['id']);

	ob_start();
	echo '<div class="card"><h2>Панель мастера</h2><p class="small">Заявки, назначенные текущему мастеру.</p></div>';
	echo '<div class="card table-wrap"><table><thead><tr><th>ID</th><th>Клиент</th><th>Контакты</th><th>Проблема</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
	foreach ($items as $item) {
		echo '<tr>';
		echo '<td>' . (int) $item['id'] . '</td>';
		echo '<td>' . htmlspecialchars($item['client_name']) . '</td>';
		echo '<td>' . htmlspecialchars($item['phone']) . '<br>' . htmlspecialchars($item['address']) . '</td>';
		echo '<td>' . htmlspecialchars($item['problem_text']) . '</td>';
		echo '<td><span class="badge">' . htmlspecialchars($item['status']) . '</span></td>';
		echo '<td>';
		if ($item['status'] === 'assigned') {
			echo '<form method="post" action="/master/take">';
			echo '<input type="hidden" name="request_id" value="' . (int) $item['id'] . '">';
			echo '<button type="submit">Взять в работу</button></form>';
		}
		if ($item['status'] === 'in_progress') {
			echo '<form method="post" action="/master/done">';
			echo '<input type="hidden" name="request_id" value="' . (int) $item['id'] . '">';
			echo '<button type="submit">Завершить</button></form>';
		}
		echo '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
	$content = (string) ob_get_clean();
	page('Панель мастера', $content, $master);
	exit;
}

http_response_code(404);
page('404', '<div class="card"><h2>Страница не найдена</h2></div>', currentUser($auth));
