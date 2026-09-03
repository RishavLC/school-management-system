<?php
/**
 * Auth
 * Handles login/logout, session state, and role-based authorization.
 * IMPORTANT: role is always re-checked from the session (server-side),
 * never trusted from a form field, query string, or hidden input.
 */
class Auth
{
    /** Roles allowed to view each area of the app. Backend source of truth. */
    public const AREA_ROLES = [
        'admin'      => ['super_admin', 'admin', 'principal'],
        'teacher'    => ['teacher'],
        'student'    => ['student'],
        'parent'     => ['parent'],
        'accountant' => ['accountant', 'admin', 'super_admin'],
    ];

    public static function attempt(string $username, string $password): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM users WHERE username = :u1 OR email = :u2 LIMIT 1');
        $stmt->execute(['u1' => $username, 'u2' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }
        if ($user['status'] !== 'active') {
            return ['ok' => false, 'message' => 'This account has been deactivated. Contact the school office.'];
        }
        if (!password_verify($password, $user['password_hash'])) {
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }

        self::login($user);
        return ['ok' => true, 'user' => $user];
    }

    private static function login(array $user): void
    {
        session_regenerate_id(true); // prevent session fixation
        $_SESSION['user_id']  = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['last_activity'] = time();

        $db = Database::connection();
        $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
           ->execute(['id' => $user['id']]);
        self::log((int) $user['id'], 'Logged in');
    }

    public static function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            self::log((int) $_SESSION['user_id'], 'Logged out');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        $cfg = require __DIR__ . '/../config/app.php';
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $cfg['session_lifetime']) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): ?string
    {
        return $_SESSION['role'] ?? null;
    }

    public static function username(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    /** Redirect target after login, based on role. */
    public static function homeFor(string $role): string
    {
        $cfg = require __DIR__ . '/../config/app.php';
        $base = $cfg['base_url'];
        return match ($role) {
            'super_admin', 'admin', 'principal' => "$base/admin/dashboard.php",
            'teacher'    => "$base/teacher/dashboard.php",
            'student'    => "$base/student/dashboard.php",
            'parent'     => "$base/parent/dashboard.php",
            'accountant' => "$base/accountant/dashboard.php",
            default      => "$base/login.php",
        };
    }

    /**
     * Enforce that the logged-in user belongs to one of the given roles.
     * Call this at the TOP of every protected page — never rely on hiding
     * a menu link as the only protection.
     */
    public static function requireRole(array $allowedRoles): void
    {
        if (!self::check()) {
            header('Location: ' . self::loginUrl());
            exit;
        }
        if (!in_array(self::role(), $allowedRoles, true)) {
            http_response_code(403);
            require __DIR__ . '/../includes/error_page.php';
            exit;
        }
    }

    /** Convenience: require the logged-in user to belong to a named app "area". */
    public static function requireArea(string $area): void
    {
        self::requireRole(self::AREA_ROLES[$area] ?? []);
    }

    public static function loginUrl(): string
    {
        $cfg = require __DIR__ . '/../config/app.php';
        return $cfg['base_url'] . '/login.php';
    }

    public static function log(int $userId, string $action): void
    {
        try {
            Database::connection()
                ->prepare('INSERT INTO activity_log (user_id, action) VALUES (:u, :a)')
                ->execute(['u' => $userId, 'a' => $action]);
        } catch (Throwable $e) {
            // Activity logging must never break the request.
            error_log('activity_log insert failed: ' . $e->getMessage());
        }
    }

    /** CSRF token helpers */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $t = htmlspecialchars(self::csrfToken(), ENT_QUOTES);
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"$t\">";
    }

    public static function verifyCsrf(): bool
    {
        $sent = $_POST['csrf_token'] ?? '';
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
    }
}
