"""The promo's soundtrack, synthesised from scratch (no samples, nothing licensed): a warm, sweet 88 BPM
I-vi-IV-V in F with soft electric-piano chords, a music-box melody, a gentle pad, and from the third scene a
soft bass and light drums; plus effects on the video's cues (pops, sparkles, paper, page turns, chimes).
`soundtrack(cues, duration, groove)` returns float32 stereo samples at RATE; `with_voice` lays Lettie's lines on top.
Adapted from the Dink promo's music.py (same mixing and ducking rules).
"""

from __future__ import annotations

import numpy as np
from scipy.signal import butter, sosfilt

RATE = 48_000
BPM = 88
BEAT = 60 / BPM
BAR = 4 * BEAT
# F - Dm - Bb - C, one chord per bar: (chord voicing, bass note)
CHORDS = [([65, 69, 72], 41), ([62, 65, 69], 38), ([58, 62, 65], 34), ([60, 64, 67], 36)]
# the music box: (beat in the bar, midi note) per chord, a little tune that rises and settles
MELODY = [
    [(0, 84), (1, 81), (2, 79), (3, 81)],
    [(0, 77), (1.5, 81), (2, 79), (3, 77)],
    [(0, 77), (1, 79), (2, 81), (3, 82)],
    [(0, 84), (1.5, 81), (2.5, 79), (3, 76)],
]

rng = np.random.default_rng(11)  # re-seeded by soundtrack(): the same audio on every render


def hz(midi: float) -> float:
    return 440.0 * 2 ** ((midi - 69) / 12)


def env(n: int, attack: float, decay: float) -> np.ndarray:
    t = np.arange(n) / RATE
    return np.clip(t / max(attack, 1e-4), 0, 1) * np.exp(-t / decay)


def filt(x: np.ndarray, kind: str, freq, order: int = 2) -> np.ndarray:
    return sosfilt(butter(order, freq, btype=kind, fs=RATE, output="sos"), x)


def add(track: np.ndarray, sound: np.ndarray, at: float, gain: float = 1.0, pan: float = 0.0):
    """Mix a mono `sound` into stereo `track` at time `at` (pan -1 left .. +1 right)."""
    start = int(round(at * RATE))
    if start >= len(track) or start < 0:
        return
    end = min(len(track), start + len(sound))
    seg = sound[: end - start] * gain
    left, right = np.cos((pan + 1) * np.pi / 4), np.sin((pan + 1) * np.pi / 4)
    track[start:end, 0] += seg * left * np.sqrt(2)
    track[start:end, 1] += seg * right * np.sqrt(2)


# ── instruments ──────────────────────────────────────────────────────────────


def keys(midi: int, length: float = 2.4) -> np.ndarray:
    """A soft electric piano: a sine with a little bell in the attack."""
    n = int(length * RATE)
    t = np.arange(n) / RATE
    f = hz(midi)
    bell = 0.9 * np.exp(-t / 0.25)
    tone = np.sin(2 * np.pi * f * t + bell * np.sin(2 * np.pi * 2 * f * t))
    tone += 0.18 * np.sin(2 * np.pi * 3 * f * t) * np.exp(-t / 0.4)
    shape = env(n, 0.006, 1.1) * np.clip((length - t) / 0.3, 0, 1)
    return tone * shape


def music_box(midi: int, length: float = 1.6) -> np.ndarray:
    n = int(length * RATE)
    t = np.arange(n) / RATE
    f = hz(midi)
    tone = np.sin(2 * np.pi * f * t) + 0.35 * np.sin(2 * np.pi * 2.76 * f * t) * np.exp(-t / 0.12)
    tone += 0.2 * np.sin(2 * np.pi * 5.4 * f * t) * np.exp(-t / 0.05)
    return tone * env(n, 0.002, 0.55)


def pad(notes: list[int], length: float) -> np.ndarray:
    n = int(length * RATE)
    t = np.arange(n) / RATE
    out = np.zeros(n)
    for m in notes:
        for cents in (-7, 0, 7):
            f = hz(m - 12) * 2 ** (cents / 1200)
            phase = rng.uniform(0, 1)
            out += 2 * np.abs(2 * ((f * t + phase) % 1.0) - 1) - 1  # triangle: softer than a saw
    out = filt(out, "lowpass", 900, 2)
    shape = np.clip(t / 0.8, 0, 1) * np.clip((length - t) / 0.8, 0, 1)
    return out * shape / (len(notes) * 3)


