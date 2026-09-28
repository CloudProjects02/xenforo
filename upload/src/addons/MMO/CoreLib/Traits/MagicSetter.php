<?php

namespace MMO\CoreLib\Traits;

use BadMethodCallException;
use MMO\CoreLib\Util\Str;

trait MagicSetter
{
    /**
     * The implementation of the current interface.
     *
     * @param string $method
     *   A method name.
     * @param array|null $arguments
     *   A list of arguments.
     *
     * @return self
     */
    public function __call(string $method, ?array $arguments): self
    {
        $setter = 'set' . Str::studly($method);
        $adder = 'add' . Str::studly($method);

        if (method_exists($this, $setter)) {
            $method = $setter;
        } elseif (method_exists($this, $adder)) {
            $method = $adder;
        } else {
            throw new BadMethodCallException();
        }

        $this->$method(...$arguments);

        return $this;
    }
}