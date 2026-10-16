"""Décodage des images réduites (flux continu, sans tout charger en mémoire)."""

import subprocess

import numpy as np

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
