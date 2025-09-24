# VivalaTable Complete Feature Documentation

This document captures all features, functions, classes, and architecture of VivalaTable as ported from PartyMinder WordPress plugin.

## Core Architecture

### Database Schema (17 Tables)
- `vt_users` - User accounts and authentication
- `vt_user_profiles` - Extended user profile data
- `vt_events` - Event records and metadata
- `vt_event_rsvps` - RSVP responses (user and guest)
- `vt_event_invitations` - Event invitation management
- `vt_communities` - Community/group records
- `vt_community_members` - Community membership and roles
- `vt_community_events` - Community-specific events
- `vt_community_invitations` - Community invitation system
- `vt_conversations` - Threaded discussion topics
- `vt_conversation_replies` - Nested conversation responses
- `vt_conversation_follows` - User conversation subscriptions
- `vt_guests` - Guest user management
- `vt_member_identities` - AT Protocol/Bluesky integration
- `vt_at_protocol_sync_log` - Bluesky synchronization logs
- `vt_ai_interactions` - AI assistant conversation history
- `vt_post_images` - Image attachments and media
- `vt_search` - Search indexing and optimization

### Core PHP Classes

#### Database.php
- Singleton PDO connection manager
- CRUD operation methods: `insert()`, `update()`, `delete()`, `selectOne()`, `select()`
- Transaction management: `beginTransaction()`, `commit()`, `rollback()`
- Table prefixing: `table($name)` returns `vt_$name`
- Secure token generation: `generateToken($length)`

#### User.php
- User account management
- Profile handling and updates
- Authentication integration
- Community and event statistics
- User search functionality
- Account deletion (soft delete)

#### EventManager.php
- Event creation and management
- RSVP system (user and guest)
- Guest invitation token generation
- Event statistics and reporting
- Event search and filtering
- Email invitation system

#### CommunityManager.php
- Community creation and management
- Membership system with roles (admin, moderator, member)
- Community event management
- Member permissions and capabilities
- Community search and discovery

#### GuestManager.php
- Guest invitation system with 32-character tokens
- Guest RSVP without account creation
- Guest-to-user conversion process
- Bulk invitation sending
- Guest statistics and tracking
- Email invitation templates

#### ConversationManager.php
- Threaded discussion system
- Nested reply support
- Participant management
- Conversation search
- Unread tracking
- Reply tree building

### Authentication System

#### Core Functions (includes/auth.php)
- `is_user_logged_in()` - Check authentication status
- `get_current_user_id()` - Get active user ID
- `vt_get_current_user()` - Get current user object
- `vt_login_user($email, $password, $remember)` - User authentication
- `vt_register_user($username, $email, $password, $display_name)` - Registration
- `vt_convert_guest_to_user($email, $name, $password)` - Guest conversion
- `vt_logout_user()` - Session termination
- `vt_user_can($capability, $object_id)` - Permission checking
- `vt_require_login($redirect_to)` - Authentication enforcement
- `vt_generate_password_reset_token($email)` - Password reset
- `vt_reset_password($token, $new_password)` - Password update

### Core Utility Functions (includes/functions.php)

#### URL and Navigation
- `vt_base_url($path)` - Generate application URLs
- `vt_redirect($url)` - Server-side redirects
- `vt_current_url()` - Get current request URL

#### Data Processing
- `vt_sanitize_text($text)` - Text input sanitization
- `vt_sanitize_textarea($text)` - Textarea sanitization
- `vt_escape_html($text)` - HTML output escaping
- `vt_escape_url($url)` - URL escaping
- `vt_escape_attr($attr)` - Attribute escaping

#### Date and Time
- `vt_format_date($date, $format)` - Date formatting
- `vt_time_ago($date)` - Relative time display

#### Email System
- `vt_send_email($to, $subject, $message, $headers)` - Email sending
- `vt_get_email_template($template, $data)` - Template rendering

#### Logging and Debugging
- `vt_log_error($message, $context)` - Error logging
- `vt_debug_log($data, $label)` - Debug output

#### Template System
- `vt_load_template($template, $data)` - Template loading
- `vt_get_template_part($template, $data)` - Partial templates

## Guest System Architecture

### Token-Based Invitations
- 32-character secure tokens for guest access
- Email-based guest identification
- No account required for RSVP
- Automatic user conversion option
- Token expiration and security

### Guest Workflow
1. Host sends invitation via email/Bluesky
2. Guest receives unique token link
3. Guest clicks link to RSVP page
4. Guest submits RSVP without creating account
5. Optional: Guest converts to full user account
6. System links all guest data to new account

### Guest Data Management
- Guest responses stored with email identifier
- Token validation and expiration
- Guest statistics and reporting
- Bulk invitation processing
- Guest-to-user data migration

## Event Management System

### Event Creation
- AI-powered event planning assistance
- Community integration
- Privacy level controls (public, private, community-only)
- Guest policy settings
- RSVP deadline management
- Menu and activity planning

