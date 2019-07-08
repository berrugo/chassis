<?php

namespace BlastCloud\Chassis;

use BlastCloud\Chassis\Filters\Filters;
use BlastCloud\Chassis\Helpers\File;
use BlastCloud\Chassis\Traits\Macros;
use PHPUnit\Framework\{Assert, ExpectationFailedException, TestCase};
use PHPUnit\Framework\MockObject\Matcher\InvokedRecorder;

/**
 * Class Expectation
 * @package Guzzler
 * @method $this endpoint(string $uri, string $method)
 * @method $this get(string $uri)
 * @method $this post(string $uri)
 * @method $this put(string $uri)
 * @method $this delete(string $uri)
 * @method $this patch(string $uri)
 * @method $this options(string $uri)
 * @method $this synchronous()
 * @method $this asynchronous()
 * @method $this withHeader(string $key, $value)
 * @method $this withHeaders(array $values)
 * @method $this withOption(string $key, $value)
 * @method $this withOptions(array $values)
 * @method $this withQuery(array $values, bool $exclusive = false)
 * @method $this withJson(array $values, bool $exclusive = false)
 * @method $this withForm(array $form, bool $exclusive = false)
 * @method $this withFormField(string $key, $value)
 * @method $this withBody($body, bool $exclusive = false)
 * @method $this withEndpoint(string $uri, string $method)
 * @method $this withFile(string $field, File $file)
 * @method $this withFiles(array $files, bool $exclusive = false)
 * @method $this withCallback(\Closure $callback, string $message = null)
 */
class Expectation
{
    use Filters, Macros;

    /** @var Chassis */
    protected $chassis;

    /** @var InvokedRecorder */
    protected $times;

    protected const PHPUNIT_82 = 'PHPUnit\Framework\MockObject\Invocation';
    protected const PHPUNIT_81 = 'PHPUnit\Framework\MockObject\Invocation\ObjectInvocation';

    /**
     * Each value in this array becomes a convenience method over endpoint().
     */
    public const VERBS = [
        'get',
        'post',
        'put',
        'delete',
        'patch',
        'options'
    ];

    /**
     * Expectation constructor.
     * @param null|InvokedRecorder $times
     * @param null|Chassis $guzzler
     */
    public function __construct($times = null, $guzzler = null)
    {
        $this->times = $times;
        $this->chassis = $guzzler;
    }

    /**
     * This is used exclusively for the convenience verb methods.
     *
     * @param string $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        if ($this->runMacro($name, $this, $arguments)) {
            return $this;
        }

        // Next try to see if it's a with* method we can use.
        if ($filter = $this->isFilter($name)) {
            $filter->add($name, $arguments);
            return $this;
        }

        throw new \Error(sprintf("Call to undefined method %s::%s()", __CLASS__, $name));
    }

    /**
     * Set a follow through; either response, callable, or Exception.
     *
     * @param $response
     * @param int $times
     * @return $this
     */
    public function will($response, int $times = 1)
    {
        for ($i = 0; $i < $times; $i++) {
            $this->chassis->queueResponse($response);
        }

        return $this;
    }

    /**
     * An alias of 'will'.
     *
     * @param $response
     * @param int $times
     * @return $this
     */
    public function willRespond($response, int $times = 1)
    {
        $this->will($response, $times);

        return $this;
    }

    protected function runFilters(array $history)
    {
        foreach ($this->filters as $filter) {
            $history = $filter($history);
        }

        return $history;
    }

    /**
     * Iterate over the history and verify the invocations against it.
     *
     * @param TestCase $instance
     * @param array $history
     */
    public function __invoke(TestCase $instance, array $history): void
    {
        $class = class_exists(self::PHPUNIT_82)
            ? self::PHPUNIT_82
            : self::PHPUNIT_81;

        foreach ($this->runFilters($history) as $i) {
            $this->times->invoked(new $class('', '', [], '', $i['request']));
        }

        try {
            // Invocation Counts
            $this->times->verify();
        } catch (ExpectationFailedException $e) {
            Assert::fail($e->getMessage() . ' ' . $this->__toString());
        }
    }

    public function __toString()
    {
        $endpoint = $messages = '';

        foreach ($this->filters as $filter) {
            $messages .= $filter->__toString() . "\n";
            if (property_exists($filter, 'endpoint')) {
                $endpoint = $filter->endpoint;
            }
        }

        return <<<MESSAGE


Expectation: {$endpoint}
-----------------------------
{$messages}
MESSAGE;
    }
}