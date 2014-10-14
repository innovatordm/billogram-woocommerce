<?php
spl_autoload_register(function($c){@include preg_replace('#\\\|_(?!.+\\\)#','/',$c).'.php';});
/* Include the Billogram API library */
use Billogram\Api as BillogramAPI;
use Billogram\Api\Exceptions\ObjectNotFoundError;

/**
   *
   * Billogram Api Wrapper class
   * Info:
   * Invoices can only be assigned to existing customers
   * So in order to invoice a new customer, the customer has to be created first
   * and then the invoice can be created.
*/
class BillogramApiWrapper {
      
    private $api,
            $query,
            $signKey,
            $invoice,
            $customer,
            $customerData = array( // Values for customers
                'name' => 'Holger Holger',
                'company_type' => 'individual',
                'org_no' => '',
                'address' => array(
                    'street_address' => 'Skeppsmäklargatan 7',
                    'zipcode' => '12069',
                    'city' => 'Stockholm',
                    'country' => 'SE'
                ),
                'contact' => array(
                    'email' => 'test@example.com'
                )
            ),
            $invoiceVal = array(
                'invoice_date' => '', // Set invoice date to today, by default
                'due_date' => '2001-01-01',
                'currency' => 'SEK',
                'customer' => array(
                    'customer_no' => '', // Must be defined!
                ),
                'invoice_fee' => 0,
                'items' => array(array()),
                'callbacks' => array(
                    'sign_key' => '',
                    'url' => ''
                )
            );

    function __construct($apiUser = '', $apiPassword = '') {
        /* 
        Load an instance of the Billogram API library using your API ID and
        API password. You can also pass an app identifier for better debugging.
        For testing you will most likely also use another API base url. 
       */
        $apiId = '1862-gWAS*wsU';
        $apiPassword = '460899fa73bd1c65898d379b12f3e61b';
        $identifier = 'Innovator test instance';
        $apiBaseUrl = 'https://sandbox.billogram.com/api/v2';
        $this->api = new BillogramAPI($apiId, $apiPassword, $identifier, $apiBaseUrl);
    }

    /****************** Invoice related functions ******************/
    
    /*
    * Attempts to create an invoice at billogram 
    */
    public function createInvoice() {
        $this->invoice = $this->api->billogram->create($this->invoiceVal);
    }
    
    /*
    * Adds an item to the invoice data
    * Define invoice data locally before creating the invoice
    */
    public function addItem($item = 1, $price = 0, $vat = 25, $title = 'Not defined') {
        $item = array(
            'count' => $count,
            'price' => $price,
            'vat' => $vat,
            'title' => $title,
        );
        // Push item to invoice array
        array_push($this->invoice->items, $item);
    }

    /****************** Shared functions ******************/

    /*
    *  Set options for customer or invoice
    *  $options must be an array and be formatted as 'optionName' => value
    */
    public function setOptions($options, $type = 'invoice') {
        if(is_array($options)) {
            if($type === 'invoice') {
                foreach ($options as $option => $key) { // loop through options to change
                    $this->invoiceVal[$option] = $key;
                }
            } elseif ($type === 'customer') {
                foreach ($options as $option => $key) {
                    $this->customerData[$option] = $key;
                }
            } else throw new Exception("Invalid option type", 1);
        }  else throw new Exception("$options must be an array!", 1);
        
    }

    /****************** Customer related functions ******************/

    /*
    * Attempts to create a new customer at billogram
    * Define customer data locally before creating the customer
    * 
    */
    public function createCustomer() {
        $this->customer = $this->api->customers->create($this->customerData);
    }
    /*
    * Checks if customer exists based on the specified value
    *
    */
    public function customerExists($value='', $field = 'contact:email') {
        if($email !== '') {
            $this->query = $this->api->customers->query()->makeFilter('field', $field, $value);
            
            if($this->query->count() > 0)
                return true;
            return false;
        } else throw new Exception("You must define an email for the function!", 1); 
    }
    /****************** Misc functions ******************/
}