# Authentication and connected accounts

Password login and GitHub login both use `AuthService::findByIdForSession()` to
load current display data, enforce active/verified accounts, and record login.
Password authentication passes the session version observed during password
verification so a concurrent password reset or session revocation rejects login.

`GitHubAuthController` owns public OAuth initiation and callbacks.
`Settings\GitHubController` owns connection settings, password-confirmed linking,
and password-confirmed disconnection. `GitHubAuthorization` shares initiation
and storage of single-use state, PKCE verifier, mode, and original session identity.

Matching email addresses never merge accounts. An existing Ticketio user must
sign in and explicitly link GitHub. An unknown GitHub identity needs a verified
primary email to create an account. Established identities do not need another
email lookup. The database enforces one GitHub identity per Ticketio user and
one Ticketio user per GitHub identity.

OAuth callbacks consume pending state before validation, expire it after ten
minutes, and reject replay. Invalid or expired state returns authenticated users
to connection settings and guests to login; this recovery does not authorize
linking. Linking additionally requires the initiating Ticketio user and session
version to remain valid. Tokens stay in request memory and are not persisted.
Only `GitHubAuthException` messages are suitable for public display.

`USER_DETAILS.GITHUB_USERNAME` is an editable public profile value, initially
seeded for new GitHub registrations. `USER_GITHUB_CONNECTIONS.GITHUB_USERNAME`
is OAuth-verified metadata, refreshed on successful GitHub login and displayed
in connection settings. Refreshing it never changes the editable profile value.

## Password setup

Settings → Password & security lets a signed-in user request a password link.
The recipient comes from their active, verified database account, never a form
field. CSRF and the shared password-recovery rate limits apply. Delivery uses
the existing account email service and local Mailpit in development.

The email proves account-email access before allowing password setup, including
for GitHub-first accounts with an unknown random password. The link uses the
existing reset form, password policy, expiring single-use token, and session
revocation transaction. Setting a password keeps GitHub connected and permits
password-confirmed disconnection. A logged-in session alone cannot replace a
password. Password confirmation remains required for linking and disconnecting;
a future provider-based reauthentication mechanism must replace that protection
before removing it.

## Development checks

Run against development SQL Server and Mailpit:

```powershell
docker compose exec -T php php tests/github-integration.php
docker compose exec -T php php tests/session-integration.php
docker compose exec -T php php tests/oauth-controller-integration.php
```

The controller suite mocks GitHub HTTP while exercising League/Guzzle, SQL,
controllers, and session behavior. It covers registration, existing login,
explicit linking, email and identity conflicts, state expiry/tampering/replay,
revoked sessions, logout during linking, and GitHub-first password setup followed
by disconnection. Test users and rate-limit buckets are removed afterward; one
test email is delivered to local Mailpit. A real browser OAuth round trip remains
a manual check after changing OAuth credentials or callback configuration.
