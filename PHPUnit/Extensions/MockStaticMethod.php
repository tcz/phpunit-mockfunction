<?php

require_once( dirname( __FILE__ ) . '/MockFunction.php' );

/**
 * Extension for PHPUnit that makes MockObject-style expectations possible for global functions (even PECL functions).
 *
 * @author zoltan.tothczifra
 */
class PHPUnit_Extensions_MockStaticMethod extends PHPUnit_Extensions_MockFunction
{
	/**
	 * Clean-up function.
	 *
	 * Removes mocked method and restores the original was there is any.
	 * Also removes the reference to the object from self::$instances.
	 */
	public function restore()
	{
		if ( $this->active )
		{
			list( $class, $method ) = $this->getClassAndMethod();

			runkit_method_remove( $class, $method );
			runkit_method_rename( $class, $this->restore_name, $method );
			$this->active = false;
		}

		parent::restore();
	}

	/**
	 * Calls original method that we temporary renamed. This maintains the oriignal functionality.
	 *
	 * @param type $arguments
	 * @return mixed
	 */
	protected function callOriginal( array $arguments )
	{
		list( $class ) = $this->getClassAndMethod();
		return call_user_func_array( array( $class, $this->restore_name ), $arguments );
	}

	/**
	 * Creates runkit method to be used for mocking, taking care of callback to this object.
	 *
	 * Also temporary renames the original method if there is.
	 */
	protected function createFunction()
	{
		list( $class, $method ) = $this->getClassAndMethod();

		$this->restore_name	= 'restore_' . $class . '_' . $method . '_' . $this->id . '_' . uniqid();

		// We save the original method in the class for restoring.
		runkit_method_copy( $class, $this->restore_name, $class, $method );
		runkit_method_redefine( $class, $method, '', $this->getCallback(), RUNKIT_ACC_STATIC );

		$this->active = true;
	}

	/**
	 * Extracts classname and method name from a string written like Class::method and checks for their existence.
	 *
	 * @staticvar array $class_and_method Memoization of the classname and method name.
	 * @return array Contains classname (0. offset) and method name (1. offset).
	 */
	protected function getClassAndMethod()
	{
		static $classses_and_methods = array();

		if ( isset( $classses_and_methods[$this->function_name] ) )
		{
			return $classses_and_methods[$this->function_name];
		}

		$class_and_method = explode( '::', $this->function_name );

		if ( 2 !== count( $class_and_method ) )
		{
			trigger_error( 'Invalid static method name. Please provide Classname::method format.', E_USER_ERROR );
		}

		if ( !class_exists( $class_and_method[0] ) )
		{
			trigger_error( "Class '{$class_and_method[0]}' must exist in order the static method to be mocked.", E_USER_ERROR );
		}

		if ( !is_callable( $class_and_method ) )
		{
			trigger_error( "Static method '{$this->function_name}' must exist and be public.", E_USER_ERROR );
		}

		return $classses_and_methods[$this->function_name] = $class_and_method;
	}
}
