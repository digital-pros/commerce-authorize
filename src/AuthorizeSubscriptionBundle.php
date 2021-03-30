<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Digital Pros - Special thanks to Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace digitalpros\commerce\authorize;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle for the Dashboard
 */
class AuthorizeSubscriptionBundle extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = '@digitalpros/commerce/authorize/resources';

        $this->js = [
            'js/subscriptionForm.js',
        ];

        parent::init();
    }
}
