-- VivalaTable Database Schema
-- Migrated from PartyMinder WordPress plugin
-- All tables use vt_ prefix instead of wp_partyminder_

-- Users and Profiles
CREATE TABLE vt_users (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    username varchar(60) NOT NULL,
    email varchar(100) NOT NULL,
    password_hash varchar(255) NOT NULL,
    display_name varchar(250) NOT NULL DEFAULT '',
    registered datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status varchar(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_user_profiles (
    user_id mediumint(9) NOT NULL,
    bio text,
    location varchar(255) DEFAULT '',
    website varchar(255) DEFAULT '',
    phone varchar(20) DEFAULT '',
    timezone varchar(50) DEFAULT 'America/New_York',
    avatar_url varchar(500) DEFAULT '',
    hosting_style varchar(50) DEFAULT 'casual',
    hosting_experience varchar(20) DEFAULT 'beginner',
    dietary_preferences text,
    accessibility_needs text,
    privacy_settings text,
    notification_settings text,
    social_links text,
    hosting_stats_events_created int DEFAULT 0,
    hosting_stats_events_hosted int DEFAULT 0,
    hosting_stats_avg_rating decimal(3,2) DEFAULT 0.00,
    hosting_stats_total_guests int DEFAULT 0,
    profile_completion int DEFAULT 0,
    last_login datetime DEFAULT NULL,
    last_profile_update datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    KEY hosting_experience (hosting_experience),
    KEY last_login (last_login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Events System
CREATE TABLE vt_events (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    event_date datetime NOT NULL,
    end_date datetime DEFAULT NULL,
    venue_info text,
    max_guests int DEFAULT NULL,
    cost_per_person decimal(8,2) DEFAULT NULL,
    rsvp_deadline datetime DEFAULT NULL,
    privacy varchar(20) NOT NULL DEFAULT 'public',
    requires_approval tinyint(1) NOT NULL DEFAULT 0,
    allow_plus_ones tinyint(1) NOT NULL DEFAULT 1,
    dietary_options text,
    accessibility_info text,
    parking_info text,
    host_id mediumint(9) NOT NULL,
    community_id mediumint(9) DEFAULT NULL,
    event_status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY host_id (host_id),
    KEY community_id (community_id),
    KEY event_date (event_date),
    KEY privacy (privacy),
    KEY event_status (event_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_guests (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    event_id mediumint(9) NOT NULL,
    invited_email varchar(100) NOT NULL,
    invited_by_user_id mediumint(9) DEFAULT NULL,
    invited_by_name varchar(100) DEFAULT '',
    rsvp_token varchar(32) NOT NULL,
    name varchar(100) DEFAULT '',
    plus_one tinyint(1) NOT NULL DEFAULT 0,
    plus_one_name varchar(100) DEFAULT '',
    dietary_restrictions text,
    message text,
    invitation_sent_at datetime DEFAULT NULL,
    rsvp_status varchar(20) DEFAULT 'pending',
    rsvp_date datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY rsvp_token (rsvp_token),
    KEY event_id (event_id),
    KEY invited_email (invited_email),
    KEY rsvp_status (rsvp_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_event_rsvps (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    event_id mediumint(9) NOT NULL,
    user_id mediumint(9) DEFAULT NULL,
    name varchar(100) NOT NULL,
    email varchar(100) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'attending',
    plus_one tinyint(1) NOT NULL DEFAULT 0,
    plus_one_name varchar(100) DEFAULT '',
    dietary_restrictions text,
    notes text,
    invitation_token varchar(32) DEFAULT NULL,
    rsvp_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY event_user_unique (event_id, email),
    KEY event_id (event_id),
    KEY user_id (user_id),
    KEY status (status),
    KEY invitation_token (invitation_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_event_invitations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    event_id mediumint(9) NOT NULL,
    invited_email varchar(100) NOT NULL,
    invited_by_user_id mediumint(9) NOT NULL,
    invitation_token varchar(32) NOT NULL,
    personal_message text,
    status varchar(20) NOT NULL DEFAULT 'pending',
    sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    opened_at datetime DEFAULT NULL,
    responded_at datetime DEFAULT NULL,
    expires_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY invitation_token (invitation_token),
    KEY event_id (event_id),
    KEY invited_email (invited_email),
    KEY status (status),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Communities System
CREATE TABLE vt_communities (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    featured_image varchar(500) DEFAULT '',
    community_type varchar(50) NOT NULL DEFAULT 'general',
    visibility varchar(20) NOT NULL DEFAULT 'public',
    member_limit int DEFAULT NULL,
    location varchar(255) DEFAULT '',
    created_by mediumint(9) NOT NULL,
    at_protocol_did varchar(255) DEFAULT '',
    at_protocol_handle varchar(255) DEFAULT '',
    at_protocol_pds_url varchar(500) DEFAULT '',
    profile_data text,
    settings text,
    stats_members int DEFAULT 0,
    stats_events int DEFAULT 0,
    stats_conversations int DEFAULT 0,
    is_featured tinyint(1) NOT NULL DEFAULT 0,
    status varchar(20) NOT NULL DEFAULT 'active',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY created_by (created_by),
    KEY visibility (visibility),
    KEY community_type (community_type),
    KEY status (status),
    KEY at_protocol_handle (at_protocol_handle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_community_members (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    community_id mediumint(9) NOT NULL,
    user_id mediumint(9) NOT NULL,
    role varchar(20) NOT NULL DEFAULT 'member',
    status varchar(20) NOT NULL DEFAULT 'active',
    joined_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    invited_by mediumint(9) DEFAULT NULL,
    invitation_accepted_at datetime DEFAULT NULL,
    last_activity_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY community_user_unique (community_id, user_id),
    KEY community_id (community_id),
    KEY user_id (user_id),
    KEY role (role),
    KEY status (status),
    KEY joined_at (joined_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_community_invitations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    community_id mediumint(9) NOT NULL,
    invited_email varchar(100) NOT NULL,
    invited_by_user_id mediumint(9) NOT NULL,
    invitation_token varchar(32) NOT NULL,
    personal_message text,
    status varchar(20) NOT NULL DEFAULT 'pending',
    sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at datetime DEFAULT NULL,
    expires_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY invitation_token (invitation_token),
    KEY community_id (community_id),
    KEY invited_email (invited_email),
    KEY status (status),
    KEY expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_community_events (
    community_id mediumint(9) NOT NULL,
    event_id mediumint(9) NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (community_id, event_id),
    KEY event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conversations System
CREATE TABLE vt_conversations (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    content text,
    event_id mediumint(9) DEFAULT NULL,
    community_id mediumint(9) DEFAULT NULL,
    created_by mediumint(9) NOT NULL,
    privacy varchar(20) NOT NULL DEFAULT 'public',
    is_pinned tinyint(1) NOT NULL DEFAULT 0,
    is_locked tinyint(1) NOT NULL DEFAULT 0,
    reply_count int DEFAULT 0,
    last_reply_at datetime DEFAULT NULL,
    last_reply_by mediumint(9) DEFAULT NULL,
    view_count int DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY event_id (event_id),
    KEY community_id (community_id),
    KEY created_by (created_by),
    KEY privacy (privacy),
    KEY last_reply_at (last_reply_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_conversation_replies (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    conversation_id mediumint(9) NOT NULL,
    parent_reply_id mediumint(9) DEFAULT NULL,
    user_id mediumint(9) NOT NULL,
    content text NOT NULL,
    reply_depth tinyint(3) NOT NULL DEFAULT 0,
    is_solution tinyint(1) NOT NULL DEFAULT 0,
    vote_count int DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY conversation_id (conversation_id),
    KEY parent_reply_id (parent_reply_id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_conversation_follows (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    conversation_id mediumint(9) NOT NULL,
    user_id mediumint(9) NOT NULL,
    notification_level varchar(20) NOT NULL DEFAULT 'all',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY conversation_user_unique (conversation_id, user_id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AT Protocol Integration
CREATE TABLE vt_member_identities (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id mediumint(9) DEFAULT NULL,
    email varchar(100) NOT NULL,
    display_name varchar(250) NOT NULL,
    at_protocol_did varchar(255) DEFAULT '',
    at_profile_handle varchar(255) DEFAULT '',
    access_jwt text,
    refresh_jwt text,
    pds_url varchar(500) DEFAULT '',
    profile_data text,
    last_sync_at datetime DEFAULT NULL,
    sync_status varchar(20) DEFAULT 'pending',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    UNIQUE KEY email (email),
    KEY at_protocol_did (at_protocol_did),
    KEY at_profile_handle (at_profile_handle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_at_protocol_sync_log (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id mediumint(9) DEFAULT NULL,
    action varchar(50) NOT NULL,
    entity_type varchar(50) NOT NULL,
    entity_id mediumint(9) DEFAULT NULL,
    at_protocol_did varchar(255) DEFAULT '',
    status varchar(20) NOT NULL DEFAULT 'pending',
    error_message text,
    request_data text,
    response_data text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY action (action),
    KEY entity_type (entity_type),
    KEY status (status),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Supporting Tables
CREATE TABLE vt_post_images (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id mediumint(9) DEFAULT NULL,
    filename varchar(255) NOT NULL,
    original_filename varchar(255) NOT NULL,
    file_path varchar(500) NOT NULL,
    file_size int NOT NULL,
    mime_type varchar(100) NOT NULL,
    width int DEFAULT NULL,
    height int DEFAULT NULL,
    alt_text varchar(500) DEFAULT '',
    entity_type varchar(50) DEFAULT '',
    entity_id mediumint(9) DEFAULT NULL,
    is_featured tinyint(1) NOT NULL DEFAULT 0,
    upload_ip varchar(45) DEFAULT '',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY entity_type (entity_type),
    KEY entity_id (entity_id),
    KEY filename (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_ai_interactions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id mediumint(9) DEFAULT NULL,
    session_id varchar(64) NOT NULL,
    interaction_type varchar(50) NOT NULL,
    prompt text NOT NULL,
    response text,
    model varchar(50) NOT NULL,
    tokens_used int DEFAULT 0,
    cost_usd decimal(8,4) DEFAULT 0.0000,
    processing_time_ms int DEFAULT 0,
    event_id mediumint(9) DEFAULT NULL,
    community_id mediumint(9) DEFAULT NULL,
    status varchar(20) NOT NULL DEFAULT 'completed',
    error_message text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY interaction_type (interaction_type),
    KEY created_at (created_at),
    KEY event_id (event_id),
    KEY community_id (community_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vt_search (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    entity_type varchar(50) NOT NULL,
    entity_id mediumint(9) NOT NULL,
    title varchar(255) NOT NULL,
    content text NOT NULL,
    tags varchar(500) DEFAULT '',
    privacy varchar(20) NOT NULL DEFAULT 'public',
    user_id mediumint(9) DEFAULT NULL,
    community_id mediumint(9) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY entity_unique (entity_type, entity_id),
    FULLTEXT KEY search_content (title, content, tags),
    KEY entity_type (entity_type),
    KEY privacy (privacy),
    KEY user_id (user_id),
    KEY community_id (community_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Foreign Key Constraints (for data integrity)
ALTER TABLE vt_user_profiles
    ADD CONSTRAINT fk_user_profiles_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_events
    ADD CONSTRAINT fk_events_host_id FOREIGN KEY (host_id) REFERENCES vt_users (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_events_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE SET NULL;

ALTER TABLE vt_guests
    ADD CONSTRAINT fk_guests_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE CASCADE;

ALTER TABLE vt_event_rsvps
    ADD CONSTRAINT fk_event_rsvps_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_event_rsvps_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_event_invitations
    ADD CONSTRAINT fk_event_invitations_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_event_invitations_invited_by_user_id FOREIGN KEY (invited_by_user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_communities
    ADD CONSTRAINT fk_communities_created_by FOREIGN KEY (created_by) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_community_members
    ADD CONSTRAINT fk_community_members_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_community_members_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_community_invitations
    ADD CONSTRAINT fk_community_invitations_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_community_invitations_invited_by_user_id FOREIGN KEY (invited_by_user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_community_events
    ADD CONSTRAINT fk_community_events_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_community_events_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE CASCADE;

ALTER TABLE vt_conversations
    ADD CONSTRAINT fk_conversations_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversations_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversations_created_by FOREIGN KEY (created_by) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_conversation_replies
    ADD CONSTRAINT fk_conversation_replies_conversation_id FOREIGN KEY (conversation_id) REFERENCES vt_conversations (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversation_replies_parent_reply_id FOREIGN KEY (parent_reply_id) REFERENCES vt_conversation_replies (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversation_replies_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_conversation_follows
    ADD CONSTRAINT fk_conversation_follows_conversation_id FOREIGN KEY (conversation_id) REFERENCES vt_conversations (id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_conversation_follows_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_member_identities
    ADD CONSTRAINT fk_member_identities_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_post_images
    ADD CONSTRAINT fk_post_images_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE CASCADE;

ALTER TABLE vt_ai_interactions
    ADD CONSTRAINT fk_ai_interactions_user_id FOREIGN KEY (user_id) REFERENCES vt_users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_ai_interactions_event_id FOREIGN KEY (event_id) REFERENCES vt_events (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_ai_interactions_community_id FOREIGN KEY (community_id) REFERENCES vt_communities (id) ON DELETE SET NULL;

-- Indexes for performance
CREATE INDEX idx_events_date_status ON vt_events (event_date, event_status);
CREATE INDEX idx_community_members_role_status ON vt_community_members (role, status);
CREATE INDEX idx_conversations_privacy_created ON vt_conversations (privacy, created_at);
CREATE INDEX idx_ai_interactions_cost_date ON vt_ai_interactions (cost_usd, created_at);
CREATE INDEX idx_search_privacy_type ON vt_search (privacy, entity_type);