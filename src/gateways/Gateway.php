<?php

namespace digitalpros\commerce\authorize\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;
use digitalpros\commerce\authorize\models\AuthorizePaymentForm;
use digitalpros\commerce\authorize\AuthorizePaymentBundle;
use digitalpros\commerce\authorize\events\AuthorizePaymentEvent as AuthorizePaymentEvent;
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

use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\models\PaymentSource;
use craft\helpers\UrlHelper;
use Omnipay\Common\CreditCard as CreditCard;

/**
 * Gateway represents Authorize.net gateway
 *
 * @author    Digital Pros - Special Thanks to Pixel & Tonic, Inc. <support@pixelandtonic.com>
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
    
    /**
     * @var string
     */
    public $savePaymentMethods;
    
    /**
     * @var string
     */
    public $savedCardPrefix; 
    
    /**
     * @var string
     */
    public $plans; 
    
    // Events
    // =========================================================================
    
    /**
     * @event AuthorizePaymentEvent - The event that is triggered just before a payment is sent to Authorize.net.
     *
     * ```php
     * use craft\commerce\events\AuthorizePaymentEvent;
     * use digitalpros\commerce\authorize\gateways\Gateway;
     * use yii\base\Event;
     *
     * Event::on(
     *     Gateway::class,
     *     Gateway::EVENT_BEFORE_AUTHORIZE_PAYMENT,
     *     function(AuthorizePaymentEvent $event) {
     *         // @var string $transaction
     *         $transaction = $event->$transaction;        
     *         // @var string $invoiceNumber
     *         $invoiceNumber = $event->invoiceNumber;        
     *         // @var string $description
     *         $description = $event->description;
     *     }
     * );
     * ```
     */
     
    public const EVENT_BEFORE_AUTHORIZE_PAYMENT = 'beforeAuthorizePayment'; 

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
    public function getPaymentFormHtml(array $params): string
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
    public function getSettingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('commerce-authorize/gatewaySettings', ['gateway' => $this]);
    }
    
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        
        Event::on(Gateway::class, Gateway::EVENT_BEFORE_GATEWAY_REQUEST_SEND, function(GatewayRequestEvent $e) {
              
              $this->orderId = $e->transaction->orderId;

              // Set the tokens in the request so that Credit Card Validation isn't needed.
              //var_dump($e);
              if(!empty($_POST["paymentForm"])) {
                $formData = $_POST["paymentForm"][array_key_first($_POST["paymentForm"])];
              }
              
              if($this->acceptJS == 1 && isset($formData['tokenDescriptor']) && isset($formData['token'])) {
                  $e->request->setOpaqueDataDescriptor($formData['tokenDescriptor']);
                  $e->request->setOpaqueDataValue($formData['token']);
              }
              
        });
        
        Event::on(Gateway::class, Gateway::EVENT_BEFORE_SEND_PAYMENT_REQUEST, function(SendPaymentRequestEvent $e) {
            
            $e->modifiedRequestData = $e->requestData;
            
            // We're using the Order ID instead of truncating the hash where available as Authorize only allows 20 characters in the RefID field.
            // Not all requests will have a refId (like the Create Profile request), so we'll check for that first, then the Order ID.
            
            if(isset($e->requestData->refId)) {
                if(!empty($this->orderId)) {
                    $e->modifiedRequestData->refId = $this->orderId;
                } else {
                    $e->modifiedRequestData->refId = mb_substr($e->modifiedRequestData->refId,0,20);
                }
            }
            
            // If using Accept.js, we'll remove the Credit Card details before sending to Authorize.net.
            
            if($this->acceptJS == 1) {
                
                if(isset($e->modifiedRequestData->transactionRequest->transactionType) && $e->modifiedRequestData->transactionRequest->transactionType != "refundTransaction" && $e->modifiedRequestData->transactionRequest->transactionType != "voidTransaction" && $e->modifiedRequestData->transactionRequest->transactionType != "priorAuthCaptureTransaction" && empty($e->modifiedRequestData->transactionRequest->profile) ) {
                    
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

        $cart = Commerce::getInstance()->getCarts()->getCart();
        
        // We need to set the Gateway to CIM for all transactions that use stored Payment Sources. 
        // We'll also check to see if we are deleting a payment source.
        
        if(NULL !== $cart->getPaymentSource() || 
        (isset($_POST['action']) && strpos($_POST['action'], 'payment-sources/delete') !== false) || 
        (isset($_POST['action']) && strpos($_POST['action'], 'payment-sources/add') !== false)) {
            
            /** @var OmnipayGateway $gateway */
            $gateway = Omnipay::create('AuthorizeNet_CIM');

            $gateway->setApiLoginId(Craft::parseEnv($this->apiLoginId));
            $gateway->setTransactionKey(Craft::parseEnv($this->transactionKey));
            $gateway->setDeveloperMode($this->developerMode);
            
            $gateway->setParameter('invoiceNumber', substr($cart->number, 0, 7));
            
        } else {
            
            /** @var OmnipayGateway $gateway */
            $gateway = Omnipay::create($this->getGatewayClassName());
    
            $gateway->setApiLoginId(Craft::parseEnv($this->apiLoginId));
            $gateway->setTransactionKey(Craft::parseEnv($this->transactionKey));
            $gateway->setDeveloperMode($this->developerMode);

            $gateway->setParameter('invoiceNumber', substr($cart->number, 0, 7));
            
        }
        
        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName(): string
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

        // Grab the parent transaction, check for TYPE_CAPTURE. If found, grab the parent's parent transaction.

        if(!empty($transaction->parentId)) {

            // Let's get the Parent Transaction and check it for a Capture type.

            $parentTransaction = Commerce::getInstance()->getTransactions()->getTransactionById($transaction->parentId);
            if(!empty($parentTransaction) && $parentTransaction->type == TransactionRecord::TYPE_CAPTURE) {

                // All good. Now let's check for the parent Authorize type.

                if(!empty($parentTransaction->parentId)) {
                    $authorizeTransaction = Commerce::getInstance()->getTransactions()->getTransactionById($parentTransaction->parentId);
                    if(!empty($authorizeTransaction) && $authorizeTransaction->type == TransactionRecord::TYPE_AUTHORIZE) {
                        $transaction = $authorizeTransaction;
                    }
                }
            }

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
    
    public function supportsPaymentSources(): bool
    {
        if(isset($this->savePaymentMethods)) {
            return $this->savePaymentMethods;
        } else {
            return FALSE;
        }     
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {
        if (!$this->supportsPaymentSources()) {
            throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
        }

        $cart = Commerce::getInstance()->getCarts()->getCart();

        if (!$address = $cart->getBillingAddress()) {
            $customer = Commerce::getInstance()->getCustomers()->getCustomerByCustomerId($customerId);

            if (!$customer || !($address = $customer->getPrimaryBillingAddress())) {
                throw new NotSupportedException(Craft::t('commerce', 'You need a billing address to save a payment source.'));
            }

            $cart->setBillingAddress($address);
            $cart->billingAddressId = $address->id;
        }
                
        // Start Modifications
        
        $fullName = $cart->getBillingAddress()->firstName . " " . $cart->getBillingAddress()->lastName;
        
        if($this->acceptJS == 1) {
            $sourceData->number = "";
            $sourceData->month = "";
            $sourceData->year = "";
            $sourceData->cvv = "";
            $sourceData->expiry = "";
        }
        
        $card = $this->createCard($sourceData, $cart);
        
        /* According to line 221 of https://github.com/thephpleague/omnipay-authorizenet/blob/master/src/Message/CIMCreateCardRequest.php,
        // using the OpaqueDataDescription (Accept.JS) means that the Omnipay Gateway cannot update existing payment profiles. If Accept.JS
        // is enabled, we'll add a random number to the description so that a new payment profile will be created for each card. */
        
        if($this->acceptJS == 1) { 
            $description = 'Commerce Customer - ' . $customerId . "-" . rand(100000000, 999999999);
        } else {
            $description = 'Commerce Customer';
        }

        $request = [
            'name' => $fullName,
            'email' => $cart->getEmail(), 
            'customerType' => 'individual',
            'customerId' => $customerId,
            'description' => $description,
            'forceCardUpdate' => true,
            'card' => $card,
            'currency' => $cart->paymentCurrency
        ];
        
        if(!empty($_POST["paymentForm"])) {
            $formData = $_POST["paymentForm"][array_key_first($_POST["paymentForm"])];
        }
        
        if($this->acceptJS == 1 && isset($formData['tokenDescriptor']) && isset($formData['token'])) {
            $request['opaqueDataDescriptor'] = $formData['tokenDescriptor'];
            $request['opaqueDataValue'] = $formData['token'];
        }
          
        $cardGateway = Omnipay::create('AuthorizeNet_CIM');
        
        $cardGateway->setApiLoginId(Craft::parseEnv($this->apiLoginId));
        $cardGateway->setTransactionKey(Craft::parseEnv($this->transactionKey));
        $cardGateway->setDeveloperMode($this->developerMode);

        $this->populateRequest($request, $sourceData);
        $createCardRequest = $cardGateway->createCard($request);

        $response = $this->sendRequest($createCardRequest);
        
        $request = Craft::$app->getRequest();
        $description = (string)$request->getBodyParam('description');  
        
        if(!empty($description)) {
            $cardDescription = $description;
        } else {
            $cardDescription = $this->savedCardPrefix . substr($card->getNumber(), -4);
        }
        
        $currentSource = null;
        
        // Need to pass ID if it exists, or Craft throws a fit.
        if($this->acceptJS != 1) {
            $paymentSources = Commerce::getInstance()->getPaymentSources()->getAllPaymentSourcesByCustomerId($customerId); 
            
            if(!empty($paymentSources)) {
                foreach($paymentSources as $source) {
                    if($source->token == $response->getCardReference()) {
                        $currentSource = $source->id;
                    }
                }
            }
        }
        
        $paymentSource = new PaymentSource([
            'id'=> $currentSource,
            'customerId' => $customerId,
            'gatewayId' => $this->id,
            'token' => $response->getCardReference(),
            'response' => $response->getMessage(),
            'description' => $cardDescription
        ]);
        
        // End Modifications

        return $paymentSource;
    }
    
    /**
     * Prepare a request for execution by transaction and a populated payment form.
     *
     * @param Transaction     $transaction
     * @param BasePaymentForm $form        Optional for capture/refund requests.
     *
     * @return mixed
     * @throws \yii\base\Exception
     */
    protected function createRequest(Transaction $transaction, BasePaymentForm $form = null): mixed
    {
        
        $order = $transaction->getOrder();
        $transactionReference = json_decode($transaction->reference);
        
        // For authorize and capture we're referring to a transaction that already took place so no card or item shenanigans.
        if (in_array($transaction->type, [TransactionRecord::TYPE_REFUND, TransactionRecord::TYPE_CAPTURE], false) && (isset($form->customerProfile) || (isset($transactionReference->card) && $transactionReference->card->number == null))) {
            
            // Start Modifications 
            // Due to Accept.js, there are certain cases where the Card number may not be available in the transactions reference, 
            // but it's stored in the response from the gateway. Grab that response, and give it back to 
            // the transaction before it's sent back for processing.
            
            // Alternatively, we could request this information from Authorize.net, but this appears to be the most efficient
            // workaround for now. 
           
            if(!empty($transactionReference) && !isset($transactionReference->card) || (isset($transactionReference->card) && $transactionReference->card->number == null)) {
                $authorizeTransaction = Commerce::getInstance()->getTransactions()->getTransactionById($transaction->parentId);
                if(!empty($authorizeTransaction)) {
                    $authorizeResponse = json_decode($authorizeTransaction->response);
                    
                    if(!empty($authorizeResponse->transactionResponse->accountNumber)) {
                        $cardNumber = str_replace("X", "", $authorizeResponse->transactionResponse->accountNumber);
                    } 
                }
              $transactionReference->card = new \stdClass();
              $transactionReference->card->number = $cardNumber;
              
              // Expiration date isn't needed - let's add a placeholder.
              $transactionReference->card->expiry = "XXXX";
              
              // Roll the data back into the transaction reference.
              $transaction->reference = json_encode($transactionReference);
              
              if(isset(json_decode($transaction->reference)->cardReference)) {
                  $card = array("customerProfile" => json_decode($transaction->reference)->cardReference);
              } else {
                  $card = array(json_decode($transaction->reference)->card);
              }
              
            }

            // End Modifications
            
            $itemBag = $this->getItemBagForOrder($order);
            
            $request = $this->createPaymentRequest($transaction, $card, $itemBag);
            $this->populateRequest($request, $form);
            
        } else {

            $card = null;
            
            // Start Modifications 
            // If the card needs to be replaced with CIM details, reference those here. 
            // Everything above (and below this statement) comes from the Craft Omnipay Gateway.php file.
            
            if ($form && !isset($form->customerProfile)) {
                $card = $this->createCard($form, $order);
            } elseif (isset($form->customerProfile)) {
                $card = array("customerProfile" => $form->customerProfile);
            }
            
            // End Modifications

            $itemBag = $this->getItemBagForOrder($order);

            $request = $this->createPaymentRequest($transaction, $card, $itemBag);
            $this->populateRequest($request, $form);
        }
 
        
        return $request;
    }
    
    /**
     * Create the parameters for a payment request based on a trasaction and optional card and item list.
     *
     * @param Transaction $transaction The transaction that is basis for this request.
     * @param CreditCard  $card        The credit card being used
     * @param ItemBag     $itemBag     The item list.
     *
     * @return array
     * @throws \yii\base\Exception
     */
    protected function createPaymentRequest(Transaction $transaction, $card = null, $itemBag = null): array
    {
        $params = ['commerceTransactionId' => $transaction->id, 'commerceTransactionHash' => $transaction->hash];
        
        // Start Modifications 
        
        // Fire a 'beforeAuthorizePayment' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_AUTHORIZE_PAYMENT)) {
            
            // Trigger the event, and set the default values.
            $this->trigger(self::EVENT_BEFORE_AUTHORIZE_PAYMENT, $purchaseEvent = new AuthorizePaymentEvent([
                'transaction' => $transaction,
                'invoiceNumber' => $transaction->order->shortNumber,
                'description' => Craft::t('commerce', 'Order').' #'.$transaction->orderId,
            ]));
            
            // Set the values from the purchase event
            $invoiceNumber = $purchaseEvent->invoiceNumber;
            $description = $purchaseEvent->description;
            
        } else {
            
            // In case there's no event handlers.
            $invoiceNumber = $transaction->order->shortNumber;
            $description = Craft::t('commerce', 'Order').' #'.$transaction->orderId;
        }
        
        $request = [
            'amount' => $transaction->paymentAmount,
            'currency' => $transaction->paymentCurrency,
            'transactionId' => $transaction->hash,
            'invoiceNumber' => (!empty($invoiceNumber) ? substr($invoiceNumber, 0, 20) : ''),
            'description' => (!empty($description) ? substr($description, 0, 255) : ''),
            'clientIp' => Craft::$app->getRequest()->userIP ?? '',
            'transactionReference' => $transaction->hash,
            'returnUrl' => UrlHelper::actionUrl('commerce/payments/complete-payment', $params),
            'cancelUrl' => UrlHelper::siteUrl($transaction->order->cancelUrl),
        ];
        
        // End Modifications

        // Set the webhook url.
        if ($this->supportsWebhooks()) {
            $request['notifyUrl'] = $this->getWebhookUrl($params);
            $request['notifyUrl'] = str_replace('rc.craft.local', 'umbushka.eu.ngrok.io', $request['notifyUrl']);
        }

        // Do not use IPv6 loopback
        if ($request['clientIp'] ===  '::1') {
            $request['clientIp'] = '127.0.0.1';
        }

        // custom gateways may wish to access the order directly
        $request['order'] = $transaction->order;
        $request['orderId'] = $transaction->order->id;

        // Stripe only params
        $request['receiptEmail'] = $transaction->order->email;

        // Paypal only params
        $request['noShipping'] = 1;
        $request['allowNote'] = 0;
        $request['addressOverride'] = 1;
        $request['buttonSource'] = 'ccommerce_SP';
        
        // Start Modifications 
        // If the card has been replaced with CIM details, reference those here. 
        // Everything above (and below this statement) comes from the Craft Omnipay Gateway.php file.

        if(!empty($card) && $card instanceof CreditCard) {
            $request['card'] = $card;
        } elseif (isset($card['customerProfile'])) {
            $request['cardReference'] = $card['customerProfile'];
        }

        // End Modifications

        if ($itemBag) {
            $request['items'] = $itemBag;
        }
        
        return $request;
    }
    
     /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
        if (!$this->supportsPaymentSources()) {
            throw new NotSupportedException(Craft::t('commerce', 'Payment sources are not supported by this gateway'));
        }

        // Some gateways support creating but don't support deleting. Assume deleted, then.
        if (!$this->gateway()->supportsDeleteCard()) {
            return true;
        }
        
        // Start Modifications
        // Decode the Card Details so it can be deleted.
        // Everything above (and below this statement) comes from the Craft Omnipay Gateway.php file.
        
        $deleteCardRequest = $this->gateway()->deleteCard(json_decode($token, true));
        
        // End Modifications
        
        $response = $this->sendRequest($deleteCardRequest);

        return $response->isSuccessful();
    }
   
        
}