### RSVP System
- User-based RSVPs with full profiles
- Guest RSVPs with token authentication
- Plus-one management
- Dietary restrictions tracking
- Accessibility needs accommodation
- Response statistics and reporting

### Event Features
- Event search and filtering
- Event sharing via email/Bluesky
- Event conversation threads
- Photo and media attachments
- Event reminders and notifications
- Host dashboard and management

## Community Management

### Community Structure
- Public and private communities
- Member roles: admin, moderator, member
- Community-specific events
- Discussion threads and conversations
- Member directory and profiles

### Community Features
- Community creation and customization
- Member invitation system
- Community event calendar
- Discussion moderation tools
- Community search and discovery
- Member statistics and analytics

## AT Protocol/Bluesky Integration

### Decentralized Identity
- DID (Decentralized Identifier) support
- AT Protocol handle verification
- Cross-platform identity linking
- Bluesky follower import

### Federated Features
- Event sharing to Bluesky
- Community discovery via AT Protocol
- Cross-platform conversation threading
- Distributed identity verification

### Sync System
- Bluesky data synchronization
- Follow relationship import
- Post and interaction sync
- Error handling and retry logic

## AI Assistant Integration

### Planning Assistance
- Event planning recommendations
- Menu suggestions based on preferences
- Activity ideas for different event types
- Guest management advice

### AI Features
- Natural language event creation
- Smart scheduling suggestions
- Automated invitation text generation
- Conversation topic suggestions

### Cost Tracking
- AI usage monitoring
- Cost per interaction tracking
- Usage analytics and reporting
- Budget management tools

## Template System

### Base Templates
- `templates/base/page.php` - Main page layout
- Navigation, header, footer structure
- User authentication state handling
- Mobile-responsive design

### Page Templates
- `public/home.php` - Homepage with feature showcase
- `public/login.php` - User authentication
- `public/register.php` - User registration
- `public/profile.php` - User profile management

### Component Templates
- Event cards and listings
- Community member displays
- Conversation threading
- RSVP forms and status

## CSS Framework (1000+ Classes)

### Prefix System
All classes use `pm-` prefix for namespace isolation:
- Layout: `.pm-container`, `.pm-grid`, `.pm-flex`
- Components: `.pm-card`, `.pm-btn`, `.pm-form-row`
- Typography: `.pm-heading`, `.pm-text-muted`, `.pm-link`
- Utilities: `.pm-mb-4`, `.pm-gap-4`, `.pm-text-center`

### Component Library
- Form elements and validation
- Button styles and states
- Card layouts and containers
- Navigation and breadcrumbs
- Modal and overlay systems
- Responsive grid system

## Public Pages and Routing

### Routing System (index.php)
- Static routes: `/`, `/login`, `/register`, `/logout`
- Dynamic routes: `/events/{slug}`, `/communities/{slug}`, `/conversations/{slug}`
- API endpoints: `/api/test`
- 404 handling for unknown routes

### Page Structure
- Homepage: Feature showcase, user dashboard
- Authentication: Login/register forms
- Events: Listing, detail, creation pages
- Communities: Browse, detail, management
- Conversations: Threading, replies, search
- Profile: Settings, statistics, preferences

## Security Features

### Authentication Security
- Password hashing with PHP password_hash()
- Session management with regeneration
- Remember me functionality
- Password reset with secure tokens

### Input/Output Security
- All inputs sanitized with `vt_sanitize_*()` functions
- All outputs escaped with `vt_escape_*()` functions
- SQL injection prevention with prepared statements
- CSRF protection for form submissions

### Guest Security
- Secure token generation for invitations
- Token expiration and validation
- Email verification for guest conversion
- Rate limiting on invitation sending

## Database Migration System

### Schema Management
- Complete schema in `migrations/schema.sql`
- Foreign key constraints for data integrity
- Proper indexing for performance
- Character set: utf8mb4 for full Unicode support

### Migration Strategy
- WordPress to LAMP stack migration
- Data transformation and cleanup
- User account migration
- Event and RSVP data transfer
- Community membership migration

## Configuration System

### Database Configuration
- `config/database.php` - Database connection settings
- PDO options and charset configuration
- Table prefix management
- Connection error handling

### Application Settings
- Base URL configuration
- Upload directory management
- Timezone settings
- Version tracking

## Error Handling and Logging

### Error Management
- PHP error logging
- Database operation error handling
- Email sending failure management
- Authentication error tracking

### Debug System
- Development mode logging
- Query debugging and optimization
- Performance monitoring
- Error reporting and alerting

## File Upload System

### Image Management
- Event photo attachments
- User avatar uploads
- Community banner images
- File type validation and security

### Upload Features
- Secure file handling
- Image resizing and optimization
- File organization and storage
- Upload quota management

This documentation serves as the complete reference for VivalaTable's architecture and features as migrated from the PartyMinder WordPress plugin.