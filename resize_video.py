#!/usr/bin/env python3
"""
Re-encode videos in a folder to target a specific file size (in KB), keeping quality as high as possible.
Uses ffmpeg (H.264 + AAC). Creates a "resized" folder inside the source folder for output.

Usage: resize_video.py <source_folder> [target_size_kb]

Requires ffmpeg. Looks on PATH, then FFMPEG_DIR env, then common Windows install locations.
"""

import argparse
import os
import subprocess
import sys

VIDEO_EXTENSIONS = {".mp4", ".mov", ".avi", ".mkv", ".webm", ".m4v", ".flv"}
DEFAULT_SIZE_KB = 4000
# Leave headroom for container/metadata so we don't exceed target
SIZE_MARGIN = 0.95
AUDIO_BITRATE_K = 128

# Resolved at startup; use find_ffmpeg_bin() before calling get_duration_seconds/resize_video
_ffprobe = "ffprobe"
_ffmpeg = "ffmpeg"


def find_ffmpeg_bin() -> bool:
    """Set _ffprobe/_ffmpeg to full paths if found; return True if both found."""
    global _ffprobe, _ffmpeg
    exe = ".exe" if sys.platform == "win32" else ""

    def has_both(directory: str) -> bool:
        p = os.path.join(directory, "ffprobe" + exe)
        m = os.path.join(directory, "ffmpeg" + exe)
        return os.path.isfile(p) and os.path.isfile(m)

    # 1) Already on PATH
    try:
        subprocess.run(["ffprobe", "-version"], capture_output=True, check=True)
        subprocess.run(["ffmpeg", "-version"], capture_output=True, check=True)
        _ffprobe, _ffmpeg = "ffprobe", "ffmpeg"
        return True
    except (FileNotFoundError, subprocess.CalledProcessError):
        pass

    # 2) FFMPEG_DIR environment variable
    env_dir = os.environ.get("FFMPEG_DIR")
    if env_dir and has_both(env_dir):
        _ffprobe = os.path.join(env_dir, "ffprobe" + exe)
        _ffmpeg = os.path.join(env_dir, "ffmpeg" + exe)
        return True

    # 3) Common Windows locations
    if sys.platform == "win32":
        for candidate in [
            os.path.join(os.environ.get("ProgramFiles", "C:\\Program Files"), "ffmpeg", "bin"),
            os.path.join(os.environ.get("ProgramFiles(x86)", "C:\\Program Files (x86)"), "ffmpeg", "bin"),
            "C:\\ffmpeg\\bin",
            os.path.expanduser("~\\ffmpeg\\bin"),
        ]:
            if os.path.isdir(candidate) and has_both(candidate):
                _ffprobe = os.path.join(candidate, "ffprobe" + exe)
                _ffmpeg = os.path.join(candidate, "ffmpeg" + exe)
                return True

    return False


def get_duration_seconds(path: str) -> float:
    """Return duration in seconds via ffprobe."""
    cmd = [
        _ffprobe,
        "-v", "error",
        "-show_entries", "format=duration",
        "-of", "default=noprint_wrappers=1:nokey=1",
        path,
    ]
    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return float(result.stdout.strip())


def get_video_bitrate_k(path: str, target_size_kb: int, duration_sec: float) -> int:
    """Compute video bitrate in kbps so total output ≈ target_size_kb (with audio)."""
    target_bits = target_size_kb * 1024 * 8
    # Reserve for audio and margin
    audio_bits = AUDIO_BITRATE_K * 1000 * duration_sec
    video_bits = (target_bits * SIZE_MARGIN) - audio_bits
    if video_bits <= 0:
        video_bits = 50 * 1000 * duration_sec  # min 50 kbps
    video_kbps = int(video_bits / duration_sec / 1000)
    return max(50, video_kbps)


def resize_video(src_path: str, out_path: str, target_size_kb: int) -> None:
    """Re-encode one video to target file size."""
    duration_sec = get_duration_seconds(src_path)
    video_k = get_video_bitrate_k(src_path, target_size_kb, duration_sec)

    cmd = [
        _ffmpeg,
        "-y",
        "-i", src_path,
        "-c:v", "libx264",
        "-preset", "medium",
        "-b:v", f"{video_k}k",
        "-maxrate", f"{int(video_k * 1.2)}k",
        "-bufsize", f"{int(video_k * 2)}k",
        "-c:a", "aac",
        "-b:a", f"{AUDIO_BITRATE_K}k",
        "-movflags", "+faststart",
        out_path,
    ]
    subprocess.run(cmd, check=True, capture_output=True)


def main():
    parser = argparse.ArgumentParser(
        description="Re-encode videos to target file size (KB). Output in source_folder/resized/."
    )
    parser.add_argument("source_folder", help="Folder containing video files")
    parser.add_argument(
        "target_size_kb",
        nargs="?",
        default=DEFAULT_SIZE_KB,
        type=int,
        metavar="target_size_kb",
        help=f"Target file size in KB (default: {DEFAULT_SIZE_KB})",
    )
    args = parser.parse_args()

    source = os.path.abspath(args.source_folder)
    output_dir = os.path.join(source, "resized")
    target_kb = args.target_size_kb

    if not os.path.isdir(source):
        print(f"Error: source folder not found: {source}", file=sys.stderr)
        sys.exit(1)

    if not find_ffmpeg_bin():
        print("Error: ffprobe/ffmpeg not found.", file=sys.stderr)
        print("  Install ffmpeg, then either:", file=sys.stderr)
        print("  - Add its bin folder to PATH, or", file=sys.stderr)
        print("  - Set FFMPEG_DIR to that bin folder (e.g. set FFMPEG_DIR=C:\\ffmpeg\\bin)", file=sys.stderr)
        sys.exit(1)

    print(f"Target size: {target_kb} KB")
    print(f"Output folder: {output_dir}")
    os.makedirs(output_dir, exist_ok=True)

    processed = 0
    for name in sorted(os.listdir(source)):
        base, ext = os.path.splitext(name)
        if ext.lower() not in VIDEO_EXTENSIONS:
            continue
        src_path = os.path.join(source, name)
        if not os.path.isfile(src_path):
            continue
        out_path = os.path.join(output_dir, name)
        try:
            resize_video(src_path, out_path, target_kb)
            print(f"  {name}")
            processed += 1
        except subprocess.CalledProcessError as e:
            print(f"  Skip {name}: ffmpeg failed", file=sys.stderr)
        except Exception as e:
            print(f"  Skip {name}: {e}", file=sys.stderr)

    print(f"Done. Processed {processed} file(s).")


if __name__ == "__main__":
    main()
