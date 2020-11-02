<?php

namespace Laravel\Flutterwave;

use Laravel\Flutterwave\Rave;
use Laravel\Flutterwave\EventHandlerInterface;

class billEventHandler implements EventHandlerInterface
{
    /**
     * This is called when the Rave class is initialized
     * */
    public function onInit($initializationData)
    {
        // Save the transaction to your DB.
    }

    /**
     * This is called only when a transaction is successful
     * */
    public function onSuccessful($transactionData)
    {
        // Get the transaction from your DB using the transaction reference (txref)
        // Check if you have previously given value for the transaction. If you have, redirect to your successpage else, continue
        // Comfirm that the transaction is successful
        // Confirm that the chargecode is 00 or 0
        // Confirm that the currency on your db transaction is equal to the returned currency
        // Confirm that the db transaction amount is equal to the returned amount
        // Update the db transaction record (includeing parameters that didn't exist before the transaction is completed. for audit purpose)
        // Give value for the transaction
        // Update the transaction to note that you have given value for the transaction
        // You can also redirect to your success page from here
        if ($transactionData["data"]["chargecode"] === '00' || $transactionData["data"]["chargecode"] === '0') {
            echo "Transaction Completed";
        } else {
            $this->onFailure($transactionData);
        }
    }

    /**
     * This is called only when a transaction failed
     * */
    public function onFailure($transactionData)
    {
        // Get the transaction from your DB using the transaction reference (txref)
        // Update the db transaction record (includeing parameters that didn't exist before the transaction is completed. for audit purpose)
        // You can also redirect to your failure page from here
    }

    /**
     * This is called when a transaction is requeryed from the payment gateway
     * */
    public function onRequery($transactionReference)
    {
        // Do something, anything!
    }

    /**
     * This is called a transaction requery returns with an error
     * */
    public function onRequeryError($requeryResponse)
    {
        // Do something, anything!
    }

    /**
     * This is called when a transaction is canceled by the user
     * */
    public function onCancel($transactionReference)
    {
        // Do something, anything!
        // Note: Somethings a payment can be successful, before a user clicks the cancel button so proceed with caution
    }

    /**
     * This is called when a transaction doesn't return with a success or a failure response. This can be a timedout transaction on the Rave server or an abandoned transaction by the customer.
     * */
    public function onTimeout($transactionReference, $data)
    {
        // Get the transaction from your DB using the transaction reference (txref)
        // Queue it for requery. Preferably using a queue system. The requery should be about 15 minutes after.
        // Ask the customer to contact your support and you should escalate this issue to the flutterwave support team. Send this as an email and as a notification on the page. just incase the page timesout or disconnects
    }
}

class Bill
{
    protected $payment;
    protected $handler;

    public function __construct()
    {
        $secret_key = config('flutterwave.secret_key');
        $prefix = config('app.name');

        $this->payment = new Rave($secret_key, $prefix);
        $this->type = array('AIRTIME','DSTV','DSTV BOX OFFICE', 'Postpaid', 'Prepaid', 'AIRTEL', 'IKEDC TOP UP','EKEDC POSTPAID TOPUP', 'EKEDC PREPAID TOPUP', 'LCC', 'KADUNA TOP UP');
    }

    /**
     * Sets the event hooks for all available triggers
     * @param object $handler This is a class that implements the Event Handler Interface
     * @return object
     * */
    public function eventHandler($handler)
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * Gets the event hooks for all available triggers
     * @return object
     * */
    public function getEventHandler()
    {
        if ($this->handler) {
            return $this->handler;
        }

        return new billEventHandler;
    }

    public function payBill($array)
    {
        if (gettype($array['amount']) !== 'integer') {
            throw new \Exception("Specified amount should be an integer and not a string", 1);
        }

        if (!in_array($array['type'], $this->type, true)) {
            throw new \Exception("The Type specified in the payload  is not {$this->type[0]}, {$this->type[1]}, {$this->type[2]} or {$this->type[3]}", 1);
        }

        switch ($array['type']) {
            case 'DSTV':
                //set type to dstv
                $this->type = 'DSTV';
                break;

            case 'EKEDC POSTPAID TOPUP':
                //set type to ekedc
                $this->type = 'EKEDC POSTPAID TOPUP';
                break;

            case 'LCC':
                //set type to lcc
                $this->type = 'LCC';
                break;

            case 'AIRTEL':
                //set type to airtel
                $this->type = 'AIRTEL';
                break;

            case 'Postpaid':
                //set type to postpaid
                $this->type = 'Postpaid';
                break;

            case 'IKEDC TOP UP':
                //set type to ikedc
                $this->type = 'IKEDC TOP UP';
                break;

            case 'KADUNA TOP UP':
                //set type to kaduna top up
                $this->type = 'KADUNA TOP UP';
                break;

            case 'DSTV BOX OFFICE':
                //set type to dstv box office
                $this->type = 'DSTV BOX OFFICE';
                break;

            default:
                //set type to airtime
                $this->type = 'AIRTIME';
                break;
        }

        $this->payment->eventHandler($this->getEventHandler())
        //set the endpoint for the api call
        ->setEndPoint("v3/bills");

        return $this->payment->bill($array);
    }

    public function bulkBill($array)
    {
        if (!array_key_exists('bulk_reference', $array) || !array_key_exists('callback_url', $array) || !array_key_exists('bulk_data', $array)) {
            throw new \Exception("Please Enter the required body parameters for the request", 1);
        }

        $this->payment->eventHandler($this->getEventHandler())

        ->setEndPoint('v3/bulk-bills');

        return $this->payment->bulkBills($array);
    }

    public function getBill($array)
    {
        $this->payment->eventHandler($this->getEventHandler());

        if (array_key_exists('reference', $array) && !array_key_exists('from', $array)) {
            $this->payment->setEndPoint('v3/bills/'.$array['reference']);
        } elseif (array_key_exists('code', $array) && !array_key_exists('customer', $array)) {
            $this->payment->setEndPoint('v3/bill-items');
        } elseif (array_key_exists('id', $array) && array_key_exists('product_id', $array)) {
            $this->payment->setEndPoint('v3/billers');
        } elseif (array_key_exists('from', $array) && array_key_exists('to', $array)) {
            if (isset($array['page']) && isset($array['reference'])) {
                $this->payment->setEndPoint('v3/bills');
            } else {
                $this->payment->setEndPoint('v3/bills');
            }
        }

        return $this->payment->getBill($array);
    }

    public function getBillCategories()
    {
        $this->payment->eventHandler($this->getEventHandler())

        ->setEndPoint('v3');

        return $this->payment->getBillCategories();
    }

    public function getAgencies()
    {
        $this->payment->eventHandler($this->getEventHandler())

        ->setEndPoint('v3');

        return $this->payment->getBillers();
    }
}
