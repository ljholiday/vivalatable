# VivalaTable Permissions Reference

This document defines the complete permission model for VivalaTable, covering Communities, Conversations, Events, and user roles.

---

## Core Principles

VivalaTable's permission model is built on five principles:

1. **Graduated Permissions** - Different roles have different capabilities (Discord, Slack model)
2. **View vs Participate Split** - Can see ≠ can interact (Discourse model)
3. **Community-Based Control** - Community owners/admins have ultimate say
4. **Trust-Based Access** - Circles of Trust inform permissions
5. **Privacy by Design** - Default to more restrictive, allow opening up

---

## Events vs Conversations - Different Rules

VivalaTable treats **Events** and **Conversations** with different permission models due to their different stakes and nature:

### Events (Structured, High Stakes)

- **Can I create an event in someone else's community?** → **DEPENDS ON PRIVACY**
  - **Public communities:** YES (any member)
  - **Private communities:** NO (owner/admin only)
- **Rationale:** Public communities self-organize; private communities maintain control
- **Alternative (Private):** "Suggest an Event" feature (admin approval required) - FUTURE
- **Precedent:** Meetup.com public groups vs private invitation-only spaces

### Conversations (Freeform, Low Stakes)

- **Can I start a conversation in someone else's community?** → **YES** (if member)
- **Rationale:** That's the point of communities - discussion spaces
- **Self-Repairing:** Bad actors get moderated, quality self-selects
- **Precedent:** Reddit, Discourse, Discord (members can create topics)

---

## Permission Questions & Answers

### COMMUNITIES

#### 1. Can I join a public community?

**YES** - Instant join without approval

- **Precedent:** Discord public servers, Slack public channels
- **Rationale:** "Public" should mean truly open; reduces friction for community growth

#### 2. Can I join a private community?

**NO** - Must be invited by admin/member

- **Precedent:** Discord private servers (invite-only), Slack private channels
- **Rationale:** Privacy by design; community owner controls membership

#### 3. Can I leave a community I'm a member of?

**YES** - Always (except Personal Circles - see Two-Community Model)

- **Precedent:** Universal across all platforms
- **Rationale:** User autonomy; can't trap people in communities

#### 4. Can I start a conversation in someone else's community?

**YES, if you're a member**
**NO, if you're not a member**

- **Precedent:** Discourse - must be in category to post; Slack - must be in channel
- **Rationale:** Membership gates participation; prevents spam; aligns with Circles of Trust (you trust members)

#### 5. Can I create an event in someone else's community?

**DEPENDS ON PRIVACY:**

- **Public communities:** YES - Any member can create events
  - **Rationale:** Public communities develop organically; members know each other and can self-organize
  - **Precedent:** Meetup.com public groups allow member-created events
  - **Example:** 1965 Mustangs community develops soul over years; members organize their own meetups
  - **Example:** Popular party host's public community; members know each other, anyone can throw events

- **Private communities:** NO - Only community owner/admin can create events
  - **Alternative:** "Suggest an Event" feature (member can suggest, admin approves) - FUTURE
  - **Rationale:** Private communities are controlled spaces; owner maintains quality and intent
  - **Precedent:** Private spaces require approval for announcements

#### 6. Can I invite others to a community I'm a member of (but don't admin)?

**Depends on community settings:**

- **Public communities:** YES - anyone can share the link (instant join)
- **Private communities:** Optional setting (admin choice)

- **Precedent:** Discord allows members to generate invite links if permissions allow
- **Rationale:** Balances growth with control; admin can restrict if needed

#### 7. Can I see members of a community I'm not a member of?

- **Public communities:** YES - see member list
- **Private communities:** NO - can't see anything

- **Precedent:** LinkedIn groups show member counts; Facebook shows some members
- **Rationale:** Transparency for public; privacy for private

---

### CONVERSATIONS

#### 8. Can I view a public conversation?

**YES** - Anyone, even non-logged-in guests

- **Precedent:** Reddit public posts, Discourse public topics
- **Rationale:** Discoverability; SEO; encourages sign-ups

#### 9. Can I view a private conversation I'm not invited to?

**NO**

- **Precedent:** Universal
- **Rationale:** Privacy by design

#### 10. Can I view a "members only" conversation if I'm in the community?

**YES** - All members can view

- **Precedent:** Discord channel-specific permissions, Discourse category permissions
- **Rationale:** "Members only" means community members; aligns with Circles of Trust

#### 11. Can I reply to any conversation I can view?

- **If logged in:** YES
- **If guest:** NO - must create account
- **Exception:** Locked/archived conversations - NO ONE can reply

- **Precedent:** Discourse trust levels, Reddit locked threads
- **Rationale:** View permission ≠ participate permission; anti-spam; encourages accounts

#### 12. Can I edit my own conversation after posting?

**YES** - with time limit (15-30 minutes?) OR show "edited" indicator

