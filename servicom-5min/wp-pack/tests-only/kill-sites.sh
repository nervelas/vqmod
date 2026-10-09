#!/bin/bash
for p in $(ps -eo pid,args | awk '/php -S 127.0.0.1:81(2[1-9]|3[0-9]) router.php/ && !/awk/{print $1}'); do kill $p 2>/dev/null; done
