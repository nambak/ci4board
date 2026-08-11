<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep18 — posts.extra (JSON) 추가
 *
 * 기획서에서 허용한 두 건의 ALTER 중 첫 번째.
 */
class AddExtraToPosts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('posts', [
            'extra' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'content_html',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('posts', 'extra');
    }
}
