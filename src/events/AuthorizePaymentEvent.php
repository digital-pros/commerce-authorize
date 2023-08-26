<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Digital Pros, LLC
 * @license https://craftcms.github.io/license/
 */
 
namespace digitalpros\commerce\authorize\events;
use yii\base\Event;

/**
 * Class SubscriptionPaymentEvent
 *
 * @author Digital Pros <hello@digitalpros.co>
 * @since 2.0
 */
class AuthorizePaymentEvent extends Event
{
    // Properties
    // ==========================================================================

    /**
     * @var Transaction Transaction
     */
    public $transaction;

    /**
     * @var Description string - Authorize.net Description (255 Characters Max)
     */
    public $description;

    /**
     * @var InvoiceNumber string - Authorize.net Invoice Number (20 Characters Max)
     */
    public $invoiceNumber;
}
