<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'admin' );

/** Database password */
define( 'DB_PASSWORD', 'admin' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '`hk{C_H^nGw`=~-LC&oC?cVfQS:qZ3k(q[:v&JE)Cpv@|pVOLV`.:w$5E} OtNVh' );
define( 'SECURE_AUTH_KEY',   'fvBH_A/6phE6F +L#P4gZ@/5c_iXsOzx3(v]PLA]vuQwzlp!nXHIGAF3zw8>:eu*' );
define( 'LOGGED_IN_KEY',     '1o%oN/44-P}HZU^@)nBrS)iEy=gc/:~~0[dV4tML#X!2zW)n!%xzKdzLw3/Z64Tn' );
define( 'NONCE_KEY',         '[5UTX-Ytc?DM:+lskrSxo2tGEo~7w66_`0Y4*6,*Q{#[[4Cy&)f&dIzd7IL?AC4/' );
define( 'AUTH_SALT',         'E:r;_6KkH?NhCwigb&>zlD/`D9B[@QFseRS4SBRtN3@NpRilM!@pj{6=l/L[K_xu' );
define( 'SECURE_AUTH_SALT',  '-]`-!~bjY6Bb};0gTwS/ZClDlZCHV4rx?OgluXh6: W[5Y{b/HUR2P$_,Z43]Xl7' );
define( 'LOGGED_IN_SALT',    '<j28DST`hyf~B|2M0O>$dQE_d){.~>98B4KqvX[yT$|9UyK5KXz3K[WF~Lk1|A4n' );
define( 'NONCE_SALT',        'qTjRHqiaG8z+P,`MH>,]dn_BXsLUG+c+Ulu43$ZR@hic{BkhY:!T,p`Xjh61i3~,' );
define( 'WP_CACHE_KEY_SALT', 'Op,_b3`~ATf]z2ZiXqMUr]-24&IOk ;+?zj49N-:-Ylt!MjlX9Bu`n;RY(Gk[+N<' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
