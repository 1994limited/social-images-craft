<?php

namespace nineteenninetyfour\socialimages\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use nineteenninetyfour\socialimages\Plugin;
use nineteenninetyfour\socialimages\support\Diagnose;
use yii\console\ExitCode;

/**
 * Renders a test image with the configured renderer (or another, with
 * --driver) and says whether it worked, and if not, what to change.
 */
class DoctorController extends Controller
{
    public $defaultAction = 'index';

    /** @var string|null Check this renderer instead of the one in use */
    public ?string $driver = null;

    public function options($actionID): array
    {
        return [...parent::options($actionID), 'driver'];
    }

    /**
     * Checks social images can be rendered.
     */
    public function actionIndex(): int
    {
        $renderers = Plugin::getInstance()->renderers();
        $driver = $this->driver ?: $renderers->defaultDriver();
        $result = Diagnose::driver($driver);

        $this->stdout(str_pad('Renderer', 12).$driver."\n");

        if ($result['capabilities']) {
            $this->stdout(str_pad('Runs', 12).($result['capabilities']['remote'] ? 'on another service (page HTML is sent to it)' : 'on this server')."\n");
        }

        $this->stdout(str_pad('Available', 12).implode(', ', $renderers->names())."\n\n");

        if ($result['ok']) {
            $this->stdout($result['message']."\n", Console::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stderr($result['message']."\n", Console::FG_RED);

        if ($result['fix']) {
            $this->stderr('⇂ '.$result['fix']."\n");
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
