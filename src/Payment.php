<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

use Lexty\Robokassa\Exception\EmptyDescriptionException;
use Lexty\Robokassa\Exception\EmptySumException;
use Lexty\Robokassa\Exception\InvalidCultureException;
use Lexty\Robokassa\Exception\InvalidExpirationDateException;
use Lexty\Robokassa\Exception\InvalidInvoiceIdException;
use Lexty\Robokassa\Exception\InvalidSumException;
use Lexty\Robokassa\Exception\InvoiceNotFoundException;

/**
 * The payment.
 *
 * @method string  getHashAlgo()
 * @method Payment setHashAlgo(string $algo)
 * @method string  getMerchantLogin()
 * @method Payment setMerchantLogin(string $login)
 * @method string  getPaymentPassword()
 * @method Payment setPaymentPassword(string $passwrod)
 * @method string  getValidationPassword()
 * @method Payment setValidationPassword(string $passwrod)
 * @method bool    isTest()
 * @method Payment setTest(bool $test)
 * @method string  getRequestMethod()
 * @method Payment setRequestMethod(string $method)
 */
class Payment
{
    const CULTURE_EN = 'en';
    const CULTURE_RU = 'ru';

    const FORM_TYPE_M   = 'M';
    const FORM_TYPE_MS  = 'MS';
    const FORM_TYPE_S   = 'S';
    const FORM_TYPE_SS  = 'SS';
    const FORM_TYPE_L   = 'L';
    const FORM_TYPE_V   = 'V';
    const FORM_TYPE_FL  = 'FL';
    const FORM_TYPE_FLS = 'FLS';

