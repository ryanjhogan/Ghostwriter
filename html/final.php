<?php 
/*
   scott campbell
   Kurt Johnson
   display temps
 */
$DBSTRING = "sqlite:" . __DIR__ . "/cse383.db";
include "sql.inc";
include "final.class.php";

// Basic CORS headers. For local development we allow any origin.
// Also handle OPTIONS preflight and exit early.
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
header("Content-Type: application/json");

// If this is a preflight request, return early
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit(0);
}

require_once "RestServer.php";
// phpinfo();

// Kurt Johnson
// with linux function code by Scott Campbell


// $method=$_REQUEST["method"];
// example request: http://path/to/resource/rest/api/vi/sayHello?&name=World
//
//
// example request: http://path/final.php/sayHello?&name=World
// method="sayHello"
$method=str_replace("/","",$_SERVER["PATH_INFO"]);
$METHOD=$method;

$rest = new RestServer (new final_rest(),$method);
$rest->handle ();
