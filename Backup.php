<?php

include_once "./SSH.class.php";
require_once "./vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define("S3_KEY", $_ENV['S3_KEY']);
define("S3_SECRET", $_ENV['S3_SECRET']);

$ip = constant("IP::$argv[1]");
$chave = constant("KEY::$argv[2]");

$ssh = new SSH_DATAGRO($ip->value, $chave->value, $argv[1], S3_KEY, S3_SECRET);
$ssh->backup($argv[3]);