def bass(midi: int, length: float = 1.2) -> np.ndarray:
    n = int(length * RATE)
    t = np.arange(n) / RATE
    f = hz(midi)
    tone = np.sin(2 * np.pi * f * t) + 0.25 * np.sin(2 * np.pi * 2 * f * t)
    return filt(tone, "lowpass", 400, 2) * env(n, 0.02, 0.5)


def kick() -> np.ndarray:
    n = int(0.35 * RATE)
    t = np.arange(n) / RATE
    f = 52 + 70 * np.exp(-t / 0.03)
    return np.sin(2 * np.pi * np.cumsum(f) / RATE) * np.exp(-t / 0.16)


def snap() -> np.ndarray:
    """A finger snap / soft rim."""
    n = int(0.12 * RATE)
    t = np.arange(n) / RATE
    noise = filt(rng.standard_normal(n), "bandpass", [1800, 5200])
    return noise * np.exp(-t / 0.018) * 0.7


def shaker() -> np.ndarray:
    n = int(0.08 * RATE)
    t = np.arange(n) / RATE
    noise = filt(rng.standard_normal(n), "highpass", 6500)
    return noise * np.clip(t / 0.01, 0, 1) * np.exp(-t / 0.025)


# ── sound effects ────────────────────────────────────────────────────────────


def pop() -> np.ndarray:
    """A bubble pop (Lettie appearing, a card landing)."""
    n = int(0.16 * RATE)
    t = np.arange(n) / RATE
    f = 380 + 900 * (t / 0.16) ** 0.5
    return np.sin(2 * np.pi * np.cumsum(f) / RATE) * np.exp(-t / 0.05) * 0.8


def sparkle() -> np.ndarray:
    """Five bell notes rising fast (a reveal)."""
    out = np.zeros(int(1.4 * RATE))
    for k, m in enumerate([84, 88, 91, 96, 100]):
        note = music_box(m, 1.0) * (0.7 - 0.08 * k)
        start = int(k * 0.06 * RATE)
        out[start : start + len(note)] += note[: len(out) - start]
    return out * 0.6


def chime() -> np.ndarray:
    out = np.zeros(int(1.8 * RATE))
    for k, m in enumerate([79, 84]):
        note = music_box(m, 1.6)
        start = int(k * 0.14 * RATE)
        out[start : start + len(note)] += note[: len(out) - start]
    return out * 0.6


def whoosh(length: float = 0.5) -> np.ndarray:
    n = int(length * RATE)
    t = np.arange(n) / RATE
    noise = rng.standard_normal(n)
    sweep = t / length
    soft = filt(noise, "bandpass", [400, 2600]) * (1 - sweep) + filt(noise, "bandpass", [2600, 7000]) * sweep
    return soft * np.sin(np.pi * sweep) ** 2 * 0.45


def paper() -> np.ndarray:
    """Paper sliding out of an envelope."""
    n = int(0.55 * RATE)
    t = np.arange(n) / RATE
    grain = filt(rng.standard_normal(n), "bandpass", [1200, 7000])
    flutter = 0.6 + 0.4 * np.sin(2 * np.pi * 23 * t) ** 2
    return grain * flutter * np.sin(np.pi * t / 0.55) * 0.5


def page() -> np.ndarray:
    """A page turning: a short swish with a soft flap at the end."""
    n = int(0.42 * RATE)
    t = np.arange(n) / RATE
    swish = filt(rng.standard_normal(n), "bandpass", [700, 5000]) * np.sin(np.pi * t / 0.42) ** 2
    flap = filt(rng.standard_normal(n), "lowpass", 900) * np.exp(-np.clip(t - 0.33, 0, None) / 0.03) * (t > 0.33)
    return swish * 0.45 + flap * 0.6


def tap() -> np.ndarray:
    n = int(0.05 * RATE)
    t = np.arange(n) / RATE
    return np.sin(2 * np.pi * 1900 * t) * np.exp(-t / 0.008) * 0.5


SFX = {"pop": pop, "sparkle": sparkle, "chime": chime, "whoosh": whoosh, "paper": paper, "page": page, "tap": tap}


# ── arrangement ──────────────────────────────────────────────────────────────


