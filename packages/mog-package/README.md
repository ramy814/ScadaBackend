## add local repository in composer.json

```json
"repositories": [
    {
        "type": "path",
        "url": "../mog-package",
        "options": {
            "symlink": true
        }
    }
],
```
## Installation

`composer require mog/project-helper`

`php artisan vendor:publish --tag=mog-helpers-config`

## Configurations

setup configurations in config/mog-helpers.php
```
'mog_app_name' => env('MOG_APP_NAME', ''),
'sso_client_id' => env('SSO_CLIENT_ID', ''),
'sso_client_secret' => env('SSO_CLIENT_SECRET', ''),
'sso_default_guard' => env('SSO_DEFAULT_GUARD', ''),
'main-disk' => env('MAIN_DISK', ''),
'oracle_conn_name' => env('ORACLE_CONN_NAME', ''),
```

## SSO Configurations
```
remove any route with name 'login', 'logout', 'change-password' to let package register this routes
```

```
use route('change-password') to add link to user to change his password
```

```
setup User Model table name 'mog_users.users'
```

## Usage

`use Mog\ProjectHelper\HelperClass;`

`HelperClass::helper_function($parameters)`

##
