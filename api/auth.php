<?php
define('KEEP_SESSION_OPEN', true);   // auth.php writes the session (login / register / logout)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/district_registry.php';

$pdo = get_db();
$action = $_GET['action'] ?? '';

// ============================================================
// POST ?action=register  { name, email, password, preferred_dialect }
// ============================================================
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';
    $dialect = trim($input['preferred_dialect'] ?? 'Chattogram');
    $district = $input['current_district'] ?? null;
    $spoken = $input['spoken_language'] ?? null;
    if ($spoken === '') $spoken = null;
    if ($spoken !== null && !vs_valid_language($spoken)) { http_response_code(400); echo json_encode(['error'=>'Unknown spoken language']); exit; }
    if ($district === '') $district = null;
    if ($district !== null && !vs_valid_district($district)) { http_response_code(400); echo json_encode(['error'=>'Unknown district']); exit; }
    if (!valid_dialect($dialect)) $dialect = 'Chattogram';
    $name = mb_substr(strip_tags($name), 0, 80);

    if ($name === '' || $email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Name, email, and password are required']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email address']);
        exit;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'An account with this email already exists']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password_hash, preferred_dialect, current_district, spoken_language) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $email, $hash, $dialect, $district, $spoken]);
        $userId = (int) $pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;

        echo json_encode([
            'success' => true,
            'user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'preferred_dialect' => $dialect, 'current_district' => $district, 'spoken_language' => $spoken],
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Registration failed', 'detail' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST ?action=login  { email, password }
// ============================================================
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';

    if ($email === '' || $password === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash, preferred_dialect, current_district, spoken_language FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Incorrect email or password']);
            exit;
        }

        $_SESSION['user_id'] = (int) $user['id'];

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'preferred_dialect' => $user['preferred_dialect'],
                'current_district' => $user['current_district'],
                'spoken_language' => $user['spoken_language'],
            ],
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Login failed', 'detail' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST ?action=logout
// ============================================================
if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// ============================================================
// GET ?action=me  -> current logged-in user, or null
// ============================================================
if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $userId = current_user_id();
    if ($userId === null) {
        echo json_encode(['success' => true, 'user' => null]);
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, preferred_dialect, current_district, spoken_language FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        echo json_encode(['success' => true, 'user' => $user ?: null]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not fetch user', 'detail' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// POST ?action=update_dialect  { preferred_dialect }  (requires login)
// ============================================================
if ($action === 'update_dialect' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $input = json_decode(file_get_contents('php://input'), true);
    $dialect = trim($input['preferred_dialect'] ?? '');
    if ($dialect === '' || !valid_dialect($dialect)) {
        http_response_code(400);
        echo json_encode(['error' => 'Choose one of the supported dialects']);
        exit;
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET preferred_dialect = ? WHERE id = ?");
        $stmt->execute([$dialect, current_user_id()]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed', 'detail' => $e->getMessage()]);
    }
    exit;
}

// Profile spoken language is independent from district and translator settings.
if ($action === 'update_spoken_language' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $language = $input['spoken_language'] ?? null;
    if ($language === '') $language = null;
    if ($language !== null && !vs_valid_language($language)) { http_response_code(400); echo json_encode(['error'=>'Unsupported language']); exit; }
    $stmt = $pdo->prepare('UPDATE users SET spoken_language=? WHERE id=?');
    $stmt->execute([$language, current_user_id()]);
    echo json_encode(['success'=>true,'spoken_language'=>$language]);
    exit;
}
// Account district is explicitly chosen; never automatically overwrites preferred dialect.
if ($action === 'update_district' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $district = $input['current_district'] ?? null;
    if ($district === '') $district = null;
    if ($district !== null && !vs_valid_district($district)) { http_response_code(400); echo json_encode(['error'=>'Unknown district']); exit; }
    $stmt = $pdo->prepare('UPDATE users SET current_district=? WHERE id=?');
    $stmt->execute([$district, current_user_id()]);
    echo json_encode(['success'=>true,'current_district'=>$district]);
    exit;
}
http_response_code(400);
echo json_encode(['error' => 'Unknown or missing action']);
