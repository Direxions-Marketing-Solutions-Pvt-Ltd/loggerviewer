<?php
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/plain');
    die("Error: Logger View requires PHP 8.1 or higher (PHP 8.4 recommended). Your current environment is running PHP " . PHP_VERSION . ".\nPlease upgrade your PHP version or check your web server configuration (e.g., fastcgi_pass).");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/autoload.php';

use App\Database;
use App\Auth;
use App\Project;
use App\LogReader;

$db = new Database(DB_PATH);
$auth = new Auth($db);
$projectManager = new Project($db);

$action = $_GET['action'] ?? 'dashboard';

// Simple Router
if (!Auth::isLoggedIn() && $action !== 'login') {
    header('Location: index.php?action=login');
    exit;
}

// Force password change for default admin credentials
if (Auth::isLoggedIn() && !empty($_SESSION['force_password_change'])) {
    $allowed = ['change_password', 'logout'];
    if (!in_array($action, $allowed, true) && strpos($action, 'api_') !== 0) {
        header('Location: index.php?action=change_password');
        exit;
    }
}

// OWASP: CSRF Protection for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Auth::verifyCsrfToken($token)) {
        header('HTTP/1.1 403 Forbidden');
        die('Invalid CSRF Token');
    }
}

ob_start();

switch ($action) {
    case 'login':
        $error = null;
        if (isset($_GET['reset']) && $_GET['reset'] === '1') {
            unset($_SESSION['otp_pending_user_id']);
        }
        $otpPending = $_SESSION['otp_pending_user_id'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['otp'])) {
                // Step 2: Verify OTP
                $otpIdentifier = 'otp:' . (int)$otpPending . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
                if ($auth->isLockedOut($otpIdentifier)) {
                    $error = 'Too many failed attempts. Please try again later.';
                } elseif ($auth->loginWithOtp((int)$otpPending, $_POST['otp'])) {
                    $auth->clearAttempts($otpIdentifier);
                    unset($_SESSION['otp_pending_user_id']);
                    header('Location: index.php?action=dashboard');
                    exit;
                } else {
                    $auth->recordFailure($otpIdentifier);
                    $error = 'Invalid or expired OTP code.';
                }
            } else {
                // Step 1: Initial Login or Password Login
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $loginMode = $_POST['login_mode'] ?? DEFAULT_AUTH_TYPE;
                $user = $auth->getUserByUsername($username);

                if ($user) {
                    $otpSendIdentifier = $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
                    if ($auth->isLockedOut($otpSendIdentifier)) {
                        $error = 'Too many failed attempts. Please try again later.';
                    } elseif ($user->auth_type !== 'password' && ($loginMode === 'otp' || $user->auth_type === 'otp')) {
                        if (empty($user->email)) {
                            $error = 'No email configured for this user. Cannot send OTP.';
                        } else {
                            $userManager = new \App\User($db);
                            $otp = $userManager->generateOtp($user->id);
                            if ($otp && \App\Mailer::send($user->email, "Login OTP", "Your login OTP is: <b>$otp</b>")) {
                                $_SESSION['otp_pending_user_id'] = $user->id;
                                $otpPending = $user->id;
                            } else {
                                $error = 'Failed to send OTP email. Please check SMTP configuration.';
                            }
                        }
                    } else {
                        // Password Login
                        $identifier = $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
                        if ($auth->isLockedOut($identifier)) {
                            $error = 'Too many failed attempts. Please try again later.';
                        } elseif ($auth->login($username, $password)) {
                            $auth->clearAttempts($identifier);
                            header('Location: index.php?action=dashboard');
                            exit;
                        } else {
                            $auth->recordFailure($identifier);
                            $error = 'Invalid username or password';
                        }
                    }
                } else {
                    $error = 'Invalid username or password';
                }
            }
        }
        require __DIR__ . '/src/views/login.php';
        break;
    
    case 'logout':
        $auth->logout();
        header('Location: index.php');
        exit;

    case 'users':
        if (!\App\Auth::isAdmin()) {
            header('Location: index.php');
            exit;
        }
        $userManager = new \App\User($db);
        require_once __DIR__ . '/src/views/users.php';
        break;

    case 'project':
        require_once __DIR__ . '/src/views/project_detail.php';
        break;

    case 'view_log':
        require_once __DIR__ . '/src/views/log_viewer.php';
        break;

    case 'settings':
        if (!\App\Auth::isAdmin()) {
            header('Location: index.php');
            exit;
        }
        require_once __DIR__ . '/src/views/settings.php';
        break;

    case 'analytics':
        $projectId = (int)($_GET['project_id'] ?? 0);
        if ($projectId > 0) {
            if (!\App\Auth::hasAccess($projectId)) {
                header('Location: index.php');
                exit;
            }
        } else {
            if (!\App\Auth::isAdmin()) {
                header('Location: index.php');
                exit;
            }
        }
        require_once __DIR__ . '/src/views/analytics.php';
        break;

    case 'changelog':
        require_once __DIR__ . '/src/views/changelog.php';
        break;

    case 'download_raw':
        $projectId = (int)($_GET['project_id'] ?? 0);
        $file = $_GET['file'] ?? '';
        
        $project = $projectManager->getById($projectId);
        if (!$project || !Auth::hasAccess($project->id)) {
            die("Unauthorized or project not found.");
        }

        $reader = new LogReader($project->webserver_path, $project->webserver_format);
        $filePath = null;
        foreach ($reader->getLogFiles() as $f) {
            if ($f['name'] === $file) {
                $filePath = $f['path'];
                break;
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            $reader = new LogReader($project->php_path, $project->php_format);
            foreach ($reader->getLogFiles() as $f) {
                if ($f['name'] === $file) {
                    $filePath = $f['path'];
                    break;
                }
            }
        }

        if ($filePath && file_exists($filePath)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        } else {
            die("File not found.");
        }
        break;

    case 'api_logs':
        header('Content-Type: application/json');
        $projectId = (int)$_GET['project_id'];
        if (!Auth::hasAccess($projectId)) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $folderType = $_GET['type'] ?? ''; // 'webserver' or 'php'
        if (!in_array($folderType, ['webserver', 'php'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid log type']);
            exit;
        }
        $filename = $_GET['file'] ?? '';
        if ($filename === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing file']);
            exit;
        }
        $offset = (int)($_GET['offset'] ?? 0);
        $filter = $_GET['filter'] ?? 'all';
        $query = $_GET['q'] ?? '';

        $project = $projectManager->getById($projectId);
        $dir = ($folderType === 'webserver') ? $project->webserver_path : $project->php_path;
        $format = ($folderType === 'webserver') ? $project->webserver_format : $project->php_format;

        $reader = new LogReader($dir, $format);
        $result = $reader->readLog($filename, $offset, 100, $filter, $query);
        
        // Add total count
        $result['total'] = $reader->getTotalLines($filename, $filter);
        
        echo json_encode($result);
        exit;

    case 'api_ai_ask':
        try {
            header('Content-Type: application/json');
            if (!defined('AI_ENABLED') || !AI_ENABLED) {
                echo json_encode(['error' => 'AI is disabled']);
                exit;
            }

            $projectId = (int)($_POST['project_id'] ?? 0);
            if ($projectId > 0 && !Auth::hasAccess($projectId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $errorMessage = $_POST['error_message'] ?? '';
            if (empty($errorMessage)) {
                echo json_encode(['error' => 'No error message provided']);
                exit;
            }

            $cache = new \App\Cache();
            $cacheKey = 'ai_analysis_' . md5($errorMessage);
            
            $cachedResponse = $cache->get($cacheKey);
            if ($cachedResponse) {
                echo json_encode(['analysis' => $cachedResponse, 'cached' => true]);
                exit;
            }

            $ai = new \App\AI();
            $analysis = $ai->analyzeError($errorMessage);
            
            if ($analysis) {
                $cache->set($cacheKey, $analysis, 86400 * 7); // Cache for 7 days
            }

            echo json_encode(['analysis' => $analysis, 'cached' => false]);
        } catch (\Throwable $e) {
            error_log("API AI ASK Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error: ' . $e->getMessage()]);
        }
        exit;

    case 'change_password':
        if (!\App\Auth::isLoggedIn()) {
            header('Location: index.php?action=login');
            exit;
        }
        $cpError = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $new = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';
            if (strlen($new) < 8) {
                $cpError = 'Password must be at least 8 characters.';
            } elseif ($new !== $confirm) {
                $cpError = 'Passwords do not match.';
            } else {
                $um = new \App\User($db);
                if ($um->update((int)$_SESSION['user_id'], ['password' => $new])) {
                    unset($_SESSION['force_password_change']);
                    header('Location: index.php?action=dashboard&msg=password_changed');
                    exit;
                } else {
                    $cpError = 'Failed to update password.';
                }
            }
        }
        require __DIR__ . '/src/views/change_password.php';
        break;

    case 'dashboard':
    default:
        require_once __DIR__ . '/src/views/dashboard.php';
        break;
}

$content = ob_get_clean();

// Template Wrapper
require_once __DIR__ . '/src/views/layout.php';
