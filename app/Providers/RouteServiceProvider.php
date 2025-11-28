<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

// inside boot(): call this method
class RouteServiceProvider
{
    protected function configureRateLimiting(): void
    {
        // existing API/web limiters ...
        RateLimiter::for('apply', function (Request $request) {
            // Primary key: email (if present) + IP. Falls back to IP only.
            $email = strtolower((string) $request->input('applicant_email'));
            $key   = $email ? "apply:{$email}|{$request->ip()}" : "apply-ip:{$request->ip()}";

            // 3 submissions per 1 day
            return [
                Limit::perDay(3)->by($key)
                    ->response(function () {
                        return back()
                            ->withErrors(['applicant_email' => 'You have reached the maximum of 3 applications today. Please try again tomorrow.'])
                            ->withInput();
                    }),
            ];
        });
    }
}
