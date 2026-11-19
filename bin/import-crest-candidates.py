#!/usr/bin/env python3
"""Recupera apenas escudos com fonte direta documentada, para branch de revisão.

Exige CSV de fontes curadas, valida assinatura da imagem e preserva links de
proveniência. Não aplica SQL no banco, não faz deploy e não resolve identidades
por semelhança de nomes. Erros de download NÃO geram escudos fictícios.
"""
import argparse
import csv
import hashlib
import importlib.util
import io
import json
import re
import urllib.request
from datetime import date
from pathlib import Path
from PIL import Image, ImageOps, UnidentifiedImageError

EXCLUIR_NOTAS = ('EXIGE_', 'CONFIRMAR_', 'APENAS_', 'FONTE_TERCEIRO', 'FOTO_PERFIL')
MAX_DOWNLOAD = 4 * 1024 * 1024


def import_reviewed_source(url):
    if not url.lower().startswith('https://'):
        raise ValueError('Somente URLs HTTPS')
    req = urllib.request.Request(url, headers={
        'User-Agent': 'Mozilla/5.0 (compatible; RumoHubCrestAudit/1.0)',
        'Accept': 'image/avif,image/webp,image/png,image/jpeg,image/gif,*/*;q=0.8',
    })
    with urllib.request.urlopen(req, timeout=18) as res:
        content = res.read(MAX_DOWNLOAD + 1)
        if len(content) > MAX_DOWNLOAD:
            raise ValueError('Imagem excede 4 MiB')
    if len(content) < 500:
        raise ValueError('Imagem muito pequena para auditoria')
    with Image.open(io.BytesIO(content)) as img:
        if getattr(img, 'n_frames', 1) != 1:
            raise ValueError('Imagem animada: requer tratamento especifico')
        img = ImageOps.exif_transpose(img)
        if img.width < 60 or img.height < 60 or img.width * img.height > 8_000_000:
            raise ValueError('Dimensoes fora da politica segura')
        rgba = img.convert('RGBA')
        rgba.load()
    return content, rgba


