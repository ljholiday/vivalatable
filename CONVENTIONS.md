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

## User Identity Convention

### Username vs Display Name
VivalaTable uses a clear distinction between usernames and display names:

#### Username (`username` field)
- **Purpose**: Used for login authentication and @mentions
- **Format**: Unique identifier, alphanumeric, no spaces
- **Database**: Stored in `vt_users.username` column
- **Usage**: `$user->username`, `@username` mentions
- **Examples**: `john_doe`, `sarah2024`, `admin`

#### Display Name (`display_name` field)
- **Purpose**: User's preferred name shown throughout the application
- **Format**: Human-readable name, can contain spaces and special characters
- **Database**: Stored in both `vt_users.display_name` and `vt_user_profiles.display_name`
- **Usage**: All user-facing displays, member lists, author credits
- **Examples**: `John Doe`, `Sarah Smith`, `Dr. Johnson`

### Implementation Guidelines

#### Priority Order
When displaying user names, always use this priority order:
1. `vt_user_profiles.display_name` (user's preferred display name)
2. `vt_users.display_name` (fallback display name)
3. `vt_users.username` (final fallback)

```php
// Correct implementation
$display_name = $profile['display_name'] ?: $user->display_name ?: $user->username;
```

#### Database Fields
- **`vt_users.username`**: Login identifier (unique, required)
- **`vt_users.display_name`**: Basic display name (set during registration)
- **`vt_user_profiles.display_name`**: User's preferred display name (editable in profile)

#### UI Guidelines
- **Profile editing**: Always show "Display Name" field for user customization
- **Member displays**: Always use display name, never show username to users
- **Login forms**: Accept "Username or Email" for authentication
- **@Mentions**: Use @username format for consistency

### Migration Notes
The username convention was established to replace the original `login` field:
- Database field renamed from `login` to `username` for clarity
- All references updated to use the new naming convention
- Maintains backward compatibility through proper fallback handling

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