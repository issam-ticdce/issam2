"""Analyse de l'étalonnage : palette, courbe tonale, dominantes et génération de LUT."""

import colorsys

import numpy as np

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
