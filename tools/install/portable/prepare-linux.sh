#!/bin/sh
# One-time administrator companion. No service changes, user creation, database access or downloaded code.
set -eu
if [ "$#" -ne 4 ]; then echo 'Usage: sh prepare-linux.sh /absolute/finance installer_user web_user web_group' >&2; exit 2; fi
finance_root=$1
installer_uid=$(id -u "$2")
web_uid=$(id -u "$3")
web_gid=$(getent group "$4" | cut -d: -f3)
case "$finance_root" in /*) ;; *) exit 2;; esac
if [ "$finance_root" = / ] || [ "$(realpath "$finance_root")" != "$finance_root" ] || [ -z "$web_gid" ] || [ "$installer_uid" = 0 ] || [ "$web_uid" = 0 ] || [ "$installer_uid" = "$web_uid" ]; then echo 'Use a canonical package folder and distinct non-root accounts.' >&2; exit 2; fi
if [ ! -f "$finance_root/installer/layout.json" ] || [ -e "$finance_root/config/customer.json" ] || [ -e "$finance_root/private/delivery-state.json" ]; then echo 'Only a NEW extracted v6 package may be provisioned.' >&2; exit 2; fi
if [ -n "$(find "$finance_root" -type l -print -quit)" ]; then echo 'Symlink rejected.' >&2; exit 2; fi
# The exact validated new package is the only ownership target.
find "$finance_root" -type d -exec chmod 0750 {} +
find "$finance_root" -type f -exec chmod 0640 {} +
chown -R "$installer_uid:$web_gid" "$finance_root"
for part in private private/agent private/delivery storage storage/license storage/setup; do
  mkdir -p "$finance_root/$part"
  chown "$installer_uid:$web_gid" "$finance_root/$part"
  chmod 0750 "$finance_root/$part"
done
chmod 0700 "$finance_root/private" "$finance_root/private/agent" "$finance_root/private/delivery"
for part in storage/sessions storage/logs storage/cache public/uploads public/assets/uploads; do
  mkdir -p "$finance_root/$part"
  chown "$web_uid:$web_gid" "$finance_root/$part"
  chmod 0750 "$finance_root/$part"
done
mkdir -p "$finance_root/storage/inbox"
chown "$installer_uid:$web_gid" "$finance_root/storage/inbox"
chmod 2770 "$finance_root/storage/inbox"
echo 'Folder prepared. Copy verified Control delivery to private/delivery, then run prepare as installer_user (not root).'