- **Precedent:** Reddit (3 minute window), Discourse (unlimited with edit history)
- **Rationale:** Fix typos/errors; "edited" indicator maintains transparency

#### 13. Can I delete my own conversation?

- **YES** - if no replies yet
- **NO** - if has replies (archive/lock instead)

- **Precedent:** Reddit (can delete own posts), Stack Overflow (can't delete if answers exist)
- **Rationale:** You started it, but once others contribute, it's community content

#### 14. Can I edit/delete someone else's conversation in my community?

**Community Admin:** YES - edit/delete/pin/lock

- **Precedent:** Discord moderators, Discourse moderators
- **Rationale:** Community management needs; prevent abuse; maintain standards

#### 15. Can I edit my own reply after posting?

**YES** - same rules as conversation (time limit or "edited" indicator)

- **Precedent:** Universal

#### 16. Can I delete my own reply?

**YES** - Always (shows "[deleted]" placeholder if has child replies)

- **Precedent:** Reddit deleted comments, Discourse
- **Rationale:** Right to remove your content; preserve threading structure

#### 17. Can I pin conversations in communities I admin?

- **Community Admin:** YES
- **Site Admin:** YES (any community)

- **Precedent:** Discord pinned messages, Reddit stickied posts
- **Rationale:** Highlight important discussions; guide community

---

### EVENTS

#### 18. Can I view a public event?

**YES** - Anyone

- **Rationale:** Same as public conversations; discoverability

#### 19. Can I view a private event I'm not invited to?

**NO**

- **Precedent:** Facebook private events
- **Rationale:** Privacy by design

#### 20. Can I RSVP to any event I can view?

- **Public events:** YES (if logged in)
- **Private events:** NO - must be invited

- **Precedent:** Facebook events, Eventbrite
- **Rationale:** View ≠ participation for private; public events are open invitations

#### 21. Can I edit my own event after creating?

**YES** - Always (with notification to attendees if major changes)

- **Precedent:** Facebook events, Eventbrite
- **Rationale:** Details change; attendees need updates

#### 22. Can I delete my own event?

- **YES** - if no RSVPs yet
- **Archive only** - if has RSVPs (notify attendees)

- **Precedent:** Eventbrite cancellation policy
- **Rationale:** Once people commit, can't just vanish; cancel properly

#### 23. Can I edit/delete someone else's event in my community?

**Community Admin:** YES

- **Rationale:** Community management; prevent inappropriate events

#### 24. Can I invite guests to someone else's event?

**NO** - only event creator invites

- **Exception:** If event is public, anyone can share the link

- **Precedent:** Most event platforms restrict invites to creator
- **Rationale:** Event creator controls guest list; prevents spam

---

### GENERAL ROLES

#### 25. What can site admins do that regular users can't?

- View/edit/delete ANY content across ALL communities
- Manage user accounts (suspend/ban)
- Access admin dashboard and analytics
- Manage site-wide settings

- **Precedent:** Universal admin powers
- **Rationale:** Site management and moderation needs

#### 26. What can community admins do that members can't?

- Edit/delete any conversation or event in their community
- Pin/lock conversations
- Manage community settings (visibility, description, etc.)
- Invite/remove members (private communities)
- Assign/remove other admins

- **Precedent:** Discord server owners, Facebook group admins
- **Rationale:** Community governance and quality control

#### 27. Can guests (non-logged-in users) view anything?

- **YES:** Public conversations, public events, public communities (descriptions/member counts)
- **NO:** Cannot participate, RSVP, or join

- **Precedent:** Reddit, Discourse (read-only for guests)
- **Rationale:** Discoverability; SEO; conversion funnel to sign-ups

---

## Summary Permission Matrix

| Action                    | Guest | User           | Community Member | Community Admin  | Site Admin   |
|---------------------------|-------|----------------|------------------|------------------|--------------|
| View public content       | ✅     | ✅              | ✅                | ✅                | ✅            |
| View members-only content | ❌     | ❌              | ✅                | ✅                | ✅            |
| Join public community     | ❌     | ✅              | -                | -                | -            |
| Post in community         | ❌     | ❌              | ✅                | ✅                | ✅            |
| Create event              | ❌     | ❌              | ❌                | ✅                | ✅            |
| Suggest event             | ❌     | ❌              | ✅                | ✅                | ✅            |
| Edit own content          | ❌     | ✅ (time limit) | ✅ (time limit)   | ✅ (time limit)   | ✅            |
| Delete own content        | ❌     | ✅ (conditions) | ✅ (conditions)   | ✅ (conditions)   | ✅            |
| Edit others' content      | ❌     | ❌              | ❌                | ✅ (in community) | ✅ (anywhere) |
| Delete others' content    | ❌     | ❌              | ❌                | ✅ (in community) | ✅ (anywhere) |
| Pin conversations         | ❌     | ❌              | ❌                | ✅                | ✅            |
| Manage community          | ❌     | ❌              | ❌                | ✅                | ✅            |

---

## Two-Community Model Permissions

Every new user gets **TWO communities automatically created**:

### 1. [Display Name] Circle (Private Personal Community)

- **Privacy:** Private (always, cannot be changed)
- **Access:** Invite-only (owner controls membership)
- **Permissions:**
  - Owner can: Create events, start conversations, invite members
  - Members can: Start conversations, reply, view all content
  - Members cannot: Create events (owner only), invite others (owner only)
  - Non-members cannot: See anything (completely hidden)
- **Leaving:** Members can leave; owner cannot delete (permanent)

### 2. [Display Name] (Public Community)

- **Privacy:** Public
- **Access:** Anyone can join (instant, no approval)
- **Permissions:**
  - Owner can: Create events, start conversations, manage settings
  - Members can: Start conversations, reply
  - Members cannot: Create events (owner/admin only)
  - Non-members can: View content, join instantly
- **Leaving:** Members can leave; owner cannot delete (permanent)

---

## Content Visibility Rules

### Public Content

- ✅ Guests can view (read-only)
- ✅ Logged-in users can view and participate
- ✅ Indexed by search engines (SEO)
- ✅ Listed in public directories

### Members-Only Content

- ❌ Guests cannot view
- ❌ Non-members cannot view
- ✅ Community members can view and participate
- ❌ Not indexed by search engines
- ❌ Not listed in public directories

### Private Content

- ❌ Only invited users can view
- ✅ Invited users can participate
- ❌ Not indexed by search engines
- ❌ Not listed anywhere public

---

## Editing/Deleting Rules

### Edit Time Limits

- **Option 1:** 15-30 minute window to edit freely
- **Option 2:** Unlimited editing with "edited" indicator and edit history

**Recommendation:** Use Option 2 (unlimited with indicator) for transparency

### Delete Conditions

**Conversations:**
- ✅ Can delete if no replies yet
- ❌ Cannot delete if has replies (shows as archived/deleted but preserves structure)

**Replies:**
- ✅ Can always delete own reply
- ❌ If reply has children, shows "[deleted]" placeholder instead of removing entirely

**Events:**
- ✅ Can delete if no RSVPs yet
- ❌ If has RSVPs, must archive/cancel (notifies attendees)

---

## Discovery Rules

### Public Communities

- ✅ Listed in community directory
- ✅ Searchable
- ✅ Anyone can join
- ✅ Content visible to guests

### Personal Circles

- ❌ Hidden from discovery
- ❌ Not searchable
- ❌ Invite-only
- ❌ Content hidden from non-members

### Private Communities (Future Feature)

- ❌ Hidden from discovery (unless you have link)
- ❌ Not in directory search
- ❌ Invite required to join
- ❌ Content hidden from non-members

---

## Implementation Notes

### Permission Check Methods

All permission checks should be implemented in Manager classes:

**VT_Community_Manager:**
- `canStartConversation($community_id, $user_id)` - member check
- `canCreateEvent($community_id, $user_id)` - owner/admin only
- `canSuggestEvent($community_id, $user_id)` - member check
- `canInviteMembers($community_id, $user_id)` - settings-dependent

**VT_Conversation_Manager:**
- `canEditConversation($conversation_id, $user_id)` - author/admin
- `canDeleteConversation($conversation_id, $user_id)` - author/admin + no replies
- `canPinConversation($conversation_id, $user_id)` - community admin/site admin

**VT_Event_Manager:**
- `canEditEvent($event_id, $user_id)` - author/community admin
- `canDeleteEvent($event_id, $user_id)` - author/site admin
- `canApproveEvent($event_id, $user_id)` - community admin

### UI Conditional Display

Templates should check permissions before showing actions:

```php
<?php if (VT_Conversation_Manager::canEditConversation($conversation_id, $user_id)): ?>
    <button class="vt-btn">Edit</button>
<?php endif; ?>

<?php if (VT_Conversation_Manager::canDeleteConversation($conversation_id, $user_id)): ?>
    <button class="vt-btn vt-btn-danger">Delete</button>
<?php endif; ?>
```

---

## Future Enhancements

### Planned Permission Features

1. **Suggest Event Workflow**
   - Members can suggest events in communities they don't admin
   - Community admins review and approve/reject suggestions
   - Status: `draft`, `suggested`, `active`, `cancelled`

2. **Custom Community Roles**
   - Beyond owner/admin/member
   - Moderator role (can pin/lock, but not delete)
   - Contributor role (can create events, but not manage)

3. **Trust Level System**
   - New members have limited permissions
   - Earn trust through participation
   - Unlock additional capabilities over time

4. **Request to Join Private Communities**
   - Non-members can request invitation
   - Admins review and approve/reject requests
   - Optional: Members can vouch for requesters

---

**This is a living document.** Update as permission rules evolve, but always maintain the core principles: graduated permissions, privacy by design, community-based control, and trust-based access.
