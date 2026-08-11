# CI4 멀티보드 플랫폼 강좌 기획서

> 작성일: 2026-08-11 · 상태: **v2 (설계 검증 완료)**
> 검증 환경: CodeIgniter 4.7.4 / PHP 8.4 / MariaDB 10.11 — 마이그레이션 12종 실행·롤백 왕복 성공. 상세는 7장.
> 저장소: `github.com/nambak/ci4board` (예정) · 태그: `ep01` ~ `ep37`
> 발행처: [blog.unwanted.me](https://blog.unwanted.me/)

---

## 0. 강좌 포지셔닝

### 독자 정의

앞선 **[ci4blog 시리즈 30편](https://github.com/nambak/ci4blog)** 을 완주한 독자. 즉 다음은 **이미 안다고 전제**한다.

- CI4 설치, `.env`, 디렉토리 구조
- MVC와 라우팅의 기본
- 마이그레이션 작성, Model 기본 CRUD
- 뷰 레이아웃(`extend`/`section`)
- Git 기본 사용

따라서 "composer create-project부터 시작"하지 않는다. 대신 **1편에서 설계 이야기로 바로 진입**한다.

### 한 줄 컨셉

> 블로그 하나를 만들 줄 아는 사람이, **설정만으로 게시판을 찍어내는 플랫폼**을 만드는 법을 배운다.

### 이전 시리즈와의 차별점

| | ci4blog (30편) | ci4board (37편) |
|---|---|---|
| 목표 | 동작하는 결과물 만들기 | **확장 가능한 구조** 설계하기 |
| 데이터 | 고정 스키마 | 설정 기반 + JSON 확장 필드 |
| 핵심 역량 | 프레임워크 사용법 | 서비스 계층, 정책, 동적 렌더링 |
| 관리자 | 없음 (직접 SQL) | 게시판을 만드는 관리자 UI |

---

## 1. 아키텍처 결정 (ADR)

### ADR-001. 멀티보드 데이터 저장 전략 → **단일 테이블 + JSON 확장 필드 (C안)**

**결정**: 모든 게시글은 `posts` 테이블 하나에 저장한다. 게시판 구분은 `board_id`, 게시판별 커스텀 필드 값은 `posts.extra` (JSON 컬럼)에 담는다. 필드 정의는 `boards.field_schema` (JSON 컬럼)에 담는다.

**대안과 기각 사유**

- **A안 (게시판별 테이블 동적 CREATE)** — 국내 게시판 솔루션의 전통적 방식. 기각: 런타임 DDL은 마이그레이션 이력을 무너뜨리고, CI4 Model/Query Builder와 궁합이 나쁘다. 학습용 강좌에서 가르치기에 위험한 습관.
- **B안 (단일 테이블, 확장 없음)** — 깔끔하지만 "게시판마다 다른 입력 항목"이라는 멀티보드의 핵심 요구를 못 푼다. 결국 컬럼 추가로 회귀.

**감수하는 비용**

- JSON 컬럼은 그냥은 인덱싱되지 않는다 → 생성 컬럼(generated column) + 인덱스를 **21편에서 정면으로 다룬다**. 이건 부담이 아니라 강좌의 세일즈 포인트다.
- 게시글이 수백만 건 규모로 가면 파티셔닝 논의가 필요하다 → 36편에서 "언제 A안이 정답이 되는가"로 짧게 언급하고 범위 밖으로 명시.

### ADR-002. 기술 스택 고정

| 항목 | 값 | 근거 |
|---|---|---|
| CodeIgniter | **4.7.x** (기준 4.7.4, 2026-07-07 릴리스) | 현재 안정 최신 |
| PHP | **8.2 이상** | CI4 4.7의 최소 요구사항 |
| 필수 확장 | `intl`, `mbstring` | CI4 요구사항 |
| DB (개발/운영) | **MySQL 8.0 이상 권장** / MariaDB 10.6 이상 가능 | JSON 함수 + 생성 컬럼 인덱스 필요 |
| DB 콜레이션 | `utf8mb4` + `utf8mb4_unicode_ci` **`.env`에 명시** | 미지정 시 Forge가 `general_ci`로 생성 (검증 F-005) |
| 프론트 | 서버 사이드 렌더링 + 최소 JS(바닐라) | 프레임워크 학습 방해 최소화 |
| 에디터 | 초반 `<textarea>` → 26편에서 교체 | 의존성을 늦게 도입 |

> **중요 ①**: 이전 시리즈는 운영 DB로 SQLite를 썼지만, 이번에는 **SQLite를 권장 스택에서 제외**한다. JSON 생성 컬럼 인덱스 전략이 MySQL/MariaDB 기준이기 때문. 1편 준비물 안내에서 명시할 것.
>
> **중요 ②**: MariaDB와 MySQL은 JSON 구현이 다르다. MariaDB의 `JSON`은 **네이티브 타입이 아니라 `LONGTEXT` + `CHECK (json_valid(...))` 별칭**이다(검증 F-004). 마이그레이션과 기본 JSON 함수는 양쪽 모두 동작하므로 강좌 진행에는 문제가 없지만, 18편에서 이 차이를 한 문단으로 짚는다.

### ADR-003. JSON 캐스팅은 Model `$casts` 사용

CI4 4.5.0부터 Model에 `$casts`가 도입되어 `json` / `json-array` 타입을 지원한다. Entity의 `$casts`와는 **동시 사용 불가**이므로, 이 강좌는 **Entity 없이 Model `$casts`만** 쓴다. (Entity 도입은 학습 부담 대비 이득이 적다.)

```php
// app/Models/PostModel.php
// 주의: 부모가 `protected array $casts` 로 선언되어 있어 타입을 반드시 붙여야 한다.
//       `protected $casts` 로 쓰면 Fatal error (검증 F-003).
protected array $casts = [
    'extra'     => '?json-array',
    'is_notice' => 'int-bool',
    'is_secret' => 'int-bool',
];
```

`json-array` 세터는 내부적으로 `json_encode($value, JSON_UNESCAPED_UNICODE)` 를 쓴다. 따라서 한글이 `\uXXXX` 로 이스케이프되지 않고 원문 그대로 저장된다 — DB에서 눈으로 읽히고 `LIKE` 검색도 걸린다. (`system/DataCaster/Cast/JsonCast.php` 확인)

### ADR-004. 한글 검색은 **LIKE 기반을 본선으로** 한다

**결정**: 26편의 기본 구현은 `LIKE '%키워드%'`. FULLTEXT + ngram은 "MySQL 8.0을 쓴다면" 조건부 보너스로 강등한다.

**근거 (실측, 검증 F-001·F-002)**

- MariaDB 10.11에는 ngram 파서 자체가 없다 → `WITH PARSER ngram` 실행 시 `ERROR 1128: Function 'ngram' is not defined`
- MariaDB 기본 FULLTEXT의 한글 토큰은 **조사가 붙은 어절 단위**다. 실제 인덱스에 들어간 토큰: `부산에서`, `세미나를`, `모임입니다`, `코드이그나이터`
- 그 결과: `그나이터` 같은 **단어 중간 검색은 0건**. `부산`(2글자)은 `innodb_ft_min_token_size=3` 에 걸려 0건. `부산*` 은 매칭.
- 같은 질의를 `LIKE '%그나이터%'` 로 하면 정상 검색됨

**결론**: 게시판 규모(수만 건)에서 LIKE 풀스캔은 충분히 빠르고, 무엇보다 **DB 종류를 가리지 않는다**. 독자가 MariaDB를 쓰든 MySQL을 쓰든 26편이 똑같이 동작하는 쪽이 강좌로서 옳다. FULLTEXT는 "왜 한글에서 잘 안 되는가"를 실측으로 보여주는 **교보재로 쓰고**, 대안(전용 검색 엔진)을 언급하며 마무리한다.

---

## 2. 최종 완성물 스펙

37편을 마쳤을 때 독자가 손에 쥐는 것.

### 2-1. 방문자 화면

| 화면 | URL | 설명 |
|---|---|---|
| 게시판 목록 | `/board/{slug}` | 페이징, 검색, 카테고리 필터, 공지 상단 고정 |
| 게시글 상세 | `/board/{slug}/{id}` | 조회수, 첨부 다운로드, 댓글, 좋아요, 이전/다음 글 |
| 글쓰기/수정 | `/board/{slug}/write`, `/edit/{id}` | 게시판 설정에 따라 **입력 폼이 달라짐** |
| 통합 검색 | `/search` | 전체 게시판 교차 검색 |
| 회원 | `/auth/*`, `/mypage` | 가입, 로그인, 프로필, 내가 쓴 글 |

### 2-2. 게시판 스킨 3종

| 스킨 | 용도 | 특징 |
|---|---|---|
| `list` | 공지사항, 자유게시판 | 표 형태 |
| `gallery` | 사진첩, 포트폴리오 | 썸네일 그리드 |
| `faq` | FAQ, Q&A | 아코디언, 본문 즉시 펼침 |

### 2-3. 관리자 화면

| 화면 | 설명 |
|---|---|
| 대시보드 | 게시판별 글/댓글 수, 최근 활동 |
| **게시판 관리** | 게시판 생성·수정·삭제. slug, 이름, 스킨, 페이지당 개수, 권한 레벨, 기능 토글 |
| **커스텀 필드 빌더** | 게시판별 추가 입력 항목을 UI로 정의 (텍스트/숫자/선택/체크박스/날짜/URL) |
| 게시물 관리 | 검색, 이동, 숨김, 일괄 삭제 |
| 댓글 관리 | 검색, 숨김, 일괄 삭제 |
| 회원 관리 | 검색, 레벨/역할 변경, 정지 |
| 신고 처리 | 신고 목록과 처리 상태 |

### 2-4. 권한 모델

- 회원 `level`: 정수 (0=비회원, 1=일반, 5=매니저, 10=관리자)
- 게시판별 `read_level` / `write_level` / `comment_level` / `download_level`
- 판정은 **Policy 클래스 한 곳**에서만 (17편)

### 2-5. 범위 밖 (명시적 제외)

강좌 1편에서 "이건 안 다룹니다"로 미리 못 박는다. 분량 폭주를 막는 장치.

- 소셜 로그인, 이메일 인증
- 에디터 자체 구현 (외부 라이브러리 사용)
- 실시간 알림, WebSocket
- 다국어(i18n)
- REST API (후속 시리즈로 예고)

---

## 3. 전체 DB 스키마

> **원칙**: 스키마는 지금 완성형으로 설계하고, 회차별로 필요한 테이블만 잘라서 마이그레이션한다.
> 뒤 회차에서 `ALTER TABLE`로 컬럼을 덧붙이는 마이그레이션은 **아래 2건만** 허용한다. 그 외의 스키마 변경이 필요해지면 기획서를 먼저 고친다.

### 마이그레이션 투입 시점

| 대상 | 편 | 비고 |
|---|---|---|
| `users`, `boards` | ep04 | `boards.field_schema` **제외** |
| `posts`, `comments`, `attachments` | ep05 | `posts.extra` **제외** |
| `ci_sessions` | ep14 | DB 세션 핸들러 도입 시 |
| `ALTER posts ADD extra JSON` | ep18 | 허용된 컬럼 추가 ① |
| `ALTER boards ADD field_schema JSON` | ep19 | 허용된 컬럼 추가 ② |
| 생성 컬럼 + 인덱스 | ep21 | 관리자가 필요할 때 생성 |
| `FULLTEXT ft_title_content` | ep26 | **선택 사항**. MySQL이면 ngram, MariaDB면 기본 파서로 분기 (ADR-004) |
| `post_likes` | ep27 | |
| `reports` | ep33 | |

### ERD 개요

```
users ──< posts >── boards
  │        │  │        │
  │        │  └──< attachments
  │        └──< comments (self-ref: parent_id)
  │        └──< post_likes
  └──< reports (polymorphic: post|comment)

ci_sessions (독립)
```

### 3-1. `users`

| 컬럼 | 타입 | 설명 |
|---|---|---|
| id | BIGINT UNSIGNED AI PK | |
| email | VARCHAR(190) UNIQUE | 로그인 ID |
| username | VARCHAR(50) UNIQUE | URL 노출용 |
| nickname | VARCHAR(50) | 표시 이름 |
| password_hash | VARCHAR(255) | `password_hash()` 결과 |
| level | TINYINT UNSIGNED DEFAULT 1 | 권한 레벨 |
| role | ENUM('member','manager','admin') DEFAULT 'member' | |
| status | ENUM('active','dormant','suspended') DEFAULT 'active' | |
| last_login_at | DATETIME NULL | |
| created_at / updated_at | DATETIME | |

### 3-2. `boards` ★ 플랫폼의 심장

| 컬럼 | 타입 | 설명 |
|---|---|---|
| id | INT UNSIGNED AI PK | |
| slug | VARCHAR(50) UNIQUE | URL 세그먼트 |
| name | VARCHAR(100) | |
| description | VARCHAR(255) NULL | |
| skin | ENUM('list','gallery','faq') DEFAULT 'list' | |
| per_page | SMALLINT UNSIGNED DEFAULT 20 | |
| categories | JSON NULL | 게시판 내 분류 배열 |
| **field_schema** | **JSON NULL** | **커스텀 필드 정의** (ep19에서 추가) |
| use_comment / use_file / use_secret / use_editor | TINYINT(1) | 기능 토글 |
| max_files | TINYINT UNSIGNED DEFAULT 5 | |
| max_file_size | INT UNSIGNED DEFAULT 5242880 | 바이트 |
| allowed_ext | VARCHAR(255) | `jpg,png,pdf,...` |
| read_level / write_level / comment_level / download_level | TINYINT UNSIGNED | |
| sort_order | SMALLINT DEFAULT 0 | |
| is_active | TINYINT(1) DEFAULT 1 | |
| created_at / updated_at | DATETIME | |

**`field_schema` 구조 (19편에서 확정)**

```json
[
  {
    "key": "region",
    "label": "지역",
    "type": "select",
    "options": ["서울", "부산", "대구"],
    "required": true,
    "searchable": true,
    "show_in_list": true
  },
  {
    "key": "event_date",
    "label": "행사일",
    "type": "date",
    "required": false,
    "searchable": false,
    "show_in_list": true
  }
]
```

지원 `type`: `text` / `textarea` / `number` / `select` / `checkbox` / `date` / `url`

### 3-3. `posts` ★ 단일 테이블

| 컬럼 | 타입 | 설명 |
|---|---|---|
| id | BIGINT UNSIGNED AI PK | |
| board_id | INT UNSIGNED FK→boards | |
| user_id | BIGINT UNSIGNED NULL FK→users | NULL = 비회원 |
| category | VARCHAR(50) NULL | boards.categories 중 하나 |
| title | VARCHAR(255) | |
| content | MEDIUMTEXT | 원본(마크다운 또는 HTML) |
| content_html | MEDIUMTEXT NULL | 렌더링 캐시 |
| **extra** | **JSON NULL** | **커스텀 필드 값** (ep18에서 추가) |
| is_notice | TINYINT(1) DEFAULT 0 | |
| is_secret | TINYINT(1) DEFAULT 0 | |
| guest_name | VARCHAR(50) NULL | 비회원 작성자명 |
| guest_password | VARCHAR(255) NULL | 비회원 수정/삭제용 해시 |
| view_count / comment_count / like_count / file_count | INT UNSIGNED DEFAULT 0 | 비정규화 카운터 |
| status | ENUM('published','hidden') DEFAULT 'published' | |
| ip | VARBINARY(16) NULL | `inet_pton()` |
| created_at / updated_at | DATETIME | |
| deleted_at | DATETIME NULL | 소프트 딜리트 |

**인덱스**

```sql
KEY idx_board_list    (board_id, deleted_at, is_notice, id)
KEY idx_board_created (board_id, created_at)
KEY idx_user          (user_id, id)
```

> `id DESC` 를 명시하지 않는다. 목록 정렬이 `ORDER BY is_notice DESC, id DESC` 여도 InnoDB는 인덱스를 역방향으로 스캔할 수 있어 오름차순 인덱스로 충분하다. (초안 v1에서 DESC 인덱스를 넣었던 것을 검증 중 제거)

> FULLTEXT 인덱스는 기본 스키마에서 뺐다. 26편에서 선택적으로 추가한다 — 이유는 ADR-004 참조.

**JSON 검색용 생성 컬럼 (21편)** — `searchable: true`인 필드에만 관리자가 인덱스 생성

```sql
ALTER TABLE posts
  ADD COLUMN extra_region VARCHAR(50)
    GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(extra, '$.region'))) STORED,
  ADD INDEX idx_extra_region (board_id, extra_region);
```

실측으로 확인한 21편의 핵심 장면 — 같은 조건인데 인덱스를 타느냐 마느냐가 갈린다.

```
-- 생성 컬럼으로 검색  → key: idx_extra_region  (Using index condition)
EXPLAIN SELECT id,title FROM posts WHERE board_id=2 AND extra_region='부산';

-- JSON 함수로 직접 검색 → key: idx_board_created (Using where) — 게시판 전체를 훑는다
EXPLAIN SELECT id,title FROM posts WHERE board_id=2
  AND JSON_UNQUOTE(JSON_EXTRACT(extra,'$.region'))='부산';
```

### 3-4. `comments`

| 컬럼 | 타입 |
|---|---|
| id | BIGINT UNSIGNED AI PK |
| post_id | BIGINT UNSIGNED FK→posts |
| user_id | BIGINT UNSIGNED NULL |
| parent_id | BIGINT UNSIGNED NULL (self) |
| depth | TINYINT UNSIGNED DEFAULT 0 (최대 1) |
| content | TEXT |
| guest_name / guest_password | VARCHAR |
| is_secret | TINYINT(1) |
| status | ENUM('published','hidden') |
| ip | VARBINARY(16) |
| created_at / updated_at / deleted_at | DATETIME |

인덱스: `(post_id, parent_id, id)`

### 3-5. `attachments`

| 컬럼 | 타입 |
|---|---|
| id | BIGINT UNSIGNED AI PK |
| post_id | BIGINT UNSIGNED NULL FK→posts (NULL=임시 업로드) |
| board_id | INT UNSIGNED |
| original_name | VARCHAR(255) |
| stored_name | VARCHAR(255) |
| path | VARCHAR(255) — `writable/uploads/{board}/{Ym}/` |
| mime | VARCHAR(100) |
| size | INT UNSIGNED |
| is_image | TINYINT(1) |
| width / height | SMALLINT UNSIGNED NULL |
| thumb_path | VARCHAR(255) NULL |
| download_count | INT UNSIGNED DEFAULT 0 |
| sort_order | TINYINT UNSIGNED |
| created_at | DATETIME |

### 3-6. `post_likes`

`id`, `post_id`, `user_id`, `created_at` — UNIQUE `(post_id, user_id)`

### 3-7. `reports`

`id`, `target_type` ENUM('post','comment'), `target_id`, `user_id`, `reason` VARCHAR(255), `status` ENUM('pending','resolved','rejected'), `created_at`, `resolved_at` — 인덱스 `(target_type, target_id)`

### 3-8. `ci_sessions`

CI4 `DatabaseHandler` 규격 그대로 (14편).

---

## 4. 회차별 목차 (전 37편)

읽기 시간은 이전 시리즈 평균(10~14분) 기준 추정치.

### 파트 0 — 시작 (ep01~ep02)

| # | 제목 | 목표 | 완성 시점 산출물 |
|---|---|---|---|
| 01 | 왜 멀티보드인가 — 세 가지 설계 갈림길 | A/B/C안 비교, C안 선택 근거, 완성 화면 미리보기, 범위 선언 | (코드 없음) |
| 02 | 프로젝트 세팅과 이 강좌의 규칙 | CI4 4.7 설치, `.env`, DB 연결, 태그 규칙, 폴더 컨벤션 | 빈 프로젝트 부팅 |

### 파트 1 — 데이터 골격 (ep03~ep06)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 03 | 설계도부터 그린다 — 테이블 8개 한눈에 | ERD, 정규화 판단, 비정규화 카운터를 두는 이유 | 02 |
| 04 | 마이그레이션 ①: users, boards | 게시판 설정 테이블이 왜 이렇게 넓은지 | 03 |
| 05 | 마이그레이션 ②: posts, comments, attachments | 인덱스 설계 근거, 소프트 딜리트 | 04 |
| 06 | 시더로 놀거리 만들기 | Faker로 게시판 3개 + 글 200건 | 05 |

### 파트 2 — 게시판 엔진 코어 (ep07~ep12)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 07 | 라우트 하나로 모든 게시판 받기 | `/board/{slug}` 플레이스홀더 라우팅, 404 처리 | 06 |
| 08 | BoardService — 설정을 읽어오는 계층 | slug→설정 조회, 캐시, 컨트롤러 다이어트 | 07 |
| 09 | 목록 만들기 — PostModel과 페이징 | 공지 우선 정렬, N+1 회피 | 08 |
| 10 | 상세 보기 — 조회수와 이전/다음 글 | 조회수 중복 방지, 커버링 인덱스 | 09 |
| 11 | 글쓰기와 수정 — Validation과 CSRF | 폼 재표시, 검증 규칙 분리 | 10 |
| 12 | 삭제 — 소프트 딜리트와 되돌아갈 곳 | 목록 상태 유지, 카운터 정합성 | 11 |

> **파트 2 진행 전제**: 회원 기능이 아직 없으므로 07~12편은 **비회원(게스트) 글쓰기 기준**으로 만든다. `user_id`는 NULL, `guest_name`/`guest_password`로 본인 확인. 회원과의 연결은 14편에서, 권한 판정은 16~17편에서 붙인다. 이 순서를 07편 도입부에 명시할 것.

### 파트 3 — 회원과 권한 (ep13~ep17)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 13 | 회원가입 — 비밀번호를 다루는 법 | `password_hash()`, 중복 검증 규칙 | 12 |
| 14 | 로그인과 세션 — DB 세션 핸들러 | `ci_sessions`, 로그인 유지, 로그아웃 | 13 |
| 15 | Filter로 문지기 세우기 | 인증 필터, 라우트별 적용 | 14 |
| 16 | 게시판별 권한 레벨 | read/write/comment/download 4종 판정 | 15 |
| 17 | 정책(Policy) 클래스 — 판정을 한 곳으로 | 본인 글, 매니저, 관리자 규칙 통합 | 16 |

### 파트 4 — 멀티보드의 심장 (ep18~ep21) ★ 하이라이트

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 18 | JSON 컬럼 입문 — extra에 무엇을 담나 | `posts.extra` 추가 마이그레이션, Model `$casts` | 17 |
| 19 | 필드 스키마 설계 — boards.field_schema | 7개 타입 정의, DTO 클래스 | 18 |
| 20 | 스키마가 폼을 그린다 — 동적 폼 렌더링 | 타입별 파셜 뷰, 값 복원 | 19 |
| 21 | 동적 검증과 JSON 검색 | 규칙 자동 생성, 생성 컬럼 + 인덱스 | 20 |

### 파트 5 — 게시판 기능 확장 (ep22~ep28)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 22 | 댓글 ①: 등록과 목록 | 카운터 동기화, 비회원 댓글 | 21 |
| 23 | 댓글 ②: 대댓글과 삭제 표시 | 1단계 depth, "삭제된 댓글입니다" | 22 |
| 24 | 파일 첨부 ①: 업로드 검증과 저장 전략 | MIME 검증, 웹루트 밖 저장, 임시 업로드 | 23 |
| 25 | 파일 첨부 ②: 썸네일과 안전한 다운로드 | CI4 Image, 권한 체크 다운로드 라우트 | 24 |
| 26 | 검색·정렬·카테고리 필터 | LIKE 기반 구현이 본선. FULLTEXT가 한글에서 왜 깨지는지 실측 비교 (ADR-004) | 25 |
| 27 | 공지 고정 · 비밀글 · 좋아요 | 상단 고정 정렬, 비밀글 열람 판정 | 26 |
| 28 | 스킨 분기 — list / gallery / faq | 뷰 경로 규칙, 스킨별 필요 데이터 | 27 |

### 파트 6 — 관리자, 여기서 플랫폼이 된다 (ep29~ep33)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 29 | 관리자 레이아웃과 대시보드 | 라우트 그룹, 관리자 필터, 집계 쿼리 | 28 |
| 30 | **게시판 만들기 UI** | 폼으로 게시판 생성 → 즉시 `/board/{slug}` 동작 | 29 |
| 31 | **커스텀 필드 빌더 UI** | 필드 추가/삭제/순서, JSON 저장, 기존 글 영향 | 30 |
| 32 | 게시물·댓글 관리와 일괄 처리 | 검색, 게시판 간 이동, 일괄 숨김/삭제 | 31 |
| 33 | 회원 관리와 신고 처리 | 레벨 변경, 정지, 신고 큐 | 32 |

### 파트 7 — 마무리 (ep34~ep37)

| # | 제목 | 목표 | 선행 |
|---|---|---|---|
| 34 | 통합 검색과 최신글 위젯 | 전체 게시판 교차 조회, 권한 반영 | 33 |
| 35 | 테스트로 회귀 막기 | Feature 테스트, `DatabaseTestTrait` | 34 |
| 36 | 성능 점검 — N+1, 캐시, 인덱스 | 쿼리 로그 읽기, 언제 A안이 정답인가 | 35 |
| 37 | 보안 점검과 배포 | XSS/CSRF/업로드 재점검, production 전환 | 36 |

---

## 5. 제작·발행 운영 규칙

### 5-1. Git

- 저장소: `nambak/ci4board` (신규)
- 브랜치: `main` 하나. 회차 작업 완료 시 커밋 후 `epNN` 태그
- 태그 메시지: `ep12: 삭제 - 소프트 딜리트와 되돌아갈 곳`
- 커밋 접두사: `feat:` `fix:` `chore:` `refactor:` `test:` `docs:` (이전 시리즈 규칙 승계)
- 각 회차 폴더에 `docs/epNN.md`로 그 편의 원고 원본 보관 → 블로그 발행본과 이원화 방지

### 5-2. 글 템플릿 (매 편 동일 구조)

```
1. 지난 편 요약 (2~3줄)
2. 이번 편에서 만들 것 (완성 화면 스크린샷)
3. 본문 — 파일 단위로 진행, 코드블록마다 파일 경로 주석
4. 확인하기 — 브라우저에서 무엇을 보면 성공인가
5. 이번 편 코드: github.com/nambak/ci4board/tree/ep12
6. 다음 편 예고 (1줄)
```

### 5-3. 발행 순서

1. 코드 작성 → 동작 확인 → 커밋 + 태그
2. 원고 작성 (`docs/epNN.md`)
3. 스크린샷 촬영
4. 블로그 발행

**코드가 먼저, 원고가 나중.** 원고를 먼저 쓰면 코드가 원고에 끌려간다.

### 5-4. 리스크와 대응

| 리스크 | 대응 |
|---|---|
| 18~21편(JSON) 난이도 급상승으로 이탈 | 18편을 의도적으로 쉽게, 실습 위주로. 파트 4 진입 전 "여기가 고비" 예고 |
| 31편 필드 빌더가 과도하게 복잡 | UI는 최소(추가/삭제/순서만). 드래그앤드롭은 범위 밖 |
| 중간에 스키마 변경 필요 발생 | 3장 스키마를 **지금 확정**. 변경 시 해당 편 이후 태그 전부 재작성 각오 |
| 분량 폭주 | 2-5절 "범위 밖"을 1편에서 공개 선언 |

---

## 6. 다음 액션

- [x] 3장 스키마를 실제 마이그레이션 파일로 옮겨 한 번 돌려보기 (설계 검증) → 7장
- [ ] 이 기획서 v2 검토·확정
- [ ] `nambak/ci4board` 저장소 생성 + README에 목차 게시
- [ ] 01편 원고 착수

---

## 7. 설계 검증 로그 (2026-08-11)

3장 스키마를 실제 CI4 마이그레이션 12종으로 옮겨 실행했다. 목적은 "글을 쓰기 전에 설계 오류를 찾는 것".

### 7-1. 환경

| 항목 | 값 |
|---|---|
| CodeIgniter | 4.7.4 |
| PHP | 8.4.21 (intl, mbstring, mysqli, gd) |
| DB | MariaDB 10.11.14 (`innodb_ft_min_token_size=3`) |

> MySQL 8.0으로도 같은 검증을 한 번 더 돌리는 것을 권한다. F-001·F-002는 MySQL에서 결과가 달라진다.

### 7-2. 통과한 것

| 검증 | 결과 |
|---|---|
| 마이그레이션 12종 실행 | 전부 성공 |
| 전체 롤백(`migrate:rollback -b 0`) | 성공. 생성 컬럼 → `extra` 컬럼 의존 순서도 역순으로 정상 해소 |
| FK 제약 (posts↔boards/users, comments 자기참조, attachments) | 정상 생성 |
| Model `$casts`의 `json-array` 왕복 | 배열 넣고 배열로 꺼냄. 한글 이스케이프 없음 |
| 생성 컬럼 `extra_region` 자동 채움 | INSERT만 해도 값이 채워짐. `extra`가 NULL이면 컬럼도 NULL |
| 생성 컬럼 인덱스 사용 | `EXPLAIN` 에서 `key: idx_extra_region` 확인 |
| `VARBINARY(16)` + `INET6_ATON` | IPv4/IPv6 모두 정상 |

### 7-3. 발견 사항과 조치

| ID | 발견 | 조치 |
|---|---|---|
| **F-001** | MariaDB에는 ngram 파서가 없다. `WITH PARSER ngram` → `ERROR 1128: Function 'ngram' is not defined` | ADR-004 신설. ep26 마이그레이션에 DB 분기 로직 추가 |
| **F-002** | MariaDB FULLTEXT의 한글 토큰은 조사 포함 어절 단위(`부산에서`, `세미나를`). 단어 중간 검색 0건, 2글자 단어 0건 | ep26 본선을 LIKE로 변경. FULLTEXT는 교보재로 강등 |
| **F-003** | `protected $casts` 로 쓰면 `Fatal error: Type of ...::$casts must be array`. 부모가 `protected array $casts` | ADR-003에 명시. ep18에서 함정으로 소개 |
| **F-004** | MariaDB의 `JSON`은 `LONGTEXT` + `CHECK (json_valid(...))` 별칭 | ADR-002 각주 추가. ep18에서 MySQL 네이티브 JSON과의 차이 설명 |
| **F-005** | DB를 `utf8mb4_unicode_ci`로 만들어도 Forge가 테이블을 `utf8mb4_general_ci`로 생성 | `.env`에 `database.default.DBCollat` 명시. ep02 세팅 체크리스트에 추가 |
| **F-006** | `id DESC` 인덱스는 불필요 (InnoDB 역방향 스캔) | `idx_board_list`에서 DESC 제거 |
| **F-007** | mysql CLI로 한글을 넣을 때 `--default-character-set=utf8mb4` 를 빠뜨리면 **이중 인코딩**된다. 화면에는 정상으로 보이고 `LIKE`도 걸려서 발견이 늦다 (`HEX()` 로만 잡힘) | ep06 시더를 CLI가 아닌 **CI4 Seeder로 하는 이유**로 활용. 실제 사고 사례로 쓰면 좋다 |

### 7-4. 검증에 쓴 파일

```
app/Database/Migrations/2026-08-11-000001_CreateUsersTable.php        (ep04)
                        ...000002_CreateBoardsTable.php              (ep04)
                        ...000003_CreatePostsTable.php               (ep05)
                        ...000004_CreateCommentsTable.php            (ep05)
                        ...000005_CreateAttachmentsTable.php         (ep05)
                        ...000006_CreateSessionsTable.php            (ep14)
                        ...000007_AddExtraToPosts.php                (ep18)
                        ...000008_AddFieldSchemaToBoards.php         (ep19)
                        ...000009_AddExtraGeneratedColumn.php        (ep21)
                        ...000010_AddFulltextToPosts.php             (ep26)
                        ...000011_CreatePostLikesTable.php           (ep27)
                        ...000012_CreateReportsTable.php             (ep33)
app/Models/PostModel.php
app/Commands/VerifyCasts.php     ← php spark verify:casts 로 재현 가능
```

`VerifyCasts` 커맨드는 강좌에는 넣지 않지만, 스키마를 고칠 때마다 돌려서 회귀를 잡는 용도로 저장소에 남겨둘 만하다.

---

## 참고

- [CodeIgniter 4 Server Requirements](https://codeigniter.com/user_guide/intro/requirements.html)
- [CodeIgniter Model — $casts](https://codeigniter.com/user_guide/models/model.html)
- [CodeIgniter Entities — $casts](https://codeigniter.com/user_guide/models/entities.html)
- [codeigniter4/framework — Packagist](https://packagist.org/packages/codeigniter4/framework)
- [이전 시리즈 저장소 nambak/ci4blog](https://github.com/nambak/ci4blog)
