<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * ep06 — 게시판 세 개.
 *
 * 스킨·기능 토글·권한이 서로 다른 셋을 고른다. 하나만 있으면
 * "게시판마다 다르다" 를 확인할 방법이 없다.
 */
class BoardSeeder extends Seeder
{
    public function run()
    {
        // FK 가 걸려 있어 TRUNCATE 는 거절당한다(ERROR 1701). DELETE 로 비운다.
        $this->db->table('boards')->emptyTable();

        $now = date('Y-m-d H:i:s');

        // 기본값과 다른 컬럼만 적는다. 나머지는 boards 의 DEFAULT 가 채운다.
        $boards = [
            [
                'slug'        => 'notice',
                'name'        => '공지사항',
                'description' => '운영 공지와 점검 안내입니다.',
                'per_page'    => 15,
                'categories'  => $this->json(['일반', '점검', '이벤트']),
                'use_comment' => 0,   // 공지에는 댓글을 받지 않는다
                'write_level' => 10,  // 관리자만 쓴다
                'sort_order'  => -10, // 목록 맨 앞. 음수를 쓸 수 있어서 다른 행을 안 건드린다
            ],
            [
                'slug'        => 'free',
                'name'        => '자유게시판',
                'description' => '무슨 이야기든 좋습니다.',
                'categories'  => $this->json(['잡담', '질문', '후기']),
                'use_secret'  => 1,
            ],
            [
                'slug'           => 'gallery',
                'name'           => '사진첩',
                'description'    => '찍은 사진을 올리는 곳입니다.',
                'skin'           => 'gallery',
                'per_page'       => 12,
                'categories'     => $this->json(['풍경', '일상', '장비']),
                'max_files'      => 10,
                'allowed_ext'    => 'jpg,jpeg,png,gif,webp',
                'download_level' => 0,
            ],
        ];

        foreach ($boards as $board) {
            $this->db->table('boards')->insert($board + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * JSON 컬럼에 넣을 문자열.
     *
     * JSON_UNESCAPED_UNICODE 가 없으면 한글이 \uXXXX 로 저장된다.
     * 동작에는 지장이 없지만 DB 에서 눈으로 읽히지 않고 LIKE 도 걸리지 않는다.
     */
    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
