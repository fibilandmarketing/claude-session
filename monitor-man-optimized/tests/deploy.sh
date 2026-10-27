#!/bin/bash
# deploy.sh <plugin-dir> : swap plugin and reset Apache OPcache
rm -rf /tmp/claude-0/wpsite/wp-content/plugins/monitor-man && cp -r "$1" /tmp/claude-0/wpsite/wp-content/plugins/monitor-man && chown -R 33:33 /tmp/claude-0/wpsite/wp-content/plugins/monitor-man
curl -s http://localhost:8081/opreset.php >/dev/null; sleep 1; curl -s http://localhost:8081/opreset.php; echo " -> $(grep -m1 'Version:' /tmp/claude-0/wpsite/wp-content/plugins/monitor-man/monitor-man.php | tr -s ' ')"
