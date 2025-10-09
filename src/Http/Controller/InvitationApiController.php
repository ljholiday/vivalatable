<?php
declare(strict_types=1);

namespace App\Http\Controller;

use App\Database\Database;
use App\Http\Request;
use App\Services\AuthService;
use App\Services\InvitationService;

require_once dirname(__DIR__, 3) . '/templates/_helpers.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-community-manager.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-member-display.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-community-display.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-event-manager.php';
require_once dirname(__DIR__, 3) . '/legacy/includes/includes/class-guest-manager.php';

final class InvitationApiController
{
    public function __construct(
        private Database $database,
        private AuthService $auth,
        private InvitationService $invitations
    ) {
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

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        $result = $this->invitations->sendCommunityInvitation($communityId, $viewerId, $email, $message);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['status']);
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

        $result = $this->invitations->listCommunityInvitations($communityId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['status']);
    }

    public function listCommunityMembers(int $communityId): array
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
            return $this->error('You do not have permission to view members.', 403);
        }

        return $this->success([
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
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

        $result = $this->invitations->deleteCommunityInvitation($communityId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['status']);
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

        $email = trim((string)$request->input('email', ''));
        $message = trim((string)$request->input('message', ''));
        $result = $this->invitations->sendEventInvitation($eventId, $viewerId, $email, $message);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function listEvent(int $eventId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_event_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->listEventInvitations($eventId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $guestRecords = $result['data']['guest_records'] ?? [];

        return $this->success([
            'invitations' => $result['data']['invitations'],
            'html' => $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function deleteEvent(int $eventId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }
        if (!$this->verifyNonce($nonce, 'vt_event_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->deleteEventInvitation($eventId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);

        return $this->success($data, $result['status']);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function resendEvent(int $eventId, int $invitationId): array
    {
        $request = $this->request();
        $nonce = (string)$request->input('nonce', '');
        if ($nonce === '') {
            $nonce = (string)$request->query('nonce', '');
        }

        if (!$this->verifyNonce($nonce, 'vt_event_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $result = $this->invitations->resendEventInvitation($eventId, $invitationId, $viewerId);
        if (!$result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $data = $result['data'];
        $guestRecords = $data['guest_records'] ?? null;
        unset($data['guest_records']);
        $data['html'] = $this->renderEventGuests($eventId, is_array($guestRecords) ? $guestRecords : null);

        return $this->success($data, $result['status']);
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

    public function updateCommunityMemberRole(int $communityId, int $memberId): array
    {
        $payload = $this->jsonBody();
        $nonce = (string)($payload['nonce'] ?? '');
        if (!$this->verifyNonce($nonce, 'vt_community_action')) {
            return $this->error('Security verification failed.', 403);
        }

        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in.', 401);
        }

        $role = strtolower((string)($payload['role'] ?? ''));
        if (!in_array($role, ['member', 'moderator', 'admin'], true)) {
            return $this->error('Invalid role.', 422);
        }

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin'])) {
            return $this->error('You do not have permission to change roles.', 403);
        }

        $manager = new \VT_Community_Manager();
        $success = $manager->updateMemberRole($communityId, $memberId, $role);
        if (!$success) {
            return $this->error('Failed to update member role.', 400);
        }

        return $this->success([
            'message' => 'Member role updated successfully.',
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
        ]);
    }

    public function removeCommunityMember(int $communityId, int $memberId): array
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

        if (!$this->canManageCommunity($communityId, $viewerId, ['admin'])) {
            return $this->error('You do not have permission to remove members.', 403);
        }

        $manager = new \VT_Community_Manager();
        $memberRole = $manager->getMemberRole($communityId, $memberId);
        if ($memberRole === 'admin') {
            $adminCount = $manager->getAdminCount($communityId);
            if ($adminCount <= 1) {
                return $this->error('Cannot remove the only admin. Promote another member first.', 400);
            }
        }

        $success = $manager->removeMember($communityId, $memberId);
        if (!$success) {
            return $this->error('Failed to remove member.', 400);
        }

        return $this->success([
            'message' => 'Member removed successfully.',
            'html' => $this->renderCommunityMembers($communityId, $viewerId),
        ]);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function accept(): array
    {
        $viewerId = $this->auth->currentUserId();
        if ($viewerId === null || $viewerId <= 0) {
            return $this->error('You must be logged in to accept invitations', 401);
        }

        $request = $this->request();
        $token = trim((string)$request->input('token', ''));

        if ($token === '') {
            return $this->error('Invitation token is required', 400);
        }

        $manager = new \VT_Community_Manager();
        $result = $manager->acceptInvitation($token);

        if (is_vt_error($result)) {
            $errorCode = $result->getErrorCode();

            $statusMap = [
                'invalid_token' => 400,
                'invalid_invitation' => 404,
                'expired_invitation' => 410,
                'login_required' => 401,
                'user_not_found' => 404,
                'email_mismatch' => 403,
                'already_member' => 409,
            ];

            $status = $statusMap[$errorCode] ?? 500;
            return $this->error($result->getErrorMessage(), $status);
        }

        return $this->success([
            'message' => 'You have successfully joined the community!',
            'member_id' => $result,
        ]);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function renderCommunityMembers(int $communityId, int $viewerId): string
    {
        $manager = new \VT_Community_Manager();
        $members = $manager->getCommunityMembers($communityId);
        $viewerRole = $manager->getMemberRole($communityId, $viewerId);

        if (!$members) {
            return '<tr><td colspan="4" class="vt-text-center vt-text-muted">No members yet.</td></tr>';
        }

        ob_start();
        foreach ($members as $member) {
            $memberId = (int)($member->id ?? 0);
            $userId = (int)($member->user_id ?? 0);
            $role = (string)($member->role ?? 'member');
            $displayName = (string)($member->display_name ?? $member->email ?? 'Member');
            $email = (string)($member->email ?? '');
            $joinedAt = (string)($member->joined_at ?? '');
            $isSelf = $userId === $viewerId;

            echo '<tr id="member-row-' . htmlspecialchars((string)$memberId) . '">';
            echo '<td>';
            if (class_exists('\\VT_Member_Display')) {
                echo \VT_Member_Display::getMemberDisplay($userId, ['avatar_size' => 40]);
            } else {
                echo '<div class="vt-flex vt-gap-2"><div class="vt-avatar"></div>';
                echo '<div><strong>' . htmlspecialchars($displayName) . '</strong></div></div>';
            }
            echo '</td>';

            echo '<td>' . htmlspecialchars($email) . '</td>';
            echo '<td>' . ($joinedAt !== '' ? htmlspecialchars(date('M j, Y', strtotime($joinedAt))) : '-') . '</td>';

            echo '<td><div class="vt-flex vt-gap-2">';
            if ($isSelf) {
                echo '<span class="vt-text-muted vt-text-sm">You</span>';
            } else {
                if ($viewerRole === 'admin' || $this->auth->currentUserCan('manage_options')) {
                    echo '<select class="vt-form-input vt-form-input-sm" onchange="changeMemberRole(' . htmlspecialchars((string)$memberId) . ', this.value, ' . htmlspecialchars((string)$communityId) . ')">';
                    echo '<option value="member"' . ($role === 'member' ? ' selected' : '') . '>Member</option>';
                    echo '<option value="moderator"' . ($role === 'moderator' ? ' selected' : '') . '>Moderator</option>';
                    echo '<option value="admin"' . ($role === 'admin' ? ' selected' : '') . '>Admin</option>';
                    echo '</select>';
                    $jsName = json_encode($displayName);
                    echo '<button class="vt-btn vt-btn-sm vt-btn-danger" onclick="removeMember(' . htmlspecialchars((string)$memberId) . ', ' . $jsName . ', ' . htmlspecialchars((string)$communityId) . ')">Remove</button>';
                } else {
                    echo '<span class="vt-badge vt-badge-' . ($role === 'admin' ? 'primary' : 'secondary') . '">' . htmlspecialchars(ucfirst($role)) . '</span>';
                }
            }
            echo '</div></td>';
            echo '</tr>';
        }

        return (string)ob_get_clean();
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

    private function renderEventGuests(int $eventId, ?array $preloadedGuests = null): string
    {
        $guests = $preloadedGuests;
        if ($guests === null) {
            $guestManager = new \VT_Guest_Manager();
            $guests = $guestManager->getEventGuests($eventId);
        }

        if (!$guests) {
            return '<div class="vt-text-center vt-text-muted">No RSVP invitations sent yet.</div>';
        }

        $invitationService = new \VT_Invitation_Service();
        $eventStmt = $this->database->pdo()->prepare(
            "SELECT slug FROM vt_events WHERE id = :id LIMIT 1"
        );
        $eventStmt->execute([':id' => $eventId]);
        $event = $eventStmt->fetch(\PDO::FETCH_ASSOC);
        $eventSlug = $event['slug'] ?? '';

        ob_start();
        foreach ($guests as $guest) {
            $status = (string)($guest->status ?? 'pending');
            $statusClass = match ($status) {
                'confirmed' => 'success',
                'declined' => 'danger',
                'maybe' => 'warning',
                default => 'secondary',
            };
            $statusLabel = match ($status) {
                'confirmed' => 'Confirmed',
                'declined' => 'Declined',
                'maybe' => 'Maybe',
                default => 'Pending',
            };
            $invitationUrl = $invitationService->buildInvitationUrl('event', $eventSlug, $guest->rsvp_token ?? '');

            echo '<div class="vt-invitation-item" id="guest-' . htmlspecialchars((string)$guest->id) . '">';
            echo '<div class="vt-invitation-badges">';
            echo '<span class="vt-badge vt-badge-' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</span>';
            $source = (string)($guest->invitation_source ?? 'direct');
            $sourceLabel = ucfirst($source);
            echo '<span class="vt-badge vt-badge-secondary">' . htmlspecialchars($sourceLabel) . '</span>';
            echo '</div>';

            echo '<div class="vt-invitation-details">';
            echo '<h4>' . htmlspecialchars($guest->email ?? '') . '</h4>';
            if (!empty($guest->name)) {
                echo '<div class="vt-text-muted">' . htmlspecialchars($guest->name) . '</div>';
            }
            if (!empty($guest->rsvp_date)) {
                echo '<div class="vt-text-muted">Invited on ' . htmlspecialchars(date('M j, Y', strtotime($guest->rsvp_date))) . '</div>';
            }
            if (!empty($guest->dietary_restrictions)) {
                echo '<div class="vt-text-muted"><strong>Dietary:</strong> ' . htmlspecialchars($guest->dietary_restrictions) . '</div>';
            }
            if (!empty($guest->notes)) {
                echo '<div class="vt-text-muted"><em>' . htmlspecialchars($guest->notes) . '</em></div>';
            }
            echo '</div>';

            echo '<div class="vt-invitation-actions">';
            echo '<button type="button" class="vt-btn vt-btn-sm vt-btn-secondary" onclick="copyInvitationUrl(' . json_encode($invitationUrl) . ')">Copy Link</button>';
            if (in_array($status, ['pending', 'maybe'], true)) {
                echo '<button type="button" class="vt-btn vt-btn-sm vt-btn-secondary resend-event-invitation" data-invitation-id="' . htmlspecialchars((string)$guest->id) . '" data-invitation-action="resend">Resend Email</button>';
            }
            if ($status === 'pending') {
                echo '<button type="button" class="vt-btn vt-btn-sm vt-btn-danger cancel-event-invitation" data-invitation-id="' . htmlspecialchars((string)$guest->id) . '" data-invitation-action="cancel">Remove</button>';
            }
            echo '</div>';
            echo '</div>';
        }

        return (string)ob_get_clean();
    }

}
