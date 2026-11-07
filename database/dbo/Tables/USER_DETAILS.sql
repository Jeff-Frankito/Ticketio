-- ///////////////////////////////////////////////////////////////////////////
-- USER_DETAILS
-- ///////////////////////////////////////////////////////////////////////////
--
-- Stores extended user profile information and application preferences.
--
-- Each user has a maximum of one USER_DETAILS record.
-- The USER_ID column is both the primary key and a foreign key
-- referencing USERS.ID.
--
-- Deleting a user automatically deletes their details record.
--
-- ///////////////////////////////////////////////////////////////////////////

CREATE TABLE [dbo].[USER_DETAILS]
(
  -- Corresponding Ticketio user account.
  [USER_ID] INT NOT NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Public Profile
  -- ///////////////////////////////////////////////////////////////////////////

  -- Preferred name shown throughout Ticketio.
  -- Falls back to the user's first and last name when NULL.
  [DISPLAY_NAME] NVARCHAR(150) NULL,

  -- Optional professional information.
  [JOB_TITLE] NVARCHAR(150) NULL,
  [COMPANY] NVARCHAR(150) NULL,

  -- Optional public profile description.
  [BIO] NVARCHAR(1000) NULL,

  -- Relative path to the user's uploaded profile picture.
  -- The actual image is stored outside the database.
  [AVATAR_PATH] NVARCHAR(500) NULL,

  -- Optional location and personal website.
  [LOCATION] NVARCHAR(150) NULL,
  [WEBSITE] NVARCHAR(500) NULL,

  -- Optional manually entered GitHub username.
  -- This is separate from the verified account identity
  -- stored in USER_GITHUB_CONNECTIONS.
  [GITHUB_USERNAME] NVARCHAR(100) NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Regional Preferences
  -- ///////////////////////////////////////////////////////////////////////////

  -- User's preferred IANA timezone identifier.
  [TIMEZONE] NVARCHAR(100) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_TIMEZONE] DEFAULT (N'Europe/London'),

  -- Preferred language and locale.
  [LANGUAGE] VARCHAR(10) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_LANGUAGE] DEFAULT ('en-GB'),

  -- Preferred date display format.
  [DATE_FORMAT] VARCHAR(20) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_DATE_FORMAT] DEFAULT ('dd/MM/yyyy'),

  -- Preferred clock format: 12-hour or 24-hour.
  [TIME_FORMAT] VARCHAR(5) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_TIME_FORMAT] DEFAULT ('24'),

  -- First day of the week.
  -- 0 = Sunday, 1 = Monday.
  [WEEK_START] TINYINT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_WEEK_START] DEFAULT (1),

  -- Whether to display times using the user's local timezone.
  [SHOW_LOCAL_TIME] BIT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_SHOW_LOCAL_TIME] DEFAULT (0),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Appearance
  -- ///////////////////////////////////////////////////////////////////////////

  -- Preferred application colour theme.
  -- Supports light, dark and system-based appearance.
  [THEME] VARCHAR(10) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_THEME] DEFAULT ('dark'),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Notifications
  -- ///////////////////////////////////////////////////////////////////////////

  -- Whether the user receives email notifications from Ticketio.
  [EMAIL_NOTIFICATIONS] BIT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_EMAIL_NOTIFICATIONS] DEFAULT (1),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Timestamps
  -- ///////////////////////////////////////////////////////////////////////////

  -- UTC creation timestamp.
  [CREATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_CREATED_AT] DEFAULT (SYSUTCDATETIME()),

  -- UTC last modification timestamp.
  -- Must be explicitly updated by the application.
  [UPDATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_UPDATED_AT] DEFAULT (SYSUTCDATETIME()),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  -- Enforces one details record per user.
  CONSTRAINT [PK_USER_DETAILS]
    PRIMARY KEY CLUSTERED ([USER_ID]),

  -- Automatically removes details when the user is deleted.
  CONSTRAINT [FK_USER_DETAILS_USERS]
    FOREIGN KEY ([USER_ID])
    REFERENCES [dbo].[USERS] ([ID])
    ON DELETE CASCADE,

  -- Restrict theme values to supported options.
  CONSTRAINT [CK_USER_DETAILS_THEME]
    CHECK ([THEME] IN ('light', 'dark', 'system')),

  -- Restrict time format to 12-hour or 24-hour.
  CONSTRAINT [CK_USER_DETAILS_TIME_FORMAT]
    CHECK ([TIME_FORMAT] IN ('12', '24')),

  -- Restrict week start to Sunday or Monday.
  CONSTRAINT [CK_USER_DETAILS_WEEK_START]
    CHECK ([WEEK_START] IN (0, 1))
);