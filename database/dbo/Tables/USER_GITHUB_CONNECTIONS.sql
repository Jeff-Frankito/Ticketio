-- ///////////////////////////////////////////////////////////////////////////
-- USER_GITHUB_CONNECTIONS
-- ///////////////////////////////////////////////////////////////////////////
--
-- Stores verified GitHub account connections.
--
-- Links a permanent GitHub account ID to an existing Ticketio user.
-- Used for GitHub authentication and explicit account linking.
--
-- GitHub usernames can change, so authentication must always use
-- GITHUB_USER_ID rather than GITHUB_USERNAME.
--
-- OAuth access tokens and temporary authorization state are not
-- persisted in this table.
--
-- ///////////////////////////////////////////////////////////////////////////

CREATE TABLE [dbo].[USER_GITHUB_CONNECTIONS]
(
  -- Ticketio user who owns the GitHub connection.
  [USER_ID] INT NOT NULL,

  -- Permanent numeric GitHub account identifier.
  [GITHUB_USER_ID] BIGINT NOT NULL,

  -- Last known GitHub username.
  -- Updated when the account is reauthenticated.
  [GITHUB_USERNAME] NVARCHAR(100) NOT NULL,

  -- ///////////////////////////////////////////////////////////////////////////
  -- Timestamps
  -- ///////////////////////////////////////////////////////////////////////////

  -- UTC timestamp when the GitHub account was first connected.
  [CONNECTED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_GITHUB_CONNECTIONS_CONNECTED_AT]
      DEFAULT SYSUTCDATETIME(),

  -- UTC timestamp of the most recent connection update.
  -- Must be explicitly maintained by the application.
  [UPDATED_AT] DATETIME2(7) NOT NULL
    CONSTRAINT [DF_USER_GITHUB_CONNECTIONS_UPDATED_AT]
      DEFAULT SYSUTCDATETIME(),

  -- ///////////////////////////////////////////////////////////////////////////
  -- Constraints
  -- ///////////////////////////////////////////////////////////////////////////

  -- Each Ticketio user can have one GitHub connection.
  CONSTRAINT [PK_USER_GITHUB_CONNECTIONS]
    PRIMARY KEY CLUSTERED ([USER_ID]),

  -- Prevents one GitHub account from being linked to
  -- multiple Ticketio users.
  CONSTRAINT [UQ_USER_GITHUB_CONNECTIONS_GITHUB_USER_ID]
    UNIQUE ([GITHUB_USER_ID]),

  -- Removes the connection when the Ticketio user is deleted.
  CONSTRAINT [FK_USER_GITHUB_CONNECTIONS_USER]
    FOREIGN KEY ([USER_ID]) REFERENCES [dbo].[USERS] ([ID])
    ON DELETE CASCADE
);