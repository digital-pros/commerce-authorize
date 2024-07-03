<?php

namespace digitalpros\commerce\authorize\gateways;

use digitalpros\commerce\authorize\Authorize as AUTH;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\omnipay\base\CreditCardGateway;

use digitalpros\commerce\authorize\models\AuthorizeSubscriptionForm;
use digitalpros\commerce\authorize\AuthorizeSubscriptionBundle;
use digitalpros\commerce\authorize\models\AuthorizePlan;
use digitalpros\commerce\authorize\models\Authorize as AuthorizeRequestResponse;
use digitalpros\commerce\authorize\responses\AuthorizeSubscriptionResponse;
use digitalpros\commerce\authorize\events\SubscriptionSuspendEvent;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

use craft\commerce\omnipay\events\SendPaymentRequestEvent;
use craft\commerce\omnipay\events\GatewayRequestEvent;
use craft\commerce\models\Transaction;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Omnipay;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\AuthorizeNet\AIMGateway as OmnipayGateway;
use yii\base\Event;
use yii\base\Exception;
use yii\base\NotSupportedException;

use craft\commerce\base\SubscriptionGateway as BaseGateway;
use craft\commerce\base\Plan;
use craft\commerce\base\SubscriptionGateway;
use craft\commerce\base\SubscriptionResponseInterface;
use craft\commerce\elements\Subscription;
use craft\commerce\models\payments\CreditCardPaymentForm;
use craft\commerce\models\subscriptions\CancelSubscriptionForm;
use craft\commerce\models\subscriptions\SubscriptionPayment;

use craft\commerce\models\subscriptions\SubscriptionForm;
use craft\commerce\models\subscriptions\SwitchPlansForm;
use craft\elements\User;
use craft\web\Response as WebResponse;

use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\models\PaymentSource;
use craft\helpers\UrlHelper;
use Omnipay\Common\CreditCard as CreditCard;

use craft\commerce\records\Subscription as SubscriptionRecord;

use craft\helpers\StringHelper;
use craft\helpers\Json;
use craft\helpers\Db as Db;

/**
 * Gateway represents Authorize.net CIM gateway
 *
 * @author    Digital Pros - Special thanks to Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since     1.0
 */
