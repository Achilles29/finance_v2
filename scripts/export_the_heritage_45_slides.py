from pathlib import Path

from PIL import Image, ImageOps


SOURCE_DIR = Path(
    r"C:\Users\fuadm\.codex\generated_images\01a00b74-b253-7b80-89d7-67ea88c7ac0d"
)
OUTPUT_DIR = Path(
    r"C:\xampp\htdocs\finance\assets\roastery\shopee-slides-the-heritage-45"
)

SOURCES = [
    "exec-9b65226c-31f7-41f2-a5e9-c216e9052477.png",
    "exec-616cde95-138e-45f8-93c7-2f68944fb5af.png",
    "exec-e6bba1b3-b79e-44f9-891f-550e60f2ca62.png",
    "exec-ecbd6bfd-ff01-427f-b457-9fc479cb39cf.png",
    "exec-39001394-9ee9-48e0-b53f-e6b090e3d909.png",
    "exec-b576d35b-b041-406a-9556-f53825af2ad0.png",
    "exec-aa23b292-c497-412a-95e3-6bbf9f5d5921.png",
    "exec-8eccc750-f248-4ad0-8a24-e485ecf39d61.png",
]


def export_slide(source: Path, destination: Path) -> None:
    with Image.open(source) as image:
        rgb = image.convert("RGB")
        square = ImageOps.fit(
            rgb,
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
    output = OUTPUT_DIR / f"the-heritage-45-slide-{index:02d}.jpg"
    export_slide(SOURCE_DIR / source_name, output)
    with Image.open(output) as exported:
        print(
            f"{output.name}: {exported.width}x{exported.height}, "
            f"{output.stat().st_size / 1024:.1f} KB"
        )
