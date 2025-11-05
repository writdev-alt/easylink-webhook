<?php

namespace Tests\Unit\Payment;

use App\Payment\Easylink\EasylinkPaymentGateway;
use App\Payment\Netzme\NetzmePaymentGateway;
use App\Payment\PaymentGatewayFactory;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayFactoryTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewayFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PaymentGatewayFactory;
    }

    public function test_factory_can_create_netzme_gateway()
    {
        $gateway = $this->factory->getGateway('netzme');

        $this->assertInstanceOf(NetzmePaymentGateway::class, $gateway);
    }

    public function test_factory_can_create_easylink_gateway()
    {
        $gateway = $this->factory->getGateway('easylink');

        $this->assertInstanceOf(EasylinkPaymentGateway::class, $gateway);
    }

    public function test_factory_throws_exception_for_unsupported_gateway()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway: unsupported_gateway');

        $this->factory->getGateway('unsupported_gateway');
    }

    public function test_factory_throws_exception_for_empty_gateway_code()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway: ');

        $this->factory->getGateway('');
    }

    public function test_factory_throws_exception_for_null_gateway_code()
    {
        $this->expectException(Exception::class);

        $this->factory->getGateway(null);
    }

    public function test_factory_is_case_sensitive()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway: NETZME');

        $this->factory->getGateway('NETZME');
    }

    public function test_factory_handles_whitespace_in_gateway_code()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway:  netzme ');

        $this->factory->getGateway(' netzme ');
    }

    public function test_factory_returns_different_instances_for_same_gateway()
    {
        $gateway1 = $this->factory->getGateway('netzme');
        $gateway2 = $this->factory->getGateway('netzme');

        // Since we're using App::make(), each call should return a new instance
        $this->assertNotSame($gateway1, $gateway2);
        $this->assertInstanceOf(NetzmePaymentGateway::class, $gateway1);
        $this->assertInstanceOf(NetzmePaymentGateway::class, $gateway2);
    }

    public function test_factory_supports_all_expected_gateways()
    {
        $supportedGateways = ['netzme', 'easylink'];
        $expectedClasses = [
            'netzme' => NetzmePaymentGateway::class,
            'easylink' => EasylinkPaymentGateway::class,
        ];

        foreach ($supportedGateways as $gatewayCode) {
            $gateway = $this->factory->getGateway($gatewayCode);
            $this->assertInstanceOf($expectedClasses[$gatewayCode], $gateway);
        }
    }

    public function test_factory_method_exists()
    {
        $this->assertTrue(method_exists($this->factory, 'getGateway'));
    }

    public function test_factory_get_gateway_method_signature()
    {
        $reflection = new \ReflectionMethod($this->factory, 'getGateway');

        $this->assertTrue($reflection->isPublic());
        $this->assertCount(1, $reflection->getParameters());

        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('gatewayCode', $parameter->getName());
        $this->assertEquals('string', $parameter->getType()->getName());
    }
}