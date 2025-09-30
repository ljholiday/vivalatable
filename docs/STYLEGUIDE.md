# VivalaTable Style Guide

This style guide defines how VivalaTable looks, feels, and speaks. It ensures consistency across code, UI, documentation, and product vocabulary.

---

## 1. Voice & Tone

- **Human, not corporate**: Write like a trusted friend, not a platform
- **Optimistic, not hype**: Emphasize connection, trust, and real life. Avoid buzzwords
- **Simple, not jargon**: If a normal person wouldn't say it at a dinner party, don't write it
- **Anti-algorithm**: Position as alternative to algorithmic feeds and engagement optimization

### Core Message

VivalaTable is an **anti-algorithm social platform** where you control what you see through **Circles of Trust** - human-curated networks, not engagement algorithms.

---

## 2. Vocabulary & Terminology

### The Three Pillars

Always use **Communities, Conversations, Events** as the three core features.

### Official Terms

- **Communities** (not groups, forums, pages)
- **Conversations** (not posts, threads, topics)
- **Messages** (units inside conversations)
- **Connections** (not followers, friends, contacts)
- **Circles** (trust levels)
- **Members** (not users, people participating)

### Action Verbs

✅ **Use these:**
- **Start a Conversation** (create new discussion)
- **Send a Message** (contribute to existing conversation)
- **Reply** (respond to someone)
- **Share** (spread content)
- **Join a Community** (become a member)
- **RSVP** (respond to event)
- **Invite** (bring others in)
- **Connect** (establish relationship)

❌ **Never use:**
- **Post** (too generic, no specific meaning - banned)
- **Tweet** (platform-specific)
- **Status** (vague, meaningless)
- **Follow/Unfollow** (algorithmic social media language)
- **Like** (engagement metric thinking)

### Circle Terminology

**The Three Circles** (always capitalize Circle when referring to trust levels):

- **Circle 1 — Close** (also: Inner Circle, Close Circle, Personal Circle)
- **Circle 2 — Trusted** (also: Trusted Circle, Friend-of-Friend)
- **Circle 3 — Extended** (also: Extended Circle, Broader Network)

**Usage examples:**
- "This conversation is visible to your Close Circle"
- "Start a conversation in your Trusted Circle"
- "Extend this to your Extended Circle to reach more people"

---

## 3. Two-Community Model

### The Architecture

Every new user automatically gets **TWO communities**:

#### 1. [Display Name] Circle (Private Personal Community)
- **Privacy**: Private (always, cannot be changed)
- **Access**: Invite-only (owner controls membership)
- **Purpose**: Close Circle (Circle 1) - trusted inner circle
- **Discovery**: Hidden from discovery, not searchable
- **Naming Convention**: No apostrophes - "Lonn Holiday Circle" not "Lonn Holiday's Circle"

**Vocabulary for UI:**
- Button text: "Invite to My Circle"
- Headings: "Your Circle" or "[Name]'s Circle"
- Descriptions: "Your private, invite-only circle for your closest connections"

#### 2. [Display Name] (Public Community)
- **Privacy**: Public
- **Access**: Anyone can join (instant, no approval)
- **Purpose**: Public-facing content, discoverable presence
- **Discovery**: Listed in community directory, searchable
- **Naming Convention**: Just the display name - "Lonn Holiday"

**Vocabulary for UI:**
- Button text: "Join [Name]'s Community"
- Headings: "Public Community" or "[Name]'s Community"
- Descriptions: "Public community where anyone can join and participate"

### Rationale (For Product/Marketing Copy)

- **Solves Cold Start Problem**: New users have instant discoverability via public community
- **Privacy by Design**: Inner circle remains protected and invite-only
- **Discovery Mechanism**: Public space allows connections before trust is established
- **Graduated Trust**: Follow publicly → earn invitation to private circle
- **No Technical Nightmares**: No apostrophes means clean URLs, database, and sorting

### UI Copy Examples

