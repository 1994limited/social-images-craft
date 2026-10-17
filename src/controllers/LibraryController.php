<?php

namespace nineteenninetyfour\socialimages\controllers;

use craft\helpers\UrlHelper;
use craft\web\UploadedFile;
use InvalidArgumentException;
use nineteenninetyfour\socialimages\Plugin;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * The designer's fonts and uploaded images.
 */
class LibraryController extends BaseController
{
    public function actionFonts(): Response
    {
        $this->requireManage();

        return $this->asJson(['fonts' => Plugin::getInstance()->fonts()->families()]);
    }

    public function actionGoogle(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $family = mb_substr(trim((string) $this->request->getBodyParam('family')), 0, 60);
        $weights = array_map('intval', (array) $this->request->getBodyParam('weights', [400, 700]));

        try {
            Plugin::getInstance()->googleFonts()->add($family, $weights, (bool) $this->request->getBodyParam('italic'));
        } catch (InvalidArgumentException $exception) {
            return $this->asJson(['message' => $exception->getMessage()])->setStatusCode(422);
        }

        return $this->asJson(['fonts' => Plugin::getInstance()->fonts()->families()]);
    }

    public function actionUploadFont(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $file = UploadedFile::getInstanceByName('file');

        try {
            if (! $file) {
                throw new InvalidArgumentException('Choose a font file.');
            }

            Plugin::getInstance()->fonts()->add(
                mb_substr(trim((string) $this->request->getBodyParam('family')), 0, 60),
                (string) file_get_contents($file->tempName),
                strtolower($file->getExtension()),
                (int) $this->request->getBodyParam('weight', 400),
                $this->request->getBodyParam('style') === 'italic' ? 'italic' : 'normal',
            );
        } catch (InvalidArgumentException $exception) {
            return $this->asJson(['message' => $exception->getMessage()])->setStatusCode(422);
        }

        return $this->asJson(['fonts' => Plugin::getInstance()->fonts()->families()]);
    }

    public function actionDeleteFont(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $fonts = Plugin::getInstance()->fonts();
        $fonts->delete((string) $this->request->getBodyParam('family'));

        return $this->asJson(['fonts' => $fonts->families()]);
    }

    public function actionUpload(): Response
    {
        $this->requirePostRequest();
        $this->requireAdminChanges();

        $file = UploadedFile::getInstanceByName('file');

        try {
            if (! $file) {
                throw new InvalidArgumentException('Choose an image.');
            }

            $name = Plugin::getInstance()->uploads()->put((string) file_get_contents($file->tempName), strtolower($file->getExtension()));
        } catch (InvalidArgumentException $exception) {
            return $this->asJson(['message' => $exception->getMessage()])->setStatusCode(422);
        }

        return $this->asJson(['source' => 'upload:'.$name, 'url' => UrlHelper::actionUrl('social-images/library/show', ['name' => $name])]);
    }

    /**
     * An uploaded image, for the designer. Sandboxed, so an SVG can't run
     * script if opened directly.
     */
    public function actionShow(string $name): Response
    {
        $this->requireView();

        $uploads = Plugin::getInstance()->uploads();
        $contents = $uploads->exists($name) ? $uploads->get($name) : null;

        if ($contents === null) {
            throw new NotFoundHttpException;
        }

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->content = $contents;
        $response->headers->set('Content-Type', $uploads->mime($name));
        $response->headers->set('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, max-age=31536000, immutable');

        return $response;
    }
}
