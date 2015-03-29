<?php
namespace Craft;

class Comments_ProtectService extends BaseApplicationComponent
{
	public function getFields()
	{
		return craft()->comments_protectOrigin->getField() . 
		craft()->comments_protectHoneypot->getField() . 
		craft()->comments_protectJavascript->getField();
	}

	public function verifyFields()
	{
		return craft()->comments_protectOrigin->verifyField()
		&& craft()->comments_protectHoneypot->verifyField()
		&& craft()->comments_protectJavascript->verifyField();
	}




}