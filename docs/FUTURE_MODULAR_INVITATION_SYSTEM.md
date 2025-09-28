# Future: Modular Invitation/Request/Join/RSVP System

## Current State Assessment

### ✅ What's Already Ported from PartyMinder:

#### Event RSVP System
- **Database**: `vt_guests`, `vt_event_invitations` tables
- **Backend**: `VT_Guest_Manager` class with full RSVP functionality
- **Email**: Event-specific invitation template and sending
- **Tokens**: 32-character token system (matches PartyMinder)
- **Guest conversion**: Guest-to-user conversion system

#### Community Invitation System
- **Database**: `vt_community_invitations`, `vt_community_members` tables
- **Backend**: Community AJAX handlers for invitations
- **Email**: Community-specific invitation template and sending
- **Features**: Send, accept, cancel invitations

#### Basic Join System
- **Backend**: `ajaxJoinCommunity` (immediate join, no approval)
- **Frontend**: Join buttons (currently being enhanced)

### ❌ Current Limitations:

#### Non-Modular Email System
- Event invitations use `VT_Mail::sendInvitationEmail()` (hardcoded for events)
- Community invitations use custom HTML in `VT_Community_Manager`
- No shared email templates or components
- Duplicate email formatting code

#### Missing Join Request Approval
- No pending/approval workflow for community joins
- No owner notification system for join requests
- No join request management interface

#### Separate Database Schema
- Event and community invitations use different tables
- No unified invitation/request status tracking
- Limited cross-system integration

## Future Modular System Design

### Core Principles
1. **Single Responsibility**: Each component handles one specific aspect
2. **Reusability**: Components work across events, communities, and future features
3. **Extensibility**: Easy to add new invitation types (groups, meetups, etc.)
4. **Consistency**: Unified UX across all invitation/request flows

### Proposed Architecture

#### 1. Unified Database Schema
```sql
-- Single table for all invitation/request types
CREATE TABLE vt_invitations (
    id bigint PRIMARY KEY AUTO_INCREMENT,
    type ENUM('event_invitation', 'community_invitation', 'join_request') NOT NULL,

    -- Polymorphic relationship
    invitable_type ENUM('event', 'community') NOT NULL,
    invitable_id bigint NOT NULL,

    -- Participants
    inviter_id bigint NULL, -- NULL for join requests
    invitee_id bigint NULL, -- NULL for email invitations
    invitee_email varchar(255) NULL,

    -- Status and metadata
    status ENUM('pending', 'accepted', 'declined', 'expired', 'cancelled') DEFAULT 'pending',
    message TEXT NULL,
    token varchar(64) NOT NULL UNIQUE,

    -- Timestamps
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,

    INDEX idx_invitable (invitable_type, invitable_id),
    INDEX idx_invitee (invitee_id, invitee_email),
    INDEX idx_status (status),
    INDEX idx_token (token)
);
```

#### 2. Modular Email System
```php
// Unified email template system
class VT_Invitation_Mailer {
    public static function send($invitation, $template_type) {
        $template = self::getTemplate($template_type, $invitation);
        return VT_Mail::sendTemplate($invitation->invitee_email, $template, $variables);
    }

    private static function getTemplate($type, $invitation) {
        // Returns unified template with context-specific variables
        switch ($type) {
            case 'event_invitation':
                return 'invitations/event-invitation';
            case 'community_invitation':
                return 'invitations/community-invitation';
            case 'join_request_notification':
                return 'invitations/join-request-notification';
        }
    }
}
```

#### 3. Unified Service Layer
```php
class VT_Invitation_Service {
    // Send any type of invitation
    public function sendInvitation($type, $invitable_id, $inviter_id, $invitee, $options = []) {}

    // Handle responses (accept/decline)
    public function respondToInvitation($token, $response, $user_id = null) {}

    // Join requests (reverse invitations)
    public function requestToJoin($type, $invitable_id, $user_id, $message = '') {}

    // Owner actions on join requests
    public function approveJoinRequest($invitation_id, $approver_id) {}
    public function declineJoinRequest($invitation_id, $approver_id, $reason = '') {}

    // Cleanup and maintenance
    public function expireOldInvitations() {}
    public function getInvitationsByType($type, $invitable_id, $status = 'pending') {}
}
```

#### 4. Reusable UI Components
```php
// Generic invitation form component
VT_UI::invitation_form([
    'type' => 'community',
    'invitable_id' => $community->id,
    'title' => 'Invite Members',
    'submit_text' => 'Send Invitations'
]);

// Generic invitation list component
VT_UI::invitation_list([
    'type' => 'join_request',
    'invitable_id' => $community->id,
    'show_actions' => true, // approve/decline buttons
    'title' => 'Join Requests'
]);
```

### Migration Strategy

#### Phase 1: Maintain Current System
- ✅ Current automatic join for development testing
- Document existing patterns and limitations
- Plan unified schema design

#### Phase 2: Create Unified Backend
- Create `VT_Invitation_Service` with current functionality
- Migrate existing data to unified schema
- Add join request approval workflow
- Maintain backward compatibility

#### Phase 3: Modular Email System
- Create unified email templates
- Migrate existing email sending to use shared components
- Add email preferences and customization

#### Phase 4: Unified Frontend
- Create reusable invitation UI components
- Update all invitation flows to use shared components
- Add real-time notifications for join requests

#### Phase 5: Advanced Features
- Bulk invitations
- Invitation templates and customization
- Integration with external calendar systems
- Advanced permission controls

### Benefits of Modular System

#### For Developers
- **DRY Principle**: No duplicate invitation logic
- **Easier Testing**: Isolated, testable components
- **Faster Development**: Reusable components for new features
- **Better Maintenance**: Single source of truth for invitation logic

#### For Users
- **Consistent UX**: Same invitation flow across events/communities
- **Better Notifications**: Unified notification system
- **More Control**: Granular invitation preferences
- **Reliability**: Tested, proven components

#### For Product
- **Scalability**: Easy to add new invitation types
- **Analytics**: Unified invitation metrics and insights
- **Integration**: Easier third-party integrations
- **Performance**: Optimized queries and caching

## Implementation Priority

**High Priority (Next Quarter)**:
- Join request approval workflow for communities
- Owner notification system
- Basic join request management UI

**Medium Priority (Following Quarter)**:
- Unified invitation service layer
- Modular email template system
- Data migration to unified schema

**Low Priority (Future)**:
- Advanced UI components
- Real-time notifications
- External integrations

## Current Workaround

For immediate development needs, the current automatic join system provides:
- ✅ Functional community joining
- ✅ Personal community "Connect" functionality
- ✅ Proper AJAX integration
- ✅ Success/error handling

This allows continued development while the modular system is planned and implemented.