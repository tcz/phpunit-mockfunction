<?php

require_once dirname(__FILE__) . '/../../PHPUnit/Extensions/MockFunction.php';

/**
 * @covers PHPUnit_Extensions_MockFunction
 */
class Tests_Extensions_MockFunctionTest extends PHPUnit_Framework_TestCase
{

    /**
     * Object to test.
     *
     * @var PHPUnit_Extensions_MockFunction
     */
    protected $object;

    /**
     * Scope object to use for calling mocked functions.
     *
     * @var PHPUnit_Extensions_MockFunction
     */
    protected $test_scope_object;

    /**
     * Name of the mocked function.
     *
     * @var PHPUnit_Extensions_MockFunction
     */
    protected $test_function_name;

    /**
     * Setting up.
     */
    protected function setUp()
    {
        $this->test_scope_object = new TestScopeObject();
    }

    protected function onNotSuccessfulTest( Exception $e )
    {
        var_dump( $e->getMessage() );
        var_dump( $e->getTraceAsString() );
    }

    /**
     * Test simple function return faking without consraints.
     */
    public function testMockWithReturn()
    {
        $this->test_function_name = self::getFunctionName( 'time' );
        $this->object = new PHPUnit_Extensions_MockFunction( $this->test_function_name, $this->test_scope_object );

        // Back to the future:
        $einsteins_clock = time() + 60;
        $this->object->expects( $this->atLeastOnce() )->will( $this->returnValue( $einsteins_clock ) );

        //  Einstein's clock is exactly one minute behind mine.
        $this->assertSame( $einsteins_clock, $this->test_scope_object->callFunction( $this->test_function_name, array() ) );

        $this->object->restore();

        // We are back in 1985.
        $this->assertSame( time(), $this->test_scope_object->callFunction( $this->test_function_name, array() ) );
    }

    /**
     * Test more advanced mocking with return callback and constraints.
     */
    public function testMockWithOriginal()
    {
        $this->test_function_name = self::getFunctionName( 'strrev' );
        $this->object = new PHPUnit_Extensions_MockFunction( $this->test_function_name, $this->test_scope_object );

        // Return normally, only checks the call.
        $this->object->expects( $this->once() )->with( $this->equalTo( 'abc' ) )->will( $this->returnCallback( 'strrev' ) );

        // The same output is returned.
        $this->assertSame( 'cba', $this->test_scope_object->callFunction( $this->test_function_name, array( 'abc' ) ) );

        $this->object->restore();
    }

    /**
     * Testing newly created function.
     */
    public function testMockNewFunction()
    {
        $this->test_function_name = 'new_random_function_' . uniqid();
        $this->object = new PHPUnit_Extensions_MockFunction( $this->test_function_name, $this->test_scope_object );

        // Return normally, only checks the call.
        $this->object->expects( $this->any() )->will( $this->returnValue( 'OK' ) );

        $this->assertSame( 'OK', $this->test_scope_object->callFunction( $this->test_function_name, array() ) );

        $this->object->restore();
    }

    protected static function getFunctionName( $function_name )
    {
        // Memoization for config value.
        static $internal_override_on;

        if ( !isset( $internal_override_on ) )
        {
            $internal_override_on = (bool) ini_get( 'runkit.internal_override' );
        }

        if ( $internal_override_on )
        {
            return $function_name;
        }

        $proxy_function_name = 'proxy_to_' . $function_name . '_' . uniqid();

        eval( <<<PROXY
            function $proxy_function_name()
            {
                \$arguments = func_get_args();
		return call_user_func_array( '$function_name', \$arguments );
            }
PROXY
         );

        return $proxy_function_name;
    }

}

/**
 * Class to be used as scope object for mocked function calls.
 */
class TestScopeObject
{
    /**
     * Simply calls a PHP callback with the passed parameters.
     *
     * @throws InvalidArgumentException In case $callback is not callable.
     * @param callback $callback
     * @param array $params
     * @return mixed The result of the callback execution.
     */
    public function callFunction( $callback, array $params )
    {
        if ( !is_callable( $callback ) )
        {
            throw new InvalidArgumentException( 'Invalid callback at parameter 1st' );
        }
        return call_user_func_array( $callback, $params );
    }
}

?>
