#!/usr/bin/env python3
"""Analyse de style vidéo -> ordre de travail post-production (fichier unique).

Analyse une vidéo de référence (montage, caméra, étalonnage, texture, son) et génère
un ordre de travail pour reproduire le même style ou un style similaire.

Prérequis :
    - ffmpeg et ffprobe installés (https://ffmpeg.org)
    - Python 3.10+ et numpy :  pip install numpy
    - Optionnel (--ia) :      pip install anthropic   + variable ANTHROPIC_API_KEY

Utilisation :
    python analyse_style_video.py analyser reference.mp4 -o sortie/
    python analyse_style_video.py analyser reference.mp4 -o sortie/ --mode similaire --nom "mon_style"
    python analyse_style_video.py analyser reference.mp4 -o sortie/ --ia
    python analyse_style_video.py comparer reference.mp4 mon_montage.mp4

Fichiers produits dans le dossier de sortie :
    ordre_de_travail.md   consignes par poste (montage, étalonnage, son, export...)
    rapport.html          rapport visuel (palette, découpage, images clés, tâches à cocher)
    look_<nom>.cube       LUT 3D à importer dans DaVinci Resolve / Premiere
    analyse.json          toutes les mesures
    images_cles/          une image par plan
"""

import argparse
import base64
import colorsys
import html
import json
import numpy as np
import os
import re
import subprocess
import sys
from fractions import Fraction

# ======================================================================
# PROBE
# ======================================================================
def _ratio(value, default=0.0):
    try:
        return float(Fraction(value))
    except (ValueError, ZeroDivisionError, TypeError):
        return default


def probe(path):
    out = subprocess.run(
        ["ffprobe", "-v", "error", "-print_format", "json",
         "-show_format", "-show_streams", path],
        capture_output=True, text=True, check=True,
    ).stdout
    data = json.loads(out)
    video = next((s for s in data["streams"] if s["codec_type"] == "video"), None)
    audio = next((s for s in data["streams"] if s["codec_type"] == "audio"), None)
    if video is None:
        raise ValueError(f"Aucun flux vidéo dans {path}")

    width, height = int(video["width"]), int(video["height"])
    rotation = 0
    for side in video.get("side_data_list", []):
        if "rotation" in side:
            rotation = int(side["rotation"])
    rotation = int(video.get("tags", {}).get("rotate", rotation))
    if abs(rotation) % 180 == 90:
        width, height = height, width

    sar = _ratio(video.get("sample_aspect_ratio", "1"), 1.0) or 1.0
    fps = _ratio(video.get("avg_frame_rate")) or _ratio(video.get("r_frame_rate"), 25.0)
    duration = float(video.get("duration") or data["format"].get("duration") or 0)

    return {
        "fichier": path,
        "largeur": width,
        "hauteur": height,
        "ratio_affichage": round(width * sar / height, 3),
        "fps": round(fps, 3),
        "duree": round(duration, 3),
        "codec_video": video.get("codec_name"),
        "profil": video.get("profile"),
        "pix_fmt": video.get("pix_fmt"),
        "bits": int(video.get("bits_per_raw_sample") or 8),
        "espace_couleur": video.get("color_space"),
        "transfert": video.get("color_transfer"),
        "primaires": video.get("color_primaries"),
        "debit_kbps": round(int(data["format"].get("bit_rate", 0)) / 1000),
        "audio": None if audio is None else {
            "codec": audio.get("codec_name"),
            "canaux": int(audio.get("channels", 0)),
            "frequence": int(audio.get("sample_rate", 0)),
        },
    }

# ======================================================================
# FRAMES
# ======================================================================
ANALYSIS_WIDTH = 192
MAX_FRAMES = 4000


def analysis_fps(info):
    duration = max(info["duree"], 0.1)
    return float(min(info["fps"] or 25.0, 10.0, max(2.0, MAX_FRAMES / duration)))


def iter_frames(path, info, fps):
    """Renvoie (temps, image RGB float32 0..1) à `fps` images/seconde."""
    w = ANALYSIS_WIDTH
    h = max(2, int(round(w * info["hauteur"] / info["largeur"] / 2)) * 2)
    cmd = ["ffmpeg", "-v", "error", "-i", path, "-an",
           "-vf", f"fps={fps},scale={w}:{h}:flags=area",
           "-f", "rawvideo", "-pix_fmt", "rgb24", "-"]
    size = w * h * 3
    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE)
    try:
        index = 0
        while True:
            buf = proc.stdout.read(size)
            if len(buf) < size:
                break
            frame = np.frombuffer(buf, np.uint8).reshape(h, w, 3).astype(np.float32) / 255.0
            yield index / fps, frame
            index += 1
    finally:
        proc.stdout.close()
        proc.wait()


def extract_still(path, t, out_path, width=480):
    subprocess.run(
        ["ffmpeg", "-v", "error", "-y", "-ss", f"{t:.3f}", "-i", path,
         "-frames:v", "1", "-vf", f"scale={width}:-2", "-q:v", "4", out_path],
        check=True,
    )


def full_res_gray_crop(path, info, t, size=512):
    """Recadrage central pleine résolution en niveaux de gris (0..255) pour grain/netteté."""
    w, h = min(info["largeur"], size), min(info["hauteur"], size)
    raw = subprocess.run(
        ["ffmpeg", "-v", "error", "-ss", f"{t:.3f}", "-i", path, "-frames:v", "1",
         "-vf", f"crop={w}:{h}", "-f", "rawvideo", "-pix_fmt", "gray", "-"],
        capture_output=True,
    ).stdout
    if len(raw) != w * h:
        return None
    return np.frombuffer(raw, np.uint8).reshape(h, w).astype(np.float32)


def texture_measures(path, info, times):
    """Bruit (méthode d'Immerkær) et netteté (variance du laplacien) en pleine résolution."""
    sigmas, sharp = [], []
    for t in times:
        img = full_res_gray_crop(path, info, t)
        if img is None or min(img.shape) < 16:
            continue
        m = (img[:-2, :-2] - 2 * img[:-2, 1:-1] + img[:-2, 2:]
             - 2 * img[1:-1, :-2] + 4 * img[1:-1, 1:-1] - 2 * img[1:-1, 2:]
             + img[2:, :-2] - 2 * img[2:, 1:-1] + img[2:, 2:])
        # Ignorer les contours pour ne mesurer que le bruit
        lap = np.abs(m)
        flat = lap <= np.percentile(lap, 60)
        sigmas.append(float(np.sqrt(np.pi / 2) * lap[flat].mean() / 6))
        p = np.pad(img, 1, mode="edge")
        lp = p[:-2, 1:-1] + p[2:, 1:-1] + p[1:-1, :-2] + p[1:-1, 2:] - 4 * img
        sharp.append(float(lp.var()))
    if not sigmas:
        return {}
    return {"bruit_sigma": round(float(np.median(sigmas)), 2), "nettete": round(float(np.median(sharp)), 1)}

# ======================================================================
# FEATURES
# ======================================================================
LUMA = np.array([0.2126, 0.7152, 0.0722], np.float32)


def luma(img):
    return img @ LUMA


def colorfulness(img):
    """Richesse colorimétrique (Hasler & Süsstrunk, partie écart-type).

    Indépendante d'une dominante globale : une image teintée mais terne reste peu « saturée ».
    """
    r, g, b = img[..., 0], img[..., 1], img[..., 2]
    rg = r - g
    yb = 0.5 * (r + g) - b
    return float(np.sqrt(rg.std() ** 2 + yb.std() ** 2))


