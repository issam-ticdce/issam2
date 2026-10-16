"""Ligne de commande.

    python -m style_video analyser reference.mp4 -o sortie/ [--mode similaire] [--ia]
    python -m style_video comparer reference.mp4 mon_montage.mp4
"""

import argparse
import json
import sys

from .pipeline import analyze_video, compare, write_outputs


def cmd_analyser(args):
    result = analyze_video(args.video, args.sortie, args.mode, args.nom,
                           with_audio=not args.sans_audio, verbose=not args.silencieux)
    if args.ia:
        from .ai import interpret
        print("[style_video] interprétation IA des images clés…", flush=True)
        try:
            result["interpretation_ia"] = interpret(result["profil"], result["images_cles"], args.mode, args.effort)
        except RuntimeError as exc:
            print(f"[style_video] IA indisponible : {exc}", file=sys.stderr)
    paths = write_outputs(result, args.sortie)
    print("\nOrdre de travail généré :")
    for p in list(paths.values()) + [f"{args.sortie}/{result['lut']}"]:
        print(f"  - {p}")
    print("\nStyle :", result["profil"]["rythme"]["style"], "|", ", ".join(result["profil"]["etalonnage"]["tags"]) or "look neutre")


def cmd_comparer(args):
    ref = analyze_video(args.reference, verbose=not args.silencieux)
    proj = analyze_video(args.projet, verbose=not args.silencieux)
    report = compare(ref, proj)
    if args.json:
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return
    print(f"\nConformité au style de référence : {report['score_conformite']} %\n")
    for r in report["criteres"]:
        mark = "OK " if r["conforme"] else "À CORRIGER"
        print(f"  [{mark:^10}] {r['critere']:<28} réf {r['reference']:>8} | projet {r['projet']:>8} | écart {r['ecart']:+} {r['correction']}")


def main(argv=None):
    parser = argparse.ArgumentParser(prog="style_video",
                                     description="Analyse le style d'une vidéo et génère un ordre de travail de post-production.")
    sub = parser.add_subparsers(dest="commande", required=True)

    a = sub.add_parser("analyser", help="analyser une vidéo de référence")
    a.add_argument("video")
    a.add_argument("-o", "--sortie", default="ordre_de_travail")
    a.add_argument("--mode", choices=["identique", "similaire"], default="identique",
                   help="identique : reproduire au plus près ; similaire : variante originale du même style")
    a.add_argument("--nom", help="nom du style (par défaut : nom du fichier)")
    a.add_argument("--ia", action="store_true", help="ajouter l'interprétation de Claude (images clés)")
    a.add_argument("--effort", default="high", choices=["low", "medium", "high", "xhigh", "max"])
    a.add_argument("--sans-audio", action="store_true")
    a.add_argument("--silencieux", action="store_true")
    a.set_defaults(func=cmd_analyser)

    c = sub.add_parser("comparer", help="vérifier qu'un montage respecte le style de référence")
    c.add_argument("reference")
    c.add_argument("projet")
    c.add_argument("--json", action="store_true")
    c.add_argument("--silencieux", action="store_true")
    c.set_defaults(func=cmd_comparer)

    args = parser.parse_args(argv)
    args.func(args)


if __name__ == "__main__":
    main()
