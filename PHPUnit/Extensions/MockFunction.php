<?php

/**
 * Extension for PHPUnit that makes MockObject-style expectations possible for global functions (even PECL functions).
 *
 * @author zoltan.tothczifra
 */
class PHPUnit_Extensions_MockFunction
{
	/**
	 * Incremental ID of the current object instance to be able to find it.
	 *
	 * @see self::$instances
	 * @var integer
	 */
	protected $id;

	/**
	 * Flag to tell if the function mocking is active or not (replacement is in place).
	 *
	 * @var boolean
	 */
	protected $active = false;

	/**
	 * Test case from where the mock function is created. Automagically found with call stack.
	 *
	 * @var PHPUnit_Framework_TestCase
	 */
	protected $test_case;

	/**
	 * Standard PHPUnit MockObject used to test invocations of the mocked function.
	 *
	 * @var object
	 */
	protected $mock_object;

	/**
	 * Object to check if the function is called from its scope (supposedly the test object to the test case).
	 *
	 * If the mocked function is called outside its scope, the original (unmocked)
	 * function is executed - if there is.
	 *
	 * @var object
	 */
	protected $scope_object;

	/**
	 * The name of the original function that gets mocked.
	 *
	 * @var string
	 */
	protected $function_name;

	/**
	 * Random temporary name of a funstion there we "save" the original, unmocked function.
	 *
	 * If the function did not exist before mocking, it's empty.
	 *
	 * @var type
	 */
	protected $restore_name;

	/**
	 * Value of the incremental ID that next time will be assigned to an instance of this class.
	 *
	 * @var integer
	 */
	protected static $next_id = 1;

	/**
	 * List of active mock object instances (those that are not restored) with their ID as key.
	 *
	 * @var type
	 */
	protected static $instances = array();

	/**
	 * Class name of PHPUnit test cases, used to automatically find them in the call stack.
	 */
	const TESTCASE_CLASSNAME = 'PHPUnit_Framework_TestCase';

	/**
	 * Number of call stack items between the function call of the test object and self::invoked().
	 *
	 * 1. Inocation from test object
	 * 2. Runkit function
	 * 3. Call to self::invoked().
	 */
	const CALL_STACK_DISTANCE = 3;

	/**
	 * Constructor setting up object.
	 *
	 * @param string $function_name Name of the function to mock. Doesn't need to exist, might be newly created.
	 * @param object $scope_object Object specifying the scope where the mocked function is used.
	 */
	public function __construct( $function_name, $scope_object )
	{
		if ( !function_exists( 'runkit_function_redefine' ) )
		{
			trigger_error( 'Runkit is not installed.', E_USER_ERROR );
		}
		
		// APC doesn't quite like runkit.
		// When they work together, it might result dead process.
		if ( function_exists( 'apc_clear_cache' ) )
		{
			apc_clear_cache();
		}
		
		$this->id				= self::$next_id;
		$this->function_name	= $function_name;
		$this->scope_object		= $scope_object;
		$this->test_case		= self::findTestCase();
		$this->mock_object		= $this->test_case->getMock( 
				'Mock_' . str_replace( '::', '__', $this->function_name ) . '_' . $this->id, 
				array( 'invoked' ) 
		);

		++self::$next_id;
		self::$instances[$this->id] = $this;

		$this->createFunction();
	}

	/**
	 * Called when all the referneces to the object are removed (even self::$instances).
	 *
	 * Makes sure the replaced functions are finally cleared in case runkit
	 * "forgets" to remove them in the end of the request.
	 * It is still highly recommended to call restore() explicitly!
	 */
	public function __destruct()
	{
		$this->restore();
	}

	/**
	 * Clean-up function.
	 *
	 * Removes mocked function and restored the original was there is any.
	 * Also removes the reference to the object from self::$instances.
	 */
	public function restore()
	{
		if ( $this->active )
		{
		runkit_function_remove( $this->function_name );
		if ( isset( $this->restore_name ) )
		{
			runkit_function_rename( $this->restore_name, $this->function_name );
		}
			$this->active = false;
		}

		if ( isset( self::$instances[$this->id] ) )
				{
					unset( self::$instances[$this->id] );
				}
	}

