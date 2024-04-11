<?php

namespace SatisPay;

/* SatisPay class */

use SatispayGBusiness\Api;
use SatispayGBusiness\ApiAuthentication;
use SatispayGBusiness\Consumer;
use SatispayGBusiness\Payment;
use SatispayGBusiness\PreAuthorizedPaymentToken;
use SatispayGBusiness\Request;

class SatisPay
{
    private static $mode;

    /**
     * Create a new controller instance.
     *
     * @param string $mode
     * @return mixed
     */
    public function __construct($mode)
    {
        return self::$mode = $mode;
    }

    /**
     * Create Order Payment
     *
     * @param object $Data
     * $Data->Intent:
     * - MATCH_CODE: to create a payment that has to be paid scanning a Dynamic Code
     * - MATCH_USER: to create a payment request for a specific consumer
     * - REFUND: to partially/completely refund a Payment that is ACCEPTED
     * - PRE_AUTHORIZED: to create a payment with a pre-authorized token
     *
     * @return mixed
     */
    public static function createOrder(object $Data)
    {
        if (self::Authentication()) {
            if ($Settings = self::_getSettings()) {
                if (self::Api($Settings)) {
                    $Payment = new Payment();
                    if ($payment = $Payment->create(self::_getData($Data))) {
                        return json_encode($payment);
                    }
                }
            }
        }
        return false;
    }

    /**
     * Create Authorize Payment
     * for recurring payment
     *
     * @param object $Data
     * $Data->Intent:
     * - MATCH_CODE: to create a payment that has to be paid scanning a Dynamic Code
     * - MATCH_USER: to create a payment request for a specific consumer
     * - REFUND: to partially/completely refund a Payment that is ACCEPTED
     * - PRE_AUTHORIZED: to create a payment with a pre-authorized token
     *
     * @return mixed
     */
    public static function createAuthorize(object $Data)
    {
        if (self::Authentication()) {
            if ($Settings = self::_getSettings()) {
                if (self::Api($Settings)) {
                    $Payment = new Payment();
                    if ($payment = $Payment->create(self::_getData($Data))) {
                        return json_encode($payment);
                    }
                }
            }
        }
        return false;
    }


    /**
     * Refund a Payment
     *
     * @param object $Data
     * @return mixed
     */
    public static function refundOrder(object $Data)
    {
        if (self::Authentication()) {
            if ($Settings = self::_getSettings()) {
                if (self::Api($Settings)) {
                    $Payment = new Payment();
                    if ($payment = $Payment->create(self::_getData($Data))) {
                        return json_encode($payment);
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get Payment
     *
     * @param object $Data
     * @return mixed
     */
    public static function captureOrder(object $Data)
    {
        if (self::Authentication()) {
            if ($Settings = self::_getSettings()) {
                if (self::Api($Settings)) {
                    $Payment = new Payment();
                    if ($payment = $Payment->get($Data->id)) {
                        return json_encode($payment);
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get Payment
     *
     * @param object $Data
     * @return mixed
     */
    public static function updateOrder(object $Data)
    {
        if (self::Authentication()) {
            if ($Settings = self::_getSettings()) {
                if (self::Api($Settings)) {
                    $Payment = new Payment();
                    if ($payment = $Payment->update($Data->id, self::_getData($Data))) {
                        return json_encode($payment);
                    }
                }
            }
        }
        return false;
    }

    /**
     * Authentication
     *
     * are used for the first time connection to SatisPay
     * are required to generate keys for all future connection
     *
     * @return mixed
     */
    private static function Authentication()
    {
        // ensure if is the first time to generate keys
        $config = config('satispay');
        if ($config[self::$mode]['Token'] === null) {
            return true; // already have keys
        } else {
            // Authenticate and generate the keys
            if ($Api = new Api()) {
                if (self::$mode === 'Sandbox') {
                    $Api->setSandbox(true);
                }
                if ($Keys = $Api->authenticateWithToken($config[self::$mode]['Token'])) {
                    // Export keys
                    $config[self::$mode]['PublicKey'] = $Keys->publicKey;
                    $config[self::$mode]['PrivateKey'] = $Keys->privateKey;
                    $config[self::$mode]['KeyId'] = $Keys->keyId;
                    $config[self::$mode]['Token'] = null;
                    if (file_put_contents(
                        config_path() . '/satispay.php',
                        "<?php\n" .
                            "/**\n * Config File for SatisPay Controller\n * the Token key will be null with generated keys\n */ \n" .
                            "return " .
                            str_replace(['array ', '(', ')'], ['', '[', ']'], var_export($config, true) .
                                ";\n")
                    )) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Get Settings
     */
    private static function _getSettings()
    {
        if ($settings = config('satispay')) {
            return (object) $settings[self::$mode];
        }
        return false;
    }

    /**
     * Api Caller
     *
     * @param object $Settings
     */
    private static function Api(object $Settings)
    {
        if ($Settings) {
            if ($Api = new Api()) {
                if (self::$mode === 'Sandbox') {
                    $Api->setSandbox(true);
                }
                $Api->setPublicKey($Settings->PublicKey);
                $Api->setPrivateKey($Settings->PrivateKey);
                $Api->setKeyId($Settings->KeyId);
                return $Api;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Create Data Transiction
     *
     * @param object $Data the Order
     *
     * $Data->Intent:
     * - MATCH_CODE: to create a payment that has to be paid scanning a Dynamic Code
     * - MATCH_USER: to create a payment request for a specific consumer
     * - REFUND: to partially/completely refund a Payment that is ACCEPTED
     * - PRE_AUTHORIZED: to create a payment with a pre-authorized token
     * - CANCEL: to delete a payment
     *
     * @return array
     */
    private static function _getData(object $Data)
    {
        $Settings = self::_getSettings();
        if (in_array($Data->Intent, ['MATCH_CODE', 'MATCH_USER', 'PRE_AUTHORIZED', 'REFUND'])) {
            $data = [
                "flow" => $Data->Intent,
                "amount_unit" => (isset($Data->Tipo->subtotale)) ? (number_format(round($Data->Tipo->totale + $Data->Tipo->subtotale, 2), 2) * 100) : (number_format(round($Data->Tipo->totale, 2), 2) * 100), //  100,
                "currency" => "EUR",
                "external_code" => $Data->Tipo->numero, // "my_order_id",
                //"callback_url" => $Settings->CallBack . "?PaymentType=SatisPay&PaymentId={uuid}",
                //"redirect_url" => $Settings->Redirect,
                /*
                "metadata" => [
                    "order_id" => $Data->Ordine->numero, // "my_order_id",
                    //"user" => "my_user_id",
                    "payment_id" => $Data->Ordine->numero, //"my_payment",
                    "session_id" => $Data->Ordine->numero, //"my_session",
                    //"key" => "value"
                ]
            */
            ];
        }
        if (in_array($Data->Intent, ['REFUND'])) {
            $data = [
                "flow" => $Data->Intent,
                "amount_unit" => (isset($Data->Tipo->subtotale)) ? (number_format(round($Data->Tipo->totale + $Data->Tipo->subtotale, 2), 2) * 100) : (number_format(round($Data->Tipo->totale, 2), 2) * 100), //  100,
                "currency" => "EUR",
                "parent_payment_uid" => $Data->id,
            ];
        }
        if (in_array($Data->Intent, ['CANCEL'])) {
            $data = [
                "action" => $Data->Intent,
            ];
        }
        return $data;
    }
}
