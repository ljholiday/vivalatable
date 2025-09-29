# Contributing to PartyMinder

Thank you for considering a contribution to the PartyMinder plugin. This document outlines the preferred practices for contributing code, fixing bugs, updating templates, and maintaining project consistency.

---

## 1. Project Philosophy

PartyMinder prioritizes:
- **Professional presentation**: Clean, business-appropriate interface without emojis or decorative symbols
- **Minimal, semantic CSS** with consistent `.pm-` prefixed class names
- **Shared layouts and templates** to avoid code duplication
- **WordPress integration**: Proper use of WordPress APIs, hooks, and security practices
- **Stability across themes**: Works reliably with any WordPress theme
- **Clean, maintainable code**: Modular structure with clear separation of concerns
- **Database integrity**: Safe schema changes with proper migrations

Avoid:
- **Emojis or decorative symbols** - This is a professional business application
- **Utility-first CSS** (e.g., `.p-4`, `.mt-2`) - Use semantic class names
- **Generic class names** (e.g., `.card`, `.button`, `.header`) - Always use `.pm-` prefix
- **Inline styles or IDs** for styling - Keep styles in the main CSS file
- **Duplicated layout structures** - Use shared template system

---

## 2. Development Setup

### Local Environment
1. **WordPress Local Development**: Use Local by Flywheel, MAMP, XAMPP, or similar
2. **Plugin Location**: Place in `wp-content/plugins/partyminder/`
3. **Database**: Ensure clean WordPress installation for testing
4. **Theme Testing**: Test with default WordPress themes (Twenty Twenty-Three, etc.)

### Clone and Setup
1. Clone the repository:
   ```bash
   git clone https://github.com/[your-org]/partyminder.git
   cd partyminder
   ```

2. **Activate Plugin**: Go to WordPress Admin > Plugins > Activate PartyMinder
3. **Check Setup**: Verify plugin pages are created and accessible
4. **Test Environment**: Create test events, users, and communities

---

## 3. How to Contribute

### Branch Management
1. **Create feature branch** from `main`:
   ```bash
   git checkout -b feature/descriptive-feature-name
   git checkout -b fix/specific-bug-description
   git checkout -b refactor/component-name
   ```

2. **Branch Naming Conventions**:
   - `feature/` - New functionality
   - `fix/` - Bug fixes
   - `refactor/` - Code improvements without functionality changes
   - `docs/` - Documentation updates

### Development Workflow
1. **Make changes** following coding guidelines below
2. **Test thoroughly** across different user scenarios
3. **Run quality checks** (see Section 6)
4. **Update documentation** if needed
5. **Commit with clear messages** (see commit format below)
6. **Push and create pull request**

### Commit Message Format
```bash
# Format: Type: Brief description (50 chars max)
# Examples:
git commit -m "Add: Event invitation email templates"
git commit -m "Fix: RSVP form validation for anonymous users"
git commit -m "Update: Database schema documentation"
git commit -m "Remove: Deprecated emoji usage from templates"
```

---

## 4. Coding and Styling Guidelines

### WordPress Standards
- **Follow WordPress Coding Standards** exactly
- **Use WordPress APIs**: Don't reinvent WordPress functionality
- **Security First**: Sanitize inputs, escape outputs, verify nonces
- **Hook Integration**: Use WordPress actions and filters appropriately

### PHP Code Quality
```php
// Always sanitize and escape
$title = sanitize_text_field( $_POST['title'] );
echo '<h1>' . esc_html( $title ) . '</h1>';

// Always verify nonces
if ( ! wp_verify_nonce( $_POST['nonce'], 'partyminder_action' ) ) {
    wp_die( 'Security check failed' );
}

// Use proper capability checks
if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( 'Insufficient permissions' );
}
```

### CSS Requirements
- **All class names must begin with `.pm-`**
- **Use semantic names**: `.pm-card`, `.pm-form-row`, `.pm-section-header`
- **Single stylesheet**: Keep everything in `assets/css/partyminder.css`
- **No inline styles**: All styling in CSS file
- **Responsive design**: Mobile-first approach
- **Exception**: `.partyminder-content` is required to override WordPress theme defaults

### Professional Tone Requirements
- **NO EMOJIS**: Never use emojis in code, templates, or user-facing text
- **Professional language**: Business-appropriate copy and messaging
- **Clean interfaces**: Remove any decorative symbols or casual elements

