<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep14 — DB 세션 핸들러용 테이블
 * CodeIgniter 4 DatabaseHandler 규격
 */
class CreateSessionsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => false],
            'timestamp'  => ['type' => 'TIMESTAMP', 'default' => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP')],
            'data'       => ['type' => 'BLOB', 'null' => false],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('timestamp', false, false, 'idx_timestamp');
        $this->forge->createTable('ci_sessions');
    }

    public function down(): void
    {
        $this->forge->dropTable('ci_sessions', true);
    }
}
