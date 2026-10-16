"""Chaîne complète : vidéo -> mesures -> profil de style -> ordre de travail."""

import os

import numpy as np

from . import audio as audio_mod
from .color import analyze_look, kmeans_palette, write_cube
from .features import FrameAnalyzer
from .frames import analysis_fps, extract_still, iter_frames, texture_measures
from .interpret import build_profile, work_order
from .probe import probe
from .shots import build_shots, detect_cuts, detect_transitions


def log(msg, verbose=True):
    if verbose:
        print(f"[style_video] {msg}", flush=True)


def analyze_video(path, out_dir=None, mode="identique", name=None, with_audio=True, max_stills=16, verbose=True):
    info = probe(path)
    fps = analysis_fps(info)
    log(f"{info['largeur']}x{info['hauteur']} · {info['fps']} i/s · {info['duree']:.1f} s — analyse à {fps:.1f} i/s", verbose)

    analyzer = FrameAnalyzer()
    for t, img in iter_frames(path, info, fps):
        analyzer.add(t, img)
    rows = analyzer.rows
    if not rows:
        raise ValueError("Aucune image décodée.")
    log(f"{len(rows)} images analysées", verbose)

    cuts = detect_cuts(rows, fps)
    shots = build_shots(rows, cuts, fps, info["duree"])
    transitions = detect_transitions(rows, cuts)
    log(f"{len(shots)} plans, {len(transitions)} transitions", verbose)

    # Les images noires/blanches des transitions faussent le look : on les exclut.
    graded = [r for r in rows if 0.04 < r["luma"] < 0.93] or rows
    look = analyze_look(graded)
    palette = kmeans_palette(analyzer.pixels())

    mids = [s["debut"] + s["duree"] / 2 for s in shots]
    tex_times = list(np.linspace(info["duree"] * 0.1, info["duree"] * 0.9, 6)) if info["duree"] > 1 else [0]
    texture = texture_measures(path, info, tex_times)

    audio = None
    if with_audio and info["audio"]:
        log("analyse audio", verbose)
        audio = audio_mod.analyze(path, [s["debut"] for s in shots[1:]])

    profile = build_profile(info, rows, shots, transitions, look, analyzer.letterbox(), texture, audio)
    nom = name or os.path.splitext(os.path.basename(path))[0]
    lut_name = f"look_{nom}.cube".replace(" ", "_")
    order = work_order(profile, mode, lut_name)

    step = max(1, len(rows) // 300)
    result = {
        "nom": nom,
        "info": info,
        "profil": profile,
        "palette": palette,
        "plans": shots,
        "transitions": transitions,
        "ordre": order,
        "lut": lut_name,
        "courbe_luma": [(round(r["t"], 2), round(r["luma"], 3)) for r in rows[::step]],
        "images_cles": [],
    }

    if out_dir:
        os.makedirs(out_dir, exist_ok=True)
        write_cube(os.path.join(out_dir, lut_name), look,
                   strength=0.75 if mode == "similaire" else 1.0, title=f"Look {nom}")
        stills_dir = os.path.join(out_dir, "images_cles")
        os.makedirs(stills_dir, exist_ok=True)
        picks = np.unique(np.linspace(0, len(shots) - 1, min(max_stills, len(shots))).astype(int))
        for i in picks:
            f = os.path.join(stills_dir, f"plan_{shots[i]['numero']:03d}.jpg")
            extract_still(path, mids[i], f)
            result["images_cles"].append({"plan": shots[i]["numero"], "t": mids[i], "fichier": f})
    return result


def write_outputs(result, out_dir):
    from .report import to_html, to_json, to_markdown
    paths = {}
    for fname, render in (("ordre_de_travail.md", to_markdown), ("rapport.html", to_html), ("analyse.json", to_json)):
        p = os.path.join(out_dir, fname)
        with open(p, "w", encoding="utf-8") as fh:
            fh.write(render(result))
        paths[fname] = p
    return paths


COMPARE_KEYS = [
    ("Durée moyenne des plans (s)", lambda p: p["rythme"]["duree_moyenne_plan"], 0.25, "relatif"),
    ("Coupes / minute", lambda p: p["rythme"]["coupes_par_minute"], 0.25, "relatif"),
    ("Point noir", lambda p: p["etalonnage"]["point_noir"], 0.03, "absolu"),
    ("Médian (gris moyen)", lambda p: p["etalonnage"]["median"], 0.05, "absolu"),
    ("Point blanc", lambda p: p["etalonnage"]["point_blanc"], 0.05, "absolu"),
    ("Contraste", lambda p: p["etalonnage"]["contraste"], 0.08, "absolu"),
    ("Saturation", lambda p: p["etalonnage"]["saturation"], 0.05, "absolu"),
    ("Température (R-B)", lambda p: p["etalonnage"]["temperature"], 0.03, "absolu"),
    ("Grain (σ)", lambda p: p["texture"].get("bruit_sigma"), 1.0, "absolu"),
    ("Loudness (LUFS)", lambda p: (p.get("audio") or {}).get("lufs_integre"), 2.0, "absolu"),
]


def compare(reference, project):
    """Écarts entre un projet monté et la référence, avec verdict par critère."""
    rows = []
    for label, get, tol, kind in COMPARE_KEYS:
        a, b = get(reference["profil"]), get(project["profil"])
        if a is None or b is None:
            continue
        delta = b - a
        limit = tol * abs(a) if kind == "relatif" else tol
        ok = abs(delta) <= max(limit, 1e-9)
        rows.append({"critere": label, "reference": a, "projet": b, "ecart": round(delta, 3), "conforme": ok,
                     "correction": "" if ok else ("augmenter" if delta < 0 else "diminuer")})
    score = round(100 * sum(r["conforme"] for r in rows) / max(len(rows), 1))
    return {"score_conformite": score, "criteres": rows}
