<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep05 — 게시글 테이블 (단일 테이블 전략의 중심)
 *
 * extra(JSON) 컬럼은 여기서 만들지 않는다. ep18에서 추가한다.
 */
class CreatePostsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'board_id' => ['type' => 'INT', 'unsigned' => true],
            'user_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'category' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],

            'title'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'content'      => ['type' => 'MEDIUMTEXT'],
            'content_html' => ['type' => 'MEDIUMTEXT', 'null' => true],

            'is_notice' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_secret' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'guest_name'     => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'guest_password' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            'view_count'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'comment_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'like_count'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'file_count'    => ['type' => 'INT', 'unsigned' => true, 'default' => 0],

            'status' => ['type' => 'ENUM', 'constraint' => ['published', 'hidden'], 'default' => 'published'],
            'ip'     => ['type' => 'VARBINARY', 'constraint' => 16, 'null' => true],

            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        // 목록 쿼리 전용 커버링 인덱스: WHERE board_id=? AND deleted_at IS NULL ORDER BY is_notice DESC, id DESC
        $this->forge->addKey(['board_id', 'deleted_at', 'is_notice', 'id'], false, false, 'idx_board_list');
        $this->forge->addKey(['board_id', 'created_at'], false, false, 'idx_board_created');
        $this->forge->addKey(['user_id', 'id'], false, false, 'idx_user');

        $this->forge->addForeignKey('board_id', 'boards', 'id', '', 'CASCADE', 'fk_posts_board');
        $this->forge->addForeignKey('user_id', 'users', 'id', '', 'SET NULL', 'fk_posts_user');

        $this->forge->createTable('posts');
    }

    public function down(): void
    {
        $this->forge->dropTable('posts', true);
    }
}
