⏺ VivalaTable Migration Plan: PartyMinder WordPress Plugin to LAMP Application
# Required reading
~/Repositories/vivalatable-docs/VIVALATABLE-MIGRATION.md
~/Repositories/vivalatable-docs/INSTRUCTIONS.md
~/Repositories/vivalatable-docs/CONTRIBUTING.md
All files in ~/Repositories/vivalatable-docs/

# Required documentation
Keep your notes and instructions in ~/Repositories/vivalatable-docs/
Keep complete documentation of your plan. Update incessently.
Keep your database structure records here. Update incessently.
Every instruction that includes map, list, document, analyze, record, MUST result in a document in the ~/Repositories/vivalatable-docs/ directory.

#  Phase 1: Complete Analysis of PartyMinder Plugin

  Step 1.1: Document Current Database Schema

## Export current PartyMinder database structure
  mysqldump -u root -proot --no-data --routines --triggers partyminder_db > partyminder_schema.sql

## List all tables with pm_ prefix
  mysql -u root -proot partyminder_db -e "SHOW TABLES LIKE 'wp_pm_%';"

## For each table, document structure and relationships
  mysql -u root -proot partyminder_db -e "DESCRIBE wp_pm_events;"
  mysql -u root -proot partyminder_db -e "DESCRIBE wp_pm_communities;"
## Continue for all tables...

  Step 1.2: Analyze Core Business Logic

## Map all PHP classes in PartyMinder
  find /Users/lonnholiday/Documents/partyminder -name "class-*.php" -exec basename {} \;

## For each class, document:
## - Public methods and their parameters
## - Database operations performed
## - WordPress hooks used
## - Dependencies on other classes

  Classes to analyze:
  1. class-event-manager.php - Event CRUD, RSVP system, guest tokens
  2. class-community-manager.php - Community management, membership
  3. class-guest-manager.php - Guest invitation system, token management
  4. class-user.php - User management, authentication
  5. class-conversation-manager.php - Discussion system, privacy controls
  6. class-ai-assistant.php - AI integration features
  7. class-at-protocol-manager.php - Bluesky integration (if implemented)

  Step 1.3: Document Guest System Architecture

## Analyze guest token generation and validation
  grep -r "guest.*token" /Users/lonnholiday/Documents/partyminder/includes/
  grep -r "32.*character" /Users/lonnholiday/Documents/partyminder/includes/

## Document guest-to-user conversion process
  grep -r "convert.*guest" /Users/lonnholiday/Documents/partyminder/includes/

  Step 1.4: Map WordPress Dependencies

## Identify all WordPress functions used
  grep -r "wp_" /Users/lonnholiday/Documents/partyminder/includes/ > wp_dependencies.txt
  grep -r "get_option\|update_option" /Users/lonnholiday/Documents/partyminder/includes/ >> wp_dependencies.txt

## Document hooks and filters
  grep -r "add_action\|add_filter" /Users/lonnholiday/Documents/partyminder/includes/ > wp_hooks.txt

  Step 1.5: Analyze "Circles of Trust" Privacy System

## Search for privacy-related logic
  grep -r -i "privacy\|permission\|access\|trust" /Users/lonnholiday/Documents/partyminder/includes/
  grep -r "can_user\|user_can" /Users/lonnholiday/Documents/partyminder/includes/

## Document conversation filtering logic
  grep -r "conversation.*filter\|filter.*conversation" /Users/lonnholiday/Documents/partyminder/includes/

#  Phase 2: Create VivalaTable Architecture Plan

  Step 2.1: Design Database Schema Translation

  -- Create mapping document from WordPress tables to LAMP tables
  -- wp_pm_events -> vt_events
  -- wp_pm_communities -> vt_communities
  -- etc.

  -- Ensure all columns match between code expectations and database reality
  -- Document any column renames needed (privacy_level -> visibility, etc.)

  Step 2.2: Plan WordPress Function Replacements

  // Create replacement functions for WordPress dependencies
  // get_option() -> custom config system
  // wp_hash() -> custom hash function
  // current_user_can() -> custom permission system
  // etc.

  Step 2.3: Design URL Routing System

  // Map WordPress shortcodes to LAMP routes
  // [partyminder_events] -> /events
  // [partyminder_communities] -> /communities
  // [partyminder_dashboard] -> /dashboard

  Phase 3: Implementation Steps

