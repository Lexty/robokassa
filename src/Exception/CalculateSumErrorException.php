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

    public function __construct($message = '', \Exception $previous = null)
    {
        parent::__construct($message, self::ERR_CODE, $previous);
    }
}
