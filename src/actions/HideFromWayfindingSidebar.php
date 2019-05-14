<?php
namespace selvinortiz\importer\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class HideFromWayfindingSidebar extends ElementAction
{
    public $replace = false;

    public function getTriggerLabel(): string
    {
        return 'Hide from wayfinding sidebar';
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $elements = $query->all();

        foreach ($elements as $element)
        {
            if ($element->placeVisibleOnSidebar)
            {
                $element->placeVisibleOnSidebar = false;

                Craft::$app->getElements()->saveElement($element);
            }
        }

        $this->setMessage('Hiding from wayfinding sidebar is done👍');

        return true;
    }
}
