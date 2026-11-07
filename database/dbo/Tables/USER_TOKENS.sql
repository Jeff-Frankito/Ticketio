-- ///////////////////////////////////////////////////////////////////////////
-- USER_TOKENS
-- ///////////////////////////////////////////////////////////////////////////
--
-- Stores temporary, single-use account security tokens.
--
-- Currently supports:
--   password_reset      - Resetting an account password.
--   email_verification  - Verifying an email address.
--
-- Only the SHA-256 hash of a token is stored in the database.
-- The original token is sent to the user and must never be
-- stored or logged in plaintext.
--
-- Tokens are validated against their expiration timestamp
-- and consumed atomically by AccountTokenService.
--
-- ///////////////////////////////////////////////////////////////////////////

CREATE TABLE [dbo].[USER_TOKENS]
(
  -- Unique identifier for the token record.
  [ID] BIGINT IDENTITY(1, 1) NOT NULL,

  -- User account associated with the token.
  [USER_ID] INT NOT NULL,

  -- SHA-256 token hash represented as 64 hexadecimal characters.
  [TOKEN_HASH] CHAR(64) NOT NULL,

  -- Identifies the security operation the token authorises.
  [TOKEN_TYPE] VARCHAR(32) NOT NULL,

  -- UTC timestamp after which the token becomes invalid.
  [EXPIRES_AT] DATETIME2(0) NOT NULL,

  -- UTC timestamp when the token was created.
  [CREATED_AT] DATETIME2(0) NOT NULL
    CONSTRAINT [DF_USER_TOKENS_CREATED_AT] DEFAULT SYSUTCDATETIME(),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  -- Primary key for token records.
  CONSTRAINT [PK_USER_TOKENS]
    PRIMARY KEY CLUSTERED ([ID]),

  -- Automatically removes tokens when their user is deleted.
  CONSTRAINT [FK_USER_TOKENS_USERS]
    FOREIGN KEY ([USER_ID])
    REFERENCES [dbo].[USERS]([ID])
    ON DELETE CASCADE,

  -- Prevents duplicate token hashes.
  CONSTRAINT [UQ_USER_TOKENS_HASH]
    UNIQUE ([TOKEN_HASH]),

  -- Restricts tokens to supported account security operations.
  CONSTRAINT [CK_USER_TOKENS_TYPE]
    CHECK ([TOKEN_TYPE] IN ('password_reset', 'email_verification'))
);
GO

-- ///////////////////////////////////////////////////////////////////////////
-- Indexes
-- ///////////////////////////////////////////////////////////////////////////

-- Supports locating and invalidating tokens belonging to a
-- particular user and security operation.

CREATE INDEX [IX_USER_TOKENS_USER_TYPE]
ON [dbo].[USER_TOKENS] ([USER_ID], [TOKEN_TYPE]);