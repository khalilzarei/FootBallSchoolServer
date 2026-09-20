-- ═══════════════════════════════════════════════════════════════
-- مایگریشن: حذف فصل از گروه‌های سنی
-- گروه سنی دیگر به فصل وصل نیست؛ عضویت فقط بر اساس بازه تاریخ تولد است.
-- ═══════════════════════════════════════════════════════════════
-- اجرا روی دیتابیس موجود (phpMyAdmin یا خط فرمان mysql):

-- ۱) نام کلید خارجی season_id را پیدا کنید (معمولاً football_age_groups_ibfk_1):
SELECT CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'football_age_groups'
  AND COLUMN_NAME = 'season_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ۲) با نام پیدا شده، کلید خارجی را حذف کنید
--    (اگر نام دیگری بود، جایگزین football_age_groups_ibfk_1 کنید):
ALTER TABLE football_age_groups DROP FOREIGN KEY football_age_groups_ibfk_1;

-- ۳) حذف ایندکس و ستون
ALTER TABLE football_age_groups DROP INDEX idx_football_age_groups_season_id;
ALTER TABLE football_age_groups DROP COLUMN season_id;

-- توجه: ستون season_id در football_classes (فصلِ خودِ کلاس) دست‌نخورده می‌ماند؛
-- این مایگریشن فقط جدول football_age_groups را تغییر می‌دهد.
