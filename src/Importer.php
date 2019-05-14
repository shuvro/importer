<?php
namespace selvinortiz\importer;

use yii\base\Event;

use craft\base\Plugin;
use craft\base\Element;
use craft\events\RegisterElementActionsEvent;

use selvinortiz\importer\models\Settings;
use selvinortiz\importer\actions\HideFromWayfindingSidebar;
use selvinortiz\importer\actions\ShowOnWayfindingSidebar;

/**
 * @property Settings $settings
 */
class Importer extends Plugin
{
    public function init()
    {
        parent::init();

        Event::on(
            Element::class,
            Element::EVENT_REGISTER_ACTIONS,
            function(RegisterElementActionsEvent $event) {
                $event->actions[] = ShowOnWayfindingSidebar::class;
                $event->actions[] = HideFromWayfindingSidebar::class;
            }
        );
    }

    public function createSettingsModel()
    {
        return new Settings();
    }
}

/**
 * @return Importer
 */
function importer()
{
    return \Craft::$app->getModule('importer');
}
