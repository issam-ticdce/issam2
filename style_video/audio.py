"""Analyse audio : loudness EBU R128, tempo, synchronisation des coupes sur le rythme."""

import re
import subprocess

import numpy as np

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


def analyze(path, cut_times):
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
