<?php
/**
 * ██╗    ██╗██████╗       ██████╗ ██████╗ ███████╗ █████╗ ██╗  ██╗███████╗██████╗
 * ██║    ██║██╔══██╗      ██╔══██╗██╔══██╗██╔════╝██╔══██╗██║ ██╔╝██╔════╝██╔══██╗
 * ██║ █╗ ██║██████╔╝█████╗██████╔╝██████╔╝█████╗  ███████║█████╔╝ █████╗  ██████╔╝
 * ██║███╗██║██╔═══╝ ╚════╝██╔══██╗██╔══██╗██╔══╝  ██╔══██║██╔═██╗ ██╔══╝  ██╔══██╗
 * ╚███╔███╔╝██║           ██████╔╝██║  ██║███████╗██║  ██║██║  ██╗███████╗██║  ██║
 *  ╚══╝╚══╝ ╚═╝           ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 *
 * WordPress Training Site Breaker
 * For educational & developer training use ONLY.
 * Drop in public_html, open in browser, choose your poison.
 *
 * EVERYTHING THIS SCRIPT DOES IS FIXABLE (even "Unfixable").
 * No log file is written — hints are revealed one at a time on the results screen.
 */

define('WP_BREAKER_VERSION', '1.0.2');

// ═══════════════════════════════════════════════════════════════════════════════
// ─── CONFIGURATION ────────────────────────────────────────────────────────────
// ═══════════════════════════════════════════════════════════════════════════════

$config = [

    // tips: true  → show full fix instructions for every issue on the results screen
    //        false → trainee mode — one hint only, no spoilers
    'tips' => false,

];

// ═══════════════════════════════════════════════════════════════════════════════
// ─── POST action handler ─────────────────────────────────────────────────────
$message    = '';
$break_log  = [];   // kept in memory only — never written to disk
$action     = $_POST['action']     ?? '';
$difficulty = $_POST['difficulty'] ?? '';
$confirmed  = $_POST['confirmed']  ?? '';

if ($action === 'break' && in_array($difficulty, ['easy','medium','hard','ultimate','unfixable']) && $confirmed === 'yes') {
    $wp_load = __DIR__ . '/wp-load.php';
    if (!file_exists($wp_load)) {
        $message = 'error:WordPress not found in this directory. Place this file in your public_html (WordPress root).';
    } else {
        define('ABSPATH_CHECK', true);
        ob_start();
        require_once $wp_load;
        ob_end_clean();

        switch ($difficulty) {
            case 'easy':
                $break_log = break_easy();
                break;
            case 'medium':
                $break_log = array_merge(break_easy(), break_medium());
                break;
            case 'hard':
                $break_log = array_merge(break_easy(), break_medium(), break_hard());
                break;
            case 'ultimate':
                $break_log = array_merge(break_easy(), break_medium(), break_hard(), break_ultimate());
                break;
            case 'unfixable':
                $break_log = array_merge(break_easy(), break_medium(), break_hard(), break_ultimate(), break_unfixable());
                break;
        }

        // Store hints in session so the one-hint-per-run rule persists across hint requests
        session_start();
        $_SESSION['breaker_hints']     = array_column($break_log, 'hint', 'id');
        $_SESSION['breaker_fixes']     = array_column($break_log, 'fix',  'id');
        $_SESSION['breaker_used_hint'] = null;
        $_SESSION['breaker_diff']      = $difficulty;
        $_SESSION['breaker_tips']      = $config['tips'];

        $message = 'success:' . $difficulty;
    }
}

// ─── Hint AJAX handler ───────────────────────────────────────────────────────
if ($action === 'hint') {
    session_start();
    header('Content-Type: application/json');
    $req_id = $_POST['hint_id'] ?? '';

    if ($_SESSION['breaker_used_hint'] !== null) {
        echo json_encode(['ok' => false, 'msg' => 'You already used your one hint.']);
    } elseif (!isset($_SESSION['breaker_hints'][$req_id])) {
        echo json_encode(['ok' => false, 'msg' => 'Unknown issue ID.']);
    } else {
        $_SESSION['breaker_used_hint'] = $req_id;
        echo json_encode([
            'ok'   => true,
            'hint' => $_SESSION['breaker_hints'][$req_id],
            'fix'  => ($_SESSION['breaker_tips'] ?? false) ? ($_SESSION['breaker_fixes'][$req_id] ?? '') : null,
        ]);
    }
    exit;
}

// ─── BREAK FUNCTIONS ─────────────────────────────────────────────────────────
// Each returns an array of issue objects:
//   id, label, symptom (shown to trainee), hint (revealed on request), fix (instructor only, in session)

