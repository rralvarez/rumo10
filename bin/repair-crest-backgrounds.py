#!/usr/bin/env python3
"""Correção conservadora de fundo branco em escudos, sem alterar o desenho.

Identifica somente áreas BRANCAS OPACAS ligadas à borda do canvas.
Escudos com fundo transparente ou casos duvidosos permanecem inalterados.

Uso:
 python repair_crest_backgrounds.py --root PATH --kind rumo10 --output /tmp/logos-audit
 python repair_crest_backgrounds.py --root PATH --kind rumohub --output /tmp/logos-audit --write

No --write: PNG de alta confiança é corrigido no próprio caminho, com versão
anterior recuperável pelo Git; JPG/GIF estáticos geram PNG novo e um plano
explícito de migração de caminhos. O SQL gerado NUNCA é executado.
"""
import argparse
import csv
import hashlib
import json
import os
from collections import Counter, deque
from pathlib import Path
from PIL import Image, ImageOps

WHITE_MIN = 243
WHITE_SPREAD = 17
EXT = {'.png', '.jpg', '.jpeg', '.gif', '.webp'}


def is_white(px):
    r, g, b, a = px
    return a >= 250 and min(r, g, b) >= WHITE_MIN and (max(r, g, b) - min(r, g, b)) <= WHITE_SPREAD


