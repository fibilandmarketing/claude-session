#!/usr/bin/env bash
# Regenerates the plugin from the design and packs dist/neuron-landing.zip
set -euo pipefail
cd "$(dirname "$0")/.."
python3 build/convert.py
python3 build/extract_lists.py
python3 build/make_partials.py
for f in $(find plugin/neuron-landing -name '*.php'); do php -l "$f" > /dev/null; done
mkdir -p dist
rm -f dist/neuron-landing.zip
(cd plugin && zip -qr ../dist/neuron-landing.zip neuron-landing -x '*.DS_Store' -x 'neuron-landing/assets/test.mp4')
echo "dist/neuron-landing.zip: $(du -h dist/neuron-landing.zip | cut -f1)"
