<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

use Lexty\Robokassa\Exception\CalculateSumErrorException;
use Lexty\Robokassa\Exception\EmptyDescriptionException;
use Lexty\Robokassa\Exception\EmptyPaymentMethodException;
use Lexty\Robokassa\Exception\EmptySumException;
use Lexty\Robokassa\Exception\InvalidCultureException;
use Lexty\Robokassa\Exception\InvalidExpirationDateException;
use Lexty\Robokassa\Exception\InvalidInvoiceIdException;
use Lexty\Robokassa\Exception\InvalidSumException;
use Lexty\Robokassa\Exception\InvoiceNotFoundException;
use Lexty\Robokassa\Exception\ResponseErrorException;
use Lexty\Robokassa\Exception\UnsupportedHashAlgorithmException;
use Lexty\Robokassa\Exception\UnsupportedRequestMethodException;

class Payment
{
    const CULTURE_EN = 'en';
    const CULTURE_RU = 'ru';

    const REQUEST_METHOD_GET  = 'get';
    const REQUEST_METHOD_POST = 'post';

    const FORM_TYPE_M   = 'M';
    const FORM_TYPE_MS  = 'MS';
    const FORM_TYPE_S   = 'S';
    const FORM_TYPE_SS  = 'SS';
    const FORM_TYPE_L   = 'L';
    const FORM_TYPE_V   = 'V';
    const FORM_TYPE_FL  = 'FL';
    const FORM_TYPE_FLS = 'FLS';

    const HASH_ALGO_MD5       = 'md5';
//    const HASH_ALGO_RIPEMD160 = 'ripemd160';
//    const HASH_ALGO_SHA1      = 'sha1';
//    const HASH_ALGO_SHA256    = 'sha256';
//    const HASH_ALGO_SHA384    = 'sha384';
//    const HASH_ALGO_SHA512    = 'sha512';

    /**
     * @var string
     */
    private $merchantLogin;
    /**
     * @var string
     */
    private $paymentPassword;
    /**
     * @var string
     */
    private $validationPassword;
    /**
     * @var float
     */
    private $sum;
    /**
     * The shop pays a commission for the buyer.
     *
     * @var bool
     */
    private $shopCommission = false;
    /**
     * Sum credited to the shop account in the Payment::$outCurrLabel currency units.
     *
     * @var float
     */
    private $shopSum;
    /**
     * The sum paid by the client, in units of currency Payment::$incCurrLabel.
     *
     * @var float
     */
    private $clientSum;
    /**
     * @var int
     */
    private $invoiceId;
    /**
     * @var string
     */
    private $description;
    /**
     * Payment method.
     *
     * @var string
     */
    private $paymentMethod;
    /**
     * Language of the interface.
     *
     * @var string
     */
    private $culture = self::CULTURE_RU;
    /**
     * @var string
     */
    private $encoding;
    /**
     * @var string
     */
    private $email;
    /**
     * @var \DateTime
     */
    private $expirationDate;
    /**
     * @var string
     */
    private $currency;
    /**
     * @var bool
     */
    private $isTest;
    /**
     * @var \string[]
     */
    private $customParams = [];
    /**
     * @var string
     */
    private $hashAlgo = self::HASH_ALGO_MD5;
    /**
     * @var string
     */
    private $formType = self::FORM_TYPE_M;
    /**
     * @var string
     */
    private $requestMethod = self::REQUEST_METHOD_GET;
    /**
     * @var bool
     */
    private $throwExceptions = true;
//    /**
//     * @var bool
//     */
//    private $recurring = false;
//    /**
//     * @var int
//     */
//    private $previousInvoiceId;
    /**
     * @var int
     */
    private $errorCode;
    /**
     * @var string
     */
    private $errorDescription;
    /**
     * @var bool
     */
    private $valid = false;

    private $isShopSumChanged   = false;
    private $isClientSumChanged = false;

    private $scriptBaseUrl    = 'https://auth.robokassa.ru/Merchant/PaymentForm/Form';
    private $paymentBaseUrl   = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    private $serviceBaseUrl   = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/';
//    private $recurringBaseUrl = 'https://auth.robokassa.ru/Merchant/Recurring';

