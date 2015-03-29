<?php
namespace Craft;

class Comments_ProtectJavascriptService extends BaseApplicationComponent
{
	public function verifyField()
	{		
		$jsset = craft()->request->getPost('__JSCHK');
 
		if (strlen($jsset) > 0) {	
			return true;			
		}

		return false;
	}

	public function getField()
	{	 						
		// Create the unique token 
		$uniqueId = uniqid();

		// Set a hidden field with no value and use javascript to set it.
		$output = sprintf('<input type="hidden" id="__JSCHK_%s" name="__JSCHK" />', $uniqueId);
		$output .= sprintf('<script type="text/javascript">document.getElementById("__JSCHK_%s").value = "%s";</script>', $uniqueId, $uniqueId); 
 		
		return $output;
	}

}