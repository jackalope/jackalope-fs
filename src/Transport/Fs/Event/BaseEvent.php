<?php

namespace Jackalope\Transport\Fs\Event;

use Symfony\Component\EventDispatcher\Event as OldEvent;
use Symfony\Contracts\EventDispatcher\Event as NewEvent;

if (class_exists(NewEvent::class)) {
    class BaseEvent extends NewEvent
    {
    }
} else {
    class BaseEvent extends OldEvent
    {
    }
}
