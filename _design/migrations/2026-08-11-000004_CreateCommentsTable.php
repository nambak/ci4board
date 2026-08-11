<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep05 — 댓글 테이블 (대댓글 1단계까지)
 */
class CreateCommentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'parent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'depth'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'content'   => ['type' => 'TEXT'],

            'guest_name'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'guest_password' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            'is_secret' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status'    => ['type' => 'ENUM', 'constraint' => ['published', 'hidden'], 'default' => 'published'],
            'ip'        => ['type' => 'VARBINARY', 'constraint' => 16, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['post_id', 'parent_id', 'id'], false, false, 'idx_post_thread');
        $this->forge->addKey(['user_id', 'id'], false, false, 'idx_user');

        $this->forge->addForeignKey('post_id', 'posts', 'id', '', 'CASCADE', 'fk_comments_post');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL', 'fk_comments_user');
        $this->forge->addForeignKey('parent_id', 'comments', 'id', '', 'CASCADE', 'fk_comments_parent');

        $this->forge->createTable('comments');
    }

    public function down(): void
    {
        $this->forge->dropTable('comments', true);
    }
}
