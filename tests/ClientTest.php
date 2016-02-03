<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Tests;

use Lexty\Robokassa\Payment;

class ClientTest extends TestCase
{
    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\ResponseErrorException
     * @expectedExceptionCode 123
     * @expectedExceptionMessage Error description.
     */
    public function api_request_response_error_exception()
    {
        $this->getClientMock($this->getErrorResponse(123, 'Error description.'))->getCurrencies();
    }

    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\InvoiceNotFoundException
     */
    public function api_request_invoice_not_found_exception()
    {
        $this->getClientMock($this->getErrorResponse(3))->getCurrencies();
    }

    /**
     * @test
     * @covers Payment::parseError()
     * @expectedException \Lexty\Robokassa\Exception\CalculateSumErrorException
     */
    public function api_request_calculate_sum_error_exception()
    {
        $this->getClientMock($this->getErrorResponse(5))->getCurrencies();
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

        $response = $this->getCurrenciesResponse($expected);

        $this->assertEquals($expected, $this->getClientMock($response)->getCurrencies());
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

        $response = $this->getPaymentMethodGroupsResponse($expected);

        $this->assertEquals($expected, $this->getClientMock($response)->getPaymentMethodGroups());
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
                    ['Label' => 'BarLabel', 'Name'  => 'BarName', 'ClientSum'  => 165],
                ],
            ], [
                'Code'        => 'BazCode',
                'Description' => 'Baz description',
                'Items'       => [
                    ['Label' => 'ButLabel', 'Name'  => 'ButName', 'ClientSum'  => 157.34],
                    ['Label' => 'QuzLabel', 'Name'  => 'QuzName', 'ClientSum'  => 153.05],
                ],
            ],
        ];

        $response = $this->getRatesResponse($expected);

        $this->assertEquals($expected, $this->getClientMock($response)->getRates(150));
    }

    /**
     * @test
     * @covers Payment::calculateShopSum()
     */
    public function api_request_calculate_shop_sum()
    {
        $expected = 143.64;

        $response = $this->getCalculateShopSumResponse($expected);

        $this->assertEquals($expected, $this->getClientMock($response)->calculateShopSum(150, 'BankCard'));
    }

    /**
     * @test
     * @covers Payment::calculateClientSum()
     */
    public function api_request_calculate_client_sum()
    {
        $expected = 157.36;

        $response = $this->getCalculateClientSumResponse($expected);

        $this->assertEquals($expected, $this->getClientMock($response)->calculateClientSum(150, 'BankCard'));
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
            'StateCode'                => Payment::STATE_COMPLETED,
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
        $invoice = $this->getClientMock($this->getInvoiceResponse($expected))
                        ->setCulture(Payment::CULTURE_EN)
                        ->getInvoice($expected['InvoiceId']);

        $this->assertEquals($expected['InvoiceId'], $invoice['InvoiceId']);
        $this->assertEquals($expected['StateCode'], $invoice['StateCode']);
        $this->assertEquals($expected['RequestDate'], $invoice['RequestDate']->format('c'));
        $this->assertEquals($expected['StateDate'], $invoice['StateDate']->format('c'));
        $this->assertEquals($expected['ClientSum'], $invoice['ClientSum']);
        $this->assertEquals($expected['ClientAccount'], $invoice['ClientAccount']);
        $this->assertEquals($expected['PaymentMethod'], $invoice['PaymentMethod']);
        $this->assertEquals($expected['PaymentMethodCode'], $invoice['PaymentMethodCode']);
        $this->assertEquals($expected['PaymentMethodDescription'], $invoice['PaymentMethodDescription']);
        $this->assertEquals($expected['Currency'], $invoice['Currency']);
        $this->assertEquals($expected['ShopSum'], $invoice['ShopSum']);
    }

    private function getErrorResponse($code, $description = '')
    {
        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>{$code}</Code>
    <Description>{$description}</Description>
  </Result>
</CurrenciesList>
XML;
    }

    private function getCurrenciesResponse($data)
    {
        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<CurrenciesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="{$data[0]['Code']}" Description="{$data[0]['Description']}">
      <Items>
        <Currency Label="{$data[0]['Items'][0]['Label']}" Name="{$data[0]['Items'][0]['Name']}" />
      </Items>
    </Group>
    <Group Code="{$data[1]['Code']}" Description="{$data[1]['Description']}">
      <Items>
        <Currency Label="{$data[1]['Items'][0]['Label']}" Name="{$data[1]['Items'][0]['Name']}" />
        <Currency Label="{$data[1]['Items'][1]['Label']}" Name="{$data[1]['Items'][1]['Name']}" />
      </Items>
    </Group>
  </Groups>
</CurrenciesList>
XML;
    }

    private function getPaymentMethodGroupsResponse($data)
    {
        $keys = array_keys($data);

        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<PaymentMethodsList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Methods>
    <Method Code="{$keys[0]}" Description="{$data['FooCode']}" />
    <Method Code="{$keys[1]}" Description="{$data['BarCode']}" />
  </Methods>
</PaymentMethodsList>
XML;
    }

    private function getRatesResponse($data)
    {
        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<RatesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="{$data[0]['Code']}" Description="{$data[0]['Description']}">
      <Items>
        <Currency Label="{$data[0]['Items'][0]['Label']}" Name="{$data[0]['Items'][0]['Name']}">
          <Rate IncSum="{$data[0]['Items'][0]['ClientSum']}" />
        </Currency>
      </Items>
    </Group>
    <Group Code="{$data[1]['Code']}" Description="{$data[1]['Description']}">
      <Items>
        <Currency Label="{$data[1]['Items'][0]['Label']}" Name="{$data[1]['Items'][0]['Name']}">
          <Rate IncSum="{$data[1]['Items'][0]['ClientSum']}" />
        </Currency>
        <Currency Label="{$data[1]['Items'][1]['Label']}" Name="{$data[1]['Items'][1]['Name']}">
          <Rate IncSum="{$data[1]['Items'][1]['ClientSum']}" />
        </Currency>
      </Items>
    </Group>
  </Groups>
</RatesList>
XML;
    }

    private function getCalculateShopSumResponse($sum)
    {
        return <<< XML
<?xml version="1.0" encoding="UTF-8"?>
<CalcSummsResponseData xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <OutSum>{$sum}</OutSum>
</CalcSummsResponseData>
XML;
    }

    private function getCalculateClientSumResponse($sum)
    {
        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<RatesList xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <Groups>
    <Group Code="FooCode" Description="Foo description">
      <Items>
        <Currency Label="BankCard" Name="Card Bank">
          <Rate IncSum="{$sum}" />
        </Currency>
      </Items>
    </Group>
  </Groups>
</RatesList>
XML;
    }

    private function getInvoiceResponse($data)
    {
        $defaults = [
            'StateCode'                => Payment::STATE_COMPLETED,
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

        $data = array_merge($defaults, $data);

        return <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<OperationStateResponse xmlns="http://auth.robokassa.ru/Merchant/WebService/">
  <Result>
    <Code>0</Code>
  </Result>
  <State>
    <Code>{$data['StateCode']}</Code>
    <RequestDate>{$data['RequestDate']}</RequestDate>
    <StateDate>{$data['StateDate']}</StateDate>
  </State>
  <Info>
    <IncCurrLabel>{$data['PaymentMethod']}</IncCurrLabel>
    <IncSum>{$data['ClientSum']}</IncSum>
    <IncAccount>{$data['ClientAccount']}</IncAccount>
    <PaymentMethod>
      <Code>{$data['PaymentMethodCode']}</Code>
      <Description>{$data['PaymentMethodDescription']}</Description>
    </PaymentMethod>
    <OutCurrLabel>{$data['Currency']}</OutCurrLabel>
    <OutSum>{$data['ShopSum']}</OutSum>
  </Info>
</OperationStateResponse>
XML;
    }
}