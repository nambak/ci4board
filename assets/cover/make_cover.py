#!/usr/bin/env python3
"""
ci4board 강좌 대표 이미지 생성기

원본 CodeIgniter 로고 이미지에서 흰색 로고만 알파 추출해서 재배치하고,
회차 번호와 부제를 얹는다.

사용:
    python make_cover.py --style a --ep 1 --subtitle "왜 게시판마다 테이블을 만들지 않는가"
    python make_cover.py --style b --ep 12 --subtitle "삭제와 소프트 딜리트"
"""

import argparse
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

BG = (238, 85, 54)
WHITE = (255, 255, 255)

SCALE = 2  # 레티나 대응
W, H = 676 * SCALE, 295 * SCALE

FONT_DIR = Path('/usr/share/fonts/opentype/noto')
KR_BOLD = (str(FONT_DIR / 'NotoSansCJK-Bold.ttc'), 1)
KR_BLACK = (str(FONT_DIR / 'NotoSansCJK-Black.ttc'), 1)
KR_REG = (str(FONT_DIR / 'NotoSansCJK-Regular.ttc'), 1)
KR_MED = (str(FONT_DIR / 'NotoSansCJK-Medium.ttc'), 1)


def font(spec, size):
    path, idx = spec
    return ImageFont.truetype(path, size, index=idx)


def extract_logo(src_path):
    """오렌지 배경 위 흰 로고를 알파 채널로 분리한다.

    배경색에서 흰색으로 얼마나 이동했는지를 채널별로 계산해 알파로 쓴다.
    이렇게 하면 안티에일리어싱된 가장자리가 그대로 살아난다.
    """
    im = Image.open(src_path).convert('RGB')
    w, h = im.size
    px = im.load()

    logo = Image.new('RGBA', (w, h), (255, 255, 255, 0))
    lp = logo.load()

    denom = [255 - c for c in BG]
    for y in range(h):
        for x in range(w):
            r, g, b = px[x, y]
            a = sum((v - c) / d for v, c, d in zip((r, g, b), BG, denom)) / 3
            a = max(0.0, min(1.0, a))
            if a > 0:
                lp[x, y] = (255, 255, 255, int(round(a * 255)))

    return logo.crop(logo.getbbox())


def paste_logo(canvas, logo, target_h, center_x, top_y):
    ratio = target_h / logo.height
    lw = int(round(logo.width * ratio))
    resized = logo.resize((lw, target_h), Image.LANCZOS)
    canvas.alpha_composite(resized, (int(center_x - lw / 2), int(top_y)))
    return lw


def style_a(logo, ep, subtitle):
    """원본 존중형 — 로고 아래에 구분선과 시리즈명."""
    canvas = Image.new('RGBA', (W, H), BG + (255,))
    d = ImageDraw.Draw(canvas)

    paste_logo(canvas, logo, int(150 * SCALE), W / 2, 22 * SCALE)

    line_y = 196 * SCALE
    d.line([(W / 2 - 150 * SCALE, line_y), (W / 2 + 150 * SCALE, line_y)],
           fill=WHITE + (110,), width=SCALE)

    f = font(KR_BOLD, int(28 * SCALE))
    text = '멀티보드 게시판 만들기'
    d.text((W / 2, 218 * SCALE), text, font=f, fill=WHITE, anchor='mt')

    if ep:
        fe = font(KR_BOLD, int(19 * SCALE))
        d.text((W - 26 * SCALE, 20 * SCALE), f'#{ep:02d}', font=fe,
               fill=WHITE + (200,), anchor='rt')

    return canvas


def style_b(logo, ep, subtitle):
    """가로 분할형 — 왼쪽 로고, 오른쪽 텍스트. 목록에서 읽기 좋다."""
    canvas = Image.new('RGBA', (W, H), BG + (255,))
    d = ImageDraw.Draw(canvas)

    paste_logo(canvas, logo, int(150 * SCALE), 175 * SCALE, 72 * SCALE)

    div_x = 320 * SCALE
    d.line([(div_x, 78 * SCALE), (div_x, 217 * SCALE)],
           fill=WHITE + (95,), width=SCALE)

    tx = 360 * SCALE
    d.text((tx, 98 * SCALE), 'CodeIgniter 4',
           font=font(KR_MED, int(21 * SCALE)), fill=WHITE + (205,), anchor='lt')
    d.text((tx, 130 * SCALE), '멀티보드',
           font=font(KR_BLACK, int(38 * SCALE)), fill=WHITE, anchor='lt')
    d.text((tx, 176 * SCALE), '게시판 만들기',
           font=font(KR_BLACK, int(38 * SCALE)), fill=WHITE, anchor='lt')

    if ep:
        d.text((W - 30 * SCALE, 30 * SCALE), f'#{ep:02d}',
               font=font(KR_BOLD, int(22 * SCALE)), fill=WHITE + (190,), anchor='rt')

    return canvas


def style_c(logo, ep, subtitle):
    """회차 강조형 — 큰 번호와 부제. 목록 페이지에서 회차 구분이 확실하다."""
    canvas = Image.new('RGBA', (W, H), BG + (255,))
    d = ImageDraw.Draw(canvas)

    paste_logo(canvas, logo, int(112 * SCALE), 108 * SCALE, 44 * SCALE)

    d.text((30 * SCALE, 190 * SCALE), 'CodeIgniter 4  멀티보드 게시판 만들기',
           font=font(KR_MED, int(19 * SCALE)), fill=WHITE + (200,), anchor='lt')

    if ep:
        fe = font(KR_BLACK, int(112 * SCALE))
        d.text((W - 34 * SCALE, 26 * SCALE), f'{ep:02d}', font=fe,
               fill=WHITE + (55,), anchor='rt')

    if subtitle:
        d.line([(30 * SCALE, 232 * SCALE), (W - 30 * SCALE, 232 * SCALE)],
               fill=WHITE + (85,), width=SCALE)
        d.text((30 * SCALE, 246 * SCALE), subtitle,
               font=font(KR_BOLD, int(26 * SCALE)), fill=WHITE, anchor='lt')

    return canvas


STYLES = {'a': style_a, 'b': style_b, 'c': style_c}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--source', default='orig_cover.png', help='원본 CodeIgniter 로고 이미지')
    ap.add_argument('--style', default='b', choices=sorted(STYLES))
    ap.add_argument('--ep', type=int, default=0, help='회차 번호 (0이면 표시 안 함)')
    ap.add_argument('--subtitle', default='', help='부제 (style c에서만 사용)')
    ap.add_argument('--out', default='')
    args = ap.parse_args()

    logo = extract_logo(args.source)
    canvas = STYLES[args.style](logo, args.ep, args.subtitle)

    out = args.out or (f'cover_{args.style}_ep{args.ep:02d}.png' if args.ep
                       else f'cover_{args.style}.png')
    canvas.convert('RGB').save(out, 'PNG', optimize=True)
    print(f'{out}  ({canvas.width}x{canvas.height})')


if __name__ == '__main__':
    main()