### JavaScript Guidelines
- **Use WordPress standards**: Enqueue scripts properly with `wp_enqueue_script()`
- **jQuery compatibility**: Use `jQuery(document).ready()` pattern
- **AJAX integration**: Use WordPress AJAX API with proper nonces
- **Error handling**: Graceful fallbacks for JavaScript failures

---

## 5. Template Architecture

### Shared Layout System
Use these master templates to avoid code duplication:

#### `main` - General Content Pages
- **Used for**: Event listings, conversations, public pages
- **Structure**: Single column, full-width content
- **Variables**: `$page_title`, `$page_description`, `$main_content`

#### `two-column` - Dashboard and Settings
- **Used for**: User dashboards, settings pages, management interfaces
- **Structure**: Main content + sidebar
- **Variables**: `$main_content`, `$sidebar_content`, `$breadcrumbs`

#### `form` - Create/Edit Screens
- **Used for**: Event creation, RSVP forms, user registration
- **Structure**: Centered form with minimal distractions
- **Variables**: `$page_title`, `$form_content`, `$breadcrumbs`

### Template Best Practices
```php
// Set up template variables before including layout
$page_title = __( 'Create Event', 'partyminder' );
$breadcrumbs = array(
    array( 'title' => __( 'Dashboard', 'partyminder' ), 'url' => PartyMinder::get_dashboard_url() ),
    array( 'title' => __( 'Create Event', 'partyminder' ) )
);

// Capture main content
ob_start();
include PARTYMINDER_PLUGIN_DIR . 'templates/create-event-form.php';
$main_content = ob_get_clean();

// Use appropriate layout template
require PARTYMINDER_PLUGIN_DIR . 'templates/base/template-form.php';
```

---

## 6. Database Changes

### Schema Updates
If your changes involve database modifications:

1. **Update table creation methods** in `includes/class-activator.php`
2. **Add migration logic** in `run_database_migrations()` method
3. **Update documentation**:
   ```bash
   # Update DATABASE_STRUCTURE.md with current schema
   # Document new tables, columns, or indexes added
   ```
4. **Use `dbDelta()`** for safe table updates
5. **Bump plugin version** in main plugin header
6. **Test migration** on existing installations

### Database Best Practices
```php
// Always use prepared statements
$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}partyminder_events WHERE id = %d", $event_id );

// Use WordPress table prefix
$table_name = $wpdb->prefix . 'partyminder_events';

// Handle errors gracefully
$result = $wpdb->insert( $table_name, $data );
if ( $result === false ) {
    error_log( 'PartyMinder: Database insert failed - ' . $wpdb->last_error );
    return new WP_Error( 'database_error', __( 'Failed to save data', 'partyminder' ) );
}
```

---

## 7. Testing Requirements

### Before Submitting Any PR

#### Code Quality Checks
```bash
# Check for unprefixed CSS classes
grep -o '^\.[a-zA-Z0-9_-]\+' assets/css/partyminder.css | grep -v '^\.pm-' | sort | uniq

# Search for emojis in codebase (should return nothing)
grep -r "[\u{1F600}-\u{1F64F}]" templates/ includes/ || echo "No emojis found ✓"

# Check for debug code
grep -r "var_dump\|print_r\|error_log.*test" templates/ includes/
```

#### Functional Testing
- **Plugin activation/deactivation**: No errors in PHP logs
- **User flows**: Test as different user roles (admin, subscriber, logged out)
- **Theme compatibility**: Test with default WordPress themes
- **Responsive design**: Verify mobile and desktop layouts
- **Browser compatibility**: Test in Chrome, Firefox, Safari, Edge
- **JavaScript functionality**: Check browser console for errors

#### WordPress Integration
- **No WordPress conflicts**: Test with common plugins active
- **Performance**: No significant impact on page load times
- **Security**: All user inputs properly sanitized
- **Accessibility**: ARIA labels, keyboard navigation works

### Manual Testing Checklist
- [ ] Plugin activates without errors
- [ ] All pages load correctly
- [ ] Forms submit successfully
- [ ] AJAX functionality works
- [ ] User permissions respected
- [ ] Email notifications sent
- [ ] Database operations succeed
- [ ] No JavaScript errors in console
- [ ] Mobile responsive layouts work
- [ ] Works with different WordPress themes

---

## 8. WordPress-Specific Guidelines

