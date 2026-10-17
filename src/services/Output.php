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

    /**
     * Whether a file is there. Each file's name carries its fingerprint, so
     * one that's there stays right: that's remembered, to spare a remote
     * filesystem a request on every page view.
     */
    public function exists(string $path): bool
    {
        if (Craft::$app->getCache()->get($this->cacheKey($path)) !== false) {
            return true;
        }

        try {
            $exists = $this->fs()->fileExists($path);
        } catch (Throwable) {
            return false;
        }

        if ($exists) {
            Craft::$app->getCache()->set($this->cacheKey($path), $this->lastModified($path, cached: false), 86400);
        }

        return $exists;
    }

    /**
     * Write a file whole: to a temporary name, then moved into place, so a
     * page never finds half an image under the real name.
     */
    public function put(string $path, string $contents): void
    {
        $fs = $this->fs();
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        $fs->write($temporary, $contents, ['visibility' => 'public', 'mimetype' => 'image/png']);

        if ($fs->fileExists($path)) {
            $fs->deleteFile($path);
        }

        $fs->renameFile($temporary, $path, ['visibility' => 'public', 'mimetype' => 'image/png']);
        Craft::$app->getCache()->set($this->cacheKey($path), time(), 86400);
    }

    public function delete(string $path): void
    {
        Craft::$app->getCache()->delete($this->cacheKey($path));

        try {
            $this->fs()->deleteFile($path);
        } catch (Throwable) {
        }
    }

    protected function cacheKey(string $path): string
    {
        return 'social-images:file:'.md5($this->fs()::class.($this->fs()->handle ?? '').$path);
    }

    public function url(string $path): string
    {
        $root = rtrim((string) $this->fs()->getRootUrl(), '/');
        $url = $root.'/'.ltrim($path, '/');

        return UrlHelper::isAbsoluteUrl($url) ? $url : UrlHelper::siteUrl(ltrim($url, '/'));
    }

    public function lastModified(string $path, bool $cached = true): int
    {
        $known = $cached ? Craft::$app->getCache()->get($this->cacheKey($path)) : false;

        if (is_int($known) && $known > 0) {
            return $known;
        }

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
            // Listing a folder that isn't there yet is logged as an error.
            if (! $this->fs()->directoryExists($directory)) {
                return [];
            }

            /** @var FsListing $listing */
            foreach ($this->fs()->getFileList($directory, false) as $listing) {
                if (! $listing->getIsDir() && ! str_ends_with($listing->getUri(), '.tmp')) {
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
            if ($this->fs()->directoryExists($directory)) {
                foreach ($this->fs()->getFileList($directory, true) as $listing) {
                    Craft::$app->getCache()->delete($this->cacheKey(trim($listing->getUri(), '/')));
                }
            }
        } catch (Throwable) {
        }

        try {
            $this->fs()->deleteDirectory($directory);
        } catch (Throwable) {
        }
    }
}
