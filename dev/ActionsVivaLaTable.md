# VivaLaTable

[Today](#today)

2025-09-23 Tuesday

Fuck it. Start over.

2025-09-26 Friday

5032c8c (HEAD \-\> lonn) Progress: Working through the instructions

1560880 (origin/main, main) More progress

63dd11c Some functionality working

9af0e5e Set one source of truth for the DB setup.

d75fca0 Stop tracking database config

752fd5b Added .gitignore

a9c7453 Major progress

24f2264 Basically an initial commit

5d6aefc Initial VivalaTable standalone application

My commit messages need better headlines. 

2025-09-26 Friday

Moving VivaLaTable forward. 

I am impressed at how this is coming together, actually.

Established the username/display name convention across the VivalaTable codebase.

Which of course was immediately forgotten. Claudia is a fucking moron.

2025-09-27 Saturday

Turns out a solution to keep claudia from forgetting shit is to write a list if instructions with the last instruction being to write the list before every response. That way she won't forget. I'm testing an instruction the read the critical principles section to save space. We'll see how that goes.

Remove [Start Conversation](http://localhost:8081/conversations/create)[Create Event](http://localhost:8081/events/create)[Browse Events](http://localhost:8081/events)[Communities](http://localhost:8081/communities) from the dashboard. I did this manually. I also adjusted the sidebar widths. Needed to add a missing class. vt-sidebar-section

Claudia thinks she set up the circles of trust. I don't think so. Maybe.

Fixed account creation and login.

2025-09-28 Sunday

Commited Personal Community stuff. Now personal communities are created for new members automatically.

More community stuff. Circles of trust might work now. 

For the time being, joining a community is automatic. Click join, you're joined. We will make this part of a larger invite, join, rsvp, request, accept modular thing later.

Ran /init. Got a new [CLAUDE.md](http://CLAUDE.md) file.

Read git log. It's much better now and has more information than I can remember to put here.

2025-09-29 Monday

Fixed create member and create default community.

Cleaned up css.

2025-09-30 Tuesday

Cleaned up documentation. Total cleanup.

Fixed commuinity conversation circles logic and implimentation.

2025-10-02 Thursday
All day yesterday cleaning up bits and pieces.
Started on oembed yesterday. Finished today.



#### **Today** {#today}

#### **Thursday 2**


This looks good. I want to audit these things across events, conversations, and communities. Delete buttons, edit buttons, manage buttons, etc. It doesn't make sense to me that

# Events Page

My Events - All Events - RSVPs (Top nav)

## Event Cards

View - Manage (Buttons in cards)

## Event Page

Manage

Create Conversation

## Magage Event

Settings - Guests - Invitations


# Conversations Page

Inner - Trusted - Extended - All - My Events - All Events (Top nav)

## Conversation Page

Has a conversation button.

### Edit conversation

# Communities Page

My Comunities - All Communities (Top nav)

## Community Page



# Document this
Can a conversation have an event ID and a community ID?
If a conversation is created in an event...
If a conversation is created in a community...
An event must have a community ID or event ID.
If a conversation is created in an event, it has an event ID.
If a conversation is created in a community, it has a community ID.
A conversation can only be created in either a community or an event.
A conversation can not be created without a community or event ID.
An event must have a community. But an event conversation does not need to be connected to that community.

A conversation created in an event does not need a community ID. Only an event ID.
A conversation created in an event should not have a community ID. I think this is the crucial understanding.
A conversation created in a community does need a community ID.

If a conversation is created in an event, is there any reason it would need a community ID?


⏺ User approved Claude's plan:
  ⎿  Clean up conversation creation: remove standalone button, dropdown, and fix validation logic

     1. Remove standalone button (templates/partials/sidebar-secondary-nav.php:34):
     - Delete "Create Conversation" button (line 34-35)
     - Conversations only created from events or communities

     2. Remove community dropdown (templates/create-conversation-content.php:61-75):
     - Delete entire community dropdown section
     - Keep hidden inputs for event_id and community_id (already exist)

     3. Fix validation logic (includes/class-pages.php:538-617):
     - Replace current validation (line 594-596) with if/elseif pattern:
     if (!empty($conversation_data['event_id'])) {
         // Event conversation - set event_id, null community_id
     } elseif (!empty($conversation_data['community_id'])) {
         // Community conversation - set community_id, null event_id
     } else {
         $errors[] = 'Conversation must be created in an event or community.';
     }
     - On GET: If no event_id/community_id in URL, redirect to /conversations

     Result:
     - Simpler: No dropdown confusion
     - Clearer: Conversations belong to one context only
     - Safer: if/elseif makes mutual exclusivity automatic


Looking at a new conversation and there is a blue bar below both the Events tab and the Conversaitons tab. I can not duplicate this.

There is still a select community dropdown on the edit event conversation page.

Event pages do not show who created the event.







⏺ Plan: Add Edit/Delete UI Buttons (Skip Pin for Now)

  What We're Doing

  Add Edit and Delete buttons to events and conversations, using the permission methods we just created. Skip Pin
  functionality - add it to script as future task.

  Phase 1: Event Management

  File: templates/manage-event-content.php

  1. Fix Cancel Event Button
  - Currently exists but does nothing
  - Make it update event_status to 'cancelled'
  - Add JavaScript confirmation dialog
  - Show success message after cancellation

  2. Add Danger Zone Section
  - Add at bottom of manage page (after all other sections)
  - Similar to community danger zone
  - Red border styling, warning icon/text
  - Contains Delete Event button
  - Only show Delete button if $event_manager->canDeleteEvent($event_id, $user_id)
  - Warning text: "Deletion is permanent. Only available if no confirmed RSVPs exist."
  - Require confirmation dialog before actual delete

  Phase 2: Conversation Management

  File: templates/single-conversation-content.php

  1. Add Edit Button to Conversation Header
  - Instantiate $conversation_manager = new VT_Conversation_Manager() at top
  - Get $current_user_id = vt_service('auth.service')->getCurrentUserId()
  - Add button near conversation title/header area
  - Only show if $conversation_manager->canEditConversation($conversation->id, $current_user_id)
  - Link to /conversations/{slug}/edit (route doesn't exist yet, will create later)
  - Use existing button styles: class="vt-btn vt-btn-secondary"

  2. Delete Button - Deferred
  - Will add when we create /conversations/{slug}/manage page
  - Keep consistent with events/communities pattern (Danger Zone)
  - Not doing in this phase

  What We're NOT Doing

  - Pin/Unpin functionality (add to script backlog)
  - Conversation delete button (waiting for manage page)
  - Edit conversation page/route (separate task)
  - Delete conversation page/route (separate task)

  Implementation Pattern

  // At top of template
  $manager = new VT_Event_Manager(); // or VT_Conversation_Manager
  $current_user_id = vt_service('auth.service')->getCurrentUserId();

  // In HTML
  <?php if ($manager->canEditEvent($event->id, $current_user_id)): ?>
      <a href="/events/<?php echo $event->slug; ?>/edit" class="vt-btn vt-btn-secondary">
          Edit
      </a>
  <?php endif; ?>

  <?php if ($manager->canDeleteEvent($event->id, $current_user_id)): ?>
      <button class="vt-btn vt-btn-danger" onclick="confirmDelete(<?php echo $event->id; ?>)">
          Delete Event
      </button>
  <?php endif; ?>

  Files to Modify

  1. templates/manage-event-content.php - Cancel button fix + Danger Zone
  2. templates/single-conversation-content.php - Edit button only

  Script Update

  Add to Priority 6 (UI/Layout Improvements):
  - Implement Pin/Unpin conversation functionality (toggle method, AJAX endpoint, UI button)








That is the fix I want today. We can leave Create Event for everyone in a public community. Maybe I create a public community for 1965 Mustangs but over the years many people come and go and the community develops a soul. Anyone in that community should be able to create an event. It's a public community. Or maybe I'm that super popular guy, I know this guy, that throws big parties at least every month. That is a large community. People in that community know each other, not necessarily that guy. Anyone in that community should be able to create an event. The current logic works if we don't show the create event to community members in private communitiies. Only the founder can. That is why we create private communities. 






  ⎿  Comprehensive Image System for VivalaTable

     Current State Analysis

     ✅ What Exists

     - VT_Image_Manager - Basic upload/resize functionality
     - Profile/cover photo upload UI in forms
     - Upload directory structure (/uploads/vivalatable/)
     - Basic GD library image processing
     - Gravatar integration for avatars

     ❌ What's Missing

     1. No responsive thumbnails - Single size only, not optimized for different devices
     2. No lazy loading - All images load immediately
     3. No WebP conversion - Missing modern format for better compression
     4. No srcset/picture elements - No responsive image markup
     5. No client-side preview - Upload button exists but no live preview
     6. No AJAX uploads - Full page reload on image upload
     7. No image optimization - No compression beyond basic quality settings
     8. No CDN integration - Direct filesystem serving only
     9. Limited embed image handling - OG images not displaying properly
     10. No avatar generation - Relying entirely on Gravatar

     Proposed Comprehensive Image System

     Phase 1: Core Image Service (Foundation)

     1. VT_Image_Service (Main Orchestrator)

     Central service for all image operations:
     - Upload handling with validation
     - Generate multiple thumbnail sizes
     - WebP conversion with fallback
     - Metadata storage
     - Cache management

     Thumbnail Sizes:
     'avatar_sm' => 32x32      // Small avatars in lists
     'avatar_md' => 64x64      // Medium avatars in cards
     'avatar_lg' => 120x120    // Large profile avatars
     'thumbnail' => 300x300    // Card thumbnails
     'medium' => 600x600       // Medium images
     'large' => 1200x1200      // Large images
     'cover' => 1200x400       // Cover photos

     2. VT_Image_Processor (Image Manipulation)

     Handles actual image operations:
     - Load/save images (JPEG, PNG, GIF, WebP)
     - Resize with aspect ratio preservation
     - Crop to exact dimensions
     - Quality optimization
     - Format conversion
     - EXIF data stripping (privacy + size)

     3. VT_Image_Storage (File Management)

     Manages filesystem operations:
     - Organized directory structure
     - Unique filename generation
     - Cleanup of old files
     - Storage quota tracking
     - CDN upload preparation

     4. VT_Avatar_Generator (Generated Avatars)

     Creates custom avatars when users don't upload:
     - Initial-based avatars (colorful circles with letters)
     - Geometric patterns (identicons)
     - Fallback to Gravatar
     - Cache generated avatars

     Phase 2: Responsive Image Delivery

     5. VT_Image_Renderer (HTML Generation)

     Generates responsive image markup:
     // Generates:
     <picture>
       <source srcset="image.webp" type="image/webp">
       <source srcset="image-large.jpg 1200w, image-medium.jpg 600w"
               sizes="(max-width: 768px) 100vw, 50vw">
       <img src="image.jpg" alt="..." loading="lazy">
     </picture>

     Features:
     - srcset for different resolutions
     - sizes attribute for responsive layouts
     - WebP with fallback
     - Lazy loading
     - Blur placeholder (LQIP)

     Phase 3: Upload Experience

     6. AJAX Upload System

     Modern upload without page reload:
     - Drag & drop interface
     - Live image preview before upload
     - Progress bar
     - Instant crop/rotate UI
     - Client-side validation
     - Multiple file selection

     JavaScript (assets/js/image-upload.js):
     - FileReader API for preview
     - XMLHttpRequest/Fetch for upload
     - Progress events
     - Error handling
     - Image cropping library (e.g., Cropper.js)

     PHP Endpoint (/api/upload-image):
     - Validates upload
     - Processes image
     - Returns JSON with URLs
     - Error responses

     Phase 4: Embed Image Enhancement

     7. Fix Open Graph Image Display

     Currently not rendering OG images. Need to:
     - Update VT_Embed_Renderer to properly handle image_url
     - Add image loading states
     - Lazy load embed images
     - Cache external images locally (optional)

     8. External Image Proxy (Optional)

     For security/performance:
     - Proxy external images through VivalaTable
     - Cache remote images
     - Resize external images
     - Prevent mixed content warnings

     Phase 5: Optimization & Advanced Features

     9. Image Optimization

     - Automated compression (ImageOptim, TinyPNG API)
     - Strip EXIF data
     - Progressive JPEG
     - Optimize PNG palettes
     - Convert animated GIF to video (MP4/WebM)

     10. CDN Integration

     - Upload to CDN (Cloudflare, Bunny, AWS S3)
     - Serve images from CDN URLs
     - Automatic invalidation
     - Fallback to local storage

     Implementation Plan

     Step 1: Foundation Classes

     1. Create VT_Image_Service - Central orchestrator
     2. Create VT_Image_Processor - GD/Imagick wrapper
     3. Create VT_Image_Storage - File management
     4. Update VT_Image_Manager to use new services

     Step 2: Responsive Images

     1. Generate thumbnails on upload (all sizes)
     2. Create VT_Image_Renderer for srcset/picture
     3. Update templates to use renderer
     4. Add lazy loading attributes

     Step 3: Avatar Generation

     1. Create VT_Avatar_Generator
     2. Generate initial-based avatars
     3. Integrate with member display
     4. Cache generated avatars

     Step 4: Upload UX

     1. Create AJAX upload endpoint
     2. Build drag-drop UI component
     3. Add live preview
     4. Integrate cropping tool
     5. Update profile/community forms

     Step 5: Embed Fixes

     1. Debug OG image display issue
     2. Add lazy loading to embeds
     3. Add loading skeletons
     4. Cache external images

     Step 6: Optimization

     1. WebP conversion on upload
     2. EXIF stripping
     3. Quality optimization
     4. Progressive loading

     Database Schema

     New Table: vt_images

     CREATE TABLE vt_images (
         id INT PRIMARY KEY AUTO_INCREMENT,
         user_id INT NOT NULL,
         filename VARCHAR(255),
         original_filename VARCHAR(255),
         mime_type VARCHAR(50),
         file_size INT,
         width INT,
         height INT,
         context VARCHAR(50), -- 'profile', 'cover', 'post', 'community'
         entity_id INT, -- Related entity ID
         sizes JSON, -- {\"thumbnail\": \"path.jpg\", \"medium\": \"path.jpg\"}
         metadata JSON, -- EXIF, alt text, caption
         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
         INDEX idx_user (user_id),
         INDEX idx_context (context, entity_id)
     );

     Security Considerations

     1. File Validation:
       - MIME type verification
       - Extension whitelist
       - File signature checking
       - Size limits (5MB default)
     2. Path Traversal Prevention:
       - Sanitize filenames
       - Restrict upload directories
       - Validate file paths
     3. XSS Prevention:
       - Strip EXIF data
       - Validate image dimensions
       - Prevent SVG uploads (or sanitize)
     4. Resource Limits:
       - Memory limits for processing
       - Disk quota per user
       - Rate limiting uploads

     CSS Requirements

     /* Responsive images */
     .vt-image-container {
         position: relative;
         overflow: hidden;
     }

     .vt-image-lazy {
         opacity: 0;
         transition: opacity 0.3s;
     }

     .vt-image-lazy.loaded {
         opacity: 1;
     }

     /* Upload UI */
     .vt-dropzone {
         border: 2px dashed #ccc;
         border-radius: 8px;
         padding: 40px;
         text-align: center;
         cursor: pointer;
     }

     .vt-dropzone.dragging {
         border-color: var(--vt-primary);
         background: var(--vt-primary-light);
     }

     /* Avatar generation */
     .vt-avatar-generated {
         display: flex;
         align-items: center;
         justify-content: center;
         font-weight: 600;
         color: white;
     }

     Deliverables

     New Files:
     - includes/Image/ImageService.php
     - includes/Image/ImageProcessor.php
     - includes/Image/ImageStorage.php
     - includes/Image/ImageRenderer.php
     - includes/Image/AvatarGenerator.php
     - assets/js/image-upload.js
     - templates/components/image-uploader.php

     Updated Files:
     - includes/class-image-manager.php - Refactor to use new services
     - includes/Embed/Renderer.php - Fix OG image display
     - templates/profile-content.php - AJAX upload
     - templates/manage-community-content.php - AJAX upload
     - templates/partials/member-display.php - Use generated avatars
     - assets/css/style.css - Image components

     Documentation:
     - docs/IMAGES.md - Complete image system guide











Hamburger menu.
The sidebar does not turn into a hamburger menu on mobile.

<permissions>
## Permissions

⏺ Based on my research and VivalaTable's philosophy, here's my recommended permission model with precedent and rationale:

  VivalaTable Permission Model Design

  Core Principles (from research):

  1. Graduated Permissions (Discord, Slack model) - Different roles have different capabilities
  2. View vs Participate Split (Discourse model) - Can see ≠ can interact
  3. Community-Based Control (all platforms) - Community owners/admins have ultimate say
  4. Trust-Based Access (VivalaTable philosophy) - Circles of Trust inform permissions
  5. Privacy by Design (VivalaTable core value) - Default to more restrictive, allow opening up

  ---
  Recommended Answers with Rationale:

### Comunities
  COMMUNITIES

  1. Can I join a public community?
  - YES - Instant join without approval
  - Precedent: Discord public servers, Slack public channels
  - Rationale: "Public" should mean truly open; reduces friction for community growth

  2. Can I join a private community?
  - NO - Must be invited by admin/member
  - Precedent: Discord private servers (invite-only), Slack private channels
  - Rationale: Privacy by design; community owner controls membership

  3. Can I leave a community I'm a member of?
  - YES - Always (except maybe "personal" communities?)
  - Precedent: Universal across all platforms
  - Rationale: User autonomy; can't trap people in communities

  4. Can I start a conversation in someone else's community?
  - YES, if you're a member
  - NO, if you're not a member
  - Precedent: Discourse - must be in category to post; Slack - must be in channel
  - Rationale: Membership gates participation; prevents spam; aligns with Circles of Trust (you trust members)

  5. Can I create an event in someone else's community?
  - YES, if you're a member (with optional admin-only toggle)
  - Precedent: Facebook Groups allow members to create events
  - Rationale: Encourages community activity; admins can moderate/delete if needed

  6. Can I invite others to a community I'm a member of (but don't admin)?
  - Depends on community settings:
    - Public communities: YES - anyone can share the link
    - Private communities: Optional setting (admin choice)
  - Precedent: Discord allows members to generate invite links if permissions allow
  - Rationale: Balances growth with control; admin can restrict if needed

  7. Can I see members of a community I'm not a member of?
  - Public communities: YES - see member list
  - Private communities: NO - can't see anything
  - Precedent: LinkedIn groups show member counts; Facebook shows some members
  - Rationale: Transparency for public; privacy for private

  ---
  CONVERSATIONS

  8. Can I view a public conversation?
  - YES - Anyone, even non-logged-in guests
  - Precedent: Reddit public posts, Discourse public topics
  - Rationale: Discoverability; SEO; encourages sign-ups

  9. Can I view a private conversation I'm not invited to?
  - NO
  - Precedent: Universal
  - Rationale: Privacy by design

  10. Can I view a "members only" conversation if I'm in the community?
  - YES - All members can view
  - Precedent: Discord channel-specific permissions, Discourse category permissions
  - Rationale: "Members only" means community members; aligns with Circles of Trust

  11. Can I reply to any conversation I can view?
  - If logged in: YES
  - If guest: NO - must create account
  - Exception: Locked/archived conversations - NO ONE can reply
  - Precedent: Discourse trust levels, Reddit locked threads
  - Rationale: View permission ≠ participate permission; anti-spam; encourages accounts

  12. Can I edit my own conversation after posting?
  - YES - with time limit (15-30 minutes?) OR show "edited" indicator
  - Precedent: Reddit (3 minute window), Discourse (unlimited with edit history)
  - Rationale: Fix typos/errors; "edited" indicator maintains transparency

  13. Can I delete my own conversation?
  - YES - if no replies yet
  - NO - if has replies (archive/lock instead)
  - Precedent: Reddit (can delete own posts), Stack Overflow (can't delete if answers exist)
  - Rationale: You started it, but once others contribute, it's community content

  14. Can I edit/delete someone else's conversation in my community?
  - Community Admin: YES - edit/delete/pin/lock
  - Precedent: Discord moderators, Discourse moderators
  - Rationale: Community management needs; prevent abuse; maintain standards

  15. Can I edit my own reply after posting?
  - YES - same rules as conversation (time limit or "edited" indicator)
  - Precedent: Universal

  16. Can I delete my own reply?
  - YES - Always (shows "[deleted]" placeholder if has child replies)
  - Precedent: Reddit deleted comments, Discourse
  - Rationale: Right to remove your content; preserve threading structure

  17. Can I pin conversations in communities I admin?
  - Community Admin: YES
  - Site Admin: YES (any community)
  - Precedent: Discord pinned messages, Reddit stickied posts
  - Rationale: Highlight important discussions; guide community

  ---
  EVENTS

  18. Can I view a public event?
  - YES - Anyone
  - Rationale: Same as public conversations; discoverability

  19. Can I view a private event I'm not invited to?
  - NO
  - Precedent: Facebook private events
  - Rationale: Privacy by design

  20. Can I RSVP to any event I can view?
  - Public events: YES (if logged in)
  - Private events: NO - must be invited
  - Precedent: Facebook events, Eventbrite
  - Rationale: View ≠ participation for private; public events are open invitations

  21. Can I edit my own event after creating?
  - YES - Always (with notification to attendees if major changes)
  - Precedent: Facebook events, Eventbrite
  - Rationale: Details change; attendees need updates

  22. Can I delete my own event?
  - YES - if no RSVPs yet
  - Archive only - if has RSVPs (notify attendees)
  - Precedent: Eventbrite cancellation policy
  - Rationale: Once people commit, can't just vanish; cancel properly

  23. Can I edit/delete someone else's event in my community?
  - Community Admin: YES
  - Rationale: Community management; prevent inappropriate events

  24. Can I invite guests to someone else's event?
  - NO - only event creator invites
  - Exception: If event is public, anyone can share the link
  - Precedent: Most event platforms restrict invites to creator
  - Rationale: Event creator controls guest list; prevents spam

  ---
  GENERAL ROLES

  25. What can site admins do that regular users can't?
  - View/edit/delete ANY content across ALL communities
  - Manage user accounts (suspend/ban)
  - Access admin dashboard and analytics
  - Manage site-wide settings
  - Precedent: Universal admin powers
  - Rationale: Site management and moderation needs

  26. What can community admins do that members can't?
  - Edit/delete any conversation or event in their community
  - Pin/lock conversations
  - Manage community settings (visibility, description, etc.)
  - Invite/remove members (private communities)
  - Assign/remove other admins
  - Precedent: Discord server owners, Facebook group admins
  - Rationale: Community governance and quality control

  27. Can guests (non-logged-in users) view anything?
  - YES: Public conversations, public events, public communities (descriptions/member counts)
  - NO: Cannot participate, RSVP, or join
  - Precedent: Reddit, Discourse (read-only for guests)
  - Rationale: Discoverability; SEO; conversion funnel to sign-ups

  ---
  Summary Permission Matrix

  | Action                    | Guest | User           | Community Member | Community Admin  | Site Admin   |
  |---------------------------|-------|----------------|------------------|------------------|--------------|
  | View public content       | ✅     | ✅              | ✅                | ✅                | ✅            |
  | View members-only content | ❌     | ❌              | ✅                | ✅                | ✅            |
  | Join public community     | ❌     | ✅              | -                | -                | -            |
  | Post in community         | ❌     | ❌              | ✅                | ✅                | ✅            |
  | Edit own content          | ❌     | ✅ (time limit) | ✅ (time limit)   | ✅ (time limit)   | ✅            |
  | Delete own content        | ❌     | ✅ (conditions) | ✅ (conditions)   | ✅ (conditions)   | ✅            |
  | Edit others' content      | ❌     | ❌              | ❌                | ✅ (in community) | ✅ (anywhere) |
  | Delete others' content    | ❌     | ❌              | ❌                | ✅ (in community) | ✅ (anywhere) |
  | Pin conversations         | ❌     | ❌              | ❌                | ✅                | ✅            |
  | Manage community          | ❌     | ❌              | ❌                | ✅                | ✅            |

  This model balances openness (public content is discoverable) with privacy (members-only content is protected) while
  empowering community admins to manage their spaces effectively, all aligned with VivalaTable's Circles of Trust
  philosophy.



I should not be able to start a conversation in someone elses community?
Should I be able to join a community? Request to join?
Public/Private
Events
Conversations
Communitites

⏺ Permission Questions to Answer

  Communities:
  1. Can I join a community if it is public?
  2. Can I join a community if it is private?
  3. Can I leave a community I'm a member of?
  4. Can I start a conversation in someone else's community?
  5. Can I create an event in someone else's community?
  6. Can I invite others to a community I'm a member of (but don't admin)?
  7. Can I see members of a community I'm not a member of?

  Conversations:
  8. Can I view a public conversation?
  9. Can I view a private conversation I'm not invited to?
  10. Can I view a "members only" conversation if I'm in the community?
  11. Can I reply to any conversation I can view?
  12. Can I edit my own conversation after posting?
  13. Can I delete my own conversation?
  14. Can I edit/delete someone else's conversation in my community?
  15. Can I edit my own reply after posting?
  16. Can I delete my own reply?
  17. Can I pin conversations in communities I admin?

  Events:
  18. Can I view a public event?
  19. Can I view a private event I'm not invited to?
  20. Can I RSVP to any event I can view?
  21. Can I edit my own event after creating?
  22. Can I delete my own event?
  23. Can I edit/delete someone else's event in my community?
  24. Can I invite guests to someone else's event?

  General:
  25. What can site admins do that regular users can't?
  26. What can community admins do that members can't?
  27. Can guests (non-logged-in users) view anything?
</permissions>







## Admin Dashboard
We also need an admin dashboard.





What is the playwright mcp? How does it work with claude code? Ask claudia.


## Login/register
We need an email based verification system for member accounts.



## Images
We will need a robust image system that keeps images in member directories and adjusts image sizes. We will need an alt text field.


## Invitations/RSVP/bluesky
We need a unified invitation section for events and communities that includes bluesky followers.



## Instructions
Please read dev/[CLAUDE.md](http://CLAUDE.md) and dev/[CONTRIBUTING.md](http://CONTRIBUTING.md) 

Tip: Run claude \--continue or claude \--resume to resume a conversation

Tip: Want Claude to remember something? Hit **\#** to add preferences, tools, and instructions to Claude's memory

Tip: Use **/memory** to view and manage Claude memory

Tip: Use **git worktrees** to run multiple Claude sessions in parallel.

    Tip: Use @<filename> to read a file into context.

Options are table, csv, json, strict-table, strict-csv, and strict-json. The default will be a table.)

Tip: Use Plan Mode to prepare for a complex request before making changes. Press **shift+tab twice** to enable.

    Use /model to choose opusplan mode. 

I don't see /changelog in my instance of claude code.

Fetch(https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)



#### **Friday 3**

#### **Saturday 4**

[Read](https://dev.to/siddhantkcode/extending-claudes-brain-how-mcp-makes-ai-actually-useful-for-developers-35c4)

[Read](https://dev.to/siddhantkcode/an-easy-way-to-stop-claude-code-from-forgetting-the-rules-h36)

[Watch](https://www.youtube.com/watch?v=rfDvkSkelhg)



#### **Sunday 5**

#### **Monday 6**

#### **Tuesday 7**

#### **Wednesday 8**

#### **Thursday 9**

#### **Friday 10**

#### **Saturday 11**

#### **Sunday 12**

#### **Monday 13**

#### **Tuesday 14**

#### **Wednesday 15**

#### **Thursday 16**

#### **Friday 17**

#### **Saturday 18**

#### **Sunday 19**

#### **Monday 20**

#### **Tuesday 21**

#### **Wednesday 22**

#### **Thursday 23**

#### **Friday 24**

#### **Saturday 25**

#### **Sunday 26**

#### **Monday 27**

#### **Tuesday 28**

#### **Wednesday 29**

#### **Thursday 30**

#### **Friday 31**

\>\>
