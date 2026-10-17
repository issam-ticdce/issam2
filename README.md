# style_video — analyse de style vidéo → ordre de travail post-production

Analyse une vidéo de référence (montage, caméra, étalonnage, texture, son) et génère
un **ordre de travail** pour reproduire le même style (`--mode identique`) ou un style
similaire original (`--mode similaire`) sur de nouveaux rushes.

## Prérequis
- `ffmpeg` / `ffprobe`
- Python 3.10+, `numpy` (`pip install -r requirements.txt`)
- Optionnel : `anthropic` + clé API pour l'interprétation IA des images clés (`--ia`)

## Version fichier unique

`analyse_style_video.py` contient tout le code dans un seul fichier :
```bash
python analyse_style_video.py analyser reference.mp4 -o sortie/
```

## Utilisation
```bash
# Analyser une référence
python -m style_video analyser reference.mp4 -o sortie/ --nom "clip_x"
python -m style_video analyser reference.mp4 -o sortie/ --mode similaire --ia

# Vérifier qu'un montage respecte le style
python -m style_video comparer reference.mp4 mon_montage.mp4
```

## Fichiers générés
| Fichier | Contenu |
|---|---|
| `ordre_de_travail.md` | consignes par poste : projet, montage, caméra, étalonnage, texture, son, export, checklist |
| `rapport.html` | rapport visuel autonome (palette, découpage, images clés, tâches à cocher) |
| `look_<nom>.cube` | LUT 3D créative (33³) reproduisant courbe tonale, saturation et split-toning |
| `analyse.json` | toutes les mesures brutes |
| `images_cles/` | une image par plan |

## Ce qui est mesuré
- **Montage** : coupes franches (seuil adaptatif), fondus au noir/blanc, fondus enchaînés, durée moyenne des plans, coupes/min, régularité, accélération.
- **Caméra** : corrélation de phase → fixe, panoramique, inclinaison, caméra épaule.
- **Étalonnage** : points noir/blanc, médian, contraste, richesse colorimétrique, température, dominantes ombres/tons moyens/hautes lumières (teal & orange…), palette (k-means), letterbox.
- **Texture** : grain (Immerkær, pleine résolution), netteté, vignettage.
- **Son** : loudness EBU R128, tempo (BPM), synchronisation des coupes sur le rythme.

Les seuils d'interprétation sont indicatifs : validez toujours à l'œil et aux scopes.
