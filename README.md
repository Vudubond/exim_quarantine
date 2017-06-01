# Exim Quarantine

These code snippets could be used to implement a simplistic quarantine for Exim.

Either to simply keep a local copy on the same Exim server, so the postmaster
could check blocked messages later (**Local Quarantine**), or either to provide
a web page for end users to manage themselves the blocked messages (**Web
Quarantine**).

This uses the ability of Exim to run random commands through the `${run ...}`
[expansion
item](http://www.exim.org/exim-html-current/doc/html/spec_html/ch-string_expansions.html).
When Exim receives a message [detected as
malware](http://www.exim.org/exim-html-current/doc/html/spec_html/ch-content_scanning_at_acl_time.html)
(through the `malware = *` condition), instead of rejecting the message as it
would usually do, it do a `fakereject` and then uploads the mbox file (through
a Bash script using `curl`) to a web PHP script. This PHP script will convert
the mbox file to HTML and notify each recipients.

## Installation

### Local Quarantine

Configure your Exim with an ACL rule wich will scan for malware and copy the
.eml file locally.

See the example in [exim_sample.conf](local_quarantine/exim_sample.conf).

Add a cron job to purge old messages:

```bash
sudo crontab -u Debian-exim -l
0 0 * * *  find /var/spool/quarantine -type f -mtime +15 -delete >/dev/null
```

### Web Quarantine

#### Prerequisites

- On the Exim Server:
  - `curl`: used to upload mbox files to the web server;
- On the Web Server:
  - `mhonarc`: to convert mbox to HTML; and
  - `swaks`: to send the action mail to a specific SMTP server.

#### Exim Server

Make sure `curl` is installed (or modify
[upload_quarantined_mail.sh](web_quarantine/upload_quarantined_mail.sh) to use
another upload tool).

Configure your Exim with an ACL rule wich will scan for malware and upload the
.eml file to a remote web server. See the example in
[exim_sample.conf](web_quarantine/exim_sample.conf).

Copy the script
[upload_quarantined_mail.sh](web_quarantine/upload_quarantined_mail.sh)
somewhere (by example `/usr/local/bin`) and set it's execution bit (`chmod
+x`).

Add a cron job to purge old messages (to prevent any race condition, you may
want to delete them after having deleted them on the web server, ie. 16 days on
Exim, if 15 days on Web):

```bash
sudo crontab -u Debian-exim -l
0 1 * * *  TIMEOUT=15; TIMEOUT_UNIT=d; exim -bpr |grep -E "^[[:digit:]]+${TIMEOUT_UNIT}\s" |while read age a msgid b; do if [ "${age/${TIMEOUT_UNIT}}" -gt "${TIMEOUT}" ]; then exim -Mrm "$msgid" >/dev/null; fi; done
```

#### Web Server

Make sure `mhonarc` and `swaks` are installed.

The web server (Apache, Nginx, etc.) must support PHP. Copy the PHP script
[quarantine.php](web_quarantine/quarantine.php) somewhere in the document root
(by example as `/var/www/quarantine/index.php`), and also create there a
subfolder `m` with write permissions for `www-data` user.

Finally, don't forget the cron job to purge the files:

```bash
sudo crontab -u www-data -l
0 0 * * *  find /var/www/quarantaine/m/ -mtime +15 -delete >/dev/null
```

