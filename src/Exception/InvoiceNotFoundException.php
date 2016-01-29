<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/Robokassa/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Exception;

use Lexty\Robokassa\Payment;

class InvoiceNotFoundException extends ResponseErrorException
{
    const ERR_CODE = 3;

    public function __construct(Payment $payment, $message = '', \Exception $previous = null)
    {
        parent::__construct($payment, $message, self::ERR_CODE, $previous);
    }
}