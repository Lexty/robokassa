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
     * @param string $return
     * @param string $method
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Payment
     */
    private function getPaymentMock($return, $method = 'sendRequest')
    {
        $paymentMock = $this->getMock(get_class($this->createPayment()), [$method], [], '', false);
        $paymentMock->expects($this->once())->method($method)->will($this->returnValue($return));
        $paymentMock
            ->setMerchantLogin($this->login)
            ->setPaymentPassword($this->pass1)
            ->setValidationPassword($this->pass2)
            ->setIsTest($this->isTest);

        return $paymentMock;
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
        $this->createPayment()
            ->setSum(123)
            ->setHashAlgo('invalid')
            ->getPaymentSignatureHash();
    }

    /**
     * @test
     * @covers Payment::getPaymentSignature()
     */
    public function get_payment_signatures()
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
     */
    public function get_payment_signature_with_autoset_shop_comission_custom_param()
    {
        $payment = $this->getPaymentMock(143, 'calculateShopSum');

        $this->assertEquals("login:143::{$this->pass1}:Shp__shop_commission=1",
            $payment
                ->setSum(123)
                ->setPaymentMethod('BankCard')
                ->setShopCommission(true)
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
     */
    public function get_payment_url_with_autoset_shop_comission_custom_param()
    {
        $prefix = $this->getPropertyValue($this->createPayment(), 'customParamsPrefix');
        $key = $this->getPropertyValue($this->createPayment(), 'shopComissionCustomParamKey');

        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=login&Description=Payment+description' .
            "&SignatureValue=b6c901935e83c3198caeee0ac8006679&OutSum=143&Culture=ru&{$prefix}{$key}=1",
            $this->getPaymentMock(143, 'calculateShopSum')
                ->setSum(150)
                ->setDescription('Payment description')
                ->setIsTest(false)
                ->setShopCommission(true)
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

    /**
     * @test
     * @covers Payment::getScriptUrl()
     */
    public function get_script_url()
    {
        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/PaymentForm/FormMS.js?MerchantLogin=login' .
            '&Description=Payment+description&SignatureValue=36a95458024a31d5e8db246f384527d8&OutSum=150.00&InvId=53' .
            '&Culture=en&Encoding=utf-8&Email=mail%40example.org&ExpirationDate=2016-01-29T15%3A18%3A20%2B00%3A00' .
            '&OutSumCurrency=USD&IncCurrLabel=BankCard&isTest=1&Shp_bar=bar+value&Shp_foo=foo+value',
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
                ->getScriptUrl(Payment::FORM_TYPE_MS));
    }

    /**
     * @test
     * @covers Payment::getScriptUrl()
     */
    public function get_script_url_with_changing_price()
    {
        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/PaymentForm/FormFLS.js?MerchantLogin=login' .
            '&Description=Payment+description&SignatureValue=19ee5a0d0764f0005f68de9919db53d3&DefaultSum=150.00' .
            '&InvId=53&Culture=ru',
            $this->createPayment()
                ->setInvoiceId(53)
                ->setSum(150)
                ->setIsTest(false)
                ->setDescription('Payment description')
                ->getScriptUrl(Payment::FORM_TYPE_FLS));
    }

    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\ResponseErrorException
     * @expectedExceptionCode 123
     * @expectedExceptionMessage Error description.
     */
    public function api_request_response_error_exception()
    {
        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>123</Code>
    <Description>Error description.</Description>
  </Result>
</CurrenciesList>
XML;

        $this->getPaymentMock($response)->getCurrencies();
    }

    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\InvoiceNotFoundException
     */
    public function api_request_invoice_not_found_exception()
    {
        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>3</Code>
    <Description></Description>
  </Result>
</CurrenciesList>
XML;

        $this->getPaymentMock($response)->getCurrencies();
    }

    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\CalculateSumErrorException
     */
    public function api_request_calculate_sum_error_exception()
    {
        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>5</Code>
    <Description></Description>
  </Result>
</CurrenciesList>
XML;

        $this->getPaymentMock($response)->getCurrencies();
    }

    /**
     * @test
     * @covers Payment::getCurrencies()
     */
    public function api_request_get_currencies()
    {
        $expected = [
            [
                'Code'        => 'foo',
                'Description' => 'Foo description',
                'Items'       => [
                    ['Label' => 'BarLabel', 'Name' => 'BarName'],
                ],
            ], [
                'Code'        => 'baz',
                'Description' => 'Baz description',
                'Items'       => [
                    ['Label' => 'BatLabel', 'Name' => 'BatName'],
                    ['Label' => 'QuxLabel', 'Name' => 'QuxName'],
                ],
            ],
        ];

        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="{$expected[0]['Code']}" Description="{$expected[0]['Description']}">
      <Items>
        <Currency Label="{$expected[0]['Items'][0]['Label']}" Name="{$expected[0]['Items'][0]['Name']}" />
      </Items>
    </Group>
    <Group Code="{$expected[1]['Code']}" Description="{$expected[1]['Description']}">
      <Items>
        <Currency Label="{$expected[1]['Items'][0]['Label']}" Name="{$expected[1]['Items'][0]['Name']}" />
        <Currency Label="{$expected[1]['Items'][1]['Label']}" Name="{$expected[1]['Items'][1]['Name']}" />
      </Items>
    </Group>
  </Groups>
</CurrenciesList>
XML;

        $this->assertEquals($expected, $this->getPaymentMock($response)->getCurrencies());
    }

    /**
     * @test
     * @covers Payment::getPaymentMethodGroups()
     */
    public function api_request_get_payment_method_groups()
    {
        $expected = [
            'FooCode' => 'Foo description',
            'BarCode' => 'Bar description',
        ];
        $keys = array_keys($expected);

        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<PaymentMethodsList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Methods>
    <Method Code="{$keys[0]}" Description="{$expected['FooCode']}" />
    <Method Code="{$keys[1]}" Description="{$expected['BarCode']}" />
  </Methods>
</PaymentMethodsList>
XML;

        $this->assertEquals($expected, $this->getPaymentMock($response)->getPaymentMethodGroups());
    }

    /**
     * @test
     * @covers Payment::getRates()
     */
    public function api_request_get_rates()
    {
        $expected = [
            [
                'Code'        => 'FooCode',
                'Description' => 'Foo description',
                'Items'       => [
                    [
                        'Label' => 'BarLabel',
                        'Name'  => 'BarName',
                        'Rate'  => 165,
                    ],
                ],
            ], [
                'Code'        => 'BazCode',
                'Description' => 'Baz description',
                'Items'       => [
                    [
                        'Label' => 'ButLabel',
                        'Name'  => 'ButName',
                        'Rate'  => 157.34,
                    ],
                    [
                        'Label' => 'QuzLabel',
                        'Name'  => 'QuzName',
                        'Rate'  => 153.05,
                    ],
                ],
            ],
        ];

        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<RatesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="{$expected[0]['Code']}" Description="{$expected[0]['Description']}">
      <Items>
        <Currency Label="{$expected[0]['Items'][0]['Label']}" Name="{$expected[0]['Items'][0]['Name']}">
          <Rate IncSum="{$expected[0]['Items'][0]['Rate']}" />
        </Currency>
      </Items>
    </Group>
    <Group Code="{$expected[1]['Code']}" Description="{$expected[1]['Description']}">
      <Items>
        <Currency Label="{$expected[1]['Items'][0]['Label']}" Name="{$expected[1]['Items'][0]['Name']}">
          <Rate IncSum="{$expected[1]['Items'][0]['Rate']}" />
        </Currency>
        <Currency Label="{$expected[1]['Items'][1]['Label']}" Name="{$expected[1]['Items'][1]['Name']}">
          <Rate IncSum="{$expected[1]['Items'][1]['Rate']}" />
        </Currency>
      </Items>
    </Group>
  </Groups>
</RatesList>
XML;

        $this->assertEquals($expected, $this->getPaymentMock($response)->getRates(150));
    }

    /**
     * @test
     * @covers Payment::calculateShopSum()
     */
    public function api_request_calculate_shop_sum()
    {
        $expected = 143.64;

        $response = <<< XML
<?xml version="1.0" encoding="UTF-8"?>
<CalcSummsResponseData xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <OutSum>{$expected}</OutSum>
</CalcSummsResponseData>
XML;

        $this->assertEquals($expected, $this->getPaymentMock($response)->calculateShopSum(150, 'BankCard'));
    }

    /**
     * @test
     * @covers Payment::calculateClientSum()
     */
    public function api_request_calculate_client_sum()
    {
        $expected = 157.36;

        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<RatesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="FooCode" Description="Foo description">
      <Items>
        <Currency Label="BankCard" Name="Card Bank">
          <Rate IncSum="{$expected}" />
        </Currency>
      </Items>
    </Group>
  </Groups>
</RatesList>
XML;

        $this->assertEquals($expected, $this->getPaymentMock($response)->calculateClientSum(150, 'BankCard'));
    }

    /**
     * @test
     * @covers Payment::getInvoice()
     */
    public function api_request_get_invoice()
    {
        $expected = [
            'InvoiceId'                => 123,
            'Culture'                  => Payment::CULTURE_EN,
            'StateCode'                => Invoice::STATE_COMPLETED,
            'StateDescription'         => 'Operation completed successfully.',
            'RequestDate'              => '2016-01-29T15:18:20+00:00',
            'StateDate'                => '2016-01-29T15:18:20+00:00',
            'ClientSum'                => 158.0,
            'ClientAccount'            => 'string',
            'PaymentMethod'            => 'BankCard',
            'PaymentMethodCode'        => 'string',
            'PaymentMethodDescription' => 'string',
            'Currency'                 => 'string',
            'ShopSum'                  => 150.0,
        ];

        $response = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<OperationStateResponse xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <State>
    <Code>{$expected['StateCode']}</Code>
    <RequestDate>{$expected['RequestDate']}</RequestDate>
    <StateDate>{$expected['StateDate']}</StateDate>
  </State>
  <Info>
    <IncCurrLabel>{$expected['PaymentMethod']}</IncCurrLabel>
    <IncSum>{$expected['ClientSum']}</IncSum>
    <IncAccount>{$expected['ClientAccount']}</IncAccount>
    <PaymentMethod>
      <Code>{$expected['PaymentMethodCode']}</Code>
      <Description>{$expected['PaymentMethodDescription']}</Description>
    </PaymentMethod>
    <OutCurrLabel>{$expected['Currency']}</OutCurrLabel>
    <OutSum>{$expected['ShopSum']}</OutSum>
  </Info>
</OperationStateResponse>
XML;

        $invoice = $this->getPaymentMock($response)->setCulture(Payment::CULTURE_EN)->getInvoice($expected['InvoiceId']);

        $this->assertEquals($expected['InvoiceId'], $invoice->getInvoiceId());
        $this->assertEquals($expected['Culture'], $invoice->getCulture());
        $this->assertEquals($expected['StateCode'], $invoice->getStateCode());
        $this->assertEquals($expected['RequestDate'], $invoice->getRequestDate()->format('c'));
        $this->assertEquals($expected['StateDate'], $invoice->getStateDate()->format('c'));
        $this->assertEquals($expected['ClientSum'], $invoice->getClientSum());
        $this->assertEquals($expected['ClientAccount'], $invoice->getClientAccount());
        $this->assertEquals($expected['PaymentMethod'], $invoice->getPaymentMethod());
        $this->assertEquals($expected['PaymentMethodCode'], $invoice->getPaymentMethodCode());
        $this->assertEquals($expected['PaymentMethodDescription'], $invoice->getPaymentMethodDescription());
        $this->assertEquals($expected['Currency'], $invoice->getCurrency());
        $this->assertEquals($expected['ShopSum'], $invoice->getShopSum());
        $this->assertEquals($expected['StateDescription'], $invoice->getStateDescription());
        $this->assertEquals($expected, $invoice->asArray());
    }
}