    private $customParamsPrefix = 'Shp_';
    private $shopComissionCustomParamKey = '_shop_commission';

    /**
     * Payment constructor.
     *
     * @param string $merchantLogin
     * @param string $paymentPassword
     * @param string $validationPassword
     * @param bool   $isTest
     */
    public function __construct($merchantLogin, $paymentPassword, $validationPassword, $isTest = false)
    {
        $this->setMerchantLogin($merchantLogin);
        $this->setPaymentPassword($paymentPassword);
        $this->setValidationPassword($validationPassword);
        $this->setIsTest($isTest);
    }

    /**
     * @param null|string $type Available values: `M`, `MS`, `S`, `SS`, `L`, `V`, `FL`, `FLS`.
     *
     * @return string
     *
     * @link http://docs.robokassa.ru/en#2537
     */
    public function getScriptUrl($type = null)
    {
        if (null === $type) {
            $type = $this->formType;
        } else {
            $type = strtoupper($type);
        }
        return $this->scriptBaseUrl . $type . '.js?' . $this->getPaymentUrlQueryString(
            self::FORM_TYPE_FL === $type || self::FORM_TYPE_FLS === $type
        );
    }

    /**
     * @return string
     */
    public function getPaymentBaseUrl()
    {
        return $this->paymentBaseUrl;
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->paymentBaseUrl . '?' . $this->getPaymentUrlQueryString();
    }

    /**
     * @param bool $defaultSum The `OutSum` will set as `DefaultSum`.
     *
     * @return string
     */
    private function getPaymentUrlQueryString($defaultSum = false)
    {
        return http_build_query($this->getPaymentUrlQueryArray($defaultSum));
    }

    /**
     * @param bool $defaultSum The `OutSum` will set as `DefaultSum`.
     *
     * @return array
     */
    private function getPaymentUrlQueryArray($defaultSum = false)
    {
        if (empty($this->description)) {
            throw new EmptyDescriptionException($this);
        }

        $params = [
            'MerchantLogin'  => $this->merchantLogin,
            'Description'    => $this->description,
            'SignatureValue' => $this->getPaymentSignatureHash(),
        ];

        if ($defaultSum) {
            $params['DefaultSum'] = $this->sum;
        } else {
            $params['OutSum'] = $this->getShopSum();
        }

        if ($this->shopCommission) {
            $this->setCustomParam('_shop_commission', 1);
        }

        if ($this->invoiceId)          $params['InvId']             = $this->invoiceId;
        if ($this->culture)            $params['Culture']           = $this->culture;
        if ($this->encoding)           $params['Encoding']          = $this->encoding;
        if ($this->email)              $params['Email']             = $this->email;
        if ($this->expirationDate)     $params['ExpirationDate']    = $this->expirationDate->format('c');
        if ($this->currency)           $params['OutSumCurrency']    = $this->currency;
        if ($this->paymentMethod)      $params['IncCurrLabel']      = $this->paymentMethod;
        if ($this->isTest)             $params['isTest']            = 1;
//        if ($this->recurring)          $params['Recurring']         = 1;
//        if ($this->previousInvoiceId)  $params['PreviousInvoiceID'] = $this->previousInvoiceId;
        if ($this->customParams)       $params += $this->getCustomParamsArray();

        return $params;
    }

    /**
     * @return string
     */
    public function getPaymentSignatureHash() {
        return $this->getSignatureHash($this->getPaymentSignature());
    }

    /**
     * @return string
     */
    public function getPaymentSignature() {
        if (!$this->sum) {
            throw new EmptySumException($this);
        }

        return $this->getSignatureValue('{ml}:{ss}:{ii}{:cr}:{pp}{:cp}', [
            'ml' => $this->merchantLogin,
            'ss' => $this->getShopSum(),
            'ii' => $this->invoiceId,
            'cr' => $this->currency,
            'pp' => $this->paymentPassword,
            'cp' => $this->getCustomParamsString(),
        ]);
    }

    /**
     * @param string $signature
     * @param array  $params
     *
     * @return string
     */
    private function getSignatureHash($signature, array $params = [])
    {
        if (!array_search($this->hashAlgo, hash_algos(), true)) {
            throw new UnsupportedHashAlgorithmException(
                $this, sprintf('Unsupported hash algorithm "%s".', $this->hashAlgo)
            );
        }

        if (empty($params)) {
            return hash($this->hashAlgo, $signature);
        } else {
            return hash($this->hashAlgo, $this->getSignatureValue($signature, $params));
        }
    }

