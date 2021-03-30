<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Digital Pros, LLC
 * @license https://craftcms.github.io/license/
 */

use digitalpros\commerce\authorize\events;

use craft\commerce\elements\Subscription;
use craft\commerce\models\subscriptions\SubscriptionPayment;
use DateTime;
use yii\base\Event;

/**
 * Class SubscriptionPaymentEvent
 *
 * @author Digital Pros <hello@digitalpros.co>
 * @since 2.0
 */
class SubscriptionSuspendEvent extends Event
{
    // Properties
    // ==========================================================================

    /**
     * @var Subscription Subscription
     */
    public $subscription;

    /**
     * @var SubscriptionPayment Subscription date
     */
    public $suspensionDate;

    /**
     * @var DateTime Date subscription paid until
     */
    public $paidUntil;
}
