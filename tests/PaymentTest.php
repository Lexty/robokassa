<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Tests;

use Lexty\Robokassa\Payment;

class PaymentTest extends TestCase
{
    /**
     * @param string $return
     * @param string $method
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Payment
     */
    private function getPaymentMock($return, $method = 'sendRequest')
    {
        return new Payment($this->createAuth(), $this->getClientMock($return, $method));
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
                ->setId(6544321)
                ->setCurrency('USD')
                ->setCustomParam('surname', 'Smith')
                ->setCustomParam('name', 'John')
                ->getPaymentSignatureHash()
        );
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
                ->setId(6544321)
                ->getPaymentSignature()
        );
        $this->assertEquals("login:123.00:6544321:USD:{$this->pass1}",
            $this->createPayment()
                ->setSum(123)
                ->setId(6544321)
                ->setCurrency('USD')
                ->getPaymentSignature()
        );
        $this->assertEquals("login:123.00:6544321:USD:{$this->pass1}:Shp_name=John:Shp_surname=Smith",
            $this->createPayment()
                ->setSum(123)
                ->setId(6544321)
                ->setCurrency('USD')
                ->setCustomParam('surname', 'Smith')
                ->setCustomParam('name', 'John')
                ->getPaymentSignature()
        );
        $receipt = <<<JSON
{
  "sno": "osn",
  "items": [
    {
      "name": "Product name 1",
      "quantity": 1,
      "sum": 100,
      "tax": "vat10"
    },
    {
      "name": "Product name 2",
      "quantity": 3,
      "sum": 450,
      "tax": "vat118"
    }
  ]
}
JSON;
        $this->assertEquals("login:123.00:6544321:USD:%7B%0A++%22sno%22%3A+%22osn%22%2C%0A++%22items%22%3A+%5B%0A++++%7B%0A++++++%22name%22%3A+%22Product+name+1%22%2C%0A++++++%22quantity%22%3A+1%2C%0A++++++%22sum%22%3A+100%2C%0A++++++%22tax%22%3A+%22vat10%22%0A++++%7D%2C%0A++++%7B%0A++++++%22name%22%3A+%22Product+name+2%22%2C%0A++++++%22quantity%22%3A+3%2C%0A++++++%22sum%22%3A+450%2C%0A++++++%22tax%22%3A+%22vat118%22%0A++++%7D%0A++%5D%0A%7D:{$this->pass1}:Shp_name=John:Shp_surname=Smith",
            $this->createPayment()
                ->setSum(123)
                ->setId(6544321)
                ->setCurrency('USD')
                ->setCustomParam('surname', 'Smith')
                ->setCustomParam('name', 'John')
                ->setReceipt(urlencode($receipt))
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

        $prefix = $this->getPropertyValue($this->createPayment(), 'customParamsPrefix');
        $key = $this->getPropertyValue($this->createPayment(), 'shopCommissionCustomParamKey');

        $this->assertEquals("login:143::{$this->pass1}:{$prefix}{$key}=1",
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
                ->setId(53)
                ->setSum(150)
                ->setDescription('Payment description')
                ->setCulture(Payment::CULTURE_EN)
                ->setEncoding('utf-8')
                ->setEmail('mail@example.org')
                ->setExpirationDate('2016-01-29T15:18:20+00:00')
                ->setCurrency('USD')
                ->setPaymentMethod('BankCard')
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
        $key = $this->getPropertyValue($this->createPayment(), 'shopCommissionCustomParamKey');

        $payment = $this->getPaymentMock(143, 'calculateShopSum')
            ->setSum(150)
            ->setDescription('Payment description')
            ->setShopCommission(true);
        $payment->getAuth()->setTest(false);

        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/Index.aspx?MerchantLogin=login&Description=Payment+description' .
            "&SignatureValue=b6c901935e83c3198caeee0ac8006679&OutSum=143&Culture=ru&{$prefix}{$key}=1",
            $payment->getPaymentUrl());
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
            'id'                 => 123,
            'description'        => 'Payment description.',
            'paymentMethod'      => 'BankCard',
            'culture'            => 'en',
            'encoding'           => 'utf-16',
            'email'              => 'mail@example.org',
            'expirationDate'     => '2016-01-29T15:18:20+00:00',
            'currency'           => 'RUB',
            'hashAlgo'           => 'md5',
            'isTest'             => true,
            'requestMethod'      => 'post',
        ];
        $payment = $this->createPayment()->set($data);

        $this->assertEquals($data['merchantLogin'], $payment->getMerchantLogin());
        $this->assertEquals($data['paymentPassword'], $payment->getPaymentPassword());
        $this->assertEquals($data['validationPassword'], $payment->getValidationPassword());
        $this->assertEquals($data['sum'], $payment->getSum());
        $this->assertEquals($data['shopCommission'], $payment->isShopCommission());
        $this->assertEquals($data['id'], $payment->getId());
        $this->assertEquals($data['description'], $payment->getDescription());
        $this->assertEquals($data['paymentMethod'], $payment->getPaymentMethod());
        $this->assertEquals($data['culture'], $payment->getCulture());
        $this->assertEquals($data['encoding'], $payment->getEncoding());
        $this->assertEquals($data['email'], $payment->getEmail());
        $this->assertEquals($data['expirationDate'], $payment->getExpirationDate()->format('c'));
        $this->assertEquals($data['currency'], $payment->getCurrency());
        $this->assertEquals($data['hashAlgo'], $payment->getHashAlgo());
        $this->assertEquals($data['isTest'], $payment->isTest());
        $this->assertEquals($data['requestMethod'], $payment->getRequestMethod());
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
        $key = $this->getPropertyValue($payment, 'shopCommissionCustomParamKey');

        $this->assertFalse($payment->isShopCommission());
        $payment->set([$prefix . $key => '1']);
        $this->assertTrue($payment->isShopCommission());
    }

    /**
     * @test
     * @covers Payment::setId()
     * @expectedException \Lexty\Robokassa\Exception\InvalidInvoiceIdException
     */
    public function set_invalid_payment_id()
    {
        $this->createPayment()->setId('-1');
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
        $this->assertEquals($payment->getExpirationDate()->format('d.m.Y H:i:s'), '21.01.2016 20:10:43');
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
     * @covers Payment::getFormUrl()
     */
    public function get_form_url()
    {
        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/PaymentForm/FormMS.js?MerchantLogin=login' .
            '&Description=Payment+description&SignatureValue=36a95458024a31d5e8db246f384527d8&OutSum=150.00&InvId=53' .
            '&Culture=en&Encoding=utf-8&Email=mail%40example.org&ExpirationDate=2016-01-29T15%3A18%3A20%2B00%3A00' .
            '&OutSumCurrency=USD&IncCurrLabel=BankCard&isTest=1&Shp_bar=bar+value&Shp_foo=foo+value',
            $this->createPayment()
                ->setId(53)
                ->setSum(150)
                ->setDescription('Payment description')
                ->setCulture(Payment::CULTURE_EN)
                ->setEncoding('utf-8')
                ->setEmail('mail@example.org')
                ->setExpirationDate('2016-01-29T15:18:20+00:00')
                ->setCurrency('USD')
                ->setPaymentMethod('BankCard')
                ->setCustomParam('foo', 'foo value')
                ->setCustomParam('bar', 'bar value')
                ->getFormUrl(Payment::FORM_TYPE_MS));
    }

    /**
     * @test
     * @covers Payment::getFormUrl()
     */
    public function get_form_url_with_changing_price()
    {
        $payment = $this->createPayment()
            ->setId(53)
            ->setSum(150)
            ->setDescription('Payment description');
        $payment->getAuth()->setTest(false);
        $this->assertEquals(
            'https://auth.robokassa.ru/Merchant/PaymentForm/FormFLS.js?MerchantLogin=login' .
            '&Description=Payment+description&SignatureValue=19ee5a0d0764f0005f68de9919db53d3&DefaultSum=150.00' .
            '&InvId=53&Culture=ru',
            $payment->getFormUrl(Payment::FORM_TYPE_FLS));
    }

    /**
     * @test
     * @covers Payment::validateResult()
     */
    public function validate_result()
    {
        $data = [
            'InvId'          => 123,
            'OutSum'         => 150,
            'SignatureValue' => '0109ea77984a645bf105f563abee3fe2',
        ];

        $invoice = [
            'StateCode'                => Payment::STATE_COMPLETED,
            'RequestDate'              => '2016-01-29T15:18:20+00:00',
            'StateDate'                => '2016-01-29T15:18:20+00:00',
            'ClientSum'                => 158.0,
            'ClientAccount'            => 'string',
            'PaymentMethod'            => 'BankCard',
            'PaymentMethodCode'        => 'string',
            'PaymentMethodDescription' => 'string',
            'Currency'                 => 'string',
            'ShopSum'                  => $data['OutSum'],
        ];

        $payment = new Payment($this->createAuth(), $this->getClientMock($invoice, 'getInvoice'));

        $this->assertTrue($payment->validateResult($data));
    }

    /**
     * @test
     * @covers Payment::validateResult()
     */
    public function validate_result_with_invoice_state_processing()
    {
        $data = [
            'InvId'          => 123,
            'OutSum'         => 150,
            'SignatureValue' => '0109ea77984a645bf105f563abee3fe2',
        ];

        $invoice = [
            'StateCode'                => Payment::STATE_PROCESSING,
            'RequestDate'              => '2016-01-29T15:18:20+00:00',
            'StateDate'                => '2016-01-29T15:18:20+00:00',
            'ClientSum'                => 158.0,
            'ClientAccount'            => 'string',
            'PaymentMethod'            => 'BankCard',
            'PaymentMethodCode'        => 'string',
            'PaymentMethodDescription' => 'string',
            'Currency'                 => 'string',
            'ShopSum'                  => $data['OutSum'],
        ];

        $payment = new Payment($this->createAuth(), $this->getClientMock($invoice, 'getInvoice'));

        $this->assertFalse($payment->validateResult($data));
    }

    /**
     * @test
     * @covers Payment::getSuccessAnswer()
     */
    public function get_success_answer()
    {
        $this->assertEquals("OK123\n", $this->createPayment()->setId(123)->getSuccessAnswer());
    }
}