### Security Requirements
```php
// Input validation and sanitization
$email = is_email( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
$title = sanitize_text_field( $_POST['title'] );
$content = wp_kses_post( $_POST['content'] );

// Output escaping
echo esc_html( $user_input );
echo esc_attr( $form_value );
echo esc_url( $link_url );

// Nonce verification for all forms
wp_nonce_field( 'partyminder_create_event', 'partyminder_nonce' );
if ( ! wp_verify_nonce( $_POST['partyminder_nonce'], 'partyminder_create_event' ) ) {
    wp_die( 'Security check failed' );
}
```

### Hook Usage Patterns
```php
// Actions - for performing operations
add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
add_action( 'init', array( $this, 'register_post_types' ) );

// Filters - for modifying data
add_filter( 'the_content', array( $this, 'inject_event_content' ) );
add_filter( 'wp_title', array( $this, 'modify_event_titles' ) );

// AJAX handlers
add_action( 'wp_ajax_partyminder_create_event', array( $this, 'handle_create_event' ) );
add_action( 'wp_ajax_nopriv_partyminder_rsvp', array( $this, 'handle_public_rsvp' ) );
```

### File Organization
- **Class files**: `includes/class-{name}.php`
- **Template files**: `templates/{page}-content.php`
- **Base templates**: `templates/base/template-{type}.php`
- **Partial templates**: `templates/partials/{component}.php`

---

## 9. Performance Guidelines

### Database Optimization
- **Minimize queries**: Use `get_results()` instead of multiple `get_row()` calls
- **Use indexes**: Ensure frequently queried columns have proper indexes
- **Cache expensive operations**: Use WordPress transients for heavy queries
- **Pagination**: Always paginate large result sets

### Frontend Performance
- **Minimize HTTP requests**: Combine CSS and JS where possible
- **Optimize images**: Proper sizing and compression
- **Conditional loading**: Only load scripts/styles where needed
- **Database caching**: Use WordPress object cache when available

---

## 10. Accessibility Requirements

### ARIA and Keyboard Navigation
```php
// Proper ARIA labels
<button aria-label="<?php esc_attr_e( 'Delete event', 'partyminder' ); ?>">
    <?php _e( 'Delete', 'partyminder' ); ?>
</button>

// Keyboard navigation
<div class="pm-tabs" role="tablist">
    <button role="tab" aria-selected="true" aria-controls="pm-events-panel">
        <?php _e( 'My Events', 'partyminder' ); ?>
    </button>
</div>
```

### Visual Accessibility
- **Color contrast**: Ensure sufficient contrast ratios
- **Focus indicators**: Clear focus states for interactive elements
- **Screen reader support**: Proper heading hierarchy and landmarks
- **Alternative text**: Descriptive alt text for images

---

## 11. Code Review Checklist

### For Reviewers
- [ ] **Security**: All inputs sanitized, outputs escaped, nonces verified
- [ ] **Performance**: No unnecessary database queries or expensive operations
- [ ] **WordPress standards**: Proper use of hooks, APIs, and coding standards
- [ ] **CSS compliance**: All classes prefixed with `.pm-`, no inline styles
- [ ] **Professional tone**: No emojis or casual language
- [ ] **Template usage**: Appropriate use of shared layout system
- [ ] **Documentation**: Code comments and documentation updated
- [ ] **Testing**: Evidence of thorough testing provided
- [ ] **Backward compatibility**: Changes don't break existing functionality

---

## 12. Version Control and Releases

### When to Bump Version Numbers
- **Major version** (1.0 → 2.0): Breaking changes, major feature additions
- **Minor version** (1.1 → 1.2): New features, significant improvements
- **Patch version** (1.1.1 → 1.1.2): Bug fixes, minor improvements

### Release Process
1. **Update version** in main plugin file header
2. **Update changelog** with new features and fixes
3. **Test thoroughly** on clean WordPress installation
4. **Tag release** in version control
5. **Update documentation** as needed

---

## 13. Issues and Questions

### GitHub Issues
Please open a GitHub issue if:
- **Bug reports**: Include steps to reproduce, expected vs actual behavior
- **Feature requests**: Describe use case and proposed solution
- **Documentation**: Questions about setup, usage, or contribution process
- **Security concerns**: Responsible disclosure of potential vulnerabilities

### Getting Help
- **Code questions**: Use GitHub discussions or issues
- **Development setup**: Check development setup section above
- **WordPress integration**: Refer to WordPress Codex and developer documentation

This plugin is maintained by the PartyMinder development team. Use GitHub for all collaboration and communication.

Thank you for contributing to PartyMinder!