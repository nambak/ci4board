<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep19 — boards.field_schema (JSON) 추가
 *
 * 기획서에서 허용한 두 건의 ALTER 중 두 번째.
 */
class AddFieldSchemaToBoards extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('boards', [
            'field_schema' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'categories',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('boards', 'field_schema');
    }
}
