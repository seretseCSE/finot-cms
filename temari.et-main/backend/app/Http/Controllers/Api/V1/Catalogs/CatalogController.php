<?php

namespace App\Http\Controllers\Api\V1\Catalogs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Base for the platform catalog studio (/api/v1/catalogs/*): Temari.et staff
 * CRUD over the seed catalogs (banks, grade levels, subjects, health
 * conditions, school directory). Every action is deny-by-default behind the
 * `catalogs.manage` PLATFORM permission — school roles never reach in.
 */
abstract class CatalogController extends Controller
{
    protected function assertCatalogManager(Request $request): void
    {
        abort_unless(
            $request->user()?->hasPlatformPermission('catalogs.manage') === true,
            403,
        );
    }
}
