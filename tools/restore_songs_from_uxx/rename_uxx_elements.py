#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
EMERGENZA: rinomina i file .uxx dal disco Elements nei nomi originali .mp3

Path di default:
  Input : /Volumes/elements/EMERGENZA/Songs/uxx
  (rinomina IN LOCO: hash.uxx -> 32211.mp3)

Formula player YourRadio:
  md5("32211") + ".uxx"  ==  nome file sul player
"""

from __future__ import print_function

import hashlib
import os
import sys

INPUT_DIR = "/Volumes/Elements/EMERGENZA/Songs/uxx"
# Se la cartella uxx non esiste, usa direttamente Songs (dove a volte sono già i .uxx)
INPUT_DIR_FALLBACK = "/Volumes/Elements/EMERGENZA/Songs"
# Range ID song da provare (allarga se serve)
ID_MIN = 1
ID_MAX = 80000


def md5_hex(text):
    return hashlib.md5(str(text).encode("utf-8")).hexdigest()


def build_map(min_id, max_id):
    mapping = {}
    for i in range(min_id, max_id + 1):
        mapping[md5_hex(i)] = str(i)
    return mapping


def main():
    input_dir = INPUT_DIR
    if len(sys.argv) > 1:
        input_dir = sys.argv[1]
    elif not os.path.isdir(input_dir) and os.path.isdir(INPUT_DIR_FALLBACK):
        input_dir = INPUT_DIR_FALLBACK

    if not os.path.isdir(input_dir):
        print("ERRORE: cartella non trovata:")
        print("  %s" % INPUT_DIR)
        print("  (né fallback %s)" % INPUT_DIR_FALLBACK)
        print("")
        print("Monta il disco 'Elements' e verifica il path.")
        return 1

    print("Cartella input: %s" % input_dir)
    print("Costruisco mappa md5 per ID %s..%s ..." % (ID_MIN, ID_MAX))
    mapping = build_map(ID_MIN, ID_MAX)
    print("Mappa pronta: %s voci" % len(mapping))
    print("Avvio rinomina in loco (.uxx -> .mp3)...")
    print("")

    found = 0
    ok = 0
    skip = 0
    missing = 0
    errors = 0
    unmatched = []

    for dirpath, _dirnames, filenames in os.walk(input_dir):
        for name in filenames:
            low = name.lower()
            if not low.endswith(".uxx"):
                continue

            found += 1
            src = os.path.join(dirpath, name)
            h = name.rsplit(".", 1)[0].lower().strip()
            sg_file = mapping.get(h)

            if not sg_file:
                missing += 1
                unmatched.append(h)
                print("NO MATCH  %s" % name)
                continue

            dest_name = sg_file + ".mp3"
            dest = os.path.join(dirpath, dest_name)

            if os.path.abspath(src) == os.path.abspath(dest):
                skip += 1
                continue

            if os.path.exists(dest):
                # già presente: non sovrascrivere
                skip += 1
                print("SKIP (esiste già)  %s" % dest_name)
                continue

            try:
                os.rename(src, dest)
                ok += 1
                print("OK  %s  ->  %s" % (name, dest_name))
            except Exception as e:
                errors += 1
                print("ERRORE  %s  ->  %s  (%s)" % (name, dest_name, e))

    print("")
    print("=== RIEPILOGO ===")
    print("File .uxx trovati : %s" % found)
    print("Rinominati OK     : %s" % ok)
    print("Skip              : %s" % skip)
    print("Senza match       : %s" % missing)
    print("Errori            : %s" % errors)

    if unmatched:
        miss_path = os.path.join(input_dir, "_unmatched_hashes.txt")
        with open(miss_path, "w", encoding="utf-8") as f:
            for h in sorted(set(unmatched)):
                f.write(h + "\n")
        print("Hash senza match  : %s" % miss_path)

    return 0 if errors == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
