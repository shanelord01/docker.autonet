#!/bin/bash

plugin_name="docker.autonet"
CWD=$(pwd)
tmpdir="$CWD/tmp/tmp.$(($RANDOM * 19318203981230 + 40))"
base_version=$(date +"%Y.%m.%d")

# Count same-day archives before creating anything, and fold that count into
# the version itself (not just the archive filename) - a same-day rebuild
# must get a version/URL the CDN has never cached before, or an install can
# fetch a stale .plg paired with a different .txz and fail an MD5 check.
existing=$(ls "$CWD"/archive/$plugin_name-"$base_version"*.txz 2>/dev/null | wc -l)
if [ "$existing" -gt 0 ]; then
    version="$base_version.$existing"
else
    version="$base_version"
fi
filename="$CWD/archive/$plugin_name-$version.txz"
mkdir -p "$tmpdir"

rsync -av --progress src/$plugin_name/ "$tmpdir" --exclude .git --exclude tmp --exclude .env --exclude archive

cd "$tmpdir" || exit

tar_command="tar"
sed_prefix="-i"

if [[ "$(uname)" == "Darwin" ]]; then
    tar_command="gtar"
    sed_prefix="-i ''"
fi
$tar_command --sort=name --mtime="@0" --owner=root --group=root -cJf "$filename" .

cd - || exit

if [[ "$(uname)" == "Darwin" ]]; then
    md5hash=$(md5 -q "$filename")
else
    md5hash=$(md5sum "$filename" | awk '{ print $1 }')
fi

rm -rf "$tmpdir"

sed "$sed_prefix" 's/<!ENTITY version ".*">/<!ENTITY version "'"$version"'">/' $plugin_name.plg
sed "$sed_prefix" 's/<!ENTITY md5 ".*">/<!ENTITY md5 "'"$md5hash"'">/' $plugin_name.plg

echo "MD5: $(md5sum "$filename")"
echo "once pushed install via https://raw.githubusercontent.com/shanelord01/$plugin_name/main/$plugin_name.plg"

$tar_command -tvf "$filename"

# Check for ownership issues
OWN=$($tar_command -tvf "$filename" | grep -v "root/root" | grep -v "root   root")
if [ -n "$OWN" ]; then
    echo "Ownership issues (should be root/root):"
    echo "$OWN"
    exit 1
fi

# Check for permission issues
PERM=$($tar_command -tvf "$filename" | grep -v "rwxr-xr-x" | grep -v "rw-r--r--")
if [ -n "$PERM" ]; then
    echo "Permission issues (should be rwxr-xr-x or rw-r--r--):"
    echo "$PERM"
    exit 1
fi
