<?php
//fill in the correct information
  
$cfg = new stdClass();
// illuminate database configuration
$cfg -> database = [ 
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'database_name'),
    'username' => env('DB_USERNAME', 'username'),
    'password' => env('DB_PASSWORD', 'password'),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => false,
    'engine' => null,
];
// new api client key and secret, along with paths to the api
  $cfg -> client_key = 'backend_client_key';
  $cfg -> client_secret = 'backend_client_secret';
  $cfg -> resource_url = 'https://backend.econjobmarket.org/api/';
  $cfg -> token_url = 'https://backend.econjobmarket.org/token/';
// true sets up some drupal specific bits in the database
$cfg -> use_drupal_module = true;

/* if you change the following setting to false, the blobs won't be transferred. You would normally only do 
 * this for debugging purposes.  It speeds things up considerably
 */
$cfg -> transfer_blobs = false;
//randomly assign each new application to someone on the recruiting committee, fill in the number of desired
// assignments per application.  This is for the drupal module only 
$cfg -> auto_assign = 0;
// rating terms that you want included in myratings
$cfg -> rating_terms = ["Good", "Bad", "Ugly",];
//whether to add rating terms if there are already rating terms, true means skip them
$cfg -> rating_terms_add_once = true;

?>