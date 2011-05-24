
## Introduction ##

MockFunction is a PHPUnit extension that uses `runkit` to mock PHP functions (both user-defined and system) and use mockobject-style invocation matchers, parameter constraints and all that magic.

To use this extension, you have to install `runkit` first (PECL package). For a working version see https://github.com/zenovich/runkit/

To be able to mock system function (not user-defined ones), you need to turn on `runkit.internal_override` in the PHP config.

## Usage ##

Assuming you are in a PHPUnit test.

    // Back to the future:
    $flux_capacitor = new PHPUnit_Extensions_MockFunction( 'time', $this->object );
    $einsteins_clock = time() + 60;
    $flux_capacitor->expects( $this->atLeastOnce() )->will( $this->returnValue( $einsteins_clock ) );

Where `$flux_capacitor` is the stub function. It can be set up with the same fluent interface as a `MockObject` (excluding method, of course).

The 2nd parameter of the constructor (`$this->object`) is the object where we expect the function to be called. The "mocking" only takes effect from here, from all the other sources it will execute the "normal" function (see next line).

Variable `$einsteins_clock` contains the value that we will return instead of the "regular" value (we add 1 minute for the current time).

In the next line we set up the mock function with the fluent interface of a mock object.

The mocked function is active for the test object instance until `$flux_capacitor->restore();` is called. If you happen to forget this in the end of the test case, normally it is not a problem, because you will test anew instance of your tested class with each test case.

## Advanced mocking ##

You can use all invocation matchers, constraints and stub returns, for example:
    
    // This will execute the original function at the end, but will test 
    // the number of exections ( $this->once() ) and the correct parameter ( $this->equalTo() ).
    $mocked_strrev = new PHPUnit_Extensions_MockFunction( 'strrev', $this->object );
    $mocked_strrev->expects( $this->once() )->with( $this->equalTo( 'abc' ) )->will( $this->returnCallback( 'strrev' ) );


    // This object cannot execute shell_exec.
    $mocked_shell = new PHPUnit_Extensions_MockFunction( 'shell_exec', $this->object );
    $mocked_shell->expects( $this->never() );


    // Expecting to check the existence of 2 file, returning true for both.
    $mocked_file_exists = new PHPUnit_Extensions_MockFunction( 'file_exists', $this->object );
    $mocked_file_exists->expects( $this->exactly( 2 ) )
        ->with(
            $this->logicalOr(
                $this->equalTo( '/tmp/file1.exe' ),
                $this->equalTo( '/tmp/file2.exe' )
            )
        )->will( $this->returnValue( true ) );

For further information see http://www.phpunit.de/manual/3.0/en/mock-objects.html