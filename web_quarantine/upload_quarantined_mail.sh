#!/bin/bash
# Arguments:
# $1 = Absolute path to the file to upload.
# $2 = Origin server FQDN (which have the message frozen in queue).
# $3 = List of recipients (separated by ", ").

URI="https://www.example.com/quarantine/"
PASS="MySecretPasswordSharedWithQuarantineServer"

[ -z "$3" ] && exit 1
[ ! -r $1 ] && exit 1

SEED="$((RANDOM+$(date +%s)))"

# Upload the mbox file
curl -s \
  -F "f=@$1" \
  -F "srv=$2" \
  -F "rcpt=$3" \
  -F "s=$SEED" \
  -F "k=$(echo -n "${SEED}${PASS}" | sha1sum | cut -d' ' -f1)" \
  ${URI}

