<?php
namespace Craft;

class Comments_ProtectService extends BaseApplicationComponent
{
    // Public Methods
    // =========================================================================

    public function getFields()
    {
        $fields = $this->getOriginField() . $this->getHoneypotField() . $this->getJSField();
        
        return $fields;
    }

    public function verifyFields()
    {
        $checks = $this->verifyOriginField() && $this->verifyHoneypotField() && $this->verifyJSField();

        return $checks;
    }

    //
    // Javascript Method
    //

    public function verifyJSField()
    {       
        $jsset = craft()->request->getPost('__JSCHK');
 
        if (strlen($jsset) > 0) {   
            return true;            
        }

        return false;
    }

    public function getJSField()
    {                           
        // Create the unique token 
        $uniqueId = uniqid();

        // Set a hidden field with no value and use javascript to set it.
        $output = sprintf('<input type="hidden" id="__JSCHK_%s" name="__JSCHK" />', $uniqueId);
        $output .= sprintf('<script type="text/javascript">document.getElementById("__JSCHK_%s").value = "%s";</script>', $uniqueId, $uniqueId); 
        
        return $output;
    }

    //
    // Origin Method
    //

    public function verifyOriginField()
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

    public function getOriginField()
    {
        $output = sprintf('<input type="hidden" id="__UAHOME" name="__UAHOME" value="%s" />', $this->getDomainHash());
        $output .= sprintf('<input type="hidden" id="__UAHASH" name="__UAHASH" value="%s"/>', $this->getUaHash()); 

        return $output;
    }

    //
    // Honeypot Method
    //

    public function verifyHoneypotField()
    {
        // The honeypot field must be left blank
        if (craft()->request->getPost('beesknees')) {
            return false;           
        }

        return true;
    }

    public function getHoneypotField()
    {
        $output = sprintf('<div id="beesknees_wrapper" style="display:none;">');
        $output .= sprintf('<label>Leave this field blank</label>');
        $output .= sprintf('<input type="text" id="beesknees" name="beesknees" style="display:none;" />');
        $output .= sprintf('</div>');

        return $output;
    }

    //
    // Duplicate Method
    //

    public function verifyDuplicateField()
    {   
        if (craft()->httpSession->get('duplicateFieldId')) {
            // If there is a valid unique token set, unset it and return true.      
            craft()->httpSession->remove('duplicateFieldId');       

            return true;            
        }

        return false;
    }

    public function getDuplicateField()
    {                           
        // Create the unique token 
        $uniqueId = uniqid();

        // Create session variable
        craft()->httpSession->add('duplicateFieldId', $uniqueId);
    }

    //
    // Time Method
    //

    public function verifyTimeField()
    {
        $time = time();
        $posted = (int)craft()->request->getPost('__UATIME', time());

        // Time operations must be done after values have been properly assigned and casted
        $diff = ($time - $posted);
        $min = 5;

        if ($diff > $min) {
            return true;
        }

        return false;
    }

    public function getTimeField()
    {
        return sprintf('<input type="hidden" id="__UATIME" name="__UATIME" value="%s" />', time() );
    }





    // Protected Methods
    // =========================================================================

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

