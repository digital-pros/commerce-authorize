<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace digitalpros\commerce\authorize\responses;

use craft\commerce\base\SubscriptionResponseInterface;
use craft\helpers\StringHelper;
use DateInterval;
use DateTime;

/**
 * This is an Authorize gateway request response.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class AuthorizeSubscriptionResponse implements SubscriptionResponseInterface
{
    /**
     * @var bool Whether this subscription is canceled
     */
    private $_isCanceled = false;
    
    /**
     * @var bool Whether this subscription is canceled
     */
    private $_isScheduledCanceled = false;

    /**
     * @var int Amount of trial days
     */
    private $_trialDays = 0;
    
    /**
     * @var mixed Data about the transaction
     */
    private $_data;
    
    /**
     * @var string Reference Number
     */
    private $_reference;
    
    /**
     * @var datetime Next payment date
     */
    private $_nextPaymentDate;

    /**
     * @inheritdoc
     */
    public function setIsCanceled(bool $isCanceled)
    {
        $this->_isCanceled = $isCanceled;
    }

    /**
     * @inheritdoc
     */
    public function setTrialDays(int $trialDays)
    {
        $this->_trialDays = $trialDays;
    }
    
    /**
     * @inheritdoc
     */
    public function setReference(string $reference)
    {
        $this->_reference = $reference;
    }
    
    /**
     * @inheritdoc
     */
    public function setNextPaymentDate($nextDate)
    {
        $this->_nextPaymentDate = $nextDate;
    }
    
    /**
     * @inheritdoc
     */
    public function setData(array $data)
    {
        $this->_data = $data;
    }

    /**
     * @inheritdoc
     */
    public function getData(): array
    {
        return $this->_data;
    }

    /**
     * @inheritdoc
     */
    public function getReference(): string
    {
        return $this->_reference;
    }

    /**
     * @inheritdoc
     */
    public function getTrialDays(): int
    {
        return $this->_trialDays;
    }

    /**
     * @inheritdoc
     */
    public function getNextPaymentDate(): DateTime
    {
        return $this->_nextPaymentDate;
    }

    /**
     * @inheritdoc
     */
    public function isCanceled(): bool
    {
        return $this->_isCanceled;
    }
    
    /**
     * @inheritdoc
     */
    public function setScheduledForCancellation($scheduled): bool
    {
        return $this->_isScheduledCanceled = $scheduled;
    }

    /**
     * @inheritdoc
     */
    public function isScheduledForCancellation(): bool
    {
        return $this->_isScheduledCanceled;
    }

    /**
     * @inheritdoc
     */
    public function isInactive(): bool
    {
        return false;
    }
}
