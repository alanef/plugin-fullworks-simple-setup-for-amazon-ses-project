# WordPress.org listing assets

Files in this folder are deployed by `.github/workflows/release.yml` to the
**`/assets/`** directory at the root of the plugin's WordPress.org SVN repo
(`https://plugins.svn.wordpress.org/fullworks-simple-setup-for-amazon-ses/`),
alongside `trunk/` and `tags/`.

These power the plugin's wp.org listing page. They are **not** shipped to users
and are **not** part of the installable zip. Editing them and pushing a release
tag updates the public listing.

> Note: the runtime CSS/JS that *does* ship to users lives in
> `fullworks-simple-setup-for-amazon-ses/assets/` — a different folder entirely.

## Expected filenames

WordPress.org matches assets by exact filename. Use these:

### Icon (square)
- `icon-128x128.png` (or `.jpg`)
- `icon-256x256.png` (or `.jpg`) — for retina/HiDPI
- `icon.svg` — optional; takes precedence over PNG if present

### Banner (header on the listing page)
- `banner-772x250.png` (or `.jpg`)
- `banner-1544x500.png` (or `.jpg`) — for retina/HiDPI

### Screenshots
- `screenshot-1.png`, `screenshot-2.png`, … (`.jpg` also allowed)
- The number maps to the captions in `readme.txt` under `== Screenshots ==`,
  in order (`screenshot-1` = first caption, etc.).

PNG is recommended for icons/banners with flat colour or text; JPG for photos.

## How deployment works

On a release tag, the workflow `rsync`s the contents of this folder into the
SVN `assets/` directory (with `--delete`, so removing a file here removes it
from wp.org too) and commits it in the same revision as the code release.