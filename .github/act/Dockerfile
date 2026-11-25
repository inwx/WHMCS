# act runner with its own dockerd, so DinD actions (php-actions/composer) see the
# job container's paths in `docker run -v`, like on GitHub's runners.
# Build: docker build -t act-dind .github/act
FROM catthehacker/ubuntu:act-latest
# apt lists kept on purpose: GitHub runners have them, and the WHMCS plugin's
# prepare step runs apt (puppeteer --install-deps)
RUN install -m 0755 -d /etc/apt/keyrings \
 && curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc \
 && echo "deb [signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" > /etc/apt/sources.list.d/docker.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends docker-ce containerd.io iptables
COPY docker-shim.sh /usr/local/bin/docker
# own fs for nested overlay2
VOLUME /var/lib/docker
