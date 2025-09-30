# VivalaTable Development Script

## Current Session: Permission Model Design & Two-Community Architecture
**Date:** 2025-09-30
**Status:** Planning Phase

---

## Two-Community Model Decision (NEW)

### Core Architecture
Every new user gets **TWO communities automatically created**:

#### 1. **[Display Name] Circle** (Private Personal Community)
- **Privacy:** Private (always, cannot be changed)
- **Access:** Invite-only (owner controls membership)
- **Purpose:** Close Circle (Circle 1) - trusted inner circle
- **Discovery:** Hidden from discovery, not searchable
- **Naming:** No apostrophes - "Lonn Holiday Circle" not "Lonn Holiday's Circle"

#### 2. **[Display Name]** (Public Community)
- **Privacy:** Public
- **Access:** Anyone can join (instant, no approval)
- **Purpose:** Public-facing content, discoverable presence
- **Discovery:** Listed in community directory, searchable
- **Naming:** Just the display name - "Lonn Holiday"

### Rationale
- **Solves Cold Start Problem:** New users have instant discoverability via public community
- **Privacy by Design:** Inner circle remains protected and invite-only
- **Discovery Mechanism:** Public space allows connections before trust is established
- **Graduated Trust:** Follow publicly → earn invitation to private circle
- **No Apostrophe Hell:** "Lonn Holiday Circle" avoids possessive grammar and technical issues

### Discovery Flow
1. User creates content in public communities (theirs or others)
2. Others see content, visit profile
3. Can join their public community (instant)
4. Can request/receive invitation to their Circle (approval required)

---

## Vocabulary & Terminology Decisions

### Official Terms (from STYLEGUIDE.md)
- **Communities** (not groups)
- **Conversations** (not posts/threads)
- **Messages** (units inside conversations)
- **Connections** (not followers/friends)
- **Circles** (trust levels)

### Action Verbs (NO "post")
- ✅ **Start a Conversation** (create new discussion)
- ✅ **Send a Message** (contribute to existing conversation)
- ✅ **Reply** (respond to someone)
- ✅ **Share** (spread content)
- ❌ **Post** (banned - too generic, no meaning)

### Circle Terminology
- **Circle 1 — Close** (personal circles)
- **Circle 2 — Trusted** (friend-of-friend)
- **Circle 3 — Extended** (broader network)

---

## Permission Model Design

### Events vs Conversations - Different Rules

#### **Events (Structured, High Stakes)**
- **Can I create an event in someone else's community?** → **NO**
- **Rationale:** Events are real-world commitments (venue, time, RSVPs)
- **Alternative:** "Suggest an Event" feature (admin approval)
- **Precedent:** You don't announce parties at someone else's house

#### **Conversations (Freeform, Low Stakes)**
- **Can I start a conversation in someone else's community?** → **YES** (if member)
- **Rationale:** That's the point of communities - discussion spaces
- **Self-Repairing:** Bad actors get moderated, quality self-selects
- **Precedent:** Reddit, Discourse, Discord (members can create topics)

### Permission Matrix

| Action | Public Community (member) | Personal Circle (member) | Someone's Circle (invited) |
|--------|--------------------------|--------------------------|---------------------------|
| Start Conversation | ✅ YES | ✅ YES | ✅ YES |
| Create Event | ❌ NO (suggest) | ✅ YES (owner) | ❌ NO (suggest) |
| Reply to Conversation | ✅ YES | ✅ YES | ✅ YES |
| Invite Others | Settings dependent | ❌ NO (owner only) | ❌ NO |
| Edit Own Content | ✅ YES (time limit) | ✅ YES (time limit) | ✅ YES (time limit) |
| Delete Own Content | ✅ YES (conditions) | ✅ YES (conditions) | ✅ YES (conditions) |
| Moderate Others | ❌ NO | ❌ NO | ❌ NO |

| Action | Community Admin | Site Admin |
|--------|----------------|------------|
| Edit Any Content | ✅ YES (in community) | ✅ YES (anywhere) |
| Delete Any Content | ✅ YES (in community) | ✅ YES (anywhere) |
| Pin Conversations | ✅ YES | ✅ YES |
| Create Events | ✅ YES | ✅ YES |
| Manage Members | ✅ YES (in community) | ✅ YES (anywhere) |

### Key Permission Rules

**Joining Communities:**
1. ✅ Public communities → instant join (no approval)
2. ✅ Private communities → invite required
3. ✅ Personal Circles → always private, always invite-only

**Content Visibility:**
1. ✅ Public conversations → anyone can view (even guests)
2. ✅ Members-only conversations → community members only
3. ✅ Private conversations → invite/permission required

**Editing/Deleting:**
1. ✅ Edit own content (15-30 min window OR "edited" indicator)
2. ✅ Delete own conversation (only if no replies yet)
3. ✅ Delete own reply (always, shows "[deleted]" if has children)
4. ✅ Community admins can edit/delete anything in their community
5. ✅ Site admins can edit/delete anything anywhere

**Discovery:**
1. ✅ Guests can view public content (read-only)
2. ✅ Guests cannot participate, RSVP, or join
3. ✅ Public communities listed in directory
4. ✅ Personal Circles hidden from discovery

---

## Implementation Implications (Next Steps)

### Database Changes Needed
- [ ] Add `community_type` column to `vt_communities` table
  - Values: `'public'`, `'circle'`
  - Circles cannot be changed to public
- [ ] Add `suggested_by` and `status` columns to `vt_events` table
  - Support "Suggest an Event" workflow
  - Status: `'draft'`, `'suggested'`, `'active'`, `'cancelled'`

### New Permission Methods Needed

