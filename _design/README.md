# _design — 설계 검증본

강좌를 쓰기 전에 스키마 설계가 맞는지 확인하려고 만든 파일들입니다.
**회차별 코드가 아닙니다.** 회차별 코드는 `epNN` 태그를 보세요.

## 왜 여기에 따로 두는가

이 저장소는 회차마다 `epNN` 태그로 코드를 고정합니다.
독자가 `git checkout ep04` 했을 때 그 시점에 없어야 할 파일이 보이면
글과 코드가 어긋나므로, 미리 만들어 둔 전체 설계본은 `app/` 밖에 둡니다.

회차가 진행되면 필요한 파일만 `app/` 아래로 복사해 넣습니다.

```bash
cp _design/migrations/2026-08-11-000001_CreateUsersTable.php app/Database/Migrations/
```

## 파일

| 파일 | 회차 | 내용 |
|---|---|---|
| `migrations/...000001_CreateUsersTable.php` | ep04 | users |
| `migrations/...000002_CreateBoardsTable.php` | ep04 | boards (field_schema 제외) |
| `migrations/...000003_CreatePostsTable.php` | ep05 | posts (extra 제외) |
| `migrations/...000004_CreateCommentsTable.php` | ep05 | comments |
| `migrations/...000005_CreateAttachmentsTable.php` | ep05 | attachments |
| `migrations/...000006_CreateSessionsTable.php` | ep14 | ci_sessions |
| `migrations/...000007_AddExtraToPosts.php` | ep18 | posts.extra (JSON) |
| `migrations/...000008_AddFieldSchemaToBoards.php` | ep19 | boards.field_schema (JSON) |
| `migrations/...000009_AddExtraGeneratedColumn.php` | ep21 | 생성 컬럼 + 인덱스 |
| `migrations/...000010_AddFulltextToPosts.php` | ep26 | FULLTEXT (DB별 분기) |
| `migrations/...000011_CreatePostLikesTable.php` | ep27 | post_likes |
| `migrations/...000012_CreateReportsTable.php` | ep33 | reports |
| `PostModel.php` | ep18 | JSON 컬럼 캐스팅 |
| `VerifyCasts.php` | — | 스키마 회귀 검증 커맨드 |

## 검증 결과

CodeIgniter 4.7.4 / PHP 8.4 / MariaDB 10.11 에서
마이그레이션 12종 실행과 전체 롤백 왕복을 확인했습니다.
발견 사항 7건과 그 조치는 [CURRICULUM.md](../CURRICULUM.md) 7장에 있습니다.

## VerifyCasts 사용법

스키마를 고쳤을 때 회귀를 잡는 용도입니다. 강좌 본편에는 넣지 않습니다.

```bash
cp _design/VerifyCasts.php app/Commands/VerifyCasts.php
php spark verify:casts
```
