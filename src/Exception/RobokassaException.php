<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/Robokassa/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Exception;

use Lexty\Robokassa\Payment;

class RobokassaException extends \RuntimeException
{
    /**
     * @var Payment
     */
    private $payment;

    /**
     * RobokassaException constructor.
     *
     * @param Payment         $payment
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct(Payment $payment, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->payment = $payment;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return Payment
     */
    public function getPayment()
    {
        return $this->payment;
    }
}