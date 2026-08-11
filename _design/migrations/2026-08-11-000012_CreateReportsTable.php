<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep33 — 신고
 *
 * target_type/target_id 다형 참조라 FK 를 걸 수 없다.
 * 정합성은 애플리케이션과 삭제 시 정리 로직이 책임진다 (ep33 본문에서 그 이유를 설명).
 */
class CreateReportsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'target_type' => ['type' => 'ENUM', 'constraint' => ['post', 'comment']],
            'target_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'reason'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'status'      => ['type' => 'ENUM', 'constraint' => ['pending', 'resolved', 'rejected'], 'default' => 'pending'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'resolved_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['target_type', 'target_id'], false, false, 'idx_target');
        $this->forge->addKey(['status', 'id'], false, false, 'idx_status');

        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL', 'fk_reports_user');

        $this->forge->createTable('reports');
    }

    public function down(): void
    {
        $this->forge->dropTable('reports', true);
    }
}
