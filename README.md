# The official Swiftmailer transport for MailPace

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mailpace/mailpace-swiftmailer.svg?style=flat-square)](https://packagist.org/packages/mailpace/mailpace-swiftmailer)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/mailpace/mailpace-swiftmailer/Tests)](https://github.com/mailpace/mailpace-swiftmailer/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/mailpace/mailpace-swiftmailer/Check%20&%20fix%20styling?label=code%20style)](https://github.com/mailpace/mailpace-swiftmailer/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mailpace/mailpace-swiftmailer.svg?style=flat-square)](https://packagist.org/packages/mailpace/mailpace-swiftmailer)


[MailPace](https://mailpace.com) lets you send transactional emails from your app over an easy to use API.

This MailPace PHP Package is a transport for SwiftMailer to send emails via [MailPace](https://mailpace.com) to make sending emails from PHP apps super simple. You can use this with popular frameworks such as Laravel, Codeigniter and Symfony to send transactional emails, or with a standalone PHP app.

This package uses the MailPace HTTPS [/send endpoint](https://docs.mailpace.com/reference/send) to send the email - which is generally faster and more reliable than SMTP, although you can of course use SMTP to send out emails from your PHP app without installing this package if you prefer.

## Pre-requisites

You will need an MailPace account with a verified domain and organization with an active plan.

## Installation

Install the package via composer:

```bash
composer require mailpace/mailpace-swiftmailer
```
### Account Setup 

Set up an account at [MailPace](https://app.mailpace.com/users/sign_up) and complete the Onboarding steps

### Configure the Package

First you will need to retrieve your API token for your sending domain from [MailPace](https://app.mailpace.com). You can find it under Organization -> Domain -> API Tokens

You'll need to store this API token somewhere in your app/runtime, we recommend Environment Variables for this, and the examples below assume that you have an environment variable called `OHMYSMTP_API_TOKEN` that contains your API token

#### Sending without a framework

```php
<?php
require_once('./vendor/autoload.php');

$transport = new MailpaceSwiftmailerTransport(env('OHMYSMTP_API_TOKEN'));
$mailer = new Swift_Mailer($transport);

$message = (new Swift_Message('A transactional email from MailPace!'))
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
$headers->addTextHeader('MailPace-Tag', 'tag-1');
$headers->addTextHeader('MailPace-Tag', 'tag with spaces');

$mailer->send($message);
?>
```

#### Sending with Laravel

To send with Laravel you need to make a few small tweaks, but it really only takes a moment.

1. Add mailpace to the `config/mail.php` configuration file:

```php
'mailpace' => [
    'transport' => 'mailpace',
],
```


2. Add the following to your `config/services.php` configuration file:

```php
'mailpace' => [
  'apiToken' => env('OHMYSMTP_API_TOKEN'),
]
```

3. In `config/app.php`, add the following to the providers array:

```php
App\Providers\MailpaceServiceProvider::class,
``` 

and remove / comment out the line:

```php
 Illuminate\Mail\MailServiceProvider::class,
 ```

4. In your `.env` file (or wherever you store environment variables), change the `MAIL_MAILER` variable as follows:

`MAIL_MAILER=mailpace`

5. Create a new file called `MailpaceServiceProvider.php` in `App/Providers` with the following contents:

```php
<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Mail\MailServiceProvider;
use Mailpace\MailpaceSwiftmailer\MailpaceSwiftmailerTransport;

class MailpaceServiceProvider extends MailServiceProvider
{
    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', function ($app) {
            $manager = new MailManager($app);
            $this->registerOhMySmtpTransport($manager);
            return $manager;
        });
    }

    protected function registerOhMySmtpTransport(MailManager $manager) {
        $manager->extend('mailpace', function ($config) {
            if (! isset($config['apiToken'])) {
                $config = $this->app['config']->get('services.mailpace', []);
            }
            return new MailpaceSwiftmailerTransport($config['apiToken']);
        });
    }
}

```
After completing the above steps, all email will be sent via MailPace.

## Support

For support please check the [MailPace Documentation](https://docs.mailpace.com) or contact us at support@mailpace.com

## Contributing

Please ensure to add a test for any changes. To run the tests:

`composer test`

Pull requests always welcome

## License
The gem is available as open source under the terms of the [MIT License](https://opensource.org/licenses/MIT).