def color_histogram(img, bins=8):
    q = np.clip((img * bins).astype(np.int32), 0, bins - 1)
    idx = q[..., 0] * bins * bins + q[..., 1] * bins + q[..., 2]
    hist = np.bincount(idx.ravel(), minlength=bins ** 3).astype(np.float32)
    return hist / hist.sum()


def laplacian(gray):
    p = np.pad(gray, 1, mode="edge")
    return p[:-2, 1:-1] + p[2:, 1:-1] + p[1:-1, :-2] + p[1:-1, 2:] - 4 * gray


def phase_correlation(a, b):
    """Translation globale (dx, dy) en pixels entre deux images en niveaux de gris."""
    win = np.outer(np.hanning(a.shape[0]), np.hanning(a.shape[1])).astype(np.float32)
    fa = np.fft.fft2((a - a.mean()) * win)
    fb = np.fft.fft2((b - b.mean()) * win)
    r = fa * np.conj(fb)
    r /= np.abs(r) + 1e-8
    corr = np.fft.ifft2(r).real
    peak = np.unravel_index(np.argmax(corr), corr.shape)
    dy, dx = [p if p <= s // 2 else p - s for p, s in zip(peak, corr.shape)]
    strength = float(corr.max())
    return float(dx), float(dy), strength


def tone_tint(img, lum, mask):
    """Dominante colorée (RGB centré sur la luminance) dans une zone tonale."""
    if mask.sum() < 20:
        return np.zeros(3, np.float32)
    px = img[mask]
    return (px - lum[mask][:, None]).mean(axis=0)


def crop_borders(img, lum, max_frac=1 / 3):
    """Retire les bandes noires (letterbox / pillarbox) avant les mesures d'image."""
    h, w = lum.shape
    rows, cols = lum.mean(axis=1) < 0.035, lum.mean(axis=0) < 0.035
    top = 0
    while top < h * max_frac and rows[top]:
        top += 1
    bottom = h
    while h - bottom < h * max_frac and rows[bottom - 1]:
        bottom -= 1
    left = 0
    while left < w * max_frac and cols[left]:
        left += 1
    right = w
    while w - right < w * max_frac and cols[right - 1]:
        right -= 1
    # Une image entièrement noire (fondu) est conservée telle quelle.
    if bottom - top < h // 4 or right - left < w // 4:
        return img
    return img[top:bottom, left:right]


class FrameAnalyzer:
    """Accumule les mesures image par image en un seul passage."""

    def __init__(self, rng_seed=0):
        self.rows = []
        self.prev_gray = None
        self.prev_hist = None
        self.rng = np.random.default_rng(rng_seed)
        self.palette_samples = []
        self.dark_rows = None

    def add(self, t, img):
        full_lum = luma(img)
        dark = full_lum.mean(axis=1) < 0.035
        self.dark_rows = dark.astype(np.int32) if self.dark_rows is None else self.dark_rows + dark
        hist = color_histogram(img)
        fh, fw = full_lum.shape
        img = crop_borders(img, full_lum)
        lum = luma(img)
        h, w = lum.shape

        p = np.percentile(lum, [1, 5, 25, 50, 75, 95, 99])
        shadows = lum <= max(p[2], 0.02)
        highlights = lum >= min(p[4], 0.98)
        mid = (~shadows) & (~highlights)

        lap = laplacian(lum)
        flat = np.abs(lap) <= np.percentile(np.abs(lap), 50)
        noise = float(np.median(np.abs(lap[flat]))) if flat.any() else 0.0

        ch, cw = h // 4, w // 4
        center = lum[ch:h - ch, cw:w - cw].mean()
        corners = np.concatenate([
            lum[:ch, :cw].ravel(), lum[:ch, -cw:].ravel(),
            lum[-ch:, :cw].ravel(), lum[-ch:, -cw:].ravel()])
        vignette = float(1.0 - corners.mean() / max(center, 1e-3))

        row = {
            "t": t,
            "luma": float(lum.mean()),
            "p1": float(p[0]), "p5": float(p[1]), "p25": float(p[2]),
            "p50": float(p[3]), "p75": float(p[4]), "p95": float(p[5]), "p99": float(p[6]),
            "luma_std": float(lum.std()),
            "sat": colorfulness(img),
            "rgb": img.reshape(-1, 3).mean(axis=0).tolist(),
            "tint_ombres": tone_tint(img, lum, shadows).tolist(),
            "tint_medians": tone_tint(img, lum, mid).tolist(),
            "tint_hautes": tone_tint(img, lum, highlights).tolist(),
            "nettete": float(np.var(lap)),
            "bruit": noise,
            "vignette": vignette,
            "hist_diff": 0.0, "pix_diff": 0.0,
            "dx": 0.0, "dy": 0.0, "residuel": 0.0,
        }

        # Coupes et mouvement : image complète (taille constante d'une image à l'autre)
        if self.prev_gray is not None:
            row["hist_diff"] = float(0.5 * np.abs(hist - self.prev_hist).sum())
            row["pix_diff"] = float(np.abs(full_lum - self.prev_gray).mean())
            dx, dy, strength = phase_correlation(full_lum, self.prev_gray)
            if strength > 0.03 and abs(dx) < fw / 4 and abs(dy) < fh / 4:
                row["dx"], row["dy"] = dx / fw, dy / fh
                shifted = np.roll(self.prev_gray, (int(dy), int(dx)), axis=(0, 1))
                m = 8
                row["residuel"] = float(np.abs(full_lum - shifted)[m:-m, m:-m].mean())
            else:
                row["residuel"] = row["pix_diff"]

        n = min(300, h * w)
        sel = self.rng.choice(h * w, n, replace=False)
        self.palette_samples.append(img.reshape(-1, 3)[sel])

        self.prev_gray, self.prev_hist = full_lum, hist
        self.rows.append(row)

    def letterbox(self):
        """Proportion de bandes noires haut/bas présentes sur >90 % des images."""
        if self.dark_rows is None or not self.rows:
            return 0.0, 0.0
        persistent = self.dark_rows >= 0.9 * len(self.rows)
        h = len(persistent)
        top = 0
        while top < h // 3 and persistent[top]:
            top += 1
        bottom = 0
        while bottom < h // 3 and persistent[h - 1 - bottom]:
            bottom += 1
        return top / h, bottom / h

    def pixels(self):
        return np.concatenate(self.palette_samples) if self.palette_samples else np.zeros((0, 3))

# ======================================================================
# SHOTS
# ======================================================================
MIN_SHOT = 0.35  # secondes


def detect_cuts(rows, fps):
    """Coupes franches par seuil adaptatif sur la différence d'histogrammes."""
    if len(rows) < 3:
        return []
    hd = np.array([r["hist_diff"] for r in rows])
    pdiff = np.array([r["pix_diff"] for r in rows])
    score = hd + 2.0 * pdiff
    med = np.median(score[1:])
    mad = np.median(np.abs(score[1:] - med)) + 1e-6
    threshold = max(0.30, med + 8 * mad)

    cuts, last = [], -1e9
    for i in range(1, len(rows)):
        neighbours = score[max(1, i - 2):i + 3]
        is_peak = score[i] >= neighbours.max()
        if score[i] > threshold and is_peak and rows[i]["t"] - last >= MIN_SHOT:
            cuts.append(i)
            last = rows[i]["t"]
    return cuts


def detect_transitions(rows, cuts):
    """Fondus au noir / au blanc et fondus enchaînés approximatifs."""
    lum = np.array([r["luma"] for r in rows])
    transitions = []
    i = 0
    while i < len(rows):
        if lum[i] < 0.04 or lum[i] > 0.93:
            kind = "fondu_noir" if lum[i] < 0.04 else "flash_blanc"
            j = i
            while j + 1 < len(rows) and ((lum[j + 1] < 0.04) if kind == "fondu_noir" else (lum[j + 1] > 0.93)):
                j += 1
            if 0 < i and j < len(rows) - 1:
                transitions.append({"type": kind, "debut": rows[i]["t"], "fin": rows[j]["t"]})
            i = j + 1
        else:
            i += 1

    # Fondus enchaînés : différence modérée et soutenue, sans coupe franche.
    pdiff = np.array([r["pix_diff"] for r in rows])
    base = np.median(pdiff) + 1e-6
    cut_set = set(cuts)
    run = 0
    for i in range(1, len(rows)):
        if pdiff[i] > 3 * base and pdiff[i] < 0.15 and i not in cut_set and abs(rows[i]["dx"]) + abs(rows[i]["dy"]) < 0.01:
            run += 1
        else:
            if run >= 4:
                transitions.append({"type": "fondu_enchaine", "debut": rows[i - run]["t"], "fin": rows[i - 1]["t"]})
            run = 0
    return sorted(transitions, key=lambda x: x["debut"])


def classify_camera(seg, fps):
    """Statique / panoramique / inclinaison / épaule / mouvement dans le cadre."""
    if len(seg) < 3:
        return "indéterminé", {}
    dx = np.array([r["dx"] for r in seg[1:]])
    dy = np.array([r["dy"] for r in seg[1:]])
    res = np.array([r["residuel"] for r in seg[1:]])
    speed = np.hypot(dx, dy) * fps  # largeur d'image par seconde
    k = min(5, len(dx))
    kernel = np.ones(k) / k
    jitter = float(np.std(dx - np.convolve(dx, kernel, "same")) + np.std(dy - np.convolve(dy, kernel, "same"))) * fps
    drift_x = float(np.mean(dx) * fps)
    drift_y = float(np.mean(dy) * fps)
    metrics = {
        "vitesse": round(float(np.median(speed)), 4),
        "derive_x": round(drift_x, 4),
        "derive_y": round(drift_y, 4),
        "tremblement": round(jitter, 4),
        "activite": round(float(np.mean(res)), 4),
    }
    if jitter > 0.06 and jitter > 0.5 * max(abs(drift_x), abs(drift_y)):
        label = "caméra épaule"
    elif abs(drift_x) > 0.03 and abs(drift_x) >= abs(drift_y):
        label = "panoramique " + ("gauche" if drift_x > 0 else "droite")
    elif abs(drift_y) > 0.03:
        label = "inclinaison " + ("haut" if drift_y > 0 else "bas")
    elif metrics["activite"] > 0.04:
        label = "fixe, action dans le cadre"
    else:
        label = "fixe"
    return label, metrics


def build_shots(rows, cuts, fps, duration):
    bounds = [0] + cuts + [len(rows)]
    shots = []
    for n, (a, b) in enumerate(zip(bounds[:-1], bounds[1:])):
        seg = rows[a:b]
        if not seg:
            continue
        start = seg[0]["t"]
        end = rows[b]["t"] if b < len(rows) else duration
        camera, cam_metrics = classify_camera(seg, fps)
        shots.append({
            "numero": n + 1,
            "debut": round(start, 3),
            "fin": round(end, 3),
            "duree": round(end - start, 3),
            "luma": round(float(np.mean([r["luma"] for r in seg])), 3),
            "saturation": round(float(np.mean([r["sat"] for r in seg])), 3),
            "rgb": [round(float(v), 3) for v in np.mean([r["rgb"] for r in seg], axis=0)],
            "camera": camera,
            "camera_mesures": cam_metrics,
        })
    return shots

# ======================================================================
# COLOR
# ======================================================================
# Quantiles de luminance d'une image « neutre » bien exposée (Rec.709, gamma 2.4)
NEUTRAL_Q = np.array([0.01, 0.05, 0.25, 0.50, 0.75, 0.95, 0.99])
NEUTRAL_V = np.array([0.03, 0.07, 0.22, 0.42, 0.62, 0.86, 0.95])
NEUTRAL_SAT = 0.18  # richesse colorimétrique d'une image vidéo standard


def kmeans_palette(pixels, k=6, iters=20, seed=0):
    if len(pixels) < k:
        return []
    rng = np.random.default_rng(seed)
    pts = pixels[rng.choice(len(pixels), min(len(pixels), 20000), replace=False)]
    centers = pts[rng.choice(len(pts), k, replace=False)]
    for _ in range(iters):
        d = ((pts[:, None, :] - centers[None]) ** 2).sum(-1)
        lab = d.argmin(1)
        for j in range(k):
            if (lab == j).any():
                centers[j] = pts[lab == j].mean(0)
    counts = np.bincount(lab, minlength=k) / len(pts)
    order = np.argsort(-counts)
    return [{"hex": "#%02x%02x%02x" % tuple(int(round(c * 255)) for c in centers[i]),
             "rgb": [round(float(c), 3) for c in centers[i]],
             "part": round(float(counts[i]), 3)} for i in order]


def describe_tint(vec):
    """Nom lisible d'une dominante RGB centrée."""
    r, g, b = vec
    mag = float(np.linalg.norm(vec))
    if mag < 0.012:
        return "neutre", mag
    h, _, _ = colorsys.rgb_to_hsv(*(np.clip(np.array(vec) / (2 * mag) + 0.5, 0, 1)))
    deg = h * 360
    names = [(15, "rouge"), (45, "orange"), (70, "jaune"), (150, "vert"),
             (195, "cyan / teal"), (255, "bleu"), (290, "violet"), (335, "magenta"), (360, "rouge")]
    name = next(n for limit, n in names if deg <= limit)
    return name, mag


def analyze_look(rows):
    def mean(key):
        return np.mean([r[key] for r in rows], axis=0)

    q = np.array([mean(k) for k in ("p1", "p5", "p25", "p50", "p75", "p95", "p99")])
    tints = {zone: mean(f"tint_{zone}") for zone in ("ombres", "medians", "hautes")}
    sat = float(mean("sat"))
    rgb = mean("rgb")
    warmth = float(rgb[0] - rgb[2])
    contrast = float(q[5] - q[1])

    names = {z: describe_tint(v) for z, v in tints.items()}
    teal_orange = (names["ombres"][0] in ("cyan / teal", "bleu", "vert")
                   and names["hautes"][0] in ("orange", "jaune", "rouge"))

    tags = []
    if q[0] > 0.07:
        tags.append("noirs relevés (look mat / film)")
    elif q[0] < 0.015:
        tags.append("noirs écrasés")
    if q[6] < 0.85:
        tags.append("hautes lumières compressées (pas de blanc pur)")
    if contrast > 0.75:
        tags.append("contraste fort")
    elif contrast < 0.5:
        tags.append("contraste doux / faible")
    if sat < 0.07:
        tags.append("très désaturé")
    elif sat < 0.12:
        tags.append("désaturé")
    elif sat > 0.30:
        tags.append("saturation forte / pop")
    if warmth > 0.04:
        tags.append("balance chaude")
    elif warmth < -0.04:
        tags.append("balance froide")
    if teal_orange:
        tags.append("split-toning teal & orange")
    elif names["ombres"][0] != "neutre" and names["ombres"][0] != names["hautes"][0]:
        tags.append(f"split-toning ombres {names['ombres'][0]} / hautes lumières {names['hautes'][0]}")
    if q[3] < 0.25:
        tags.append("low-key (image sombre)")
    elif q[3] > 0.55:
        tags.append("high-key (image claire)")

    return {
        "quantiles_luma": [round(float(v), 3) for v in q],
        "point_noir": round(float(q[0]), 3),
        "point_blanc": round(float(q[6]), 3),
        "median": round(float(q[3]), 3),
        "contraste": round(contrast, 3),
        "saturation": round(sat, 3),
        "temperature": round(warmth, 3),
        "dominantes": {z: {"teinte": names[z][0], "intensite": round(names[z][1], 3),
                           "rgb": [round(float(v), 4) for v in tints[z]]} for z in tints},
        "teal_orange": bool(teal_orange),
        "tags": tags,
    }


def resolve_wheels(look):
    """Valeurs indicatives pour les roues Lift / Gamma / Gain (DaVinci Resolve)."""
    d = look["dominantes"]
    def wheel(vec, scale):
        return [round(float(v) * scale, 3) for v in vec]
    return {
        "lift": wheel(d["ombres"]["rgb"], 1.0),
        "gamma": wheel(d["medians"]["rgb"], 0.8),
        "gain": wheel(d["hautes"]["rgb"], 1.0),
        "lift_master": round(look["point_noir"] - NEUTRAL_V[0], 3),
        "gain_master": round(look["point_blanc"] - NEUTRAL_V[-1], 3),
        "saturation": round(50 * look["saturation"] / NEUTRAL_SAT),
    }


def make_transform(look, strength=1.0):
    """Fonction RGB->RGB reproduisant le look mesuré à partir d'une image neutre."""
    target = np.array(look["quantiles_luma"])
    xs = np.concatenate([[0.0], NEUTRAL_V, [1.0]])
    ys = np.concatenate([[max(0.0, target[0] - 0.03)], target, [min(1.0, target[-1] + 0.03)]])
    ys = np.maximum.accumulate(ys)
    sat_gain = float(np.clip(look["saturation"] / NEUTRAL_SAT, 0.4, 1.6))
    d = look["dominantes"]
    sh, md, hi = (np.array(d[z]["rgb"]) * 1.2 for z in ("ombres", "medians", "hautes"))

    def apply(rgb):
        l = rgb @ np.array([0.2126, 0.7152, 0.0722])
        l_new = np.interp(l, xs, ys)
        chroma = (rgb - l[..., None]) * sat_gain
        w_sh = np.clip(1 - l_new / 0.5, 0, 1)[..., None] ** 2
        w_hi = np.clip((l_new - 0.5) / 0.5, 0, 1)[..., None] ** 2
        w_md = 1 - w_sh - w_hi
        out = l_new[..., None] + chroma + w_sh * sh + w_md * md + w_hi * hi
        return np.clip(rgb + strength * (out - rgb), 0, 1)
    return apply


def write_cube(path, look, strength=1.0, size=33, title="Look reference"):
    f = make_transform(look, strength)
    grid = np.linspace(0, 1, size)
    b, g, r = np.meshgrid(grid, grid, grid, indexing="ij")  # R varie le plus vite
    rgb = np.stack([r, g, b], axis=-1).reshape(-1, 3)
    out = f(rgb)
    with open(path, "w") as fh:
        fh.write(f'TITLE "{title}"\nLUT_3D_SIZE {size}\nDOMAIN_MIN 0 0 0\nDOMAIN_MAX 1 1 1\n')
        for v in out:
            fh.write(f"{v[0]:.6f} {v[1]:.6f} {v[2]:.6f}\n")

# ======================================================================
# AUDIO
# ======================================================================
SR = 11025
HOP = 256


def loudness(path):
    proc = subprocess.run(
        ["ffmpeg", "-nostats", "-i", path, "-vn", "-af", "ebur128=peak=true", "-f", "null", "-"],
        capture_output=True, text=True,
    )
    summary = proc.stderr.split("Summary:")[-1]
    def grab(label):
        m = re.search(rf"{label}:\s+(-?[\d.]+|-inf)", summary)
        return None if not m or m.group(1) == "-inf" else float(m.group(1))
    return {"lufs_integre": grab("I"), "lra": grab("LRA"), "true_peak": grab("Peak")}


def load_mono(path):
    raw = subprocess.run(
        ["ffmpeg", "-v", "error", "-i", path, "-vn", "-ac", "1", "-ar", str(SR), "-f", "s16le", "-"],
        capture_output=True, check=True,
    ).stdout
    return np.frombuffer(raw, np.int16).astype(np.float32) / 32768.0


def onset_envelope(y):
    n = len(y) // HOP
    if n < 4:
        return np.zeros(0)
    frames = y[: n * HOP].reshape(n, HOP)
    spec = np.abs(np.fft.rfft(frames * np.hanning(HOP), axis=1))
    logspec = np.log1p(100 * spec)
    flux = np.maximum(np.diff(logspec, axis=0), 0).sum(axis=1)
    flux = np.concatenate([[0], flux])
    flux -= np.convolve(flux, np.ones(16) / 16, "same")
    return np.maximum(flux, 0)


def estimate_tempo(env):
    rate = SR / HOP
    if len(env) < rate * 4 or env.std() < 1e-6:
        return None, 0.0
    # Lissage : tolère les périodes non entières en nombre de trames
    smooth = np.convolve(env, np.hanning(7) / np.hanning(7).sum(), "same")
    e = smooth - smooth.mean()
    ac = np.correlate(e, e, "full")[len(e) - 1:]
    ac /= ac[0] + 1e-9
    lags = np.arange(len(ac))
    bpm = 60 * rate / np.maximum(lags, 1)
    valid = (bpm >= 60) & (bpm <= 180)
    # Pondération douce autour de 120 BPM (tempo perçu le plus fréquent)
    weight = np.exp(-0.5 * (np.log2(bpm / 120) / 0.9) ** 2)
    score = np.where(valid, ac * weight, -np.inf)
    lag = int(np.argmax(score))
    # Correction d'octave : si le double tempo est presque aussi périodique, il est perçu
    half = int(round(lag / 2))
    if bpm[lag] * 2 <= 180 and half > 0:
        window = ac[max(1, half - 1):half + 2]
        if window.max() > 0.6 * ac[lag]:
            lag = max(1, half - 1) + int(np.argmax(window))
    # Interpolation parabolique : période sous-trame
    frac = float(lag)
    if 1 <= lag < len(ac) - 1:
        a, b, c = ac[lag - 1], ac[lag], ac[lag + 1]
        denom = a - 2 * b + c
        if denom < 0:
            frac = lag + 0.5 * (a - c) / denom
    return float(round(60 * rate / frac, 1)), float(max(ac[lag], 0))


def beat_times(env, bpm):
    rate = SR / HOP
    period = 60 * rate / bpm
    best_phase, best = 0, -1
    for phase in range(int(period)):
        idx = np.arange(phase, len(env), period).astype(int)
        s = env[idx].sum()
        if s > best:
            best, best_phase = s, phase
    return np.arange(best_phase, len(env), period) / rate


def cut_sync(cut_times, beats, bpm):
    """Part des coupes proches d'un temps, comparée au hasard."""
    if not len(cut_times) or beats is None or not len(beats):
        return None
    tol = min(0.08, 15 / bpm)
    dist = np.array([np.min(np.abs(beats - t)) for t in cut_times])
    ratio = float(np.mean(dist <= tol))
    chance = min(1.0, 2 * tol * bpm / 60)
    return {"ratio": round(ratio, 3), "hasard": round(chance, 3), "tolerance_s": round(tol, 3)}


def audio_analyze(path, cut_times):
    y = load_mono(path)
    if len(y) < SR:
        return None
    rms = np.sqrt(np.mean(y[: len(y) // SR * SR].reshape(-1, SR) ** 2, axis=1) + 1e-12)
    db = 20 * np.log10(rms)
    env = onset_envelope(y)
    bpm, confidence = estimate_tempo(env)
    beats = beat_times(env, bpm) if bpm and confidence > 0.1 else None
    result = {
        **loudness(path),
        "silence_ratio": round(float(np.mean(db < -50)), 3),
        "dynamique_db": round(float(np.percentile(db, 95) - np.percentile(db, 10)), 1),
        "bpm": bpm if beats is not None else None,
        "bpm_confiance": round(confidence, 3),
        "synchro_coupes": cut_sync(np.array(cut_times), beats, bpm) if beats is not None else None,
        "energie_par_seconde": [round(float(v), 1) for v in db],
    }
    return result

# ======================================================================
# INTERPRET
# ======================================================================
STANDARD_RATIOS = [(2.76, "2.76:1 (Ultra Panavision)"), (2.39, "2.39:1 (Scope)"), (2.0, "2:1 (Univisium)"),
                   (1.85, "1.85:1 (Flat cinéma)"), (1.78, "16:9"), (1.33, "4:3"), (1.0, "1:1 (carré)"),
                   (0.8, "4:5 (Instagram)"), (0.5625, "9:16 (vertical)")]


def nearest_ratio(r):
    return min(STANDARD_RATIOS, key=lambda x: abs(x[0] - r))[1]


def rhythm_profile(shots, duration):
    lengths = np.array([s["duree"] for s in shots]) if shots else np.array([duration])
    asl = float(lengths.mean())
    cv = float(lengths.std() / max(asl, 1e-6))
    half = len(lengths) // 2
    trend = None
    if half >= 3:
        a, b = lengths[:half].mean(), lengths[half:].mean()
        trend = "accélération" if b < 0.75 * a else "ralentissement" if b > 1.33 * a else "stable"
    if asl < 1.5:
        label = "montage très rapide (clip musical, pub, bande-annonce)"
    elif asl < 3:
        label = "montage rapide / dynamique"
    elif asl < 6:
        label = "rythme modéré (narratif, corporate dynamique)"
    elif asl < 12:
        label = "rythme posé (documentaire, fiction)"
    else:
        label = "rythme contemplatif / plans-séquences"
    return {
        "nombre_plans": len(lengths),
        "duree_moyenne_plan": round(asl, 2),
        "duree_mediane_plan": round(float(np.median(lengths)), 2),
        "plan_min": round(float(lengths.min()), 2),
        "plan_max": round(float(lengths.max()), 2),
        "coupes_par_minute": round(60 * max(len(lengths) - 1, 0) / max(duration, 1e-6), 1),
        "regularite_cv": round(cv, 2),
        "regularite": "très régulier (métronomique)" if cv < 0.35 else "variable" if cv < 0.8 else "très irrégulier (ruptures)",
        "evolution": trend,
        "style": label,
    }


def camera_profile(shots):
    if not shots:
        return {}
    total = sum(s["duree"] for s in shots) or 1
    share = {}
    for s in shots:
        key = s["camera"].split(" ")[0] if s["camera"].startswith(("panoramique", "inclinaison")) else s["camera"]
        share[key] = share.get(key, 0) + s["duree"] / total
    share = {k: round(v, 3) for k, v in sorted(share.items(), key=lambda kv: -kv[1])}
    dominant = next(iter(share))
    return {"repartition": share, "dominante": dominant}


def texture_profile(texture, mean_vignette):
    tags = []
    grain = texture.get("bruit_sigma")
    if grain is not None:
        if grain < 1.5:
            tags.append("image propre (peu ou pas de grain)")
        elif grain < 3:
            tags.append("grain léger")
        elif grain < 6:
            tags.append("grain visible (look pellicule / ISO élevé)")
        else:
            tags.append("grain fort")
    sharp = texture.get("nettete")
    if sharp is not None:
        if sharp < 60:
            tags.append("image douce (diffusion, flou, filtre type Pro-Mist possible)")
        elif sharp > 400:
            tags.append("image très piquée (accentuation)")
    if mean_vignette > 0.3:
        tags.append("vignettage marqué")
    elif mean_vignette > 0.18:
        tags.append("vignettage léger")
    return {**texture, "vignette": round(mean_vignette, 3), "tags": tags}


def build_profile(info, rows, shots, transitions, look, letterbox, texture, audio):
    duration = info["duree"]
    top, bottom = letterbox
    active_ratio = info["ratio_affichage"] / max(1e-6, 1 - top - bottom)
    vignette = float(np.mean([r["vignette"] for r in rows])) if rows else 0.0
    trans_count = {}
    for t in transitions:
        trans_count[t["type"]] = trans_count.get(t["type"], 0) + 1

    fps = info["fps"]
    if abs(fps - 23.976) < 0.05 or abs(fps - 24) < 0.05:
        cadence = "24p (rendu cinéma)"
    elif abs(fps - 25) < 0.05:
        cadence = "25p (PAL / broadcast Europe)"
    elif fps < 31:
        cadence = "30p (web / vidéo)"
    else:
        cadence = f"{fps:g} i/s (fluide, sport, réseaux sociaux ou ralenti possible)"

    return {
        "format": {
            "resolution": f"{info['largeur']}x{info['hauteur']}",
            "ratio_conteneur": nearest_ratio(info["ratio_affichage"]),
            "bandes_noires": round(top + bottom, 3) > 0.02,
            "ratio_image_active": nearest_ratio(active_ratio),
            "ratio_image_active_valeur": round(active_ratio, 3),
            "cadence": cadence,
            "orientation": "verticale" if info["ratio_affichage"] < 1 else "horizontale",
        },
        "rythme": rhythm_profile(shots, duration),
        "transitions": {"compte": trans_count,
                        "dominante": "coupes franches" if not transitions else max(trans_count, key=trans_count.get)},
        "camera": camera_profile(shots),
        "etalonnage": look,
        "texture": texture_profile(texture, vignette),
        "audio": audio,
    }


def _task(action, params=None, outil=None):
    t = {"action": action}
    if params:
        t["parametres"] = params
    if outil:
        t["outil"] = outil
    return t


def work_order(profile, mode="identique", lut_name="look_reference.cube"):
    """Ordre de travail structuré, applicable à de nouveaux rushes."""
    similar = mode == "similaire"
    fmt, rh, cam = profile["format"], profile["rythme"], profile["camera"]
    look, tex, au = profile["etalonnage"], profile["texture"], profile["audio"]
    wheels = resolve_wheels(look)
    tol = (lambda v, p=0.2: f"{v * (1 - p):.2f} – {v * (1 + p):.2f}") if similar else (lambda v, p=0: f"{v:.2f}")

    sections = []

    sections.append({
        "titre": "1. Préparation du projet",
        "objectif": "Configurer la timeline pour reproduire le cadre technique de la référence.",
        "taches": [
            _task("Créer la timeline", {"résolution": fmt["resolution"], "cadence": fmt["cadence"],
                                        "orientation": fmt["orientation"]}),
            _task("Ratio d'image", {"conteneur": fmt["ratio_conteneur"],
                                    "image active": fmt["ratio_image_active"],
                                    "bandes noires": "oui (masque / letterbox)" if fmt["bandes_noires"] else "non"}),
            _task("Espace couleur de travail", {"timeline": "Rec.709 gamma 2.4 (ou DaVinci Wide Gamut + ACES si rushes LOG)",
                                                "sortie": "Rec.709"}),
        ],
    })

    montage = [
        _task("Rythme de coupe", {"durée moyenne des plans (s)": tol(rh["duree_moyenne_plan"]),
                                  "durée médiane (s)": tol(rh["duree_mediane_plan"]),
                                  "coupes / minute": tol(rh["coupes_par_minute"]),
                                  "plage": f"{rh['plan_min']} s → {rh['plan_max']} s"}),
        _task(f"Style : {rh['style']}", {"régularité": rh["regularite"]}),
    ]
    if rh.get("evolution") and rh["evolution"] != "stable":
        montage.append(_task(f"Structure : {rh['evolution']} du montage vers la fin",
                             {"conseil": "plans longs en ouverture, raccourcir progressivement" if rh["evolution"] == "accélération"
                              else "attaque nerveuse puis respiration en fin de film"}))
    tc = profile["transitions"]["compte"]
    if tc:
        montage.append(_task("Transitions", {k.replace("_", " "): v for k, v in tc.items()}))
    else:
        montage.append(_task("Transitions : uniquement des coupes franches (cut), aucun fondu"))
    sync = (au or {}).get("synchro_coupes")
    if sync and au.get("bpm"):
        on_beat = sync["ratio"] > sync["hasard"] + 0.15
        montage.append(_task("Synchronisation musique / image",
                             {"tempo": f"{au['bpm']} BPM",
                              "coupes sur le temps": f"{sync['ratio'] * 100:.0f} % (hasard ≈ {sync['hasard'] * 100:.0f} %)",
                              "consigne": "caler les coupes sur les temps (marqueurs de beat)" if on_beat
                              else "montage libre, non calé sur le tempo"}))
    sections.append({"titre": "2. Montage", "objectif": "Reproduire le rythme et la grammaire de coupe.", "taches": montage})

    if cam:
        rep = ", ".join(f"{k} {v * 100:.0f} %" for k, v in cam["repartition"].items())
        cam_tasks = [_task("Répartition des mouvements de caméra", {"mesuré": rep})]
        if cam["dominante"] == "caméra épaule":
            cam_tasks.append(_task("Conserver le tremblé : pas de stabilisation forte ; ajouter un camera-shake léger sur les plans trop stables",
                                   outil="Resolve : Camera Shake (Edit/Fusion) · Premiere : Transformer + wiggle"))
        else:
            cam_tasks.append(_task("Stabiliser les plans tremblés pour coller au rendu posé de la référence",
                                   outil="Resolve : Stabilizer (Color) · Premiere : Stabilisation de déformation"))
        sections.append({"titre": "3. Cadre & mouvement", "objectif": "Respecter la grammaire de caméra.", "taches": cam_tasks})

    color_tasks = [
        _task("Normalisation (nœud 1)", {"conseil": "convertir les rushes LOG en Rec.709 (CST) avant le look ; balance des blancs neutre"}),
        _task("Primaire – courbe tonale (nœud 2)", {
            "point noir cible": tol(look["point_noir"], 0.15),
            "médian (gris moyen) cible": tol(look["median"], 0.1),
            "point blanc cible": tol(look["point_blanc"], 0.05),
            "Lift master": f"{wheels['lift_master']:+.3f}",
            "Gain master": f"{wheels['gain_master']:+.3f}",
        }, outil="Resolve : Primaries Wheels / Curves · Premiere : Lumetri > Courbes"),
        _task("Saturation globale", {"saturation moyenne mesurée": look["saturation"],
                                     "réglage Resolve (50 = neutre)": wheels["saturation"]}),
        _task("Split-toning (nœud 3)", {
            "ombres": f"{look['dominantes']['ombres']['teinte']} → Lift RGB {wheels['lift']}",
            "tons moyens": f"{look['dominantes']['medians']['teinte']} → Gamma RGB {wheels['gamma']}",
            "hautes lumières": f"{look['dominantes']['hautes']['teinte']} → Gain RGB {wheels['gain']}",
        }, outil="Resolve : roues Lift/Gamma/Gain (offsets RGB) · Premiere : Lumetri > Roues chromatiques"),
        _task("Appliquer la LUT créative générée", {"fichier": lut_name,
                                                     "intensité": "70–80 % (base, puis personnaliser)" if similar else "100 % puis affiner",
                                                     "position": "après la normalisation, avant les secondaires"}),
    ]
    if look["teal_orange"]:
        color_tasks.append(_task("Secondaire : protéger les carnations",
                                 {"conseil": "qualifier les tons chair (HSL) et les ramener vers l'orange naturel"}))
    color_tasks.append(_task("Contrôle", {"tags du look": ", ".join(look["tags"]) or "neutre",
                                          "scopes": "Parade RGB + vecteurscope, comparer en split-screen avec la référence"}))
    sections.append({"titre": "4. Étalonnage", "objectif": "Reproduire le look colorimétrique.", "taches": color_tasks})

    tex_tasks = []
    g = tex.get("bruit_sigma")
    if g is not None and g >= 1.5:
        tex_tasks.append(_task("Ajouter du grain", {"niveau mesuré (σ, 0-255)": round(g, 2),
                                                    "Resolve Film Grain": "35mm, intensité ~%d" % min(100, int(g * 8))}))
    for tag in tex["tags"]:
        if "douce" in tag:
            tex_tasks.append(_task("Adoucir l'image", {"Resolve": "Mist / Glow léger ou flou 0.2–0.4",
                                                       "alternative": "filtre Pro-Mist 1/8 au tournage"}))
        if "vignettage" in tag:
            tex_tasks.append(_task("Vignettage", {"intensité": "%.0f %% d'assombrissement des coins" % (tex["vignette"] * 100)}))
        if "piquée" in tag:
            tex_tasks.append(_task("Accentuation", {"Resolve": "Sharpen radius 0.4–0.45"}))
    if fmt["bandes_noires"]:
        tex_tasks.append(_task("Letterbox", {"ratio": fmt["ratio_image_active"],
                                             "Resolve": "Timeline > Output Blanking"}))
    if tex_tasks:
        sections.append({"titre": "5. Texture & finition", "objectif": "Matière de l'image.", "taches": tex_tasks})

    if au:
        audio_tasks = [_task("Mixage / loudness", {
            "LUFS intégré mesuré": au.get("lufs_integre"),
            "LRA (plage)": au.get("lra"),
            "true peak": au.get("true_peak"),
            "cible livraison": "-14 LUFS (web/réseaux) · -23 LUFS (TV EBU R128)"})]
        if au.get("bpm"):
            audio_tasks.append(_task("Musique", {"tempo à rechercher": f"{au['bpm']:.0f} BPM (±{8 if similar else 3})",
                                                 "dynamique": f"{au['dynamique_db']} dB"}))
        if au.get("silence_ratio", 0) > 0.1:
            audio_tasks.append(_task("Silences utilisés comme effet", {"part de silence": f"{au['silence_ratio'] * 100:.0f} %"}))
        sections.append({"titre": "6. Son", "objectif": "Reproduire l'énergie sonore.", "taches": audio_tasks})

    sections.append({
        "titre": "7. Export",
        "objectif": "Livrer au même format que la référence.",
        "taches": [
            _task("Master", {"codec": "ProRes 422 HQ / DNxHR HQX", "résolution": fmt["resolution"]}),
            _task("Diffusion web", {"codec": "H.264 High / H.265", "débit": "≥ 20 Mb/s en 1080p, ≥ 45 Mb/s en 4K",
                                    "audio": "AAC 320 kb/s 48 kHz"}),
        ],
    })

    if similar:
        sections.append({
            "titre": "8. Variante « style similaire »",
            "objectif": "Garder l'ADN du style tout en créant une version originale.",
            "taches": [
                _task("Garder", {"éléments": "structure tonale (noirs/blancs), rythme moyen, type de transitions"}),
                _task("Faire varier", {"palette": "décaler la teinte du split-toning de ±10–20°",
                                       "rythme": "±20 % sur la durée des plans",
                                       "texture": "grain et vignettage au goût"}),
                _task("Ne pas copier", {"conseil": "musique, cadrages précis et graphismes doivent rester originaux"}),
            ],
        })

    checklist = [
        "Comparer en split-screen une image clé de la référence et du projet",
        "Vérifier la parade RGB : noirs et blancs aux mêmes niveaux",
        "Durée moyenne des plans dans la tolérance",
        "Loudness conforme à la cible de diffusion",
    ]
    return {"mode": mode, "sections": sections, "checklist": checklist}

# ======================================================================
# AI
# ======================================================================
MODEL = "claude-opus-5-5"

SYSTEM = (
    "Tu es superviseur de post-production (monteur, étalonneur, mixeur). "
    "On te donne des images clés d'une vidéo de référence et les mesures objectives "
    "issues d'une analyse automatique. Rédige en français, pour une équipe de post-production, "
    "une interprétation du style puis des consignes concrètes pour reproduire ce style "
    "sur de nouveaux rushes. Appuie-toi sur les mesures chiffrées ; quand tu déduis quelque chose "
    "des images seules (valeurs de plan, lumière, décors, genre), dis-le. "
    "Ne donne pas de consignes sur des éléments protégés (musique précise, logos) : propose des équivalents."
)

PROMPT = """Mesures de l'analyse automatique (JSON) :
{profile}

Mode demandé : {mode} ({mode_desc}).

Réponds en Markdown avec exactement ces sections :
## Lecture du style
(genre probable, intention, ambiance, références visuelles comparables)
## Cadrage et lumière
(valeurs de plan dominantes, focales apparentes, profondeur de champ, schéma d'éclairage)
## Consignes complémentaires de montage
## Consignes complémentaires d'étalonnage
## Habillage et effets
(titrages, typographie, effets, speed ramps si visibles)
## Points de vigilance
"""


def interpret(profile, stills, mode="identique", effort="high"):
    """Renvoie un texte Markdown, ou lève RuntimeError si l'API n'est pas disponible."""
    try:
        import anthropic
    except ImportError as exc:
        raise RuntimeError("Le paquet `anthropic` n'est pas installé (pip install anthropic).") from exc

    content = []
    for still in stills[:16]:
        with open(still["fichier"], "rb") as fh:
            data = base64.standard_b64encode(fh.read()).decode("ascii")
        content.append({"type": "text", "text": f"Plan {still['plan']} – t={still['t']:.1f}s"})
        content.append({"type": "image", "source": {"type": "base64", "media_type": "image/jpeg", "data": data}})

    slim = {k: v for k, v in profile.items() if k != "audio"}
    if profile.get("audio"):
        slim["audio"] = {k: v for k, v in profile["audio"].items() if k != "energie_par_seconde"}
    mode_desc = ("reproduire le style au plus près" if mode == "identique"
                 else "créer une variante originale d'un style similaire")
    content.append({"type": "text", "text": PROMPT.format(
        profile=json.dumps(slim, ensure_ascii=False, indent=1), mode=mode, mode_desc=mode_desc)})

    client = anthropic.Anthropic()
    try:
        with client.beta.messages.stream(
            model=MODEL,
            max_tokens=32000,
            system=SYSTEM,
            thinking={"type": "adaptive"},
            output_config={"effort": effort},
            betas=["server-side-fallback-2026-07-01"],
            extra_body={"fallbacks": "default"},
            messages=[{"role": "user", "content": content}],
        ) as stream:
            message = stream.get_final_message()
    except anthropic.AuthenticationError as exc:
        raise RuntimeError("Identifiants Anthropic invalides ou absents (ANTHROPIC_API_KEY).") from exc
    except anthropic.RateLimitError as exc:
        raise RuntimeError("Limite de débit de l'API atteinte, réessayer plus tard.") from exc
    except anthropic.APIStatusError as exc:
        raise RuntimeError(f"Erreur API ({exc.status_code}) : {exc.message}") from exc
    except anthropic.APIConnectionError as exc:
        raise RuntimeError("Impossible de joindre l'API Anthropic.") from exc
    except TypeError as exc:
        # Levée par le SDK quand aucune clé API ni profil n'est configuré
        raise RuntimeError("Aucune clé API Anthropic trouvée : définir ANTHROPIC_API_KEY.") from exc

    if message.stop_reason == "refusal":
        raise RuntimeError("La requête d'interprétation a été refusée par le modèle.")
    return "".join(b.text for b in message.content if b.type == "text").strip()

# ======================================================================
# REPORT
# ======================================================================
def _fmt_time(t):
    m, s = divmod(t, 60)
    return f"{int(m):02d}:{s:05.2f}"


def _params_md(params):
    return "".join(f"\n    - **{k}** : {v}" for k, v in params.items())


def to_markdown(result):
    info, prof, order = result["info"], result["profil"], result["ordre"]
    look = prof["etalonnage"]
    lines = [
        f"# Ordre de travail post-production — style « {result['nom']} »",
        "",
        f"*Référence analysée* : `{info['fichier']}` · {info['duree']:.1f} s · "
        f"{info['largeur']}x{info['hauteur']} · {info['fps']:g} i/s · mode **{order['mode']}**",
        "",
        "## Synthèse du style",
        "",
        f"- **Montage** : {prof['rythme']['style']} — plan moyen {prof['rythme']['duree_moyenne_plan']} s, "
        f"{prof['rythme']['coupes_par_minute']} coupes/min, {prof['rythme']['regularite']}",
        f"- **Transitions** : {prof['transitions']['dominante'].replace('_', ' ')}",
    ]
    if prof["camera"]:
        lines.append(f"- **Caméra** : dominante {prof['camera']['dominante']}")
    lines.append(f"- **Look** : {', '.join(look['tags']) or 'neutre'}")
    if prof["texture"]["tags"]:
        lines.append(f"- **Texture** : {', '.join(prof['texture']['tags'])}")
    au = prof.get("audio")
    if au:
        bpm = f"{au['bpm']:.0f} BPM, " if au.get("bpm") else ""
        lines.append(f"- **Son** : {bpm}{au.get('lufs_integre')} LUFS intégré")
    lines += ["", "**Palette dominante** : " + " ".join(f"`{c['hex']}` ({c['part'] * 100:.0f} %)" for c in result["palette"]), ""]

    for sec in order["sections"]:
        lines += [f"## {sec['titre']}", "", f"_Objectif : {sec['objectif']}_", ""]
        for t in sec["taches"]:
            outil = f" — *{t['outil']}*" if t.get("outil") else ""
            lines.append(f"- [ ] {t['action']}{outil}{_params_md(t.get('parametres', {}))}")
        lines.append("")

    if result.get("interpretation_ia"):
        lines += ["## Interprétation IA (images clés)", "", result["interpretation_ia"], ""]

    lines += ["## Liste des plans", "", "| # | Début | Durée | Caméra | Luminance | Saturation |",
              "|---|---|---|---|---|---|"]
    for s in result["plans"]:
        lines.append(f"| {s['numero']} | {_fmt_time(s['debut'])} | {s['duree']:.2f} s | {s['camera']} | "
                     f"{s['luma']:.2f} | {s['saturation']:.2f} |")
    lines += ["", "## Checklist de validation", ""] + [f"- [ ] {c}" for c in order["checklist"]]
    lines += ["", "## Fichiers livrés", "", f"- LUT créative : `{result['lut']}`",
              "- Données brutes : `analyse.json`", "- Rapport visuel : `rapport.html`", ""]
    return "\n".join(lines)


CSS = """
:root{--bg:#f7f6f3;--fg:#1c1c1c;--muted:#6b6b6b;--card:#fff;--line:#e3e1dc;--accent:#c4552d}
@media (prefers-color-scheme:dark){:root:not([data-theme=light]){--bg:#141414;--fg:#ececec;--muted:#9a9a9a;--card:#1e1e1e;--line:#333;--accent:#e9825a}}
:root[data-theme=dark]{--bg:#141414;--fg:#ececec;--muted:#9a9a9a;--card:#1e1e1e;--line:#333;--accent:#e9825a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:15px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
main{max-width:1100px;margin:auto;padding:24px 16px 64px}h1{font-size:1.7rem;margin:.2em 0}h2{margin-top:2em;border-bottom:1px solid var(--line);padding-bottom:.3em}
.muted{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.card{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px}
.card b{display:block;font-size:.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.swatches{display:flex;height:56px;border-radius:8px;overflow:hidden;border:1px solid var(--line)}
.timeline{display:flex;height:34px;border-radius:6px;overflow:hidden;border:1px solid var(--line)}
.timeline div{border-right:1px solid var(--bg)}
.stills{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px}
.stills figure{margin:0}.stills img{width:100%;border-radius:6px;display:block}.stills figcaption{font-size:.75rem;color:var(--muted)}
ul.tasks{list-style:none;padding:0}ul.tasks>li{background:var(--card);border:1px solid var(--line);border-left:3px solid var(--accent);border-radius:8px;padding:10px 12px;margin:8px 0}
ul.tasks ul{margin:.4em 0 0;padding-left:1.1em;font-size:.92rem}.tool{font-size:.82rem;color:var(--muted)}
table{border-collapse:collapse;width:100%;font-size:.88rem}td,th{border-bottom:1px solid var(--line);padding:5px 6px;text-align:left}
.tablewrap{overflow-x:auto}.tag{display:inline-block;background:var(--card);border:1px solid var(--line);border-radius:99px;padding:2px 10px;margin:2px;font-size:.85rem}
pre.ai{white-space:pre-wrap;background:var(--card);border:1px solid var(--line);border-radius:8px;padding:14px;font:inherit}
svg{width:100%;height:auto}
"""


def _img_tag(path):
    with open(path, "rb") as fh:
        return "data:image/jpeg;base64," + base64.b64encode(fh.read()).decode("ascii")


def _luma_svg(curve, duration):
    if len(curve) < 2:
        return ""
    w, h = 1000, 120
    pts = " ".join(f"{t / max(duration, 1e-6) * w:.1f},{h - v * h:.1f}" for t, v in curve)
    return (f'<svg viewBox="0 0 {w} {h}" preserveAspectRatio="none" role="img" aria-label="Luminance dans le temps">'
            f'<polyline fill="none" stroke="currentColor" stroke-width="1.5" points="{pts}"/></svg>')


def to_html(result):
    e = html.escape
    info, prof, order = result["info"], result["profil"], result["ordre"]
    look, rh = prof["etalonnage"], prof["rythme"]
    dur = info["duree"] or 1

    cards = [
        ("Montage", f"{rh['style']}<br>plan moyen {rh['duree_moyenne_plan']} s · {rh['coupes_par_minute']} coupes/min"),
        ("Format", f"{prof['format']['resolution']} · {prof['format']['ratio_image_active']}<br>{prof['format']['cadence']}"),
        ("Caméra", e(prof["camera"].get("dominante", "—"))),
        ("Étalonnage", f"noir {look['point_noir']} · médian {look['median']} · blanc {look['point_blanc']}<br>saturation {look['saturation']}"),
    ]
    if prof.get("audio"):
        au = prof["audio"]
        cards.append(("Son", f"{au.get('lufs_integre')} LUFS · " + (f"{au['bpm']:.0f} BPM" if au.get("bpm") else "tempo non détecté")))

    out = [f"<!doctype html><html lang='fr'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>"
           f"<title>Ordre de travail</title><style>{CSS}</style></head><body><main>",
           f"<p class='muted'>Ordre de travail post-production · mode {e(order['mode'])}</p>",
           f"<h1>Style « {e(result['nom'])} »</h1>",
           f"<p class='muted'>{e(info['fichier'])} · {info['duree']:.1f} s · {info['largeur']}x{info['hauteur']} · {info['fps']:g} i/s</p>",
           "<div class='grid'>" + "".join(f"<div class='card'><b>{k}</b>{v}</div>" for k, v in cards) + "</div>",
           "<h2>Look</h2><div>" + "".join(f"<span class='tag'>{e(t)}</span>" for t in look["tags"] + prof["texture"]["tags"]) + "</div>",
           "<h3>Palette</h3><div class='swatches'>" + "".join(
               f"<div title='{c['hex']}' style='flex:{c['part']};background:{c['hex']}'></div>" for c in result["palette"]) + "</div>",
           "<p class='muted'>" + " · ".join(f"{c['hex']} {c['part'] * 100:.0f} %" for c in result["palette"]) + "</p>",
           "<h3>Découpage (couleur moyenne de chaque plan)</h3><div class='timeline'>" + "".join(
               "<div title='Plan {n} · {d:.2f}s' style='flex:{d};background:rgb({r},{g},{b})'></div>".format(
                   n=s["numero"], d=s["duree"], r=int(s["rgb"][0] * 255), g=int(s["rgb"][1] * 255), b=int(s["rgb"][2] * 255))
               for s in result["plans"]) + "</div>",
           "<h3>Luminance dans le temps</h3>" + _luma_svg(result["courbe_luma"], dur)]

    if result["images_cles"]:
        out.append("<h2>Images clés</h2><div class='stills'>" + "".join(
            f"<figure><img loading='lazy' src='{_img_tag(s['fichier'])}' alt='Plan {s['plan']}'>"
            f"<figcaption>Plan {s['plan']} · {_fmt_time(s['t'])}</figcaption></figure>" for s in result["images_cles"]) + "</div>")

    for sec in order["sections"]:
        out.append(f"<h2>{e(sec['titre'])}</h2><p class='muted'>{e(sec['objectif'])}</p><ul class='tasks'>")
        for t in sec["taches"]:
            params = "".join(f"<li><b>{e(str(k))}</b> : {e(str(v))}</li>" for k, v in t.get("parametres", {}).items())
            tool = f"<div class='tool'>{e(t['outil'])}</div>" if t.get("outil") else ""
            out.append(f"<li><label><input type='checkbox'> {e(t['action'])}</label>{tool}{'<ul>' + params + '</ul>' if params else ''}</li>")
        out.append("</ul>")

    if result.get("interpretation_ia"):
        out.append(f"<h2>Interprétation IA</h2><pre class='ai'>{e(result['interpretation_ia'])}</pre>")

    out.append("<h2>Plans</h2><div class='tablewrap'><table><tr><th>#</th><th>Début</th><th>Durée</th><th>Caméra</th><th>Lum.</th><th>Sat.</th></tr>")
    for s in result["plans"]:
        out.append(f"<tr><td>{s['numero']}</td><td>{_fmt_time(s['debut'])}</td><td>{s['duree']:.2f} s</td>"
                   f"<td>{e(s['camera'])}</td><td>{s['luma']:.2f}</td><td>{s['saturation']:.2f}</td></tr>")
    out.append("</table></div><h2>Checklist</h2><ul class='tasks'>" + "".join(
        f"<li><label><input type='checkbox'> {e(c)}</label></li>" for c in order["checklist"]) + "</ul>")
    out.append("</main></body></html>")
    return "\n".join(out)


def to_json(result):
    return json.dumps(result, ensure_ascii=False, indent=2)

# ======================================================================
# PIPELINE
# ======================================================================
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
        audio = audio_analyze(path, [s["debut"] for s in shots[1:]])

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

# ======================================================================
# __MAIN__
# ======================================================================
def cmd_analyser(args):
    result = analyze_video(args.video, args.sortie, args.mode, args.nom,
                           with_audio=not args.sans_audio, verbose=not args.silencieux)
    if args.ia:
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
