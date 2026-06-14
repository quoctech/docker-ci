<?php

/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer's autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
defined('COMPOSER_PATH') || define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR')   || define('HOUR', 3600);
defined('DAY')    || define('DAY', 86400);
defined('WEEK')   || define('WEEK', 604800);
defined('MONTH')  || define('MONTH', 2_592_000);
defined('YEAR')   || define('YEAR', 31_536_000);
defined('DECADE') || define('DECADE', 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined('EXIT_SUCCESS')        || define('EXIT_SUCCESS', 0);        // no errors
defined('EXIT_ERROR')          || define('EXIT_ERROR', 1);          // generic error
defined('EXIT_CONFIG')         || define('EXIT_CONFIG', 3);         // configuration error
defined('EXIT_UNKNOWN_FILE')   || define('EXIT_UNKNOWN_FILE', 4);   // file not found
defined('EXIT_UNKNOWN_CLASS')  || define('EXIT_UNKNOWN_CLASS', 5);  // unknown class
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT')     || define('EXIT_USER_INPUT', 7);     // invalid user input
defined('EXIT_DATABASE')       || define('EXIT_DATABASE', 8);       // database error
defined('EXIT__AUTO_MIN')      || define('EXIT__AUTO_MIN', 9);      // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX')      || define('EXIT__AUTO_MAX', 125);    // highest automatically-assigned error code

/*
 |--------------------------------------------------------------------------
 | Application Constants
 |--------------------------------------------------------------------------
 |
 | Hằng số riêng của BladeEngine Platform.
 | Tập trung giá trị lặp lại để dễ thay đổi và tránh magic strings.
 |
 */

// --- Datetime Format ---
defined('APP_DATETIME_FORMAT') || define('APP_DATETIME_FORMAT', 'Y-m-d H:i:s');

// --- Password Hashing (Argon2id) ---
defined('APP_HASH_MEMORY_COST') || define('APP_HASH_MEMORY_COST', 65536); // 64MB
defined('APP_HASH_TIME_COST')   || define('APP_HASH_TIME_COST', 4);
defined('APP_HASH_THREADS')     || define('APP_HASH_THREADS', 3);

// --- User Roles (RBAC) ---
defined('ROLE_SUPER_ADMIN')    || define('ROLE_SUPER_ADMIN', 'super_admin');
defined('ROLE_WORKSPACE_ADMIN') || define('ROLE_WORKSPACE_ADMIN', 'workspace_admin');
defined('ROLE_USER')           || define('ROLE_USER', 'user');

// --- User Status ---
defined('STATUS_ACTIVE')  || define('STATUS_ACTIVE', 'active');
defined('STATUS_LOCKED')  || define('STATUS_LOCKED', 'locked');
defined('STATUS_PENDING') || define('STATUS_PENDING', 'pending');

// --- Security: Brute Force ---
defined('MAX_LOGIN_ATTEMPTS')    || define('MAX_LOGIN_ATTEMPTS', 5);
defined('LOCK_DURATION_MINUTES') || define('LOCK_DURATION_MINUTES', 30);
defined('LOGIN_RATE_LIMIT')      || define('LOGIN_RATE_LIMIT', 10);       // requests per window
defined('LOGIN_RATE_WINDOW')     || define('LOGIN_RATE_WINDOW', 60);      // seconds
defined('REGISTER_RATE_LIMIT')   || define('REGISTER_RATE_LIMIT', 1);
defined('REGISTER_RATE_WINDOW')  || define('REGISTER_RATE_WINDOW', 5);

// --- Redis Key Prefixes ---
defined('REDIS_PREFIX_BLACKLIST')  || define('REDIS_PREFIX_BLACKLIST', 'jwt:blacklist:');
defined('REDIS_PREFIX_SESSION')    || define('REDIS_PREFIX_SESSION', 'session:user:');
defined('REDIS_PREFIX_LOGIN')      || define('REDIS_PREFIX_LOGIN', 'login:attempts:');
defined('REDIS_PREFIX_RATE')       || define('REDIS_PREFIX_RATE', 'rate:');
defined('REDIS_KEY_MODULES')      || define('REDIS_KEY_MODULES', 'modules:status');
defined('REDIS_KEY_SITE_CONFIG')  || define('REDIS_KEY_SITE_CONFIG', 'site:config');

// --- VortexEngine: Subscription Engine ---
defined('SUB_STATUS_TRIAL')   || define('SUB_STATUS_TRIAL', 'TRIAL');
defined('SUB_STATUS_VIP')     || define('SUB_STATUS_VIP', 'VIP');
defined('SUB_STATUS_EXPIRED') || define('SUB_STATUS_EXPIRED', 'EXPIRED');

// Redis key pattern: sub:student:{student_id}
defined('REDIS_PREFIX_SUBSCRIPTION') || define('REDIS_PREFIX_SUBSCRIPTION', 'sub:student:');
defined('SUBSCRIPTION_CACHE_TTL')    || define('SUBSCRIPTION_CACHE_TTL', 300); // 5 phút
