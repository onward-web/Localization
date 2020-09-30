<?php

declare(strict_types=1);

namespace Arcanedev\Localization\Tests\Middleware;

use Arcanedev\Localization\Tests\TestCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Class     LocaleCookieRedirectTest
 *
 * @author   ARCANEDEV <arcanedev.maroc@gmail.com>
 */
class LocaleCookieRedirectTest extends TestCase
{
    /* -----------------------------------------------------------------
     |  Tests
     | -----------------------------------------------------------------
     */

    /** @test */
    public function it_can_redirect_with_locale_cookie(): void
    {
        $this->refreshApplication(false, false, true);

        /** @var Response|RedirectResponse $response */
        $response = $this->call('GET', $this->testUrlOne, [], ['locale' => 'fr']);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame($this->testUrlOne.'fr', $response->getTargetUrl());

        $response = $this->call('GET', $this->testUrlOne, [], ['locale' => 'es']);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame($this->testUrlOne . 'es', $response->getTargetUrl());
    }

    /** @test */
    public function it_can_pass_redirect_without_cookie(): void
    {
        $this->refreshApplication(false, true);
        session()->put('locale', null);

        /** @var RedirectResponse $response */
        $response = $this->call('GET', $this->testUrlOne);

        static::assertSame(302, $response->getStatusCode());
        static::assertSame($this->testUrlOne . 'en', $response->getTargetUrl());
    }

    /** @test */
    public function it_can_pass_redirect_with_unsupported_locale_in_cookie(): void
    {
        $this->refreshApplication(false, true, true);

        $supported = ['en', 'es'];
        localization()->setSupportedLocales($supported);
        static::assertSame($supported, localization()->getSupportedLocalesKeys());

        $response = $this->call('GET', $this->testUrlOne, [], ['locale' => 'fr']);
        static::assertSame(302, $response->getStatusCode());
    }
}
