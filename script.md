# VivalaTable Migration Project

## What We're Building
**Complete LAMP migration of PartyMinder WordPress plugin to standalone application**

VivalaTable is a professional event management and community platform that replicates all PartyMinder functionality without WordPress dependencies:

- **Event Management System** - Create, manage, and RSVP to events with guest limits and privacy controls
- **Community Platform** - Create communities with membership management and community-specific events
- **Guest System** - 32-character token-based guest invitations allowing RSVP without registration
- **Circles of Trust Privacy** - Anti-algorithm social filtering with granular privacy controls
- **User Management** - Registration, authentication, profiles, and statistics tracking
- **Conversation System** - Community discussions with privacy-based filtering
- **AI Assistant Integration** - AI-powered event planning and management features
- **AT Protocol/Bluesky Integration** - Social media connectivity

## Project Status: ACTIVE MIGRATION
**Last Updated:** 2025-09-28
**Current Phase:** Community System Integration Issues

## Understanding Circles of Trust

### Core Concept
**Anti-algorithm social filtering system** that replaces traditional algorithmic feeds with relationship-based content visibility:

1. **Inner Circle** - Content from communities you're a member of (direct relationships)
2. **Trusted Circle** - Inner + content from communities created by members of your communities (friend-of-friend)
3. **Extended Circle** - Trusted + content from the broader network (friend-of-friend-of-friend)

### Implementation Status
- **Conversations**: Circles of Trust filtering working correctly
- **Communities**: Discovery system partially functional, membership sync broken
- **Events**: Privacy system exists but needs validation

### Critical Business Logic
The Circles of Trust system is the **core differentiator** from traditional social platforms. It ensures users see content based on real social relationships rather than algorithmic manipulation.

## Current Status Assessment

### What's Working ✅
- **Basic Infrastructure**: Database, routing, authentication, templates
- **Event Management**: Core creation, listing, RSVP functionality
- **User Management**: Registration, login, profiles
- **Conversation System**: Circle filtering working properly
- **Guest Token System**: 32-character tokens validated and working
- **JavaScript Modularization**: Separated into focused files per code standards

### Critical Issues Identified ❌

#### 1. Community Membership System Broken
**PROBLEM:** Community membership state is inconsistent across the platform
- Joined communities don't appear in "My Communities" tab
- Cross-user community visibility broken
- Member status not properly synchronized
- Community growth mechanism fails

**ROOT CAUSE:** Incomplete integration between:
- Community joining AJAX endpoint
- Template membership queries
- Cross-user visibility logic

#### 2. Missing Community Management Methods
**PROBLEM:** VT_Community_Manager missing 9 critical methods from PartyMinder
- `getAdminCount()` - Admin validation
- `removeMember()` - Member removal
- `updateMemberRole()` - Role management
- `getCommunityInvitations()` - Invitation system
- `cancelInvitation()` - Invitation management
- `getCommunityStats()` - Analytics
- `generateUniqueSlug()` - URL generation
- `ensureMemberHasDid()` - Identity management
- `generateCommunityDid()` - Community identity

**IMPACT:** Community administration and growth features non-functional