function break_easy(): array {
    $issues = [];

    // 1. Fake active plugin
    $active = get_option('active_plugins', []);
    $fake   = 'broken-plugin/broken-plugin.php';
    if (!in_array($fake, $active)) {
        $active[] = $fake;
        update_option('active_plugins', $active);
    }
    $issues[] = [
        'id'      => 'E1',
        'label'   => 'Something is preventing the site from loading normally.',
        'hint'    => 'WordPress tried to load something that does not exist. Check what is registered as active.',
        'fix'     => 'Remove broken-plugin/broken-plugin.php from active_plugins option. WP-CLI: wp plugin deactivate --all',
    ];

    // 2. Corrupt tagline
    $orig = get_option('blogdescription');
    update_option('_breaker_orig_tagline', $orig);
    update_option('blogdescription', $orig . '<!-- BROKEN_TAG -->');
    $issues[] = [
        'id'      => 'E2',
        'label'   => 'The site renders but something in the page source looks off.',
        'hint'    => 'Look at the HTML source carefully. Something has been appended where it should not be.',
        'fix'     => 'Fix blogdescription option — remove the <!-- BROKEN_TAG --> suffix. Settings > General > Tagline.',
    ];

    // 3. Maintenance mode
    file_put_contents(__DIR__ . '/.maintenance', '<?php $upgrading = ' . time() . ';');
    $issues[] = [
        'id'      => 'E3',
        'label'   => 'The site shows a maintenance message even though no update is running.',
        'hint'    => 'WordPress uses a specific file to trigger maintenance mode. It lives in the root directory.',
        'fix'     => 'Delete .maintenance from WordPress root.',
    ];

    // 4. Wrong siteurl port
    $orig_url = get_option('siteurl');
    update_option('_breaker_orig_siteurl', $orig_url);
    if (strpos($orig_url, ':9999') === false) {
        update_option('siteurl', rtrim($orig_url, '/') . ':9999');
    }
    $issues[] = [
        'id'      => 'E4',
        'label'   => 'All links redirect to a dead address. The admin panel may still be reachable via direct URL.',
        'hint'    => 'The site\'s registered URL does not match where it actually lives. Check the options table.',
        'fix'     => 'Restore siteurl in DB/wp-config. WP-CLI: wp option update siteurl https://yourdomain.com',
    ];

    return $issues;
}

function break_medium(): array {
    global $wpdb;
    $issues = [];

    // 1. Memory limit in wp-config
    $config_file = __DIR__ . '/wp-config.php';
    if (file_exists($config_file)) {
        $cfg = file_get_contents($config_file);
        if (strpos($cfg, 'BREAKER: memory too low') === false) {
            $cfg = str_replace("<?php", "<?php\ndefine('WP_MEMORY_LIMIT', '1M'); // BREAKER: memory too low\n", $cfg);
            file_put_contents($config_file, $cfg);
        }
    }
    $issues[] = [
        'id'      => 'M1',
        'label'   => 'Pages crash with a memory error. Deactivating plugins does not help.',
        'hint'    => 'The PHP memory ceiling has been set somewhere outside the database. Think configuration files.',
        'fix'     => 'Remove define(\'WP_MEMORY_LIMIT\', \'1M\') from wp-config.php.',
    ];

    // 2. Wipe active plugins
    $active = get_option('active_plugins', []);
    update_option('_breaker_orig_plugins', $active);
    update_option('active_plugins', ['broken-plugin/broken-plugin.php']);
    $issues[] = [
        'id'      => 'M2',
        'label'   => 'All plugins appear deactivated. Reactivating them from the admin does not persist.',
        'hint'    => 'The active plugin list in the database has been wiped. The original list was saved elsewhere in the options table.',
        'fix'     => 'Restore active_plugins from _breaker_orig_plugins option. WP-CLI: wp option get _breaker_orig_plugins --format=json',
    ];

    // 3. Broken theme
    $orig_t = get_option('template');
    $orig_s = get_option('stylesheet');
    update_option('_breaker_orig_template', $orig_t);
    update_option('_breaker_orig_stylesheet', $orig_s);
    update_option('template', 'nonexistent-theme-xyz');
    update_option('stylesheet', 'nonexistent-theme-xyz');
    $issues[] = [
        'id'      => 'M3',
        'label'   => 'The frontend shows a critical error. The theme switcher in the admin is unavailable.',
        'hint'    => 'WordPress is trying to load a theme that does not exist on disk. Check the template and stylesheet options.',
        'fix'     => 'wp option update template twentytwentyfour && wp option update stylesheet twentytwentyfour (or any installed theme slug).',
    ];

    // 4. Trash homepage
    $page_on_front = get_option('page_on_front');
    if ($page_on_front) {
        wp_update_post(['ID' => $page_on_front, 'post_status' => 'trash']);
    }
    $issues[] = [
        'id'      => 'M4',
        'label'   => 'The homepage returns a 404 or blank. Other pages load fine.',
        'hint'    => 'The page set as the homepage still exists — but its status has changed.',
        'fix'     => 'Restore the homepage post from Trash (Pages > Trash) or: wp post update ' . $page_on_front . ' --post_status=publish',
    ];

    // 5. Broken rewrite rules
    update_option('rewrite_rules', ['broken^rule$' => 'index.php?broken=1']);
    $issues[] = [
        'id'      => 'M5',
        'label'   => 'All post and page URLs return 404. The homepage loads. Admin works.',
        'hint'    => 'WordPress uses a set of URL routing rules stored in the database. They may have been replaced.',
        'fix'     => 'Flush rewrites: Settings > Permalinks > Save. Or: wp rewrite flush',
    ];

    return $issues;
}

