<?php

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.1'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// Provide a minimal fallback for the `Locale` class when the PHP `intl`
// extension is not available. CodeIgniter calls `Locale::setDefault()`
// during bootstrap; defining this no-op/compat shim prevents a fatal
// error on systems without `ext-intl`. Enabling `intl` in PHP is still
// the recommended fix.
if (! class_exists('Locale')) {
    class Locale
    {
        public static function setDefault($locale)
        {
            // Try to set a sensible locale fallback using PHP's setlocale
            // and environment variable. This is intentionally minimal.
            if (! empty($locale)) {
                // setlocale expects different locale formats; try the raw
                // value and a UTF-8 variant.
                @setlocale(LC_ALL, $locale);
                @setlocale(LC_ALL, $locale . '.UTF-8');
                @putenv('LC_ALL=' . $locale);
            }
        }
        
        public static function getDefault(): string
        {
            $vars = [
                getenv('LC_ALL'),
                getenv('LANG'),
                getenv('LC_MESSAGES'),
                getenv('LANGUAGE'),
            ];

            foreach ($vars as $v) {
                if (! empty($v)) {
                    $v = preg_replace('/\.(UTF-?8|utf-?8)$/i', '', $v);
                    $v = str_replace('_', '-', $v);
                    return $v;
                }
            }

            return 'en';
        }
    }
}

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
