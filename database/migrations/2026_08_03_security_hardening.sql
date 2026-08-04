-- Couche anti-bruteforce pour les routes publiques et les connexions.
CREATE TABLE IF NOT EXISTS security_rate_limits (
    bucket_key CHAR(64) NOT NULL,
    scope VARCHAR(50) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (bucket_key),
    KEY ix_security_rate_limits_expiry (expires_at),
    KEY ix_security_rate_limits_scope (scope, expires_at)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

