Swoole UDP Hole punching
=========================

Usage
-------------------------
A public machine acts as a server (running server.php), two
clients behind NAT start client.php.
A client can ask the rendezvous server to help them him to connect to another
client using the TCP hole punching procedure.

1. Server(WAN)
    node server.js

2. Client A
    php client.php [server] [nameA]

3. Client B
    php client.js [server] [nameB] [nameA]


