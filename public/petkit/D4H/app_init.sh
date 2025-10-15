#!/bin/sh

ETH0=`ls /sys/class/net | grep "eth0"`
SYS_WPA_CONF=/system/wpa_supplicant.conf

#cp /system/init/resolv.conf /tmp/resolv.conf
#ifconfig eth0 192.168.33.238 netmask 255.255.255.0
#route add default gw 192.168.33.1

diff /app/script/system_init.sh /system/system_init.sh > /dev/null
if [ $? != 0 ]; then
    md5sum -c /app/script/system_init.sh.md5
    if [ $? = 0 ]; then
        cp /app/script/system_init.sh /system/system_init.sh
    fi
fi

# after cut laspe, rmem need more then 25M, if change mtd size ,need change "UPDATE_BOOTARGS_STR"
OLD_RMEM_STR="42M@0x5600000"
UPDATE_BOOTARGS_STR="console=ttyS1,115200n8 mem=100M@0x0 rmem=28M@0x6400000 init=/linuxrc rootfstype=squashfs root=/dev/mtdblock2 rw mtdparts=jz_sfc:256k(boot),1536k(kernel),4096k(rootfs),4096k(backup),64k(user_cfg),64k(sys_cfg),-(appfs)"
RMEM_STR=`fw_printenv | grep $OLD_RMEM_STR`

# check rmem size.
if [[ "$RMEM_STR" == "" ]];then
echo "-------------- new, not update rmem, start app init --------------"
else
echo "-------------- old, need update mem & rmem --------------"
fw_setenv bootargs $UPDATE_BOOTARGS_STR
fi

echo "--------------------------mount audio--------------------------"
fcrc /system/audio.img
if [ $? -ne 0 ]; then
    rm /system/audio.img
fi
if [ -f /system/audio.img ]; then
    if [ ! -d /system/audio_file ];then
        mkdir /system/audio_file
    fi
    mount -t squashfs -o offset=64 /system/audio.img /system/audio_file
fi

fcrc /system/alg.img
if [ $? -ne 0 ]; then
    rm /system/alg.img
fi
if [ -f /system/alg.img ]; then
    if [ ! -d /system/alg_lib ];then
        mkdir /system/alg_lib
    fi
    mount -t squashfs -o offset=64 /system/alg.img /system/alg_lib
fi

#umount  /tmp/sd
#mount -t vfat /dev/mmcblk0p1 /tmp/sd -o rw,errors=continue

echo "--------------------------insmod driver--------------------------"

#insmod /lib/modules/tx-isp-t31.ko  isp_clk=200000000
#insmod /lib/modules/sensor_gc2053_t31.ko data_interface=1
#insmod /lib/modules/avpu.ko clk_name='vpll'

insmod /modules/tx-isp-t31.ko

# audo insmod sensor
SENSOR_MODULE_PATH=/app/etc/modules
INSMOD_SENSOR_PATH=

if [ -f ${SENSOR_MODULE_PATH}/pk_sinfo.ko ]; then
    insmod ${SENSOR_MODULE_PATH}/pk_sinfo.ko;
    echo 1 >/proc/jz/sinfo/info;
    SENSOR_NAME=`cat /proc/jz/sinfo/info | awk -F 'sensor :' '{printf $2}'`

    if [[ "${SENSOR_NAME}" == "gc2083" ]]; then
        INSMOD_SENSOR_PATH=${SENSOR_MODULE_PATH}/sensor_${SENSOR_NAME}_t31.ko
    else
        INSMOD_SENSOR_PATH=/modules/sensor_${SENSOR_NAME}_t31.ko
    fi

    echo ====== insmod sensor : ${INSMOD_SENSOR_PATH} ======
    insmod ${INSMOD_SENSOR_PATH}
else
    if [ -f /modules/sensor_gc2053_t31.ko ]; then
        insmod /modules/sensor_gc2053_t31.ko
    elif [ -f /modules/sensor_gc2083_t31.ko ]; then
        insmod /modules/sensor_gc2083_t31.ko
    else
        echo ====== unknow sensor type ======
    fi
fi
echo ============ get sensor name : ${SENSOR_NAME} ============

insmod /modules/audio.ko
insmod /modules/avpu.ko

# if [ -n "$ETH0" ]; then
#     echo "---- This is Eth0 device ----"
#     ifconfig eth0 up
# else
#     echo "---- This is WiFi device ----"
#     insmod /modules/8188fu.ko

    # #Load wifi
    if [ -f "$SYS_WPA_CONF" ];then
        /app/script/wifi_connect.sh &
    fi
#fi

export LD_LIBRARY_PATH='/app/bin:/usr/lib'

#Inject own CA
mount --bind /system/misc/ca.crt /app/bin/ca.crt
mount --bind /system/misc/ca.crt /app/etc/ca.crt
mount --bind /system/misc/ca.crt /etc/ca.crt
mount --bind /system/misc/default.script /app/script/default.script
mount --bind /system/misc/ctrl_patched /app/bin/ctrl

#Load app
echo "--------------------------start app--------------------------"
./system/_init.sh > /tmp/emergency 2>&1 &

cd /app/bin/
./ble &
./media &
./ctrl &
./tserver &

telnetd &
