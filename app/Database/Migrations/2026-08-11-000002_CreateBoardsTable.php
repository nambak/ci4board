<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep04 — 게시판 설정 테이블
 *
 * field_schema 컬럼은 여기서 만들지 않는다. ep19에서 추가한다.
 */
class CreateBoardsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'skin'        => ['type' => 'ENUM', 'constraint' => ['list', 'gallery', 'faq'], 'default' => 'list'],
            'per_page'    => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 20],
            'categories'  => ['type' => 'JSON', 'null' => true],

            'use_comment' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'use_file'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'use_secret'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'use_editor'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],

            'max_files'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 5],
            'max_file_size' => ['type' => 'INT', 'unsigned' => true, 'default' => 5242880],
            'allowed_ext'   => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'jpg,jpeg,png,gif,webp,pdf,zip'],

            'read_level'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'write_level'    => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'comment_level'  => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],
            'download_level' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 1],

            'sort_order' => ['type' => 'SMALLINT', 'default' => 0],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug');
        $this->forge->addKey(['is_active', 'sort_order'], false, false, 'idx_active_sort');
        $this->forge->createTable('boards');
    }

    public function down(): void
    {
        $this->forge->dropTable('boards', true);
    }
}
