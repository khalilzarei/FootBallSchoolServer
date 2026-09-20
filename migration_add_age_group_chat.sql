-- ═══════════════════════════════════════════════════════════════
-- مهاجرت: گفتگوی گروهی گروه‌های سنی + قفل/باز کردن توسط ادمین
-- این فایل را در phpMyAdmin هاست اجرا کنید (فقط یک بار)
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE football_chat_rooms
    ADD COLUMN age_group_id BIGINT UNSIGNED NULL AFTER class_id,
    ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD KEY idx_football_chat_rooms_age_group_id (age_group_id),
    ADD CONSTRAINT fk_football_chat_rooms_age_group
        FOREIGN KEY (age_group_id) REFERENCES football_age_groups(id)
        ON DELETE SET NULL ON UPDATE CASCADE;
