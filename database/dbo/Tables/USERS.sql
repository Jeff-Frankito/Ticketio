-- ///////////////////////////////////////////////////////////////////////////
-- USERS
-- ///////////////////////////////////////////////////////////////////////////
--
-- Stores the core Ticketio user account information.
--
-- This table is the primary identity record for every Ticketio user,
-- regardless of whether they authenticate using a password or GitHub.
--
-- Additional profile information and user preferences are stored in
-- USER_DETAILS.
--
-- ///////////////////////////////////////////////////////////////////////////

CREATE TABLE [dbo].[USERS]
(
  -- Unique internal Ticketio user identifier.
  [ID] INT IDENTITY(1, 1) NOT NULL,

  -- Account holder's first and last names.
  [FIRST_NAME] NVARCHAR(100) NOT NULL,
  [LAST_NAME] NVARCHAR(100) NOT NULL,

  -- Unique email address used for account identification.
  -- Case-insensitive collation prevents duplicate addresses
  -- differing only by letter case.
  [EMAIL] NVARCHAR(254) COLLATE Latin1_General_100_CI_AS NOT NULL,

  -- Secure password hash, never the original password.
  -- GitHub-created accounts may have an unusable random password hash.
  [PASSWORD_HASH] VARCHAR(255) NOT NULL,

  -- Controls whether the account is permitted to authenticate.
  [IS_ACTIVE] BIT NOT NULL
    CONSTRAINT [DF_USERS_IS_ACTIVE] DEFAULT (1),

  -- UTC account creation and last modification timestamps.
  -- UPDATED_AT must be maintained by application UPDATE statements.
  [CREATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USERS_CREATED_AT] DEFAULT (SYSUTCDATETIME()),

  [UPDATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USERS_UPDATED_AT] DEFAULT (SYSUTCDATETIME()),

  -- Most recent successful authentication.
  [LAST_LOGIN_AT] DATETIME2(7) NULL,

  -- NULL until the account's email address has been verified.
  [EMAIL_VERIFIED_AT] DATETIME2(0) NULL,

  -- Increment to invalidate existing authenticated sessions.
  -- SessionManager stores the value at login and AuthService
  -- checks that it still matches the database.
  [SESSION_VERSION] INT NOT NULL
    CONSTRAINT [DF_USERS_SESSION_VERSION] DEFAULT (1),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  CONSTRAINT [PK_USERS]
    PRIMARY KEY CLUSTERED ([ID]),

  CONSTRAINT [UQ_USERS_EMAIL]
    UNIQUE ([EMAIL])
);