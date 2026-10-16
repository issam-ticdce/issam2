"""Rendu de l'ordre de travail : Markdown, HTML autonome et JSON."""

import base64
import html
import json


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
