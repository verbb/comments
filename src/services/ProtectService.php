<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;
use craft\helpers\Json;

use GuzzleHttp\Client;

class ProtectService extends Component
{
    // Constants
    // =========================================================================

    const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const API_URL = 'https://www.google.com/recaptcha/api.js';


    // Public Methods
    // =========================================================================

    public function getFields(): string
    {
        return $this->getOriginField() . $this->getHoneypotField() . $this->getJSField() . $this->getRecaptcha();
    }

    public function verifyFields(): bool
    {
        return $this->verifyOriginField() && $this->verifyHoneypotField() && $this->verifyJSField() && $this->verifyRecaptcha();
    }

    //
    // reCAPTCHA
    //

    public function getRecaptcha(): void
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->recaptchaEnabled) {
            Craft::$app->view->registerJsFile(self::API_URL . '?render=' . $settings->recaptchaKey, [
                'defer' => 'defer',
                'async' => 'async',
            ]);
        }
    }

    public function verifyRecaptcha(): bool
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->recaptchaEnabled) {
            $captchaResponse = Craft::$app->getRequest()->getParam('g-recaptcha-response');

            if (!$captchaResponse) {
                return false;
            }

            $client = new Client();

            $response = $client->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret' => $settings->recaptchaSecret,
                    'response' => $captchaResponse,
                    'remoteip' => Craft::$app->request->getRemoteIP(),
                ],
            ]);

            $result = Json::decode((string)$response->getBody(), true);

            if (!$result['success']) {
                return false;
            }
        }

        return true;
    }

    //
    // Javascript Method
    //

    public function verifyJSField(): bool
    {
        $jsset = Craft::$app->getRequest()->getBodyParam('__JSCHK');

        return $jsset != '';
    }

    public function getJSField(): string
    {
        // Create the unique token 
        $uniqueId = uniqid();

        // Set a hidden field with no value and use javascript to set it.
        $output = '<input type="hidden" id="__JSCHK_' . $uniqueId . '" name="__JSCHK" />';
        $output .= '<script type="text/javascript">document.getElementById("__JSCHK_' . $uniqueId . '").value = "' . $uniqueId . '";</script>';

        return $output;
    }

    //
    // Origin Method
    //

    public function verifyOriginField(): bool
    {
        $uahash = Craft::$app->getRequest()->getBodyParam('__UAHASH');
        $uahome = Craft::$app->getRequest()->getBodyParam('__UAHOME');

        // Run a user agent check
        if (!$uahash || $uahash != $this->getUaHash()) {
            return false;
        }

        // Run originating domain check
        if (!$uahome || $uahome != $this->getDomainHash()) {
            return false;
        }

        // Passed
        return true;
    }

    public function getOriginField(): string
    {
        $output = '<input type="hidden" id="__UAHOME" name="__UAHOME" value="' . $this->getDomainHash() . '" />';
        $output .= '<input type="hidden" id="__UAHASH" name="__UAHASH" value="' . $this->getUaHash() . '"/>';

        return $output;
    }

    //
    // Honeypot Method
    //

    public function verifyHoneypotField(): bool
    {
        // The honeypot field must be left blank
        if (Craft::$app->getRequest()->getBodyParam('beesknees')) {
            return false;
        }

        return true;
    }

    public function getHoneypotField(): string
    {
        $output = '<div id="beesknees_wrapper" style="display:none;">';
        $output .= '<label>Leave this field blank</label>';
        $output .= '<input type="text" id="beesknees" name="beesknees" style="display:none;" />';
        $output .= '</div>';

        return $output;
    }

    //
    // Duplicate Method
    //

    public function verifyDuplicateField(): bool
    {
        if (Craft::$app->getSession()->get('duplicateFieldId')) {
            // If there is a valid unique token set, unset it and return true.      
            Craft::$app->getSession()->remove('duplicateFieldId');

            return true;
        }

        return false;
    }

    public function getDuplicateField(): void
    {
        // Create the unique token 
        $uniqueId = uniqid();

        // Create session variable
        Craft::$app->getSession()->set('duplicateFieldId', $uniqueId);
    }

    //
    // Time Method
    //

    public function verifyTimeField(): bool
    {
        $time = time();
        $posted = (int)Craft::$app->getRequest()->getBodyParam('__UATIME', time());

        // Time operations must be done after values have been properly assigned and cast
        $diff = ($time - $posted);
        $min = 5;

        return $diff > $min;
    }

    public function getTimeField(): string
    {
        return sprintf('<input type="hidden" id="__UATIME" name="__UATIME" value="%s" />', time());
    }

    public function getCaptchaHtml(): string
    {
        $settings = Comments::$plugin->getSettings();

        if (!$settings->recaptchaEnabled) {
            return '';
        }

        Craft::$app->view->registerJsFile(self::API_URL . '?render=' . $settings->recaptchaKey, ['defer' => 'defer', 'async' => 'async']);

        // Craft::$app->view->registerJs('grecaptcha.ready(function() {
        //     grecaptcha.execute(' . $settings->recaptchaKey . ', {action: "homepage"}).then(function(token) {

        //     });
        // });', View::POS_END);

        // Craft::$app->view->registerCss('#g-recaptcha-response {
        //     display: block !important;
        //     position: absolute;
        //     margin: -78px 0 0 0 !important;
        //     width: 302px !important;
        //     height: 76px !important;
        //     z-index: -999999;
        //     opacity: 0;
        // }');

        return '';
        // return '<div class="g-recaptcha" data-sitekey="' . $settings->recaptchaKey . '"></div>';
    }



    // Protected Methods
    // =========================================================================

    protected function getDomainHash(): string
    {
        $domain = Craft::$app->getRequest()->getHostInfo();

        return $this->getHash($domain);
    }

    protected function getUaHash(): string
    {
        return $this->getHash(Craft::$app->getRequest()->getUserAgent());
    }

    protected function getHash($str): string
    {
        return md5(sha1($str));
    }
}

