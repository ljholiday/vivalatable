# VivalaTable Coding Conventions

## Method Naming

### PHP Methods
- **All PHP class methods must use camelCase naming convention**
- Examples:
  - `getUserProfile()` ✅ (correct)
  - `get_user_profile()` ❌ (incorrect)
  - `sendRsvpInvitation()` ✅ (correct)
  - `send_rsvp_invitation()` ❌ (incorrect)

### WordPress Functions (Standalone)
- WordPress standalone functions use snake_case (this is WordPress core convention)
- Examples:
  - `current_time()` ✅ (WordPress function)
  - `wp_parse_args()` ✅ (WordPress function)

### Database Functions
- VT_Database class methods use camelCase
- Examples:
  - `$db->getResults()` ✅
  - `$db->getRow()` ✅
  - `$db->getVar()` ✅

## Class Naming

### VivalaTable Classes
- Use `VT_` prefix with underscore-separated words
- Examples:
  - `VT_Event_Manager` ✅
  - `VT_Guest_Manager` ✅
  - `VT_Community_Manager` ✅

## Variable Naming

### PHP Variables
- Use snake_case for local variables
- Examples:
  - `$current_user` ✅
  - `$event_data` ✅
  - `$rsvp_status` ✅

### Array Keys
- Use snake_case for array keys
- Examples:
  - `$data['event_date']` ✅
  - `$profile['display_name']` ✅

## File Naming

### PHP Class Files
- Use `class-` prefix with hyphen-separated words
- Examples:
  - `class-event-manager.php` ✅
  - `class-guest-manager.php` ✅

### Template Files
- Use hyphen-separated words with descriptive names
- Examples:
  - `dashboard-content.php` ✅
  - `guest-rsvp-content.php` ✅

## Database Conventions

### Table Names
- Use singular nouns with underscores
- VT prefix applied automatically by database class
- Examples:
  - `vt_events` ✅
  - `vt_guests` ✅
  - `vt_communities` ✅

### Column Names
- Use snake_case for all column names
- Examples:
  - `event_date` ✅
  - `created_at` ✅
  - `display_name` ✅

## Method Conversion Examples

### Before (snake_case methods - INCORRECT)
```php
$guest_manager->get_guest_by_token($token);
$event_manager->send_rsvp_invitation($email);
$conversation_manager->get_recent_conversations();
```

### After (camelCase methods - CORRECT)
```php
$guest_manager->getGuestByToken($token);
$event_manager->sendRsvpInvitation($email);
$conversation_manager->getRecentConversations();
```

## Static vs Instance Methods

### Static Method Calls
- Use `::` for static method calls
- Examples:
  - `VT_Auth::getCurrentUser()` ✅
  - `VT_Security::verifyNonce()` ✅

### Instance Method Calls
- Use `->` for instance method calls
- Examples:
  - `$manager->createEvent()` ✅
  - `$guest_manager->processRsvp()` ✅

## Common Patterns

### Manager Classes
- All manager classes use camelCase methods:
  - `VT_Event_Manager->createEvent()`
  - `VT_Guest_Manager->processAnonymousRsvp()`
  - `VT_Community_Manager->addMember()`
  - `VT_Conversation_Manager->getRecentConversations()`

### Utility Classes
- All utility classes use camelCase methods:
  - `VT_Sanitize::textField()`
  - `VT_Time::currentTime()`
  - `VT_Http::jsonSuccess()`

## Migration Notes

During the migration from PartyMinder (WordPress) to VivalaTable (LAMP), all method names were systematically converted from snake_case to camelCase to follow PHP best practices and maintain consistency across the application.

This conversion ensures:
1. Better IDE support and autocomplete
2. Consistency with modern PHP frameworks
3. Cleaner, more readable code
4. Compliance with PSR coding standards