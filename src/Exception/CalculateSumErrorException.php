<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Exception;

use Lexty\Robokassa\Payment;

class CalculateSumErrorException extends ResponseErrorException
{
    const ERR_CODE = 5;

    public static $msg = [
        Payment::CULTURE_EN => 'An error in the calculation of the amounts.',
        Payment::CULTURE_RU => 'Ошибка при расчёте сумм.',
    ];

    public function __construct(Payment $payment, $message = '', \Exception $previous = null)
    {
        if (empty($message)) {
            $message = self::$msg[$payment->getCulture()];
        }

        parent::__construct($payment, $message, self::ERR_CODE, $previous);
    }
}