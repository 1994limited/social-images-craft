<?php

namespace nineteenninetyfour\socialimages\events;

use nineteenninetyfour\socialimages\models\DesignerTemplate;
use yii\base\Event;

class TemplateEvent extends Event
{
    public DesignerTemplate $template;
}
