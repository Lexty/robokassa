<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa;

class PaymentTest extends \PHPUnit_Framework_TestCase
{
    private $login = 'login';
    private $pass1 = 'pass1';
    private $pass2 = 'pass2';
    private $isTest = true;

    private function createPayment()
    {
        return new Payment($this->login, $this->pass1, $this->pass2, $this->isTest);
    }

    private function getPropertyValue($object, $property)
    {
        $objectReflection = new \ReflectionObject($object);
        $propertyReflection = $objectReflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        return $propertyReflection->getValue($object);
    }

    /**
     * @test
     */
    public function manage_custom_params_by_magic_methods()
    {
        $payment = $this->createPayment();

        $this->assertFalse($payment->hasShpUserId());
        $payment->setShpUserId(156);
        $this->assertTrue($payment->hasShpUserId());
        $this->assertEquals(156, $payment->getShpUserId());
        $payment->removeShpUserId();
        $this->assertFalse($payment->hasShpUserId());
        $this->assertNull($payment->getShpUserId());
        $this->assertEquals('default', $payment->getShpUserId('default'));
    }

    /**
     * @test
     * @covers Payment::getPaymentSignatureHash()
     */
    public function get_payment_signature_hash()
    {
        $this->assertEquals('864798f31e43ac88bea969e54608a54a', $this->createPayment()
                ->setSum(123)
                ->setInvoiceId(6544321)
                ->setCurrency('USD')
                ->setCustomParam('surname', 'Smith')
                ->setCustomParam('name', 'John')
                ->getPaymentSignatureHash()
        );
    }

    /**
     * @test
     * @covers Payment::getPaymentSignatureHash()
     * @expectedException \Lexty\Robokassa\Exception\UnsupportedHashAlgorithmException
     */
    public function get_payment_signature_hash_with_invalid_algorithm()
    {
        $this->assertEquals('864798f31e43ac88bea969e54608a54a', $this->createPayment()
            ->setSum(123)
            ->setHashAlgo('invalid')
            ->getPaymentSignatureHash()
        );
    }

    /**
     * @test
     * @covers Payment::getPaymentSignature()
     */
    public function get_correct_payment_signatures()
    {
        $this->assertEquals("login:123.00::{$this->pass1}",
            $this->createPayment()
                ->setSum(123)
                ->getPaymentSignature()
        );
        $this->assertEquals("login:123.00:6544321:{$this->pass1}",
            $this->createPayment()
                ->setSum(123)
                ->setInvoiceId(6544321)
                ->getPaymentSignature()
        );
        $this->assertEquals("login:123.00:6544321:USD:{$this->pass1}",
            $this->createPayment()
                ->setSum(123)
                ->setInvoiceId(6544321)
                ->setCurrency('USD')
                ->getPaymentSignature()
        );
        $this->assertEquals("login:123.00:6544321:USD:{$this->pass1}:Shp_name=John:Shp_surname=Smith",
            $this->createPayment()
                ->setSum(123)
                ->setInvoiceId(6544321)
                ->setCurrency('USD')
                ->setCustomParam('surname', 'Smith')
                ->setCustomParam('name', 'John')
                ->getPaymentSignature()
        );
    }

    /**
     * @test
     * @covers Payment::getPaymentSignature()
     * @expectedException \Lexty\Robokassa\Exception\EmptySumException
     */
    public function get_payment_signature_without_sum()
    {
        $this->createPayment()->getPaymentSignature();
    }

