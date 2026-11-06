
CREATE TABLE [dbo].[USER_DETAILS]
(
  [USER_ID] INT NOT NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Public Profile
  -- ///////////////////////////////////////////////////////////////////////////

  [DISPLAY_NAME] NVARCHAR(150) NULL,
  [JOB_TITLE] NVARCHAR(150) NULL,
  [COMPANY] NVARCHAR(150) NULL,
  [BIO] NVARCHAR(1000) NULL,
  [AVATAR_PATH] NVARCHAR(500) NULL,
  [LOCATION] NVARCHAR(150) NULL,
  [WEBSITE] NVARCHAR(500) NULL,
  [GITHUB_USERNAME] NVARCHAR(100) NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Regional Preferences
  -- ///////////////////////////////////////////////////////////////////////////

  [TIMEZONE] NVARCHAR(100) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_TIMEZONE] DEFAULT (N'Europe/London'),

  [LANGUAGE] VARCHAR(10) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_LANGUAGE] DEFAULT ('en-GB'),

  [DATE_FORMAT] VARCHAR(20) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_DATE_FORMAT] DEFAULT ('dd/MM/yyyy'),

  [TIME_FORMAT] VARCHAR(5) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_TIME_FORMAT] DEFAULT ('24'),

  [WEEK_START] TINYINT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_WEEK_START] DEFAULT (1),

  [SHOW_LOCAL_TIME] BIT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_SHOW_LOCAL_TIME] DEFAULT (0),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Appearance
  -- ///////////////////////////////////////////////////////////////////////////

  [THEME] VARCHAR(10) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_THEME] DEFAULT ('dark'),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Notifications
  -- ///////////////////////////////////////////////////////////////////////////

  [EMAIL_NOTIFICATIONS] BIT NOT NULL
    CONSTRAINT [DF_USER_DETAILS_EMAIL_NOTIFICATIONS] DEFAULT (1),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Timestamps
  -- ///////////////////////////////////////////////////////////////////////////

  [CREATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_CREATED_AT] DEFAULT (SYSUTCDATETIME()),

  [UPDATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_DETAILS_UPDATED_AT] DEFAULT (SYSUTCDATETIME()),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  CONSTRAINT [PK_USER_DETAILS]
    PRIMARY KEY CLUSTERED ([USER_ID]),

  CONSTRAINT [FK_USER_DETAILS_USERS]
    FOREIGN KEY ([USER_ID])
    REFERENCES [dbo].[USERS] ([ID])
    ON DELETE CASCADE,

  CONSTRAINT [CK_USER_DETAILS_THEME]
    CHECK ([THEME] IN ('light', 'dark', 'system')),

  CONSTRAINT [CK_USER_DETAILS_TIME_FORMAT]
    CHECK ([TIME_FORMAT] IN ('12', '24')),

  CONSTRAINT [CK_USER_DETAILS_WEEK_START]
    CHECK ([WEEK_START] IN (0, 1))
);
