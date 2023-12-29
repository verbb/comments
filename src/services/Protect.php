<?php
namespace verbb\comments\services;

use verbb\comments\Comments;

use Craft;
use craft\base\Component;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\web\View;

use GuzzleHttp\Client;

class Protect extends Component
{
    // Constants
    // =========================================================================

    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    public const API_URL = 'https://www.google.com/recaptcha/api.js';


    // Public Methods
    // =========================================================================

    public function getFields(bool $loadInline = false): string
    {
        return $this->getOriginField() . $this->getHoneypotField() . $this->getJSField() . $this->getRecaptcha($loadInline);
    }

    public function verifyFields(): bool
    {
        return $this->verifyOriginField() && $this->verifyHoneypotField() && $this->verifyJSField() && $this->verifyRecaptcha();
    }

    //
    // reCAPTCHA
    //

    public function getRecaptcha(bool $loadInline = false): ?string
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->recaptchaEnabled) {
            if ($loadInline) {
                return Html::jsFile(self::API_URL . '?render=' . $settings->getRecaptchaKey(), [
                    'defer' => 'defer',
                    'async' => 'async',
                ]);
            } else {
                Craft::$app->getView()->registerJsFile(self::API_URL . '?render=' . $settings->getRecaptchaKey(), [
                    'defer' => 'defer',
                    'async' => 'async',
                ]);
            }
        }

        return null;
    }

    public function verifyRecaptcha(): bool
    {
        $settings = Comments::$plugin->getSettings();

        if ($settings->recaptchaEnabled) {
            $captchaResponse = Craft::$app->getRequest()->getParam('g-recaptcha-response');

            // Protect against invalid data being sent. No need to log, likely malicious
            if (!$captchaResponse || !is_string($captchaResponse)) {
                return false;
            }

            $client = Craft::createGuzzleClient();

            $response = $client->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret' => $settings->getRecaptchaSecret(),
                    'response' => $captchaResponse,
                    'remoteip' => Craft::$app->getRequest()->getRemoteIP(),
                ],
            ]);

            $result = Json::decode((string)$response->getBody(), true);

            if (isset($result['score'])) {
                return ($result['score'] >= $settings->recaptchaMinScore);
            }

            return $result['success'] ?? false;
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

