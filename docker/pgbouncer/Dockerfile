# SPEC-015 RLT-NF01: PgBouncer in transaction mode, for the isolation tests.
# Alpine's own package (1.21 or later), so no third-party image is trusted.
FROM alpine:3.20

RUN apk add --no-cache pgbouncer \
    && pgbouncer --version

COPY pgbouncer.ini userlist.txt /etc/pgbouncer/

# PgBouncer refuses to run as root.
USER nobody
EXPOSE 6432
CMD ["pgbouncer", "/etc/pgbouncer/pgbouncer.ini"]