    /**
     * @test
     * @covers Payment::getPaymentUrl()
     */
    public function get_payment_url()
    {
        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=login&Description=Payment+description' .
            '&SignatureValue=36a95458024a31d5e8db246f384527d8&OutSum=150.00&InvId=53&Culture=en&Encoding=utf-8' .
            '&Email=mail%40example.org&ExpirationDate=2016-01-29T15%3A18%3A20%2B00%3A00&OutSumCurrency=USD' .
            '&IncCurrLabel=BankCard&isTest=1&Shp_bar=bar+value&Shp_foo=foo+value',
            $this->createPayment()
                ->setInvoiceId(53)
                ->setSum(150)
                ->setDescription('Payment description')
                ->setCulture(Payment::CULTURE_EN)
                ->setEncoding('utf-8')
                ->setEmail('mail@example.org')
                ->setExpirationDate('2016-01-29T15:18:20+00:00')
                ->setCurrency('USD')
                ->setPaymentMethod('BankCard')
                ->setIsTest(true)
                ->setCustomParam('foo', 'foo value')
                ->setCustomParam('bar', 'bar value')
                ->getPaymentUrl());
    }

    /**
     * @test
     * @covers Payment::getPaymentUrl()
     * @expectedException \Lexty\Robokassa\Exception\EmptyDescriptionException
     */
    public function get_payment_url_with_empty_description()
    {
        $this->createPayment()->getPaymentUrl();
    }

    /**
     * @test
     * @covers Payment::getPaymentUrl()
     * @expectedException \Lexty\Robokassa\Exception\EmptySumException
     */
    public function get_payment_url_with_empty_sum()
    {
        $this->createPayment()->setDescription('foo')->getPaymentUrl();
    }

    /**
     * @test
     * @covers Payment::set()
     */
    public function set_general_properties_from_array()
    {
        $data = [
            'merchantLogin'      => 'customLogin',
            'paymentPassword'    => 'password1',
            'validationPassword' => 'password2',
            'sum'                => '100500',
            'shopCommission'     => true,
            'invoiceId'          => 123,
            'description'        => 'Payment description.',
            'paymentMethod'      => 'BankCard',
            'culture'            => 'en',
            'encoding'           => 'utf-16',
            'email'              => 'mail@example.org',
            'expirationDate'     => '2016-01-29T15:18:20+00:00',
            'currency'           => 'RUB',
            'hashAlgo'           => 'md5',
            'isTest'             => true,
            'formType'           => 'FL',
            'requestMethod'      => 'post',
            'throwExceptions'    => false,
        ];
        $payment = $this->createPayment()->set($data);

        $this->assertEquals($data['merchantLogin'], $payment->getMerchantLogin());
        $this->assertEquals($data['paymentPassword'], $payment->getPaymentPassword());
        $this->assertEquals($data['validationPassword'], $payment->getValidationPassword());
        $this->assertEquals($data['sum'], $payment->getSum());
        $this->assertEquals($data['shopCommission'], $payment->isShopCommission());
        $this->assertEquals($data['invoiceId'], $payment->getInvoiceId());
        $this->assertEquals($data['description'], $payment->getDescription());
        $this->assertEquals($data['paymentMethod'], $payment->getPaymentMethod());
        $this->assertEquals($data['culture'], $payment->getCulture());
        $this->assertEquals($data['encoding'], $payment->getEncoding());
        $this->assertEquals($data['email'], $payment->getEmail());
        $this->assertEquals($data['expirationDate'], $payment->getExpirationDate()->format('c'));
        $this->assertEquals($data['currency'], $payment->getCurrency());
        $this->assertEquals($data['hashAlgo'], $payment->getHashAlgo());
        $this->assertEquals($data['isTest'], $payment->isTest());
        $this->assertEquals($data['formType'], $payment->getFormType());
        $this->assertEquals($data['requestMethod'], $payment->getRequestMethod());
        $this->assertEquals($data['throwExceptions'], $payment->isThrowExceptions());
    }

    /**
     * @test
     * @covers Payment::set()
     */
    public function set_custom_params_from_array_by_set_method()
    {
        $payment = $this->createPayment();

        $prefix = $this->getPropertyValue($payment, 'customParamsPrefix');

        $payment->set([
            "{$prefix}user_id" => 456,
            "{$prefix}login"   => 'vasya',
        ]);

        $this->assertEquals(456, $payment->getCustomParam('user_id'));
        $this->assertEquals('vasya', $payment->getCustomParam('login'));
    }

    /**
     * @test
     * @covers Payment::set()
     */
    public function set_shop_comission_from_array_by_custom_param_in_set_method()
    {
        $payment = $this->createPayment();

        $prefix = $this->getPropertyValue($payment, 'customParamsPrefix');
        $key = $this->getPropertyValue($payment, 'shopComissionCustomParamKey');

        $this->assertFalse($payment->isShopCommission());
        $payment->set([$prefix . $key => '1']);
        $this->assertTrue($payment->isShopCommission());
    }

    /**
     * @test
     * @covers Payment::setInvoiceId()
     * @expectedException \Lexty\Robokassa\Exception\InvalidInvoiceIdException
     */
    public function set_invalid_invoice_id()
    {
        $this->createPayment()->setInvoiceId('-1');
    }

    /**
     * @test
     * @covers Payment::setCulture()
     * @expectedException \Lexty\Robokassa\Exception\InvalidCultureException
     */
    public function set_invalid_culture()
    {
        $this->createPayment()->setCulture('fr');
    }

    /**
     * @test
     * @covers Payment::setExpirationDate()
     */
    public function set_expiration_date_as_object()
    {
        $date = new \DateTime('NOW');
        $payment = $this->createPayment()->setExpirationDate($date);
        $this->assertEquals($payment->getExpirationDate(), $date);
    }

    /**
     * @test
     * @covers Payment::setExpirationDate()
     */
    public function set_expiration_date_as_string()
    {
        $payment = $this->createPayment()->setExpirationDate('2016-01-29T17:22:48+00:00');
        $this->assertEquals($payment->getExpirationDate()->format('c'), '2016-01-29T17:22:48+00:00');
    }

    /**
     * @test
     * @covers Payment::setExpirationDate()
     */
    public function set_expiration_date_as_string_in_custom_format()
    {
        $payment = $this->createPayment()->setExpirationDate('21.01.2016 20:10:43', 'd.m.Y H:i:s');
        $this->assertEquals($payment->getExpirationDate()->format('c'), '2016-01-21T20:10:43+00:00');
    }

    /**
     * @test
     * @covers Payment::setExpirationDate()
     * @expectedException \Lexty\Robokassa\Exception\InvalidExpirationDateException
     */
    public function set_invalid_expiration_date()
    {
        $this->createPayment()->setExpirationDate('invalid date');
    }

    /**
     * @test
     * @covers Payment::setRequestMethod()
     * @expectedException \Lexty\Robokassa\Exception\UnsupportedRequestMethodException
     */
    public function set_invalid_request_method()
    {
        $this->createPayment()->setRequestMethod('PUT');
    }

    public function validate_result()
    {
        // TODO add mock to Payment::sendRequest() for this test
        $data = [
            'InvId'          => '124',
            'OutSum'         => '1000000',
            'SignatureValue' => '124',
        ];

        $payment = $this->createPayment()->validateResult($data);
    }
}
