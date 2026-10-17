<?php

namespace nineteenninetyfour\socialimages\services;

use Craft;
use craft\base\FsInterface;
use craft\fs\Local;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\models\FsListing;
use nineteenninetyfour\socialimages\Plugin;
use Throwable;
use yii\base\Component;

/**
 * Where generated images are kept: a folder in a filesystem chosen in the
 * settings, or web/social-images when none is.
 */
class Output extends Component
{
    protected ?FsInterface $fs = null;

    public function fs(): FsInterface
    {
        if ($this->fs) {
            return $this->fs;
        }

        $handle = App::parseEnv(Plugin::getInstance()->getSettings()->imagesFs);
        $fs = $handle ? Craft::$app->getFs()->getFilesystemByHandle($handle) : null;

        return $this->fs = $fs ?? new Local(['path' => '@webroot', 'url' => '@web', 'hasUrls' => true]);
    }

    /**
     * The folder images go in, e.g. "social-images".
     */
    public function directory(): string
    {
        return trim((string) App::parseEnv(Plugin::getInstance()->getSettings()->imagesPath), '/') ?: 'social-images';
    }

    public function exists(string $path): bool
    {
        try {
            return $this->fs()->fileExists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public function put(string $path, string $contents): void
    {
        $this->fs()->write($path, $contents, ['visibility' => 'public', 'mimetype' => 'image/png']);
    }

    public function delete(string $path): void
    {
        try {
            $this->fs()->deleteFile($path);
        } catch (Throwable) {
        }
    }

    public function url(string $path): string
    {
        $root = rtrim((string) $this->fs()->getRootUrl(), '/');
        $url = $root.'/'.ltrim($path, '/');

        return UrlHelper::isAbsoluteUrl($url) ? $url : UrlHelper::siteUrl(ltrim($url, '/'));
    }

    public function lastModified(string $path): int
    {
        try {
            return $this->fs()->getDateModified($path);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array<int, string>
     */
    public function files(string $directory): array
    {
        $files = [];

        try {
            /** @var FsListing $listing */
            foreach ($this->fs()->getFileList($directory, false) as $listing) {
                if (! $listing->getIsDir()) {
                    $files[] = trim($listing->getUri(), '/');
                }
            }
        } catch (Throwable) {
        }

        return $files;
    }

    public function deleteDirectory(string $directory): void
    {
        try {
            $this->fs()->deleteDirectory($directory);
        } catch (Throwable) {
        }
    }
}
