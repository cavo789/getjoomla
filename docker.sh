#! /bin/bash

clear

docker-compose up -d

printf '\e[1;32m%-6s\n\n\e[m' "Open a browser and navigate to http://127.0.0.1:8080 to let the script starts"
printf '\e[1;32m%-6s\n\n\e[m' "Run docker exec -it getjoomla_php_1 /bin/bash in a CLI to start an interactive shell"

