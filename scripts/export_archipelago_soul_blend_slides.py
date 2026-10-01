from pathlib import Path

from PIL import Image, ImageOps


SOURCE_DIR = Path(
    r"C:\Users\fuadm\.codex\generated_images\01a00b74-b253-7b80-89d7-67ea88c7ac0d"
)
OUTPUT_DIR = Path(
    r"C:\xampp\htdocs\finance\assets\roastery\shopee-slides-archipelago-soul-blend"
)

SOURCES = [
    "exec-5dc00af3-2c38-4aef-998b-f3b0a42b1932.png",
    "exec-24f31afc-bacd-4850-9fca-ee85e4ed4740.png",
    "exec-f7f1fc41-71c7-4caf-bf4b-aca6a06e5f2a.png",
    "exec-fdf5ea86-49b3-4fb7-93b9-fdf651b36ffa.png",
    "exec-508b6738-3279-4c77-b22e-7f71aea111c6.png",
    "exec-b0b514bb-89b8-49e5-9c7d-dabf6d42e1e7.png",
    "exec-a6423ebb-6cd5-4160-a6a0-ad5e3b49ef93.png",
    "exec-3c945355-e50c-46ae-8e05-73d34c500e6b.png",
]


def export_slide(source: Path, destination: Path) -> None:
    with Image.open(source) as image:
        square = ImageOps.fit(
            image.convert("RGB"),
            (1200, 1200),
            method=Image.Resampling.LANCZOS,
            centering=(0.5, 0.5),
        )

        for quality in range(92, 69, -2):
            square.save(
                destination,
                "JPEG",
                quality=quality,
                optimize=True,
                progressive=True,
            )
            if destination.stat().st_size < 2_000_000:
                return

        raise RuntimeError(f"Could not compress {destination.name} below 2 MB")


OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
for index, source_name in enumerate(SOURCES, start=1):
    output = OUTPUT_DIR / f"archipelago-soul-blend-slide-{index:02d}.jpg"
    export_slide(SOURCE_DIR / source_name, output)
    with Image.open(output) as exported:
        print(
            f"{output.name}: {exported.width}x{exported.height}, "
            f"{output.stat().st_size / 1024:.1f} KB"
        )
