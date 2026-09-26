# Defect Guardian Academy narration

These MP3 files were generated locally with the official OpenBMB VoxCPM2
model. The voice began as an original Voice Design preset, not a real person,
and is now kept consistent by cloning the versioned synthetic reference at
`assets/training/voice/defect-guardian-narrator-reference.mp3`.

Voice direction:

> Professional British English male narrator, mid thirties, warm and
> trustworthy, clear construction training voice, measured pace, natural
> conversational delivery, confident but not theatrical.

The production website only serves the compressed MP3 files. VoxCPM2, Python,
CUDA and the model weights are not required on the web server.

To regenerate the clips, create a Python 3.10–3.12 environment with `voxcpm`
and `lameenc`, then run:

```powershell
python scripts/generate_training_voiceovers.py --device cuda
```

The generator uses `scripts/export_training_narration.php` so spoken copy stays
aligned with the lesson scene definitions in `training/TrainingContent.php`.
New or rewritten lessons therefore retain the same approved narrator without
depending on a new random Voice Design result.

VoxCPM2 code and model: <https://github.com/OpenBMB/VoxCPM> (Apache-2.0).
