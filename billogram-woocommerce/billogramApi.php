<?php

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
            $signKey,
            $invoice,
            $customer
            $customerData = array( // Values for customers
                'name' => '',
                'company_type' => 'individual',
                'org_no' => '',
                'address' => array(
                    'street_address' => '',
                    'zipcode' => '',
                    'city' => '',
                    'country' => ''
                ),
                'contact' => array(
                    'email' => ''
                )
            ),
            $invoiceVal = array(
                'invoice_date' => date("Y-m-d"), // Set invoice date to today, by default
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
        $apiBaseUrl = 'https://billogram.com/api/v2';
        $this->api = new BillogramAPI($apiId, $apiPassword, $identifier, $apiBaseUrl);
        spl_autoload_register(array(this,'autoload'));
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
        $this->customer = $this->api->customer->create($this->customerData);
    }

    /****************** Misc functions ******************/
   
    private function autoload($className) {
        $className = ltrim($className, '\\');
        $fileName  = '';
        $namespace = '';
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) .
                DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        require $fileName;
    }
}
/* Creates a customer */
echo '- Creating customer:' . "\n";
$customerObject = $api->customers->create(array(
    'name' => 'Company 1 AB',
    'address' => array(
        'street_address' => 'Street 22',
        'zipcode' => '12345',
        'city' => 'Stockholm',
        'country' => 'SE'
    ),
    'contact' => array(
        'email' => 'invoicing@example.org'
    )
));

echo 'Customer "' . $customerObject->name .
    '" created, with customer number: ' . $customerObject->customer_no . '.' .
    "\n";

echo "\n";

/* Create a billogram */
echo '- Creating billogram:' . "\n";
$signKey = uniqid(); // This could be whatever secret string you want.
echo 'The sign_key is set to "' . $signKey . '".' . "\n";
$billogramObject = $api->billogram->create(array(
    'invoice_date' => '2013-09-11',
    'due_date' => '2013-10-11',
    'currency' => 'SEK',
    'customer' => array(
        'customer_no' => $customerObject->customer_no,
    ),
    'invoice_fee' => 0,
    'items' => array(array(
        'count' => 1,
        'price' => 300,
        'vat' => 25,
        'title' => 'Test item',
    )),
    'callbacks' => array(
        'sign_key' => $signKey,
        'url' => 'http://example.org/billogram-callback'
    ),
));

$billogramId = $billogramObject->id;

echo 'Billogram "' . $billogramObject->id .
    '" created with a total sum: ' . $billogramObject->total_sum . ' ' .
    $billogramObject->currency . ', state "' . $billogramObject->state . '".' .
    "\n";

/* Send billogram with the delivery method "Letter", could also be "Email"
   or "Email+Letter". The $billogramObject will refresh it's data with
   up to date data. */
$billogramObject->send('Letter');

echo 'Billogram "' . $billogramObject->id .
    '" has been sent ' . $billogramObject->attested_at . ', state "' .
    $billogramObject->state . '".' . "\n";


/* Wait for the PDF file to be generated and then get the PDF content.
   Usually, we don't recommend waiting for the PDF content as the invoice will
   already be sent out to the customer (via Letter, or Email). */
echo 'Waiting for PDF to be generated, this may take a few seconds.' . "\n";
do {
    $pdfContent = '';
    try {
        $pdfContent = $billogramObject->getInvoicePdf();
        break;
    } catch (ObjectNotFoundError $e) { // PDF has not been created yet.
        sleep(1);
    }
} while (true);
echo 'PDF content stored in $pdfContent (' . strlen($pdfContent) .
    ' bytes).' . "\n";

/* Credits the full amount of the billogram which will cause the billogram
   to generate a credit invoice and ultimately go to an ended state (in this
   case the state "Credited"). */
$billogramObject->creditFull();

echo 'Billogram "' . $billogramObject->id .
    '" has been credited, remaining sum: ' . $billogramObject->remaining_sum .
    ' ' . $billogramObject->currency . ', state "' . $billogramObject->state .
    '".' . "\n";

echo "\n";

/* Fetch an existing billogram by id. We stored the id in $billogramId earlier
   when we created the billogram in the first example. */
$billogramObject = $api->billogram->get($billogramId);

echo 'Billogram "' . $billogramObject->id . '" fetched. This is a ' .
    'billogram for "' . $billogramObject->customer->name . '".' . "\n";

echo "\n";

/* Fetch a set of a all customers, the default limit is to get 100 at a time
   but this limit could be increased to 500 at a time. */
echo '- Fetching customers' . "\n";
$customersQuery = $api->customers->query()->order('created_at', 'asc');
$totalPages = $customersQuery->totalPages();
for ($page = 1; $page <= $totalPages; $page++) {
    $customersArray = $customersQuery->getPage($page);
    /* Loop over the customersArray and do something with the customers
       here. */
}

echo $customersQuery->count() . ' customers returned.' . "\n";

echo "\n";

/* Fetch a set of a all unattested billogram. This time we filter on the
   'state' parameter and fetch 50 at a time. We'll also sort on due_date */
echo '- Fetching set of unattested billogram' . "\n";
$billogramQuery = $api->billogram->query()->
    pageSize(50)->
    filterField('state', 'Unattested')->
    order('due_date', 'asc');
$totalPages = $billogramQuery->totalPages();
for ($page = 1; $page <= $totalPages; $page++) {
    $billogramArray = $billogramQuery->getPage($page);
    /* Loop over the billogramArray and do something with the billogram
       here. */
    foreach ($billogramArray as $billogram) {
        /* For example we could send them by invoking the send() method.
           Note: However, if we do something with the first 50 here,
           page number 2 will not actually return the original 100-150
           (instead of 50-100 as we would want). */
        // $billogram->send('Email');
    }
}

echo $billogramQuery->count() . ' unattested billogram returned.' . "\n";

echo "\n";
