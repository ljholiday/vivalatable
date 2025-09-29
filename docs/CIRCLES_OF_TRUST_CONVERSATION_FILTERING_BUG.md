# Circles of Trust Conversation Filtering Bug

## Problem Summary

The conversations page on `/conversations` shows all conversations initially, but clicking any circle filter button (Inner/Trusted/Extended) clears the screen instead of filtering conversations by the user's social network.

## Expected Behavior

1. User visits `/conversations`
2. Sees conversations from their **Inner Circle** (communities they belong to + events in those communities)
3. Clicks **"Trusted"** button → sees conversations from Inner Circle + communities their community members belong to
4. Clicks **"Extended"** button → sees conversations from Trusted Circle + communities those members belong to
5. Each click should show progressively more conversations based on expanding social network

## Actual Behavior

1. User visits `/conversations`
2. Sees **all conversations** (no filtering applied)
3. Clicks any circle button → **screen goes completely blank**
4. No conversations displayed at all

## Technical Details

### Frontend JavaScript
- File: `assets/js/conversations.js`
- Makes AJAX POST request to `/ajax/conversations`
- Sends parameters: `circle`, `filter`, `page`, `nonce`
- Expects JSON response with `html` containing filtered conversations

### Backend AJAX Handler
- Route: `POST /ajax/conversations` → `VT_Conversation_Ajax_Handler::ajaxGetConversations`
- Should use circle filtering logic to return appropriate conversations
- Currently returning empty results instead of filtered conversation list

### Core Issue
The VivalaTable implementation is missing the **Circles of Trust filtering logic** that:
1. Resolves which communities the user belongs to (Inner Circle)
2. Finds communities that those members belong to (Trusted Circle)
3. Finds communities that those members belong to (Extended Circle)
4. Returns conversations from communities and events within the selected circle

## Impact

**This breaks the fundamental value proposition of VivalaTable.**

The Circles of Trust system is the **anti-algorithm social filtering mechanism** that makes the platform unique. Without it working:
- Users get no content filtering based on their social network
- The platform becomes just another generic social media site
- The community-centric, relationship-based content discovery is broken

## Files Involved

### VivalaTable (Broken)
- `templates/conversations-content.php` - Template with circle filter buttons
- `assets/js/conversations.js` - Frontend AJAX calls
- `includes/class-conversation-ajax-handler.php` - AJAX endpoint handler
- `includes/class-conversation-feed.php` - Conversation filtering logic
- `includes/class-circle-scope.php` - Circle resolution (may be incomplete)

### PartyMinder (Working Reference)
- `includes/class-circle-scope.php` - Complete circle resolution logic
- `includes/class-circles-resolver.php` - Enhanced circle calculation
- `includes/class-conversation-feed.php` - Working conversation filtering
- `docs/circle-based-conversation-filtering.md` - Complete technical documentation

## Solution Required

1. **Fix the AJAX handler** to properly implement circle filtering
2. **Ensure circle resolution logic** matches PartyMinder's implementation
3. **Test that each circle returns appropriate conversations** based on community membership relationships
4. **Verify progressive expansion** - Inner ⊆ Trusted ⊆ Extended

## Definition of Done

- [ ] User visits `/conversations` and sees only Inner Circle conversations
- [ ] Clicking "Trusted" shows more conversations (never fewer)
- [ ] Clicking "Extended" shows even more conversations (never fewer)
- [ ] Each circle properly filters based on community membership relationships
- [ ] No blank screens when switching between circles
- [ ] Conversations include both community discussions and event discussions from appropriate circles