**New User Onboarding:**
> "Welcome! We've created two communities for you:
>
> **Your Circle** - Your private space for close connections
> **Your Community** - Your public presence where others can find you
>
> Start by inviting your closest friends to Your Circle, then share your Community with the world."

**Community Tabs:**
- "My Circle" (private)
- "My Community" (public)
- "Other Communities" (browsing)

**Join vs Invite Buttons:**
- Public communities: "Join Community" (instant)
- Personal Circles: "Request Invitation" (approval required)

---

## 4. Anti-Algorithm Philosophy

### Key Messaging

**What We Are:**
- Human-curated trust networks
- You control what you see
- Content organized by relationship proximity, not engagement
- Privacy-first design
- Real connections over metrics

**What We're Not:**
- Not an algorithmic feed
- Not engagement optimization
- Not attention economy
- Not surveillance capitalism
- Not "growth hacking"

### Copy Examples

**Landing Page:**
> "An Actually Social Network
>
> No algorithms deciding what you see.
> No engagement metrics manipulating your feed.
> Just you, your circles of trust, and real connections."

**Circles of Trust Explanation:**
> "Circles of Trust put you in control:
>
> **Close Circle**: Your inner circle - content from communities you're in
> **Trusted Circle**: Friend-of-friend connections you trust
> **Extended Circle**: The broader network, discovered through trust
>
> You decide how far your content reaches. Not an algorithm."

**Feature Descriptions:**
> "Start Conversations that matter to the people you trust, not the algorithm."
> "Join Communities based on shared interests, not engagement metrics."
> "RSVP to Events in real life, not just online."

---

## 5. UI Components & Design Language

### Color System (Variables)

```css
:root {
  --vt-primary: #4B0082;      /* Indigo Purple */
  --vt-secondary: #E6739F;    /* Warm Pink */
  --vt-surface: #FFFFFF;      /* White */
  --vt-background: #F9F5F0;   /* Soft Beige */
  --vt-text: #444444;         /* Soft Charcoal */
  --vt-success: #2E8B57;      /* Emerald Green */
  --vt-error: #DC143C;        /* Crimson */
}
```

### Typography

- **Headings**: Poppins Bold or Montserrat Bold
- **Body Text**: Inter Regular or Open Sans
- **Buttons/UI Labels**: Poppins Medium

**Rules:**
- Headings: Sentence case (not all caps)
- Body: Short paragraphs, max 2–3 sentences
- Links: Underlined by default

### UI Component Standards

**Buttons:**
- Rounded corners (8–12px)
- Solid fills for primary actions
- Outline for secondary actions
- Always use action verbs: "Start Conversation", "Join Community", "RSVP"

**Cards/Sections:**
- Minimal shadow
- Soft rounded edges
- Consistent padding (16–24px)
- Clear hierarchy

**Forms:**
- Vertical layout
- Labels above inputs
- Brand colors for accents only
- Clear error states

**Avatars & Banners:**
- Circular avatars
- Wide rectangular banners
- Always allow easy upload/change

---

## 6. Copywriting Do's & Don'ts

### Do

✅ Say: "Send a Message to your Circle"
✅ Say: "This event is visible to your Trusted Circle"
✅ Say: "Join Conversations that matter"
✅ Say: "Connect with people you trust"
✅ Say: "Your Close Circle includes..."
✅ Say: "Start a Conversation in your Community"

### Don't

❌ Don't use "post," "tweet," or "status" EVER
❌ Don't call Circles "layers" or "degrees of separation"
❌ Don't use "followers" or "friends" in UI copy - use **Connections**
❌ Don't use algorithmic language: "trending," "viral," "boost," "engagement"
❌ Don't use metrics-focused copy: "views," "reach," "impressions"
❌ Don't use apostrophes in community names: "Lonn's Circle" → "Lonn Holiday Circle"

---

## 7. Code & CSS Naming

### CSS Class Prefix

All classes must use `.vt-` prefix:

