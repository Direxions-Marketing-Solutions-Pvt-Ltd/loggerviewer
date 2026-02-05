<?php

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
        $otpPending = $_SESSION['otp_pending_user_id'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['otp'])) {
                // Step 2: Verify OTP
                if ($auth->loginWithOtp((int)$otpPending, $_POST['otp'])) {
                    unset($_SESSION['otp_pending_user_id']);
                    header('Location: index.php?action=dashboard');
                    exit;
                } else {
                    $error = 'Invalid or expired OTP code.';
                }
            } else {
                // Step 1: Initial Login or Password Login
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $loginMode = $_POST['login_mode'] ?? DEFAULT_AUTH_TYPE;
                $user = $auth->getUserByUsername($username);

                if ($user) {
                    if ($loginMode === 'otp' || $user->auth_type === 'otp') {
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
                        if ($auth->login($username, $password)) {
                            header('Location: index.php?action=dashboard');
                            exit;
                        } else {
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

        $folderType = $_GET['type']; // 'webserver' or 'php'
        $filename = $_GET['file'];
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

    case 'dashboard':
    default:
        require_once __DIR__ . '/src/views/dashboard.php';
        break;
}

$content = ob_get_clean();

// Template Wrapper
require_once __DIR__ . '/src/views/layout.php';
