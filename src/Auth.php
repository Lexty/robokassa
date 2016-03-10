<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

use Lexty\Robokassa\Exception\UnsupportedHashAlgorithmException;

/**
 * Authenticate credentials.
 */
class Auth
{
    const HASH_ALGO_MD5 = 'md5';

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
     * @var bool
     */
    private $isTest;
    /**
     * @var string
     */
    private $hashAlgo = self::HASH_ALGO_MD5;

    /**
     * Auth constructor.
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
        $this->setTest($isTest);
    }

    /**
     * @param string $signature
     * @param array  $params
     *
     * @return string
     */
    public function getSignatureHash($signature, array $params = [])
    {
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
    public function getSignatureValue($signature, array $params)
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
     * @return Auth
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
     * @return Auth
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
     * @return Auth
     */
    public function setValidationPassword($validationPassword)
    {
        $this->validationPassword = (string)$validationPassword;

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
     * @return Auth
     */
    public function setTest($isTest)
    {
        $this->isTest = (bool)$isTest;

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
     * @return Auth
     */
    public function setHashAlgo($hashAlgo)
    {
        if (!array_search((string)$hashAlgo, hash_algos(), true)) {
            throw new UnsupportedHashAlgorithmException(
                sprintf('Unsupported hash algorithm "%s".', $this->hashAlgo)
            );
        }

        $this->hashAlgo = (string)$hashAlgo;

        return $this;
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
}
