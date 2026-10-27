#!/bin/bash
# run full manual scan via admin-ajax like the dashboard does
C=$(python3 -c "import json;print(json.load(open('/tmp/claude-0/auth.json'))['cookie'])"); N=$(python3 -c "import json;print(json.load(open('/tmp/claude-0/auth.json'))['nonce'])")
U=http://localhost:8081/wp-admin/admin-ajax.php
p(){ curl -s -b "$C" -d "action=mm_scan&nonce=$N&$1" $U; }
p "phase=reset" | head -c 100; echo
p "phase=traffic" | head -c 150; echo
pg=1; while :; do r=$(p "phase=orders&page=$pg"); echo "$r" | head -c 160; echo; python3 -c 'import json,sys;d=json.loads(sys.argv[1])["data"];sys.exit(0 if d["page"]>=d["max_pages"] else 1)' "$r" && break; pg=$((pg+1)); [ $pg -gt 50 ] && break; done
p "phase=finish" | head -c 200; echo