def append_json_entries_without_reformat(path, entries):
    if not entries:
        return
    raw = path.read_bytes().decode('utf-8-sig')
    data = json.loads(raw)
    pending = {k: v for k, v in entries.items() if k not in data}
    if not pending:
        return
    lb = '\r\n' if '\r\n' in raw else '\n'
    trimmed = raw.rstrip()
    if not trimmed.endswith('}') or not isinstance(data, dict):
        raise ValueError('Formato inesperado de catalogo de escudos')
    body = trimmed[:-1].rstrip()
    parts = []
    for club, entry in sorted(pending.items()):
        parts.append('  ' + json.dumps(club, ensure_ascii=False) + ': ' + json.dumps(entry, ensure_ascii=False))
    if data:
        body += ','
    body += lb + (',' + lb).join(parts) + lb + '}' + lb
    assert len(json.loads(body)) == len(data) + len(pending)
    path.write_bytes(body.encode('utf-8'))


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--root', type=Path, required=True)
    p.add_argument('--kind', choices=['rumo10','rumohub'], required=True)
    p.add_argument('--sources', type=Path, required=True)
    p.add_argument('--output', type=Path, default=Path('escudos_fontes_resultado'))
    p.add_argument('--write', action='store_true')
    a = p.parse_args()
    root = a.root.resolve()
    output = a.output.resolve(); output.mkdir(parents=True, exist_ok=True)
    assets = root / ('public/assets/clubs' if a.kind == 'rumo10' else 'storage/media/clubs')
    assert assets.is_dir(), assets
    records = list(csv.DictReader(a.sources.open(encoding='utf-8-sig', newline=''), delimiter=';'))
    if len(records) != 60:
        p.error('Manifesto incompleto: esperado inventario dos 60 clubes')
    spec = importlib.util.spec_from_file_location('crest_repair',root/'bin/repair-crest-backgrounds.py')
    module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)
    report=[]; mapped={}
    for r in records:
        name = r['clube']
        if a.kind == 'rumo10' and r['grupo'] != 'Rumo10':
            continue
        url = r['url_imagem_ou_pagina']
        base = {'clube':name,'origem':url,'imagem':r['nome_proposto_png'],'estado':''}
        if r.get('tipo_referencia') != 'direta' or not url:
            report.append(base | {'estado':'FONTE_DIRETA_NAO_LOCALIZADA'}); continue
        if any(r.get('controle_identidade','').startswith(x) for x in EXCLUIR_NOTAS):
            report.append(base | {'estado':'IDENTIDADE_OU_VARIANTE_REQUER_REVISAO'}); continue
        filename = r['nome_proposto_png']
        if not re.fullmatch(r'club-rec-[a-f0-9]{16}\.png',filename):
            report.append(base | {'estado':'NOME_DE_ARQUIVO_INVALIDO'}); continue
        target = assets / filename
        if target.exists():
            report.append(base | {'estado':'ARQUIVO_JA_EXISTENTE_SEM_ALTERACAO'}); continue
        try:
            raw,img = import_reviewed_source(url)
            if a.write:
                img.save(target, 'PNG', optimize=True)
                correction = module.audit_and_fix(target, output, True)
                with Image.open(target) as check:
                    check.verify()
                mapped[name]={
                    'path':'/assets/clubs/'+filename,
                    'source':r['url_confirmacao'] or url,
                    'download_url':url,
                    'retrieved':date.today().isoformat(),
                    'audit_status':correction.get('status',''),
                    'sha256_source':hashlib.sha256(raw).hexdigest(),
                }
            report.append(base | {'estado':'IMAGEM_PREPARADA_PARA_REVISAO' if a.write else 'IMAGEM_VALIDADA_SEM_ESCRITA',
                                  'bytes':len(raw),'largura':img.width,'altura':img.height,
                                  'hash_fonte':hashlib.sha256(raw).hexdigest()})
        except Exception as ex:
            report.append(base | {'estado':'FALHA_FONTE_SEM_INVENTAR_IMAGEM','detalhe':f'{type(ex).__name__}: {ex}'})
    if a.write and mapped:
        if a.kind=='rumo10':
            append_json_entries_without_reformat(root/'database/club-badges.json', {
                n:{'path':m['path'],'source':m['source'],'download_url':m['download_url'],'retrieved':m['retrieved']}
                for n,m in mapped.items()
            })
        # Em ambos: relatorio novo de procedencia, nunca alterar IDs nem tabelas.
        evidence=root/'database/escudos_resgatados_20261119.json'
        evidence.write_text(json.dumps({'kind':a.kind,'clubs':mapped},ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
        if a.kind=='rumohub':
            sql=['-- PROPOSTA, NAO EXECUTAR AUTOMATICAMENTE. Verificar backup e versoes antes da importacao.',
                 '-- Apenas preenche badges vazios ou troca caminho de arquivo ja convertido.',
                 'START TRANSACTION;']
            for n,m in sorted(mapped.items()):
                name=n.replace("'","''")
                sql.append("UPDATE rh_clubes SET escudo_caminho='{}' WHERE codigo='{}' AND (escudo_caminho IS NULL OR escudo_caminho='');".format(m['path'],name))
            # PNGs gerados a partir de JPG/GIF nos quais o caminho historico nao muda sozinho.
            media=assets
            for old in sorted(list(media.glob('*.jpg'))+list(media.glob('*.jpeg'))+list(media.glob('*.gif'))):
                new=old.with_suffix('.png')
                if new.is_file():
                    oldname=old.name.replace("'","''"); newname=new.name.replace("'","''")
                    sql.append("UPDATE rh_clubes SET escudo_caminho='/assets/clubs/{}' WHERE escudo_caminho='/assets/clubs/{}';".format(newname,oldname))
            sql.append('COMMIT;')
            (root/'database/production/escudos_20261119_PROPOSTA_NAO_EXECUTAR.sql').write_text('\n'.join(sql)+'\n',encoding='utf-8')
    fields=['clube','origem','imagem','estado','detalhe','bytes','largura','altura','hash_fonte']
    with (output/'relatorio_importacao.csv').open('w',encoding='utf-8-sig',newline='') as f:
        writer=csv.DictWriter(f,fieldnames=fields,delimiter=';',extrasaction='ignore');writer.writeheader();writer.writerows(report)
    summary={'universo_revisado':len(report),'candidatos_processados':sum(r['estado']=='IMAGEM_PREPARADA_PARA_REVISAO' for r in report),'falhas_download':sum(r['estado']=='FALHA_FONTE_SEM_INVENTAR_IMAGEM' for r in report),'pendencias_restantes':sum(r['estado']!='IMAGEM_PREPARADA_PARA_REVISAO' for r in report)}
    (output/'resumo_importacao.json').write_text(json.dumps(summary,indent=2,ensure_ascii=False),encoding='utf-8')
    print(json.dumps(summary,ensure_ascii=False,indent=2))


if __name__=='__main__':
    main()

