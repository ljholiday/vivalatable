-- VivalaTable Database Schema
-- Converted from PartyMinder WordPress plugin schema
-- Table prefix: vt_ (instead of wp_partyminder_)

-- Configuration table for application settings
CREATE TABLE IF NOT EXISTS vt_config (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    option_name varchar(191) NOT NULL,
    option_value longtext NOT NULL,
    autoload varchar(20) DEFAULT 'yes',
    PRIMARY KEY (id),
    UNIQUE KEY option_name (option_name),
    KEY autoload (autoload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Events table - pure custom table, no WordPress posts
CREATE TABLE IF NOT EXISTS vt_events (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description longtext,
    excerpt text,
    event_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    event_time varchar(20) DEFAULT '',
    guest_limit int(11) DEFAULT 0,
    venue_info text,
    host_email varchar(100) DEFAULT '',
    host_notes text,
    ai_plan longtext,
    event_status varchar(20) DEFAULT 'active',
    author_id bigint(20) UNSIGNED DEFAULT 1,
    community_id mediumint(9) DEFAULT NULL,
    featured_image varchar(255) DEFAULT '',
    meta_title varchar(255) DEFAULT '',
    meta_description text DEFAULT '',
    privacy varchar(20) DEFAULT 'public',
    created_by bigint(20) UNSIGNED NOT NULL DEFAULT 1,
    post_id bigint(20) UNSIGNED NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY event_date (event_date),
    KEY event_status (event_status),
    KEY author_id (author_id),
    KEY community_id (community_id),
    KEY privacy (privacy),
    KEY created_by (created_by),
    KEY post_id (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Guests table for RSVP management
CREATE TABLE IF NOT EXISTS vt_guests (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    rsvp_token varchar(255) DEFAULT '',
    temporary_guest_id varchar(32) DEFAULT '',
    converted_user_id bigint(20) UNSIGNED DEFAULT NULL,
    event_id mediumint(9) NOT NULL,
    name varchar(100) NOT NULL,
    email varchar(100) NOT NULL,
    phone varchar(20) DEFAULT '',
    status varchar(20) DEFAULT 'pending',
    invitation_source varchar(50) DEFAULT 'direct',
    dietary_restrictions text,
    plus_one tinyint(1) DEFAULT 0,
    plus_one_name varchar(100) DEFAULT '',
    notes text,
    rsvp_date datetime DEFAULT CURRENT_TIMESTAMP,
    reminder_sent tinyint(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY event_id (event_id),
    KEY email (email),
    KEY status (status),
    KEY rsvp_token (rsvp_token),
    KEY temporary_guest_id (temporary_guest_id),
    KEY converted_user_id (converted_user_id),
    KEY invitation_source (invitation_source),
    UNIQUE KEY unique_guest_event (event_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- AI interactions table for cost tracking
CREATE TABLE IF NOT EXISTS vt_ai_interactions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    event_id mediumint(9) DEFAULT NULL,
    interaction_type varchar(50) NOT NULL,
    prompt_text text,
    response_text longtext,
    tokens_used int(11) DEFAULT 0,
    cost_cents int(11) DEFAULT 0,
    provider varchar(20) DEFAULT 'openai',
    model varchar(50) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY event_id (event_id),
    KEY interaction_type (interaction_type),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Event invitations table
CREATE TABLE IF NOT EXISTS vt_event_invitations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    event_id mediumint(9) NOT NULL,
    invited_by_user_id bigint(20) UNSIGNED NOT NULL,
    invited_email varchar(100) NOT NULL,
    invited_user_id bigint(20) UNSIGNED DEFAULT NULL,
    invitation_token varchar(32) NOT NULL,
    message text,
    status varchar(20) DEFAULT 'pending',
    expires_at datetime DEFAULT NULL,
    responded_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_id (event_id),
    KEY invited_by_user_id (invited_by_user_id),
    KEY invited_email (invited_email),
    KEY invited_user_id (invited_user_id),
    KEY invitation_token (invitation_token),
    KEY status (status),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Conversation topics table
CREATE TABLE IF NOT EXISTS vt_conversation_topics (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    icon varchar(10) DEFAULT '',
    sort_order int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY sort_order (sort_order),
    KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Conversations table
CREATE TABLE IF NOT EXISTS vt_conversations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    topic_id mediumint(9) NOT NULL,
    event_id mediumint(9) DEFAULT NULL,
    community_id mediumint(9) DEFAULT NULL,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    content longtext NOT NULL,
    author_id bigint(20) UNSIGNED NOT NULL,
    author_name varchar(100) NOT NULL,
    author_email varchar(100) NOT NULL,
    privacy varchar(20) DEFAULT 'public',
    is_pinned tinyint(1) DEFAULT 0,
    is_locked tinyint(1) DEFAULT 0,
    reply_count int(11) DEFAULT 0,
    last_reply_date datetime DEFAULT CURRENT_TIMESTAMP,
    last_reply_author varchar(100) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY topic_id (topic_id),
    KEY event_id (event_id),
    KEY community_id (community_id),
    KEY author_id (author_id),
    KEY privacy (privacy),
    KEY is_pinned (is_pinned),
    KEY last_reply_date (last_reply_date),
    UNIQUE KEY slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Conversation replies table
CREATE TABLE IF NOT EXISTS vt_conversation_replies (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    conversation_id mediumint(9) NOT NULL,
    parent_reply_id mediumint(9) DEFAULT NULL,
    content longtext NOT NULL,
    author_id bigint(20) UNSIGNED NOT NULL,
    author_name varchar(100) NOT NULL,
    author_email varchar(100) NOT NULL,
    depth_level int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_id (conversation_id),
    KEY parent_reply_id (parent_reply_id),
    KEY author_id (author_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Conversation follows table
CREATE TABLE IF NOT EXISTS vt_conversation_follows (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    conversation_id mediumint(9) NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    email varchar(100) NOT NULL,
    last_read_at datetime DEFAULT CURRENT_TIMESTAMP,
    notification_frequency varchar(20) DEFAULT 'immediate',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_id (conversation_id),
    KEY user_id (user_id),
    KEY email (email),
    UNIQUE KEY unique_follow (conversation_id, user_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Communities table
CREATE TABLE IF NOT EXISTS vt_communities (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    type varchar(50) DEFAULT 'standard',
    privacy varchar(20) DEFAULT 'public',
    visibility varchar(20) DEFAULT 'public',
    personal_owner_user_id bigint(20) UNSIGNED DEFAULT NULL,
    member_count int(11) DEFAULT 0,
    event_count int(11) DEFAULT 0,
    creator_id bigint(20) UNSIGNED NOT NULL,
    creator_email varchar(100) NOT NULL,
    featured_image varchar(255) DEFAULT '',
    settings longtext DEFAULT '',
    at_protocol_did varchar(255) DEFAULT '',
    at_protocol_handle varchar(255) DEFAULT '',
    at_protocol_data longtext DEFAULT '',
    is_active tinyint(1) DEFAULT 1,
    requires_approval tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    UNIQUE KEY at_protocol_did (at_protocol_did),
    KEY creator_id (creator_id),
    KEY personal_owner_user_id (personal_owner_user_id),
    KEY privacy (privacy),
    KEY visibility (visibility),
    KEY type (type),
    KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Community members table
CREATE TABLE IF NOT EXISTS vt_community_members (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    community_id mediumint(9) NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    email varchar(100) NOT NULL,
    display_name varchar(100) NOT NULL,
    role varchar(50) DEFAULT 'member',
    permissions longtext DEFAULT '',
    status varchar(20) DEFAULT 'active',
    at_protocol_did varchar(255) DEFAULT '',
    joined_at datetime DEFAULT CURRENT_TIMESTAMP,
    last_seen_at datetime DEFAULT CURRENT_TIMESTAMP,
    invitation_data longtext DEFAULT '',
    PRIMARY KEY (id),
    KEY community_id (community_id),
    KEY user_id (user_id),
    KEY email (email),
    KEY role (role),
    KEY status (status),
    KEY at_protocol_did (at_protocol_did),
    UNIQUE KEY unique_member (community_id, user_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Community invitations table
CREATE TABLE IF NOT EXISTS vt_community_invitations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    community_id mediumint(9) NOT NULL,
    invited_by_user_id bigint(20) UNSIGNED NOT NULL,
    invited_email varchar(100) NOT NULL,
    invited_user_id bigint(20) UNSIGNED DEFAULT NULL,
    invitation_token varchar(255) NOT NULL,
    message text,
    role varchar(50) DEFAULT 'member',
    status varchar(20) DEFAULT 'pending',
    expires_at datetime DEFAULT NULL,
    responded_at datetime DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY community_id (community_id),
    KEY invited_by_user_id (invited_by_user_id),
    KEY invited_email (invited_email),
    KEY invited_user_id (invited_user_id),
    KEY invitation_token (invitation_token),
    KEY status (status),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- User profiles table
CREATE TABLE IF NOT EXISTS vt_user_profiles (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    display_name varchar(255) DEFAULT '',
    bio text DEFAULT '',
    location varchar(255) DEFAULT '',
    website varchar(255) DEFAULT '',
    avatar_url varchar(255) DEFAULT '',
    social_links longtext DEFAULT '',
    privacy_settings longtext DEFAULT '',
    events_hosted int(11) DEFAULT 0,
    events_attended int(11) DEFAULT 0,
    reputation_score int(11) DEFAULT 0,
    last_active_at datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_id (user_id),
    KEY reputation_score (reputation_score),
    KEY last_active_at (last_active_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Users table (WordPress replacement)
CREATE TABLE IF NOT EXISTS vt_users (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    username varchar(60) NOT NULL DEFAULT '',
    email varchar(100) NOT NULL DEFAULT '',
    display_name varchar(250) NOT NULL DEFAULT '',
    password varchar(255) NOT NULL DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    KEY status (status),
    KEY display_name (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;