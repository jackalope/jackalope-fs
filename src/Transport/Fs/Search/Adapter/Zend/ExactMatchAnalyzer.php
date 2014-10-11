<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Search
 */

namespace Jackalope\Transport\Fs\Search\Adapter\Zend;

use ZendSearch\Lucene\Analysis;
use ZendSearch\Lucene\Analysis\Analyzer\Common\AbstractCommon;

/**
 * Analyzer which indexes values whole.
 *
 * Note that this is not good for full text searching, a different
 * index should be used for that.
 */
class ExactMatchAnalyzer extends AbstractCommon
{
    private $done = false;

    public function reset()
    {
        $this->done = false;

        if ($this->_input === null) {
            return;
        }

        // convert input into ascii
        if (PHP_OS != 'AIX') {
            $this->_input = iconv($this->_encoding, 'ASCII//TRANSLIT', $this->_input);
        }
        $this->_encoding = 'ASCII';
    }

    public function nextToken()
    {
        if ($this->done || $this->_input === null) {
            return null;
        }

        $token = new Analysis\Token($this->_input, 0, strlen($this->_input));
        $this->done = true;

        return $token;
    }
}


