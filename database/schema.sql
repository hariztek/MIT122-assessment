-- Student SkillBridge — core database schema
-- MIT122 Assessment 2 — Harikrushna Patel (985703)
--
-- 6 core tables per the Assessment 1 proposal ("Database Choice"):
--   users, skills, user_skills, session_requests, sessions, reviews
-- Engine: InnoDB (foreign keys, transactions). Charset: utf8mb4.
--
-- Idempotent: safe to re-run against a fresh or existing database —
-- tables are dropped (child-to-parent order) and recreated every time.
-- This is a schema-only script; seed data (skill categories, admin
-- promotion) is handled separately, not baked in here.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS session_requests;
DROP TABLE IF EXISTS user_skills;
DROP TABLE IF EXISTS skills;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- users
-- Account, credentials, role, and profile fields (name/bio/campus
-- folded in per the proposal — no separate one-to-one profiles table).
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    bio             TEXT NULL,
    campus          VARCHAR(100) NULL,
    status          ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- skills
-- Canonical skill catalogue with category, shared across all students.
-- ---------------------------------------------------------------------
CREATE TABLE skills (
    skill_id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    category        ENUM(
                        'technology',
                        'creative',
                        'languages',
                        'career_study',
                        'practical'
                    ) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_skills_name (name),
    KEY idx_skills_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- user_skills
-- Many-to-many join between users and skills. One row per
-- offered-or-wanted skill entry. level/mode/availability/description
-- live here (per skill entry), not on users, per proposal §2.2.
-- ---------------------------------------------------------------------
CREATE TABLE user_skills (
    user_skill_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    skill_id        INT UNSIGNED NOT NULL,
    type            ENUM('offer', 'want') NOT NULL,
    level           ENUM('beginner', 'intermediate', 'advanced', 'expert')
                        NOT NULL DEFAULT 'beginner',
    mode            ENUM('online', 'in_person', 'both')
                        NOT NULL DEFAULT 'both',
    availability    VARCHAR(255) NULL,
    description     TEXT NULL,
    status          ENUM('active', 'hidden') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_skill_type (user_id, skill_id, type),
    KEY idx_user_skills_skill (skill_id),
    KEY idx_user_skills_type_skill (type, skill_id),
    CONSTRAINT fk_user_skills_user
        FOREIGN KEY (user_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_user_skills_skill
        FOREIGN KEY (skill_id) REFERENCES skills(skill_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- session_requests
-- A learner (sender) requests a session with a teacher (receiver) on a
-- specific skill. Controlled state machine:
--   pending -> accepted -> completed
--   pending -> declined
--   accepted -> cancelled
-- ---------------------------------------------------------------------
CREATE TABLE session_requests (
    request_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id       INT UNSIGNED NOT NULL,
    receiver_id     INT UNSIGNED NOT NULL,
    skill_id        INT UNSIGNED NOT NULL,
    goal            TEXT NOT NULL,
    proposed_time   DATETIME NOT NULL,
    status          ENUM('pending', 'accepted', 'declined', 'cancelled', 'completed')
                        NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_session_requests_sender (sender_id, status),
    KEY idx_session_requests_receiver (receiver_id, status),
    CONSTRAINT fk_session_requests_sender
        FOREIGN KEY (sender_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_session_requests_receiver
        FOREIGN KEY (receiver_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_session_requests_skill
        FOREIGN KEY (skill_id) REFERENCES skills(skill_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_session_requests_not_self
        CHECK (sender_id <> receiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- sessions
-- Created exactly once, when an accepted request is marked completed
-- (not at acceptance time — an accepted request that is later
-- cancelled never gets a sessions row).
-- ---------------------------------------------------------------------
CREATE TABLE sessions (
    session_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL,
    completed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sessions_request (request_id),
    CONSTRAINT fk_sessions_request
        FOREIGN KEY (request_id) REFERENCES session_requests(request_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- reviews
-- Tied to a completed session. Either participant may review the
-- other; at most one review per (session, reviewer) pair.
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    review_id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id      INT UNSIGNED NOT NULL,
    reviewer_id     INT UNSIGNED NOT NULL,
    reviewee_id     INT UNSIGNED NOT NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    comment         TEXT NULL,
    status          ENUM('visible', 'hidden') NOT NULL DEFAULT 'visible',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reviews_session_reviewer (session_id, reviewer_id),
    KEY idx_reviews_reviewee (reviewee_id),
    CONSTRAINT fk_reviews_session
        FOREIGN KEY (session_id) REFERENCES sessions(session_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewer
        FOREIGN KEY (reviewer_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_reviewee
        FOREIGN KEY (reviewee_id) REFERENCES users(user_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_reviews_not_self
        CHECK (reviewer_id <> reviewee_id),
    CONSTRAINT chk_reviews_rating_range
        CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
