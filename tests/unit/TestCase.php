<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\models\DesignerTemplate;
use nineteenninetyfour\socialimages\Plugin;
use UnitTester;

abstract class TestCase extends Unit
{
    protected UnitTester $tester;

    protected FakeCamera $camera;

    protected function _before(): void
    {
        parent::_before();

        $this->camera = new FakeCamera;
        $this->plugin()->set('camera', $this->camera);

        foreach (['web/social-images', 'storage/social-images-library'] as $folder) {
            FileHelper::removeDirectory(CRAFT_ROOT_PATH.'/'.$folder);
        }

        Craft::$app->getCache()->flush();
        $this->settings(['rules' => [], 'generateOnSave' => true, 'fallbackImageId' => null, 'seomatic' => 'replace']);
    }

    protected function plugin(): Plugin
    {
        return Plugin::getInstance();
    }

    /**
     * Set settings for this test, without saving them.
     */
    protected function settings(array $values): void
    {
        $settings = $this->plugin()->getSettings();

        foreach ($values as $key => $value) {
            $settings->$key = $value;
        }
    }

    /**
     * A designer template with a title text layer, saved.
     */
    protected function template(string $handle = 'basic', array $layers = [], array $extra = []): DesignerTemplate
    {
        return $this->plugin()->templates->save($handle, [
            'schema' => Schema::VERSION,
            'title' => ucfirst($handle),
            'layouts' => [
                'og' => ['width' => 1200, 'height' => 630, 'layers' => $layers ?: [['id' => 'title', 'type' => 'text', 'text' => '{title}']]],
                ...$extra,
            ],
        ]);
    }

    /**
     * A rule: entries in these sections (handles) use this template.
     */
    protected function useTemplate(string $template, array $sections = [], array $layouts = [], array $sites = []): void
    {
        $entries = Craft::$app->getEntries();

        $this->settings(['rules' => [[
            'sections' => array_map(fn ($handle) => $entries->getSectionByHandle($handle)->uid, $sections),
            'sites' => $sites,
            'template' => $template,
            'layouts' => $layouts,
        ]]]);
    }

    protected function entry(string $section = 'journal', string $title = 'Hello world', array $fields = [], array $attributes = []): Entry
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($section);
        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $section->getEntryTypes()[0]->id,
            'title' => $title,
            'slug' => StringHelper::slugify($title).'-'.bin2hex(random_bytes(2)),
            'postDate' => new \DateTime('2026-10-02 12:00:00'),
            ...$attributes,
        ]);
        $entry->setFieldValues($fields);

        $this->assertTrue(Craft::$app->getElements()->saveElement($entry), json_encode($entry->getErrors()));

        return $entry;
    }

    /**
     * An image asset in the Images volume, made from a small PNG.
     */
    protected function asset(string $filename = 'photo.png', int $width = 80, int $height = 60): Asset
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 40));
        $temp = Craft::$app->getPath()->getTempPath().'/'.$filename;
        imagepng($image, $temp);

        $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
        $asset = new Asset([
            'tempFilePath' => $temp,
            'filename' => $filename,
            'newFolderId' => Craft::$app->getAssets()->getRootFolderByVolumeId($volume->id)->id,
            'volumeId' => $volume->id,
            'avoidFilenameConflicts' => true,
        ]);
        $asset->setScenario(Asset::SCENARIO_CREATE);

        $this->assertTrue(Craft::$app->getElements()->saveElement($asset), json_encode($asset->getErrors()));

        return $asset;
    }

    protected function user(string $name = 'Mira Castellane', array $attributes = []): User
    {
        $user = new User(['username' => strtolower(explode(' ', $name)[0]).bin2hex(random_bytes(2)), 'email' => bin2hex(random_bytes(3)).'@example.com', 'fullName' => $name, ...$attributes]);
        $this->assertTrue(Craft::$app->getElements()->saveElement($user), json_encode($user->getErrors()));

        return $user;
    }

    /**
     * Run what's queued.
     */
    protected function runQueue(): void
    {
        Craft::$app->getQueue()->run();
    }
}
