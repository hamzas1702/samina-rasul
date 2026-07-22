<?php
// Router for PHP built-in server — serves static files directly,
// maps directory URLs to their index.php, everything else to WordPress.
$root = __DIR__;
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( $path !== '/' && file_exists( $root . $path ) && ! is_dir( $root . $path ) ) {
	return false; // static file — let the server handle it
}

if ( is_dir( $root . $path ) && file_exists( $root . rtrim( $path, '/' ) . '/index.php' ) ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
	require $root . $_SERVER['SCRIPT_NAME'];
	return true;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
