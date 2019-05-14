<?php
namespace selvinortiz\importer\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class ShowOnWayfindingSidebar extends ElementAction
{
    public $replace = false;

    public function getTriggerLabel(): string
    {
        return 'Show on wayfinding sidebar';
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $elements = $query->all();

        foreach ($elements as $element)
        {
            if (!$element->placeVisibleOnSidebar)
            {
                $element->placeVisibleOnSidebar = true;

                Craft::$app->getElements()->saveElement($element);
            }
        }

        $this->setMessage('Showing on wayfinding sidebar is done 👍');

        return true;
    }
}