class Subscriptions extends BaseGateway
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
    
    /**
     * @var string
     */
    public $reference; 
    
    /**
     * Authorize.net API Gateway
     */
    private $gateway;
    
    /**
     * @var string
    */
    private $customer;
    
    /**
     * @var string
    */
    private $environment;
    
    /**
     * @var string
    */
    private $paymentSource;
    
    /**
     * @var float
    */
    private $amount;
    
    /**
     * @var string
     */
    public $webhookUrl; 
    
    /**
      * @var string
   */
    public $webhookSignature;
    
    /**
      * @var string
      */
     public $duplicateWindow; 
    
    /**
      * @event CancelSubscriptionEvent The event that is triggered after a subscription is canceled
      *
      * Plugins can get notified after a subscription gets canceled.
      *
      * ```php
      * use digitalpros\commerce\authorize\events\SubscriptionSuspendEvent;
      * use digitalpros\commerce\authorize\gateways\Subscriptions;
      * use yii\base\Event;
      *
      * Event::on(Subscriptions::class, Subscriptions::EVENT_AFTER_SUSPEND_SUBSCRIPTION, function(CancelSubscriptionEvent $e) {
      *     // Do something - maybe refund the user for the remainder of the subscription.
      * });
      * ```
      */
     const EVENT_AFTER_SUSPEND_SUBSCRIPTION = 'afterSuspendSubscription';
    

    // Public Methods
    // =========================================================================
    
    public function init(): void
    {
        parent::init();

        $params = ['gateway' => $this->id];
        $this->webhookUrl = UrlHelper::siteUrl() . 'commerce/webhooks/process-webhook/gateway/' . $this->id;
        
        // All in the 
        
        $this->gateway = new AnetAPI\MerchantAuthenticationType();
        $this->gateway->setName(Craft::parseEnv($this->apiLoginId));
        $this->gateway->setTransactionKey(Craft::parseEnv($this->transactionKey));

        $this->environment = ($this->isDeveloperMode() ? \net\authorize\api\constants\ANetEnvironment::SANDBOX : \net\authorize\api\constants\ANetEnvironment::PRODUCTION);

    }
    
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'Authorize.net Subscriptions');
    }
    
    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('commerce-authorize/subscriptionSettings', ['gateway' => $this]);
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
        
        // Check to see if developer mode is enabled.
        
        if($this->isDeveloperMode()) { 
            $view->registerJsFile('https://jstest.authorize.net/v1/Accept.js');
        } else {
            $view->registerJsFile('https://js.authorize.net/v1/Accept.js');
        }

        $view->registerAssetBundle(AuthorizeSubscriptionBundle::class);

        $html = Craft::$app->getView()->renderTemplate('commerce-authorize/subscribeForm', $params);
        $view->setTemplateMode($previousMode);
    
        return $html;
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new AuthorizeSubscriptionForm();
    }

    /**
     * @inheritdoc
     */
    public function authorize(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new AuthorizeRequestResponse($form);
    }

    /**
     * @inheritdoc
     */
    public function capture(Transaction $transaction, string $reference): RequestResponseInterface
    {
        return new AuthorizeRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completeAuthorize(Transaction $transaction): RequestResponseInterface
    {
        return new AuthorizeRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function completePurchase(Transaction $transaction): RequestResponseInterface
    {
        return new AuthorizeRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function createPaymentSource(BasePaymentForm $sourceData, int $customerId): PaymentSource
    {

        $merchantAuthentication = $this->gateway;
        
        // Set the transaction's refId
        $refId = 'ref' . time();
    
        // Create a Customer Profile Request
        //  1. (Optionally) create a Payment Profile
        //  2. (Optionally) create a Shipping Profile
        //  3. Create a Customer Profile (or specify an existing profile)
        //  4. Submit a CreateCustomerProfile Request
        //  5. Validate Profile ID returned
    
        // Set credit card information for payment profile
        // Create the payment object for a payment nonce
        $opaqueData = new AnetAPI\OpaqueDataType();
        $opaqueData->setDataDescriptor("COMMON.ACCEPT.INAPP.PAYMENT");
        $opaqueData->setDataValue($sourceData->token);
        
        // Add the payment data to a paymentType object
        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setOpaqueData($opaqueData);
    
        // Create the Bill To info for new payment type
        $billTo = new AnetAPI\CustomerAddressType();
        (!empty($sourceData->firstName)) ? $billTo->setFirstName($sourceData->firstName) : "";
        (!empty($sourceData->lastName)) ? $billTo->setLastName($sourceData->lastName) : "";
        (!empty($sourceData->address)) ? $billTo->setAddress($sourceData->address) : "";
        (!empty($sourceData->city)) ? $billTo->setCity($sourceData->city) : "";
        (!empty($sourceData->state)) ? $billTo->setState($sourceData->state) : "";
        (!empty($sourceData->postalCode)) ? $billTo->setZip($sourceData->postalCode) : "";
        (!empty($sourceData->country)) ? $billTo->setCountry($sourceData->country) : "";
        (!empty($sourceData->phone)) ? $billTo->setPhoneNumber($sourceData->phone) : "";
    
        // Create a new CustomerPaymentProfile object
        $paymentProfile = new AnetAPI\CustomerPaymentProfileType();
        $paymentProfile->setCustomerType('individual');
        $paymentProfile->setBillTo($billTo);
        $paymentProfile->setPayment($paymentOne);
        $paymentProfiles[] = $paymentProfile;
    
        // Create a new CustomerProfileType and add the payment profile object
        $customerProfile = new AnetAPI\CustomerProfileType();
        $customerProfile->setDescription("Craft Subscription");
        $customerProfile->setMerchantCustomerId("CS-" . $customerId . "-" . time());
        $customerProfile->setEmail($sourceData->email);
        $customerProfile->setpaymentProfiles($paymentProfiles);
    
        // Assemble the complete transaction request
        $request = new AnetAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setProfile($customerProfile);
    
        // Create the controller and get the response
        $controller = new AnetController\CreateCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->environment);
       
        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            $paymentProfiles = $response->getCustomerPaymentProfileIdList();
            
            /** @var CreditCardPaymentForm $sourceData */
    
            $paymentSource = new PaymentSource();
            $paymentSource->gatewayId = $this->id;
            $paymentSource->token = '{"customerProfileId":"' . $response->getCustomerProfileId() . '","customerPaymentProfileId":"' .  $paymentProfiles[0] . '"}';
            $paymentSource->response = json_encode($response->getMessages()->getMessage());
            $paymentSource->description = (!empty($sourceData->cardDescription) ? $sourceData->cardDescription : "Saved Card for Subscription");

        } else {
            $errorMessages = $response->getMessages()->getMessage();
            throw new Exception(Craft::t('commerce', 'Something went wrong while creating a profile. (' . $errorMessages[0]->getCode() . ') Pleae try again.'));
            //echo "Response : " .  . "  " .$errorMessages[0]->getText() . "\n";
        }
        
        $this->paymentSource = $paymentSource->token;

        return $paymentSource;
    }

    /**
     * @inheritdoc
     */
    public function deletePaymentSource($token): bool
    {
         $merchantAuthentication = $this->gateway;
         $token = json_decode($token);
       
         // Delete an existing customer profile  
         $request = new AnetAPI\DeleteCustomerProfileRequest();
         $request->setMerchantAuthentication($merchantAuthentication);
         $request->setCustomerProfileId($token->customerProfileId);
      
         $controller = new AnetController\DeleteCustomerProfileController($request);
         $response = $controller->executeWithApiResponse($this->environment);
         
         if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") ) {
            // Successful removal.
            return true;
         } else {
            // Failure to remove, we'll assume it was removed already.      
            return true;
         }
    }

    /**
     * @inheritdoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return new AuthorizeRequestResponse($form);
    }

    /**
     * @inheritdoc
     */
    public function processWebHook(): WebResponse
    {
        
        $payload = Craft::$app->request->getBodyParams();
        $headers = Craft::$app->request->getHeaders();
        
        if(!isset($headers['X-ANET-Signature']) && $headers['X-ANET-Signature'] == Craft::parseEnv($this->webhookSignature)) {
           return Craft::$app->end();
        }
        
         switch($payload['eventType']) {

             case "net.authorize.customer.subscription.cancelled": 
             case "net.authorize.customer.subscription.terminated":
             case "net.authorize.customer.subscription.expired":
             case "net.authorize.customer.subscription.failed":
             case "net.authorize.customer.subscription.terminated": 
               $subscription = Subscription::find()->reference($payload['payload']['id'])->one();
               try {
                 if(!empty($subscription)) {
                    $this->expireSubscription($subscription); 
                  } else {
                     Craft::warning('Authorize.net Subscriptions: Failed to expire transaction: ' . $payload['payload']['id']);
                  }
               } catch (\Exception $e) {
                 Craft::warning('Authorize.net Subscriptions: Failed to expire transaction: ' . $payload['payload']['id'] . ': ' . $e->getMessage());
               }
             break;
                       
             // Subscription was suspended
             case "net.authorize.customer.subscription.suspended":
               $subscription = Subscription::find()->reference($payload['payload']['id'])->one();
               try {
                  if(!empty($subscription)) { 
                     $this->suspendSubscription($subscription); 
                  } else {
                     Craft::warning('Authorize.net Subscriptions: Failed to suspend transaction: ' . $payload['payload']['id']);
                  }
               } catch (\Exception $e) {
                 Craft::warning('Authorize.net Subscriptions: Failed to suspend transaction: ' . $payload['payload']['id'] . ': ' . $e->getMessage());
               }
             break;  
             
             // Transaction Captured
             case "net.authorize.payment.authcapture.created": 
             case "net.authorize.payment.capture.created":
             
             // Check if we're dealing with a first-time payment.
             if(isset($payload['payload']['merchantReferenceId'])) {
                
               $subscription = Subscription::find()->reference(str_replace("CS-", "", $payload['payload']['merchantReferenceId']))->one();

               if($subscription->getOrder() !== null && !empty($subscription->getOrder()->currency)) {
                  $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($subscription->getOrder()->currency);
               } else {
                  $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso("USD");
               }

               $payment = new SubscriptionPayment([
                   'paymentAmount' => $payload['payload']['authAmount'],
                   'paymentDate' => strtotime($payload['eventDate']),
                   'paymentReference' => $payload['payload']['id'],
                   'paymentCurrency' => $currency,
                   'paid' => true,
                   'response' => Json::encode($payload['payload']),
               ]);
               
               // We're after the initial start date, so we'll adjust the plan by what we know from the stored plan details.
               $dateAdjust = $subscription->getSubscriptionData()['planData'][1] .$subscription->getSubscriptionData()['planData'][2];
               
               if($subscription->getSubscriptionData()['planData'][2] == "months") {
                  $adjustPaymentDate = $this->sameDateNextMonth($subscription->nextPaymentDate, $subscription->getSubscriptionData()['planData'][1]);
               } else {
                  $adjustPaymentDate = $subscription->nextPaymentDate->modify("+" . $dateAdjust);
               }
               
               try {
                  if(!empty($subscription)) { $this->subscriptionPayment($subscription, $payment, $adjustPaymentDate); }
               } catch (\Exception $e) {
                  Craft::warning('Authorize.net Subscriptions: Failed to update paid transaction: ' . $payload['payload']['id'] . ': ' . $e->getMessage());
               }
              
             // Deal with subsequent notifications.   
             } else {
                
                  $merchantAuthentication = $this->gateway;
                
                  $request = new AnetAPI\GetTransactionDetailsRequest();
                  $request->setMerchantAuthentication($merchantAuthentication);
                  $request->setTransId($payload['payload']['id']);
             
                  $controller = new AnetController\GetTransactionDetailsController($request);
                  $response = $controller->executeWithApiResponse($this->environment);
             
                  if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                     if($response->getTransaction()->getSubscription() !== null) {
                       
                        $subscription = Subscription::find()->reference($response->getTransaction()->getSubscription()->getId())->one();

                        if($subscription->getOrder() !== null && !empty($subscription->getOrder()->currency)) {
                           $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($subscription->getOrder()->currency);
                        } else {
                           $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso("USD");
                        }
        
                        $payment = new SubscriptionPayment([
                           'paymentAmount' => $payload['payload']['authAmount'],
                           'paymentDate' => strtotime($payload['eventDate']),
                           'paymentReference' => $payload['payload']['id'],
                           'paymentCurrency' => $currency,
                           'paid' => true,
                           'response' => Json::encode($payload['payload']),
                        ]);
                       
                        $dateAdjust = $subscription->getSubscriptionData()['planData'][1] . $subscription->getSubscriptionData()['planData'][2];
                        
                        // Adjust for fun PHP error where a month might be skipped if it has less days.
                        if($subscription->getSubscriptionData()['planData'][2] == "months") {
                           $adjustPaymentDate = $this->sameDateNextMonth($subscription->nextPaymentDate, $subscription->getSubscriptionData()['planData'][1]);
                        } else {
                           $adjustPaymentDate = $subscription->nextPaymentDate->modify("+" . $dateAdjust);
                        }
                        
                        try {
                           if(!empty($subscription)) { 
                              $this->subscriptionPayment($subscription, $payment, $adjustPaymentDate);
                           } else {
                              Craft::warning('Authorize.net Subscriptions: Failed to update paid transaction: ' . $payload['payload']['id']);
                           }
                        } catch (\Exception $e) {
                           Craft::warning('Authorize.net Subscriptions: Failed to update paid transaction: ' . $payload['payload']['id'] . ': ' . $e->getMessage());
                        }
                       
                     }
                  }
             }

             break;
         }
        
        return Craft::$app->end();
    }
    
    // Special thanks to derekm for this creative solution.
    // https://stackoverflow.com/questions/3602405/php-datetimemodify-adding-and-subtracting-months/3602421#3602421 
    
    function sameDateNextMonth($createdDate, $months = 1) {
        $currentDate = new \DateTime();
        $addMon = clone $currentDate;
        $addMon->add(new \DateInterval("P" . $months . "M"));
    
        $nextMon = clone $currentDate;
        $nextMon->modify("last day of " . $months . " month");
    
        if ($addMon->format("n") == $nextMon->format("n")) {
            $recurDay = $createdDate->format("j");
            $daysInMon = $addMon->format("t");
            $currentDay = $currentDate->format("j");
            if ($recurDay > $currentDay && $recurDay <= $daysInMon) {
                $addMon->setDate($addMon->format("Y"), $addMon->format("n"), $recurDay);
            }
            return $addMon;
        } else {
            return $nextMon;
        }
    }
    
    function expireSubscription($subscription) {
        if(!$subscription->isCanceled) {
           return Commerce::getInstance()->getSubscriptions()->expireSubscription($subscription);
        } else {
           return true;
        }
    }
    
    function subscriptionPayment($subscription, $payment, $nextBillingDate) {
       
       // Store the transaction in the database.
       $subscriptionData = $subscription->getSubscriptionData($subscription);
       array_push($subscriptionData['transactions'], $payment);
       
       // Reset the billing issues.
       $subscriptionData['billingIssues'] = "";
       
       $subscription->setSubscriptionData($subscriptionData);

       return Commerce::getInstance()->getSubscriptions()->receivePayment($subscription, $payment, $nextBillingDate);
    }

    /**
     * @inheritdoc
     */
    public function refund(Transaction $transaction): RequestResponseInterface
    {
        return new AuthorizeRequestResponse();
    }

    /**
     * @inheritdoc
     */
    public function supportsAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCapture(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompleteAuthorize(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsCompletePurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsPurchase(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPartialRefund(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getCancelSubscriptionFormHtml(Subscription $subscription): string
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function getCancelSubscriptionFormModel(): CancelSubscriptionForm
    {
        return new CancelSubscriptionForm();
    }

    /**
     * @inheritdoc
     */
    public function getPlanSettingsHtml(array $params = []):string
    {
        return Craft::$app->getView()->renderTemplate('commerce-authorize/planSettings', $params);
    }

    /**
     * @inheritdoc
     */
    public function getPlanModel(): Plan
    {
        return new AuthorizePlan();
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionFormModel(): SubscriptionForm
    {
        return new SubscriptionForm();
    }

    /**
     * @inheritdoc
     */
    public function getSwitchPlansFormModel(): SwitchPlansForm
    {
       throw new NotSupportedException(Craft::t('commerce', "Authorize.net Recurring Billing doesn't support switching between plans."));
       //return new SwitchPlansForm();
    }

    /**
     * @inheritdoc
     */
    public function cancelSubscription(Subscription $subscription, CancelSubscriptionForm $parameters): SubscriptionResponseInterface
    {
        $merchantAuthentication = $this->gateway;
        
        $refId = 'ref' . time();
       
        $request = new AnetAPI\ARBCancelSubscriptionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscriptionId($subscription->reference);
    
        $controller = new AnetController\ARBCancelSubscriptionController($request);
        $authorizeResponse = $controller->executeWithApiResponse($this->environment);
        
        $response = new AuthorizeSubscriptionResponse();
        $response->setData($subscription->getSubscriptionData());
        $response->setScheduledForCancellation(true);
        
        if (($authorizeResponse != null) && ($authorizeResponse->getMessages()->getResultCode() == "Ok")) {
           // If the transaction succeeds, return the response.
           return $response;
        } else {
           // If it fails, assume that it's already been canceled.
           return $response;
        }
        
    }

    /**
     * @inheritdoc
     */
    public function getNextPaymentAmount(Subscription $subscription): string
    {
         return $subscription->getSubscriptionData()['planData'][3];
    }

    /**
     * @inheritdoc
     */
   public function getSubscriptionPayments(Subscription $subscription): array
   {
      
      $subscriptionData = $subscription->getSubscriptionData($subscription);
      $authorizeHistory = $this->getPaymentHistory($subscriptionData['customerProfileID']);
       
      if(!empty($authorizeHistory)) {
         echo '<span class="stored-data">Due to limitations in tranasaction records, transactions in this view will always be delimited as USD without currency conversion.</span>';
         $transactionsList = $authorizeHistory;
      } else {
         echo '<span class="stored-data">No payment history available. The first transaction is processing and transactions may take some time to update. Due to limitations in tranasaction records, transactions in this view will always be delimited as USD without currency conversion.</span>';
         
         $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso("USD");
         
          $transactionsList = [];
          foreach ($subscriptionData['transactions'] as $transaction) {
             
              // Check to see if transaction already exists.
               foreach ($transactionsList as $existingTransaction) {
                 if(json_decode($existingTransaction['response'])->id == $transaction['response']['id']) { continue 2; }
               }
              
               if(!empty($subscriptionData['currencyCode'])) {
                  $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso($subscriptionData['currencyCode']);
               } else {
                  $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso("USD");
               }
              
               $transactionsList[] = new SubscriptionPayment([
                  'paymentAmount' => $transaction['paymentAmount'],
                  'paymentDate' => $transaction['paymentDate'],
                  'paymentReference' => $transaction['paymentReference'],
                  'paymentCurrency' => $currency,
                  'paid' => $transaction['paid'],
                  'response' => Json::encode($transaction['response']),
               ]);
          }
      }

       return $transactionsList;
   }


    /**
     * @inheritdoc
     */
    public function getSubscriptionPlanByReference(string $reference): string
    {
        return (isset($this->plans[(int)$reference]) ? json_encode($this->plans[(int)$reference]) : null);
    }

    /**
     * @inheritdoc
     */
    public function getSubscriptionPlans(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function subscribe(User $user, Plan $plan, SubscriptionForm $parameters): SubscriptionResponseInterface
    {
        // We have to wait for Authorize.net to finalize the payment method.
        // We've run a boatload of tests, and it never completes in under 10 seconds.
        sleep(15);
        
        $craftSubscription = new AuthorizeSubscriptionResponse();
        
        $paymentSource = $this->getAllPaymentSources($user->id, $this->paymentSource);
        
        // Grab the latest information from the gateway. Information stored in PlanData is saved when the plan is created.
        // So we'll use this instead to pull the latest information from the Gateway so the plan doesn't have to be re-created.
        
        if(isset($this->plans[$plan->reference])) {
            $planData = $this->plans[$plan->reference];
        } else {
            throw new Exception(Craft::t('commerce', "Plans don't match options in the Gateway settings."));
        }
        
        $startDate = new \DateTime();
        $nextBillingDate = new \DateTime();
        
        $planName = (!empty($planData[0]) ? $planData[0] : "Craft Subscription");
        $planLength = (!empty($planData[1]) ? (string)$planData[1] : 1);
        $planUnit = (!empty($planData[2]) ? $planData[2] : "months");
        $planTotal = (!empty($planData[6]) ? (string)$planData[6] : 9999);
        $planAmount = (!empty($planData[3]) ? (float)$planData[3] : 0);
        $trialAmount = (!empty($planData[4]) ? (float)$planData[4] : 0);
        $trialLength = (!empty($planData[5]) ? (int)$planData[5] : 0);
        $startDate = (!empty($planData[7]) ? $startDate->modify("+" . (int)$planData[7] . " days") : $startDate);
        $startDateDays = (!empty($planData[7]) ? (int)$planData[7] : 0);
        $trialDays = (!empty($parameters->trialDays) ? (int)$parameters->trialDays : 0);

        $this->amount = $planAmount;
        
        $trialConfirmedLength = 0;
        
         if($trialDays > 0) {
           // Safety check to make sure we can't add more days to the trial than allowed.     
           if($trialDays >= $trialLength) {
              $trialConfirmedLength = $trialLength;
           } else {
              $trialConfirmedLength = $trialDays;
           }
           
           // Now we'll calculate the total number of days needed for the trial.
           $trialDays = $trialConfirmedLength * $planLength;
           $now = new \DateTime();
           $trialEnd = new \DateTime();
           
           if($planUnit == "months") {
              $trialEnd = $this->sameDateNextMonth($now, $trialDays);
           } else {
              $trialEnd->modify("+" . $trialDays . " " . $planUnit);
           }
           
           $totalTrialDays = $this->dateDifference($now, $trialEnd, $differenceFormat = '%a');
           $totalTrialDays = $totalTrialDays + $startDateDays;
           
           // Set total number of trial days.
           $craftSubscription->setTrialDays($totalTrialDays);
         } else {
           $craftSubscription->setTrialDays(0);
         }

        if($trialAmount == "" || $trialAmount == 0) {

            $nextBillingDate = $nextBillingDate->modify("+" . $startDateDays . " days");
            
            if($planUnit == "months") {
               $nextBillingDate = $this->sameDateNextMonth($nextBillingDate, $trialDays);
            } else {
               $nextBillingDate = $nextBillingDate->modify("+" . $trialDays . " " . $planUnit);
            }
           
        } else {

            // Regular billing or when trial is greater than $0. 
            // We'll show a next payment date of when the subscription starts or on the next renewal.
            
            $nextBillingDate = $nextBillingDate->modify("+" . $startDateDays . " days");
            
            // Only increase the plan if there isn't a first-time charge.
            
            if(empty($planData[8])) {
               if($planUnit == "months") {
                   $nextBillingDate = $this->sameDateNextMonth($nextBillingDate, $planLength);
               } else {
                  $nextBillingDate = $nextBillingDate->modify("+" . $planLength . " " . $planUnit);
               }
            }
        }
        
        $merchantAuthentication = $this->gateway;
        
        // Set the transaction's refId
        $refId = 'ref' . time();
    
        // Subscription Type Info
        $subscription = new AnetAPI\ARBSubscriptionType();
        $subscription->setName($planName);
    
        $interval = new AnetAPI\PaymentScheduleType\IntervalAType();
        $interval->setLength($planLength);
        $interval->setUnit($planUnit);

        $paymentSchedule = new AnetAPI\PaymentScheduleType();
        $paymentSchedule->setInterval($interval);
        $paymentSchedule->setStartDate($startDate);
        $paymentSchedule->setTotalOccurrences($planTotal);
        $paymentSchedule->setTrialOccurrences($trialConfirmedLength);
    
        $subscription->setPaymentSchedule($paymentSchedule);
        $subscription->setAmount($planAmount);
        $subscription->setTrialAmount($trialAmount);
        
        $paymentToken = json_decode($this->paymentSource);
        
        $customerProfileId = (isset($paymentToken->customerProfileId) ? $paymentToken->customerProfileId : null);
        $customerPaymentProfileId = (isset($paymentToken->customerPaymentProfileId) ? $paymentToken->customerPaymentProfileId : null); 
        
        $profile = new AnetAPI\CustomerProfileIdType();
        $profile->setCustomerProfileId($customerProfileId);
        $profile->setCustomerPaymentProfileId($customerPaymentProfileId);
        
        $subscription->setProfile($profile);
    
        $order = new AnetAPI\OrderType();
        $order->setInvoiceNumber("CS-" . $plan->id . "-" . time());
        $subscription->setOrder($order);
    
        $request = new AnetAPI\ARBCreateSubscriptionRequest();
        $request->setmerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setSubscription($subscription);
        $controller = new AnetController\ARBCreateSubscriptionController($request);
    
        $response = $controller->executeWithApiResponse($this->environment);
        
        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") ) {
            $craftSubscription->setReference($response->getSubscriptionId());
            $craftSubscription->setNextPaymentDate($nextBillingDate);
            $planDetails = array(
               "planData" => $planData,
               "message" => $response->getMessages()->getMessage(),
               "startBilling" => $startDate,
               "customerProfileID" => (isset($paymentToken->customerProfileId) ? $paymentToken->customerProfileId : null),
               "transactions" => array(),
               "billingIssues" => '',
            );
            $craftSubscription->setData($planDetails);
        } else {
            $errorMessages = $response->getMessages()->getMessage();
            throw new Exception(Craft::t('commerce', 'Something went wrong while scheduling the recurring payment in Authorize.net (' . $errorMessages[0]->getText() . ') Please try again.'));
        }
        
        if(!empty($planData[8])) {
            $this->chargeProfile($this->paymentSource, $planData[8], $response->getSubscriptionId());
        }

        return $craftSubscription;
    }
    
    /**
     * Charges Authorize.net account immediately using profile and payment credentials.
     */
    public function chargeProfile($paymentSourceToken, $amount, $subscriptionID = null) {

        $merchantAuthentication = $this->gateway;
        
        // Set the transaction's refId
        $refId = 'CS-' . $subscriptionID;
        
        $paymentToken = json_decode($paymentSourceToken);
        
        $customerProfileId = (isset($paymentToken->customerProfileId) ? $paymentToken->customerProfileId : null);
        $customerPaymentProfileId = (isset($paymentToken->customerPaymentProfileId) ? $paymentToken->customerPaymentProfileId : null);  
    
        $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
        $profileToCharge->setCustomerProfileId($customerProfileId);
        
        $paymentProfile = new AnetAPI\PaymentProfileType();
        $paymentProfile->setPaymentProfileId($customerPaymentProfileId);
        
        $profileToCharge->setPaymentProfile($paymentProfile);
    
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction"); 
        $transactionRequestType->setAmount($amount);
        $transactionRequestType->setProfile($profileToCharge);
    
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId($refId);
        $request->setTransactionRequest( $transactionRequestType);
        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->environment);
    
        if ($response != null) {
          if($response->getMessages()->getResultCode() == "Ok") {
            $tresponse = $response->getTransactionResponse();
            if ($tresponse != null && $tresponse->getMessages() != null) {
              return true;
              // echo " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
              // echo "Charge Customer Profile APPROVED  :" . "\n";
              // echo " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
              // echo " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
              // echo " Code : " . $tresponse->getMessages()[0]->getCode() . "\n"; 
              // echo " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
            } else {
              if($tresponse->getErrors() != null) {
                throw new Exception(Craft::t('commerce', 'Payment details are invalid. (' . $tresponse->getErrors()[0]->getErrorCode() . ') Pleae try again.'));     
              }
            }
          } else {
            $tresponse = $response->getTransactionResponse();
            if($tresponse != null && $tresponse->getErrors() != null) {
                throw new Exception(Craft::t('commerce', 'Payment details are invalid. (' . $tresponse->getErrors()[0]->getErrorText() . ') Pleae try again.'));                   
            } else {
                throw new Exception(Craft::t('commerce', 'Payment details are invalid. (' . $response->getMessages()->getMessage()[0]->getCode() . ') Pleae try again.'));
            }
          }
        } else {
          throw new Exception(Craft::t('commerce', 'No Response Returned from the Gateway. Pleae try again.')); 
        }
    
        return $response;
    }
    
    public function suspendSubscription($subscription) {
       
       $subscription->isCanceled = true;
       $subscription->dateCanceled = Db::prepareDateForDb(new \DateTime());
       
       $subscriptionData = $subscription->getSubscriptionData();
       $subscriptionData['billingIssues'] = "Subscription Suspended";
       $subscription->setSubscriptionData($subscriptionData);

       try {
           Craft::$app->getElements()->saveElement($subscription, false);

           // fire an 'afterCancelSubscription' event
           if ($this->hasEventHandlers(self::EVENT_AFTER_SUSPEND_SUBSCRIPTION)) {
               $this->trigger(self::EVENT_AFTER_SUSPEND_SUBSCRIPTION, new SuspendSubscriptionEvent([
                   'subscription' => $subscription,
                   'suspensionDate' => Db::prepareDateForDb(new \DateTime()),
                   'paidUntil' =>  $subscription->dateCanceled
               ]));
           }
       } catch (Throwable $exception) {
           Craft::warning('Failed to suspend subscription ' . $subscription->reference . ': ' . $exception->getMessage());
           throw new SubscriptionException(Plugin::t( 'Unable to suspend subscription at this time.'));
       }
    }
    
    public function getPaymentHistory($customerProfileId) {
       
      $merchantAuthentication = $this->gateway;
    
      $request = new AnetAPI\GetTransactionListForCustomerRequest();
      $request->setMerchantAuthentication($merchantAuthentication);
      $request->setCustomerProfileId($customerProfileId);
    
      $controller = new AnetController\GetTransactionListController($request);
    
      $response = $controller->executeWithApiResponse($this->environment);
      
      // We have to assume that the payment is USD here because the GetTransactionListController return
      // doesn't allow us to determine the currency of the transaction.
      
      $currency = Commerce::getInstance()->getCurrencies()->getCurrencyByIso("USD");
        
      $transactions = [];
      if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
      {
         if(null != $response->getTransactions()) {
               foreach($response->getTransactions() as $transaction) {
                  $transactions[] = new SubscriptionPayment([
                       'paymentAmount' => $transaction->getSettleAmount(),
                       'paymentDate' => $transaction->getSubmitTimeUTC(),
                       'paymentCurrency' => $currency,
                       'paymentReference' => $transaction->getTransId(),
                       'paid' => $this->paidStatus($transaction->getTransactionStatus()),
                       'response' => Json::encode($transaction),
                  ]);
              }
         }
      }
      return $transactions;
    }
    
    public function dateDifference($date1, $date2, $differenceFormat = '%a')
    {

        $interval = date_diff($date1, $date2);       
        return $interval->format($differenceFormat);
       
    }
    
    public function paidStatus($status) {
       switch($status) {
          case "capturedPendingSettlement": 
          case "capturedPendingSettlement": 
          case "approvedReview": 
          case "settledSuccessfully": 
          case "underReview": 
          case "FDSPendingReview": 
          case "FDSAuthorizedPendingReview": 
          return true; 
          break;
          default: return false;
       }
    }

    /**
     * @inheritdoc
     */
    public function switchSubscriptionPlan(Subscription $subscription, Plan $plan, SwitchPlansForm $parameters): SubscriptionResponseInterface
    {
        return new AuthorizeSubscriptionResponse();
    }

    /**
     * @inheritdoc
     */
    public function supportsReactivation(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function supportsPlanSwitch(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getBillingIssueDescription(Subscription $subscription): string
    {
        return $subscription->getSubscriptionData()['billingIssues'];
    }

    /**
     * @inheritdoc
     */
    public function getBillingIssueResolveFormHtml(Subscription $subscription): string
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function getHasBillingIssues(Subscription $subscription): bool
    {
       if($subscription->getSubscriptionData()['billingIssues'] == "") { return false; } else { return true; }
    }
    
    /**
    * Get all payment sources from Craft 
    **/
    private function getAllPaymentSources($customerId, $authorizeToken = null) 
    {
        $paymentSources = Commerce::getInstance()->getPaymentSources()->getAllGatewayPaymentSourcesByCustomerId($this->id, $customerId);
        
        if(empty($paymentSources)) {
            return null;
        }
        
        if($authorizeToken) {
            foreach($paymentSources as $source) {
                if($source->token == $authorizeToken) {
                    return $source;
                }
            }
        }
        
        return $paymentSources[0];
    }
    
    /**
      * Whether developer mode is enabled
      */
     public function isDeveloperMode(): bool
     {
         return filter_var(\craft\helpers\App::parseEnv($this->developerMode), FILTER_VALIDATE_BOOLEAN);
     }
   
}
