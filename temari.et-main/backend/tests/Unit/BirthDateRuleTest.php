<?php

use App\Rules\BirthDate;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

function birthDateFails(mixed $value, int $minAge = 1, int $maxAge = 100): bool
{
    return Validator::make(
        ['dob' => $value],
        ['dob' => ['nullable', 'date', new BirthDate(minAgeYears: $minAge, maxAgeYears: $maxAge)]],
    )->fails();
}

it('rejects future birth dates', function () {
    expect(birthDateFails(now()->addDay()->toDateString()))->toBeTrue();
});

it('rejects a student younger than one year', function () {
    expect(birthDateFails(now()->subMonths(6)->toDateString()))->toBeTrue();
});

it('accepts a ten-year-old student', function () {
    expect(birthDateFails(now()->subYears(10)->toDateString()))->toBeFalse();
});

it('rejects an implausibly old birth date', function () {
    expect(birthDateFails('1890-01-01'))->toBeTrue();
});

it('enforces the employee working-age minimum', function () {
    expect(birthDateFails(now()->subYears(14)->toDateString(), minAge: 15))->toBeTrue()
        ->and(birthDateFails(now()->subYears(20)->toDateString(), minAge: 15))->toBeFalse();
});

it('leaves null and non-date values to the paired rules', function () {
    expect(birthDateFails(null))->toBeFalse();
});
