# PHP library for Robokassa payment system

[![Build Status](https://travis-ci.org/Lexty/robokassa.svg?branch=master)](https://travis-ci.org/Lexty/robokassa)
[![Latest Stable Version](https://poser.pugx.org/lexty/robokassa/v/stable)](https://packagist.org/packages/lexty/robokassa)
[![Latest Unstable Version](https://poser.pugx.org/lexty/robokassa/v/unstable)](https://packagist.org/packages/lexty/robokassa)
[![Total Downloads](https://poser.pugx.org/lexty/robokassa/downloads)](https://packagist.org/packages/lexty/robokassa)
[![License](https://poser.pugx.org/lexty/robokassa/license)](https://packagist.org/packages/lexty/robokassa)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/a4941db4-0d75-4cca-9406-c195d89c646f/mini.png)](https://insight.sensiolabs.com/projects/a4941db4-0d75-4cca-9406-c195d89c646f)

## Installation

```bash
$ composer require lexty/robokassa
```

## Examples

Create payment:
```php
$payment = new \Lexty\Robokassa\Payment(
    new \Lexty\Robokassa\Auth('your_login', 'password1', 'password2', true)
);

$payment
    ->setInvoiceId($orderId)
    ->setSum($orderAmount)
    ->setCulture(Payment::CULTURE_EN)
    ->setDescription('Payment for some goods');

// redirect to payment url
header("Location: {$payment->getPaymentUrl()}");

// or show payment button on page
// <script src="<?php echo $payment->getFormUrl(Payment::FORM_TYPE_L); ?>"></script>
```

Check payment result:

```php
// somewhere in result url handler...
...
$payment = new \Lexty\Robokassa\Payment(
    new \Lexty\Robokassa\Auth('your_login', 'password1', 'password2', true)
);

if ($payment->validateResult($_GET) {

    // send answer
    echo $payment->getSuccessAnswer(); // "OK123\n"
}
...
```

Check payment on Success page:
```php
...
$payment = new \Lexty\Robokassa\Payment(
    new \Lexty\Robokassa\Auth('your_login', 'password1', 'password2', true)
);

if ($payment->validateSuccess($_GET) {
    // payment is valid
}
...
```

### Pay a commission for the buyer

For paying comission for the buyer is sufficient to simply call `$payment->setShopCommission(true)`

Please bear in mind that a user who is on the payment page ROBOKASSA can change the payment method with another
commission. In this case, he will pay a different amount.

### Getting list of currency

[Robokassa API](http://docs.robokassa.ru/en/#2565)

Returns the list of currencies available to pay for the orders from a particular store/website.

It is used to specify the value of `$payment->setPaymentMethod($method)` and to display the available payment options
right on your website, if you wish to give more information to your clients.

```php
use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;

$client = new Client(
    new Auth('your_login', 'password1', 'password2', true)
);

$currencies = $client
	->setCulture(Client::CULTURE_EN)
	->getCurrencies();
```

Returns
```php
array (
  array (
    'Code' => 'Terminals',
    'Description' => 'terminal ',
    'Items' => 
    array (
      array (
        'Label' => 'TerminalsElecsnetOceanR',
        'Alias' => 'Elecsnet',
        'Name' => 'Elecsnet',
        'MaxValue' => '15000',
      ),
    ),
  ),
  array (
    'Code' => 'EMoney',
    'Description' => 'e-wallet',
    'Items' => 
    array (
      array (
        'Label' => 'W1OceanR',
        'Alias' => 'W1',
        'Name' => 'RUR W1',
        'MaxValue' => '14999',
      ),
      array (
        'Label' => 'ElecsnetWalletR',
        'Alias' => 'ElecsnetWallet',
        'Name' => 'ElecsnetWallet',
        'MaxValue' => '14999',
      ),
    ),
  ),
)
```

### Getting list of available payment method groups

[Robokassa API](http://docs.robokassa.ru/en/#2582)

Returns the list of payment methods available to pay for the orders from a particular store/website.
It is used to display the available payment methods right on your website, if you wish to give more
information to your clients. The key difference from the `$payment->getCurrencies()` – is that no
detailed information is shown here for all payment options, while only payment groups/methods are
displayed.

```php
use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;

$client = new Client(
    new Auth('your_login', 'password1', 'password2', true)
);

$paymentMethodGroups = $client
	->setCulture(Client::CULTURE_EN)
	->getPaymentMethodGroups();
```

Returns
```php
array (
  'Terminals' => 'terminal ',
  'EMoney' => 'e-wallet',
  'BankCard' => 'Bank card',
  'Bank' => 'Internet Banking',
  'Other' => 'other methods ',
)
```

### Calculating the amount payable including ROBOKASSA’s charge

[Robokassa API](http://docs.robokassa.ru/en/#2596)

Helps calculate the amount payable by the buyer including ROBOKASSA’s charge (according to the
service plan) and charges of other systems through which the buyer decided to pay for the order.
It may be used both for your internal payments and to provide additional information to the
clients on your website.

```php
use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;

$client = new Client(
    new Auth('your_login', 'password1', 'password2', true)
);

$rates = $client
	->setCulture(Client::CULTURE_EN)
	->getRates(500);
```

Returns
```php
array (
  array (
    'Code' => 'Terminals',
    'Description' => 'terminal ',
    'Items' => 
    array (
      array (
        'Label' => 'TerminalsElecsnetOceanR',
        'Alias' => 'Elecsnet',
        'Name' => 'Elecsnet',
        'MaxValue' => '15000',
        'ClientSum' => 534.76,
      ),
    ),
  ),
  array (
    'Code' => 'EMoney',
    'Description' => 'e-wallet',
    'Items' => 
    array (
      array (
        'Label' => 'W1OceanR',
        'Alias' => 'W1',
        'Name' => 'RUR W1',
        'MaxValue' => '14999',
        'ClientSum' => 537.63,
      ),
      array (
        'Label' => 'ElecsnetWalletR',
        'Alias' => 'ElecsnetWallet',
        'Name' => 'ElecsnetWallet',
        'MaxValue' => '14999',
        'ClientSum' => 535,
      ),
    ),
  ),
)
```

### Outgoing summ calculation

[Robokassa API](http://docs.robokassa.ru/en/#2616)

Helps calculate the amount receivable on the basis of ROBOKASSA prevailing exchange rates from
the amount payable by the user.

```php
use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;

$client = new Client(
    new Auth('your_login', 'password1', 'password2', true)
);

$sum = $client->calculateClientSum(500, 'TerminalsElecsnetOceanR'); // 534.76
```

### Operation State

[Robokassa API](http://docs.robokassa.ru/en/#2629)

Returns detailed information on the current status and payment details.

Please bear in mind that the transaction is initiated not when the user is redirected to the payment
page, but later – once his payment details are confirmed, i.e. you may well see no transaction,
which you believe should have been started already.

```php
use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;

$client = new Client(
    new Auth('your_login', 'password1', 'password2', true)
);

$invoice = $client
    ->setCulture(Client::CULTURE_EN)
    ->getInvoice(1);
```

Returns
```php
array (
  'InvoiceId' => 1,
  'StateCode' => 100,
  'RequestDate' => \DateTime object,
  'StateDate' => \DateTime object,
  'PaymentMethod' => 'HandyBankKB',
  'ClientSum' => 100,
  'ClientAccount' => 'Test account',
  'PaymentMethodCode' => 'eInvoicing',
  'PaymentMethodDescription' => 'Internet bank',
  'Currency' => 'MerchantR',
  'ShopSum' => 94.5,
)
```

## License

[MIT](LICENSE)
