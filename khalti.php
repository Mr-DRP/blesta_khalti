<?php
/**
 * Razorpay
 *
 * Razorpay Webhook reference:
 * https://razorpay.com/docs/webhooks/
 * Razorpay API reference:
 * https://razorpay.com/docs/api/
 *
 * @package blesta
 * @subpackage blesta.components.gateways.razorpay
 * @copyright Copyright (c) 2020, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class Khalti extends NonmerchantGateway
{
    /**
     * @var array An array of meta data for this gateway
     */
    private $meta;

    /**
     * Construct a new merchant gateway
     */
    public function __construct()
    {
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this gateway
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this gateway
        Language::loadLang('khalti', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Sets the currency code to be used for all subsequent payments
     *
     * @param string $currency The ISO 4217 currency code to be used for subsequent payments
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * Create and return the view content required to modify the settings of this gateway
     *
     * @param array $meta An array of meta (settings) data belonging to this gateway
     * @return string HTML content containing the fields to update the meta data for this gateway
     */
    public function getSettings(array $meta = null)
    {
        $this->view = $this->makeView('settings', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('meta', $meta);

        return $this->view->fetch();
    }

    /**
     * Validates the given meta (settings) data to be updated for this gateway
     *
     * @param array $meta An array of meta (settings) data to be updated for this gateway
     * @return array The meta data to be updated in the database for this gateway, or reset into the form on failure
     */
    public function editSettings(array $meta)
    {
        // Verify meta data is valid
        $rules = [
            'public_key' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Khalti.!error.public_key.valid', true)
                ]
            ],
            'secret_key' => [
                'valid' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Khalti.!error.secret_key.valid', true)
                ]
            ]
        ];

        $this->Input->setRules($rules);

        // Validate the given meta data to ensure it meets the requirements
        $this->Input->validates($meta);
        // Return the meta data, no changes required regardless of success or failure for this gateway
        return $meta;
    }

    /**
     * Returns an array of all fields to encrypt when storing in the database
     *
     * @return array An array of the field names to encrypt when storing in the database
     */
    public function encryptableFields()
    {
        return ['public_key', 'secret_key'];
    }

    /**
     * Sets the meta data for this particular gateway
     *
     * @param array $meta An array of meta data to set for this gateway
     */
    public function setMeta(array $meta = null)
    {
        $this->meta = $meta;
    }

    /**
     * Returns all HTML markup required to render an authorization and capture payment form
     *
     * @param array $contact_info An array of contact info including:
     *
     *  - id The contact ID
     *  - client_id The ID of the client this contact belongs to
     *  - user_id The user ID this contact belongs to (if any)
     *  - contact_type The type of contact
     *  - contact_type_id The ID of the contact type
     *  - first_name The first name on the contact
     *  - last_name The last name on the contact
     *  - title The title of the contact
     *  - company The company name of the contact
     *  - address1 The address 1 line of the contact
     *  - address2 The address 2 line of the contact
     *  - city The city of the contact
     *  - state An array of state info including:
     *      - code The 2 or 3-character state code
     *      - name The local name of the country
     *  - country An array of country info including:
     *      - alpha2 The 2-character country code
     *      - alpha3 The 3-cahracter country code
     *      - name The english name of the country
     *      - alt_name The local name of the country
     *  - zip The zip/postal code of the contact
     * @param float $amount The amount to charge this contact
     * @param array $invoice_amounts An array of invoices, each containing:
     *
     *  - id The ID of the invoice being processed
     *  - amount The amount being processed for this invoice (which is included in $amount)
     * @param array $options An array of options including:
     *
     *  - description The Description of the charge
     *  - return_url The URL to redirect users to after a successful payment
     *  - recur An array of recurring info including:
     *      - start_date The date/time in UTC that the recurring payment begins
     *      - amount The amount to recur
     *      - term The term to recur
     *      - period The recurring period (day, week, month, year, onetime) used in
     *          conjunction with term in order to determine the next recurring payment
     * @return mixed A string of HTML markup required to render an authorization and
     *  capture payment form, or an array of HTML markup
     */
    public function buildProcess(array $contact_info, $amount, array $invoice_amounts = null, array $options = null)
    {
         // Load the models required
         Loader::loadModels($this, ['Clients','Invoices']);

         $client = $this->Clients->get($contact_info['client_id']);
         // Load the Paystack API
         $public = $this->meta['public_key'];
 
         $params = [
             'amount' => $amount * 100,
             'public_key' => $public,
             'product_name' => $options['description'],
             'product_id' => $invoice_amounts['0']['id'],
             'callback_url' => Configure::get('Blesta.gw_callback_url') . Configure::get('Blesta.company_id') . '/khalti/',
             'return_url' => $options['return_url'],
             'metadata' => (object)[
                'client_id' => $contact_info['client_id'],
                'invoices' => $this->serializeInvoices($invoice_amounts)
            ],
         ];
 
         // Get the url to redirect the client to

         return $this->buildForm($params);
     }
 
     /**
      * Builds the HTML form.
      *
      * @return string The HTML form
      */
     private function buildForm($params)
     {
         $this->view = $this->makeView('process', 'default', str_replace(ROOTWEBDIR, '', dirname(__FILE__) . DS));
 
         // Load the helpers required for this view
         Loader::loadHelpers($this, ['Form', 'Html']);
 
         $this->view->set('params', $params);
         return $this->view->fetch();
     }
 
    /**
     * Validates the incoming POST/GET response from the gateway to ensure it is
     * legitimate and can be trusted.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, sets any errors using Input if the data fails to validate
     *
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's
     *      original transaction (in the case of refunds)
     */
    public function validate(array $get, array $post)
    {
        $callback_data = json_decode(@file_get_contents("php://input"));

        $args = http_build_query(array(
            'token' => $callback_data->{'token'},
            'amount'  => $callback_data->{'amount'}
            ));
            $url = "https://khalti.com/api/payment/verify/";
            
            # Make the call using API.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,$args);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            $headers = ['Authorization: Key '.$this->meta['secret_key']];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            
            // for debug
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            
            // Response
            $response = curl_exec($ch);
            
            curl_close($ch);
            $vars = json_decode($response);
            
            switch ($vars->state->name) {
                case 'Completed':
                    $status = 'approved';
                    break;
                default:
                    $status = 'declined';
                    break;
            }
            // Force 2-decimal places only
            $amount = number_format(($vars->amount / 100), 2, '.', '');
            $currency = 'NPR';
            $this->log(
                'validate1',
                json_encode(
                    [
                        'client_id' => $callback_data->{'merchant_client'},
                            'amount' => $this->ifSet($amount),
                            'currency' => $this->ifSet($currency),
                            'status' => $status,
                            'transaction_id' => $this->ifSet($vars->idx),
                            'invoices' => $this->unserializeInvoices($callback_data->{'merchant_invoice'})
                    ],
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                ),
                'output',
                true
            );
        return [
            'client_id' => $callback_data->{'merchant_client'},
                'amount' => $this->ifSet($amount),
                'currency' => $this->ifSet($currency),
                'status' => $status,
                'transaction_id' => $this->ifSet($vars->idx),
                'invoices' => $this->unserializeInvoices($vars->meta->{'merchant_invoice'})
        ];
    }

    /**
     * Returns data regarding a success transaction. This method is invoked when
     * a client returns from the non-merchant gateway's web site back to Blesta.
     *
     * @param array $get The GET data for this request
     * @param array $post The POST data for this request
     * @return array An array of transaction data, may set errors using Input if the data appears invalid
     *
     *  - client_id The ID of the client that attempted the payment
     *  - amount The amount of the payment
     *  - currency The currency of the payment
     *  - invoices An array of invoices and the amount the payment should be applied to (if any) including:
     *      - id The ID of the invoice to apply to
     *      - amount The amount to apply to the invoice
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - transaction_id The ID returned by the gateway to identify this transaction
     *  - parent_transaction_id The ID returned by the gateway to identify this transaction's original transaction
     */
    public function success(array $get, array $post)
    { 
        $client_id = $this->ifSet($get['client_id']);

            $url = "https://khalti.com/api/v2/merchant-transaction/".$get['idx']."/";

            # Make the call using API.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $headers = ['Authorization: Key '.$this->meta['secret_key']];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Response
            $response = curl_exec($ch);
            $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $vars = json_decode($response);
            
            switch ($vars->state->name) {
                case 'Completed':
                    $status = 'approved';
                    break;
                default:
                    $status = 'declined';
                    break;
            }
            // Force 2-decimal places only
            $amount = number_format(($vars->amount / 100), 2, '.', '');
            $currency = 'NPR';

            $this->log(
                'success2',
                json_encode(
                    $status,
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                ),
                'output',
                true
            );

        return [
            'client_id' => $client_id,
                'amount' => $this->ifSet($amount),
                'currency' => $this->ifSet($currency),
                'status' => $status,
                'transaction_id' => $this->ifSet($vars->idx),
                'invoices' => $this->unserializeInvoices($vars->meta->merchant_invoice)
        ];
    }

    /**
     * Refund a payment
     *
     * @param string $reference_id The reference ID for the previously submitted transaction
     * @param string $transaction_id The transaction ID for the previously submitted transaction
     * @param float $amount The amount to refund this transaction
     * @param string $notes Notes about the refund that may be sent to the client by the gateway
     * @return array An array of transaction data including:
     *
     *  - status The status of the transaction (approved, declined, void, pending, reconciled, refunded, returned)
     *  - reference_id The reference ID for gateway-only use with this transaction (optional)
     *  - transaction_id The ID returned by the remote gateway to identify this transaction
     *  - message The message to be displayed in the interface in addition to the standard
     *      message for this transaction status (optional)
     */
    public function refund($reference_id, $transaction_id, $amount, $notes = null)
    {
        if (isset($this->Input))
            $this->Input->setErrors($this->getCommonError("unsupported"));
    }

    /**
     * Serializes an array of invoice info into a string
     *
     * @param array A numerically indexed array invoices info including:
     *
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     * @return string A serialized string of invoice info in the format of key1=value1|key2=value2
     */
    private function serializeInvoices(array $invoices)
    {
        $str = '';
        foreach ($invoices as $i => $invoice) {
            $str .= ($i > 0 ? '|' : '') . $invoice['id'] . '=' . $invoice['amount'];
        }
        return $str;
    }

    /**
     * Unserializes a string of invoice info into an array
     *
     * @param string A serialized string of invoice info in the format of key1=value1|key2=value2
     * @return array A numerically indexed array invoices info including:
     *
     *  - id The ID of the invoice
     *  - amount The amount relating to the invoice
     */
    private function unserializeInvoices($str)
    {
        $invoices = [];
        $temp = explode('|', $str);
        foreach ($temp as $pair) {
            $pairs = explode('=', $pair, 2);
            if (count($pairs) != 2) {
                continue;
            }
            $invoices[] = ['id' => $pairs[0], 'amount' => $pairs[1]];
        }
        return $invoices;
    }

    private function getApi($secret_key)
    {
        // Load library methods
        Loader::load(dirname(__FILE__) . DS . 'lib' . DS . 'khaltiAPI.php');

        return new KhaltiApi($secret_key);
    }
}
