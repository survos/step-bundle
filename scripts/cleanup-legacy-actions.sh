#!/usr/bin/env bash
set -euo pipefail

# Adjust ROOT if you run from a different directory
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

declare -a FILES=(
  "src/Metadata/Actions/Bash.php"
  "src/Metadata/Actions/Console.php"
  "src/Metadata/Actions/Composer.php"
  "src/Metadata/Actions/ComposerRequire.php"
  "src/Metadata/Actions/ImportmapRequire.php"
  "src/Metadata/Actions/YamlWrite.php"
  "src/Metadata/Actions/FileWrite.php"
  "src/Metadata/Actions/DisplayCode.php"
  "src/Metadata/Actions/BrowserVisit.php"
  "src/Metadata/Actions/Section.php"
  # Optional (only if replaced by polymorphic versions)
  # "src/Metadata/Actions/ShowClass.php"
  # "src/Metadata/Actions/BrowserClick.php"
  # "src/Metadata/Actions/BrowserAssert.php"
  # "src/Metadata/Actions/Env.php"
  # "src/Metadata/Actions/FileCopy.php"
  # "src/Metadata/Actions/RequirePackage.php"
)

echo "About to git rm the following files (if present):"
for f in "${FILES[@]}"; do
  if [[ -e "$ROOT/$f" ]]; then
    echo "  $f"
  fi
done

read -r -p "Proceed with git rm? [y/N] " ANS
if [[ "${ANS:-N}" =~ ^[yY]$ ]]; then
  for f in "${FILES[@]}"; do
    if [[ -e "$ROOT/$f" ]]; then
      git rm "$ROOT/$f"
    fi
  done
  echo "Done."
else
  echo "Aborted."
fi