	/**
	 * Callback method to be used in runkit function when it is invoked.
	 *
	 * It takes the parameters of the function call and passes them to the mock object.
	 *
	 * @param type $arguments 0-indexed array of arguments with which the mocked function was called.
	 * @return mixed
	 */
	public function invoked( array $arguments )
	{
		// Original function is called when the invocation is ousides he scope or
		// the invocation comes from this object.
		$caller_object = self::getCallStackObject( self::CALL_STACK_DISTANCE );
		if ( $caller_object === $this || ( isset( $this->scope_object ) && $this->scope_object !== $caller_object ) )
		{
			if ( isset( $this->restore_name ) )
			{
				return $this->callOriginal( $arguments );
			}
			trigger_error( 'Undefined function: ' . $this->function_name, E_USER_ERROR );
		}
		return call_user_func_array( array( $this->mock_object, __FUNCTION__ ), $arguments );
	}
	
	/**
	 * Calls original function that we temporary renamed. This maintains the oriignal functionality.
	 *
	 * @param type $arguments
	 * @return mixed
	 */
	protected function callOriginal( array $arguments )
	{
		return call_user_func_array( $this->restore_name, $arguments );
	}

	/**
	 * Proxy to the 'expects' of the mock object.
	 *
	 * Also calld method() so after this the mock object can be used to set
	 * parameter constraints and return values.
	 *
	 * @return object
	 */
	public function expects()
	{
		$arguments = func_get_args();
		return call_user_func_array( array( $this->mock_object, __FUNCTION__ ), $arguments )->method( 'invoked' );
	}

	/**
	 * Returns an instance of this class selected by its ID. Used in the runkit function.
	 *
	 * @param integer $id
	 * @return object
	 */
	public static function findMock( $id )
	{
		if ( !isset( self::$instances[$id] ) )
		{
			throw new Exception( 'Mock object not found, might be destroyed already.' );
		}
		return self::$instances[$id];
	}

	/**
	 * Finds the rist object in the call cstack that is instance of a PHPUnit test case.
	 *
	 * @see self::TESTCASE_CLASSNAME
	 * @return object
	 */
	public static function findTestCase()
	{
		$backtrace = debug_backtrace();
		$classname = self::TESTCASE_CLASSNAME;

		do
		{
			$calling_test = array_shift( $backtrace );
		} while( isset( $calling_test ) && !( isset( $calling_test['object'] ) && $calling_test['object'] instanceof $classname ) );

		if ( !isset( $calling_test ) )
		{
			trigger_error( 'No calling test found.', E_USER_ERROR );
		}

		return $calling_test['object'];
	}

	/**
	 * Creates runkit function to be used for mocking, taking care of callback to this object.
	 *
	 * Also temporary renames the original function if there is.
	 */
	protected function createFunction()
	{
		if ( function_exists( $this->function_name ) )
		{
			$this->restore_name	= 'restore_' . $this->function_name . '_' . $this->id . '_' . uniqid();

			runkit_function_copy( $this->function_name, $this->restore_name );
			runkit_function_redefine( $this->function_name, '', $this->getCallback() );
		}
		else
		{
			runkit_function_add( $this->function_name, '', $this->getCallback() );
		}

		$this->active = true;
	}

	/**
	 * Gives back the source code body of the runkit function replacing the original.
	 *
	 * The function is quite simple - find the function mock instance (of this class)
	 * that created it, then calls its invoked() method with the parameters of its invokation.
	 *
	 * @return string
	 */
	protected function getCallback()
	{
		$class_name = __CLASS__;
		return <<<CALLBACK
			\$mock		= $class_name::findMock( {$this->id} );
			\$arguments = func_get_args();
			return \$mock->invoked( \$arguments );
CALLBACK;
	}

	/**
	 * Returns an object from the call stack at Nth distance if there is, null otherwise.
	 *
	 * In theory we should instement the distance by one because when we call this
	 * method, we don't count it itself to the callstack, but since the stack is
	 * 0-indexed, we can avoid this step.
	 *
	 * Function calls are ignored, the first call after $distance that is made form
	 * is returned.
	 *
	 * @param type $distance The distance in the call stack from the current call and the desired one.
	 * @return object
	 */
	protected static function getCallStackObject( $distance )
	{
		$backtrace = debug_backtrace();

		do
		{
			if ( isset( $backtrace[$distance]['object'] ) )
			{
				return $backtrace[$distance]['object'];
			}

			/* If there is no object assiciated to this call, we go further until
			 * the next one.
			 * Funcsion calls and functions like "user_call_func" get ignored.
			 */
			++$distance;
		} while ( isset( $backtrace[$distance] ) );

		return null;
	}

}
