<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace digitalpros\commerce\authorize\models;

use craft\commerce\base\Plan;
use craft\commerce\base\PlanInterface;

/**
 * Class AuthorizePlan
 *
 * @author Digital Pros - Special thanks to Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class AuthorizePlan extends Plan
{
    /**
     * @inheritdoc
     */
    public function canSwitchFrom(PlanInterface $currentPlant): bool
    {
        return true;
    }
}