function break_hard(): array {
    global $wpdb;
    $issues = [];

    // 1. Corrupt .htaccess
    $htaccess = __DIR__ . '/.htaccess';
    if (file_exists($htaccess)) {
        file_put_contents(__DIR__ . '/.htaccess.breaker-backup', file_get_contents($htaccess));
        file_put_contents($htaccess,
            "# BREAKER\nOptions -Indexes\nRewriteEngine On\nRewriteRule ^(.*)$ /dev/null [R=500,L]\n" .
            "php_value memory_limit 1M\nphp_flag display_errors off\n"
        );
    }
    $issues[] = [
        'id'      => 'H1',
        'label'   => 'Every request returns a 500 error. Server logs show Apache/Nginx rewrite issues.',
        'hint'    => 'Apache reads a plain-text configuration file in the web root on every request. That file may have been tampered with.',
        'fix'     => 'Restore .htaccess from .htaccess.breaker-backup, or paste the standard WP .htaccess block.',
    ];

    // 2. Wipe cron
    update_option('_breaker_orig_cron', get_option('cron'));
    update_option('cron', []);
    $issues[] = [
        'id'      => 'H2',
        'label'   => 'Scheduled emails stopped. Auto-updates are not running. No errors appear in the log.',
        'hint'    => 'WordPress manages its own task scheduler via the database. The schedule table may be empty.',
        'fix'     => 'wp cron event list (will be empty). Reactivate plugins to re-register events, or: wp cron event schedule wp_version_check now',
    ];

    // 3. Empty user roles
    $orig_roles = get_option($wpdb->prefix . 'user_roles');
    update_option('_breaker_orig_roles', $orig_roles);
    update_option($wpdb->prefix . 'user_roles', []);
    $issues[] = [
        'id'      => 'H3',
        'label'   => 'The admin panel loads but shows "Sorry, you are not allowed to access this page" for most screens.',
        'hint'    => 'WordPress capabilities are defined by a role map stored as an option. If that map is empty, nobody has permission to do anything.',
        'fix'     => 'Restore ' . $wpdb->prefix . 'user_roles option from _breaker_orig_roles. Or deactivate all plugins and re-save Settings > General.',
    ];

    // 4. Broken object-cache drop-in
    $object_cache = WP_CONTENT_DIR . '/object-cache.php';
    if (!file_exists($object_cache)) {
        file_put_contents($object_cache,
            "<?php\n// BREAKER DROP-IN\nthrow new Exception('Object cache failure — BREAKER training tool');\n"
        );
    }
    $issues[] = [
        'id'      => 'H4',
        'label'   => 'Fatal error on every page load. Stack trace mentions the cache layer.',
        'hint'    => 'WordPress supports a drop-in file that replaces the object cache. If that file is broken, nothing loads.',
        'fix'     => 'Delete wp-content/object-cache.php',
    ];

    // 5. Read-only uploads
    $uploads_dir = WP_CONTENT_DIR . '/uploads';
    if (is_dir($uploads_dir)) chmod($uploads_dir, 0444);
    $issues[] = [
        'id'      => 'H5',
        'label'   => 'Media uploads fail silently. Existing images still display. No PHP error shown.',
        'hint'    => 'The server cannot write to the folder where uploads are stored. This is a filesystem permissions issue.',
        'fix'     => 'chmod 755 wp-content/uploads (or 775 depending on server owner/group config).',
    ];

    return $issues;
}

function break_ultimate(): array {
    global $wpdb;
    $issues = [];

    // 1. broken advanced-cache.php
    file_put_contents(WP_CONTENT_DIR . '/advanced-cache.php',
        "<?php\nheader('HTTP/1.1 503 Service Unavailable');\ndie('Cache layer failure — BREAKER training');\n"
    );
    $issues[] = [
        'id'      => 'U1',
        'label'   => 'The entire site returns 503. WP-CLI commands still work.',
        'hint'    => 'A WordPress drop-in file is intercepting every request before WordPress even boots. Look in wp-content.',
        'fix'     => 'Delete wp-content/advanced-cache.php and remove define(\'WP_CACHE\',true) from wp-config.php if present.',
    ];

    // 2. Bad DB_HOST in wp-config
    $config_file = __DIR__ . '/wp-config.php';
    if (file_exists($config_file)) {
        $cfg = file_get_contents($config_file);
        if (strpos($cfg, 'BREAKER_DB_HOST') === false) {
            file_put_contents(__DIR__ . '/wp-config.php.breaker-backup', $cfg);
            $cfg = preg_replace(
                "/define\(\s*'DB_HOST'\s*,\s*'([^']*)'\s*\)/",
                "define('DB_HOST', '127.0.0.1:9999') /* BREAKER_DB_HOST — orig: '$1' */",
                $cfg
            );
            file_put_contents($config_file, $cfg);
        }
    }
    $issues[] = [
        'id'      => 'U2',
        'label'   => '"Error establishing a database connection" on every page.',
        'hint'    => 'WordPress cannot reach the database server. The connection details live in one specific file.',
        'fix'     => 'Edit wp-config.php — restore DB_HOST to localhost (or correct value). Backup at wp-config.php.breaker-backup.',
    ];

    // 3. Corrupt post meta
    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = 'BREAKER_CORRUPTED' WHERE meta_key IN ('_thumbnail_id','_wp_page_template') LIMIT 50");
    $issues[] = [
        'id'      => 'U3',
        'label'   => 'Featured images are missing on up to 50 posts. Some pages use the wrong template.',
        'hint'    => 'Post meta values for thumbnails and templates have been corrupted in the postmeta table.',
        'fix'     => 'Reassign featured images manually, or restore postmeta rows from a DB backup. wp post meta list --meta_key=_thumbnail_id',
    ];

    // 4. Rename uploads folder
    $uploads    = WP_CONTENT_DIR . '/uploads';
    $broken_up  = WP_CONTENT_DIR . '/uploads_BROKEN';
    if (is_dir($uploads) && !is_dir($broken_up)) rename($uploads, $broken_up);
    $issues[] = [
        'id'      => 'U4',
        'label'   => 'Every media file on the site returns 404. New uploads fail with a directory error.',
        'hint'    => 'The uploads directory does not exist at its expected path. It may have been renamed or moved.',
        'fix'     => 'Rename wp-content/uploads_BROKEN back to wp-content/uploads.',
    ];

    // 5. Demote all admins
    foreach (get_users(['role' => 'administrator']) as $admin) {
        update_user_meta($admin->ID, '_breaker_orig_role', 'administrator');
        (new WP_User($admin->ID))->set_role('subscriber');
    }
    $issues[] = [
        'id'      => 'U5',
        'label'   => 'All administrator accounts have been locked out of the admin panel.',
        'hint'    => 'User roles are stored in usermeta. Every admin account\'s role capability has been downgraded.',
        'fix'     => 'wp user set-role <ID> administrator  OR  update wp_usermeta: set meta_value = \'a:1:{s:13:"administrator";b:1;}\'',
    ];

    // 6. REST API killed via mu-plugin
    $mu = WP_CONTENT_DIR . '/mu-plugins';
    if (!is_dir($mu)) mkdir($mu, 0755, true);
    file_put_contents($mu . '/breaker-disable-rest.php',
        "<?php\n// BREAKER MU-PLUGIN\nadd_filter('rest_authentication_errors', function(\$r) {\n    return new WP_Error('disabled','REST API disabled by BREAKER', ['status'=>403]);\n});\n"
    );
    $issues[] = [
        'id'      => 'U6',
        'label'   => 'Gutenberg editor fails to load. REST API endpoints return 403. Mobile apps cannot connect.',
        'hint'    => 'A must-use plugin is blocking the REST API. MU plugins live in a special folder and cannot be deactivated from the admin.',
        'fix'     => 'Delete wp-content/mu-plugins/breaker-disable-rest.php',
    ];

    return $issues;
}

