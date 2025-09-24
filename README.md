# VivalaTable

**Real-world social networking for event planning and community building**

VivalaTable is a LAMP stack web application that connects people through events and communities, with federated social integration via AT Protocol (Bluesky) and AI-powered event planning assistance.

## 🎯 Core Features

### Events & RSVPs
- **Guest-friendly RSVPs** - No account required for event responses
- **Smart invitations** - Email + Bluesky integration with token-based tracking
- **Dietary & accessibility** - Comprehensive guest preference management
- **AI event planning** - OpenAI-powered party planning suggestions with cost tracking

### Communities
- **Public & private communities** - Flexible privacy controls with approval workflows
- **Role-based management** - Admin, moderator, member hierarchy
- **AT Protocol federation** - Cross-platform community membership via Bluesky
- **Event organization** - Community-specific event planning and management

### Conversations
- **Threaded discussions** - Nested replies with depth tracking
- **Privacy inheritance** - Conversations inherit privacy from parent events/communities
- **Real-time engagement** - Subscription-based notification system
- **Full-text search** - Elasticsearch-ready search across all content

### AT Protocol Integration
- **Bluesky connectivity** - Profile sync, follower import, federated invitations
- **Decentralized identity** - DID management for cross-platform identity
- **Future-ready federation** - Built for expanding AT Protocol ecosystem

## 🏗️ Architecture

### Tech Stack
- **Frontend:** Pure HTML/CSS/JavaScript with custom `pm-*` CSS framework (1000+ classes)
- **Backend:** PHP 8.1+ with custom MVC-style architecture
- **Database:** MySQL 8.0+ with 17 custom tables optimized for social features
- **Email:** PHP `mail()` function for invitation delivery
- **AI:** OpenAI GPT-4 integration with usage tracking and cost management
- **Federation:** AT Protocol (Bluesky) via native PHP client

### Database Schema
```sql
-- Core Tables (17 total)
Events System:          communities_events, guests, event_invitations, event_rsvps
Community System:       communities, community_members, community_invitations
Conversation System:    conversations, conversation_replies, conversation_follows
AT Protocol:            member_identities, at_protocol_sync_log
Supporting:             user_profiles, post_images, ai_interactions, search
```

### Directory Structure
```
vivalatable.com/
├── config/             # Database, email, AI configuration
├── includes/           # Core functions and authentication
├── classes/            # Business logic managers
├── public/             # Public-facing pages and API endpoints
├── templates/          # HTML templates (base, events, communities, conversations)
├── assets/             # CSS, JavaScript, images
├── migrations/         # Database migrations and data import
└── docs/               # Technical documentation
```

## 🚀 Key Features Deep Dive

### Guest RSVP System
- **No registration required** - Guests can RSVP via email invitation links
- **32-character tokens** - WordPress-compatible secure invitation tokens
- **Guest conversion** - Automatic user account creation from RSVP data
- **Plus-one support** - Guest count management with dietary restrictions

### Community Management
- **Approval workflows** - Private community join requests with admin approval
- **Member limits** - Default 10 communities per user (configurable)
- **AT Protocol sync** - Bluesky follower → community member conversion
- **Privacy inheritance** - Community events inherit community privacy settings

### AI Assistant Integration
- **OpenAI GPT-4** - Party planning suggestions with structured JSON output
- **Cost tracking** - Monthly usage limits with per-user budgets
- **Demo mode** - Free tier with limited AI suggestions
- **Interaction logging** - Full conversation history for debugging and improvement

### AT Protocol (Bluesky) Features
- **Profile synchronization** - Two-way sync of profile data and avatars
- **Follower import** - Import Bluesky followers as potential community members
- **Federated invitations** - Send event invitations via Bluesky platform
- **DID management** - Decentralized identifier generation and storage

### Feature Flag System
- **8-step rollout** - Safe feature deployment with individual user flags
- **Permission gates** - Role-based feature access control
- **A/B testing ready** - Infrastructure for feature experimentation

## 🔧 Installation & Setup

### Requirements
- **PHP 8.1+** with extensions: mysqli, curl, json, openssl
- **MySQL 8.0+** with full-text search support
- **Apache 2.4+** with mod_rewrite enabled
- **SSL certificate** required for AT Protocol integration

### Quick Start
1. Clone repository to web root
2. Import database schema: `mysql < migrations/schema.sql`
3. Configure settings: Copy `config/database.example.php` → `config/database.php`
4. Set up email delivery (SMTP recommended for production)
5. Configure OpenAI API key for AI features
6. Enable Apache mod_rewrite for SEO URLs

### Environment Configuration
```php
// config/database.php
define('VT_DB_HOST', 'localhost');
define('VT_DB_NAME', 'vivalatable');
define('VT_DB_USER', 'your_user');
define('VT_DB_PASS', 'your_password');

// config/ai.php
define('VT_OPENAI_API_KEY', 'sk-...');
define('VT_AI_MONTHLY_LIMIT', 50.00); // USD

// config/email.php
define('VT_FROM_EMAIL', 'noreply@vivalatable.com');
define('VT_FROM_NAME', 'VivalaTable');
```

## 📊 Migration from PartyMinder

### Data Migration Process
1. **Export WordPress data** - Custom SQL scripts extract PartyMinder tables
2. **Schema conversion** - Migrate `wp_partyminder_*` → `vivalatable_*` tables
3. **User mapping** - WordPress users → VivalaTable user profiles
4. **Relationship preservation** - Maintain all community memberships, RSVPs, conversations
5. **AT Protocol data** - Preserve Bluesky connections and DID mappings

### Migration Command
```bash
php migrations/migrate_from_wordpress.php --source=partyminder_db --target=vivalatable_db --preserve-ids
```

