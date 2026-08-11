<?php

namespace App\Models;

use CodeIgniter\Model;

class PostModel extends Model
{
    protected $table         = 'posts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'board_id', 'user_id', 'category', 'title', 'content', 'content_html',
        'extra', 'is_notice', 'is_secret', 'guest_name', 'guest_password', 'status', 'ip',
    ];

    // ep18 — JSON 컬럼 캐스팅 (CI 4.5.0+)
    // 주의: 부모 클래스가 `protected array $casts` 로 선언되어 있어 타입을 반드시 명시해야 한다.
    protected array $casts = [
        'extra'     => '?json-array',
        'is_notice' => 'int-bool',
        'is_secret' => 'int-bool',
    ];
}
