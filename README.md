# Bullhorn Client Bundle

Provides a simple client for the Bullhorn REST API.

Installation
============

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Applications that use Symfony Flex
----------------------------------

Open a command console, enter your project directory and execute:

```console
$ composer require developersnl/bullhorn-client-bundle
```

Add a `config\packages\bullhorn_client.yaml` file to set the configuration for this client. The following configuration is required:

```yaml
bullhorn_client:
  authentication:
    clientId: ''
    clientSecret: ''
    authUrl: 'https://auth-emea.bullhornstaffing.com/oauth/authorize'
    tokenUrl: 'https://auth-emea.bullhornstaffing.com/oauth/token'
    loginUrl: 'https://rest-emea.bullhornstaffing.com/rest-services/login'
  rest:
    username: ''
    password: ''
```

Applications that don't use Symfony Flex
----------------------------------------

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require developersnl/bullhorn-client-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Developersnl\BullhornClientBundle\BullhornClientBundle::class => ['all' => true],
];
```