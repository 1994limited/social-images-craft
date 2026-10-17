<?php

namespace nineteenninetyfour\socialimages\tests\unit;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;

class ImagesTest extends TestCase
{
    public function test_an_image_field_is_cropped_to_the_box_its_drawn_in(): void
    {
        $asset = $this->asset('cover.png', 400, 300);
        $entry = $this->entry('books', 'Coral', ['coverImage' => [$asset->id]]);
        $images = $this->plugin()->images();

        // Statamic's handle finds Craft's field.
        $found = $images->find(['field:hero_image', 'field:cover_image'], $entry);
        $this->assertSame($asset->id, $found->id);

        $uri = $images->dataUri($found, 120, 120);
        $this->assertMatchesRegularExpression('#^data:image/(webp|png);base64,#', $uri);

        $size = getimagesizefromstring(base64_decode(substr($uri, strpos($uri, ',') + 1)));
        $this->assertSame([120, 120], [$size[0], $size[1]]);
    }

    public function test_moving_the_focal_point_changes_the_fingerprint(): void
    {
        $asset = $this->asset('cover.png', 400, 300);
        $this->template(layers: [['id' => 'pic', 'type' => 'image', 'source' => ['field:coverImage']]]);
        $this->useTemplate('basic', ['books']);
        $this->settings(['generateOnSave' => false]);

        $entry = $this->entry('books', 'Coral', ['coverImage' => [$asset->id]]);
        $hash = $this->plugin()->cards->for($entry)->hash();

        $asset = Asset::find()->id($asset->id)->one();
        $asset->setFocalPoint(['x' => 0.2, 'y' => 0.3]);
        $this->assertTrue(Craft::$app->getElements()->saveElement($asset));

        $fresh = Entry::find()->id($entry->id)->one();
        $this->assertNotSame($hash, $this->plugin()->cards->for($fresh)->hash());
    }

    public function test_an_asset_source_and_related_images(): void
    {
        $photo = $this->asset('photo.png');
        $images = $this->plugin()->images();

        $this->assertSame($photo->id, $images->find(['asset:'.$photo->id], null)->id);
        $this->assertSame($photo->id, $images->find(['asset:'.$photo->uid], null)->id);
        $this->assertNull($images->find(['asset:999999'], null));
    }
}
