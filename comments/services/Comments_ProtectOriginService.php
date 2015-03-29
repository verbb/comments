<?php
namespace Craft;

class Comments_ProtectOriginService extends BaseApplicationComponent
{
	public function verifyField()
	{
		$uahash = craft()->request->getPost('__UAHASH');
		$uahome = craft()->request->getPost('__UAHOME');

		// Run a user agent check
		if ( ! $uahash || $uahash != $this->getUaHash() ) {
			return false;
		}

		// Run originating domain check
		if ( ! $uahome || $uahome != $this->getDomainHash() ) {
			return false;
		}

		// Passed
		return true;
	}

	public function getField()
	{
		$output = sprintf('<input type="hidden" id="__UAHOME" name="__UAHOME" value="%s" />', $this->getDomainHash());
		$output .= sprintf('<input type="hidden" id="__UAHASH" name="__UAHASH" value="%s"/>', $this->getUaHash()); 

		return $output;
	}

	protected function getDomainHash()
	{
		$domain = craft()->request->getHostInfo();

		return $this->getHash( $domain );
	}

	protected function getUaHash()
	{
		return $this->getHash( craft()->request->getUserAgent() );
	}

	protected function getHash($str)
	{
		return md5( sha1($str) );
	}
}
