# Integrate Zephyr into Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/redberryproducts/laravel-zephyr.svg?style=flat-square)](https://packagist.org/packages/redberryproducts/laravel-zephyr)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/redberryproducts/laravel-zephyr/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/redberryproducts/laravel-zephyr/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/redberryproducts/laravel-zephyr/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/redberryproducts/laravel-zephyr/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/redberryproducts/laravel-zephyr.svg?style=flat-square)](https://packagist.org/packages/redberryproducts/laravel-zephyr)

This package allows you to integrate Zephyr Test Suite into Laravel.

## Installation

You can install the package via composer:

```bash
composer require redberryproducts/laravel-zephyr
```

You can publish the config file with:

```bash
sail php artisan vendor:publish --provider="RedberryProducts\Zephyr\ZephyrServiceProvider"
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
sail artisan zephyr:generate $projectKey
```

### Sending test results to Zephyr

```bash
sail artisan zephyr:results $projectKey
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