    const STATE_NEW        = 0;
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
        self::CULTURE_EN => [
            self::STATE_NEW        => '',
            self::STATE_INITIATED  => 'Initiated, payment is not received by the service.',
            self::STATE_CANCELED   => 'Payment was not received, operation canceled.',
            self::STATE_PROCESSING => 'Payment received, payment is transferred to the shop account.',
            self::STATE_RETURNED   => 'Payment was returned to buyer after it was received.',
            self::STATE_SUSPENDED  => 'Operation execution is suspended.',
            self::STATE_COMPLETED  => 'Operation completed successfully.',
        ],
        self::CULTURE_RU => [
            self::STATE_NEW        => '',
            self::STATE_INITIATED  => 'Операция только инициализирована, деньги от покупателя не получены.',
            self::STATE_CANCELED   => 'Операция отменена, деньги от покупателя не были получены.',
            self::STATE_PROCESSING => 'Деньги от покупателя получены, производится зачисление денег на счет магазина.',
            self::STATE_RETURNED   => 'Деньги после получения были возвращены покупателю.',
            self::STATE_SUSPENDED  => 'Исполнение операции приостановлено.',
            self::STATE_COMPLETED  => 'Операция выполнена, завершена успешно.',
        ],
    ];

    /**
     * @var Auth
     */
    private $auth;
    /**
     * @var Client
     */
    private $client;

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
     * @var string
     */
    private $receipt;
    /**
     * The sum paid by the client, in units of currency Payment::$incCurrLabel.
     *
     * @var float
     */
    private $clientSum;
    /**
     * @var int
     */
    private $id;
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
     * @var \string[]
     */
    private $customParams = [];
    /**
     * @var int
     */
    private $stateCode = self::STATE_NEW;
    /**
     * @var null|\DateTime
     */
    private $requestDate;
    /**
     * @var null|\DateTime
     */
    private $stateDate;
    /**
     * @var string
     */
    private $clientAccount;
    /**
     * @var string
     */
    private $paymentMethodCode;
    /**
     * @var string
     */
    private $paymentMethodDescription;
    /**
     * @var bool
     */
    private $valid = false;

    private $isShopSumChanged   = false;
    private $isClientSumChanged = false;

    private $customParamsPrefix = 'Shp_';
    private $shopCommissionCustomParamKey = '_shop_commission';

    /**
     * Payment constructor.
     *
     * @param Auth   $auth   Authenticate credentials.
     * @param Client $client Robokassa API client.
     */
    public function __construct(Auth $auth, Client $client = null)
    {
        if (null === $client) {
            $client = new Client($auth);
        }

        $this->auth = $auth;
        $this->client = $client;
        $this->culture = $client->getCulture();
    }

    /**
     * @param null|string $type Available values: `M`, `MS`, `S`, `SS`, `L`, `V`, `FL`, `FLS`.
     *
     * @return string
     *
     * @link http://docs.robokassa.ru/en#2537
     */
    public function getFormUrl($type = null)
    {
        $type = strtoupper($type);
        return $this->client->getFormBaseUrl() . $type . '.js?' . $this->getPaymentUrlQueryString(
            self::FORM_TYPE_FL === $type || self::FORM_TYPE_FLS === $type
        );
    }

    /**
     * @return string
     */
    public function getPaymentUrl()
    {
        return $this->client->getPaymentBaseUrl() . '?' . $this->getPaymentUrlQueryString();
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
            throw new EmptyDescriptionException();
        }

        $params = [
            'MerchantLogin'  => $this->auth->getMerchantLogin(),
            'Description'    => $this->description,
            'SignatureValue' => $this->getPaymentSignatureHash(),
            'Receipt' => $this->receipt,
        ];

        if ($defaultSum) {
            $params['DefaultSum'] = $this->sum;
        } else {
            $params['OutSum'] = $this->getShopSum();
        }

        if ($this->id) $params['InvId'] = $this->id;
        if ($this->culture)            $params['Culture']           = $this->culture;
        if ($this->encoding)           $params['Encoding']          = $this->encoding;
        if ($this->email)              $params['Email']             = $this->email;
        if ($this->expirationDate)     $params['ExpirationDate']    = $this->expirationDate->format('c');
        if ($this->currency)           $params['OutSumCurrency']    = $this->currency;
        if ($this->paymentMethod)      $params['IncCurrLabel']      = $this->paymentMethod;
        if ($this->auth->isTest())     $params['isTest']            = 1;
        if ($this->customParams)       $params += $this->getCustomParamsArray();

        return $params;
    }

    /**
     * @return string
     */
    public function getPaymentSignatureHash() {
        return $this->auth->getSignatureHash($this->getPaymentSignature());
    }

    /**
     * @return string
     */
    public function getPaymentSignature() {
        if (!$this->sum) {
            throw new EmptySumException();
        }

        return $this->auth->getSignatureValue('{ml}:{ss}:{ii}{:cr}{:rc}:{pp}{:cp}', [
            'ml' => $this->auth->getMerchantLogin(),
            'ss' => $this->getShopSum(),
            'ii' => $this->id,
            'cr' => $this->currency,
            'rc' => $this->receipt,
            'pp' => $this->auth->getPaymentPassword(),
            'cp' => $this->getCustomParamsString(),
        ]);
    }

    /**
     * Gets the authenticate credentials.
     *
     * @return Auth
     */
    public function getAuth()
    {
        return $this->auth;
    }

    /**
     * Gets the Robokassa API client.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
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
                throw new InvalidSumException();
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
                $this->shopSum = $this->client->calculateShopSum($this->sum, $this->paymentMethod);
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
                $this->clientSum = $this->client->calculateClientSum($this->sum, $this->paymentMethod);
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

            if ($shopCommission) {
                $this->setCustomParam($this->shopCommissionCustomParamKey, 1);
            } else {
                $this->removeCustomParam($this->shopCommissionCustomParamKey);
            }
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $invId
     *
     * @return Payment
     */
    public function setId($invId)
    {
        if ($invId < 0) {
            throw new InvalidInvoiceIdException();
        }

        $this->id = (int)$invId;

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
            throw new InvalidCultureException(sprintf('Unsupported culture "%s".', $culture));
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
            throw new InvalidExpirationDateException($e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $date && !($date instanceof \DateTime)) {
            throw new InvalidExpirationDateException();
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
     * @return null|\DateTime
     */
    public function getRequestDate()
    {
        return $this->requestDate;
    }

    /**
     * @return null|\DateTime
     */
    public function getStateDate()
    {
        return $this->stateDate;
    }

    /**
     * @return string
     */
    public function getClientAccount()
    {
        return $this->clientAccount;
    }

    /**
     * @return string
     */
    public function getPaymentMethodCode()
    {
        return $this->paymentMethodCode;
    }

    /**
     * @return string
     */
    public function getPaymentMethodDescription()
    {
        return $this->paymentMethodDescription;
    }

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
     * Json string
     *
     * Should be URL encoded!
     *
     * @param string $data
     * @return Payment
     */
    public function setReceipt(string $data)
    {
        $this->receipt = $data;

        return $this;
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

            $method = 'getCustomParam';
            $arguments = [substr($name, 6), isset($arguments[0]) ? $arguments[0] : null];

        } else if (strlen($name) > 6 && 0 === stripos($name, 'setshp') && count($arguments) === 1) {

            $method = 'setCustomParam';
            $arguments = [substr($name, 6), $arguments[0]];

        } else if (strlen($name) > 6 && 0 === stripos($name, 'hasshp')) {

            $method = 'hasCustomParam';
            $arguments = [substr($name, 6)];

        } else if (strlen($name) > 9 && 0 === stripos($name, 'removeshp')) {

            $method = 'removeCustomParam';
            $arguments = [substr($name, 9)];

        } else if (method_exists($this->auth, $name)) {

            $return = call_user_func_array([$this->auth, $name], $arguments);
            return $return === $this->auth ? $this : $return;

        } else if (method_exists($this->client, $name)) {

            $return = call_user_func_array([$this->client, $name], $arguments);
            return $return === $this->client ? $this : $return;

        } else {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', get_class($this), $name));
        }

        return call_user_func_array([$this, $method], $arguments);
    }

    /**
     * Set any property which has setter method from array.
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
            } else if ($this->customParamsPrefix . $this->shopCommissionCustomParamKey === $key) {
                $this->setShopCommission($value);
            } else if (0 === strpos($key, $this->customParamsPrefix)) {
                $this->setCustomParam(substr($key, 4), $value);
            }
        }

        $this->auth->set($data);
        $this->client->set($data);

        return $this;
    }

    /**
     * @param array $data   Request data.
     * @param bool  $strict Addition check invoice existence and its state.
     *
     * @return bool
     */
    public function validateResult(array $data, $strict = true)
    {
        return $this->validate($data, $strict, 'validation');
    }

    /**
     * @param array $data   Request data.
     * @param bool  $strict Addition check invoice existence and its state.
     *
     * @return bool
     */
    public function validateSuccess(array $data, $strict = true)
    {
        return $this->validate($data, $strict, 'payment');
    }

    /**
     * @return string
     */
    public function getSuccessAnswer()
    {
        return 'OK' . $this->getId() . "\n";
    }

    /**
     * @param array  $data
     * @param bool   $strict
     * @param string $passwordType
     *
     * @return bool
     */
    private function validate(array $data, $strict, $passwordType)
    {
        if (!isset($data['InvId'], $data['OutSum'], $data['SignatureValue'])) {
            return false;
        }

        $this->set($data);
        $this->setId($data['InvId']);

        $this->valid = strcasecmp($data['SignatureValue'], $this->auth->getSignatureHash(
            '{os}:{ii}:{pp}{:cp}',
            [
                'os' => $data['OutSum'],
                'ii' => $data['InvId'],
                'pp' => $this->auth->{'get' . $passwordType . 'Password'}(),
                'cp' => $this->getCustomParamsString(),
            ]
        )) === 0;

        if ($this->valid && $strict) {
            try {
                $this->fetch();
                $this->valid = $data['OutSum'] == $this->shopSum
                               && Payment::STATE_COMPLETED === $this->stateCode;
            } catch (InvoiceNotFoundException $e) {
                $this->valid = false;
            }
        }

        return $this->valid;
    }

    /**
     * @return Payment
     */
    public function fetch()
    {
        $invoice = $this->client->getInvoice($this->id);

        $this->stateCode                = $invoice['StateCode'];
        $this->requestDate              = $invoice['RequestDate'];
        $this->stateDate                = $invoice['StateDate'];
        $this->paymentMethod            = $invoice['PaymentMethod'];
        $this->clientSum                = $invoice['ClientSum'];
        $this->clientAccount            = $invoice['ClientAccount'];
        $this->paymentMethodCode        = $invoice['PaymentMethodCode'];
        $this->paymentMethodDescription = $invoice['PaymentMethodDescription'];
        $this->currency                 = $invoice['Currency'];
        $this->shopSum                  = $invoice['ShopSum'];

        $this->sum = $this->shopCommission ? $this->clientSum : $this->shopSum;
        $this->isShopSumChanged   = false;
        $this->isClientSumChanged = false;

        return $this;
    }
}
