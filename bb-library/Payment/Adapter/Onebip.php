<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2010-2012 BoxBilling (http://www.boxbilling.com)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @version   $Id$
 */
class Payment_Adapter_Onebip extends Payment_AdapterAbstract
{
    public function init()
    {
        
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  false,
            'description'     =>  'Onebip is a system that allows mobile phone subscribers to transfer pre-paid or post-paid credit from their SIM card to another use.',
            'form'  => array(
                'username' => array('email', array(
                            'label' => 'The email address associated to your Onebip account.',
                    ),
                 ),
                'api_key' => array('password', array(
                            'label' => 'OneBip API key. Stored in OneBip account settings. Used to validate payment notification',
                    ),
                 ),
                'logo_url' => array('text', array(
                            'label' => 'URL to your company logo. The URL of the 360x45-pixel image displayed as your logo in the upper left corner of the Onebip payment pages',
                    ),
                 ),
            ),
        );
    }

    /**
     * Return payment gateway type
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    /**
     * Return payment gateway call url
     * @return string
     */
    public function getServiceURL()
    {
		return 'https://www.onebip.com/otms2/';
    }

    /**
     * Init call to webservice or return form params
     * @param Payment_Invoice $invoice
     */
    public function singlePayment(Payment_Invoice $invoice)
    {
        $data = array();
        $data['username'] = $this->getParam('username');
        $data['description'] = $invoice->getTitle();
        $data['price'] = $invoice->getTotalWithTax() * 100;
        $data['currency'] = $invoice->getCurrency();
        $data['command'] = 'express_pay';
        
        $data['item_code'] = $invoice->getId();
        $data['return_url'] = $this->getParam('return_url');
        $data['notify_url'] = $this->getParam('notify_url');
        $data['cancel_url'] = $this->getParam('cancel_url');
        
        $c = $invoice->getBuyer();
        $data['customer_email'] = $c->getEmail();
        $data['customer_firstname'] = $c->getFirstName();
        $data['customer_lastname'] = $c->getLastName();
        $data['customer_cell'] = $c->getPhone();
        $data['customer_country'] = $c->getCountry();
        
        $data['logo_url'] = $this->getParam('logo_url');
        
        //throw new Exception(print_r($data, 1));
        return $data;
    }

    /**
     * Perform recurent payment
     * @param Payment_Invoice $invoice
     */
    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Payment_Exception('Not implemented yet');
    }
    
    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn    = $data['get'];
        $server = $data['server'];
        
        if (isset($ipn['hash'])) {
            $my_api_key = $this->getParam('api_key');
            $basename = basename($server['REQUEST_URI']);
            $pos = strrpos($basename, "&hash");
            $basename_without_hash = substr($basename, 0, $pos);    
            $my_hash = md5($my_api_key . $basename_without_hash);
            if ($my_hash != $ipn['hash']) {
                $this->setOutput('ERROR: Invalid hash code');
                return false;
            }
        }
        
        $this->setOutput('OK');
        return true;
    }
    
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['get'];
        
        // Onebip parameters:
        $payment_id         = $ipn['payment_id'];
        $country            = $ipn['country'];
        $currency           = $ipn['currency'];
        $price              = $ipn['price'];
        $tax                = $ipn['tax'];
        $commission         = $ipn['commission'];
        $amount             = $ipn['amount'];
        $original_price     = $ipn['original_price'];
        $original_currency  = $ipn['original_currency'];

        $tx = new Payment_Transaction();
        $tx->setId($payment_id);
        $tx->setAmount($price / 100);
        $tx->setCurrency($currency);
        $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        return $tx;
    }
}