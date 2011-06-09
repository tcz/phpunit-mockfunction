<?php

require_once dirname(__FILE__) . '/../../PHPUnit/Extensions/MockStaticMethod.php';

/**
 * @covers PHPUnit_Extensions_MockFunction
 */
class Tests_Extensions_MockStaticMethodTest extends PHPUnit_Framework_TestCase
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
        $this->test_scope_object = new TestScopeObjectForStatic();
    }

    /**
     * Test simple function return faking without consraints.
     */
    public function testMockWithReturn()
    {
        $this->object = new PHPUnit_Extensions_MockStaticMethod( 'TestStatic::test', $this->test_scope_object );

		// CHanging return value.
        $this->object->expects( $this->atLeastOnce() )->will( $this->returnValue( 'DEF' ) );

        $this->assertSame( 'DEF', $this->test_scope_object->callStatic() );
		
		// From this scope the original method is called.
		$this->assertSame( 'ABC', TestStatic::test() );

        $this->object->restore();

        // We are back in 1985.
        $this->assertSame( 'ABC', $this->test_scope_object->callStatic() );
    }

}

/**
 * Class to be used as scope object for mocked function calls.
 */
class TestScopeObjectForStatic
{
    /**
     * Calls the TestStatic object.
     *
     * @return mixed The result of the static call.
     */
    public function callStatic()
    {
        return TestStatic::test();
    }
}

/**
 * Static object to test method overriding.
 */
class TestStatic
{
	/**
	 * Method to override.
	 * 
	 * @return string
	 */
	public static function test()
	{
		return 'ABC';
	}
}

?>