function break_unfixable(): array {
    global $wpdb;
    $issues = [];

    // 1. Rotate auth salts
    $config_file = __DIR__ . '/wp-config.php';
    if (file_exists($config_file)) {
        $cfg = file_get_contents($config_file);
        foreach (['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT'] as $salt) {
            $cfg = preg_replace(
                "/define\(\s*'{$salt}'\s*,\s*'[^']*'\s*\)/",
                "define('{$salt}', 'BREAKER_INVALIDATED_" . bin2hex(random_bytes(4)) . "')",
                $cfg
            );
        }
        file_put_contents($config_file, $cfg);
    }
    $issues[] = [
        'id'      => 'X1',
        'label'   => 'All logged-in users are instantly logged out. Logging back in immediately logs out again. Nonces fail across the site.',
        'hint'    => 'WordPress uses secret keys and salts to sign cookies and nonces. If these change, all existing sessions are invalidated.',
        'fix'     => 'Generate fresh salts at https://api.wordpress.org/secret-key/1.1/salt/ and replace all eight define lines in wp-config.php.',
    ];

    // 2. db.php drop-in — random query failures
    $db_dropin = WP_CONTENT_DIR . '/db.php';
    if (!file_exists($db_dropin)) {
        file_put_contents($db_dropin, <<<'PHP'
<?php
// BREAKER DB DROP-IN
class wpdb_breaker extends wpdb {
    public function query($query) {
        if (preg_match('/^(UPDATE|DELETE)/i', trim($query)) && mt_rand(1,10) <= 7) {
            $this->last_error = 'BREAKER: Query sabotaged.';
            return false;
        }
        return parent::query($query);
    }
}
$wpdb = new wpdb_breaker(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
PHP
        );
    }
    $issues[] = [
        'id'      => 'X2',
        'label'   => 'Saving posts, updating options, and other write actions fail unpredictably — roughly 70% of the time.',
        'hint'    => 'WordPress allows a drop-in file to replace the entire database layer. If one is present in wp-content, it intercepts every query.',
        'fix'     => 'Delete wp-content/db.php',
    ];

    // 3. Timebomb mu-plugin
    $mu = WP_CONTENT_DIR . '/mu-plugins';
    if (!is_dir($mu)) mkdir($mu, 0755, true);
    file_put_contents($mu . '/zzz-breaker-timebomb.php',
        "<?php\n// BREAKER TIMEBOMB\nadd_action('wp', function() {\n    if (is_singular() && get_the_ID() % 3 === 0) {\n        trigger_error('BREAKER timebomb triggered', E_USER_ERROR);\n    }\n});\n"
    );
    $issues[] = [
        'id'      => 'X3',
        'label'   => 'Some posts load fine. Others crash with a fatal error. The pattern seems random but is not.',
        'hint'    => 'A must-use plugin is triggering a fatal error based on a mathematical property of the post ID. Look at which posts fail.',
        'fix'     => 'Delete wp-content/mu-plugins/zzz-breaker-timebomb.php (fatals on singular posts whose ID is divisible by 3).',
    ];

    // 4. Mass autoload corruption
    $wpdb->query("UPDATE {$wpdb->options} SET autoload = 'yes' WHERE autoload = 'no' LIMIT 500");
    $issues[] = [
        'id'      => 'X4',
        'label'   => 'The site has slowed to a near halt. No errors shown. Server resources look normal.',
        'hint'    => 'WordPress loads certain options on every page request. If too many options are marked for autoloading, the initial DB query becomes enormous.',
        'fix'     => 'Run: SELECT COUNT(*) FROM wp_options WHERE autoload=\'yes\'; then reset non-essential options: UPDATE wp_options SET autoload=\'no\' WHERE option_name LIKE \'%transient%\'',
    ];

    // 5. Fake multisite via sunrise.php
    file_put_contents(WP_CONTENT_DIR . '/sunrise.php',
        "<?php\n// BREAKER SUNRISE\nif(!defined('MULTISITE')) define('MULTISITE', true);\nif(!defined('SUBDOMAIN_INSTALL')) define('SUBDOMAIN_INSTALL', true);\n"
    );
    $issues[] = [
        'id'      => 'X5',
        'label'   => 'WordPress behaves as if it is a multisite network. Routing is broken. Network admin screens appear.',
        'hint'    => 'A drop-in file in wp-content can define constants before WordPress fully loads. One of them can fake multisite mode.',
        'fix'     => 'Delete wp-content/sunrise.php and verify MULTISITE is not defined in wp-config.php.',
    ];

    // 6. Scramble all user passwords
    foreach ($wpdb->get_results("SELECT ID FROM {$wpdb->users} LIMIT 20") as $user) {
        $orig = $wpdb->get_var("SELECT user_pass FROM {$wpdb->users} WHERE ID={$user->ID}");
        update_user_meta($user->ID, '_breaker_orig_pass', $orig);
        $wpdb->update($wpdb->users, ['user_pass' => md5('BREAKER_LOCKED_' . $user->ID)], ['ID' => $user->ID]);
    }
    $issues[] = [
        'id'      => 'X6',
        'label'   => 'No user can log in. Password reset emails are sent but the links do not work either.',
        'hint'    => 'User passwords have been replaced in the users table, and salts may also be wrong — making password reset tokens invalid too.',
        'fix'     => 'wp user update <ID> --user_pass=\'newpassword\'  OR  SQL: UPDATE wp_users SET user_pass=MD5(\'newpass\') WHERE ID=1. Also fix salts (X1).',
    ];

    // 7. Intermittent sleep payload in wp-content/index.php
    $bad_index  = WP_CONTENT_DIR . '/index.php';
    $orig_index = file_exists($bad_index) ? file_get_contents($bad_index) : "<?php // Silence is golden.\n";
    file_put_contents(WP_CONTENT_DIR . '/index.php.breaker-backup', $orig_index);
    file_put_contents($bad_index, "<?php\n// BREAKER\n@require_once __DIR__ . '/breaker-payload.php';\n" . $orig_index);
    file_put_contents(WP_CONTENT_DIR . '/breaker-payload.php',
        "<?php\n// BREAKER PAYLOAD\nif (isset(\$_GET['breaker_unlock'])) {\n    header('Content-Type: text/plain');\n    echo \"BREAKER HINT: Check wp-content/index.php and delete breaker-payload.php\\n\";\n    exit;\n}\nif (mt_rand(1,10) <= 3) { sleep(2); }\n"
    );
    $issues[] = [
        'id'      => 'X7',
        'label'   => 'Page load times are erratic — fast sometimes, very slow others. No error. No pattern visible in logs.',
        'hint'    => 'Something is introducing a random delay early in the request lifecycle. Check files that WordPress always includes.',
        'fix'     => 'Delete wp-content/breaker-payload.php and restore wp-content/index.php from index.php.breaker-backup.',
    ];

    return $issues;
}

// ─── HTML OUTPUT ─────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WP BREAKER — WordPress Training Tool</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Exo+2:wght@300;600;900&display=swap');

  :root {
    --bg:       #0a0a0f;
    --surface:  #12121a;
    --border:   #1e1e2e;
    --red:      #ff2244;
    --orange:   #ff6622;
    --yellow:   #ffcc00;
    --green:    #00ff88;
    --cyan:     #00ccff;
    --purple:   #aa44ff;
    --text:     #ccd0e0;
    --muted:    #555577;
    --mono:     'Share Tech Mono', monospace;
    --sans:     'Exo 2', sans-serif;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--sans);
    min-height: 100vh;
    overflow-x: hidden;
  }

  body::before {
    content: '';
    position: fixed; inset: 0;
    background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,0.13) 2px, rgba(0,0,0,0.13) 4px);
    pointer-events: none;
    z-index: 9999;
  }

  header {
    background: linear-gradient(135deg, #0d0010 0%, #130005 50%, #000d1a 100%);
    border-bottom: 2px solid var(--red);
    padding: 2rem 2rem 1.5rem;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 50% 0%, rgba(255,34,68,0.15) 0%, transparent 70%);
    pointer-events: none;
  }

  .logo {
    font-family: var(--mono);
    font-size: clamp(1.2rem, 4vw, 2rem);
    color: var(--red);
    text-shadow: 0 0 20px rgba(255,34,68,0.8), 0 0 40px rgba(255,34,68,0.4);
    letter-spacing: 0.15em;
    line-height: 1.2;
  }
  .logo span { color: var(--text); }

  .tagline {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--muted);
    font-family: var(--mono);
  }

  .warning-banner {
    background: repeating-linear-gradient(-45deg, rgba(255,204,0,0.12), rgba(255,204,0,0.12) 10px, rgba(255,204,0,0.04) 10px, rgba(255,204,0,0.04) 20px);
    border-bottom: 2px solid var(--yellow);
    color: var(--yellow);
    font-family: var(--mono);
    font-size: 0.78rem;
    padding: 0.75rem 1.5rem;
    text-align: center;
    letter-spacing: 0.05em;
  }

  main { max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

  .intro {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 4px solid var(--cyan);
    border-radius: 4px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 2rem;
    font-size: 0.88rem;
    line-height: 1.75;
  }
  .intro strong { color: var(--cyan); }
  .intro code { font-family: var(--mono); color: var(--orange); font-size: 0.82em; }

  h2 {
    font-family: var(--mono);
    font-size: 0.7rem;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--border);
    padding-bottom: 0.5rem;
  }

  /* ─── Difficulty cards ─── */
  .diff-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .diff-card {
    position: relative;
    cursor: pointer;
    border-radius: 6px;
    padding: 1.25rem 1rem;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.2s;
    background: var(--surface);
  }
  .diff-card:hover { transform: translateY(-3px); }
  .diff-card input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
  .diff-inner { opacity: 0.55; transition: opacity 0.2s; }
  .diff-card:hover .diff-inner { opacity: 0.8; }
  .diff-card:has(input:checked) {
    border-color: var(--card-color, var(--muted));
    box-shadow: 0 0 20px -5px var(--card-color, var(--muted));
    background: color-mix(in srgb, var(--card-color, var(--muted)) 8%, var(--surface));
  }
  .diff-card:has(input:checked) .diff-inner { opacity: 1; }

  .diff-icon { font-size: 1.8rem; margin-bottom: 0.4rem; display: block; filter: drop-shadow(0 0 8px var(--card-color, white)); }
  .diff-name { font-family: var(--mono); font-size: 0.85rem; letter-spacing: 0.1em; color: var(--card-color, var(--text)); font-weight: bold; text-transform: uppercase; }
  .diff-desc { font-size: 0.68rem; color: var(--muted); margin-top: 0.35rem; line-height: 1.45; }

  .diff-card[data-diff="easy"]      { --card-color: var(--green); }
  .diff-card[data-diff="medium"]    { --card-color: var(--cyan); }
  .diff-card[data-diff="hard"]      { --card-color: var(--orange); }
  .diff-card[data-diff="ultimate"]  { --card-color: var(--red); }
  .diff-card[data-diff="unfixable"] { --card-color: var(--purple); }

  /* ─── Confirm ─── */
  .confirm-section {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 4px solid var(--red);
    border-radius: 4px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    display: none;
  }
  .confirm-section.visible { display: block; }
  .confirm-section p { font-size: 0.85rem; line-height: 1.65; margin-bottom: 0.75rem; }
  .confirm-section p strong { color: var(--red); }
  .confirm-check {
    display: flex; align-items: center; gap: 0.6rem;
    font-family: var(--mono); font-size: 0.8rem; color: var(--yellow);
    cursor: pointer; margin-bottom: 1rem;
  }
  .confirm-check input { width: 16px; height: 16px; accent-color: var(--red); }

  .btn-break {
    display: block; width: 100%; padding: 1rem;
    background: linear-gradient(135deg, #3a0010 0%, #1a0005 100%);
    border: 2px solid var(--red); border-radius: 4px;
    color: var(--red); font-family: var(--mono); font-size: 1rem;
    letter-spacing: 0.2em; text-transform: uppercase; cursor: pointer;
    transition: all 0.2s; opacity: 0.4; pointer-events: none;
  }
  .btn-break.ready { opacity: 1; pointer-events: all; animation: pulse-red 2s infinite; }
  .btn-break.ready:hover {
    background: linear-gradient(135deg, #5a0018 0%, #2a000a 100%);
    box-shadow: 0 0 30px rgba(255,34,68,0.4);
    transform: scale(1.01);
  }
  @keyframes pulse-red {
    0%,100% { box-shadow: 0 0 10px rgba(255,34,68,0.3); }
    50%      { box-shadow: 0 0 25px rgba(255,34,68,0.6); }
  }

  /* ─── Result panel ─── */
  .result-panel { margin-top: 2rem; border-radius: 6px; overflow: hidden; }
  .result-panel.success  { border: 2px solid var(--red); }
  .result-panel.err      { border: 2px solid var(--yellow); }
  .result-header { padding: 1rem 1.5rem; font-family: var(--mono); font-size: 0.85rem; letter-spacing: 0.15em; text-transform: uppercase; }
  .result-panel.success .result-header { background: rgba(255,34,68,0.12); color: var(--red); }
  .result-panel.err     .result-header { background: rgba(255,204,0,0.08); color: var(--yellow); }
  .result-body { background: #080810; padding: 1.5rem; font-family: var(--mono); font-size: 0.75rem; line-height: 1.8; color: #8888aa; }

  /* ─── Issue cards ─── */
  .issue-list { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1.5rem; }

  .issue-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 5px;
    overflow: hidden;
  }

  .issue-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0.85rem 1.1rem; gap: 1rem;
  }

  .issue-id {
    font-family: var(--mono);
    font-size: 0.65rem;
    letter-spacing: 0.15em;
    color: var(--muted);
    flex-shrink: 0;
    background: rgba(255,255,255,0.04);
    padding: 0.2rem 0.45rem;
    border-radius: 3px;
  }

  .issue-label {
    font-size: 0.82rem;
    line-height: 1.5;
    flex: 1;
    color: var(--text);
  }

  .btn-hint {
    flex-shrink: 0;
    padding: 0.3rem 0.75rem;
    background: transparent;
    border: 1px solid var(--muted);
    border-radius: 3px;
    color: var(--muted);
    font-family: var(--mono);
    font-size: 0.68rem;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
  }
  .btn-hint:hover:not(:disabled) { border-color: var(--yellow); color: var(--yellow); }
  .btn-hint:disabled { opacity: 0.3; cursor: not-allowed; }
  .btn-hint.used { border-color: var(--yellow); color: var(--yellow); opacity: 0.6; }
  .btn-hint.spent { opacity: 0.2; }

  .hint-reveal {
    display: none;
    padding: 0.75rem 1.1rem;
    border-top: 1px solid var(--border);
    background: rgba(255,204,0,0.04);
    font-family: var(--mono);
    font-size: 0.75rem;
    line-height: 1.65;
    color: var(--yellow);
  }
  .hint-reveal.visible { display: block; }
  .hint-reveal::before { content: '⚡ HINT  '; opacity: 0.5; }

  .fix-reveal {
    padding: 0.75rem 1.1rem;
    border-top: 1px solid var(--border);
    background: rgba(0,255,136,0.04);
    font-family: var(--mono);
    font-size: 0.75rem;
    line-height: 1.65;
    color: var(--green);
  }
  .fix-reveal::before { content: '🔧 FIX  '; opacity: 0.55; }

  .tips-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-family: var(--mono);
    font-size: 0.68rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--green);
    background: rgba(0,255,136,0.08);
    border: 1px solid rgba(0,255,136,0.25);
    border-radius: 3px;
    padding: 0.25rem 0.6rem;
    margin-left: 0.75rem;
    vertical-align: middle;
  }

  .hint-quota {
    font-family: var(--mono);
    font-size: 0.7rem;
    color: var(--muted);
    text-align: right;
    margin-bottom: 0.5rem;
    letter-spacing: 0.08em;
  }
  .hint-quota.spent { color: var(--red); opacity: 0.7; }

  /* ─── Next steps ─── */
  .next-steps {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: 4px solid var(--green);
    border-radius: 4px;
    padding: 1.25rem 1.5rem;
    margin-top: 1.5rem;
  }
  .next-steps h3 { font-family: var(--mono); font-size: 0.7rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--green); margin-bottom: 0.75rem; }
  .next-steps ul { list-style: none; padding: 0; font-size: 0.82rem; line-height: 1.9; }
  .next-steps ul li::before { content: '▸ '; color: var(--green); font-family: var(--mono); }

  footer { text-align: center; padding: 2rem; font-family: var(--mono); font-size: 0.65rem; color: var(--muted); letter-spacing: 0.1em; border-top: 1px solid var(--border); }
