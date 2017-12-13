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
$filename = isset($_POST['file-name']) ? $_POST['file-name'] : $data_key;

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
  case 'getfileurl':
    $filepath = merge_paths(UPLOAD_FOLDER, $username, $filename);
    if (file_exists($filepath)) {
      $filepath = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $filepath);
      $filepath = str_replace('\\', '/', $filepath);
      $url = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
      $url .= $_SERVER['HTTP_HOST'] . $filepath;
      http_response_code(200);
      echo $url;
      exit;
    } else {
      http_response_code(500);
      echo "Error: The Requested File does not Exists.";
      exit;
    }
    break;
  case 'uploadfile':
    if (isset($_FILES) && isset($_FILES['file'])) {
      if ($_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $filepath = merge_paths(UPLOAD_FOLDER, $username, $_FILES['file']['name']);
        if (!file_exists(dirname($filepath))) {
          mkdir(dirname($filepath), 0777, true);
        }
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
          http_response_code(200);
          exit("File Successfully Uploaded");
        } else {
          http_response_code(500);
          echo "Error: Upload Failed, maybe the file is invalid or there is some problem with the file.";
          exit;
        }
      } else {
        http_response_code(500);
        echo "Error: Upload Failed, here is why: \n";
        switch ($_FILES['file']['error']) {
          case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
          case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
          case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
          case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
          case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
          case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
          case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;
          default:
            $message = "Unknown upload error";
            break;
        }
        echo $message;
        exit;
      }
    } else {
      http_response_code(500);
      echo "Error: No File Received, Upload Failed.";
      exit;
    }
    break;
  case 'downloadfile':
    $filepath = merge_paths(UPLOAD_FOLDER, $username, $filename);
    if (file_exists($filepath)) {
      header('Content-Description: File Transfer');
      header('Content-Type: application/octet-stream');
      header("Content-Transfer-Encoding: Binary");
      header('Expires: 0');
      header('Cache-Control: must-revalidate');
      header('Pragma: public');
      header("Content-Length: " . filesize($filepath));
      readfile($filepath);
    } else {
      http_response_code(500);
      echo "Error: Requested File does not Exists, Download Failed.";
      exit;
    }
    break;
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
