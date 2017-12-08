#/usr/bin/bash
sudo docker run --rm -it --net local -v "$PWD/src:/app" -v "$PWD/data:/data" mysqlphinxdump -- $*
