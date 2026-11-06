<?php

declare(strict_types = 1);

namespace App\Models;

use PDO;

/**
 * User profile details and preferences model.
 */
final class UserDetails {

  // ///////////////////////////////////////////////////////////////////////////

  public function __construct(
    private readonly PDO $db
  ) {}

  // ///////////////////////////////////////////////////////////////////////////
  // Read
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Retrieve a user's extended details and preferences.
   *
   * @return array<string, mixed>|null
   */
  public function findByUserId(int $userId): ?array {
    $query = $this->db->prepare(
      'SELECT
         [USER_ID],
         [DISPLAY_NAME],
         [JOB_TITLE],
         [COMPANY],
         [BIO],
         [AVATAR_PATH],
         [LOCATION],
         [WEBSITE],
         [GITHUB_USERNAME],

         [TIMEZONE],
         [LANGUAGE],
         [DATE_FORMAT],
         [TIME_FORMAT],
         [WEEK_START],
         [SHOW_LOCAL_TIME],
         [THEME],
         [EMAIL_NOTIFICATIONS],

         [CREATED_AT],
         [UPDATED_AT]

       FROM [dbo].[USER_DETAILS]
       WHERE [USER_ID] = :user_id'
    );

    $query->execute([
      'user_id' => $userId
    ]);

    $details = $query->fetch(PDO::FETCH_ASSOC);

    return $details === false ? null : $details;
  }

  // ///////////////////////////////////////////////////////////////////////////
  // Create / Update
  // ///////////////////////////////////////////////////////////////////////////

  /**
   * Insert or update a user's public profile details.
   *
   * Called inside the User model's database transaction.
   * Input must already have been validated.
   *
   * @param array<string, string|null> $data
   */
  public function saveProfile(int $userId, array $data): void {
    $values = [
      'user_id' => $userId,
      'display_name' => $data['DISPLAY_NAME'],
      'job_title' => $data['JOB_TITLE'],
      'company' => $data['COMPANY'],
      'bio' => $data['BIO'],
      'location' => $data['LOCATION'],
      'website' => $data['WEBSITE'],
      'github_username' => $data['GITHUB_USERNAME']
    ];

    // Lock the key until the surrounding transaction completes.
    $query = $this->db->prepare(
      'SELECT [USER_ID]
       FROM [dbo].[USER_DETAILS] WITH (UPDLOCK, HOLDLOCK)
       WHERE [USER_ID] = :user_id'
    );

    $query->execute([
      'user_id' => $userId
    ]);

    $exists = $query->fetchColumn() !== false;
    $query->closeCursor();

    if ($exists) {
      $query = $this->db->prepare(
        'UPDATE [dbo].[USER_DETAILS]
         SET
           [DISPLAY_NAME] = :display_name,
           [JOB_TITLE] = :job_title,
           [COMPANY] = :company,
           [BIO] = :bio,
           [LOCATION] = :location,
           [WEBSITE] = :website,
           [GITHUB_USERNAME] = :github_username,
           [UPDATED_AT] = SYSUTCDATETIME()
         WHERE [USER_ID] = :user_id'
      );
    } else {
      $query = $this->db->prepare(
        'INSERT INTO [dbo].[USER_DETAILS] (
           [USER_ID],
           [DISPLAY_NAME],
           [JOB_TITLE],
           [COMPANY],
           [BIO],
           [LOCATION],
           [WEBSITE],
           [GITHUB_USERNAME]
         )
         VALUES (
           :user_id,
           :display_name,
           :job_title,
           :company,
           :bio,
           :location,
           :website,
           :github_username
         )'
      );
    }

    $query->execute($values);
  }

  // ///////////////////////////////////////////////////////////////////////////

}
