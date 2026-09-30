-- Student SkillBridge — demo students + skill entries (local testing/demo only)
--
-- Gives search, matches and the dashboard realistic data to show.
-- Run AFTER schema.sql and seed_skills.sql. Safe to re-run: demo users are
-- keyed on their unique @example.test emails (INSERT IGNORE), and skill
-- entries on the (user_id, skill_id, type) unique key.
--
-- All demo accounts share one local test password: YGUpkCHBmRc
-- (never used anywhere real; change or delete these rows before any deploy).

-- Demo admin (SB-032/033) — same local test password as the students.
INSERT IGNORE INTO users (name, email, password_hash, role, campus, bio) VALUES
    ('Site Admin', 'admin@example.test', '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'admin', NULL, 'Moderation account for the demo.');

INSERT IGNORE INTO users (name, email, password_hash, campus, bio) VALUES
    ('Priya Shah',     'priya@example.test',  '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'City campus',   'Second-year IT student. Happy to help with Python and SQL.'),
    ('Marco Rossi',    'marco@example.test',  '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'City campus',   'Photography club member learning to code.'),
    ('Aiko Tanaka',    'aiko@example.test',   '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'North campus',  'Languages nerd. Teaching Mandarin basics.'),
    ('Sam Okafor',     'sam@example.test',    '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'North campus',  'Business student, loves public speaking and cooking.'),
    ('Lena Fischer',   'lena@example.test',   '$2y$12$cJc3Zc2MXsXMJwTD/leeFuyB7jDkXGumDaJByHKAsT89KhwOcJ5Xq', 'Online only',   'Design student, video editing on the side.');

-- Helper pattern: look up ids by email / skill name so this file does not
-- depend on auto-increment values.
INSERT IGNORE INTO user_skills (user_id, skill_id, type, level, mode, availability, description)
SELECT u.user_id, s.skill_id, x.type, x.level, x.mode, x.availability, x.description
FROM (
    SELECT 'priya@example.test' AS email, 'Python' AS skill, 'offer' AS type, 'advanced' AS level, 'online' AS mode, 'Weeknights after 6pm' AS availability, 'Intro to Python, loops, functions and small projects.' AS description
    UNION ALL SELECT 'priya@example.test', 'SQL',              'offer', 'intermediate', 'both',      'Weeknights after 6pm', 'Joins, GROUP BY and designing simple schemas.'
    UNION ALL SELECT 'priya@example.test', 'Spanish',          'want',  'beginner',     'online',    'Weekends',             'Want conversational basics before exchange.'
    UNION ALL SELECT 'marco@example.test', 'Photography',      'offer', 'advanced',     'in_person', 'Saturday mornings',    'Manual mode, composition and editing in Lightroom.'
    UNION ALL SELECT 'marco@example.test', 'Python',           'want',  'beginner',     'both',      'Weekday afternoons',   'Need Python basics for a data unit.'
    UNION ALL SELECT 'marco@example.test', 'Web development',  'want',  'beginner',     'online',    'Weekday afternoons',   'Want to build a portfolio site.'
    UNION ALL SELECT 'aiko@example.test',  'Mandarin',         'offer', 'expert',       'online',    'Weekends',             'Tones, pinyin and everyday phrases.'
    UNION ALL SELECT 'aiko@example.test',  'Excel',            'want',  'intermediate', 'online',    'Weekends',             'Pivot tables and charts for assignments.'
    UNION ALL SELECT 'sam@example.test',   'Public speaking',  'offer', 'advanced',     'in_person', 'Tuesdays and Thursdays','Presentation structure and nerves.'
    UNION ALL SELECT 'sam@example.test',   'Cooking',          'offer', 'intermediate', 'in_person', 'Sunday afternoons',    'Budget student meals.'
    UNION ALL SELECT 'sam@example.test',   'Interview prep',   'want',  'beginner',     'both',      'Tuesdays and Thursdays','Mock interviews for grad programs.'
    UNION ALL SELECT 'lena@example.test',  'Graphic design',   'offer', 'advanced',     'online',    'Flexible',             'Figma, typography and layout basics.'
    UNION ALL SELECT 'lena@example.test',  'Video editing',    'offer', 'intermediate', 'online',    'Flexible',             'Premiere Pro cuts, captions and colour.'
    UNION ALL SELECT 'lena@example.test',  'Python',           'want',  'beginner',     'online',    'Flexible',             'Automate boring design tasks.'
    -- Extra entries so matches show a ranked spread and a two-way swap.
    UNION ALL SELECT 'sam@example.test',   'Python',           'offer', 'beginner',     'in_person', 'Tuesdays and Thursdays 2pm','Just finished the intro unit, happy to revise together.'
    UNION ALL SELECT 'aiko@example.test',  'Python',           'offer', 'intermediate', 'in_person', 'Weekends',             'Data analysis with pandas.'
    UNION ALL SELECT 'lena@example.test',  'Web development',  'offer', 'advanced',     'online',    'Flexible',             'HTML, CSS and portfolio sites.'
    UNION ALL SELECT 'lena@example.test',  'Photography',      'want',  'beginner',     'online',    'Flexible',             'Better photos of my design work.'
    UNION ALL SELECT 'priya@example.test', 'Excel',            'offer', 'intermediate', 'online',    'Weeknights after 6pm', 'Formulas, lookups and pivot tables.'
) AS x
JOIN users  u ON u.email = x.email
JOIN skills s ON s.name  = x.skill;
