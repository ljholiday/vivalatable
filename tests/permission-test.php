<?php declare(strict_types=1);

use App\Services\AuthService;

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (!class_exists('VT_Auth')) {
    /**
     * Lightweight stub to simulate the legacy VT_Auth interface for fallback tests.
     */
    final class VT_Auth
    {
        public static ?int $currentUserId = null;
        public static ?object $currentUser = null;

        public static function getCurrentUserId(): ?int
        {
            return self::$currentUserId;
        }

        public static function getCurrentUser(): ?object
        {
            return self::$currentUser;
        }
    }
}

final class AuthServiceTest
{
    private AuthService $auth;
    /** @var array<int, array{status:string,message:string}> */
    private array $results = [];

    public function __construct()
    {
        $service = vt_service('auth.service');
        if (!$service instanceof AuthService) {
            throw new RuntimeException('auth.service did not resolve to App\Services\AuthService.');
        }

        $this->auth = $service;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        echo "Running AuthService tests...\n\n";

        $this->resetSession();
        $this->testSessionUserId();

        $this->resetSession();
        $this->testSessionEmail();

        $this->resetSession();
        $this->testLegacyFallbackId();

        $this->resetSession();
        $this->testLegacyFallbackEmail();

        $this->resetSession();
        $this->testEmptyState();

        $this->report();
    }

    private function testSessionUserId(): void
    {
        echo "Checking session-based currentUserId... ";
        $_SESSION['user_id'] = '123';

        $value = $this->auth->currentUserId();
        if ($value === 123) {
            $this->pass('currentUserId returns session user_id as int.');
        } else {
            $this->fail('Expected 123, got ' . var_export($value, true));
        }
    }

    private function testSessionEmail(): void
    {
        echo "Checking session-based currentUserEmail... ";
        $_SESSION['user_email'] = 'session@example.com';

        $value = $this->auth->currentUserEmail();
        if ($value === 'session@example.com') {
            $this->pass('currentUserEmail returns session email.');
        } else {
            $this->fail('Expected session@example.com, got ' . var_export($value, true));
        }
    }

    private function testLegacyFallbackId(): void
    {
        echo "Checking legacy fallback for currentUserId... ";
        VT_Auth::$currentUserId = 456;

        $value = $this->auth->currentUserId();
        if ($value === 456) {
            $this->pass('currentUserId falls back to VT_Auth when session empty.');
        } else {
            $this->fail('Expected 456, got ' . var_export($value, true));
        }
    }

    private function testLegacyFallbackEmail(): void
    {
        echo "Checking legacy fallback for currentUserEmail... ";
        VT_Auth::$currentUser = (object)['email' => 'legacy@example.com'];

        $value = $this->auth->currentUserEmail();
        if ($value === 'legacy@example.com') {
            $this->pass('currentUserEmail falls back to VT_Auth user.');
        } else {
            $this->fail('Expected legacy@example.com, got ' . var_export($value, true));
        }
    }

    private function testEmptyState(): void
    {
        echo "Checking empty state returns nulls... ";
        VT_Auth::$currentUserId = null;
        VT_Auth::$currentUser = null;

        $id = $this->auth->currentUserId();
        $email = $this->auth->currentUserEmail();

        if ($id === null && $email === null) {
            $this->pass('AuthService returns null when no session or legacy user is present.');
        } else {
            $this->fail('Expected null/null, got ' . var_export([$id, $email], true));
        }
    }

    private function pass(string $message): void
    {
        echo "PASS\n";
        $this->results[] = ['status' => 'PASS', 'message' => $message];
    }

    private function fail(string $message): void
    {
        echo "FAIL\n";
        $this->results[] = ['status' => 'FAIL', 'message' => $message];
    }

    private function report(): void
    {
        echo "\n" . str_repeat('=', 42) . "\n";
        echo "AuthService Test Results\n";
        echo str_repeat('=', 42) . "\n";

        foreach ($this->results as $result) {
            echo "{$result['status']}: {$result['message']}\n";
        }

        $failed = array_filter($this->results, static fn($r) => $r['status'] === 'FAIL');
        echo "\n";
        $hasFailures = $failed !== [];
        if (!$hasFailures) {
            echo "AuthService behavior looks good.\n";
        } else {
            echo "Investigate the failing assertions above.\n";
        }
        exit($hasFailures ? 1 : 0);
    }

    private function resetSession(): void
    {
        $_SESSION = [];
        VT_Auth::$currentUserId = null;
        VT_Auth::$currentUser = null;
    }
}

(new AuthServiceTest())->run();
