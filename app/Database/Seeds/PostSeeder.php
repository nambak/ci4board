<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

/**
 * ep06 — 글 200건.
 *
 * 목록·페이징·검색을 시험할 수 있을 만큼 만들되, 전부 똑같이 생기지 않게 한다.
 * 비회원 글, 공지, 숨긴 글, 지운 글이 섞여 있어야 9편 이후의 필터가
 * 실제로 일하는지 확인할 수 있다.
 */
class PostSeeder extends Seeder
{
    /** 게시판별 글 수. 합이 200이다. */
    private const COUNTS = ['notice' => 12, 'free' => 148, 'gallery' => 40];

    /** 상단 고정할 공지 수. */
    private const NOTICES = ['notice' => 3, 'free' => 2, 'gallery' => 0];

    private const SOFT_DELETED = 5;
    private const HIDDEN       = 3;

    /**
     * 글이 놓일 기간. 날짜를 박아 둔다.
     *
     * '-180 days' 같은 상대 표현을 쓰면 시드를 고정해도 실행할 때마다
     * 다른 데이터가 나온다. 이유는 6편 5절에 있다.
     */
    private const SINCE = '2026-02-24 00:00:00';
    private const UNTIL = '2026-08-23 23:59:59';

    private const TITLES = [
        'notice' => [
            '%s 정기 점검 안내',
            '%s 서비스 점검 완료 안내',
            '%s 이벤트 당첨자 발표',
            '%s 고객센터 운영 안내',
        ],
        'free' => [
            '%s 다녀왔습니다',
            '%s 후기 남깁니다',
            '%s 추천 좀 부탁드려요',
            '요즘 %s 때문에 고민입니다',
            '%s 어떻게들 생각하세요?',
        ],
        'gallery' => [
            '%s에서 찍은 사진',
            '%s 나들이',
            '%s 풍경 몇 장',
        ],
    ];

    private const SUBJECTS = [
        'notice'  => ['1월', '3월', '설 연휴', '추석 연휴', '여름 휴가철', '연말'],
        'free'    => ['이직', '점심 메뉴', '재택근무', '새 키보드', '주말 등산', '전기요금', '중고 거래'],
        'gallery' => ['한강', '제주도', '남산', '베란다', '동네 골목', '퇴근길'],
    ];

    public function run()
    {
        $this->db->table('posts')->emptyTable();

        $faker = Factory::create('ko_KR');

        // 시드를 고정하면 몇 번을 돌려도 같은 데이터가 나온다. 화면이 바뀐 게
        // 코드 때문인지 데이터 때문인지 구분할 수 있어야 하기 때문이다.
        // Faker 의 seed() 는 PHP 의 mt_rand() 까지 함께 고정한다.
        $faker->seed(20260824);

        $boards = $this->boards();
        $users  = $this->db->table('users')->select('id, role')->get()->getResultArray();

        $userIds = array_column($users, 'id');
        $staffIds = array_column(
            array_filter($users, static fn ($u) => $u['role'] !== 'member'),
            'id',
        );

        // 비회원 글의 수정·삭제용 해시. 200건마다 새로 계산하면 몇 분이 걸린다.
        $guestHash = password_hash('1234', PASSWORD_DEFAULT);

        // 게시판 자리를 섞는다. 섞지 않으면 게시판별로 id 가 뭉쳐서
        // "최근 글" 이 한 게시판에만 몰린다.
        $slots = [];

        foreach (self::COUNTS as $slug => $count) {
            $slots = array_merge($slots, array_fill(0, $count, $slug));
        }
        shuffle($slots);

        // 작성 시각을 오름차순으로 정렬해 앞에서부터 쓴다. 이래야 id 순서와
        // 시간 순서가 같아지고, 9편의 `ORDER BY id DESC` 가 최신순이 된다.
        $dates = [];

        for ($i = 0, $total = count($slots); $i < $total; $i++) {
            $dates[] = $faker->dateTimeBetween(self::SINCE, self::UNTIL);
        }
        usort($dates, static fn ($a, $b) => $a <=> $b);

        // 본문 조각을 미리 만들어 두고 돌려 쓴다. 글마다 새로 생성하면
        // 느리고, 200번의 생성이 난수열 한가운데 끼어 결과가 흔들린다(5절).
        $paragraphs = [];

        for ($i = 0; $i < 24; $i++) {
            $paragraphs[] = $faker->realText(mt_rand(150, 400));
        }

        $rows = [];

        foreach ($slots as $i => $slug) {
            $board = $boards[$slug];

            // 공지사항은 write_level 이 10 이다. 비회원 글이 있으면 안 된다.
            // 시더는 애플리케이션의 권한 검사를 거치지 않으므로 여기서 맞춘다.
            $isStaffOnly = $slug === 'notice';
            $isGuest     = ! $isStaffOnly && mt_rand(1, 100) <= 20;
            $written     = $dates[$i]->format('Y-m-d H:i:s');

            $rows[] = [
                'board_id'       => $board['id'],
                'user_id'        => match (true) {
                    $isGuest     => null,
                    $isStaffOnly => $staffIds[array_rand($staffIds)],
                    default      => $userIds[array_rand($userIds)],
                },
                'category'       => $board['categories'][array_rand($board['categories'])],
                'title'          => $this->title($slug),
                'content'        => $this->content($paragraphs),
                'content_html'   => null,   // 11편에서 채운다
                'is_notice'      => 0,
                'is_secret'      => $slug === 'free' && mt_rand(1, 100) <= 5 ? 1 : 0,
                'guest_name'     => $isGuest ? $faker->name() : null,
                'guest_password' => $isGuest ? $guestHash : null,
                'view_count'     => mt_rand(0, 500),

                // 카운터는 0 그대로 둔다. 댓글도 첨부도 아직 없는데 숫자만
                // 채워 넣으면 22편에서 만들 재계산 명령이 첫 실행부터
                // 틀린 값을 고치게 된다(3편 5절).
                'comment_count' => 0,
                'like_count'    => 0,
                'file_count'    => 0,

                'status'     => 'published',
                'ip'         => inet_pton($faker->ipv4()),
                'created_at' => $written,
                'updated_at' => $written,
                'deleted_at' => null,
            ];
        }

        $this->markNotices($rows, $boards);
        $this->markExceptions($rows);

        // 한 건씩 넣으면 왕복이 200번이다. 100건씩 끊어 보낸다.
        foreach (array_chunk($rows, 100) as $chunk) {
            $this->db->table('posts')->insertBatch($chunk);
        }
    }

