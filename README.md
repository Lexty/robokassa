# PHP library for Robokassa payment system

## Installation

```bash
$ composer require lexty/robokassa "dev-master"
```

## Examples

Create payment:

```php
$payment = new \Lexty\Robokassa\Payment(
    'your_login', 'password1', 'password2', true
);

$payment
    ->setInvoiceId($orderId)
    ->setSum($orderAmount)
    ->setCulture(Payment::CULTURE_EN)
    ->setDescription('Payment for some goods');

// redirect to payment url
header("Location: {$payment->getPaymentUrl()}");

// or show payment button on page
// <script src="<?php echo $payment->getScriptUrl(Payment::FORM_TYPE_L); ?>"></script>
```

Check payment result:

```php
// somewere in result url handler...
...
$payment = new \Lexty\Robokassa\Payment(
    'your_login', 'password1', 'password2', true
);

if ($payment->validateResult($_GET) {

    // send answer
    echo $payment->getSuccessAnswer(); // "OK123\n"
}
...
```

### Pay a commission for the buyer

For paying comission for the buyer is sufficient to simply call `$payment->setShopCommission(true)`

Please bear in mind that a user who is on the payment page ROBOKASSA can change the payment method with another
commission. In this case, he will pay a different amount.

## License

[MIT](LICENSE)