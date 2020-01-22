set -x
cd tests/ab

SKIP_DEFLATE=
if [ "$TRAVIS" = "true" ]; then
if [ $(phpenv version-name) = "hhvm" -o $(phpenv version-name) = "5.4" -o $(phpenv version-name) = "5.5" -o $(phpenv version-name) = "5.6" ]; then
    echo "Skipping deflate autobahn tests for $(phpenv version-name)"
    SKIP_DEFLATE=_skip_deflate
fi
fi

wstest -m fuzzingserver -s fuzzingserver$SKIP_DEFLATE.json &
sleep 5
php clientRunner.php

sleep 2

php startServer.php &
sleep 3

wstest -m fuzzingclient -s fuzzingclient$SKIP_DEFLATE.json
sleep 1
kill $(ps aux | grep 'php startServer.php' | awk '{print $2}' | head -n 1)

