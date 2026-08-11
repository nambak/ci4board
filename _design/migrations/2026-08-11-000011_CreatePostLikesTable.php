<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep27 — 좋아요(추천)
 */
class CreatePostLikesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['post_id', 'user_id'], 'uq_post_user');
        $this->forge->addKey(['user_id', 'id'], false, false, 'idx_user');

        $this->forge->addForeignKey('post_id', 'posts', 'id', '', 'CASCADE', 'fk_likes_post');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'CASCADE', 'fk_likes_user');

        $this->forge->createTable('post_likes');
    }

    public function down(): void
    {
        $this->forge->dropTable('post_likes', true);
    }
}
