<?php

// Set up autoloading
require __DIR__ .'/../vendor/autoload.php';

// Read the .env file for environment specific settings
$dotenv = new \Dotenv\Dotenv(__DIR__.'/..');
$dotenv->load();

// Process the incoming HTTP request
Grovers\Deployer\Deployer::process();