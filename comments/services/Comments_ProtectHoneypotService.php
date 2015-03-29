<?php
namespace Craft;

class Comments_ProtectHoneypotService extends BaseApplicationComponent
{
	public function verifyField()
	{
		// The honeypot field must be left blank
		if (craft()->request->getPost('beesknees')) {
			return false;			
		}

		return true;
	}

	public function getField()
	{
		$output = sprintf('<div id="beesknees_wrapper" style="display:none;">');
		$output .= sprintf('<label>Leave this field blank</label>');
		$output .= sprintf('<input type="text" id="beesknees" name="beesknees" style="display:none;" />');
		$output .= sprintf('</div>');

		return $output;
	}
}
