#!/bin/bash

git status --porcelain | while IFS= read -r line; do
  status="${line:0:2}"
  file="${line:3}"

  # Skip deleted files
  if [ ! -f "$file" ]; then
    continue
  fi

  git add "$file"
  git commit -m "feat: $file"
  echo "Committed: $file"
done

echo "========DONE==========="