#### 3. Personal Community Visibility Issues
**PROBLEM:** Personal communities (user's own communities) not appearing in discovery
- Personal communities should be visible to others in "All Communities"
- Privacy/visibility logic not properly implemented
- Community creation may not be setting proper visibility flags

#### 4. Conversation Navigation Broken
**PROBLEM:** Clicking community conversations redirects to wrong page
- Community conversations should stay within community context
- Instead redirects to general conversations page
- Breaks community-specific discussion flow

### Architecture Issues Discovered

#### Community Discovery Logic Gap
PartyMinder has sophisticated two-layer community system:
1. **My Communities** - User's memberships with role badges and management options
2. **All Communities** - Public discovery with join functionality

VivalaTable implementation:
- ✅ UI tabs exist and switch properly
- ❌ Backend membership queries incomplete
- ❌ Cross-user visibility broken
- ❌ Join workflow partially functional

#### Missing Integration Points
- Community joining doesn't update "My Communities" view
- Member status changes don't propagate across UI
- Personal communities don't appear in public discovery
- Community privacy/visibility settings incomplete

## Phase Status: Community System Integration

### Completed This Phase ✅
- [DONE] Fixed JavaScript tab switching functionality
- [DONE] Created community discovery template structure
- [DONE] Implemented basic AJAX join endpoint
- [DONE] Added missing Community Manager scaffolding methods
- [DONE] Resolved JavaScript syntax errors

### Current Issues Blocking Progress ❌
- [CRITICAL] Community membership state synchronization broken
- [CRITICAL] Cross-user community visibility not working
- [CRITICAL] Personal community discovery broken
- [CRITICAL] Missing core Community Manager methods
- [CRITICAL] Conversation navigation redirects incorrectly

### What We Learned About the Problem
The community system requires **deep integration** across multiple layers:
1. **Database Layer** - Membership queries and visibility logic
2. **Business Logic** - Community Manager methods and privacy rules
3. **UI Layer** - Template membership state and cross-user views
4. **Navigation** - Proper routing and context preservation

The current implementation addressed only the UI layer. The backend integration is incomplete.

## Next Steps: Community System Deep Integration

### Immediate Priority (Next Session)
**Focus:** Fix community membership synchronization and cross-user visibility

#### Step 1: Audit Community Membership Queries
```bash
# Test current membership detection
curl -b /tmp/cookies.txt "http://localhost:8081/communities" | grep -A 10 "My Communities"

# Check database state
mysql -u vivalatable -p'ZNJnGg7GvJxYFPqM' -h 127.0.0.1 vivalatable -e "
SELECT c.name, cm.user_id, cm.role, cm.status
FROM vt_communities c
JOIN vt_community_members cm ON c.id = cm.community_id
WHERE cm.status = 'active'"
```

#### Step 2: Fix Community Manager Integration
1. **Complete missing methods** - Implement all 9 missing Community Manager methods
2. **Fix membership queries** - Ensure "My Communities" shows all user memberships
3. **Fix visibility logic** - Ensure personal communities appear in "All Communities"
4. **Test cross-user scenarios** - Verify user A can see user B's public communities

#### Step 3: Fix Conversation Navigation
1. **Audit conversation routing** - Fix community conversation redirects
2. **Preserve community context** - Keep users within community discussion flow
3. **Test conversation filtering** - Verify circle filtering still works

#### Step 4: End-to-End Validation
1. **Test complete user journey** - Create community → invite member → join → view content
2. **Test circle filtering** - Verify content appears in correct circles
3. **Test privacy controls** - Ensure private communities respect visibility rules

### Success Criteria
- User joins community → appears in "My Communities" immediately
- Personal communities visible in other users' "All Communities" tab
- Community conversations stay within community context
- Member status updates propagate across all UI elements
- Circle filtering shows correct content based on membership relationships

### After Community System Fixed
1. **Complete missing class implementations** (AI Assistant, AT Protocol)
2. **Advanced privacy system validation**
3. **Performance optimization**
4. **Production readiness**

## Key Architectural Decisions

### Community System Architecture
- **Two-Layer Discovery** - Personal membership vs public discovery
- **Real-time Membership** - AJAX joining with immediate UI updates
- **Privacy-First Design** - Circles of Trust determine visibility
- **Context Preservation** - Community discussions stay in community context

### Integration Requirements
- Community joining must update both database and UI state
- Membership queries must be consistent across all templates
- Privacy rules must be enforced at database query level
- Cross-user visibility must respect community privacy settings

## Testing Strategy

### Community System Tests
```bash
# Test 1: Join community and verify membership
# Test 2: Check cross-user community visibility
# Test 3: Verify personal community discovery
# Test 4: Test conversation navigation context
# Test 5: Validate circle filtering integration
```

### Critical User Journeys
1. **New User Journey** - Register → discover communities → join → participate
2. **Community Creator Journey** - Create community → invite members → manage discussions
3. **Cross-User Discovery** - Browse public communities → join → access content
4. **Privacy Validation** - Create private community → verify access controls

## Critical Discovery: Personal Community Creation Broken

**NEW FINDING (2025-09-28):** Created new user account - no communities visible including missing default personal community.

**IMPLICATIONS:**
- Personal community auto-creation during registration is broken
- This explains why personal communities don't appear in "All Communities"
- New users have no starting point for community participation
- Registration workflow incomplete

**ROOT CAUSE ANALYSIS NEEDED:**
- Check if personal communities are created during user registration
- Verify community creation sets proper visibility flags
- Audit user registration workflow for missing community setup

**PRIORITY ESCALATION:** This is now a **registration system bug** not just a discovery bug.

## Notes

This script reflects our current understanding after discovering the community system integration issues. The focus has shifted from basic functionality to deep system integration across multiple architectural layers.

The Circles of Trust system is working for conversations but needs proper integration with the community membership and discovery system to function as intended.

**Critical Finding:** New user registration does not create personal communities, breaking the fundamental user onboarding experience.