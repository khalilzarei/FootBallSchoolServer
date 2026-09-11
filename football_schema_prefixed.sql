-- =====================================================================
-- سامانه مدرسه فوتبال — اسکیمای نهایی (شامل پشتیبانی از آواتار)
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS football_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(150) NOT NULL,
    mobile VARCHAR(20) NULL,
    national_code CHAR(10) NULL,
    avatar_path VARCHAR(500) NULL, -- ← ستون جدید برای آواتار
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    deleted_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_users_mobile (mobile, deleted_seq),
    UNIQUE KEY uq_football_users_national_code (national_code, deleted_seq),
    KEY idx_football_users_role_status (role, status),
    KEY idx_football_users_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_user_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    device_name VARCHAR(150) NULL,
    platform VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_user_tokens_token_hash (token_hash),
    KEY idx_football_user_tokens_user_id (user_id),
    KEY idx_football_user_tokens_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_guardians (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    address VARCHAR(500) NULL,
    emergency_phone VARCHAR(20) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_guardians_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_coaches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    specialty VARCHAR(150) NULL,
    license_level VARCHAR(100) NULL,
    bio TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_coaches_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_seasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    age_cutoff_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'inactive',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_seasons_status (status),
    KEY idx_football_seasons_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_age_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    season_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    birth_date_from DATE NOT NULL,
    birth_date_to DATE NOT NULL,
    min_age_at_cutoff TINYINT UNSIGNED NULL,
    max_age_at_cutoff TINYINT UNSIGNED NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_age_groups_season_id (season_id),
    KEY idx_football_age_groups_status (status),
    KEY idx_football_age_groups_birth_dates (birth_date_from, birth_date_to),
    FOREIGN KEY (season_id) REFERENCES football_seasons(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    national_code CHAR(10) NULL,
    birth_date DATE NOT NULL,
    gender VARCHAR(10) NULL,
    avatar_path VARCHAR(500) NULL,
    medical_notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    deleted_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_players_national_code (national_code, deleted_seq),
    KEY idx_football_players_birth_date (birth_date),
    KEY idx_football_players_status (status),
    KEY idx_football_players_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_player_seasons (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NOT NULL,
    age_group_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    assigned_by BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_player_seasons_player_season (player_id, season_id),
    KEY idx_football_player_seasons_age_group_id (age_group_id),
    KEY idx_football_player_seasons_assigned_by (assigned_by),
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (season_id) REFERENCES football_seasons(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (age_group_id) REFERENCES football_age_groups(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_guardians_players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    guardian_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    relation VARCHAR(20) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    can_view_reports TINYINT(1) NOT NULL DEFAULT 1,
    can_pay TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_guardians_players_guardian_player (guardian_id, player_id),
    KEY idx_football_guardians_players_player_id (player_id),
    KEY idx_football_guardians_players_status (status),
    FOREIGN KEY (guardian_id) REFERENCES football_guardians(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_classes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    season_id BIGINT UNSIGNED NULL,
    age_group_id BIGINT UNSIGNED NULL,
    coach_id BIGINT UNSIGNED NULL,
    assistant_coach_id BIGINT UNSIGNED NULL,
    capacity INT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    location VARCHAR(255) NULL,
    description TEXT NULL,
    pricing_type VARCHAR(20) NOT NULL DEFAULT 'monthly',
    monthly_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
    session_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
    registration_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_football_classes_season_id (season_id),
    KEY idx_football_classes_age_group_id (age_group_id),
    KEY idx_football_classes_coach_id (coach_id),
    KEY idx_football_classes_assistant_coach_id (assistant_coach_id),
    KEY idx_football_classes_status (status),
    KEY idx_football_classes_created_by (created_by),
    FOREIGN KEY (season_id) REFERENCES football_seasons(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (age_group_id) REFERENCES football_age_groups(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES football_coaches(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (assistant_coach_id) REFERENCES football_coaches(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_class_schedules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_class_schedules_class_weekday_time (class_id, weekday, start_time),
    KEY idx_football_class_schedules_class_weekday (class_id, weekday),
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_enrollments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    enrolled_at DATE NOT NULL,
    ended_at DATE NULL,
    monthly_fee_override BIGINT UNSIGNED NULL,
    session_fee_override BIGINT UNSIGNED NULL,
    registration_fee_override BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_enrollments_class_player (class_id, player_id),
    KEY idx_football_enrollments_player_status (player_id, status),
    KEY idx_football_enrollments_class_status (class_id, status),
    KEY idx_football_enrollments_created_by (created_by),
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id BIGINT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
    topic VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_sessions_class_date_time (class_id, session_date, start_time),
    KEY idx_football_sessions_class_date_status (class_id, session_date, status),
    KEY idx_football_sessions_status (status),
    KEY idx_football_sessions_created_by (created_by),
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_attendances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL,
    is_billable TINYINT(1) NOT NULL DEFAULT 1,
    note TEXT NULL,
    recorded_by BIGINT UNSIGNED NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_attendances_session_player (session_id, player_id),
    KEY idx_football_attendances_player_id (player_id),
    KEY idx_football_attendances_recorded_by (recorded_by),
    KEY idx_football_attendances_status (status),
    FOREIGN KEY (session_id) REFERENCES football_sessions(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES football_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_evaluations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id BIGINT UNSIGNED NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    coach_id BIGINT UNSIGNED NOT NULL,
    evaluation_type VARCHAR(20) NOT NULL DEFAULT 'session',
    technical_score TINYINT UNSIGNED NULL,
    discipline_score TINYINT UNSIGNED NULL,
    physical_score TINYINT UNSIGNED NULL,
    teamwork_score TINYINT UNSIGNED NULL,
    overall_score TINYINT UNSIGNED NULL,
    strengths TEXT NULL,
    weaknesses TEXT NULL,
    notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_evaluations_session_player_type (session_id, player_id, evaluation_type),
    KEY idx_football_evaluations_player_id (player_id),
    KEY idx_football_evaluations_session_player (session_id, player_id),
    KEY idx_football_evaluations_coach_id (coach_id),
    FOREIGN KEY (session_id) REFERENCES football_sessions(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (coach_id) REFERENCES football_coaches(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uploader_id BIGINT UNSIGNED NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    original_name VARCHAR(255) NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500) NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    duration_seconds INT UNSIGNED NULL,
    visibility VARCHAR(20) NOT NULL DEFAULT 'private',
    related_type VARCHAR(30) NULL,
    related_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_media_uploader_id (uploader_id),
    KEY idx_football_media_related (related_type, related_id),
    KEY idx_football_media_visibility_status (visibility, status),
    FOREIGN KEY (uploader_id) REFERENCES football_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_media_audiences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    media_id BIGINT UNSIGNED NOT NULL,
    audience_type VARCHAR(20) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_media_audiences_media_id (media_id),
    KEY idx_football_media_audiences_audience (audience_type, target_id),
    FOREIGN KEY (media_id) REFERENCES football_media(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_invoices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number VARCHAR(30) NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    season_id BIGINT UNSIGNED NULL,
    invoice_type VARCHAR(20) NOT NULL,
    period_start_date DATE NULL,
    period_end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    due_date DATE NULL,
    subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
    discount_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    paid_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    remaining_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_invoices_invoice_number (invoice_number),
    KEY idx_football_invoices_player_status (player_id, status),
    KEY idx_football_invoices_due_status (due_date, status),
    KEY idx_football_invoices_season_id (season_id),
    KEY idx_football_invoices_created_by (created_by),
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (season_id) REFERENCES football_seasons(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_invoice_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    item_type VARCHAR(30) NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    total BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NULL,
    session_id BIGINT UNSIGNED NULL,
    attendance_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_invoice_items_invoice_id (invoice_id),
    KEY idx_football_invoice_items_class_id (class_id),
    KEY idx_football_invoice_items_session_id (session_id),
    KEY idx_football_invoice_items_attendance_id (attendance_id),
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (session_id) REFERENCES football_sessions(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES football_attendances(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_discounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(150) NOT NULL,
    discount_type VARCHAR(20) NOT NULL,
    `value` BIGINT UNSIGNED NOT NULL,
    applies_to VARCHAR(20) NOT NULL DEFAULT 'any',
    auto_apply TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATE NULL,
    end_date DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    description TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_discounts_status (status),
    KEY idx_football_discounts_dates (start_date, end_date),
    KEY idx_football_discounts_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_discount_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    discount_id BIGINT UNSIGNED NOT NULL,
    target_type VARCHAR(20) NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_discount_targets_discount_id (discount_id),
    KEY idx_football_discount_targets_target (target_type, target_id),
    FOREIGN KEY (discount_id) REFERENCES football_discounts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_invoice_discounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    discount_id BIGINT UNSIGNED NULL,
    title VARCHAR(150) NOT NULL,
    discount_type VARCHAR(20) NOT NULL,
    `value` BIGINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_invoice_discounts_invoice_id (invoice_id),
    KEY idx_football_invoice_discounts_discount_id (discount_id),
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (discount_id) REFERENCES football_discounts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_installments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    installment_number SMALLINT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    paid_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_installments_invoice_number (invoice_id, installment_number),
    KEY idx_football_installments_due_status (due_date, status),
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_number VARCHAR(30) NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,
    installment_id BIGINT UNSIGNED NULL,
    amount BIGINT UNSIGNED NOT NULL,
    allocated_total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    payment_method VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    receipt_media_id BIGINT UNSIGNED NULL,
    gateway_name VARCHAR(50) NULL,
    gateway_reference VARCHAR(100) NULL,
    gateway_status VARCHAR(30) NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_payments_payment_number (payment_number),
    KEY idx_football_payments_player_status (player_id, status),
    KEY idx_football_payments_invoice_id (invoice_id),
    KEY idx_football_payments_installment_id (installment_id),
    KEY idx_football_payments_receipt_media_id (receipt_media_id),
    KEY idx_football_payments_confirmed_by (confirmed_by),
    KEY idx_football_payments_created_by (created_by),
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (installment_id) REFERENCES football_installments(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (receipt_media_id) REFERENCES football_media(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_payment_allocations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    installment_id BIGINT UNSIGNED NULL,
    amount BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_payment_allocations_payment_id (payment_id),
    KEY idx_football_payment_allocations_invoice_id (invoice_id),
    KEY idx_football_payment_allocations_installment_id (installment_id),
    KEY idx_football_payment_allocations_created_by (created_by),
    FOREIGN KEY (payment_id) REFERENCES football_payments(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (installment_id) REFERENCES football_installments(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_credit_transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    player_id BIGINT UNSIGNED NOT NULL,
    amount BIGINT NOT NULL,
    transaction_type VARCHAR(20) NOT NULL,
    payment_id BIGINT UNSIGNED NULL,
    invoice_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_credit_transactions_player_id (player_id),
    KEY idx_football_credit_transactions_payment_id (payment_id),
    KEY idx_football_credit_transactions_invoice_id (invoice_id),
    KEY idx_football_credit_transactions_type (transaction_type),
    KEY idx_football_credit_transactions_created_by (created_by),
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES football_payments(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES football_invoices(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_news (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    publish_at DATETIME NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_football_news_status_publish (status, publish_at),
    KEY idx_football_news_created_by (created_by),
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_news_audiences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    news_id BIGINT UNSIGNED NOT NULL,
    audience_type VARCHAR(20) NOT NULL,
    role VARCHAR(20) NULL,
    target_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_news_audiences_news_id (news_id),
    KEY idx_football_news_audiences_audience (audience_type, target_id),
    KEY idx_football_news_audiences_role (role),
    FOREIGN KEY (news_id) REFERENCES football_news(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_chat_rooms (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    room_type VARCHAR(30) NOT NULL,
    player_id BIGINT UNSIGNED NULL,
    class_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255) NULL,
    unique_key CHAR(64) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_chat_rooms_unique_key (unique_key),
    KEY idx_football_chat_rooms_player_id (player_id),
    KEY idx_football_chat_rooms_class_id (class_id),
    KEY idx_football_chat_rooms_created_by (created_by),
    KEY idx_football_chat_rooms_type_status (room_type, status),
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_chat_room_members (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chat_room_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_read_message_id BIGINT UNSIGNED NULL,
    is_muted TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_chat_room_members_room_user (chat_room_id, user_id),
    KEY idx_football_chat_room_members_user_id (user_id),
    KEY idx_football_chat_room_members_last_read_message_id (last_read_message_id),
    FOREIGN KEY (chat_room_id) REFERENCES football_chat_rooms(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    chat_room_id BIGINT UNSIGNED NOT NULL,
    sender_id BIGINT UNSIGNED NOT NULL,
    message_type VARCHAR(20) NOT NULL DEFAULT 'text',
    body TEXT NULL,
    media_id BIGINT UNSIGNED NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at DATETIME NULL,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_chat_messages_room_sent (chat_room_id, sent_at),
    KEY idx_football_chat_messages_sender_id (sender_id),
    KEY idx_football_chat_messages_media_id (media_id),
    FOREIGN KEY (chat_room_id) REFERENCES football_chat_rooms(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES football_users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (media_id) REFERENCES football_media(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE football_chat_room_members
ADD FOREIGN KEY (last_read_message_id) REFERENCES football_chat_messages(id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS football_matches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    match_type VARCHAR(30) NOT NULL,
    class_id BIGINT UNSIGNED NULL,
    age_group_id BIGINT UNSIGNED NULL,
    opponent_team VARCHAR(255) NULL,
    match_date DATE NOT NULL,
    match_time TIME NOT NULL,
    location VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'planned',
    home_score SMALLINT UNSIGNED NULL,
    away_score SMALLINT UNSIGNED NULL,
    `result` VARCHAR(10) NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_matches_date_status (match_date, status),
    KEY idx_football_matches_class_id (class_id),
    KEY idx_football_matches_age_group_id (age_group_id),
    KEY idx_football_matches_created_by (created_by),
    FOREIGN KEY (class_id) REFERENCES football_classes(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (age_group_id) REFERENCES football_age_groups(id) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (created_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_match_players (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    match_id BIGINT UNSIGNED NOT NULL,
    player_id BIGINT UNSIGNED NOT NULL,
    invitation_status VARCHAR(20) NOT NULL DEFAULT 'invited',
    attendance_status VARCHAR(20) NULL,
    jersey_number SMALLINT UNSIGNED NULL,
    `position` VARCHAR(50) NULL,
    goals SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    assists SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    yellow_cards SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    red_cards SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    minutes_played SMALLINT UNSIGNED NULL,
    rating DECIMAL(3,1) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_match_players_match_player (match_id, player_id),
    KEY idx_football_match_players_player_id (player_id),
    FOREIGN KEY (match_id) REFERENCES football_matches(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (player_id) REFERENCES football_players(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    type VARCHAR(30) NOT NULL DEFAULT 'info',
    `data` JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_notifications_user_read (user_id, is_read),
    KEY idx_football_notifications_type (type),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_device_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    platform VARCHAR(20) NOT NULL,
    token VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_device_tokens_token (token),
    KEY idx_football_device_tokens_user_id (user_id),
    KEY idx_football_device_tokens_platform_active (platform, is_active),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier VARCHAR(30) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_id BIGINT UNSIGNED NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    failure_reason VARCHAR(40) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_login_attempts_identifier_time (identifier, created_at),
    KEY idx_football_login_attempts_ip_time (ip_address, created_at),
    KEY idx_football_login_attempts_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_sequences (
    name VARCHAR(30) NOT NULL,
    period VARCHAR(10) NOT NULL DEFAULT '',
    prefix VARCHAR(10) NOT NULL DEFAULT '',
    padding TINYINT UNSIGNED NOT NULL DEFAULT 6,
    current_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (name, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_football_audit_logs_entity (entity_type, entity_id),
    KEY idx_football_audit_logs_user_id (user_id),
    KEY idx_football_audit_logs_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS football_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    description TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_football_settings_setting_key (setting_key),
    KEY idx_football_settings_updated_by (updated_by),
    FOREIGN KEY (updated_by) REFERENCES football_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO football_settings (setting_key, setting_value, value_type, description) VALUES
('currency_label', 'تومان', 'string', 'واحد پول سیستم'),
('session_fee_policy', 'present_or_late', 'string', 'سیاست محاسبه شهریه جلسه‌ای'),
('monthly_invoice_day', '1', 'number', 'روز پیش‌فرض صدور فاکتور ماهانه'),
('chat_enabled', '1', 'boolean', 'فعال بودن چت'),
('chat_poll_interval_seconds', '10', 'number', 'فاصله پیشنهادی درخواست پیام‌های جدید در اپ'),
('match_stats_enabled', '1', 'boolean', 'فعال بودن آمار مسابقات'),
('media_max_upload_size_mb', '200', 'number', 'حداکثر حجم آپلود رسانه به مگابایت'),
('token_idle_lifetime_days', '30', 'number', 'مهلت بی‌استفاده ماندن توکن قبل از انقضا'),
('login_max_failed_attempts', '5', 'number', 'تعداد تلاش ناموفق قبل از قفل موقت حساب'),
('login_lockout_minutes', '15', 'number', 'مدت قفل موقت حساب به دقیقه'),
('login_attempt_window_minutes', '15', 'number', 'بازه شمارش تلاش‌های ناموفق به دقیقه'),
('password_min_length', '8', 'number', 'حداقل طول رمز عبور');

INSERT INTO football_sequences (name, period, prefix, padding, current_value) VALUES
('invoice', '', 'INV', 6, 0),
('payment', '', 'PAY', 6, 0);