def soundtrack(cues: list[dict], duration: float, groove: tuple[float, float]) -> np.ndarray:
    """groove: (from, to) seconds with bass and drums; before it keys and the music box only, after it the outro."""
    global rng
    rng = np.random.default_rng(11)
    n = int(round(duration * RATE))
    music = np.zeros((n, 2))
    groove_from, groove_to = groove
    bars = int(np.ceil(duration / BAR))
    for bar in range(bars):
        notes, root = CHORDS[bar % len(CHORDS)]
        t0 = bar * BAR
        if t0 >= duration - 0.5:
            break
        outro = t0 >= groove_to
        add(music, pad(notes, BAR + 0.8), t0, 0.5 if not outro else 0.65)
        # the piano: the chord on 1, its notes broken on the off-beats
        for k, m in enumerate(notes):
            add(music, keys(m, BAR * 0.9), t0 + k * 0.012, 0.16, pan=(k - 1) * 0.3)
        if not outro:
            for eighth in (1, 3, 5, 7):
                at = t0 + eighth * BEAT / 2
                add(music, keys(notes[(eighth // 2) % 3] + 12, 0.9), at, 0.075, pan=0.4 if eighth % 4 == 1 else -0.4)
        # the music box, from the second scene
        if t0 >= 3.2 and not outro:
            for beat, m in MELODY[bar % len(MELODY)]:
                add(music, music_box(m), t0 + beat * BEAT, 0.12, pan=0.15)
        # bass and drums in the middle
        for beat in range(4):
            at = t0 + beat * BEAT
            if not (groove_from <= at < groove_to):
                continue
            if beat in (0, 2):
                add(music, bass(root if beat == 0 else root + 7), at, 0.5)
                add(music, kick(), at, 0.55)
            else:
                add(music, snap(), at, 0.32, pan=0.1)
            for half in (0, 1):
                add(music, shaker(), at + half * BEAT / 2, 0.12 if half else 0.07, pan=0.35)
    # the outro: the first chord again, held, and the tune's last two notes
    tail = groove_to
    add(music, pad(CHORDS[0][0] + [77], duration - tail + 0.2), tail, 0.6)
    for k, m in enumerate([81, 84]):
        add(music, music_box(m, 2.2), tail + 0.4 + k * BEAT, 0.14)
    for m in CHORDS[0][0]:
        add(music, keys(m, 3.5), tail, 0.15)
    fade_in = np.clip(np.arange(n) / RATE / 0.6, 0, 1)
    fade_out = np.clip((duration - np.arange(n) / RATE) / 1.4, 0, 1)
    music *= (fade_in * fade_out)[:, None]

    effects = np.zeros((n, 2))
    for c in cues:
        add(effects, SFX[c["kind"]](), c["t"], 0.85 * c.get("gain", 1.0), pan=c.get("pan", 0.0))

    mix = music * 0.9 + effects
    mix = np.tanh(mix * 1.05) / np.tanh(1.05)
    peak = np.max(np.abs(mix)) or 1.0
    return (mix / peak * 0.89).astype(np.float32)


# ── narration (same rules as the Dink promo) ─────────────────────────────────

VOICE_RMS_DB = -16.0  # every line levelled to the same loudness
BED_DB = -6.0  # music + effects, overall: a little under the voice, so it stays a sweet, voice-led spot
DUCK_DB = 10.0  # extra dip of the bed while Lettie speaks


def with_voice(bed: np.ndarray, lines: list[tuple[float, np.ndarray]]) -> tuple[np.ndarray, float]:
    """Mix narration (mono lines at RATE, placed at their start times) over the stereo `bed`, ducking the bed under
    speech. Returns the mix and how far the voice sits above the ducked bed while speaking (dB, RMS)."""
    n = len(bed)
    voice = np.zeros(n)
    speaking = np.zeros(n)
    for at, x in lines:
        x = filt(x.astype(np.float64), "highpass", 90)
        x *= 10 ** (VOICE_RMS_DB / 20) / (np.sqrt(np.mean(x**2)) or 1.0)
        start = int(round(at * RATE))
        end = min(n, start + len(x))
        voice[start:end] += x[: end - start]
        speaking[max(0, start - int(0.08 * RATE)) : min(n, end + int(0.2 * RATE))] = 1.0
    window = np.hanning(int(0.25 * RATE))
    window /= window.sum()
    duck = np.clip(np.convolve(speaking, window, mode="same"), 0, 1)
    bed = bed * (10 ** ((BED_DB - DUCK_DB * duck) / 20))[:, None]
    talk = speaking > 0.5
    ratio = 20 * np.log10(
        (np.sqrt(np.mean(voice[talk] ** 2)) or 1e-9) / (np.sqrt(np.mean(bed[talk] ** 2)) or 1e-9)
    )
    mix = bed + voice[:, None]
    mix = np.tanh(mix * 1.05) / np.tanh(1.05)
    peak = np.max(np.abs(mix)) or 1.0
    return (mix / peak * 0.89).astype(np.float32), float(ratio)
