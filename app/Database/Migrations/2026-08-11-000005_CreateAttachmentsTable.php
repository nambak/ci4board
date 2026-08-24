<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep05 — 첨부파일 테이블
 *
 * post_id 가 NULL 인 행 = 아직 글에 붙지 않은 임시 업로드 (ep24)
 */
class CreateAttachmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'       => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'post_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'board_id' => ['type' => 'INT', 'unsigned' => true],

            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'stored_name'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'path'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'size'          => ['type' => 'INT', 'unsigned' => true],

            'is_image'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'width'      => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'height'     => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => true],
            'thumb_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            'download_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'sort_order'     => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['post_id', 'sort_order'], false, false, 'idx_post_sort');
        // 임시 업로드 청소용 (post_id IS NULL AND created_at < ?)
        $this->forge->addKey(['created_at'], false, false, 'idx_created');

        $this->forge->addForeignKey('post_id', 'posts', 'id', '', 'CASCADE', 'fk_attachments_post');
        $this->forge->addForeignKey('board_id', 'boards', 'id', '', 'CASCADE', 'fk_attachments_board');

        $this->forge->createTable('attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('attachments', true);
    }
}
