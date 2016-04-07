# Deployer Example

This example implementation consists of 4 important files.

1. `composer.json` 
> This installs Deployer and related packages and sets up autload.
1. `.env` 
> The configuration values for your environment must be set up.
1. `public/index.php` 
> This file does the magic.  It loads the autoload capabilities, loads the configuration values, and then calls the deployer process.
1. `deployer.log` 
> This file is created when the first job is run through the process.  It will only be created if the LOG_ENABLE environment variable is set to true.

## Setup

1. Copy the example directory to your target location.
2. Run `composer install`
3. Test (postman, manual commits, etc. - as long as a post is made to the `public/index.php` file and that post contains a valid payload)
