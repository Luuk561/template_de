<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Forge API Token
    |--------------------------------------------------------------------------
    |
    | Your Forge API token for authenticating API requests.
    | Generate at: https://forge.laravel.com/user-profile/api
    |
    */

    'api_token' => env('FORGE_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Forge Organization Slug
    |--------------------------------------------------------------------------
    |
    | Your organization slug (usually your username or company name).
    | Find this in your Forge URL: forge.laravel.com/orgs/{slug}
    |
    */

    'organization' => env('FORGE_ORGANIZATION', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Forge Server ID
    |--------------------------------------------------------------------------
    |
    | The ID of the server where new sites should be created.
    |
    */

    'server_id' => env('FORGE_SERVER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Git Repository Configuration
    |--------------------------------------------------------------------------
    |
    | The Git repository that will be cloned for each new site.
    |
    */

    'git' => [
        'repository' => env('FORGE_GIT_REPO_URL', 'https://github.com/Luuk561/template'),
        'branch' => env('FORGE_GIT_BRANCH', 'main'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PHP Version
    |--------------------------------------------------------------------------
    |
    | The PHP version to use for new sites.
    |
    */

    'php_version' => env('FORGE_PHP_VERSION', 'php84'),

    /*
    |--------------------------------------------------------------------------
    | Database Password
    |--------------------------------------------------------------------------
    |
    | The MySQL password for the 'forge' user on your Forge server.
    | Find this in Forge: Server → Database tab
    |
    */

    'database_password' => env('FORGE_DATABASE_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Shared Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials that are shared across all generated sites.
    |
    */

    'shared_credentials' => [
        'bol_client_id' => env('BOL_CLIENT_ID'),
        'bol_client_secret' => env('BOL_CLIENT_SECRET'),
        'openai_api_key' => env('OPENAI_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Environment Variables
    |--------------------------------------------------------------------------
    |
    | Default environment variables for new sites.
    |
    */

    'default_env' => [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'APP_LOCALE' => 'nl',
        'APP_FALLBACK_LOCALE' => 'nl',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '12',
        'LOG_CHANNEL' => 'stack',
        'LOG_STACK' => 'single',
        'LOG_LEVEL' => 'debug',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'SESSION_DRIVER' => 'database',
        'SESSION_LIFETIME' => '120',
        'QUEUE_CONNECTION' => 'database',
        'CACHE_STORE' => 'database',
        'MAIL_MAILER' => 'log',
        'FILESYSTEM_DISK' => 'local',
        'BROADCAST_CONNECTION' => 'log',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Script Template
    |--------------------------------------------------------------------------
    |
    | The deployment script that will be used for new sites.
    |
    */

    'deploy_script' => <<<'BASH'
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci
npm run build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
BASH,

];
