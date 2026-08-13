<?php

declare(strict_types=1);

/**
 * 원고 발행 스크립트.
 *
 * docs/epNN.md 의 front matter 를 읽어 블로그 발행 API 로 보낸다.
 * 대표 이미지가 지정돼 있으면 먼저 업로드하고 그 파일명을 글에 붙인다.
 *
 * 사용:
 *   php scripts/publish.php docs/ep03.md                 임시저장(draft)으로 올린다
 *   php scripts/publish.php docs/ep03.md --publish       바로 공개한다
 *   php scripts/publish.php docs/ep03.md --dry-run       보낼 내용만 보여 준다
 *   php scripts/publish.php docs/*.md --publish          여러 편을 한 번에
 *
 * 환경변수:
 *   BLOG_API_URL     예: https://blog.unwanted.me
 *   BLOG_API_TOKEN   php spark api:token 으로 발급한 토큰
 *
 * 같은 원고를 몇 번 보내도 결과가 같다(slug 기준 upsert). 오타를 고쳐 다시
 * 보내는 일이 잦은데 그때마다 글이 늘어나면 쓸 수 없는 도구가 되기 때문이다.
 */

const EXIT_OK    = 0;
const EXIT_USAGE = 1;
const EXIT_FAIL  = 2;

function main(array $argv): int
{
    $args    = array_slice($argv, 1);
    $files   = [];
    $publish = false;
    $dryRun  = false;
    $status  = null;

    foreach ($args as $arg) {
        if ($arg === '--publish') {
            $publish = true;
        } elseif ($arg === '--dry-run') {
            $dryRun = true;
        } elseif (str_starts_with($arg, '--status=')) {
            $status = substr($arg, 9);
        } elseif (str_starts_with($arg, '--')) {
            fwrite(STDERR, "알 수 없는 옵션: {$arg}\n");

            return EXIT_USAGE;
        } else {
            $files[] = $arg;
        }
    }

    if ($files === []) {
        fwrite(STDERR, "사용법: php scripts/publish.php <원고.md> [--publish] [--dry-run]\n");

        return EXIT_USAGE;
    }

    $base  = rtrim((string) getenv('BLOG_API_URL'), '/');
    $token = (string) getenv('BLOG_API_TOKEN');

    if (! $dryRun && ($base === '' || $token === '')) {
        fwrite(STDERR, "BLOG_API_URL 과 BLOG_API_TOKEN 환경변수를 설정해 주세요.\n");
        fwrite(STDERR, "  export BLOG_API_URL=https://blog.unwanted.me\n");
        fwrite(STDERR, "  export BLOG_API_TOKEN=발급받은_토큰\n");

        return EXIT_USAGE;
    }

    $status ??= $publish ? 'published' : 'draft';
    $failed = 0;

    foreach ($files as $file) {
        if (! is_file($file)) {
            fwrite(STDERR, "파일이 없습니다: {$file}\n");
            $failed++;
            continue;
        }

        if (! publishOne($file, $base, $token, $status, $dryRun)) {
            $failed++;
        }
    }

    return $failed === 0 ? EXIT_OK : EXIT_FAIL;
}

function publishOne(string $file, string $base, string $token, string $status, bool $dryRun): bool
{
    [$fm, $body] = parseFrontMatter((string) file_get_contents($file));

    $name = basename($file);

    foreach (['title', 'slug'] as $required) {
        if (trim((string) ($fm[$required] ?? '')) === '') {
            fwrite(STDERR, "  {$name}: front matter 에 {$required} 이(가) 없습니다.\n");

            return false;
        }
    }

    if (trim($body) === '') {
        fwrite(STDERR, "  {$name}: 본문이 비어 있습니다.\n");

        return false;
    }

    $payload = [
        'title'  => $fm['title'],
        'slug'   => $fm['slug'],
        'body'   => trim($body),
        'status' => $fm['status'] ?? $status,
    ];

    foreach (['category', 'tags', 'published_at'] as $optional) {
        if (isset($fm[$optional]) && $fm[$optional] !== '') {
            $payload[$optional] = $fm[$optional];
        }
    }

    // 대표 이미지: front matter 의 cover 경로를 먼저 업로드하고 파일명만 붙인다.
    $cover = trim((string) ($fm['cover'] ?? ''));

    if ($cover !== '') {
        $coverPath = resolveCover($file, $cover);

        if ($coverPath === null) {
            fwrite(STDERR, "  {$name}: 대표 이미지를 찾을 수 없습니다: {$cover}\n");

            return false;
        }

        if ($dryRun) {
            echo "  [dry-run] 이미지 업로드 예정: {$coverPath}\n";
        } else {
            $uploaded = uploadCover($base, $token, $coverPath);

            if ($uploaded === null) {
                return false;
            }

            $payload['image'] = $uploaded;
            echo "  이미지 업로드: {$uploaded}\n";
        }
    }

    if ($dryRun) {
        echo "  [dry-run] {$name} → slug={$payload['slug']}, status={$payload['status']}, "
            . mb_strlen($payload['body']) . "자\n";

        return true;
    }

    [$code, $res] = httpJson($base . '/api/posts', $token, $payload);

    if ($code === 200 || $code === 201) {
        $verb = ($res['outcome'] ?? '') === 'created' ? '생성' : '갱신';
        echo "  {$verb}: {$name} → {$res['url']} [{$res['status']}]\n";

        return true;
    }

    fwrite(STDERR, "  실패({$code}): {$name}\n");

    foreach ((array) ($res['messages'] ?? ['error' => $res]) as $field => $msg) {
        fwrite(STDERR, '    ' . $field . ': ' . (is_scalar($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE)) . "\n");
    }

    return false;
}

