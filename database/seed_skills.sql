-- Student SkillBridge — skill catalogue seed (SB-019)
-- Categories match skills.category ENUM. INSERT IGNORE so the script
-- is safe to re-run without duplicating names.

INSERT IGNORE INTO skills (name, category) VALUES
    ('Python',             'technology'),
    ('Java',               'technology'),
    ('Web development',    'technology'),
    ('SQL',                'technology'),
    ('Excel',              'technology'),
    ('Graphic design',     'creative'),
    ('Photography',        'creative'),
    ('Video editing',      'creative'),
    ('Music production',   'creative'),
    ('Spanish',            'languages'),
    ('Mandarin',           'languages'),
    ('Academic English',   'languages'),
    ('Auslan',             'languages'),
    ('Resume writing',     'career_study'),
    ('Interview prep',     'career_study'),
    ('Academic writing',   'career_study'),
    ('Public speaking',    'career_study'),
    ('Cooking',            'practical'),
    ('First aid',          'practical'),
    ('Personal finance',   'practical'),
    ('Bike repair',        'practical');
