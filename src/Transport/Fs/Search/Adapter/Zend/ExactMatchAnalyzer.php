<?php

namespace Jackalope\Transport\Fs\Search\Adapter\Zend;

use ZendSearch\Lucene\Analysis;
use ZendSearch\Lucene\Analysis\Analyzer\Common\AbstractCommon;
use Jackalope\Transport\Fs\Search\Adapter\ZendSearchAdapter;

/**
 * Analyzer which indexes values whole.
 *
 * Note that this is not good for full text searching, a different
 * index should be used for that.
 */
class ExactMatchAnalyzer extends AbstractCommon
{
    private $position = 0;

    /**
     * {@inheritDoc}
     */
    public function reset()
    {
        $this->position = 0;

        if ($this->_input === null) {
            return;
        }

        // convert input into ascii
        if (PHP_OS != 'AIX') {
            $this->_input = iconv($this->_encoding, 'ASCII//TRANSLIT', $this->_input);
        }
        $this->_encoding = 'ASCII';
    }

    /**
     * {@inheritDoc}
     */
    public function nextToken()
    {
        // we provide a token for each individual value when separated by the
        // multivalue separator
        $parts = explode(ZendSearchAdapter::MULTIVALUE_SEPARATOR, $this->_input);

        if (!isset($parts[$this->position]) || $this->_input === null) {
            return null;
        }


        $token = new Analysis\Token($parts[$this->position], 0, strlen($this->_input));
        $this->position++;

        return $token;
    }
}


