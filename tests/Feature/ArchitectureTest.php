<?php

declare(strict_types=1);

// 1. Debugging functions forbidden
arch('no debug functions in application')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

// 2. Controllers must not use DB facade directly (layering rule)
arch('controllers do not use DB facade')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

// 3. Models must not depend on Controllers
arch('models do not depend on controllers')
    ->expect('App\Models')
    ->not->toUse('App\Http\Controllers');

// 4. Services must not call authorization gate directly
arch('services do not call authorization')
    ->expect('App\Services')
    ->not->toUse('Illuminate\Support\Facades\Gate');

// 5. Models must extend Eloquent Model
arch('models extend eloquent model')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->ignoring('App\Models\Concerns\ClearsCacheOnChange');

// 6. FormRequests must extend FormRequest
arch('form requests extend base form request')
    ->expect('App\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');
