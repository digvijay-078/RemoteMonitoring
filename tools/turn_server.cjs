const Turn = require('node-turn');
const server = new Turn({
  authMech: 'long-term',
  credentials: {
    'remotemonitor': 'turnsecret'
  },
  listeningPort: 3478
});
server.start();
console.log('STUN/TURN server running on UDP/TCP port 3478 with long-term auth');
