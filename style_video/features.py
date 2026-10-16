"""Mesures image par image : couleur, contraste, texture, mouvement, coupes."""

import numpy as np

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
