<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('member_import_rows');
        Schema::dropIfExists('member_imports');
    }

    public function down(): void
    {
        // Member import was removed. Recreate via a prior overlay migration if needed.
    }
};
