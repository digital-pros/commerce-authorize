<?php

namespace digitalpros\commerce\authorize\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;
use craft\base\Model;

/**
 * WorldPay Payment form model.
 *
 * @author    Digital Pros - Special thanks to Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class AuthorizeSubscriptionForm extends CreditCardPaymentForm
{
	
	public $customerProfile;
    
    public $address;
    
    public $city;
    
    public $state;
    
    public $postalCode;
    
    public $country;
    
    public $phone;
    
    public $email;
    
    public $cardDescription;
	
    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
	    
        parent::setAttributes($values, $safeOnly);

        if (isset($values['token'])) {
            $this->token = $values['token'];
        }
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
	    
        if (empty($this->token) && !isset($this->customerProfile)) {
            return parent::rules();
        }

        return [];
    }
    
    /**
     * @inheritdoc
     */
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
       
        $this->customerProfile = $paymentSource->token;
        
    }
}