    /**
     * slug 로 게시판을 찾아 둔다.
     *
     * id 를 1, 2, 3 으로 적어 두면 안 된다. 시더가 DELETE 로 비우기 때문에
     * 두 번째 실행부터 auto_increment 가 이어져 id 가 달라진다.
     *
     * @return array<string, array{id:int, categories:list<string>}>
     */
    private function boards(): array
    {
        $out = [];

        foreach ($this->db->table('boards')->get()->getResultArray() as $row) {
            $out[$row['slug']] = [
                'id'         => (int) $row['id'],
                'categories' => json_decode((string) $row['categories'], true) ?? [null],
            ];
        }

        return $out;
    }

    private function title(string $slug): string
    {
        $templates = self::TITLES[$slug];
        $subjects  = self::SUBJECTS[$slug];

        return sprintf($templates[array_rand($templates)], $subjects[array_rand($subjects)]);
    }

    /** 미리 만들어 둔 조각에서 두셋을 골라 잇는다.
     *
     * @param list<string> $pool
     */
    private function content(array $pool): string
    {
        $picked = [];

        for ($i = 0, $n = mt_rand(2, 3); $i < $n; $i++) {
            $picked[] = $pool[array_rand($pool)];
        }

        return implode("\n\n", $picked);
    }

    /**
     * 게시판마다 가장 최근 글 몇 개를 공지로 올린다.
     *
     * @param list<array<string, mixed>> $rows
     * @param array<string, array{id:int, categories:list<string>}> $boards
     */
    private function markNotices(array &$rows, array $boards): void
    {
        foreach (self::NOTICES as $slug => $count) {
            if ($count === 0) {
                continue;
            }

            $indexes = array_keys(array_filter(
                $rows,
                static fn ($row) => $row['board_id'] === $boards[$slug]['id'],
            ));

            foreach (array_slice($indexes, -$count) as $index) {
                $rows[$index]['is_notice'] = 1;
            }
        }
    }

    /**
     * 지운 글과 숨긴 글을 섞는다.
     *
     * 정상인 데이터만 있으면 목록에서 걸러지는지 확인할 수가 없다.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function markExceptions(array &$rows): void
    {
        $picked = (array) array_rand($rows, self::SOFT_DELETED + self::HIDDEN);

        foreach (array_slice($picked, 0, self::SOFT_DELETED) as $index) {
            $rows[$index]['deleted_at'] = $rows[$index]['created_at'];
        }

        foreach (array_slice($picked, self::SOFT_DELETED) as $index) {
            $rows[$index]['status'] = 'hidden';
        }
    }
}
