<?php

namespace Laravel\Dusk;

use Closure;
use Exception;
use Throwable;
use ReflectionFunction;
use Illuminate\Support\Collection;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;

abstract class TestCase extends FoundationTestCase
{
    use SupportsChrome;

    /**
     * All of the active browser instances.
     *
     * @var array
     */
    protected static $browsers = [];

    /**
     * The callbacks that should be run on class tear down.
     *
     * @var array
     */
    protected static $afterClassCallbacks = [];

    /**
     * Register the base URL with Dusk.
     *
     * @before
     * @return void
     */
    public function propagateScaffoldingToBrowser()
    {
        Browser::$baseUrl = $this->baseUrl();

        Browser::$userResolver = function () {
            return $this->user();
        };
    }

    /**
     * Tear down the Dusk test case class.
     *
     * @afterClass
     * @return void
     */
    public static function tearDownDuskClass()
    {
        static::closeAll();

        foreach (static::$afterClassCallbacks as $callback) {
            $callback();
        }
    }

    /**
     * Register an "after class" tear down callback.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function afterClass(Closure $callback)
    {
        static::$afterClassCallbacks[] = $callback;
    }

    /**
     * Create a new browser instance.
     *
     * @param  \Closure  $callback
     * @return \Laravel\Dusk\Browser|void
     */
    public function browse(Closure $callback)
    {
        $browsers = $this->createBrowsersFor($callback);

        try {
            $callback(...$browsers->all());
        } catch (Exception $e) {
            $this->captureFailuresFor($browsers);

            throw $e;
        } catch (Throwable $e) {
            $this->captureFailuresFor($browsers);

            throw $e;
        } finally {
            static::$browsers = $this->closeAllButPrimary($browsers);
        }
    }

    /**
     * Create the browser instances needed for the given callback.
     *
     * @param  \Closure  $callback
     * @return array
     */
    protected function createBrowsersFor(Closure $callback)
    {
        if (count(static::$browsers) === 0) {
            static::$browsers = collect([new Browser($this->createWebDriver())]);
        }

        $additional = $this->browsersNeededFor($callback) - 1;

        for ($i = 0; $i < $additional; $i++) {
            static::$browsers->push(new Browser($this->createWebDriver()));
        }

        return static::$browsers;
    }

    /**
     * Get the nmber of browsers needed for a given callback.
     *
     * @param  \Closure  $callback
     * @return int
     */
    protected function browsersNeededFor(Closure $callback)
    {
        return (new ReflectionFunction($callback))->getNumberOfParameters();
    }

    /**
     * Capture failure screenshots for each browser.
     *
     * @param  \Illuminate\Support\Collection  $browsers
     * @return void
     */
    protected function captureFailuresFor($browsers)
    {
        $browsers->each(function ($browser, $key) {
            $browser->screenshot('failure-'.$this->getName().'-'.$key);
        });
    }

    /**
     * Close all of the browsers except the primary (first) one.
     *
     * @param  \Illuminate\Support\Collection  $browsers
     * @return \Illuminate\Support\Collection
     */
    protected function closeAllButPrimary($browsers)
    {
        $browsers->slice(1)->each->quit();

        return $browsers->take(1);
    }

    /**
     * Close all of the active browsers.
     *
     * @return void
     */
    public static function closeAll()
    {
        Collection::make(static::$browsers)->each->quit();

        static::$browsers = collect();
    }

    /**
     * Create the remote web driver instance.
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    protected function createWebDriver()
    {
        return retry(5, function () {
            return RemoteWebDriver::create('http://localhost:9515', DesiredCapabilities::chrome());
        }, 50);
    }

    /**
     * Determine the application's base URL.
     *
     * @var string
     */
    protected function baseUrl()
    {
        return config('app.url');
    }

    /**
     * Get a callback that returns the default user to authenticate.
     *
     * @return \Closure
     */
    protected function user()
    {
        throw new Exception("User resolver has not been set.");
    }
}