<?php
// wp-config.php's DB_HOST ('localhost') resolves via Local's socket under PHP-FPM but not
// from the CLI. Defining it first wins — wp-config's own define() then no-ops.
define( 'DB_HOST', 'localhost:/Users/locnguyen/Library/Application Support/Local/run/zIjaRXwHG/mysql/mysqld.sock' );
require __DIR__ . '/seed-demo.php';