</style>
</head>
<body>

<header>
  <div class="logo">WP&nbsp;<span>◼</span>&nbsp;BREAKER</div>
  <div class="tagline">WordPress Developer Training Tool &nbsp;·&nbsp; v<?= WP_BREAKER_VERSION ?></div>
</header>

<div class="warning-banner">
  ⚠ &nbsp; THIS SCRIPT INTENTIONALLY BREAKS YOUR WORDPRESS SITE &nbsp;·&nbsp;
  STAGING / TRAINING ENVIRONMENTS ONLY &nbsp;·&nbsp;
  ALL DAMAGE IS FIXABLE &nbsp; ⚠
</div>

<main>

  <div class="intro">
    <strong>What is this?</strong> A controlled demolition tool for WordPress developer training.
    Choose a difficulty, confirm you know what you're doing, and the script breaks your WP installation
    in realistic ways — common and uncommon. No log file is written to disk.
    Trainees get symptom descriptions and <strong>one hint</strong> they can reveal.<br><br>
    <strong>Requirements:</strong> Place this file in your WordPress root (same folder as <code>wp-config.php</code>).
    Always use a staging environment with a fresh DB backup.
  </div>

  <?php
    $msg_parts = explode(':', $message, 2);
    $msg_type  = $msg_parts[0];
    $msg_val   = $msg_parts[1] ?? '';
  ?>

  <?php if ($msg_type === 'success'): ?>
  <?php $tips_on = $config['tips']; ?>

    <div class="result-panel success">
      <div class="result-header">
        💥 &nbsp; SITE BROKEN — <?= strtoupper(htmlspecialchars($msg_val)) ?> DIFFICULTY — <?= count($break_log) ?> ISSUES ACTIVE
        <?php if ($tips_on): ?>
          <span class="tips-badge">✓ TIPS ON</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!$tips_on): ?>
    <div class="hint-quota" id="hintQuota">
      HINT BUDGET: <span id="hintCount">1 remaining</span>
    </div>
    <?php endif; ?>

    <div class="issue-list" id="issueList">
      <?php foreach ($break_log as $issue): ?>
      <div class="issue-card" id="card-<?= htmlspecialchars($issue['id']) ?>">
        <div class="issue-header">
          <span class="issue-id"><?= htmlspecialchars($issue['id']) ?></span>
          <span class="issue-label"><?= htmlspecialchars($issue['label']) ?></span>
          <?php if (!$tips_on): ?>
          <button
            class="btn-hint"
            data-id="<?= htmlspecialchars($issue['id']) ?>"
            onclick="useHint(this)"
          >💡 Hint</button>
          <?php endif; ?>
        </div>
        <?php if ($tips_on): ?>
          <div class="hint-reveal visible"><?= htmlspecialchars($issue['hint']) ?></div>
          <div class="fix-reveal"><?= htmlspecialchars($issue['fix']) ?></div>
        <?php else: ?>
          <div class="hint-reveal" id="hint-<?= htmlspecialchars($issue['id']) ?>"></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="next-steps">
      <h3>// Recovery Challenge</h3>
      <ul>
        <li>Visit the broken site — observe and document the symptoms you see</li>
        <li>Diagnose each issue using WP-CLI, phpMyAdmin, FTP, or your hosting panel</li>
        <?php if ($tips_on): ?>
        <li>Tips mode is <strong>ON</strong> — hints and fixes are visible for all issues</li>
        <?php else: ?>
        <li>You have <strong>one hint</strong> — use it wisely</li>
        <?php endif; ?>
        <li>Verify every page of the site is restored before calling time</li>
      </ul>
    </div>

  <?php elseif ($msg_type === 'error'): ?>

    <div class="result-panel err">
      <div class="result-header">⚠ &nbsp; Error</div>
      <div class="result-body"><?= htmlspecialchars($msg_val) ?></div>
    </div>

  <?php else: ?>

  <form method="POST">
    <input type="hidden" name="action" value="break">

    <h2>// 01 — Choose Difficulty</h2>

    <div class="diff-grid">

      <label class="diff-card" data-diff="easy">
        <input type="radio" name="difficulty" value="easy">
        <div class="diff-inner">
          <span class="diff-icon">🟢</span>
          <div class="diff-name">Easy</div>
          <div class="diff-desc">Something is clearly wrong. The symptoms are loud. A fresh pair of eyes should crack it.</div>
        </div>
      </label>

      <label class="diff-card" data-diff="medium">
        <input type="radio" name="difficulty" value="medium">
        <div class="diff-inner">
          <span class="diff-icon">🔵</span>
          <div class="diff-name">Medium</div>
          <div class="diff-desc">The site misbehaves in multiple ways. Some issues hide behind others.</div>
        </div>
      </label>

      <label class="diff-card" data-diff="hard">
        <input type="radio" name="difficulty" value="hard">
        <div class="diff-inner">
          <span class="diff-icon">🟠</span>
          <div class="diff-name">Hard</div>
          <div class="diff-desc">Errors point in the wrong direction. Fixing one thing reveals another.</div>
        </div>
      </label>

      <label class="diff-card" data-diff="ultimate">
        <input type="radio" name="difficulty" value="ultimate">
        <div class="diff-inner">
          <span class="diff-icon">🔴</span>
          <div class="diff-name">Ultimate</div>
          <div class="diff-desc">Multiple layers of damage. Admin access is compromised. Recovery requires real skill.</div>
        </div>
      </label>

      <label class="diff-card" data-diff="unfixable">
        <input type="radio" name="difficulty" value="unfixable">
        <div class="diff-inner">
          <span class="diff-icon">☠️</span>
          <div class="diff-name">Unfixable</div>
          <div class="diff-desc">It is fixable. You will question that. Good luck.</div>
        </div>
      </label>

    </div>

    <h2>// 02 — Confirm & Launch</h2>

    <div class="confirm-section" id="confirmSection">
      <p>You are about to <strong>deliberately break a WordPress site</strong>. This script modifies files,
      database records, and folder permissions. It is designed for <strong>training and staging environments only</strong>.</p>
      <p>Every change is <strong>reversible</strong> — but only by someone who can find and fix it.
      No log file will be written to disk. Hints are revealed on the results screen, one per run.</p>
      <label class="confirm-check">
        <input type="checkbox" id="confirmCheckbox" name="confirmed" value="yes">
        I understand this will break my WordPress site. I have a backup. This is a staging environment.
      </label>
    </div>

    <button type="submit" class="btn-break" id="submitBtn">
      ◼ &nbsp; INITIATE SITE DESTRUCTION &nbsp; ◼
    </button>

  </form>

  <?php endif; ?>