## 🎨 CSS Framework

### Design System
- **1000+ utility classes** - Complete `pm-*` prefixed CSS framework
- **Mobile-first responsive** - Optimized for mobile event planning
- **Component-based** - Modular card, form, modal, navigation components
- **Trust-focused vocabulary** - "Confirm" not "approve", "Join" not "subscribe"

### Core Components
```css
/* Layout */
.pm-page-two-column    /* Main layout system */
.pm-card              /* Content cards */
.pm-modal             /* Dialog modals */

/* Forms */
.pm-form              /* Form containers */
.pm-btn               /* Button system */
.pm-form-input        /* Input fields */

/* Utilities */
.pm-p-4               /* Padding utilities */
.pm-text-center       /* Typography utilities */
.pm-mb-4              /* Margin utilities */
```

## 🔌 API Integration

### AT Protocol (Bluesky)
- **Authentication:** OAuth-style handle/password flow
- **Profile Sync:** Bidirectional profile data synchronization
- **Event Sharing:** Cross-platform event publication
- **Follower Import:** Community member recruitment via social graph

### OpenAI Integration
- **Model:** GPT-4 for event planning suggestions
- **Input:** Event type, guest count, dietary restrictions, budget
- **Output:** Structured JSON with menu suggestions, activities, timeline
- **Tracking:** Cost per request, monthly spending limits, usage analytics

### Future LLM Integration
- **Self-hosted readiness** - Abstracted AI interface for easy provider swapping
- **API compatibility** - OpenAI-compatible endpoint structure
- **Local processing** - Infrastructure ready for on-premise LLM deployment

## 🔐 Security & Privacy

### Authentication
- **Session-based auth** - Secure PHP session management
- **Password hashing** - PHP `password_hash()` with modern algorithms
- **Guest tokens** - Secure 32-character invitation tokens with expiration
- **CSRF protection** - Form token validation throughout application

### Data Privacy
- **Community privacy** - Public/private visibility with inheritance
- **Conversation privacy** - Thread-level privacy controls
- **Guest data handling** - Minimal data collection, GDPR-ready architecture
- **AT Protocol privacy** - User-controlled federation and data sharing

### Input Security
- **SQL injection prevention** - Prepared statements throughout codebase
- **XSS protection** - HTML escaping with allowlist for rich content
- **File upload validation** - MIME type checking for image uploads
- **Rate limiting ready** - Infrastructure for API rate limiting

## 📈 Scaling Considerations

### Database Optimization
- **Indexed relationships** - Optimized queries for community/event lookups
- **Full-text search** - Dedicated search table with composite indexes
- **Read replicas ready** - Architecture supports database scaling
- **Caching layer ready** - Redis integration planned for session and profile data

### Performance Architecture
- **Stateless design** - Horizontal scaling ready
- **Asset optimization** - CSS/JS minification and compression ready
- **CDN compatible** - Static asset delivery optimization ready
- **Database sharding ready** - User-based partitioning architecture planned

### Migration Path
- **Framework agnostic** - Business logic abstracted from framework specifics
- **API-first planning** - REST endpoints ready for mobile apps and microservices
- **Service extraction** - Core managers can be extracted to separate services
- **Container ready** - Docker deployment configuration planned

## 🧪 Development & Testing

### Local Development
```bash
# Set up local environment
git clone https://github.com/yourusername/vivalatable.git
cd vivalatable
php -S localhost:8000 -t public/
```

### Testing Strategy
- **Unit tests** - Core business logic testing with PHPUnit
- **Integration tests** - Database and API endpoint testing
- **User acceptance tests** - Critical user flows (RSVP, community join, event creation)
- **AT Protocol tests** - Federation functionality validation

### Code Quality
- **PSR-12 standards** - Modern PHP coding standards
- **Type declarations** - PHP 8.1+ type hints throughout
- **Error handling** - Comprehensive exception handling and logging
- **Documentation** - PHPDoc comments for all public methods

## 🚀 Production Deployment

### Server Requirements
- **PHP 8.1+ with OPcache** - Performance optimization
- **MySQL 8.0+ with InnoDB** - Full-text search and transaction support
- **Apache/Nginx with SSL** - HTTPS required for AT Protocol
- **Redis (optional)** - Session and cache storage
- **Cron jobs** - Scheduled tasks for email reminders and cleanup

### Monitoring & Analytics
- **Error logging** - Comprehensive application error tracking
- **AI cost tracking** - OpenAI usage monitoring and alerting
- **Performance monitoring** - Query analysis and optimization tracking
- **User analytics ready** - Privacy-focused usage analytics infrastructure

## 🤝 Contributing

### Development Workflow
1. Fork repository and create feature branch
2. Follow PSR-12 coding standards
3. Add unit tests for new features
4. Update documentation for API changes
5. Submit pull request with detailed description

### Architecture Guidelines
- **Single Responsibility** - Each class has one clear purpose
- **Dependency Injection** - Avoid tight coupling between components
- **Interface Segregation** - Small, focused interfaces
- **Database First** - Schema changes drive application changes

## 📄 License

Open Source - MIT License

## 🔮 Roadmap

### Phase 1: Core Migration (8-10 weeks)
- Complete PartyMinder feature parity
- Data migration tools and validation
- Production deployment and DNS cutover

### Phase 2: Enhancement (3-6 months)
- Self-hosted LLM integration
- Mobile-responsive optimization
- Advanced AT Protocol features (public posting)
- Performance optimization and caching

### Phase 3: Scale (6-12 months)
- Microservices architecture migration
- Mobile app API development
- Advanced analytics and insights
- Multi-language internationalization

---

**Built with ❤️ for real-world social connection**

*VivalaTable: Where digital planning meets real-world celebration*