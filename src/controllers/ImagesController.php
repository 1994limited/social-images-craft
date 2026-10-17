<?php

namespace nineteenninetyfour\socialimages\controllers;

use Craft;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use NineteenNinetyFour\SocialImages\Core\Templates\Schema;
use nineteenninetyfour\socialimages\jobs\GenerateImage;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\RateLimits;
use Throwable;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Social Images → Images: every entry's image, with buttons to make the
 * missing ones or remake them all, and the endpoints behind the field's
 * preview.
 *
 * Bulk runs are driven from the page, one request per image, so each shows
 * as it lands and a long run never meets a request timeout.
 */
class ImagesController extends BaseController
{
    public function actionIndex(): Response
    {
        $this->requireView();

        $cards = Plugin::getInstance()->cards;
        $user = Craft::$app->getUser()->getIdentity();
        $elements = Craft::$app->getElements();
        $rows = [];

        foreach (Entry::find()->sectionId(':notempty:')->site('*')->status(null)->all() as $entry) {
            if ($cards->supports($entry) && $cards->templateFor($entry) && $elements->canView($entry, $user)) {
                $rows[] = $this->row($entry, $elements->canSave($entry, $user) || $this->canManage());
            }
        }

        usort($rows, fn ($a, $b) => strcasecmp($a['collection'].'|'.$a['title'].'|'.$a['site'], $b['collection'].'|'.$b['title'].'|'.$b['site']));

        return $this->page('Images', Craft::t('social-images', 'Images'), 'images', [
            'entries' => $rows,
            'enabled' => Plugin::getInstance()->getSettings()->enabled,
            'urls' => [
                'templates' => UrlHelper::cpUrl('social-images'),
                'settings' => Craft::$app->getUser()->getIsAdmin() ? UrlHelper::cpUrl('social-images/settings') : null,
            ],
        ]);
    }

    /**
     * The entry's current image, or a null URL while it's still being made.
     */
    public function actionStatus(int $entryId, int $siteId): Response
    {
        $this->requireAcceptsJson();

        $entry = $this->entry($entryId, $siteId);
        $layout = $this->layout();
        $plugin = Plugin::getInstance();
        $card = $plugin->cards->for($entry, $layout);
        $override = $plugin->cards->override($entry);
        $current = $card && $card->exists();

        // Missing or out of date: start making it, as a page asking for it
        // would. Repeated asks are one render. Not for an entry with its own
        // image (it's never shown), and only for people who could make it.
        $mayMake = Craft::$app->getElements()->canSave($entry) || $this->canManage();

        if ($card && ! $current && ! $override && $mayMake && $plugin->getSettings()->enabled) {
            GenerateImage::queue($card);
        }

        $failed = $card && ! $current && GenerateImage::recentlyFailed($card);
        $since = $card && ! $current ? $card->renderingSince() : null;

        return $this->asJson([
            'url' => $current ? $card->previewUrl() : null,
            'previous' => $card && ! $current ? $card->previousPreviewUrl() : null,
            'failed' => $failed,
            'state' => match (true) {
                ! $card => null,
                $current => 'current',
                $failed => 'failed',
                $since !== null => 'rendering',
                default => 'queued',
            },
            'seconds' => $since !== null ? max(0, time() - $since) : null,
            'override' => $override?->getUrl(),
            'template' => $card?->template->title(),
            'width' => $card?->width(),
            'height' => $card?->height(),
        ]);
    }

    /**
     * Re-render even when the image looks current (a picture replaced in
     * place can leave the fingerprint as it was). Renders here, so the
     * editor sees the new image, or the error, straight away.
     */
    public function actionRegenerate(): Response
    {
        $this->requirePostRequest();

        // The Images page puts them in the URL, the field in the body.
        $entry = $this->entry((int) $this->request->getParam('entryId'), (int) $this->request->getParam('siteId'), write: true);
        $layout = $this->layout();
        $cards = Plugin::getInstance()->cards;

        if (! $card = $cards->for($entry, $layout)) {
            return $this->asJson(['message' => Craft::t('social-images', 'This entry has no social image template.')])->setStatusCode(422);
        }

        try {
            (new GenerateImage(['entryId' => $entry->id, 'siteId' => $entry->siteId, 'layout' => $layout, 'force' => true]))->render(queued: false);
        } catch (Throwable $exception) {
            Craft::error('Social Images couldn’t make an image: '.$exception->getMessage(), 'social-images');

            $limited = RateLimits::caused($exception);
            $exhausted = RateLimits::exhausted($exception);

            return $this->asJson([
                'message' => match (true) {
                    $exhausted => Craft::t('social-images', 'The screenshot service’s allowance has run out. Try again once it resets.'),
                    $limited => Craft::t('social-images', 'The screenshot service is busy or its limit has been reached. Try again in a minute.'),
                    default => Craft::t('social-images', 'The image couldn’t be generated. See the log for details.'),
                },
                'fix' => RateLimits::fix($exception),
                'rate_limited' => $limited,
                'quota' => $exhausted,
                'retry_after' => RateLimits::retryAfter($exception),
                'resets_at' => RateLimits::resetsAt($exception)?->format(DATE_ATOM),
            ])->setStatusCode($limited ? 429 : 500);
        }

        return $this->asJson([
            'message' => Craft::t('social-images', 'Image generated'),
            'url' => $cards->for($entry, $layout)->previewUrl(),
        ]);
    }

    protected function row(Entry $entry, bool $canEdit): array
    {
        $cards = Plugin::getInstance()->cards;
        $layouts = [];

        foreach ($cards->layoutsFor($entry) as $layout) {
            $card = $cards->for($entry, $layout);
            $current = $card->exists();

            $layouts[] = [
                'layout' => $layout,
                'url' => $current ? $card->previewUrl() : null,
                // Out of date: the image still in use until the new one's made.
                'previous' => $current ? null : $card->previousPreviewUrl(),
                'regenerate_url' => UrlHelper::actionUrl('social-images/images/regenerate', ['entryId' => $entry->id, 'siteId' => $entry->siteId, 'layout' => $layout]),
            ];
        }

        return [
            'id' => $entry->id.':'.$entry->siteId,
            'title' => (string) $entry->title,
            'collection' => (string) $entry->getSection()?->name,
            'site' => $entry->getSite()->getName(),
            'template' => $cards->templateFor($entry)?->title(),
            'override' => $cards->override($entry)?->getUrl(),
            'edit_url' => $entry->getCpEditUrl(),
            'can_edit' => $canEdit,
            'layouts' => $layouts,
        ];
    }

    /**
     * Anyone who can see the entry can see its image; making one takes
     * being able to save the entry, or to manage the templates.
     */
    protected function entry(int $entryId, int $siteId, bool $write = false): Entry
    {
        $entry = Entry::find()->id($entryId)->siteId($siteId)->status(null)->one();
        $user = Craft::$app->getUser()->getIdentity();
        $elements = Craft::$app->getElements();

        if (! $entry || ! Plugin::getInstance()->cards->supports($entry)) {
            throw new NotFoundHttpException;
        }

        if ($write ? ! ($elements->canSave($entry, $user) || $this->canManage()) : ! $elements->canView($entry, $user)) {
            throw new ForbiddenHttpException('User is not authorized to perform this action.');
        }

        return $entry;
    }

    protected function layout(): string
    {
        return Schema::handle($this->request->getParam('layout')) ?? Schema::BASE_LAYOUT;
    }
}
