
# WP Breaker ☠️

> A controlled demolition tool for WordPress developer training.

**WP Breaker is a single PHP file that intentionally breaks a WordPress site in realistic, fixable ways — designed for trainers, educators, and developers who want to practice real-world WordPress recovery skills.**

----------

## ⚠️ Important

-   **Use on staging / training environments only**
-   Always take a full database + file backup before running
-   Every change the script makes is reversible
-   Delete the file from your server when training is complete
-   No log files are written to disk — all hints are session-based

----------

## What It Does

Drop the file into your WordPress root, open it in a browser, and choose a difficulty level. The script breaks your WordPress installation in ways that mirror real-world incidents — misconfigured files, database corruption, plugin conflicts, permission failures, and more.

Trainees see a list of **vague symptom descriptions** (no spoilers) and have access to **one hint** per run, revealed on demand. The trainer retains full knowledge of what was broken and how to fix it.

----------

## Difficulty Levels

Level

Shield

Experience

**Easy**

🟢

Something is clearly wrong. Symptoms are loud. A fresh pair of eyes should crack it.

**Medium**

🔵

The site misbehaves in multiple ways. Some issues hide behind others.

**Hard**

🟠

Errors point in the wrong direction. Fixing one thing reveals another.

**Ultimate**

🔴

Multiple layers of damage. Admin access is compromised. Requires real skill.

**Unfixable**

☠️

It is fixable. You will question that. Good luck.

Each difficulty is **cumulative** — Hard includes all Easy and Medium issues, etc.

----------

## Installation

1.  **Back up your database and files.** Seriously.
2.  Copy `wp-breaker.php` into your WordPress root — the same directory as `wp-config.php`.
3.  Open `https://your-staging-site.com/wp-breaker.php` in a browser.
4.  Choose a difficulty, check the confirmation box, and click **Initiate Site Destruction**.
5.  Hand the trainee the broken site URL. They get one hint.

----------

## The Hint System

After the site is broken, trainees see a list of **symptom descriptions** — what they would observe, not what caused it. Each issue has a 💡 **Hint** button.

-   **One hint is available per run**, enforced both client-side and server-side via PHP session
-   Once a hint is used, all other hint buttons are disabled
-   Hints describe _where to look_, not _what to do_ — they nudge, not solve

----------

## 🚨 SPOILER ALERT: What Gets Broken (by level)

<details> <summary><strong>🟢 Easy</strong> — 4 issues</summary>

-   A non-existent plugin injected into the active plugins list
-   Site tagline corrupted with HTML injection
-   WordPress maintenance mode triggered via `.maintenance` file
-   `siteurl` option set to a wrong port causing redirect loops

</details> <details> <summary><strong>🔵 Medium</strong> — +5 issues</summary>

-   `WP_MEMORY_LIMIT` set to 1M in `wp-config.php` causing memory exhaustion
-   All active plugins deactivated (original list saved in DB)
-   Active theme set to a non-existent slug
-   Homepage post sent to Trash
-   Rewrite rules replaced with broken rules (all pretty permalinks → 404)

</details> <details> <summary><strong>🟠 Hard</strong> — +5 issues</summary>

-   `.htaccess` replaced with rules that force 500 errors (backup saved)
-   WP-Cron scheduled events table wiped
-   User role definitions emptied from the database
-   Broken `object-cache.php` drop-in planted in `wp-content`
-   `wp-content/uploads` permissions set to read-only (444)

</details> <details> <summary><strong>🔴 Ultimate</strong> — +6 issues</summary>

-   Broken `advanced-cache.php` drop-in returning 503 on every request
-   `DB_HOST` in `wp-config.php` changed to an unreachable address (backup saved)
-   `_thumbnail_id` and `_wp_page_template` post meta corrupted for up to 50 posts
-   `wp-content/uploads/` folder renamed to `uploads_BROKEN/`
-   All administrator accounts demoted to Subscriber
-   Must-use plugin installed that kills the REST API entirely

</details> <details> <summary><strong>☠️ Unfixable</strong> — +7 issues</summary>

-   All WordPress security keys and salts rotated to garbage values (session/nonce invalidation)
-   `db.php` drop-in installed that randomly fails 70% of UPDATE/DELETE queries
-   Must-use timebomb plugin that fatals on singular posts with ID divisible by 3
-   500 non-autoloaded options flipped to autoload (massive DB overhead on every page load)
-   `sunrise.php` drop-in installed that fakes multisite constants
-   Passwords scrambled for up to 20 user accounts
-   Intermittent 2-second sleep injected into `wp-content/index.php` (30% of requests)

</details>

<details>  <summary>Recovery Tools</summary>

Trainees are expected to use standard WordPress recovery methods:

-   **WP-CLI** — the primary tool for most fixes
-   **phpMyAdmin / Adminer** — direct database access
-   **FTP / SFTP / File Manager** — for file-level issues
-   **Hosting control panel** — for permission and PHP config fixes
-   **wp-config.php** — direct editing for configuration-level breaks

</details>
----------



## Cleanup

When training is complete:

1.  Restore your database from backup (recommended — cleanest reset)
2.  Or manually revert the changes documented in the trainer's session
3.  **Delete `wp-breaker.php` from your server**

> The script stores recovery metadata (original option values, backups) prefixed with `_breaker_` in the WordPress options table, and saves `.breaker-backup` files alongside any modified files. These are safe to clean up after training.

----------

## Requirements

-   PHP 7.4+
-   WordPress 5.0+ installed in the same directory
-   Web server with write access to the WordPress root (Apache or Nginx)
-   PHP `session_start()` support (for hint system)

----------

## Who This Is For

-   **WordPress trainers** running hands-on recovery workshops
-   **Developers** stress-testing their own debugging skills
-   **Agencies** onboarding new developers and testing their problem-solving process
-   **Bootcamps and courses** covering WordPress administration and recovery

----------

## License

MIT — free to use, modify, and share. Attribution appreciated.

----------

> **Delete this file from your server when training is complete.**
