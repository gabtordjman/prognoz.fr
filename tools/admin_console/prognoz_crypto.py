from __future__ import annotations

import base64

from cryptography.hazmat.primitives.ciphers.aead import AESGCM

ENC_PREFIX = "enc1:"


def encryption_key_from_env(raw: str) -> bytes:
    if not raw:
        raise ValueError("APP_ENCRYPTION_KEY manquant.")
    key = base64.b64decode(raw, validate=True)
    if len(key) != 32:
        raise ValueError("APP_ENCRYPTION_KEY invalide.")
    return key


def decrypt_sensitive(stored: str | None, key: bytes) -> str | None:
    if stored is None or stored == "":
        return stored
    if not stored.startswith(ENC_PREFIX):
        return stored

    payload = base64.b64decode(stored[len(ENC_PREFIX) :], validate=True)
    if len(payload) < 28:
        return "[contenu illisible]"

    iv = payload[:12]
    tag = payload[12:28]
    cipher = payload[28:]

    try:
        return AESGCM(key).decrypt(iv, cipher + tag, None).decode("utf-8")
    except Exception:
        return "[contenu illisible]"