/**
 * cover 경로를 찾는다. 원고 파일 기준 상대경로와 저장소 루트 기준을 모두 시도한다.
 *
 * front matter 에 `assets/cover/cover_ep03.png` 라고 적는 쪽이 자연스러운데,
 * 원고는 docs/ 안에 있어서 그 경로가 원고 기준으로는 맞지 않는다. 둘 다 받아
 * 주면 원고를 쓸 때 어느 기준인지 고민하지 않아도 된다.
 */
function resolveCover(string $mdFile, string $cover): ?string
{
    if (str_starts_with($cover, '/') && is_file($cover)) {
        return $cover;
    }

    $candidates = [
        dirname($mdFile) . '/' . $cover,   // 원고 기준
        getcwd() . '/' . $cover,           // 실행 위치(보통 저장소 루트) 기준
        dirname($mdFile) . '/../' . $cover, // docs/ 에서 한 단계 위
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return realpath($path) ?: $path;
        }
    }

    return null;
}

/** 이미지를 업로드하고 저장된 파일명을 돌려준다. 실패하면 null. */
function uploadCover(string $base, string $token, string $path): ?string
{
    $ch = curl_init($base . '/api/uploads');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_POSTFIELDS     => ['image' => new CURLFile($path)],
        CURLOPT_TIMEOUT        => 60,
    ]);

    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        fwrite(STDERR, "  이미지 업로드 실패: {$err}\n");

        return null;
    }

    $res = json_decode((string) $raw, true);

    if ($code !== 201 || ! isset($res['name'])) {
        fwrite(STDERR, "  이미지 업로드 실패({$code}): " . substr((string) $raw, 0, 300) . "\n");

        return null;
    }

    return (string) $res['name'];
}

/**
 * JSON POST. [상태코드, 디코딩된 응답] 을 돌려준다.
 *
 * @return array{0:int, 1:array<string,mixed>}
 */
function httpJson(string $url, string $token, array $payload): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT    => 60,
    ]);

    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return [0, ['error' => $err]];
    }

    return [$code, (array) (json_decode((string) $raw, true) ?? ['error' => $raw])];
}

/**
 * front matter 와 본문을 분리한다.
 *
 * 개행 매칭에 \R 을 쓰지 않는다. \R 은 바이트 0x85 를 개행으로 보는데 그 바이트가
 * 한글 UTF-8 인코딩 가운데 나타나(예: '테' = ED 85 8C) 문자를 쪼갠다.
 * 블로그의 PostsImport 가 같은 이유로 개행 3종을 명시한다.
 *
 * @return array{0: array<string,mixed>, 1: string}
 */
function parseFrontMatter(string $raw): array
{
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);

    if (! preg_match('/^---\s*(?:\r\n|\n|\r)(.*?)(?:\r\n|\n|\r)---\s*(?:\r\n|\n|\r)?(.*)$/s', $raw, $m)) {
        return [[], $raw];
    }

    $out = [];

    foreach (preg_split('/\r\n|\n|\r/', $m[1]) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, ':');

        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));

        if (str_starts_with($val, '[') && str_ends_with($val, ']')) {
            $inner     = trim(substr($val, 1, -1));
            $out[$key] = $inner === ''
                ? []
                : array_map(static fn ($s) => trim(trim($s), "\"'"), explode(',', $inner));
            continue;
        }

        $out[$key] = trim($val, "\"'");
    }

    return [$out, $m[2]];
}

exit(main($argv));
