#!/bin/sh

sleep 15

if [ ! -f /system/misc/go2rtc.yml ]; then
    cd /system/misc
    wget http://api-eu.petkt.com/petkit/D4H/go2rtc.yml
fi

#Go2RTC
cd /tmp
wget http://10.10.48.85/petkit/D4H/go2rtc
chmod +x go2rtc
./go2rtc -c /system/misc/go2rtc.yml &
