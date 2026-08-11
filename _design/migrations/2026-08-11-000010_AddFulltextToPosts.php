<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ep26 — 제목/본문 전문 검색 인덱스
 *
 * 주의: 한글 검색 품질은 DB 종류에 따라 다르다.
 *  - MySQL 8.0  : WITH PARSER ngram 사용 가능
 *  - MariaDB    : ngram 파서 없음 → 기본 파서 + 토큰 길이 조정
 * ep26 본문에서 이 차이를 실측으로 비교한다.
 */
class AddFulltextToPosts extends Migration
{
    public function up(): void
    {
        if ($this->supportsNgram()) {
            $this->db->query('ALTER TABLE `posts` ADD FULLTEXT KEY `ft_title_content` (`title`, `content`) WITH PARSER ngram');
        } else {
            $this->db->query('ALTER TABLE `posts` ADD FULLTEXT KEY `ft_title_content` (`title`, `content`)');
        }
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE `posts` DROP INDEX `ft_title_content`');
    }

    /**
     * ngram 파서 플러그인이 살아있는지 확인 (MySQL 5.7+ 전용)
     */
    private function supportsNgram(): bool
    {
        try {
            $row = $this->db->query(
                "SELECT PLUGIN_STATUS FROM information_schema.PLUGINS WHERE PLUGIN_NAME = 'ngram'"
            )->getRowArray();

            return $row !== null && strtoupper($row['PLUGIN_STATUS']) === 'ACTIVE';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
