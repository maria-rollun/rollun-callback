<?php
/**
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license LICENSE.md New BSD License
 */

namespace rollun\callback\Callback\Interrupter;

interface InterrupterInterface
{
    /**
     * @param $value
     * @return mixed
     */
    public function __invoke($value): PromiseInterface;
}
