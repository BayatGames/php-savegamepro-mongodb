<?php

if (!isset($_POST) || !isset($_POST['db_name']) || !isset($_POST['db_user'])) {
  if (!file_exists("config.php")) {
    include "includes/install.inc.php";
  } else {
    include "includes/ready.inc.php";
  }
  exit;
}

$secret_key = md5(microtime().rand());
$replace = array(
  '<DB_NAME>' => '"' . $_POST["db_name"] . '"',
  '<DB_HOST>' => '"' . $_POST["db_host"] . '"',
  '<DB_PASS>' => '"' . $_POST["db_pass"] . '"',
  '<DB_USER>' => '"' . $_POST["db_user"] . '"',
  '<SECRET_KEY>' => '"' . $secret_key . '"'
);

$config_string = file_get_contents("config.tpl");
$config_string = str_replace(array_keys($replace), array_values($replace), $config_string);
file_put_contents("config.php", $config_string);

include "config.php";
include "includes/header.inc.php";
require 'vendor/autoload.php';

echo '<div class="container mt-5 mb-5">';

$url = "mongodb://" . DB_USER . ":" . DB_PASS . "@" . DB_HOST;
try {
  $client = new MongoDB\Client($url);
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to create connection to the MongoDB server, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $db = $client->{DB_NAME};
  echo '<div class="alert alert-success" role="alert">Database Created Successfully</div>';
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the database, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $users = $db->users;
  echo '<div class="alert alert-success" role="alert">Users Collection Created Successfully</div>';
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the users collection, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $saves = $db->saves;
  echo '<div class="alert alert-success" role="alert">Saves Collection Created Successfully</div>';
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the saves collection, here is why:\n";
  echo $e->getMessage();
  exit;
}

echo "<p>Here is your secret key, Write it down to a paper or some sources, because you can't access this page again:</p>";
echo '<div class="card"><div class="card-body text-center"><strong>' . $secret_key . "</strong></div></div>";

echo "</div>";

include "includes/footer.inc.php";
