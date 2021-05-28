# The official Swiftmailer transport for OhMySMTP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ohmysmtp/ohmysmtp-swiftmailer.svg?style=flat-square)](https://packagist.org/packages/ohmysmtp/ohmysmtp-swiftmailer)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/ohmysmtp/ohmysmtp-swiftmailer/run-tests?label=tests)](https://github.com/ohmysmtp/ohmysmtp-swiftmailer/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/ohmysmtp/ohmysmtp-swiftmailer/Check%20&%20fix%20styling?label=code%20style)](https://github.com/ohmysmtp/ohmysmtp-swiftmailer/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ohmysmtp/ohmysmtp-swiftmailer.svg?style=flat-square)](https://packagist.org/packages/ohmysmtp/ohmysmtp-swiftmailer)


[OhMySMTP](https://ohmysmtp.com) lets you send transactional emails from your app over an easy to use API.

This OhMySMTP PHP Package is a transport for SwiftMailer to send emails via [OhMySMTP](https://ohmysmtp.com) to make sending emails from PHP apps super simple. You can use this with popular frameworks such as Laravel, Codeigniter and Symfony to send transactional emails, or with a standalone PHP app.

## Pre-requisites

You will need an OhMySMTP account with a verified domain and organization with an active plan.

## Installation

Install the package via composer:

```bash
composer require ohmysmtp/ohmysmtp-swiftmailer
```
### Account Setup 

Set up an account at [OhMySMTP](https://app.ohmysmtp.com/users/sign_up) and complete the Onboarding steps

### Configure the Package

First you will need to retrieve your API token for your sending domain from [OhMySMTP](https://app.ohmysmtp.com). You can find it under Organization -> Domain -> API Tokens

You'll need to store this API token somewhere in your app/runtime, we recommend Environment Variables for this, and the examples below assume that you have an environment variable called `OHMYSMTP_API_TOKEN` that contains your API token

#### Sending without a framework

```php
<?php
require_once('./vendor/autoload.php');

$transport = new Ohmysmtp\OhmysmtpSwiftmailer(env('OHMYSMTP_API_TOKEN'));
$mailer = new Swift_Mailer($transport);

$message = (new Swift_Message('A transactional email from OhMySMTP!'))
  ->setFrom(['php@yourdomain.com' => 'Your Name'])
  ->setTo(['someone@example.com'])
  ->setBody('<h1>HTML content</h1>', 'text/html')
  ->addPart('Text Body','text/plain');

// Attachments
$data = 'Raw Attachment Data';
$attachment = new Swift_Attachment($data, 'attachment.txt', 'text/plain');
$message->attach($attachment);

// Email Tags
$headers = $message->getHeaders();
$headers->addTextHeader('OMS-Tag', 'tag-1');
$headers->addTextHeader('OMS-Tag', 'tag with spaces');

$mailer->send($message);
?>
```

#### Sending with Laravel

Set the default option in your `config/mail.php` configuration file to `ohmysmtp`

Verify that your `config/services.php` configuration file contains the following option:

```php
'ohmysmtp' => [
    'apiToken' => env('OHMYSMTP_API_TOKEN'),
]
```

#### Sending with Symfony

TODO

## Support

For support please check the [OhMySMTP Documentation](https://docs.ohmysmtp.com) or contact us at support@ohmysmtp.com

## Contributing

Please ensure to add a test for any changes. To run the tests:

`composer test`

Pull requests always welcome

## License
The gem is available as open source under the terms of the [MIT License](https://opensource.org/licenses/MIT).