<?php

namespace Jackalope\Transport\Fs\Search\Adapter\Zend;

use ZendSearch\Lucene\Index as BaseIndex;

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
        try {
            $level = error_reporting(0);
            parent::__destruct();
            error_reporting($level);
        } catch (\Exception $e) {
            error_reporting($level);
            if (false === $this->hideDestructException) {
                throw $e;
            }
        }
    }
}
