<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', 'root' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'epr8Hnbes7JqArbhco6qRy6FM3CeASNgyoGqs+8MSBVuiJ8Wh7rV/mm+L7nrK6Gz+Ey/GhoI4HXIdyRcP/Ae/Q==');
define('SECURE_AUTH_KEY',  'h58LscGoPJUfp7Ctzb7OWPspfNJy5mXatrZaaWyrcvfyfzPI9YKikVU9XH2njOA8paIzWuf0WjWQDlv6VponVA==');
define('LOGGED_IN_KEY',    'H3mnIgs9n38m1EeMtBecDGq9Q6tomAhvg1gA9brY2oCxzGKsF0ZA7acfgt3J0p/1xjwPoq4y4AUI16Er8qgU1w==');
define('NONCE_KEY',        'pyZtOyaIusGmTPG25hXikA3lj2owcNsXe0K9e0K7kEaH/DfGZC/z00UNU7SJn0Oii8r0ka1LeszXwvcZZxAKLw==');
define('AUTH_SALT',        '6VDVR/sXEy6JB1p4latovZA2wp6uFImeRbmm2veetfHGKl69D2r/Q1U43B46E+aYp5OsR8AA4ipaNxkeq//b4A==');
define('SECURE_AUTH_SALT', 'GJxMjsiu3Ybn9D74OcevAx1/QkVhNWjXRgzOrOIvsL7QzSD4cUgiRFM6OFksmIAHA/MD2uS+u6/vFP3fb1nY9g==');
define('LOGGED_IN_SALT',   'lyrrOiCL/tKwXyyeVrzy27ngoFCtQS9fcAlL07xawbUPeZLMUbFrodgmAl6mWKyNcVOynwJ2YEr0CVRgMU16gA==');
define('NONCE_SALT',       'rWE4X4Fdum9VtR/9SvrYnRd8tBPaFQRUQscO5CexgnZ2OaloE5SKTjN74yib4tinnOY08wVyAZNfymyxv6s++A==');

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';




/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
