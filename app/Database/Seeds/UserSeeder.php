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

    public function run()
    {
        $this->db->table('users')->emptyTable();

        $faker = Factory::create('ko_KR');
        $faker->seed(20260824);

        $now = date('Y-m-d H:i:s');

        // 해시는 한 번만 계산해 돌려 쓴다. password_hash() 는 일부러 느린
        // 함수라 열 번만 불러도 체감된다(4편 2절).
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

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
