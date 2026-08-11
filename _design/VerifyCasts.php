<?php

namespace App\Commands;

use App\Models\PostModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * 설계 검증용 커맨드 — 실행: php spark verify:casts
 *
 * 확인 항목
 *  1) Model $casts 로 extra(JSON) 가 배열 <-> 문자열 왕복되는가
 *  2) 생성 컬럼 extra_region 이 자동으로 채워지는가
 *  3) 한글이 깨지지 않고 저장되는가
 *  4) 생성 컬럼으로 검색이 되는가
 */
class VerifyCasts extends BaseCommand
{
    protected $group       = 'Verify';
    protected $name        = 'verify:casts';
    protected $description = '스키마 설계 검증 (JSON 캐스팅 / 생성 컬럼 / 인코딩)';

    public function run(array $params): void
    {
        $db = Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS=0');
        $db->table('posts')->truncate();
        $db->table('boards')->truncate();
        $db->query('SET FOREIGN_KEY_CHECKS=1');

        $db->table('boards')->insert([
            'slug'         => 'event',
            'name'         => '행사게시판',
            'skin'         => 'gallery',
            'field_schema' => json_encode([
                ['key' => 'region', 'label' => '지역', 'type' => 'select', 'options' => ['서울', '부산'], 'required' => true, 'searchable' => true],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $boardId = $db->insertID();

        $model = new PostModel();

        // 배열을 그대로 넣는다 — $casts 가 json_encode 를 대신해야 한다
        $id = $model->insert([
            'board_id'   => $boardId,
            'title'      => '부산 개발자 모임',
            'content'    => '부산에서 열리는 정기 모임입니다.',
            'extra'      => ['region' => '부산', 'event_date' => '2026-09-01'],
            'is_notice'  => true,
            'guest_name' => '홍길동',
        ], true);

        $row = $model->find($id);

        CLI::write('--- 1) Model::find() 캐스팅 결과 ---', 'yellow');
        CLI::write('extra 타입      : ' . gettype($row['extra']));
        CLI::write('extra[region]   : ' . ($row['extra']['region'] ?? '(없음)'));
        CLI::write('is_notice 타입  : ' . gettype($row['is_notice']) . ' / ' . var_export($row['is_notice'], true));

        $raw = $db->table('posts')->select('extra, extra_region')->where('id', $id)->get()->getRowArray();

        CLI::write('');
        CLI::write('--- 2) DB 원본 ---', 'yellow');
        CLI::write('extra (raw)     : ' . $raw['extra']);
        CLI::write('extra_region    : ' . var_export($raw['extra_region'], true) . '   <- 생성 컬럼');

        CLI::write('');
        CLI::write('--- 3) 한글 인코딩 ---', 'yellow');
        CLI::write('title 앞 2글자 HEX : ' . strtoupper(bin2hex(mb_substr($row['title'], 0, 2))) . '  (기대 EBB680EC82B0)');

        CLI::write('');
        CLI::write('--- 4) 생성 컬럼으로 검색 ---', 'yellow');
        $found = $model->where('board_id', $boardId)->where('extra_region', '부산')->findAll();
        CLI::write('검색 결과 건수  : ' . count($found));

        CLI::write('');
        CLI::write('--- 5) JSON 안에 한글이 유니코드 이스케이프 없이 저장됐는가 ---', 'yellow');
        CLI::write(str_contains($raw['extra'], '부산') ? 'OK — 원문 그대로 저장' : 'NG — \\uXXXX 로 이스케이프됨');
    }
}
