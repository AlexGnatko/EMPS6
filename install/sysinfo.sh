#!/usr/bin/env bash
# Quick system summary: CPU, RAM, disk, OS

model=$(lscpu | awk -F: '/^Model name/{gsub(/^[ \t]+/,"",$2); print $2; exit}')
threads=$(nproc)
sockets=$(lscpu | awk -F: '/^Socket\(s\)/{gsub(/ /,"",$2); print $2; exit}')
cores_per=$(lscpu | awk -F: '/^Core\(s\) per socket/{gsub(/ /,"",$2); print $2; exit}')
cores=$(( ${sockets:-1} * ${cores_per:-$threads} ))

# Frequency: use max MHz if available, otherwise what /proc/cpuinfo reports (VMs)
max_mhz=$(lscpu | awk -F: '/CPU max MHz/{gsub(/ /,"",$2); print $2; exit}')
if [ -n "$max_mhz" ]; then
    freq=$(awk -v m="$max_mhz" 'BEGIN{printf "%.2f GHz (max)", m/1000}')
else
    cur_mhz=$(awk -F: '/cpu MHz/{gsub(/ /,"",$2); print $2; exit}' /proc/cpuinfo)
    freq=$(awk -v m="$cur_mhz" 'BEGIN{printf "%.2f GHz (reported)", m/1000}')
fi

os=$(. /etc/os-release && echo "$PRETTY_NAME")
virt=$(systemd-detect-virt 2>/dev/null || echo "unknown")

echo "OS:          $os"
echo "Kernel:      $(uname -r)"
echo "Virtualized: $virt"
echo "CPU:         $model"
echo "Cores:       $cores"
echo "Threads:     $threads"
echo "Frequency:   $freq"
free -h | awk '/^Mem:/{print "RAM:         "$2} /^Swap:/{print "Swap:        "$2}'
df -h / | awk 'NR==2{print "Disk (/):    "$2" total, "$4" free"}'
