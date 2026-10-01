const Turn = require('node-turn');
const server = new Turn({
  authMech: 'long-term',
  credentials: {
    'remotemonitor': 'turnsecret'
  },
  listeningPort: 3478
});
server.start();
console.log('TURN server running on port 3478');
