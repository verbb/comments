<?php
namespace Craft;

class Comments_ProtectDuplicateService extends BaseApplicationComponent
{
	public function verifyField()
	{	
		if (craft()->httpSession->get('duplicateFieldId')) {
			// If there is a valid unique token set, unset it and return true.		
			craft()->httpSession->remove('duplicateFieldId');		

			return true;			
		}

		return false;
	}

	public function getField()
	{	 						
		// Create the unique token 
		$uniqueId = uniqid();

		// Create session variable
		craft()->httpSession->add('duplicateFieldId', $uniqueId);
	}
}
