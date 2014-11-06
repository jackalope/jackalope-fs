<?php

namespace Jackalope\Transport\Fs\Search;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Jackalope\Transport\Fs\Events;
use Jackalope\Transport\Fs\Event\NodeWriteEvent;

class IndexSubscriber implements EventSubscriberInterface
{
    private $searchAdapter;
    private $pendingOperations;

    public function __construct(SearchAdapterInterface $searchAdapter)
    {
        $this->searchAdapter = $searchAdapter;
        $this->pendingOperations = array(
            'index' => array()
        );
    }

    public static function getSubscribedEvents()
    {
        return array(
            Events::POST_WRITE_NODE => 'postWriteIndex',
            Events::COMMIT => 'finishSave',
        );
    }

    public function postWriteIndex(NodeWriteEvent $event)
    {
        $this->pendingOperations['index'][$event->getPath()] = array(
            'workspace' => $event->getWorkspace(),
            'path' => $event->getPath(),
            'node_data' => $event->getNode()
        );
    }

    public function finishSave()
    {
        foreach ($this->pendingOperations['index'] as $operation) {
            $this->searchAdapter->index(
                $operation['workspace'],
                $operation['path'],
                $operation['node_data']
            );
        }

        $this->pendingOperations['index'] = array();
    }
}
