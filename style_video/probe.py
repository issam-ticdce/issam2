"""Lecture des métadonnées techniques via ffprobe."""

import json
import subprocess
from fractions import Fraction


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
