-- ///////////////////////////////////////////////////////////////////////////
-- AUTH_RATE_LIMITS
-- ///////////////////////////////////////////////////////////////////////////
--
-- Stores authentication rate-limit counters.
--
-- Used by AuthRateLimiter to limit repeated requests across
-- PHP workers and browser sessions.
--
-- Examples:
--   Login attempts
--   Account registration attempts
--   Password reset requests
--   Email verification requests
--
-- Rate limiting uses fixed time windows.
--
-- Bucket identifiers are SHA-256 hashes generated from values
-- such as the action, IP address and email address.
--
-- Raw IP addresses and email addresses are not stored here.
--
-- ///////////////////////////////////////////////////////////////////////////

CREATE TABLE [dbo].[AUTH_RATE_LIMITS]
(
  -- Unique hashed identifier for the rate-limit bucket.
  [BUCKET_HASH] CHAR(64) NOT NULL,

  -- Number of recorded attempts during the current window.
  [ATTEMPTS] INT NOT NULL,

  -- UTC timestamp when the current rate-limit window expires.
  [EXPIRES_AT] DATETIME2(0) NOT NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  -- Ensures each rate-limit bucket has one counter.
  CONSTRAINT [PK_AUTH_RATE_LIMITS]
    PRIMARY KEY ([BUCKET_HASH])
);
GO

-- ///////////////////////////////////////////////////////////////////////////
-- Indexes
-- ///////////////////////////////////////////////////////////////////////////

-- Improves performance when locating and deleting expired
-- rate-limit records during routine cleanup.

CREATE INDEX [IX_AUTH_RATE_LIMITS_EXPIRES_AT]
ON [dbo].[AUTH_RATE_LIMITS] ([EXPIRES_AT]);