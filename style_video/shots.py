"""Détection des plans, transitions et mouvements de caméra."""

import numpy as np

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