    /**
     * @param string $signature
     * @param array  $params
     *
     * @return string
     */
    private function getSignatureValue($signature, array $params)
    {
        foreach ($params as $ph => $param) {
            $signature = str_replace(
                ['{:' . $ph . '}', '{' . $ph . '}'],
                [$param ? ':' . $param : '', $param],
                $signature
            );
        }
        return $signature;
    }

    /**
     * @return string
     */
    public function getMerchantLogin()
    {
        return $this->merchantLogin;
    }

    /**
     * @param string $merchantLogin
     *
     * @return Payment
     */
    public function setMerchantLogin($merchantLogin)
    {
        $this->merchantLogin = (string)$merchantLogin;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentPassword()
    {
        return $this->paymentPassword;
    }

    /**
     * @param string $paymentPassword
     *
     * @return Payment
     */
    public function setPaymentPassword($paymentPassword)
    {
        $this->paymentPassword = (string)$paymentPassword;

        return $this;
    }

    /**
     * @return string
     */
    public function getValidationPassword()
    {
        return $this->validationPassword;
    }

    /**
     * @param string $validationPassword
     *
     * @return Payment
     */
    public function setValidationPassword($validationPassword)
    {
        $this->validationPassword = (string)$validationPassword;

        return $this;
    }

    /**
     * @return float
     */
    public function getSum()
    {
        return $this->sum;
    }

    /**
     * @param float $sum
     *
     * @return Payment
     */
    public function setSum($sum)
    {
        if ($this->sum !== $sum) {
            $sum = number_format($sum, 2, '.', '');
            if ($sum <= 0) {
                throw new InvalidSumException($this);
            }

            $this->sum                = $sum;
            $this->isShopSumChanged   = true;
            $this->isClientSumChanged = true;
        }

        return $this;
    }

    /**
     * @return float
     */
    public function getShopSum()
    {
        if ($this->isShopSumChanged) {
            if ($this->shopCommission) {
                $this->shopSum = $this->calculateShopSum($this->sum, $this->paymentMethod);
            } else {
                $this->shopSum = $this->sum;
            }
            $this->isShopSumChanged = false;
        }

        return $this->shopSum;
    }

    /**
     * @return float
     */
    public function getClientSum()
    {
        if ($this->isClientSumChanged) {
            if ($this->shopCommission) {
                $this->clientSum = $this->sum;
            } else {
                $this->clientSum = $this->calculateClientSum($this->sum, $this->paymentMethod);
            }
            $this->isClientSumChanged = false;
        }

        return $this->clientSum;
    }

    /**
     * @return bool
     */
    public function isShopCommission()
    {
        return $this->shopCommission;
    }

    /**
     * @param bool $shopCommission
     *
     * @return Payment
     */
    public function setShopCommission($shopCommission)
    {
        $shopCommission = (bool)$shopCommission;
        if ($this->shopCommission !== $shopCommission) {
            $this->shopCommission     = $shopCommission;
            $this->isShopSumChanged   = true;
            $this->isClientSumChanged = true;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    /**
     * @param int $invId
     *
     * @return Payment
     */
    public function setInvoiceId($invId)
    {
        if ($invId < 0) {
            throw new InvalidInvoiceIdException($this);
        }

        $this->invoiceId = (int)$invId;

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return Payment
     */
    public function setDescription($description)
    {
        $this->description = (string)$description;

        return $this;
    }

    /**
     * @return string
     */
    public function getPaymentMethod()
    {
        return $this->paymentMethod;
    }

    /**
     * @param string $paymentMethod
     *
     * @return Payment
     */
    public function setPaymentMethod($paymentMethod)
    {
        if ($this->paymentMethod !== $paymentMethod) {
            $this->paymentMethod      = (string)$paymentMethod;
            $this->isShopSumChanged   = true;
            $this->isClientSumChanged = true;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCulture()
    {
        return $this->culture;
    }

    /**
     * @param string $culture
     *
     * @return Payment
     */
    public function setCulture($culture)
    {
        $lcCulture = strtolower($culture);
        if (self::CULTURE_EN !== $lcCulture && self::CULTURE_RU !== $lcCulture) {
            throw new InvalidCultureException($this, sprintf('Unsupported culture "%s".', $culture));
        }
        $this->culture = $lcCulture;

        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param string $encoding
     *
     * @return Payment
     */
    public function setEncoding($encoding)
    {
        $this->encoding = (string)$encoding;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     *
     * @return Payment
     */
    public function setEmail($email)
    {
        $this->email = (string)$email;

        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getExpirationDate()
    {
        return $this->expirationDate;
    }

    /**
     * @param \DateTime|string|null $expirationDate
     * @param string                $format
     *
     * @return Payment
     */
    public function setExpirationDate($expirationDate, $format = '')
    {
        try {
            if (is_string($expirationDate) && $format) {
                $date = \DateTime::createFromFormat($format, $expirationDate);
            } else if (is_string($expirationDate) && !$format) {
                $date = new \DateTime($expirationDate);
            } else {
                $date = $expirationDate;
            }
        } catch (\Exception $e) {
            throw new InvalidExpirationDateException($this, $e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $date && !($date instanceof \DateTime)) {
            throw new InvalidExpirationDateException($this);
        }

        $this->expirationDate = $date;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     *
     * @return Payment
     */
    public function setCurrency($currency)
    {
        $this->currency = (string)$currency;

        return $this;
    }

    /**
     * @return string
     */
    public function getHashAlgo()
    {
        return $this->hashAlgo;
    }

    /**
     * @param string $hashAlgo
     *
     * @return Payment
     */
    public function setHashAlgo($hashAlgo)
    {
        $this->hashAlgo = (string)$hashAlgo;

        return $this;
    }

    /**
     * @return bool
     */
    public function isTest()
    {
        return $this->isTest;
    }

    /**
     * @param bool $isTest
     *
     * @return Payment
     */
    public function setIsTest($isTest)
    {
        $this->isTest = (bool)$isTest;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormType()
    {
        return $this->formType;
    }

    /**
     * @param string $formType
     *
     * @return Payment
     */
    public function setFormType($formType)
    {
        $this->formType = strtoupper($formType);

        return $this;
    }

    /**
     * @return string
     */
    public function getRequestMethod()
    {
        return $this->requestMethod;
    }

    /**
     * @param string $requestMethod
     *
     * @return Payment
     */
    public function setRequestMethod($requestMethod)
    {
        $lcRequestMethod = strtolower($requestMethod);
        if (self::REQUEST_METHOD_GET !== $lcRequestMethod && self::REQUEST_METHOD_POST !== $lcRequestMethod) {
            throw new UnsupportedRequestMethodException(
                $this, sprintf('Unsupported request method "%s".', $requestMethod)
            );
        }

        $this->requestMethod = $lcRequestMethod;

        return $this;
    }

    /**
     * @return bool
     */
    public function isThrowExceptions()
    {
        return $this->throwExceptions;
    }

    /**
     * @param bool $throwExceptions
     *
     * @return Payment
     */
    public function setThrowExceptions($throwExceptions)
    {
        $this->throwExceptions = $throwExceptions;

        return $this;
    }

//    /**
//     * @return bool
//     */
//    public function isRecurring()
//    {
//        return $this->recurring;
//    }
//
//    /**
//     * @param bool $recurring
//     *
//     * @return Payment
//     */
//    public function setRecurring($recurring)
//    {
//        $this->recurring = (bool)$recurring;
//
//        return $this;
//    }
//
//    /**
//     * @return int
//     */
//    public function getPreviousInvoiceId()
//    {
//        return $this->previousInvoiceId;
//    }
//
//    /**
//     * @param int $previousInvoiceId
//     *
//     * @return Payment
//     */
//    public function setPreviousInvoiceId($previousInvoiceId)
//    {
//        $this->previousInvoiceId = (int)$previousInvoiceId;
//
//        return $this;
//    }

    /**
     * Returns whether the Robokassa query is valid.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * @return bool
     */
    public function hasError()
    {
        return (bool)$this->errorCode;
    }

    /**
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getErrorDescription()
    {
        return $this->errorDescription;
    }

    /**
     * @return \string[]
     */
    public function getCustomParams()
    {
        return $this->customParams;
    }

    /**
     * getCustomParams() alias
     *
     * @return \string[]
     * @see Payment::getCustomParams()
     */
    public function getShp()
    {
        return $this->getCustomParams();
    }

    /**
     * @param array $customParams
     *
     * @return Payment
     */
    public function setCustomParams(array $customParams)
    {
        $this->customParams = $customParams;

        return $this;
    }

    /**
     * setCustomParams() alias
     *
     * @param array $customParams
     *
     * @return Payment
     * @see Payment::setCustomParams()
     */
    public function setShp(array $customParams)
    {
        return $this->setCustomParams($customParams);
    }

    /**
     * @return bool
     */
    public function hasCustomParams()
    {
        return !empty($this->customParams);
    }

    /**
     * hasCustomParams() alias
     *
     * @return bool
     * @see Payment::hasCustomParams()
     */
    public function hasShp()
    {
        return $this->hasCustomParams();
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return Payment
     */
    public function setCustomParam($key, $value)
    {
        $this->customParams[(string)$key] = (string)$value;

        return $this;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasCustomParam($key)
    {
        return array_key_exists($key, $this->customParams);
    }

    /**
     * @param string     $key
     * @param null|mixed $default
     *
     * @return null|\string[]
     */
    public function getCustomParam($key, $default = null)
    {
        return array_key_exists($key, $this->customParams) ? $this->customParams[$key] : $default;
    }

    /**
     * @param string $key
     *
     * @return Payment
     */
    public function removeCustomParam($key)
    {
        if (array_key_exists($key, $this->customParams)) {
            unset($this->customParams[$key]);
        }

        return $this;
    }

    /**
     * Returns custom parameters for url query.
     *
     * @return \string[]
     */
    private function getCustomParamsArray()
    {
        $customParams = $this->customParams;
        ksort($customParams);
        foreach ($customParams as $key => $value) {
            $customParams[$this->customParamsPrefix . $key] = $value;
            unset($customParams[$key]);
        }
        return $customParams;
    }

    /**
     * Returns custom parameters for signature.
     *
     * @return string
     */
    private function getCustomParamsString()
    {
        $customParams = $this->getCustomParamsArray();
        foreach ($customParams as $key => $value) {
            $customParams[$key] = $key . '=' . $value;
        }
        return implode(':', $customParams);
    }

    /**
     * For support magic methods:
     *  * `$payment->getShpKey($default = null)`
     *  * `$payment->setShpKey($value)`
     *  * `$payment->hasShpKey()`
     *  * `$payment->removeShpKey()`
     */
    public function __call($name, $arguments)
    {
        if (strlen($name) > 6 && 0 === stripos($name, 'getshp')) {

            // $payment->getShpParam('default value');
            $method = 'getCustomParam';
            $arguments = [substr($name, 6), isset($arguments[0]) ? $arguments[0] : null];

        } else if (strlen($name) > 6 && 0 === stripos($name, 'setshp') && count($arguments) === 1) {

            // $payment->setShpParam('value');
            $method = 'setCustomParam';
            $arguments = [substr($name, 6), $arguments[0]];

        } else if (strlen($name) > 6 && 0 === stripos($name, 'hasshp')) {

            // $payment->hasShpParam();
            $method = 'hasCustomParam';
            $arguments = [substr($name, 6)];

        } else if (strlen($name) > 9 && 0 === stripos($name, 'removeshp')) {

            // $payment->removeShpParam();
            $method = 'removeCustomParam';
            $arguments = [substr($name, 9)];

        } else {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', get_class($this), $name));
        }

        return call_user_func_array([$this, $method], $arguments);
    }

    /**
     * Set any property which has `set` method from array.
     *
     * @param array $data
     *
     * @return Payment
     */
    public function set(array $data)
    {
        foreach ($data as $key => $value) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($value);
            } else if ($this->customParamsPrefix . $this->shopComissionCustomParamKey === $key) {
                $this->setShopCommission($value);
            } else if (0 === strpos($key, $this->customParamsPrefix)) {
                $this->setCustomParam(substr($key, 4), $value);
            }
        }

        return $this;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function validateResult(array $data)
    {
        return $this->validate($data, 'validation');
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function validateSuccess(array $data)
    {
        return $this->validate($data, 'payment');
    }

    /**
     * @return string
     */
    public function getSuccessAnswer()
    {
        return 'OK' . $this->getInvoiceId() . "\n";
    }

    /**
     * @param array  $data
     * @param string $passwordType
     *
     * @return bool
     */
    private function validate(array $data, $passwordType)
    {
        if (!isset($data['InvId'], $data['OutSum'], $data['SignatureValue'])) {
            return false;
        }

        $this->set($data);
        $this->setInvoiceId($data['InvId']);

        $this->valid = $data['SignatureValue'] === $this->getSignatureHash('{os}:{ii}:{pp}{:cp}', [
            'os' => $data['OutSum'],
            'ii' => $data['InvId'],
            'pp' => $this->{$passwordType . 'Password'},
            'cp' => $this->getCustomParamsString(),
        ]);

        if ($this->valid) {
            try {
                $inv = $this->getInvoice();
                $this->valid = $inv
                    && $data['OutSum'] == $inv->getShopSum()
                    && $inv->getStateCode() === Invoice::STATE_COMPLETED;

                if ($this->valid) {
                    $this->paymentMethod = $inv->getPaymentMethod();
                    $this->clientSum = $inv->getClientSum();
                    $this->shopSum = $inv->getShopSum();
                }
            } catch (InvoiceNotFoundException $e) {
                $this->valid = false;
            }
        }

        return $this->valid;
    }

    /**
     * Returns the list of currencies available to pay for the orders from a particular store/website.
     *
     * @return array
     */
    public function getCurrencies()
    {

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'GetCurrencies',
            ['MerchantLogin' => $this->merchantLogin, 'Language' => $this->culture],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        if ($this->parseError($sxe)) {
            return null;
        }

        if (!$sxe->Groups->Group) {
            return [];
        }
        $groups = [];
        foreach ($sxe->Groups->Group as $i => $group) {
            $items = [];
            foreach ($group->Items->Currency as $item) {
                $item = (array)$item;
                $items[] = $item['@attributes'];
            }
            $groups[] = [
                'Code'        => (string)$group->attributes()->Code,
                'Description' => (string)$group->attributes()->Description,
                'Items'       => $items,
            ];
        }
        return $groups;
    }

    /**
     * Returns the list of available payment method groups.
     *
     * @return \string[]
     */
    public function getPaymentMethodGroups()
    {
        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'GetPaymentMethods',
            ['MerchantLogin' => $this->merchantLogin, 'Language' => $this->culture],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        if ($this->parseError($sxe)) {
            return null;
        }

        if (!$sxe->Methods->Method) {
            return [];
        }
        $methods = [];
        foreach ($sxe->Methods->Method as $method) {
            $methods[(string)$method->attributes()->Code] = (string)$method->attributes()->Description;
        }
        return $methods;
    }

    /**
     * Returns the sums with comission and some addition data.
     *
     * @param float       $shopSum
     * @param string      $paymentMethod
     * @param string|null $culture
     *
     * @return array
     */
    public function getRates($shopSum, $paymentMethod = '', $culture = null)
    {
        if (null === $culture) {
            $culture = $this->culture;
        }

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'GetRates',
            [
                'MerchantLogin' => $this->merchantLogin,
                'IncCurrLabel'  => $paymentMethod,
                'OutSum'        => $shopSum,
                'Language'      => $culture
            ],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        if ($this->parseError($sxe)) {
            return null;
        }

        if (!$sxe->Groups->Group) {
            return [];
        }
        $groups = [];
        foreach ($sxe->Groups->Group as $i => $group) {
            $items = [];
            foreach ($group->Items->Currency as $item) {
                $rate = (double)$item->Rate->attributes()->IncSum;
                $item = (array)$item;
                $items[] = $item['@attributes'] + ['Rate' => $rate];
            }
            $groups[] = [
                'Code'        => (string)$group->attributes()->Code,
                'Description' => (string)$group->attributes()->Description,
                'Items'       => $items,
            ];
        }

        return $groups;
    }

    /**
     * Returns the sum with comission for `$paymentMethod`.
     *
     * Helps calculate the amount receivable on the basis of ROBOKASSA
     * prevailing exchange rates from the amount payable by the user.
     *
     * @param double $clientSum
     * @param string $paymentMethod
     *
     * @return double
     * @throws CalculateSumErrorException If `$paymentMethod` not found and `Payment::isThrowExceptions()` is `true`.
     */
    public function calculateShopSum($clientSum, $paymentMethod)
    {
        if (!$paymentMethod) {
            throw new EmptyPaymentMethodException($this);
        }

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'CalcOutSumm',
            ['MerchantLogin' => $this->merchantLogin, 'IncCurrLabel' => $paymentMethod, 'IncSum' => $clientSum],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        if ($this->parseError($sxe)) {
            return null;
        }

        return (double)$sxe->OutSum;
    }

    /**
     * Returns the sum without comission for `$paymentMethod`.

     * Helps calculate the amount payable by the buyer including ROBOKASSA’s
     * charge (according to the service plan) and charges of other systems
     * through which the buyer decided to pay for the order.
     *
     * @param double $shopSum
     * @param string $paymentMethod
     *
     * @return double
     * @throws CalculateSumErrorException If `$paymentMethod` not found and `Payment::isThrowExceptions()` is `true`.
     */
    public function calculateClientSum($shopSum, $paymentMethod)
    {
        if (!$paymentMethod) {
            throw new EmptyPaymentMethodException($this);
        }

        $rates = $this->getRates($shopSum, $paymentMethod);

        if (empty($rates)) {
            // for the same behaviour as calculateShopSum()
            $this->errorCode = CalculateSumErrorException::ERR_CODE;
            $this->errorDescription = CalculateSumErrorException::$msg[$this->culture];
            if ($this->throwExceptions) {
                throw new CalculateSumErrorException($this);
            }
            return null;
        } else {
            return $rates[0]['Items'][0]['Rate'];
        }
    }

    /**
     * Returns detailed information on the current status and payment details.
     *
     * Please bear in mind that the transaction is initiated not when the
     * user is redirected to the payment page, but later – once his payment
     * details are confirmed, i.e. you may well see no transaction, which
     * you believe should have been started already.
     *
     * Returns `null` if invoice is not found and `Payment::isThrowExceptions()` is `false`.
     *
     * @param null|int $invoiceId Invoice ID.
     *
     * @return Invoice|null
     * @throws InvoiceNotFoundException If invoice is not found and `Payment::isThrowExceptions()` is `true`.
     */
    public function getInvoice($invoiceId = null)
    {
        if (null === $invoiceId) {
            $invoiceId = $this->invoiceId;
        }

        $signature = $this->getSignatureHash('{ml}:{ii}:{vp}', [
            'ml' => $this->merchantLogin,
            'ii' => $invoiceId,
            'vp' => $this->validationPassword,
        ]);

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'OpState',
            ['MerchantLogin' => $this->merchantLogin, 'InvoiceID' => $invoiceId, 'Signature' => $signature],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        if ($this->parseError($sxe)) {
            return null;
        }

        return new Invoice($this->invoiceId, $this->culture, $sxe);
    }

    private function parseError(\SimpleXMLElement $sxe)
    {
        $this->errorCode = (int)$sxe->Result->Code;
        $this->errorDescription = (string)$sxe->Result->Description;

        if ($this->hasError() && $this->throwExceptions) {
            switch ($this->errorCode) {
                case InvoiceNotFoundException::ERR_CODE:
                    throw new InvoiceNotFoundException($this, $this->errorDescription);
                case CalculateSumErrorException::ERR_CODE:
                    throw new CalculateSumErrorException($this, $this->errorDescription);
                default:
                    throw new ResponseErrorException($this, $this->errorDescription, $this->errorCode);
            }
        }

        return $this->hasError();
    }

    public function sendRequest($url, array $params, $method)
    {
        $lcMethod = strtolower($method);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 20,
        ];

        if (Payment::REQUEST_METHOD_GET === $lcMethod) {
            $url .= '?' . http_build_query($params);
        } else {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);

        if (!$response) {
            throw new \RuntimeException('cURL: ' . curl_error($ch), curl_errno($ch));
        }
        curl_close($ch);

        return $response;
    }
}