def outer_statistics(im):
    w, h = im.size
    pixels = im.load()
    step_x = max(1, w // 300)
    step_y = max(1, h // 300)
    # Perimetro e quatro quadrados de canto de 8% da imagem.
    outer = [pixels[x, y] for x in range(0, w, step_x) for y in (0, h - 1)]
    outer += [pixels[x, y] for y in range(0, h, step_y) for x in (0, w - 1)]
    corner = max(3, int(min(w, h) * .07))
    corners = [
        (0, 0), (w - corner, 0), (0, h - corner), (w - corner, h - corner)
    ]
    ratios = []
    for sx, sy in corners:
        samples = [pixels[x, y] for y in range(sy, sy + corner, max(1, corner // 12))
                   for x in range(sx, sx + corner, max(1, corner // 12))]
        ratios.append(sum(map(is_white, samples)) / max(1, len(samples)))
    return {
        'white_edge_ratio': sum(map(is_white, outer)) / max(1, len(outer)),
        'transparent_edge_ratio': sum(px[3] < 80 for px in outer) / max(1, len(outer)),
        'white_corners_min': min(ratios),
        'white_corners_mean': sum(ratios) / len(ratios),
    }


def connected_white_mask(im):
    """Flood fill estritamente em branco externo; preserva branco interno."""
    w, h = im.size
    pix = im.load()
    reached = bytearray(w * h)
    stack = deque()

    def seed(x, y):
        i = y * w + x
        if not reached[i] and is_white(pix[x, y]):
            reached[i] = 1
            stack.append((x, y))

    for x in range(w):
        seed(x, 0)
        seed(x, h - 1)
    for y in range(h):
        seed(0, y)
        seed(w - 1, y)

    while stack:
        x, y = stack.popleft()
        if x and not reached[y * w + x - 1]:
            seed(x - 1, y)
        if x + 1 < w and not reached[y * w + x + 1]:
            seed(x + 1, y)
        if y and not reached[(y - 1) * w + x]:
            seed(x, y - 1)
        if y + 1 < h and not reached[(y + 1) * w + x]:
            seed(x, y + 1)
    return reached, reached.count(1)


def audit_and_fix(path, output_dir, write):
    base = {'arquivo': path.name, 'status': '', 'caminho_anterior': '',
            'novo_arquivo': '', 'mudou_pixels': 0, 'erro': ''}
    try:
        with Image.open(path) as src:
            frames = getattr(src, 'n_frames', 1)
            if frames > 1:
                return base | {'status': 'GIF_ANIMADO_REVISAR', 'frames': frames}
            image = ImageOps.exif_transpose(src).convert('RGBA')
        w, h = image.size
        if min(w, h) < 24 or w * h > 15_000_000:
            return base | {'status': 'DIMENSAO_INADEQUADA', 'largura': w, 'altura': h}
        stats = outer_statistics(image)
        common = base | stats | {'largura': w, 'altura': h, 'frames': frames}
        if stats['transparent_edge_ratio'] > .04:
            return common | {'status': 'JA_TEM_TRANSPARENCIA'}
        if (stats['white_edge_ratio'] < .94 or stats['white_corners_min'] < .90):
            return common | {'status': 'SEM_FUNDO_BRANCO_SEGURO'}
        mask, changed = connected_white_mask(image)
        fraction = changed / (w * h)
        common['removidos_px'] = changed
        common['removidos_pct'] = round(fraction * 100, 2)
        if fraction < .015 or fraction > .86:
            return common | {'status': 'REVISAR_SILHUETA_OU_FUNDO'}
        if not write:
            return common | {'status': 'CORRIGIVEL_SEM_ALTERACAO'}
        p = image.load()
        for row in range(h):
            offset = row * w
            for col in range(w):
                if mask[offset + col]:
                    r, g, b, a = p[col, row]
                    p[col, row] = (r, g, b, 0)
        if path.suffix.lower() == '.png':
            target = path
        else:
            target = path.with_suffix('.png')
            if target.exists():
                return common | {'status': 'CAMINHO_PNG_JA_EXISTE_REVISAR'}
        tmp = output_dir / ('stage_' + hashlib.sha256(str(path).encode()).hexdigest()[:16] + '.png')
        image.save(tmp, format='PNG', optimize=True)
        # Verifica o novo PNG antes de substituir o arquivo original.
        with Image.open(tmp) as verify:
            verify.verify()
        os.replace(tmp, target)
        return common | {'status': 'CORRIGIDO_PNG_MESMO_CAMINHO' if target == path else 'CORRIGIDO_CONVERTIDO_PNG',
                         'caminho_anterior': str(path), 'novo_arquivo': str(target), 'mudou_pixels': changed}
    except Exception as exc:
        return base | {'status': 'ERRO_PROCESSAMENTO', 'erro': f'{type(exc).__name__}: {exc}'}


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--root', required=True, type=Path)
    p.add_argument('--kind', required=True, choices=['rumo10', 'rumohub'])
    p.add_argument('--output', default='audit-escudos', type=Path)
    p.add_argument('--write', action='store_true')
    args = p.parse_args()
    root, out = args.root.resolve(), args.output.resolve()
    assets = root / ('public/assets/clubs' if args.kind == 'rumo10' else 'storage/media/clubs')
    if not assets.is_dir():
        p.error(f'Pasta de escudos inexistente: {assets}')
    out.mkdir(parents=True, exist_ok=True)
    images = sorted([f for f in assets.iterdir() if f.is_file() and f.suffix.lower() in EXT])
    rows = [audit_and_fix(f, out, args.write) for f in images]
    fieldnames = ['arquivo', 'status', 'largura', 'altura', 'frames', 'white_edge_ratio',
                  'transparent_edge_ratio', 'white_corners_min', 'white_corners_mean',
                  'removidos_px', 'removidos_pct', 'caminho_anterior', 'novo_arquivo', 'mudou_pixels', 'erro']
    with (out / 'escudos_analise.csv').open('w', encoding='utf-8-sig', newline='') as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames, delimiter=';', extrasaction='ignore')
        writer.writeheader()
        writer.writerows(rows)
    changed = [r for r in rows if r['status'].startswith('CORRIGIDO')]
    conversions = [r for r in changed if r['status'] == 'CORRIGIDO_CONVERTIDO_PNG']
    if args.write and conversions:
        mapping = {Path(r['caminho_anterior']).name: Path(r['novo_arquivo']).name for r in conversions}
        # Alteracao de caminhos em JSONs somente quando referencia exata antiga existe.
        catalogs = ([root / 'database/club-badges.json'] if args.kind == 'rumo10'
                    else [root / 'database/ea-markets-2026.json'])
        for catalog in catalogs:
            if catalog.exists():
                content = catalog.read_text(encoding='utf-8-sig')
                updated = content
                for old, new in mapping.items():
                    updated = updated.replace('/assets/clubs/' + old, '/assets/clubs/' + new)
                if content != updated:
                    catalog.write_text(updated, encoding='utf-8')
        if args.kind == 'rumohub':
            # Gerar SQL idempotente, NUNCA executar sem a etapa formal de aprovacao.
            sql = ['-- Revisar antes de aplicar. Nenhuma instrucao executada automaticamente.',
                   '-- Requer backup do banco e validacao de identidade do clube.',
                   'START TRANSACTION;']
            for old, new in sorted(mapping.items()):
                sql.append("UPDATE rh_clubes SET escudo_caminho='/assets/clubs/{}' "
                           "WHERE escudo_caminho='/assets/clubs/{}';".format(new, old))
            sql.append('COMMIT;')
            (out / 'SQL_PENDENTE_DE_APROVACAO.sql').write_text('\n'.join(sql) + '\n', encoding='utf-8')
    counts = Counter(r['status'] for r in rows)
    summary = {
        'repositorio': args.kind,
        'modo': 'alteracao_somente_em_branch_de_revisao' if args.write else 'somente_leitura',
        'arquivos_lidos': len(images),
        'contagens': dict(sorted(counts.items())),
        'arquivos_modificados': len(changed),
        'arquivos_convertidos_de_gif_ou_jpg': len(conversions),
        'limite': 'Nao diagnostica referencia escudo_caminho no banco de producao. GIF animado e fundo ambiguo exigem revisao.',
    }
    (out / 'resumo.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps(summary, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()

