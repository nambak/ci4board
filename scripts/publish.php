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
 *   php scripts/publish.php docs/ep03.md --publish --yes 확인 절차 없이 (자동화용)
 *
 * 안전장치:
 *   - 실행할 때마다 대상 주소와 상태를 첫 줄에 찍는다.
 *   - 외부 주소에 공개 상태로 보내려 하면 한 번 묻는다(--yes 로 건너뜀).
 *
 * 환경변수:
 *   BLOG_API_URL     예: https://blog.unwanted.me
 *   BLOG_API_TOKEN   php spark api:token 으로 발급한 토큰.
 *                    비워 두면 macOS 키체인에서 꺼낸다(아래).
 *
 * 토큰을 키체인에 둘 때 (권장):
 *   security add-generic-password -s blog-api-token -a "$USER" -w
 *   토큰이 셸 히스토리와 프로세스 목록에 남지 않는다.
 *
 *   키체인 항목 이름을 바꾸려면:
 *     BLOG_API_TOKEN_SERVICE   기본값 blog-api-token
 *     BLOG_API_TOKEN_ACCOUNT   기본값 없음(서비스 이름만으로 찾는다)
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
    $files     = [];
    $publish   = false;
    $dryRun    = false;
    $status    = null;
    $assumeYes = false;

    foreach ($args as $arg) {
        if ($arg === '--publish') {
            $publish = true;
        } elseif ($arg === '--dry-run') {
            $dryRun = true;
        } elseif ($arg === '--yes' || $arg === '-y') {
            $assumeYes = true;
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
        fwrite(STDERR, "사용법: php scripts/publish.php <원고.md> [--publish] [--dry-run] [--yes]\n");

        return EXIT_USAGE;
    }

    $base  = rtrim((string) getenv('BLOG_API_URL'), '/');
    $token = resolveToken();

    if (! $dryRun && $base === '') {
        fwrite(STDERR, "BLOG_API_URL 을 설정해 주세요.\n");
        fwrite(STDERR, "  export BLOG_API_URL=https://blog.unwanted.me\n");

        return EXIT_USAGE;
    }

    if (! $dryRun && $token === '') {
        $service = tokenService();

        fwrite(STDERR, "토큰을 찾지 못했습니다.\n");
        fwrite(STDERR, "  키체인에 넣어 두려면:\n");
        fwrite(STDERR, "    security add-generic-password -s {$service} -a \"\$USER\" -w\n");
        fwrite(STDERR, "  또는 이번 한 번만:\n");
        fwrite(STDERR, "    export BLOG_API_TOKEN=발급받은_토큰\n");

        return EXIT_USAGE;
    }

    $status ??= $publish ? 'published' : 'draft';

    // 실제로 존재하는 파일만 대상으로 삼는다. 확인 화면에 "3편" 이라고 띄워 놓고
    // 그중 하나가 오타 경로면 숫자와 결과가 어긋난다.
    $targets = array_values(array_filter($files, 'is_file'));

    foreach (array_diff($files, $targets) as $missing) {
        fwrite(STDERR, "파일이 없습니다: {$missing}\n");
    }

    announceTarget($base, $status, $dryRun);

    if (! $dryRun && ! confirmIfRisky($base, $status, $targets, $assumeYes)) {
        fwrite(STDERR, "중단했습니다.\n");

        return EXIT_USAGE;
    }

    $failed = count($files) - count($targets);

    foreach ($targets as $file) {
        if (! publishOne($file, $base, $token, $status, $dryRun)) {
            $failed++;
        }
    }

    return $failed === 0 ? EXIT_OK : EXIT_FAIL;
}

/** 키체인 항목의 서비스 이름. */
function tokenService(): string
{
    $service = trim((string) getenv('BLOG_API_TOKEN_SERVICE'));

    return $service !== '' ? $service : 'blog-api-token';
}

/**
 * 토큰을 구한다. 환경변수가 있으면 그것을, 없으면 macOS 키체인을 본다.
 *
 * 환경변수를 먼저 보는 이유는 임시로 다른 토큰을 쓰는 경우가 있어서다 —
 * 로컬 서버로 시험 발행하거나, 토큰을 새로 발급해 확인해 볼 때.
 *
 * 키체인 쪽을 기본으로 두는 이유는 명령줄에 토큰을 쓰지 않기 위해서다.
 * `BLOG_API_TOKEN=xxx php scripts/publish.php ...` 로 실행하면 그 토큰이
 * 셸 히스토리 파일에 남고, 실행 중에는 `ps` 로도 보인다. 발행은 자주 하는
 * 일이라 그런 흔적이 계속 쌓인다.
 */
function resolveToken(): string
{
    $fromEnv = trim((string) getenv('BLOG_API_TOKEN'));

    if ($fromEnv !== '') {
        return $fromEnv;
    }

    if (PHP_OS_FAMILY !== 'Darwin') {
        return '';
    }

    $cmd     = 'security find-generic-password -s ' . escapeshellarg(tokenService());
    $account = trim((string) getenv('BLOG_API_TOKEN_ACCOUNT'));

    if ($account !== '') {
        $cmd .= ' -a ' . escapeshellarg($account);
    }

    // -w 는 비밀번호만 찍는다. 항목이 없으면 상태 44 로 끝나고 아무것도 안 나온다.
    $out = shell_exec($cmd . ' -w 2>/dev/null');

    return $out === null ? '' : trim($out);
}

/**
 * 어디로 보내는지 매번 첫 줄에 찍는다.
 *
 * 이 스크립트는 환경변수 하나로 대상이 바뀐다. 터미널을 새로 열거나 다른
 * 프로젝트를 오가다 보면 BLOG_API_URL 이 무엇인지 기억에 의존하게 되는데,
 * 그 기억이 틀렸을 때 대가가 크다 — 초안으로 두려던 글이 공개되거나,
 * 운영에 올리려던 글이 로컬에만 들어간다. 한 줄이면 그 실수가 대부분 사라진다.
 */
