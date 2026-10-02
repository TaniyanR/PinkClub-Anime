SET @fixed_pages_exists := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'fixed_pages');
SET @sql := IF(@fixed_pages_exists > 0, 'UPDATE fixed_pages
SET body = REPLACE(
    body,
    ''・ [Privacy Policy(URL付き)]ページ'',
    ''・ [Privacy Policy(URL付き)]''
),
updated_at = NOW()
WHERE slug = ''about''
  AND body LIKE ''%・ [Privacy Policy(URL付き)]ページ%''', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
