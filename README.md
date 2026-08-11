# ci4board — CodeIgniter 4 멀티보드 플랫폼 강좌

CodeIgniter 4로 **설정만으로 게시판을 찍어내는 멀티보드 플랫폼**을 한 회차씩 만들어 가는 학습용 프로젝트입니다.

> 앞선 시리즈 [nambak/ci4blog](https://github.com/nambak/ci4blog) (30편) 완주자를 독자로 상정합니다.
> 설치·MVC 기초·기본 CRUD는 다루지 않고, **확장 가능한 구조 설계**에서 시작합니다.

- 강좌 발행처: <https://blog.unwanted.me/>
- 전체 기획서 · DB 스키마 · 회차별 목차: [CURRICULUM.md](CURRICULUM.md)

## 기술 스택

| 항목 | 값 |
|---|---|
| CodeIgniter | 4.7.x |
| PHP | 8.2 이상 (`intl`, `mbstring` 필수) |
| DB | MySQL 8.0 이상 권장 / MariaDB 10.6 이상 |
| 콜레이션 | `utf8mb4` + `utf8mb4_unicode_ci` |

## 핵심 설계

게시판마다 테이블을 만들지 않습니다. **단일 `posts` 테이블 + `boards` 설정 + JSON 확장 필드** 구조로,
관리자가 게시판을 만들고 커스텀 입력 항목을 정의하면 폼과 검색이 자동으로 따라옵니다.

자세한 결정 근거는 기획서의 ADR 절을 참고하세요.

## 회차별 코드 받기

각 회차는 `epNN` 태그로 고정되어 있습니다.

```bash
git clone https://github.com/nambak/ci4board.git
cd ci4board
git checkout ep12          # 12편 시점의 코드
```

## 로컬 실행

```bash
composer install
cp env .env                # DB 접속 정보 입력
php spark migrate --all
php spark serve
```

## 커밋 규칙

`feat:` `fix:` `chore:` `refactor:` `test:` `docs:`

한 회차의 작업을 모두 마친 뒤 한 번에 커밋하고 `epNN` 태그를 붙입니다.

## 라이선스

MIT
