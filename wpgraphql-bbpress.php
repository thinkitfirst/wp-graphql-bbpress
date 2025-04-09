<?php

/**
 * Plugin Name: WPGraphQL for bbPress
 * Plugin URI: https://thinkitfirst.com
 * Description: WPGraphQL for bbPress integrates bbPress with WPGraphQL.
 * Version: 1.0.0
 * Author: Think It First
 * Author URI: https://thinkitfirst.com
 * Requires Plugins: wp-graphql, bbpress
 */

defined('ABSPATH') || die;

require_once __DIR__ . '/graphql/register-types.php';
require_once __DIR__ . '/graphql/fields/root-query.php';