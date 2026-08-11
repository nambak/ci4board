<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep21 — JSON 값 검색용 생성 컬럼 + 인덱스
 *
 * Forge 는 GENERATED ALWAYS AS 를 지원하지 않으므로 raw SQL 을 쓴다.
 * 실제 서비스에서는 관리자가 "이 필드를 검색 가능하게" 체크했을 때
 * 이 형태의 DDL 을 동적으로 실행한다 (ep21 본문).
 */
class AddExtraGeneratedColumn extends Migration
{
    public function up(): void
    {
        $this->db->query("
            ALTER TABLE `posts`
              ADD COLUMN `extra_region` VARCHAR(50)
                GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(`extra`, '$.region'))) STORED,
              ADD INDEX `idx_extra_region` (`board_id`, `extra_region`)
        ");
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `posts` DROP INDEX `idx_extra_region`');
        $this->db->query('ALTER TABLE `posts` DROP COLUMN `extra_region`');
    }
}