function announceTarget(string $base, string $status, bool $dryRun): void
{
    if ($dryRun) {
        echo "대상: (dry-run — 전송하지 않음)\n";

        return;
    }

    echo "대상: {$base}  [" . statusLabel($status) . ']'
        . (isLocalHost($base) ? '  (로컬)' : '') . "\n";
}

/** 상태 값의 한국어 라벨. 알 수 없는 값은 그대로 보여 준다(서버가 거부할 것이다). */
function statusLabel(string $status): string
{
    return ['draft' => '임시저장', 'published' => '공개', 'private' => '비공개'][$status] ?? $status;
}

/**
 * 외부 주소에 공개 상태로 보내려 하면 한 번 묻는다.
 *
 * 되돌리기 힘든 조합은 하나뿐이다 — 원격 + 공개. 로컬은 몇 번을 잘못 올려도
 * 지우면 그만이고, 임시저장은 원격이어도 독자에게 보이지 않는다. 그래서 그
 * 한 조합에만 확인을 건다. 모든 실행마다 물으면 금세 습관적으로 엔터를 치게 되고,
 * 그러면 확인이 아무것도 막지 못한다.
 *
 * @param list<string> $files
 */
function confirmIfRisky(string $base, string $status, array $files, bool $assumeYes): bool
{
    if ($files === [] || isLocalHost($base) || $assumeYes) {
        return true;
    }

    // 기본이 draft 여도 원고 front matter 가 status 를 덮어쓸 수 있다(publishOne 이
    // front matter 를 우선한다). 파일을 먼저 훑어 실제로 공개될 것만 골라낸다 —
    // 그러지 않으면 "--publish 를 안 붙였으니 안전하다" 는 착각이 생긴다.
    $public = [];

    foreach ($files as $file) {
        [$fm]      = parseFrontMatter((string) file_get_contents($file));
        $effective = trim((string) ($fm['status'] ?? $status));

        if ($effective !== 'draft') {
            $public[$file] = $effective;
        }
    }

    if ($public === []) {
        return true;
    }

    $count = count($public);

    fwrite(STDERR, "\n");
    fwrite(STDERR, "  ⚠  운영 주소에 공개 상태로 발행하려 합니다.\n");
    fwrite(STDERR, "     대상 : {$base}\n");
    fwrite(STDERR, "     원고 : {$count}편\n");

    $shown = 0;

    foreach ($public as $file => $effective) {
        if ($shown++ === 5) {
            fwrite(STDERR, '            … 외 ' . ($count - 5) . "편\n");
            break;
        }

        // 상태를 파일마다 붙인다. --publish 없이 front matter 만으로 공개되는
        // 경우가 있어서, 한 줄짜리 요약으로는 무엇이 왜 공개되는지 알 수 없다.
        fwrite(STDERR, '            - ' . basename($file) . '  [' . statusLabel($effective) . "]\n");
    }

    fwrite(STDERR, "\n");

    // 터미널이 아니면 물어볼 수 없다. 조용히 진행하는 쪽이 더 위험하므로 멈춘다.
    // 자동화에서 쓰려면 의도를 명시하라는 뜻이다.
    if (! stream_isatty(STDIN)) {
        fwrite(STDERR, "  대화형 터미널이 아니라 확인을 받을 수 없습니다. 의도한 실행이면 --yes 를 붙이세요.\n");

        return false;
    }

    fwrite(STDERR, '  계속하려면 y 를 입력하세요: ');

    $answer = strtolower(trim((string) fgets(STDIN)));

    return $answer === 'y' || $answer === 'yes';
}

/** 로컬 개발 주소인가. Herd/Valet 의 .test 와 흔한 로컬 호스트명을 본다. */
function isLocalHost(string $base): bool
{
    $host = strtolower((string) parse_url($base, PHP_URL_HOST));

    if ($host === '') {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
        return true;
    }

    foreach (['.test', '.local', '.localhost', '.internal'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return true;
        }
    }

    return false;
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

    foreach (explainFailure($res) as $line) {
        fwrite(STDERR, '    ' . $line . "\n");
    }

    return false;
}

/**
 * 실패 응답에서 사람이 읽을 줄만 뽑아낸다.
 *
 * 처리되지 않은 예외가 나면 CI4 는 개발 환경에서 스택 트레이스까지 담은 거대한
 * JSON 을 돌려준다. 그걸 그대로 찍으면 터미널이 수천 줄로 덮여 정작 원인 한 줄이
 * 묻힌다 — 실제로 그렇게 한 번 겪었다.
 *
 * @return list<string>
 */
function explainFailure(array $res): array
{
    // 정상적인 API 오류: {"messages": {"필드": "사유"}}
    if (isset($res['messages']) && is_array($res['messages'])) {
        $out = [];

        foreach ($res['messages'] as $field => $msg) {
            $out[] = $field . ': ' . (is_scalar($msg) ? $msg : json_encode($msg, JSON_UNESCAPED_UNICODE));
        }

        return $out;
    }

    // 처리되지 않은 예외: {"title": ..., "message": ..., "file": ..., "line": ...}
    if (isset($res['message'])) {
        $out = [(string) $res['message']];

        if (isset($res['file'], $res['line'])) {
            $out[] = basename((string) $res['file']) . ':' . $res['line'];
        }

        return $out;
    }

    return [substr(json_encode($res, JSON_UNESCAPED_UNICODE) ?: '', 0, 300)];
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
