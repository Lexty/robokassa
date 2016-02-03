<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Tests;

class AuthTest extends TestCase
{
    /**
     * @test
     * @covers Auth::setHashAlgo()
     * @expectedException \Lexty\Robokassa\Exception\UnsupportedHashAlgorithmException
     */
    public function get_payment_signature_hash_with_invalid_algorithm()
    {
        $this->createAuth()->setHashAlgo('invalid');
    }

    /**
     * @test
     * @covers Auth::getSignatureValue()
     */
    public function get_signature_value()
    {
        $this->assertEquals('foo:bat:bar:quz', $this->createAuth()->getSignatureValue('{foo}{:bat}:{bar}{:quz}', [
            'foo' => 'foo',
            'bat' => 'bat',
            'bar' => 'bar',
            'quz' => 'quz',
        ]));

        $this->assertEquals('foo::quz', $this->createAuth()->getSignatureValue('{foo}{:bat}:{bar}{:quz}', [
            'foo' => 'foo',
            'bat' => '',
            'bar' => '',
            'quz' => 'quz',
        ]));
    }

    /**
     * @test
     * @covers Auth::getSignatureHash()
     */
    public function get_signature_hash()
    {
        $this->assertEquals('d7308f4d19d309a988f17788804f763b', $this->createAuth()->getSignatureHash('{foo}{:bat}:{bar}{:quz}', [
            'foo' => 'foo',
            'bat' => 'bat',
            'bar' => 'bar',
            'quz' => 'quz',
        ]));
    }
}