</main>

<footer>
  WP BREAKER <?= WP_BREAKER_VERSION ?> &nbsp;·&nbsp; For educational use only &nbsp;·&nbsp;
  Delete this file when training is complete
</footer>

<script>
  // ─── Selection form ───────────────────────────────────────────────────────
  const radios   = document.querySelectorAll('input[type=radio]');
  const confirmS = document.getElementById('confirmSection');
  const checkbox = document.getElementById('confirmCheckbox');
  const btn      = document.getElementById('submitBtn');

  function updateBtn() {
    const chosen    = [...radios].some(r => r.checked);
    const confirmed = checkbox && checkbox.checked;
    if (btn)      btn.classList.toggle('ready', chosen && confirmed);
    if (confirmS) confirmS.classList.toggle('visible', chosen);
  }
  radios.forEach(r => r.addEventListener('change', updateBtn));
  if (checkbox) checkbox.addEventListener('change', updateBtn);

  // ─── Hint system ─────────────────────────────────────────────────────────
  let hintUsed = false;

  function useHint(btn) {
    if (hintUsed) return;

    const id       = btn.dataset.id;
    const quota    = document.getElementById('hintQuota');
    const countEl  = document.getElementById('hintCount');
    const hintDiv  = document.getElementById('hint-' + id);

    btn.disabled = true;
    btn.textContent = '⏳';

    fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=hint&hint_id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        hintUsed = true;

        // Show hint text
        hintDiv.textContent = data.hint;
        hintDiv.classList.add('visible');
        btn.textContent = '💡 Used';
        btn.classList.add('used');

        // Show fix block if tips mode is on server-side
        if (data.fix) {
          const fixDiv = document.createElement('div');
          fixDiv.className = 'fix-reveal';
          fixDiv.textContent = data.fix;
          hintDiv.after(fixDiv);
        }

        // Disable all other hint buttons
        document.querySelectorAll('.btn-hint').forEach(b => {
          if (b !== btn) {
            b.disabled = true;
            b.classList.add('spent');
            b.textContent = '💡 Hint';
          }
        });

        // Update quota indicator
        countEl.textContent = '0 remaining';
        quota.classList.add('spent');
      } else {
        // Already used
        btn.textContent = '— spent';
        btn.classList.add('spent');
        hintUsed = true;
        document.querySelectorAll('.btn-hint').forEach(b => {
          b.disabled = true;
          b.classList.add('spent');
        });
        countEl.textContent = '0 remaining';
        quota.classList.add('spent');
      }
    })
    .catch(() => {
      btn.textContent = '💡 Hint';
      btn.disabled = false;
    });
  }
</script>

</body>
</html>
