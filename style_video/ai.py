"""Interprétation optionnelle par Claude (vision) des images clés + mesures."""

import base64
import json

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
