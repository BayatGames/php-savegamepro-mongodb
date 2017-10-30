<?php

include "includes/pollyfills.inc.php";
require 'vendor/autoload.php';

if (!file_exists('config.php')) {
  http_response_code(503);
  include "includes/notinstalled.inc.php";
  exit();
}

include "config.php";

if (!isset($_POST) || !isset($_POST['action'])) {
  http_response_code(400);
  echo 'Bad Request, the Request should use HTTP POST method with an "action" parameter.';
  exit;
}

$action = $_POST['action'];
$secret_key = $_POST['secret-key'];
$username = $_POST['username'];
$password = $_POST['password'];
$data_key = isset($_POST['data-key']) ? $_POST['data-key'] : false;
$data_value = isset($_POST['data-value']) ? $_POST['data-value'] : false;
$type = isset($_POST['type']) ? $_POST['type'] : 'user';
$create_account = isset($_POST['create-account']) ? true : false;

if ($secret_key !== SECRET_KEY) {
  http_response_code(400);
  exit("The Secret Key is invalid.");
}

if (!empty(DB_USER) && !empty(DB_PASS)) {
  $url = "mongodb://" . DB_USER . ":" . DB_PASS . "@" . DB_HOST;
} else if (!empty(DB_USER)) {
  $url = "mongodb://" . DB_USER . "@" . DB_HOST;
} else {
  $url = "mongodb://" . DB_HOST;
}

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
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the database, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $users = $db->users;
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the users collection, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $saves = $db->saves;
} catch (Exception $e) {
  http_response_code(500);
  echo "Error: Failed to select the saves collection, here is why:\n";
  echo $e->getMessage();
  exit;
}

try {
  $user = $users->findOne(array("username" => $username, "password" => $password));
} catch (Exception $e) {
  if ($create_account) {
    $date = date("Y-m-d H:i:s");
    $result = $users->insertOne(array("username" => $username, "password" => $password, "type" => $type, "registered" => $date));
    $user = array('_id' => $result->getInsertedId(), 'username' => $username, 'password' => $password);
  } else {
    http_response_code(500);
    echo "Error: Failed to find the user, here is why:\n";
    echo $e->getMessage();
    exit;
  }
}

switch ($action) {
  case 'save':
    try {
      $saves->updateOne(array(
        "user_id" => $user["_id"],
        "data_key" => $data_key
      ), array( '$set' => array(
        "data_value" => $data_value
      )), array(
        "upsert" => true
      ));
    } catch (Exception $e) {
      http_response_code(500);
      echo "Error: Failed to insert the save, here is why:\n";
      echo $e->getMessage();
      exit;
    }
    http_response_code(200);
    exit("Data Successfully Saved");
    break;
  case 'load':
    try {
      $save = $saves->findOne(array(
        "user_id" => $user["_id"],
        "data_key" => $data_key
      ));
    } catch (Exception $e) {
      http_response_code(500);
      echo "Error: Failed to find the save, here is why:\n";
      echo $e->getMessage();
      exit;
    }
    if (empty($save)) {
      http_response_code(500);
      echo "Error: Failed to find the save, It seems the save with the given details does not exists.";
      exit;
    }
    http_response_code(200);
    exit($save['data_value']);
    break;
  case 'delete':
    try {
      $saves->deleteOne(array(
        "user_id" => $user["_id"],
        "data_key" => $data_key
      ));
    } catch (Exception $e) {
      http_response_code(500);
      echo "Error: Failed to delete the save, here is why:\n";
      echo $e->getMessage();
      exit;
    }
    http_response_code(200);
    exit("User Data Successfully Deleted");
    break;
  case 'clear':
    try {
      $saves->deleteMany(array(
        "user_id" => $user["_id"]
      ));
    } catch (Exception $e) {
      http_response_code(500);
      echo "Error: Failed to delete the save, here is why:\n";
      echo $e->getMessage();
      exit;
    }
    http_response_code(200);
    exit("User Data Successfully Cleared");
    break;
  default:
    http_response_code(400);
    exit("The given action does not exists: $action");
    break;
}
