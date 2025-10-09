<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Database\Database;
use App\Http\Request;
use App\Services\AuthService;

require_once dirname(__DIR__, 3) . '/templates/_helpers.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-community-manager.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-community-display.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-event-manager.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-guest-manager.php';

final class InvitationApiController
{
    public function __construct(private Database $database, private AuthService $auth)
    {
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function sendCommunity(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_nonce')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $community = $this->database->pdo()->prepare('SELECT id, name FROM vt_communities WHERE id = :id LIMIT 1');
        $community->execute([':id' => $communityId]);
        $communityRow = $community->fetch(\PDO::FETCH_ASSOC);
        if ($communityRow === false) {
            return $this->error('Community not found.', 404);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->error('You do not have permission to send invitations.', 403);
        }

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email address required.', 422);
        }

        $manager = new \VT_Community_Manager();
        $result = $manager->sendInvitation($communityId, [
            'invited_email' => $email,
            'personal_message' => $message,
        ]);

        if (is_vt_error($result)) {
            return $this->error($result->getErrorMessage(), 400);
        }

        return $this->success([
            'message' => 'Invitation sent successfully!',
        ], 201);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listCommunity(int $communityId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_community_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->error('You do not have permission to view invitations.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, invited_email, status, created_at
             FROM vt_community_invitations
             WHERE community_id = :community_id
             ORDER BY created_at DESC"
        );
        $stmt->execute([':community_id' => $communityId]);
        $invitations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->success([
            'invitations' => $invitations,
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function deleteCommunity(int $communityId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_community_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->error('You do not have permission to cancel invitations.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM vt_community_invitations
             WHERE id = :id AND community_id = :community_id"
        );
        $success = $stmt->execute([
            ':id' => $invitationId,
            ':community_id' => $communityId,
        ]);

        if ($success === false || $stmt->rowCount() === 0) {
            return $this->error('Failed to cancel invitation.', 400);
        }

        return $this->success(['message' => 'Invitation cancelled successfully.']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function sendEvent(int $eventId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_nonce')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $eventStmt = $this->database->pdo()->prepare(
            "SELECT id, title, author_id FROM vt_events WHERE id = :id LIMIT 1"
        );
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);

        if ($event === false) {
            return $this->error('Event not found.', 404);
        }

        if ((int)$event['author_id'] !== $viewerId && !$this->auth->currentUserCan('edit_others_posts')) {
            return $this->error('Only the event host can send invitations.', 403);
        }

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('Valid email address required.', 422);
        }

        $guestManager = new \VT_Guest_Manager();

        $db = $this->database->pdo();
        $existing = $db->prepare(
            "SELECT id FROM vt_guests WHERE event_id = :event_id AND email = :email AND status != 'declined'"
        );
        $existing->execute([
            ':event_id' => $eventId,
            ':email' => $email,
        ]);
        if ($existing->fetchColumn()) {
            return $this->error('This email has already been invited.', 400);
        }

        $result = $guestManager->sendRsvpInvitation(
            $eventId,
            $email,
            $this->auth->getCurrentUser()->display_name ?? '',
            $message
        );

        if (is_vt_error($result)) {
            return $this->error($result->getErrorMessage(), 400);
        }

        if (!is_array($result) || empty($result['success'])) {
            return $this->error('Failed to create invitation.', 400);
        }

        $responseMessage = 'RSVP invitation created successfully!';
        if (empty($result['email_sent'])) {
            $responseMessage .= ' Note: Email delivery may have failed.';
        }

        return $this->success([
            'message' => $responseMessage,
            'invitation_url' => $result['url'] ?? '',
        ], 201);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listEvent(int $eventId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if (!$this->verifyNonce($nonce, 'vt_event_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $eventStmt = $this->database->pdo()->prepare(
            "SELECT id, author_id FROM vt_events WHERE id = :id LIMIT 1"
        );
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);

        if ($event === false) {
            return $this->error('Event not found.', 404);
        }

        if ((int)$event['author_id'] !== $viewerId && !$this->auth->currentUserCan('edit_others_posts')) {
            return $this->error('Only the event host can view invitations.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT id, email, status, rsvp_date
             FROM vt_guests
             WHERE event_id = :event_id
             ORDER BY rsvp_date DESC"
        );
        $stmt->execute([':event_id' => $eventId]);
        $guests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->success([
            'invitations' => $guests,
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function deleteEvent(int $eventId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if (!$this->verifyNonce($nonce, 'vt_event_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $eventStmt = $this->database->pdo()->prepare(
            "SELECT id, author_id FROM vt_events WHERE id = :id LIMIT 1"
        );
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);

        if ($event === false) {
            return $this->error('Event not found.', 404);
        }

        if ((int)$event['author_id'] !== $viewerId && !$this->auth->currentUserCan('edit_others_posts')) {
            return $this->error('Only the event host can remove guests.', 403);
        }

        $stmt = $this->database->pdo()->prepare(
            "DELETE FROM vt_guests WHERE id = :id AND event_id = :event_id"
        );
        $success = $stmt->execute([
            ':id' => $invitationId,
            ':event_id' => $eventId,
        ]);

        if ($success === false || $stmt->rowCount() === 0) {
            return $this->error('Failed to cancel invitation.', 400);
        }

        return $this->success(['message' => 'Invitation cancelled successfully.']);
    }

    private function request(): Request
    {
        /** @var Request $request */
        $request = vt_service('http.request');
        return $request;
    }

    private function verifyNonce(string $nonce, string $action): bool
    {
        if ($nonce === '') {
            return false;
        }

        if (class_exists('\VT_Security')) {
            try {
                return \VT_Security::verifyNonce($nonce, $action);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        try {
            $security = vt_service('security.service');
            if (is_object($security) && method_exists($security, 'verifyNonce')) {
                return (bool)$security->verifyNonce($nonce, $action);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return false;
    }

    private function canManageCommunity(int $communityId, int $viewerId, array $roles): bool
    {
        $stmt = $this->database->pdo()->prepare(
            "SELECT role FROM vt_community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $viewerId,
        ]);
        $role = $stmt->fetchColumn();

        if ($role === false) {
            return false;
        }

        if (in_array($role, $roles, true)) {
            return true;
        }

        return $this->auth->currentUserCan('manage_options');
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => true,
                'data' => $data,
            ],
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
