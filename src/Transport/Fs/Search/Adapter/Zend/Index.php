<?php

namespace Jackalope\Transport\Fs\Search\Adapter\Zend;

use ZendSearch\Lucene\Index as BaseIndex;
use ZendSearch\Lucene\Document;

/**
 * This class adds the possibility to hide destructor errors
 * which generally occur when running consecutive tests.
 *
 * @author Daniel Leech <daniel@dantleech.com>
 */
class Index extends BaseIndex
{
    /**
     * @Var boolean
     */
    private $hideDestructException = false;

    /**
     * Workaround for problems encountered when functional testing ZendSearch.
     * The __destruct is called at the end of the test suite, and throws an
     * error causing exit-code to be non-zero  even if there were no failures.
     *
     * Set to true to catch exceptions in the __destruct method
     *
     * @param boolean $hideDestructException
     */
    public function setHideException($hideDestructException)
    {
        $this->hideDestructException = $hideDestructException;
    }

    public function __destruct()
    {
        if (false === $this->hideDestructException) {
            return parent::__destruct();
        }

        try {
            $level = error_reporting(0);
            error_reporting($level);
            parent::__destruct();
        } catch (\Exception $e) {
            error_reporting($level);
        }
    }

    public function addDocument(Document $document)
    {
        try {
            $level = error_reporting(0);
            parent::addDocument($document);
        } catch (\Exception $e) {
            error_reporting($level);
        }
    }
}
