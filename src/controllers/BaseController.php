<?php

namespace nineteenninetyfour\socialimages\controllers;

use Craft;
use craft\web\Controller;
use nineteenninetyfour\socialimages\Plugin;
use yii\web\ForbiddenHttpException;

/**
 * What the control panel's controllers share: permissions, and that
 * templates (kept in Project Config) can only change where admin changes
 * are allowed.
 */
abstract class BaseController extends Controller
{
    protected function requireView(): void
    {
        $user = Craft::$app->getUser();

        if (! $user->checkPermission(Plugin::PERMISSION_VIEW) && ! $user->checkPermission(Plugin::PERMISSION_MANAGE)) {
            throw new ForbiddenHttpException('User is not authorized to perform this action.');
        }
    }

    protected function requireManage(): void
    {
        $this->requirePermission(Plugin::PERMISSION_MANAGE);
    }

    /**
     * Saving a template writes Project Config.
     */
    protected function requireAdminChanges(): void
    {
        $this->requireManage();

        if (! Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException(Craft::t('social-images', 'Templates can only be changed where admin changes are allowed.'));
        }
    }

    protected function canManage(): bool
    {
        return Craft::$app->getUser()->checkPermission(Plugin::PERMISSION_MANAGE);
    }

    protected function canChangeTemplates(): bool
    {
        return $this->canManage() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
    }

    /**
     * A control panel page that mounts one of the shared Vue pages.
     */
    protected function page(string $page, string $title, string $subnav, array $props): \yii\web\Response
    {
        return $this->renderTemplate('social-images/_page', [
            'page' => $page,
            'title' => $title,
            'selectedSubnavItem' => $subnav,
            'props' => $props,
        ]);
    }
}
