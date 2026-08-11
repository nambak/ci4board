# 대표 이미지

강좌 대표 이미지와 생성 스크립트.

## 파일

| 파일 | 용도 |
|---|---|
| `cover_series.png` | 시리즈 공통 대표 이미지 (회차 표시 없음) |
| `cover_epNN.png` | 회차별 대표 이미지 |
| `orig_cover.png` | 로고 추출용 원본 (이전 ci4blog 시리즈 대표 이미지) |
| `make_cover.py` | 생성 스크립트 |
| `_variants/` | 채택하지 않은 시안 (참고용) |

## 생성

```bash
cd assets/cover

# 시리즈 공통 (회차 없음)
python make_cover.py --style a --out cover_series.png

# 회차별
python make_cover.py --style a --ep 12 --out cover_ep12.png
```

`--style` 은 `a`(채택), `b`(가로 분할형), `c`(회차 강조형) 중 선택.

## 사양

- 1352 × 590 px (원본 676 × 295 의 2배, 레티나 대응)
- 배경 `#EE5536` — 원본에서 추출한 값
- 폰트 Noto Sans CJK KR
- 로고는 `orig_cover.png` 에서 흰색 픽셀의 알파를 계산해 분리한 뒤 재배치.
  배경색에서 흰색으로 이동한 정도를 알파로 쓰기 때문에 가장자리 안티에일리어싱이 유지된다.

## 의존성

```bash
pip install Pillow
```

Noto Sans CJK KR 폰트가 시스템에 있어야 한다. macOS 에서 없다면:

```bash
brew install --cask font-noto-sans-cjk
```

폰트 경로는 `make_cover.py` 상단 `FONT_DIR` 에서 조정한다.
