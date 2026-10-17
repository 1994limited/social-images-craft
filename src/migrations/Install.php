<?php

namespace nineteenninetyfour\socialimages\migrations;

use Craft;
use craft\db\Migration;

/**
 * Nothing to make on install: templates live in Project Config and images
 * in a filesystem. On uninstall the templates go from Project Config too
 * (Craft removes the plugin's settings itself). Generated images and the
 * fonts and pictures in the library folder are left where they are.
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        return true;
    }

    public function safeDown(): bool
    {
        Craft::$app->getProjectConfig()->remove('socialImages', 'Uninstall Social Images');

        return true;
    }
}
