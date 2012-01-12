<?php

include '../../../application/tests\test_base.php';


require_once '../InvitationController.php';

class InvitationControllerTest extends PHPUnit_Framework_TestCase
{


/*
 * test to check the validation of email addresses
 */
public function testZendEmailValidator()
{
   $validator = new Zend_Validate_EmailAddress();
   

   
   // test some possible addresses
   $this->assertEquals(true,$validator->isValid("test@gmx.de"));
   $this->assertEquals(true,$validator->isValid("test@gmx.com"));
   $this->assertEquals(true,$validator->isValid("test@gmx.co.uk"));
   $this->assertEquals(true,$validator->isValid("test@gmx.it"));
   
   // test some incorrect addresses
   $this->assertEquals(false,$validator->isValid("testgmx.de"));
   $this->assertEquals(false,$validator->isValid(".test@gm.de"));
   $this->assertEquals(false,$validator->isValid("test.@gmx.de"));
   $this->assertEquals(false,$validator->isValid("te st@gmx.it"));
   
   
   
   
   
}


}
?>