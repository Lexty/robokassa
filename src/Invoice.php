<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

/**
 * The detailed information on the current status and payment details.
 */
class Invoice
{
    const STATE_INITIATED  = 5;
    const STATE_CANCELED   = 10;
    const STATE_PROCESSING = 50;
    const STATE_RETURNED   = 60;
    const STATE_SUSPENDED  = 80;
    const STATE_COMPLETED  = 100;

    /**
     * @var string[]
     */
    private static $stateDescriptions = [
        Payment::CULTURE_EN => [
            self::STATE_INITIATED  => 'Initiated, payment is not received by the service.',
            self::STATE_CANCELED   => 'Payment was not received, operation canceled.',
            self::STATE_PROCESSING => 'Payment received, payment is transferred to the shop account.',
            self::STATE_RETURNED   => 'Payment was returned to buyer after it was received.',
            self::STATE_SUSPENDED  => 'Operation execution is suspended.',
            self::STATE_COMPLETED  => 'Operation completed successfully.',
        ],
        Payment::CULTURE_RU => [
            self::STATE_INITIATED  => 'Операция только инициализирована, деньги от покупателя не получены.',
            self::STATE_CANCELED   => 'Операция отменена, деньги от покупателя не были получены.',
            self::STATE_PROCESSING => 'Деньги от покупателя получены, производится зачисление денег на счет магазина.',
            self::STATE_RETURNED   => 'Деньги после получения были возвращены покупателю.',
            self::STATE_SUSPENDED  => 'Исполнение операции приостановлено.',
            self::STATE_COMPLETED  => 'Операция выполнена, завершена успешно.',
        ],
    ];

    /**
     * Invoice ID.
     *
     * @var int
     */
    private $invoiceId;
    /**
     * Language of the payment interface.
     *
     * @var string
     */
    private $culture;
    /**
     * Operation current state code.
     *
     * @var int
     */
    private $stateCode;
    /**
     * Date/time of the request response.
     *
     * @var \Datetime
     */
    private $requestDate;
    /**
     * Date/time of last change of payment operation state.
     *
     * @var \Datetime
     */
    private $stateDate;
    /**
     * The sum paid by the client, in units of method `$paymentMethod`.
     *
     * @var float
     */
    private $clientSum;
    /**
     * Client account number (wallet, credit card number) in the payment system, used for paying.
     *
     * @var string
     */
    private $clientAccount;
    /**
     * The method of payment, which took advantage of the client.
     *
     * @var string
     */
    private $paymentMethod;
    /**
     * Payment method code.
     *
     * @var string
     */
    private $paymentMethodCode;
    /**
     * Payment method description.
     *
     * @var string
     */
    private $paymentMethodDescription;
    /**
     * Currency received by the shop.
     *
     * @var string
     */
    private $currency;
    /**
     * Sum credited to the shop account in the `$currency` currency units.
     *
     * @var float
     */
    private $shopSum;

    /**
     * Invoice constructor.
     *
     * @param int               $invoiceId Invoice ID.
     * @param string            $culture   Language of the payment interface.
     * @param \SimpleXMLElement $sxe       The XML object with data.
     */
    public function __construct($invoiceId, $culture, \SimpleXMLElement $sxe)
    {
        $this->invoiceId = (int)$invoiceId;
        $this->culture   = strtolower($culture);
        $this->parse($sxe);
    }

    /**
     * @return int
     */
    public function getStateCode()
    {
        return $this->stateCode;
    }

    /**
     * @return string
     */
    public function getStateDescription()
    {
        if (!isset(self::$stateDescriptions[$this->culture][$this->stateCode])) {
            return '';
        }
        return self::$stateDescriptions[$this->culture][$this->stateCode];
    }

    /**
     * Get invoice ID.
     *
     * @return int
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * Get Language of the payment interface.
     *
     * @return string
     */
    public function getCulture()
    {
        return $this->culture;
    }

    /**
     * Get date/time of the request response.
     *
     * @return \Datetime
     */
    public function getRequestDate()
    {
        return $this->requestDate;
    }

    /**
     * Get date/time of last change of payment operation state.
     *
     * @return \Datetime
     */
    public function getStateDate()
    {
        return $this->stateDate;
    }

    /**
     * Get the sum paid by the client, in units of method `getPaymentMethod()`.
     *
     * @return float
     */
    public function getClientSum()
    {
        return $this->clientSum;
    }

    /**
     * Get client account number (wallet, credit card number) in the payment system, used for paying.
     *
     * @return string
     */
    public function getClientAccount()
    {
        return $this->clientAccount;
    }

    /**
     * Get the method of payment, which took advantage of the client.
     *
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * Get the payment method code.
     *
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return $this->paymentMethodCode;
    }

    /**
     * Get the payment method description.
     *
     * @return string
     */
    public function getPaymentMethodDescription()
    {
        return $this->paymentMethodDescription;
    }

    /**
     * Get the currency received by the shop.
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Get the sum credited to the shop account in the `getCurrency()` currency units.
     *
     * @return float
     */
    public function getShopSum()
    {
        return $this->shopSum;
    }

    /**
     * Get the array representation of this object.
     *
     * @return array
     */
    public function asArray()
    {
        return [
            'InvoiceId'                => $this->invoiceId,
            'StateCode'                => $this->stateCode,
            'StateDescription'         => $this->getStateDescription(),
            'RequestDate'              => $this->requestDate->format('c'),
            'StateDate'                => $this->stateDate->format('c'),
            'PaymentMethod'            => $this->paymentMethod,
            'ClientSum'                => $this->clientSum,
            'ClientAccount'            => $this->clientAccount,
            'PaymentMethodCode'        => $this->paymentMethodCode,
            'PaymentMethodDescription' => $this->paymentMethodDescription,
            'Currency'                 => $this->currency,
            'ShopSum'                  => $this->shopSum,
        ];
    }

    private function parse($sxe)
    {
        $this->stateCode = (int)$sxe->State->Code;
        $this->requestDate = new \DateTime((string)$sxe->State->RequestDate);
        $this->stateDate = new \DateTime((string)$sxe->State->StateDate);

        $this->paymentMethod            = (string)$sxe->Info->IncCurrLabel;
        $this->clientSum                = (float)$sxe->Info->IncSum;
        $this->clientAccount            = (string)$sxe->Info->IncAccount;
        $this->paymentMethodCode        = (string)$sxe->Info->PaymentMethod->Code;
        $this->paymentMethodDescription = (string)$sxe->Info->PaymentMethod->Description;
        $this->currency                 = (string)$sxe->Info->OutCurrLabel;
        $this->shopSum                  = (float)$sxe->Info->OutSum;
    }
}