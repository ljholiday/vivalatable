<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;

require_once dirname(__DIR__, 2) . '/legacy/includes/includes/class-community-manager.php';
require_once dirname(__DIR__, 2) . '/legacy/includes/includes/class-guest-manager.php';
require_once dirname(__DIR__, 2) . '/legacy/includes/includes/class-invitation-service.php';

final class InvitationService
{
    public function __construct(
        private Database $database,
        private AuthService $auth
    ) {
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function sendCommunityInvitation(int $communityId, int $viewerId, string $email, string $message): array
    {
        $community = $this->fetchCommunity($communityId);
        if ($community === null) {
            return $this->failure('Community not found.', 404);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to send invitations.', 403);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Valid email address required.', 422);
        }

        $manager = new \VT_Community_Manager();
        $result = $manager->sendInvitation($communityId, [
            'invited_email' => $email,
            'personal_message' => $message,
        ]);

        if (is_vt_error($result)) {
            return $this->failure($result->getErrorMessage(), 400);
        }

        return $this->success([
            'message' => 'Invitation sent successfully!',
        ], 201);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function listCommunityInvitations(int $communityId, int $viewerId): array
    {
        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to view invitations.', 403);
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
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function deleteCommunityInvitation(int $communityId, int $invitationId, int $viewerId): array
    {
        if (!$this->canManageCommunity($communityId, $viewerId, ['admin', 'moderator'])) {
            return $this->failure('You do not have permission to cancel invitations.', 403);
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
            return $this->failure('Failed to cancel invitation.', 400);
        }

        return $this->success([
            'message' => 'Invitation cancelled successfully.',
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function sendEventInvitation(int $eventId, int $viewerId, string $email, string $message): array
    {
        $event = $this->fetchEvent($eventId, includeSlug: true, includeTitle: true);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can send invitations.', 403);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->failure('Valid email address required.', 422);
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
            return $this->failure('This email has already been invited.', 400);
        }

        $sendResult = $guestManager->sendRsvpInvitation(
            $eventId,
            $email,
            $this->auth->getCurrentUser()->display_name ?? '',
            $message
        );

        if (is_vt_error($sendResult)) {
            return $this->failure($sendResult->getErrorMessage(), 400);
        }

        $emailSent = false;
        $temporaryId = null;
        $token = '';
        if (is_array($sendResult)) {
            $emailSent = (bool)($sendResult['email_sent'] ?? false);
            $temporaryId = $sendResult['temporary_guest_id'] ?? null;
            $token = (string)($sendResult['token'] ?? '');
        }

        $invitationService = new \VT_Invitation_Service();
        $invitationUrl = $token !== '' ? $invitationService->buildInvitationUrl('event', (string)$event['slug'], $token) : '';

        $responseMessage = 'RSVP invitation created successfully!';
        if (!$emailSent) {
            $responseMessage .= ' Note: Email delivery may have failed.';
        }

        $guestRecords = $guestManager->getEventGuests($eventId);

        return $this->success([
            'message' => $responseMessage,
            'invitation_url' => $invitationUrl,
            'temporary_guest_id' => $temporaryId,
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ], 201);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function listEventInvitations(int $eventId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can view invitations.', 403);
        }

        $guestManager = new \VT_Guest_Manager();
        $guestRecords = $guestManager->getEventGuests($eventId);

        return $this->success([
            'invitations' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function deleteEventInvitation(int $eventId, int $invitationId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can remove guests.', 403);
        }

        $guestManager = new \VT_Guest_Manager();
        $deleteResult = $guestManager->deleteGuest($invitationId);
        if (is_vt_error($deleteResult)) {
            return $this->failure($deleteResult->getErrorMessage(), 400);
        }

        if ($deleteResult !== true) {
            return $this->failure('Failed to cancel invitation.', 400);
        }

        $guestRecords = $guestManager->getEventGuests($eventId);

        return $this->success([
            'message' => 'Invitation cancelled successfully.',
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    public function resendEventInvitation(int $eventId, int $invitationId, int $viewerId): array
    {
        $event = $this->fetchEvent($eventId);
        if ($event === null) {
            return $this->failure('Event not found.', 404);
        }

        if (!$this->canManageEvent($event, $viewerId)) {
            return $this->failure('Only the event host can resend invitations.', 403);
        }

        $guestStmt = $this->database->pdo()->prepare(
            "SELECT id, status FROM vt_guests WHERE id = :id AND event_id = :event_id LIMIT 1"
        );
        $guestStmt->execute([
            ':id' => $invitationId,
            ':event_id' => $eventId,
        ]);

        $guest = $guestStmt->fetch(\PDO::FETCH_ASSOC);
        if ($guest === false) {
            return $this->failure('Invitation not found for this event.', 404);
        }

        $status = strtolower((string)($guest['status'] ?? ''));
        if (!in_array($status, ['pending', 'maybe'], true)) {
            return $this->failure('This guest has already responded. Remove them before sending a new invitation.', 409);
        }

        $guestManager = new \VT_Guest_Manager();
        $resendResult = $guestManager->resendInvitation($invitationId);
        if (is_vt_error($resendResult)) {
            return $this->failure($resendResult->getErrorMessage(), 400);
        }

        $emailSent = (bool)$resendResult;
        $message = $emailSent
            ? 'Invitation email resent successfully.'
            : 'Invitation resent. Email delivery may have failed.';

        $guestRecords = $guestManager->getEventGuests($eventId);

        return $this->success([
            'message' => $message,
            'email_sent' => $emailSent,
            'guests' => $this->normalizeEventGuests($guestRecords),
            'guest_records' => $guestRecords,
        ]);
    }

    /**
     * @param array<string,mixed> $community
     */
    private function canManageEvent(array $event, int $viewerId): bool
    {
        if ((int)($event['author_id'] ?? 0) === $viewerId) {
            return true;
        }

        return $this->auth->currentUserCan('edit_others_posts');
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchCommunity(int $communityId): ?array
    {
        $stmt = $this->database->pdo()->prepare(
            'SELECT id, name FROM vt_communities WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $communityId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchEvent(int $eventId, bool $includeSlug = false, bool $includeTitle = false): ?array
    {
        $fields = ['id', 'author_id'];
        if ($includeSlug) {
            $fields[] = 'slug';
        }
        if ($includeTitle) {
            $fields[] = 'title';
        }

        $fieldList = implode(', ', $fields);

        $stmt = $this->database->pdo()->prepare(
            "SELECT {$fieldList} FROM vt_events WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function canManageCommunity(int $communityId, int $viewerId, array $roles): bool
    {
        if ($viewerId <= 0) {
            return false;
        }

        $stmt = $this->database->pdo()->prepare(
            "SELECT role FROM vt_community_members WHERE community_id = :community_id AND user_id = :user_id LIMIT 1"
        );
        $stmt->execute([
            ':community_id' => $communityId,
            ':user_id' => $viewerId,
        ]);
        $role = $stmt->fetchColumn();

        if ($role === false) {
            return $this->auth->currentUserCan('manage_options');
        }

        return in_array($role, $roles, true) || $this->auth->currentUserCan('manage_options');
    }

    /**
     * @param array<int, object|array<string,mixed>> $guestRecords
     * @return array<int, array<string,mixed>>
     */
    private function normalizeEventGuests(array $guestRecords): array
    {
        $normalized = [];

        foreach ($guestRecords as $guest) {
            if ($guest === null) {
                continue;
            }

            $record = is_object($guest) ? $guest : (object)$guest;

            $normalized[] = [
                'id' => (int)($record->id ?? 0),
                'name' => (string)($record->name ?? ''),
                'email' => (string)($record->email ?? ''),
                'status' => (string)($record->status ?? 'pending'),
                'rsvp_date' => $record->rsvp_date ?? null,
                'plus_one' => (int)($record->plus_one ?? 0),
                'plus_one_name' => (string)($record->plus_one_name ?? ''),
                'notes' => (string)($record->notes ?? ''),
                'dietary_restrictions' => (string)($record->dietary_restrictions ?? ''),
                'invitation_source' => (string)($record->invitation_source ?? ''),
                'temporary_guest_id' => (string)($record->temporary_guest_id ?? ''),
                'rsvp_token' => (string)($record->rsvp_token ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function success(array $data, int $status = 200): array
    {
        return [
            'success' => true,
            'status' => $status,
            'message' => '',
            'data' => $data,
        ];
    }

    /**
     * @return array{success:bool,status:int,message:string,data:array<string,mixed>}
     */
    private function failure(string $message, int $status): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'data' => [],
        ];
    }
}