```css
.vt-button          /* Buttons */
.vt-card            /* Cards */
.vt-conversation    /* Conversation components */
.vt-circle-badge    /* Circle indicators */
.vt-community-card  /* Community listings */
```

### Layout Templates

- `main` - General content pages
- `two-column` - Dashboard and settings
- `form` - Create/edit screens

### Responsive Design

- Mobile-first approach
- Extend with media queries
- Touch-friendly tap targets (44px minimum)

---

## 8. Accessibility

### Requirements

- Maintain AA contrast ratio for all text (4.5:1 minimum)
- Provide alt text for all images
- Don't rely on color alone for meaning
- Use semantic HTML
- Keyboard navigation support
- Screen reader friendly labels

### ARIA Labels for Circles

```html
<button aria-label="Filter to Close Circle">Close</button>
<button aria-label="Filter to Trusted Circle">Trusted</button>
<button aria-label="Filter to Extended Circle">Extended</button>
```

---

## 9. Iconography

- **Simple line icons**: Consistent stroke weight
- **Meaningful symbols**: Avoid abstract representations
- **Circle Indicators**: Use concentric circle icons for trust levels
  - 1 circle = Close
  - 2 circles = Trusted
  - 3 circles = Extended

---

## 10. Photography & Imagery

### Guidelines

- Encourage authentic event/community photos
- Avoid stock imagery
- Show real people at real events
- Prioritize diversity and inclusion
- No staged corporate photos

### Default Images

- Placeholder avatars: Initials on colored background
- Placeholder community covers: Abstract patterns, not photos
- Placeholder event images: Location-based imagery

---

## 11. Error Messages & System Copy

### Tone for Errors

- Helpful, not judgmental
- Clear about what went wrong
- Actionable next steps
- Human language, not error codes

### Examples

**Good:**
> "We couldn't send your message. Please check your connection and try again."

**Bad:**
> "Error 403: Forbidden. Access denied."

**Good:**
> "This conversation is only visible to the Close Circle. Join the community to participate."

**Bad:**
> "You do not have permission to view this resource."

---

## 12. Documentation Style

### Technical Documentation

- Use second person ("you") when addressing developers
- Be direct and concise
- Code examples for complex concepts
- Link to relevant files and line numbers

### User-Facing Help

- Friendly, conversational tone
- Step-by-step instructions
- Screenshots where helpful
- Emphasize the "why" not just the "how"

---

## 13. Naming Conventions for Files

### Templates

```
dashboard-content.php          # Main dashboard
create-conversation-content.php # Create conversation form
single-community-content.php    # Single community view
```

### JavaScript

```
conversations.js    # Conversation functionality
circles.js          # Circle filtering
communities.js      # Community features
```

### CSS

All styles in `assets/css/style.css` with `.vt-` prefixes.

---

## 14. Brand Header for Code Files

Every top-level CSS and JavaScript file should begin with:

```javascript
/**
 * ======================================================
 *  VivalaTable – An Anti-Algorithm Social Platform
 *  Human-curated trust networks. You control what you see.
 * ======================================================
 *
 *  File: [filename]
 *  Description: [what this file handles]
 *
 *  Vocabulary:
 *  - Communities, Conversations, Events (the three pillars)
 *  - Circles (Close, Trusted, Extended)
 *  - Messages (not posts)
 *  - Connections (not followers)
 *
 *  Never use: post, tweet, status, followers, viral
 *
 * ======================================================
 */
```

---

## 15. Future Vocabulary Decisions

As features are added, maintain consistency:

- **Notifications**: "Updates" not "Notifications"
- **Bookmarks**: "Save" not "Bookmark"
- **Blocking**: "Block" is acceptable for safety
- **Reporting**: "Report" for content violations
- **Search**: "Find" or "Search" both acceptable

**When in doubt**: Choose the most human, least technical term that clearly describes the action.

---

**This is a living document.** Update as vocabulary and features evolve, but always maintain the core principles: human language, anti-algorithm positioning, trust-based relationships.
