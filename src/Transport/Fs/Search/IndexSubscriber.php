<?php

namespace Jackalope\Transport\Fs\Search;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Jackalope\Transport\Fs\Events;
use Jackalope\Transport\Fs\Event\NodeWriteEvent;

class IndexSubscriber implements EventSubscriberInterface
{
    private $searchAdapter;

    public function __construct(SearchAdapterInterface $searchAdapter)
    {
        $this->searchAdapter = $searchAdapter;
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::POST_WRITE_NODE => 'postWriteIndex',
        );
    }

    public function postWriteIndex(NodeWriteEvent $event)
    {
        $this->searchAdapter->index(
            $event->getWorkspace(),
            $event->getPath(),
            $event->getNodeData()
        );
    }
}