**In `VT_Community_Manager`:**
- [ ] `canStartConversation($community_id, $user_id)` - member check
- [ ] `canCreateEvent($community_id, $user_id)` - owner/admin only
- [ ] `canSuggestEvent($community_id, $user_id)` - member check
- [ ] `canInviteMembers($community_id, $user_id)` - settings-dependent

**In `VT_Conversation_Manager`:**
- [ ] `canEditConversation($conversation_id, $user_id)` - author/admin
- [ ] `canDeleteConversation($conversation_id, $user_id)` - author/admin + no replies
- [ ] `canPinConversation($conversation_id, $user_id)` - community admin/site admin

**In `VT_Event_Manager`:**
- [ ] `canEditEvent($event_id, $user_id)` - author/community admin
- [ ] `canDeleteEvent($event_id, $user_id)` - author/site admin
- [ ] `canApproveEvent($event_id, $user_id)` - community admin

### UI Changes Needed
- [ ] Update community creation flow to create BOTH communities for new users
- [ ] Add "Suggest Event" button/flow for members
- [ ] Add edit/delete buttons to conversations (conditional on permissions)
- [ ] Add edit/delete buttons to replies (conditional on permissions)
- [ ] Add "Request to Join" button for Personal Circles (future feature)
- [ ] Update community listings to distinguish public vs circles

### Templates to Update
- [ ] Create `suggest-event-content.php` template
- [ ] Create `edit-conversation-content.php` template
- [ ] Update `single-conversation-content.php` with edit/delete UI
- [ ] Update community creation to handle two-community setup
- [ ] Update user registration to create both communities

### Documentation Needed
- [ ] Update STYLEGUIDE.md with two-community model
- [ ] Update CONVENTIONS.md with naming rules (no apostrophes)
- [ ] Create PERMISSIONS.md with complete permission matrix
- [ ] Update CONTRIBUTING.md with permission checking patterns

---

## Recent Work Completed

### Conversation Replies System ✅
- Fixed validation architecture (Trust Boundary Pattern)
- Removed validator calls from managers (managers trust data)
- Fixed insert_id timing bug (retrieve before UPDATE queries)
- Added avatar integration using member-display.php partial
- Added reply formatting CSS (cards, hover effects, spacing)
- Removed redundant reply buttons

### Error Handling Improvements ✅
- Fixed JSON corruption (display_errors → log_errors)
- Created copyable error modal system (modal.js)
- Updated all AJAX handlers to use VT_Ajax::sendError()
- Replaced browser alert() with vtShowError()/vtShowSuccess()

### Code Quality ✅
- Removed all error_log() function calls (forbidden)
- Updated dev/php.xml with "NEVER use error_log()" rule
- Established debug.log as debugging file location
- Fixed all validator/sanitizer misuse in templates and managers

### Git Workflow ✅
- Created 8 logical commits (no files left behind)
- Commits: error handling, modal system, validation architecture, replies, conversations, security, database, cleanup
- Established multi-stage commit pattern for complex work

### UI Improvements ✅
- Changed sidebar "Edit Profile" button to "Profile" (clearer navigation)
- Avatar display already working in sidebar (blue circle with initial)

### Repository Maintenance ✅
- Expanded .gitignore with comprehensive coverage
- Added sections for dev tools, environment files, build artifacts, IDE files, OS files
- Better organization with section headers and comments

---

## Current Codebase Status

### Working Systems ✅
- User registration/authentication
- Community creation and discovery
- Event creation and RSVP
- Conversation system with Circles of Trust filtering
- Reply system with avatars
- Guest token system
- Security (CSRF, nonces, prepared statements)

### Permission Gaps (Current Focus)
- No canEdit/canDelete methods for conversations
- No canEdit/canDelete methods for replies
- No canEdit/canDelete methods for events
- No "Suggest Event" workflow
- No two-community auto-creation for new users
- No distinction between public communities and personal circles

---

## Next Actions

**Priority 1: Document Decisions**
1. Update STYLEGUIDE.md with two-community model and terminology
2. Create PERMISSIONS.md with complete permission matrix
3. Update CONVENTIONS.md with naming conventions

**Priority 2: Implement Two-Community Model**
1. Update user registration to create both communities
2. Add community_type database column
3. Update UI to distinguish public vs circles

**Priority 3: Implement Permission Methods**
1. Add all canEdit/canDelete methods to managers
2. Update templates to show edit/delete UI conditionally
3. Create edit templates for conversations

**Priority 4: Suggest Event Feature**
1. Add suggested event workflow
2. Create suggest-event-content.php template
3. Add admin approval interface

---

## Development Standards

### Critical Rules
- ✅ **NEVER use error_log()** - use debug.log in root
- ✅ **NEVER use validators in managers** - sanitize at boundaries only
- ✅ **NEVER commit directly to main** - always work on lonn branch
- ✅ **NEVER leave uncommitted files** - multi-stage logical commits
- ✅ **NEVER use apostrophes** in naming (technical + UX nightmare)

### Git Workflow
1. Work on `lonn` branch
2. Merge `lonn` into `main` when ready
3. Push `main` to GitHub
4. Checkout `lonn` again to continue development

### Validation Architecture (Trust Boundary Pattern)
- **Templates (Boundary):** Sanitize input, validate, check permissions
- **Managers (Business Logic):** Trust pre-sanitized data, implement logic
- **Database (Storage):** Use prepared statements, store clean data

### Naming Conventions
- Communities: No apostrophes ever ("Lonn Holiday Circle" not "Lonn Holiday's")
- Methods: camelCase (getUserProfile)
- Variables: snake_case ($current_user)
- CSS: .vt- prefix for all classes
- Tables: vt_ prefix, snake_case