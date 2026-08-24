<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * ep06 — 시더 전체를 순서대로 부른다.
 *
 * 순서가 곧 외래 키 의존성이다. posts 는 board_id 와 user_id 가 가리킬
 * 대상이 이미 있어야 들어간다. 4편의 마이그레이션 순서와 같은 이유다.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call('BoardSeeder');
        $this->call('UserSeeder');
        $this->call('PostSeeder');
    }
}
