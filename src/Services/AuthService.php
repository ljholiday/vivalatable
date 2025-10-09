<?php
declare(strict_types=1);

namespace App\Services;

final class AuthService
{
    /**
     * @return int|null
     */
    public function currentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }

        if (class_exists('\\VT_Auth')) {
            return \VT_Auth::getCurrentUserId() ?: null;
        }

        return null;
    }

    /**
     * @return string|null
     */
    public function currentUserEmail(): ?string
    {
        if (class_exists('\\VT_Auth')) {
            $user = \VT_Auth::getCurrentUser();
            if ($user && isset($user->email)) {
                return (string)$user->email;
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_email']) ? (string)$_SESSION['user_email'] : null;
    }
}
