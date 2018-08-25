<?php
namespace verbb\comments\services;

use Craft;
use craft\base\Component;

class ProtectService extends Component
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
        $jsset = Craft::$app->getRequest()->getBodyParam('__JSCHK');
 
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
        $uahash = Craft::$app->getRequest()->getBodyParam('__UAHASH');
        $uahome = Craft::$app->getRequest()->getBodyParam('__UAHOME');

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
        if (Craft::$app->getRequest()->getBodyParam('beesknees')) {
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
        if (Craft::$app->getSession()->get('duplicateFieldId')) {
            // If there is a valid unique token set, unset it and return true.      
            Craft::$app->getSession()->remove('duplicateFieldId');       

            return true;            
        }

        return false;
    }

    public function getDuplicateField()
    {                           
        // Create the unique token 
        $uniqueId = uniqid();

        // Create session variable
        Craft::$app->getSession()->add('duplicateFieldId', $uniqueId);
    }

    //
    // Time Method
    //

    public function verifyTimeField()
    {
        $time = time();
        $posted = (int)Craft::$app->getRequest()->getBodyParam('__UATIME', time());

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
        return sprintf('<input type="hidden" id="__UATIME" name="__UATIME" value="%s" />', time());
    }


    // Protected Methods
    // =========================================================================

    protected function getDomainHash()
    {
        $domain = Craft::$app->getRequest()->getHostInfo();

        return $this->getHash($domain);
    }

    protected function getUaHash()
    {
        return $this->getHash(Craft::$app->getRequest()->getUserAgent());
    }

    protected function getHash($str)
    {
        return md5(sha1($str));
    }
}

