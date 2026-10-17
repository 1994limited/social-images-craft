<?php

namespace nineteenninetyfour\socialimages\rendering;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\FileHelper;
use Illuminate\Support\Collection;
use NineteenNinetyFour\SocialImages\Core\Rendering\Images;
use nineteenninetyfour\socialimages\support\Handles;
use Throwable;

/**
 * Pictures from Craft assets. Each is cropped (around its focal point) or
 * fitted to the exact size it's drawn at with Craft's own image library,
 * here on the server, so the page stays small and nothing is fetched.
 */
class CraftImages extends Images
{
    protected function fromField(object $entry, string $handle): ?object
    {
        if (! $entry instanceof ElementInterface) {
            return null;
        }

        [$handle, $rest] = array_pad(explode('.', $handle, 2), 2, null);

        try {
            $field = Handles::field($entry, $handle);
            $value = match (true) {
                $field !== null => $entry->getFieldValue($field),
                $handle === 'author' && method_exists($entry, 'getAuthor') => $entry->getAuthor(),
                $handle === 'photo' && $entry instanceof User => $entry->getPhoto(),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        $first = $this->first($value);

        if ($rest !== null) {
            return $first instanceof ElementInterface ? $this->fromField($first, $rest) : null;
        }

        return $first instanceof Asset && $this->usable($first) ? $first : null;
    }

    protected function asset(string $id): ?object
    {
        $query = Asset::find()->status(null);
        $asset = ctype_digit($id) ? $query->id((int) $id)->one() : $query->uid($id)->one();

        return $asset && $this->usable($asset) ? $asset : null;
    }

    protected function assetIdentity(object $asset): string
    {
        /** @var Asset $asset */
        $focal = $asset->getFocalPoint();

        return implode('|', [
            $asset->id,
            $asset->dateModified?->getTimestamp(),
            $asset->size,
            $focal ? round($focal['x'], 4).','.round($focal['y'], 4) : '',
        ]);
    }

    protected function assetDataUri(object $asset, int $width, int $height, string $fit): ?string
    {
        /** @var Asset $asset */
        $extension = strtolower((string) $asset->getExtension());

        // Craft's image library can't resize these; they're inlined as they are.
        if (in_array($extension, ['svg', 'gif'], true)) {
            return $this->raw($asset);
        }

        $images = Craft::$app->getImages();
        $format = $images->getSupportsWebP() ? 'webp' : 'png';
        $target = Craft::$app->getPath()->getTempPath().'/social-images-'.bin2hex(random_bytes(8)).'.'.$format;
        $source = null;

        try {
            $source = $asset->getCopyOfFile();
            $image = $images->loadImage($source);

            if ($fit === 'contain') {
                $image->scaleToFit($width, $height, true);
            } else {
                $image->scaleAndCrop($width, $height, true, $asset->getFocalPoint() ?? 'center-center');
            }

            $image->setQuality(90);
            $image->saveAs($target);

            return 'data:image/'.$format.';base64,'.base64_encode((string) file_get_contents($target));
        } catch (Throwable $exception) {
            Craft::warning('Social Images couldn’t resize asset '.$asset->id.': '.$exception->getMessage(), 'social-images');

            return $this->raw($asset);
        } finally {
            foreach ([$source, $target] as $file) {
                if ($file && is_file($file)) {
                    FileHelper::unlink($file);
                }
            }
        }
    }

    protected function raw(Asset $asset): ?string
    {
        try {
            return 'data:'.$asset->getMimeType().';base64,'.base64_encode($asset->getContents());
        } catch (Throwable) {
            return null;
        }
    }

    protected function usable(Asset $asset): bool
    {
        return $asset->kind === Asset::KIND_IMAGE;
    }

    protected function first(mixed $value): mixed
    {
        if ($value instanceof ElementQueryInterface) {
            return $value->one();
        }

        if ($value instanceof Collection) {
            return $value->first();
        }

        return is_array($value) ? (reset($value) ?: null) : $value;
    }
}
