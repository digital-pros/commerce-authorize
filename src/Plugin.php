<?php

namespace digitalpros\commerce\authorize;


use digitalpros\commerce\authorize\gateways\Gateway;
use digitalpros\commerce\authorize\gateways\Subscriptions;

use Craft;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\commerce\services\Gateways;
use craft\events\RegisterComponentTypesEvent;
use craft\log\FileTarget;
use yii\log\Logger;
use yii\base\Event;


/**
 * Plugin represents the Authorize.net plugin.
 *
 * @author Digital Pros <hello@digitalpros.co>
 * @since  1.0
 */
class Plugin extends \craft\base\Plugin
{
    // Public Methods
    // =========================================================================
    
    /**
     * @var Authorize
     */
    public static $plugin;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(Gateways::class, Gateways::EVENT_REGISTER_GATEWAY_TYPES,  function(RegisterComponentTypesEvent $event) {
            $event->types[] = Gateway::class;
            $event->types[] = Subscriptions::class;
        });
    }

    
}
