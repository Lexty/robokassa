<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

use Lexty\Robokassa\Exception\CalculateSumErrorException;
use Lexty\Robokassa\Exception\EmptyPaymentMethodException;
use Lexty\Robokassa\Exception\InvalidCultureException;
use Lexty\Robokassa\Exception\InvoiceNotFoundException;
use Lexty\Robokassa\Exception\ResponseErrorException;
use Lexty\Robokassa\Exception\UnsupportedRequestMethodException;

/**
 * Robokassa API client.
 */
class Client
{
    const CULTURE_EN = 'en';
    const CULTURE_RU = 'ru';

    const REQUEST_METHOD_GET  = 'get';
    const REQUEST_METHOD_POST = 'post';

    /**
     * @var Auth
     */
    private $auth;
    /**
     * Language of the interface.
     *
     * @var string
     */
    private $culture = self::CULTURE_RU;
    /**
     * @var string
     */
    private $requestMethod = self::REQUEST_METHOD_GET;

    private $formBaseUrl    = 'https://auth.robokassa.ru/Merchant/PaymentForm/Form';
    private $paymentBaseUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
    private $serviceBaseUrl = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/';
//    private $recurringBaseUrl = 'https://auth.robokassa.ru/Merchant/Recurring';

    /**
     * Client constructor.
     *
     * @param Auth $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
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
     * @return Client
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
        if (self::REQUEST_METHOD_GET !== $lcRequestMethod
            && self::REQUEST_METHOD_POST !== $lcRequestMethod
        ) {
            throw new UnsupportedRequestMethodException(
                sprintf('Unsupported request method "%s".', $requestMethod)
            );
        }

        $this->requestMethod = $lcRequestMethod;

        return $this;
    }

    /**
     * @return string
     */
    public function getFormBaseUrl()
    {
        return $this->formBaseUrl;
    }

    /**
     * @return string
     */
    public function getPaymentBaseUrl()
    {
        return $this->paymentBaseUrl;
    }

    /**
     * Set any property which has setter method from array.
     *
     * @param array $data
     *
     * @return Auth
     */
    public function set(array $data)
    {
        foreach ($data as $key => $value) {
            if (method_exists($this, 'set' . $key)) {
                $this->{'set' . $key}($value);
            }
        }

        return $this;
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
            ['MerchantLogin' => $this->auth->getMerchantLogin(), 'Language' => $this->culture],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        $this->parseError($sxe);

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
            ['MerchantLogin' => $this->auth->getMerchantLogin(), 'Language' => $this->culture],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        $this->parseError($sxe);

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
     * Returns the sums with commission and some addition data.
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
                'MerchantLogin' => $this->auth->getMerchantLogin(),
                'IncCurrLabel'  => $paymentMethod,
                'OutSum'        => $shopSum,
                'Language'      => $culture
            ],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        $this->parseError($sxe);

        if (!$sxe->Groups->Group) {
            return [];
        }
        $groups = [];
        foreach ($sxe->Groups->Group as $i => $group) {
            $items = [];
            foreach ($group->Items->Currency as $item) {
                $clientSum = (double)$item->Rate->attributes()->IncSum;
                $item = (array)$item;
                $items[] = $item['@attributes'] + ['ClientSum' => $clientSum];
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
     * Returns the sum with commission for `$paymentMethod`.
     *
     * Helps calculate the amount receivable on the basis of ROBOKASSA
     * prevailing exchange rates from the amount payable by the user.
     *
     * @param float  $clientSum
     * @param string $paymentMethod
     *
     * @return float
     * @throws EmptyPaymentMethodException If `$paymentMethod` is empty.
     * @throws CalculateSumErrorException  If `$paymentMethod` not found.
     */
    public function calculateShopSum($clientSum, $paymentMethod)
    {
        if (!$paymentMethod) {
            throw new EmptyPaymentMethodException();
        }

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'CalcOutSumm',
            [
                'MerchantLogin' => $this->auth->getMerchantLogin(),
                'IncCurrLabel'  => $paymentMethod,
                'IncSum'        => $clientSum
            ],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        $this->parseError($sxe);

        return (float)$sxe->OutSum;
    }

    /**
     * Returns the sum without commission for `$paymentMethod`.

     * Helps calculate the amount payable by the buyer including ROBOKASSA’s
     * charge (according to the service plan) and charges of other systems
     * through which the buyer decided to pay for the order.
     *
     * @param float  $shopSum
     * @param string $paymentMethod
     *
     * @return float
     * @throws EmptyPaymentMethodException If `$paymentMethod` is empty.
     * @throws CalculateSumErrorException  If `$paymentMethod` not found.
     */
    public function calculateClientSum($shopSum, $paymentMethod)
    {
        if (!$paymentMethod) {
            throw new EmptyPaymentMethodException();
        }

        $rates = $this->getRates($shopSum, $paymentMethod);

        if (empty($rates)) {
            // for the same behaviour as calculateShopSum()
            throw new CalculateSumErrorException(CalculateSumErrorException::$msg[$this->getCulture()]);
        } else {
            return $rates[0]['Items'][0]['ClientSum'];
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
     * @throws InvoiceNotFoundException If invoice is not found.
     */
    public function getInvoice($invoiceId = null)
    {

        $signature = $this->auth->getSignatureHash('{ml}:{ii}:{vp}', [
            'ml' => $this->auth->getMerchantLogin(),
            'ii' => $invoiceId,
            'vp' => $this->auth->getValidationPassword(),
        ]);

        $response = $this->sendRequest(
            $this->serviceBaseUrl . 'OpState',
            [
                'MerchantLogin' => $this->auth->getMerchantLogin(),
                'InvoiceID'     => $invoiceId,
                'Signature'     => $signature
            ],
            $this->requestMethod
        );

        $sxe = simplexml_load_string($response);
        $this->parseError($sxe);
        return [
            'InvoiceId'                => (int)$invoiceId,
            'StateCode'                => (int)$sxe->State->Code,
            'RequestDate'              => new \DateTime((string)$sxe->State->RequestDate),
            'StateDate'                => new \DateTime((string)$sxe->State->StateDate),
            'PaymentMethod'            => (string)$sxe->Info->IncCurrLabel,
            'ClientSum'                => (float)$sxe->Info->IncSum,
            'ClientAccount'            => (string)$sxe->Info->IncAccount,
            'PaymentMethodCode'        => (string)$sxe->Info->PaymentMethod->Code,
            'PaymentMethodDescription' => (string)$sxe->Info->PaymentMethod->Description,
            'Currency'                 => (string)$sxe->Info->OutCurrLabel,
            'ShopSum'                  => (float)$sxe->Info->OutSum,
        ];
    }

    private function parseError(\SimpleXMLElement $sxe)
    {
        $code = (int)$sxe->Result->Code;
        $descr = (string)$sxe->Result->Description;

        if ($code > 0) {
            switch ((int)$sxe->Result->Code) {
                case InvoiceNotFoundException::ERR_CODE:
                    throw new InvoiceNotFoundException($descr);
                case CalculateSumErrorException::ERR_CODE:
                    throw new CalculateSumErrorException($descr);
                default:
                    throw new ResponseErrorException($descr, $code);
            }
        }
    }

    /**
     * Send request and return response.
     *
     * Protected for phpUnit mocking.
     *
     * @param string $url    URL
     * @param array  $params `GET` or `POST` parameters.
     * @param string $method `GET` or `POST` method.
     *
     * @return string
     */
    protected function sendRequest($url, array $params, $method)
    {
        $method = strtolower($method);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
        ];

        if (self::REQUEST_METHOD_GET === $method) {
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
