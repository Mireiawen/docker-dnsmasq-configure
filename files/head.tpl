########################################################################
# dnsmasq config, for a complete example, see:
#  http://oss.segetech.com/intra/srv/dnsmasq.conf

## Generic configuration
# Log all dns queries
log-queries

# Don't use hosts nameservers
no-resolv
no-poll

# Don't use hosts-file
no-hosts

# Require domain name when forwarding
domain-needed

# Don't forward private addresses to upstream
bogus-priv

########################################################################
## Upstream servers
# quadone / cloudflare
server=1.1.1.1
server=1.0.0.1

# quad9
#server=9.9.9.9
#server=149.112.112.112