##  Step 3.1: Database Schema Creation

  # Start with clean database
  mysql -u root -proot -e "DROP DATABASE IF EXISTS vivalatable; CREATE DATABASE vivalatable CHARACTER SET utf8mb4 COLLATE 
  utf8mb4_unicode_ci;"

## Create exact table structure based on analysis
## Ensure column names match what the code expects
## Include ALL required columns from the start

  Step 3.2: Core Infrastructure

  // 1. Database singleton class with proper error handling
  // 2. Authentication system matching guest token architecture  
  // 3. Permission system replicating "circles of trust"
  // 4. Configuration system replacing get_option/update_option
  // 5. Utility functions replacing WordPress helpers

  Step 3.3: Business Logic Classes (Port exactly from PartyMinder)

  CRITICAL: NO FEATURE FLAGS - All functionality must be enabled and working. Do not use feature flags for anything.

  CRITICAL MIGRATION METHODOLOGY:

  // Port each class methodically:
  // 1. Copy method signatures exactly
  // 2. Map WordPress fields to VT database schema fields properly - DO NOT DELETE
  // 3. Understand what each removed piece of functionality was supposed to do
  // 4. Keep a record of what needs proper replacement vs. removal
  // 5. Replace WordPress database calls with PDO
  // 6. Replace WordPress functions with custom equivalents
  // 7. Implement Circles of Trust system (CRITICAL: Anti-algorithm social filtering)
  // 8. Test that the migrated functionality actually works, not just that it doesn't throw errors
  // 9. Test each method individually before moving to next

  NEVER:
  - Simply delete WordPress-specific fields without understanding their purpose
  - Remove functionality just because it references WordPress
  - Mark something "complete" when only syntax errors are fixed
  - Assume unused classes don't need to work (profiles are essential for membership sites)
  - Use feature flags for anything - ALL functionality must be enabled and working

  Step 3.4: Frontend Pages

  // Port each page/shortcode:
  // 1. Convert WordPress shortcode logic to PHP pages
  // 2. Ensure CSS classes remain exactly the same (pm-* prefixes)
  // 3. Test each page individually
  // 4. Fix any missing CSS or broken styles

  Step 3.5: Guest System Implementation

  // Replicate exact guest workflow:
  // 1. Token generation (32-character tokens)
  // 2. Email invitation system
  // 3. Guest RSVP without registration
  // 4. Guest-to-user conversion process
  // 5. Session management for guests vs users

##  Phase 4: Testing and Validation

  Step 4.1: Unit Testing

## Test each business method individually
## Verify database operations work correctly
### Test permission system with various user roles
## Validate guest token system

  Step 4.2: Integration Testing

## Test complete user workflows:
## - User registration and login
## - Event creation and RSVP
## - Community creation and joining  
## - Guest invitation and RSVP
## - Conversation creation and replies

  Step 4.3: Migration Testing

## Create migration scripts to move data from PartyMinder to VivalaTable
##  # Test with copy of production data
## Validate data integrity after migration

##  Phase 5: Production Deployment

  Step 5.1: Server Configuration

## Configure production LAMP stack
## Set up SSL certificates
## Configure database with proper credentials
## Set up backup systems

  Step 5.2: Data Migration

## Run migration scripts on production data
## Validate all data transferred correctly
## Test all functionality with real data

  Key Guidelines from CLAUDE.md:

  1. NO EMOJIS - Professional tone only
  2. NEVER ADD FEATURES - Only port existing functionality
  3. SECURITY FIRST - No exposed secrets, proper validation
  4. TEST EVERYTHING - Don't ask user to test, test yourself
  5. FOLLOW EXISTING PATTERNS - Match PartyMinder's architecture
  6. 1000+ CSS CLASSES - Keep all pm-* classes working
  7. GUEST SYSTEM CRITICAL - 32-character tokens, no registration required
  8. CIRCLES OF TRUST - Understand and replicate privacy system exactly

  Success Criteria:

  - All PartyMinder functionality works identically in VivalaTable
  - Database schema matches code expectations perfectly
  - CSS styling renders correctly on all pages
  - Guest system works exactly like PartyMinder
  - Migration scripts successfully move production data
  - No WordPress dependencies remain
  - All security requirements met
  - Performance acceptable for millions of users

  This plan requires executing each step completely before moving to the next. No shortcuts, no assumptions, no "quick
  fixes" that create more problems.


