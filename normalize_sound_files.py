#!/usr/bin/env python3
"""
Normalize loudness of audio files in a folder to match a model sound file (default: darkmenace.wav).
Uses LUFS (ITU-R BS.1770-4). Creates normalized_volume inside the source folder and writes there.

Usage: normalize_sound_files.py <source_folder> [model_sound_file]

Dependencies: pip install -r requirements_normalize.txt
For MP3 support, ffmpeg must be installed on the system.
"""

import argparse
import os
import sys

import numpy as np
from pydub import AudioSegment
import pyloudnorm as pyln

# Extensions to process (pydub can read/write these with ffmpeg)
AUDIO_EXTENSIONS = {".wav", ".mp3", ".flac", ".ogg", ".m4a", ".aac"}
REFERENCE_DEFAULT = "darkmenace.wav"


def get_audio_array_and_rate(path: str):
    """Load audio file with pydub, return (samples_float, rate). samples shape (N, channels)."""
    seg = AudioSegment.from_file(path)
    rate = seg.frame_rate
    channels = seg.channels
    samples = np.array(seg.get_array_of_samples(), dtype=np.float32) / 32768.0
    if channels == 2:
        samples = samples.reshape(-1, 2)
    else:
        samples = samples.reshape(-1, 1)
    return samples, rate, channels


def save_audio_from_array(path: str, data: np.ndarray, rate: int, channels: int, format_hint: str = None):
    """Save float array (-1..1) to file. Format inferred from path or format_hint."""
    data = np.clip(data, -1.0, 1.0)
    int16 = (data * 32767).astype(np.int16)
    interleaved = int16.flatten()
    seg = AudioSegment(
        data=interleaved.tobytes(),
        sample_width=2,
        frame_rate=rate,
        channels=channels,
    )
    fmt = format_hint or (os.path.splitext(path)[1][1:] or "wav").lower()
    if fmt == "mp3":
        seg.export(path, format="mp3", bitrate="192k")
    else:
        seg.export(path, format=fmt)


def get_lufs(data: np.ndarray, rate: int) -> float:
    """Return integrated LUFS. Handles silent/very quiet by returning a safe value."""
    meter = pyln.Meter(rate)
    try:
        return meter.integrated_loudness(data)
    except Exception:
        return -60.0  # silent or too short


def normalize_file(
    src_path: str,
    out_path: str,
    target_lufs: float,
) -> None:
    """Normalize one file to target_lufs and write to out_path."""
    data, rate, channels = get_audio_array_and_rate(src_path)
    current_lufs = get_lufs(data, rate)
    normalized = pyln.normalize.loudness(data, current_lufs, target_lufs)
    ext = os.path.splitext(out_path)[1][1:].lower() or "wav"
    save_audio_from_array(out_path, normalized, rate, channels, ext)


def main():
    parser = argparse.ArgumentParser(
        description="Normalize audio files to match a reference file's loudness (LUFS)."
    )
    parser.add_argument("source_folder", help="Folder containing audio files; normalized_volume will be created inside it")
    parser.add_argument(
        "model_sound_file",
        nargs="?",
        default=REFERENCE_DEFAULT,
        metavar="model_sound_file",
        help=f"Optional. Audio file to use as volume target (default: {REFERENCE_DEFAULT}). Filename in source folder, or path.",
    )
    args = parser.parse_args()

    source = os.path.abspath(args.source_folder)
    output_dir = os.path.join(source, "normalized_volume")

    if not os.path.isdir(source):
        print(f"Error: source folder not found: {source}", file=sys.stderr)
        sys.exit(1)

    model = args.model_sound_file
    ref_path = os.path.abspath(model) if os.path.isabs(model) or os.path.sep in model else os.path.join(source, model)
    if not os.path.isfile(ref_path):
        print(f"Error: reference file not found: {ref_path}", file=sys.stderr)
        sys.exit(1)

    ref_data, ref_rate, _ = get_audio_array_and_rate(ref_path)
    target_lufs = get_lufs(ref_data, ref_rate)
    if target_lufs < -59:
        print("Warning: reference file is very quiet or silent; target LUFS may be unreliable.", file=sys.stderr)
    print(f"Model: {args.model_sound_file} -> target LUFS: {target_lufs:.1f}")
    print(f"Output folder: {output_dir}")

    os.makedirs(output_dir, exist_ok=True)

    processed = 0
    for name in sorted(os.listdir(source)):
        base, ext = os.path.splitext(name)
        if ext.lower() not in AUDIO_EXTENSIONS:
            continue
        src_path = os.path.join(source, name)
        if not os.path.isfile(src_path):
            continue
        out_path = os.path.join(output_dir, name)
        try:
            normalize_file(src_path, out_path, target_lufs)
            print(f"  {name}")
            processed += 1
        except Exception as e:
            print(f"  Skip {name}: {e}", file=sys.stderr)

    print(f"Done. Normalized {processed} file(s).")


if __name__ == "__main__":
    main()
