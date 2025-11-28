<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
       Schema::create('announcements', function (Blueprint $t) {
    $t->id();
    $t->string('title');
    $t->text('body');
    $t->string('category')->default('news'); // news | event | notice
    $t->boolean('pinned')->default(false);
    $t->boolean('is_published')->default(false);
    $t->timestamp('published_at')->nullable();
    $t->string('slug')->nullable()->unique(); // optional
    $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $t->timestamps();
    $t->softDeletes();
});
    }
    public function down(): void {
        Schema::dropIfExists('announcements');
    }
};
