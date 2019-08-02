<?php

namespace digitalpros\commerce\authorize\models;

use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\PaymentSource;

/**
 * WorldPay Payment form model.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class AuthorizePaymentForm extends CreditCardPaymentForm
{
	
	public $customerProfile;
	
    /**
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true)
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
    public function populateFromPaymentSource(PaymentSource $paymentSource)
    {
       
        $this->customerProfile = $paymentSource->token;
        
    }
}