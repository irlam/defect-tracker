"""Generate Defect Tracker Academy narration with OpenBMB VoxCPM2.

The model and Python environment stay on the production workstation. Only the
compressed MP3 assets produced by this script are deployed with the PHP app.
"""

from __future__ import annotations

import argparse
import json
import random
import subprocess
from pathlib import Path

import lameenc
import numpy as np
import torch
from voxcpm import VoxCPM


DEFAULT_REFERENCE_AUDIO = Path("assets/training/voice/defect-guardian-narrator-reference.mp3")


def load_manifest(repo_root: Path) -> list[dict[str, object]]:
    command = ["php", str(repo_root / "scripts" / "export_training_narration.php")]
    result = subprocess.run(command, cwd=repo_root, check=True, capture_output=True, text=True)
    manifest = json.loads(result.stdout)
    return list(manifest["items"])


def encode_mp3(wav: np.ndarray, sample_rate: int, output: Path) -> None:
    audio = np.asarray(wav, dtype=np.float32).reshape(-1)
    peak = float(np.max(np.abs(audio))) if audio.size else 0.0
    if peak > 0.98:
        audio = audio * (0.98 / peak)
    pcm = np.clip(audio, -1.0, 1.0)
    pcm16 = (pcm * 32767.0).astype("<i2")

    encoder = lameenc.Encoder()
    encoder.set_bit_rate(96)
    encoder.set_in_sample_rate(sample_rate)
    encoder.set_channels(1)
    encoder.set_quality(2)
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_bytes(encoder.encode(pcm16.tobytes()) + encoder.flush())


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--model", default="openbmb/VoxCPM2")
    parser.add_argument("--device", default="cuda")
    parser.add_argument("--cache-dir", type=Path)
    parser.add_argument(
        "--reference-audio",
        type=Path,
        default=DEFAULT_REFERENCE_AUDIO,
        help="Stable VoxCPM2 voice reference, relative to the repository unless absolute.",
    )
    parser.add_argument("--force", action="store_true")
    parser.add_argument("--skip", type=int, default=0, help="Skip the first N manifest entries.")
    parser.add_argument("--limit", type=int, default=0)
    args = parser.parse_args()

    repo_root = args.repo.resolve()
    reference_audio = args.reference_audio
    if not reference_audio.is_absolute():
        reference_audio = repo_root / reference_audio
    reference_audio = reference_audio.resolve()
    if not reference_audio.is_file():
        parser.error(f"Voice reference does not exist: {reference_audio}")

    items = load_manifest(repo_root)
    if args.skip > 0:
        items = items[args.skip :]
    if args.limit > 0:
        items = items[: args.limit]

    pending = [item for item in items if args.force or not (repo_root / str(item["output"])).exists()]
    if not pending:
        print("All training narration assets already exist.")
        return 0

    model = VoxCPM.from_pretrained(
        args.model,
        device=args.device,
        cache_dir=str(args.cache_dir) if args.cache_dir else None,
        load_denoiser=False,
        optimize=False,
    )
    sample_rate = int(model.tts_model.sample_rate)

    for position, item in enumerate(pending, start=1):
        output = repo_root / str(item["output"])
        print(f"[{position}/{len(pending)}] {item['lesson']} scene {item['scene']}: {item['title']}")
        random.seed(240926)
        np.random.seed(240926)
        torch.manual_seed(240926)
        if torch.cuda.is_available():
            torch.cuda.manual_seed_all(240926)
        wav = model.generate(
            text=str(item["text"]),
            reference_wav_path=str(reference_audio),
            cfg_value=2.0,
            inference_timesteps=10,
            # The English normalizer currently fails on some typographic
            # apostrophes used in the lesson copy; VoxCPM2 handles the source
            # text directly and preserves the intended wording.
            normalize=False,
            retry_badcase=True,
            retry_badcase_max_times=3,
        )
        encode_mp3(wav, sample_rate, output)
        print(f"  saved {output.relative_to(repo_root)}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
