# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Architecture

VivalaTable is a complete LAMP migration of the PartyMinder WordPress plugin - a professional event management and community platform with anti-algorithm social filtering.

### Core Business Logic
- **Event Management** - Create, manage, RSVP with guest limits and privacy controls
- **Community Platform** - Communities with membership management and community-specific events
- **Guest System** - 32-character token-based invitations allowing RSVP without registration
- **Circles of Trust** - Anti-algorithm social filtering with granular privacy controls based on community membership relationships
- **Conversation System** - Community discussions with trust-circle-based filtering

### Database Architecture
- All tables use `vt_` prefix (replaces WordPress `wp_pm_` tables)
- Core tables: `vt_events`, `vt_communities`, `vt_guests`, `vt_users`, `vt_conversations`, `vt_community_members`
- Guest system requires exactly 32-character tokens in `vt_guests.rsvp_token` field
- Circles of Trust implemented through `vt_community_members` relationships

## Development Commands

```bash
# Database operations
mysql -u root -proot vivalatable
mysql -u root -proot partyminder_db  # Source reference

# Compare schemas
mysql -u root -proot vivalatable -e "SHOW TABLES LIKE 'vt_%';"
mysql -u root -proot partyminder_db -e "SHOW TABLES LIKE 'wp_pm_%';"

# Test guest token system (must be exactly 32 characters)
mysql -u root -proot vivalatable -e "SELECT LENGTH(rsvp_token) FROM vt_guests;"

# Check circle filtering implementation
mysql -u root -proot vivalatable -e "SELECT DISTINCT privacy FROM vt_events;"
```

## Critical Migration Rules

### Security and Standards
- All PHP methods use camelCase (never snake_case)
- All CSS classes use `.vt-` prefix (never generic names)
- All forms require CSRF tokens via `VT_Security::createNonce()`
- All output escaped with `htmlspecialchars()`
- All database queries use prepared statements
- No feature flags - all functionality must be enabled and working

### Code Organization (per dev/code.xml)
- PHP: Server logic only in `includes/class-*.php`
- JavaScript: Behavior only in separate `assets/js/*.js` files (no inline scripts > 3-5 lines)
- HTML: Structure only in `templates/*.php`
- CSS: Presentation only in `assets/css/vivalatable.css` with `.vt-` prefixes

### WordPress Migration Methodology
- Port each PartyMinder class method exactly - never delete functionality without understanding purpose
- Map WordPress fields to VT database schema properly (critical: verify column names match code expectations)
- Replace WordPress functions: `get_option()` → `VT_Config::get()`, `wp_hash()` → `VT_Security::hash()`
- Preserve guest system architecture: 32-character tokens, RSVP without registration
- Maintain Circles of Trust filtering: community membership-based content visibility

## File Structure

```
includes/class-*.php          # Business logic classes (port from PartyMinder)
templates/*.php               # Page templates (convert from WordPress shortcodes)
assets/js/*.js               # JavaScript behavior (separate by functionality)
assets/css/vivalatable.css   # All styles with .vt- prefixes
config/database.php          # Database configuration
script.md                    # Migration progress tracking (keep updated)
```

## Testing and Validation

### Guest Token System Validation
```php
// Test 32-character token generation
$token = VT_Security::generateToken(32);  // Must be exactly 32 chars
// Test guest RSVP without registration
// Test guest-to-user conversion process
```

### Circles of Trust Validation
```php
// Test community membership relationships
// Test conversation filtering by trust circles (Inner/Trusted/Extended)
// Test event privacy controls based on community membership
```

## Common Issues

### Database Schema Mismatches
- Always verify database column names before writing insert/update statements
- Cross-reference PartyMinder source for expected field names
- Database schema and AJAX handlers often created in different sessions

### Missing Community Manager Methods
Current gaps requiring implementation:
- `getPublicCommunities()` - Community discovery
- `isMember()` - Membership status checking
- `removeMember()` - Leave community functionality
- `updateMemberRole()` - Admin controls

### AJAX Endpoint Requirements
- Return JSON with `success` boolean and `html` content for frontend updates
- Include CSRF token validation
- Handle both logged-in and guest user states
- Follow camelCase naming for method names

## Migration Status

Track progress in `script.md`. Current critical gaps:
- Community discovery and joining system (blocks organic community growth)
- Missing AI Assistant integration
- Missing AT Protocol/Bluesky integration

Reference PartyMinder source at:
- `/Users/lonnholiday/social.partyminder.com/wp-content/plugins/partyminder/`
- Documentation: `/Users/lonnholiday/Repositories/vivalatable-docs/`