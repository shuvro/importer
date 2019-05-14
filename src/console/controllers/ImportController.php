<?php
namespace selvinortiz\importer\console\controllers;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;

use yii\console\ExitCode;
use yii\console\Controller;

use Craft;
use craft\elements\Entry;
use craft\elements\Category;
use craft\records\CategoryGroup;

use function selvinortiz\importer\importer;
use craft\helpers\ElementHelper;
use yii\helpers\Console;

class ImportController extends Controller
{
    protected $allowAnonymous = true;

    public function actionRun()
    {
        if (!$this->addDepartments())
        {
            $this->stdout('Departments could not be imported😱', Console::FG_RED);
            $this->stdout(PHP_EOL);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$this->addFloors())
        {
            $this->stdout('Floors could not be imported😱', Console::FG_RED);
            $this->stdout(PHP_EOL);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$this->addDestinations())
        {
            $this->stdout('Destinations could not be imported😱', Console::FG_RED);
            $this->stdout(PHP_EOL);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!$this->addPeople())
        {
            $this->stdout('People could not be imported😱', Console::FG_RED);
            $this->stdout(PHP_EOL);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Everything was imported successfully👍', Console::FG_GREEN);
        $this->stdout(PHP_EOL);

        return ExitCode::OK;
    }

    private function addFloors()
    {
        $config = importer()->settings->floors;

        $floors = $this->read($config);
        $floors = $this->uniqueValuesByKey($floors, 'floor');

        $sectionId = $this->sectionId($config['section']);
        $typeId = $this->typeId($config['type'], $sectionId);
        $parentId = importer()->settings->buildings['id'];

        foreach ($floors as $floor)
        {
            $this->addFloor($floor, $sectionId, $typeId, $parentId);
        }

        return true;
    }

    private function addFloor($floor, int $sectionId, int $typeId, int $parentId = null)
    {
        $entry = new Entry();

        $entry->siteId = importer()->settings->siteId;
        $entry->authorId = importer()->settings->authorId;
        $entry->enabled = true;
        $entry->typeId = $typeId;
        $entry->sectionId = $sectionId;

        if ($parentId)
        {
            $entry->newParentId = $parentId;
        }

        $fieldValues = [
            'floorNumber' => $floor,
        ];

        $entry->setFieldValues($fieldValues);

        return Craft::$app->getElements()->saveElement($entry);
    }

    private function addDestinations()
    {
        $config = importer()->settings->destinations;

        $destinations = $this->read($config);

        $sectionId = $this->sectionId($config['section']);
        $typeId = $this->typeId($config['type'], $sectionId);

        foreach ($destinations as $destination)
        {
            $this->addDestination($destination, $sectionId, $typeId);
        }

        return true;
    }

    private function addDestination($destination, int $sectionId, int $typeId)
    {
        $entry = new Entry();

        $entry->siteId = importer()->settings->siteId;
        $entry->authorId = importer()->settings->authorId;
        $entry->enabled = true;
        $entry->typeId = $typeId;
        $entry->sectionId = $sectionId;

        $relatedFloorId = Entry::findOne([
            'slug' => 'floor-'.$destination['relatedFloor'],
            'section' => 'places',
        ])->id ?? null;

        if ($relatedFloorId)
        {
            $entry->newParentId = $relatedFloorId;
        }

        $fieldValues = [
            'placeName' => $destination['placeName'],
            'placeRoomId' => $destination['placeRoomId'],
        ];

        $entry->setFieldValues($fieldValues);

        return Craft::$app->getElements()->saveElement($entry);
    }

    private function addDepartments()
    {
        $config = importer()->settings->departments;

        $rows = $this->read($config);
        $departments = $this->uniqueValuesByKey($rows, 'department');

        $groupId = CategoryGroup::find([
            'slug' => $config['slug']
        ])->one()->id ?? null;

        foreach ($departments as $department)
        {
            $this->addDepartment($groupId, $department);
        }

        return true;
    }

    private function addDepartment(int $groupId, string $departmentName)
    {
        $category = new Category();

        $category->siteId = importer()->settings->siteId;
        $category->groupId = $groupId;
        $category->enabled = true;

        $category->title = $departmentName;

        return Craft::$app->getElements()->saveElement($category);
    }

    private function addPeople()
    {
        $config = importer()->settings->people;

        $people = $this->read($config);

        $sectionId = $this->sectionId($config['section']);
        $typeId = $this->typeId($config['type'], $sectionId);

        foreach ($people as $person)
        {
            $this->addPerson($person, $sectionId, $typeId);
        }

        return true;
    }

    private function addPerson($person, int $sectionId, int $typeId)
    {
        $entry = new Entry();

        $entry->siteId = importer()->settings->siteId;
        $entry->authorId = importer()->settings->authorId;
        $entry->enabled = true;
        $entry->sectionId = $sectionId;
        $entry->typeId = $typeId;

        $fieldValues = [
            'personFirstName' => ucfirst(mb_strtolower($person['personFirstName'])) ?? null,
            'personLastName' => ucfirst(mb_strtolower($person['personLastName'])) ?? null,
            'personEmail' => mb_strtolower($person['personEmail']) ?? null,
            'personPhone' => $person['personPhone'] ?? null,
            'personTitle' => $person['personTitle'] ?? null,
        ];

        $entry->setFieldValues($fieldValues);

        $relatedDestinationId = Entry::find()->section('places')->where([
            'content.field_placeRoomId' => $person['relatedDestination']
        ])->one()->id ?? null;

        $slug = ElementHelper::createSlug($person['relatedDepartments']);
        $relatedDepartmentId = Category::findOne(compact('slug'))->id ?? null;

        if ($relatedDestinationId)
        {
            $entry->relatedDestination = [(int) $relatedDestinationId];
        }

        if ($relatedDepartmentId)
        {
            $entry->relatedDepartments = [(int) $relatedDepartmentId];
        }

        return Craft::$app->getElements()->saveElement($entry);
    }

    private function typeId(string $typeHandle, int $sectionId)
    {
        $types = Craft::$app->sections->getEntryTypesBySectionId($sectionId);

        if ($types)
        {
            foreach ($types as $type)
            {
                if ($type->handle == $typeHandle)
                {
                    return $type->id;
                }
            }
        }

        return null;
    }

    private function sectionId(string $handle)
    {
        return Craft::$app->sections->getSectionByHandle($handle)->id ?? null;
    }

    private function read(array $config)
    {
        $reader = ReaderFactory::create(Type::XLSX); // for XLSX files

        $reader->open($config['filePath']);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet)
        {
            foreach ($sheet->getRowIterator() as $index => $row)
            {
                // Skip unwanted rows
                if ($index >= $config['startingRow'] && $index <= $config['endingRow']) {
                    $rows[$index] = $this->addRowKeys($row, $config['headers']);
                }
            }
        }

        $reader->close();

        return $rows;
    }

    private function uniqueValuesByKey(array $rows, string $key)
    {
        $unique = [];

        foreach ($rows as $row)
        {
            $value = $row[$key];

            $unique[$value] = $value;
        }

        $unique = array_values($unique);

        sort($unique);

        return $unique;
    }

    private function addRowKeys(array $row, array $headers)
    {
        $rowWithKeys = [];

        foreach ($headers as $name => $key)
        {
            $rowWithKeys[$name] = $row[$key] ?? null;
        }

        return $rowWithKeys;
    }
}
