<?php

namespace digitalpros\commerce\authorize\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use digitalpros\commerce\authorize\models\AuthorizePaymentForm;
use digitalpros\commerce\authorize\AuthorizePaymentBundle;
use craft\commerce\omnipay\events\SendPaymentRequestEvent;
use craft\commerce\omnipay\events\GatewayRequestEvent;
use craft\commerce\models\Transaction;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Omnipay;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\AuthorizeNet\AIMGateway as OmnipayGateway;
use yii\base\Event;
use yii\base\NotSupportedException;

/**
 * Gateway represents WorldPay gateway
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class Gateway extends CreditCardGateway
{
    // Properties
    // =========================================================================

    /**
     * @var string
     */
    public $apiLoginId;

    /**
     * @var string
     */
    public $transactionKey;
    
    /**
     * @var string
     */
    public $publicKey;

    /**
     * @var string
     */
    public $developerMode;
    
    /**
     * @var bool
     */
    public $acceptJS;
    
    /**
    * @var bool
    */
    public $disableAcceptData;
    
    /**
     * @var bool
     */
    public $voidRefunds;
    
    /**
     * @var bool
     */
    public $insertForm;
    
    /**
     * @var string
     */
    public $paymentButton;
    
    /**
     * @var string
     */
    private $orderId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Authorize.net');
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params)
    {
        $defaults = [
            'gateway' => $this,
            'paymentForm' => $this->getPaymentFormModel()
        ];

        $params = array_merge($defaults, $params);
		
	        $view = Craft::$app->getView();
	
	        $previousMode = $view->getTemplateMode();
	        $view->setTemplateMode(View::TEMPLATE_MODE_CP);
			
			// Register Accept.JS if it's enabled in the backend.
		
			if($this->acceptJS == 1) {
			
			// Check to see if developer mode is enabled.
			
				if($this->developerMode == 1) { 
					$view->registerJsFile('https://jstest.authorize.net/v1/Accept.js');
				} else {
					$view->registerJsFile('https://js.authorize.net/v1/Accept.js');
				}
				
			} 
		
	        $view->registerAssetBundle(AuthorizePaymentBundle::class);
	
	        $html = Craft::$app->getView()->renderTemplate('commerce-authorize/paymentForm', $params);
	        $view->setTemplateMode($previousMode);
        
			return $html;
        
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new AuthorizePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {
        return Craft::$app->getView()->renderTemplate('commerce-authorize/gatewaySettings', ['gateway' => $this]);
    }
    
    /**
     * @inheritdoc
     */
    public function init() 
    {
	    
	    Event::on(Gateway::class, Gateway::EVENT_BEFORE_GATEWAY_REQUEST_SEND, function(GatewayRequestEvent $e) {
          	
          	$this->orderId = $e->transaction->orderId;
          	
          	// Set the tokens in the request so that Credit Card Validation isn't needed.
          	
          	if($this->acceptJS == 1 && isset($_POST['tokenDescriptor']) && isset($_POST['token'])) {
          		$e->request->setOpaqueDataDescriptor($_POST['tokenDescriptor']);
          		$e->request->setOpaqueDataValue($_POST['token']);
          	}
          	
        });
        
	    Event::on(Gateway::class, Gateway::EVENT_BEFORE_SEND_PAYMENT_REQUEST, function(SendPaymentRequestEvent $e) {
            
            $e->modifiedRequestData = $e->requestData;
            
            // We're using the Order ID instead of truncating the hash where available as Authorize only allows 20 characters in the RefID field.
            
            if(!empty($this->orderId)) {
	            $e->modifiedRequestData->refId = $this->orderId;
            } else {
	            $e->modifiedRequestData->refId = mb_substr($e->modifiedRequestData->refId,0,20);
            }
            
            // If using Accept.js, we'll remove the Credit Card details before sending to Authorize.net.
            
            if($this->acceptJS == 1) {
	            
	            if(isset($e->modifiedRequestData->transactionRequest->transactionType) && $e->modifiedRequestData->transactionRequest->transactionType != "refundTransaction" && $e->modifiedRequestData->transactionRequest->transactionType != "voidTransaction" ) {
		            
	            	unset($e->modifiedRequestData->transactionRequest->payment->creditCard);
					unset($e->modifiedRequestData->transactionRequest->payment->cardNumber);
					unset($e->modifiedRequestData->transactionRequest->payment->expirationDate);
					unset($e->modifiedRequestData->transactionRequest->payment->cardCode);

	            }
	            	            
			}
            
        }); 

    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = Omnipay::create($this->getGatewayClassName());

        $gateway->setApiLoginId($this->apiLoginId);
        $gateway->setTransactionKey($this->transactionKey);
        $gateway->setDeveloperMode($this->developerMode);
       
		
        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName()
    {
        return '\\'.OmnipayGateway::class;
    }
    
    /**
     * Prepare a refund request from request data and reference of the transaction being refunded.
     *
     * @param array  $request
     * @param string $reference
     *
     * @return RequestInterface
     */
    protected function prepareVoidRequest($request, string $reference): RequestInterface
    {
        /** @var AbstractRequest $refundRequest */
        $voidRequest = $this->gateway()->void($request);
        $voidRequest->setTransactionReference($reference);
        
        return $voidRequest;
    }
        
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        if (!$this->supportsRefund()) {
            throw new NotSupportedException(Craft::t('commerce', 'Refunding is not supported by this gateway'));
        }
                       
        $request = $this->createRequest($transaction);
        $refundRequest = $this->prepareRefundRequest($request, $transaction->reference);
        $processRefund = $this->performRequest($refundRequest, $transaction);
        
        if(!$processRefund->isSuccessful() && $this->voidRefunds == "1") {
	        $voidRequest = $this->prepareVoidRequest($request, $transaction->reference);
	        	        
	        $order = craft\commerce\elements\Order::find()->id($transaction->orderId)->one();
	        if($transaction->amount < $order->getTotalPaid()) {
		    	throw new NotSupportedException(Craft::t('commerce', 'Enter the full transaction amount to void the transaction, or disable void in the gateway settings.'));
	        }
	        
	        $processVoid = $this->performRequest($voidRequest, $transaction);
	        return $processVoid;
        } else {
	        return $processRefund;
        }

    }
        
}
