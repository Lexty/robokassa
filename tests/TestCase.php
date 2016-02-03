<?php
/**
 * @author Alexandr Medvedev <alexandr.mdr@gmail.com>
 * @license https://github.com/Lexty/robokassa/blob/master/LICENSE MIT
 * @link https://github.com/Lexty/Robokassa
 */
namespace Lexty\Robokassa\Tests;

use Lexty\Robokassa\Auth;
use Lexty\Robokassa\Client;
use Lexty\Robokassa\Payment;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected $login = 'login';
    protected $pass1 = 'pass1';
    protected $pass2 = 'pass2';
    protected $isTest = true;

    protected function createAuth()
    {
        return new Auth($this->login, $this->pass1, $this->pass2, $this->isTest);
    }
    protected function createPayment()
    {
        return new Payment($this->createAuth());
    }

    /**
     * For not public properties.
     *
     * @param object $object
     * @param string $property
     *
     * @return mixed
     */
    protected function getPropertyValue($object, $property)
    {
        $objectReflection = new \ReflectionObject($object);
        $propertyReflection = $objectReflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        return $propertyReflection->getValue($object);
    }

    /**
     * @param string $classname Class name.
     * @param string $method    Method name.
     * @param string $return    The value of that $method should return.
     * @param array  $args      Constructor arguments.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    protected function mock($classname, $return, $method, array $args = [])
    {
        $mock = $this->getMock($classname, [$method], $args, '', true);
        $mock->expects($this->once())->method($method)->will($this->returnValue($return));
        return $mock;
    }

    /**
     * @param        $return
     * @param string $method
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Client
     */
    protected function getClientMock($return, $method = 'sendRequest')
    {
        return $this->mock('Lexty\Robokassa\Client', $return, $method, [$this->createAuth()]);
    }
}