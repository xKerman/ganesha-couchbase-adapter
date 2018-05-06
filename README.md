# ganesha-plugin/couchbase-adapter

This package provides Couchbase adapter for [ackintosh/ganesha](https://packagist.org/packages/ackintosh/ganesha).


## Installation

$ composer require ganesha-plugin/couchbase-adapter


## How to use

With [ackintosh/ganesha](https://packagist.org/packages/ackintosh/ganesha):

```php
// create bucket instance
$cluster = new \Couchbase\Cluster('...');
$authenticator = new \Couchbase\PasswordAuthenticator();
$authenticator->username('...')->password('...');
$cluster->authenticate($authenticator);
$bucket = $cluster->openBucket('...');

$ganesha = \Ackintosh\Ganesha\Builder::build([
    ..., // other options
    'adapter' => new \GaneshaPlugin\Adapter\Couchbase($bucket),
]);
```

## Development

to run unit test:

```
$ make start    # start couchbase server in docker container
$ composer test # run unit test
```

## License

MIT License
