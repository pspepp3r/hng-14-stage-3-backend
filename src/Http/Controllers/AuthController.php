<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Response;
use App\Services\AuthService;
use App\Models\UserRole;
use Exception;

final class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function githubRedirect(): void
    {
        $clientId = getenv('GITHUB_CLIENT_ID');
        $redirectUri = getenv('GITHUB_REDIRECT_URI');
        $state = bin2hex(random_bytes(16));

        // In a real app, we'd store the state in session or DB to validate it later.
        // For simplicity here, we'll just redirect.

        $url = "https://github.com/login/oauth/authorize?" . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'read:user user:email'
        ]);

        header("Location: $url");
        exit;
    }

    public function githubCallback(): void
    {
        try {
            $code = $_GET['code'] ?? null;
            $codeVerifier = $_GET['code_verifier'] ?? null; // For PKCE (CLI flow)

            if (!$code) {
                Response::error('Authorization code missing', 400)->send();
                return;
            }

            // Determine if this is CLI or Web flow based on code_verifier or other markers
            $isCli = !empty($codeVerifier);
            $clientId = $isCli ? getenv('GITHUB_CLI_CLIENT_ID') : getenv('GITHUB_CLIENT_ID');
            $clientSecret = $isCli ? getenv('GITHUB_CLI_CLIENT_SECRET') : getenv('GITHUB_CLIENT_SECRET');

            // Exchange code for access token
            $tokenResponse = $this->exchangeCodeForToken($code, $clientId, $clientSecret, $codeVerifier);

            if (isset($tokenResponse['error'])) {
                Response::error('GitHub Auth Failed: ' . ($tokenResponse['error_description'] ?? $tokenResponse['error']), 401)->send();
                return;
            }

            $accessToken = $tokenResponse['access_token'];

            // Get user info from GitHub
            $githubUser = $this->getGitHubUser($accessToken);

            // Find or create user in our DB
            $user = $this->authService->findOrCreateUser($githubUser);

            // Generate our own JWTs
            $tokens = $this->authService->generateTokens($user);

            if ($isCli) {
                // Return tokens directly for CLI
                Response::success($tokens)->send();
            } else {
                // Set HTTP-only cookies for Web
                setcookie('access_token', $tokens['access_token'], [
                    'expires' => time() + (int)getenv('JWT_ACCESS_EXPIRY'),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                setcookie('refresh_token', $tokens['refresh_token'], [
                    'expires' => time() + (int)getenv('JWT_REFRESH_EXPIRY'),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);

                // Redirect to frontend dashboard (SPA hash routing)
                header("Location: " . getenv('FRONTEND_URL') . "/#dashboard");
                exit;
            }
        } catch (Exception $e) {
            Response::error($e->getMessage(), 500)->send();
        }
    }

    public function refresh(): void
    {
        $refreshToken = $_POST['refresh_token'] ?? $_COOKIE['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::error('Refresh token missing', 401)->send();
            return;
        }

        $decoded = $this->authService->validateToken($refreshToken);
        if (!$decoded || ($decoded['type'] ?? '') !== 'refresh') {
            Response::error('Invalid or expired refresh token', 401)->send();
            return;
        }

        $user = $this->authService->findUserById($decoded['sub']);
        if (!$user) {
            Response::error('User not found', 401)->send();
            return;
        }

        $tokens = $this->authService->generateTokens($user);

        // Update cookies if they exist
        if (isset($_COOKIE['refresh_token'])) {
            setcookie('access_token', $tokens['access_token'], [
                'expires' => time() + (int)getenv('JWT_ACCESS_EXPIRY'),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            setcookie('refresh_token', $tokens['refresh_token'], [
                'expires' => time() + (int)getenv('JWT_REFRESH_EXPIRY'),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        Response::success($tokens)->send();
    }

    public function logout(): void
    {
        // Clear cookies
        setcookie('access_token', '', time() - 3600, '/');
        setcookie('refresh_token', '', time() - 3600, '/');

        Response::success(['message' => 'Logged out successfully'])->send();
    }

    public function updateRole(string $targetUserId, array $decodedToken): void
    {
        $payload = \json_decode(\file_get_contents('php://input'), true);
        $newRoleStr = $_POST['role'] ?? $payload['role'] ?? null;

        $newRole = UserRole::tryFrom($newRoleStr);

        if (!$newRole) {
            Response::error('Invalid role. Must be admin or analyst', 400)->send();
            return;
        }

        $stmt = $this->authService->getDb()->prepare("UPDATE users SET role = ? WHERE id = UNHEX(REPLACE(?, '-', ''))");
        $stmt->execute([$newRole->value, $targetUserId]);

        if ($stmt->rowCount() === 0) {
            Response::error('User not found or role unchanged', 404)->send();
            return;
        }

        Response::success(['message' => "User role updated to {$newRole->value}"])->send();
    }

    public function me(array $decodedToken): void
    {
        $user = $this->authService->findUserById($decodedToken['sub']);
        if (!$user) {
            Response::error('User not found', 404)->send();
            return;
        }

        // Ensure role is converted to string for JSON output
        if ($user['role'] instanceof UserRole) {
            $user['role'] = $user['role']->value;
        }

        Response::success($user)->send();
    }

    private function exchangeCodeForToken(string $code, string $clientId, string $clientSecret, ?string $codeVerifier = null): array
    {
        $postData = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code
        ];

        // Include code_verifier for PKCE flow (CLI)
        if ($codeVerifier) {
            $postData['code_verifier'] = $codeVerifier;
        }

        $ch = curl_init('https://github.com/login/oauth/access_token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?: [];
    }

    private function getGitHubUser(string $token): array
    {
        $ch = curl_init('https://api.github.com/user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: token ' . $token,
            'User-Agent: Insighta-Labs-App'
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        $user = json_decode($response, true);
        if (!$user || isset($user['message'])) {
            throw new Exception('Failed to fetch GitHub user: ' . ($user['message'] ?? 'Unknown error'));
        }

        return $user;
    }
}
