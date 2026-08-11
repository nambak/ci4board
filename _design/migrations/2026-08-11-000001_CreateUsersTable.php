<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep04 — 회원 테이블
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 190],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'nickname'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'level'         => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'role'          => ['type' => 'ENUM', 'constraint' => ['member', 'manager', 'admin'], 'default' => 'member'],
            'status'        => ['type' => 'ENUM', 'constraint' => ['active', 'dormant', 'suspended'], 'default' => 'active'],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users');
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
