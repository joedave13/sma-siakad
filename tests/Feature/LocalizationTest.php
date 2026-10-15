<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

it('runs the application in wib with indonesian locale', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Jakarta')
        ->and(config('app.locale'))->toBe('id')
        ->and(now()->timezone->getName())->toBe('Asia/Jakarta');
});

it('formats dates with indonesian month names', function (): void {
    expect(Carbon::parse('2026-10-15')->translatedFormat('d F Y'))->toBe('15 Oktober 2026');
});

it('returns indonesian validation messages', function (): void {
    $messages = Validator::make([], ['name' => 'required'])->errors()->all();

    expect($messages)->not->toBeEmpty()
        ->and(implode(' ', $messages))->toContain('wajib diisi');
});
