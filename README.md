# Integrate Zephyr into Laravel

This package allows you to integrate Zephyr Test Suite into Laravel.

## Installation

You can install the package via composer:

```bash
composer require redberryproducts/laravel-zephyr
```

You can publish the config file with:

```bash
php artisan vendor:publish --provider="RedberryProducts\Zephyr\ZephyrServiceProvider"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

Run pest tests using `pest --log-junit storage/app/junit.xml`

### Getting test cases & structure from Zephyr and storing locally

You should get project key from test cases/folders index. For example, if test case ID is `SVB-T1`, project key is `SVB`

```bash
php artisan zephyr:generate $projectKey
```

### Sending test results to Zephyr

```bash
php artisan zephyr:results $projectKey
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [RedberryProducts](https://github.com/RedberryProducts)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

### Todo

* Make filesystem disk dynamic (move to config)
* Use stubs instead of hardcoding test file creation strings
* Use Junit format instead of JSON?