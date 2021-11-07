<?php

namespace spawn\system\Core\Custom\Extensions;

use spawn\system\Throwables\AbstractException;
use Throwable;

class FailedConvertToReflectionObjectException extends AbstractException {

    public function __construct($class, $method, Throwable $previous = null)
    {
        if(!is_scalar($class)) {
            try                     { $class = (string)$class;      }
            catch(\Exception $e)    { $class = json_encode($class); }
        }

        if(!is_scalar($method)) {
            try                     { $class = (string)$class;      }
            catch(\Exception $e)    { $class = json_encode($class); }
        }

        parent::__construct([
            'class' => $class,
            'method' => $method,
        ], $previous);
    }

    protected function getMessageTemplate(): string
    {
        return 'Error when converting data to ReflectionObject with class "%class%" and method "%method%"!';
    }

    protected function getExitCode(): int
    {
        return 37;
    }
}