<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace digitalpros\commerce\authorize\migrations;

use Craft;
use digitalpros\commerce\authorize\gateways\Gateway;
use craft\db\Migration;
use craft\db\Query;

/**
 * Installation Migration
 *
 * @author Digital Pros - Referenced from Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  1.0
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Convert any built-in Authorize.net AIM gateways to ours
        $this->_convertGateways();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        return true;
    }

    // Private Methods
    // =========================================================================

    /**
     * Convert any built-in Authorize.net AIM gateways to this one
     *
     * @return void
     */
    private function _convertGateways()
    {
        $gateways = (new Query())
            ->select(['id','settings'])
            ->where(['type' => 'craft\\commerce\\gateways\\AuthorizeNet_AIM'])
            ->from(['{{%commerce_gateways}}'])
            ->all();

        $dbConnection = Craft::$app->getDb();

        foreach ($gateways as $gateway) {
	        
	        $settings = json_decode($gateway['settings'], true);
	        
	        unset($settings['testMode']);
	        unset($settings['hashSecret']);
	        unset($settings['liveEndpoint']);
	        unset($settings['developerEndpoint']);
	        	        
            $values = [
                'type' => Gateway::class,
                'settings' => json_encode($settings),
            ];

            $dbConnection->createCommand()
                ->update('{{%commerce_gateways}}', $values, ['id' => $gateway['id']])
                ->execute();
        }

    }
}
