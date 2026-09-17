#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Ripristina i nomi originali delle song dai file player (.uxx).

Sui player YourRadio i file sono salvati così:
  {md5(sg_file)}.uxx
es. 32211.mp3  ->  md5("32211").uxx  =  761b59a8e028e110dec4be2114ee567d.uxx

Uso tipico:
  1) Copia dal player la cartella song (con tutti i .uxx) su questo Mac
  2) Lancia:

     python3 uxx_to_mp3.py --input /path/con/uxx --output /path/ripristino

Opzioni:
  --lookup HASH          stampa solo il nome originale di un hash
  --map-from-api         costruisce la mappa scaricando le song dall'API
  --map-range MIN MAX    costruisce la mappa calcolando md5(i) per i in [MIN..MAX]
  --dry-run              non copia, mostra solo il mapping
"""

from __future__ import print_function

import argparse
import hashlib
import json
import os
import shutil
import sys
import urllib.request


DEFAULT_API = "https://yourradio.org/api"


def md5_hex(text):
    return hashlib.md5(str(text).encode("utf-8")).hexdigest()


def build_map_from_range(min_id, max_id):
    """Mappa hash -> sg_file per tutti gli ID numerici nel range."""
    mapping = {}
    for i in range(int(min_id), int(max_id) + 1):
        mapping[md5_hex(i)] = str(i)
        # anche variante stringa con zeri a sinistra non serve: sui player è senza padding
    return mapping


def build_map_from_list(ids):
    mapping = {}
    for raw in ids:
        sid = str(raw).strip()
        if not sid:
            continue
        # accetta "32211" o "32211.mp3"
        if sid.lower().endswith(".mp3"):
            sid = sid[:-4]
        mapping[md5_hex(sid)] = sid
    return mapping


def fetch_json(url):
    req = urllib.request.Request(url, headers={"User-Agent": "uxx-restore/1.0"})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.loads(resp.read().decode("utf-8"))


def build_map_from_api(api_base):
    """
    Prova a costruire la mappa da API:
    1) /api/songs/maxids  (se esiste) + range 1..max
    2) fallback: scarica lista songs (paginata se possibile)
    """
    mapping = {}
    api_base = api_base.rstrip("/")

    # 1) max ids
    try:
        data = fetch_json(api_base + "/songs/maxids")
        if data.get("success") and data.get("data"):
            max_file = int(data["data"].get("max_sg_file") or data["data"].get("next_sg_file") or 0)
            if max_file > 0:
                print("API max_sg_file = %s → genero mappa 1..%s" % (max_file, max_file))
                return build_map_from_range(1, max_file)
    except Exception as e:
        print("AVVISO: /songs/maxids non disponibile (%s)" % e)

    # 2) lista songs (può essere grande)
    try:
        # prova senza filtri
        data = fetch_json(api_base + "/songs?attivo=*")
        rows = data.get("data") if isinstance(data, dict) else None
        if not rows and isinstance(data, dict) and data.get("success"):
            rows = data.get("data")
        if isinstance(rows, list) and rows:
            ids = []
            for r in rows:
                if isinstance(r, dict):
                    if r.get("sg_file") is not None:
                        ids.append(r["sg_file"])
                    elif r.get("id") is not None and r.get("file") is not None:
                        ids.append(r["file"])
                    elif r.get("id") is not None:
                        # spesso id != sg_file, ma alcuni dump espongono solo id
                        pass
            if ids:
                print("API songs: %s file ids" % len(ids))
                return build_map_from_list(ids)
    except Exception as e:
        print("AVVISO: lista songs API fallita (%s)" % e)

    raise RuntimeError(
        "Impossibile costruire la mappa dall'API. "
        "Usa --map-range 1 50000 oppure --ids-file elenco.txt"
    )


def load_ids_file(path):
    ids = []
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            # CSV grezzo: prende la prima colonna
            ids.append(line.split(",")[0].strip().strip('"'))
    return build_map_from_list(ids)


def iter_uxx_files(root):
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            low = name.lower()
            if low.endswith(".uxx") or low.endswith(".tmp"):
                yield os.path.join(dirpath, name)


def normalize_hash_from_filename(name):
    base = os.path.basename(name)
    if "." in base:
        base = base.rsplit(".", 1)[0]
    return base.lower().strip()


def restore(input_dir, output_dir, mapping, dry_run=False, copy_mode=True):
    os.makedirs(output_dir, exist_ok=True)

    found = 0
    restored = 0
    already_done = 0
    missing = 0
    missing_hashes = []

    for src in iter_uxx_files(input_dir):
        found += 1
        h = normalize_hash_from_filename(src)
        sg_file = mapping.get(h)
        if not sg_file:
            missing += 1
            missing_hashes.append(h)
            continue

        dest = os.path.join(output_dir, sg_file + ".mp3")
        if dry_run:
            if os.path.exists(dest) and os.path.getsize(dest) == os.path.getsize(src):
                already_done += 1
                print("GIA FATTO  %s  (già in output)" % (sg_file + ".mp3"))
            else:
                restored += 1
                print("OK  %s  ->  %s" % (os.path.basename(src), sg_file + ".mp3"))
            continue

        if os.path.exists(dest):
            # se esiste già e stessa size, skip (non ricopia/sposta)
            if os.path.getsize(dest) == os.path.getsize(src):
                already_done += 1
                print("GIA FATTO  %s  <-  %s" % (sg_file + ".mp3", os.path.basename(src)))
                continue
            # altrimenti sovrascrivi
        if copy_mode:
            shutil.copy2(src, dest)
        else:
            shutil.move(src, dest)
        restored += 1
        print("RIPRISTINATO  %s  <-  %s" % (sg_file + ".mp3", src))

    print("")
    print("=== RIEPILOGO ===")
    print("File .uxx trovati : %s" % found)
    print("Ripristinati      : %s" % restored)
    print("Già fatti         : %s" % already_done)
    print("Senza match       : %s" % missing)
    if missing_hashes:
        miss_path = os.path.join(output_dir, "_unmatched_hashes.txt")
        with open(miss_path, "w", encoding="utf-8") as f:
            for h in sorted(set(missing_hashes)):
                f.write(h + "\n")
        print("Hash senza match salvati in: %s" % miss_path)
    return restored, already_done, missing


def lookup(hash_value, mapping):
    h = normalize_hash_from_filename(hash_value)
    sg = mapping.get(h)
    if sg:
        print("%s  =>  %s.mp3" % (h, sg))
        return 0
    print("%s  =>  NON TROVATO nella mappa" % h)
    return 1


def main():
    p = argparse.ArgumentParser(description="Ripristina nomi originali da file .uxx (md5(sg_file).uxx)")
    p.add_argument("--input", "-i", help="Cartella con i file .uxx (anche ricorsiva)")
    p.add_argument("--output", "-o", help="Cartella di destinazione dei .mp3 ripristinati")
    p.add_argument("--lookup", help="Hash da risolvere (con o senza .uxx)")
    p.add_argument("--map-from-api", action="store_true", help="Costruisci mappa dall'API YourRadio")
    p.add_argument("--api", default=DEFAULT_API, help="Base API (default: %s)" % DEFAULT_API)
    p.add_argument("--map-range", nargs=2, type=int, metavar=("MIN", "MAX"),
                   help="Costruisci mappa md5(i) per i=MIN..MAX (consigliato: 1 60000)")
    p.add_argument("--ids-file", help="File testo/CSV con elenco sg_file (uno per riga)")
    p.add_argument("--dry-run", action="store_true", help="Non copia, mostra solo i match")
    p.add_argument("--move", action="store_true", help="Sposta invece di copiare")
    args = p.parse_args()

    # Costruzione mappa
    if args.ids_file:
        mapping = load_ids_file(args.ids_file)
        print("Mappa da file: %s voci" % len(mapping))
    elif args.map_range:
        mapping = build_map_from_range(args.map_range[0], args.map_range[1])
        print("Mappa da range %s..%s: %s voci" % (args.map_range[0], args.map_range[1], len(mapping)))
    elif args.map_from_api:
        mapping = build_map_from_api(args.api)
        print("Mappa da API: %s voci" % len(mapping))
    else:
        # default sicuro: range ampio
        mapping = build_map_from_range(1, 80000)
        print("Mappa default 1..80000: %s voci" % len(mapping))

    if args.lookup:
        return lookup(args.lookup, mapping)

    if not args.input or not args.output:
        p.error("Per il ripristino servono --input e --output (oppure usa --lookup HASH)")

    if not os.path.isdir(args.input):
        print("ERRORE: input non è una cartella: %s" % args.input, file=sys.stderr)
        return 2

    restore(
        input_dir=args.input,
        output_dir=args.output,
        mapping=mapping,
        dry_run=args.dry_run,
        copy_mode=not args.move,
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
