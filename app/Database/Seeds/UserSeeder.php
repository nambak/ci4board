<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Faker\Factory;

/**
 * ep06 — 회원 열 명.
 *
 * 로그인은 13편부터다. 지금은 posts.user_id 가 가리킬 대상이 필요할 뿐이다.
 */
class UserSeeder extends Seeder
{
    /** 개발용 공통 비밀번호. 운영 데이터에는 절대 쓰지 않는다. */
    private const PASSWORD = 'ci4board!';

    /**
     * 위 비밀번호를 password_hash() 로 한 번 계산해 박아 둔 값.
     *
     * 매번 계산하면 bcrypt 가 소금을 새로 뽑아 실행할 때마다 값이 달라진다.
     * 다른 컬럼은 시드를 고정해 재현되는데 이 컬럼만 어긋난다(6편 5절).
     */
    private const PASSWORD_HASH = '$2y$10$DgGwMXxwUU7yNWdny15fOeODeEHrhmmy2INTAtiKGCjUJQhY5uvN2';

    public function run()
    {
        $this->db->table('users')->emptyTable();

        $faker = Factory::create('ko_KR');
        $faker->seed(20260824);

        $now = date('Y-m-d H:i:s');

        $hash = self::PASSWORD_HASH;

        $rows = [
            ['email' => 'admin@example.com', 'username' => 'admin', 'nickname' => '관리자', 'level' => 10, 'role' => 'admin'],
            ['email' => 'manager@example.com', 'username' => 'manager', 'nickname' => '운영자', 'level' => 5, 'role' => 'manager'],
        ];

        for ($i = 1; $i <= 8; $i++) {
            $rows[] = [
                'email'    => "member{$i}@example.com",
                'username' => "member{$i}",
                'nickname' => $faker->name(),   // 겹쳐도 된다. nickname 은 UNIQUE 가 아니다
                'level'    => 1,
                'role'     => 'member',
            ];
        }

        foreach ($rows as &$row) {
            $row['password_hash'] = $hash;
            $row['created_at']    = $now;
            $row['updated_at']    = $now;
        }
        unset($row);

        $this->db->table('users')->insertBatch($rows);
    }
}
