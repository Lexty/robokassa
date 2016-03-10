<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Exception;

class InvoiceNotFoundException extends ResponseErrorException
{
    const ERR_CODE = 3;

    public function __construct($message = '', \Exception $previous = null)
    {
        parent::__construct($message, self::ERR_CODE, $previous);
    }
}
