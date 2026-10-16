"""Interprétation des mesures en profil de style et en ordre de travail."""

import numpy as np

from .color import resolve_wheels

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
