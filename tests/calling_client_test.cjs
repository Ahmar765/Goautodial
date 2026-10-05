// Runs the actual PHP-rendered dialer scripts with mocked media/network services.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
let checks = 0;
function check(condition, message) { assert.ok(condition, message); checks++; }
async function testView(view, mode, microphoneAllowed = true) {
  const html = fs.readFileSync(path.join(root, 'tmp', `calling-${view}-${mode}.html`), 'utf8');
  for (const asset of html.matchAll(/(?:src|href)=['"](https:\/\/crm\.example\.test[^'"]+)['"]/g)) {
    const url = new URL(asset[1]);
    check(fs.existsSync(path.join(root, url.pathname)), `${view}: module asset exists and URL uses forward slashes`);
  }
  const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match => match[1]).filter(text => text.trim());
  check(scripts.length === 1, `${view}: inline script found`);
  const alerts = [];
  class Socket { constructor(url) { this.url = url; } }
  class UA {
    constructor(configuration) { this.configuration = configuration; this.handlers = {}; this.stopped = false; }
    on(event, callback) { this.handlers[event] = callback; }
    stop() { this.stopped = true; }
  }
  function jquery() { return { css() {}, snackbar() {} }; }
  jquery.prototype.snackbar = true;
  jquery.snackbar = () => {};
  const audio = { srcObject: null };
  const context = vm.createContext({
    JsSIP: { WebSocketInterface: Socket, UA }, document: { querySelector: () => audio }, $: jquery,
    navigator: { mediaDevices: { getUserMedia: () => microphoneAllowed ? Promise.resolve({ fixture: true }) : Promise.reject(new Error('Denied')) } },
    console: { log() {}, error() {} }, swal: (value) => alerts.push(value), XMLHttpRequest: class {},
    phone_pass: "fixture'phone", server_ip: 'pbx.example.test', phoneRegistered: false, registrationFailed: false,
    sendLogout() {}, MediaStream: class { constructor(tracks) { this.tracks = tracks; } }
  });
  vm.runInContext(scripts[0], context, { timeout: 1000 });
  if (view === 'admin') vm.runInContext('registerPhone(phone_login, phone_pass)', context);
  await new Promise(resolve => setImmediate(resolve));
  check(context.configuration.sockets[0].url === 'wss://ws.example.test:8089/', `${view}: proxy HTTPS uses WSS`);
  check(context.configuration.uri === `sip:1001@${mode === 'fallback' ? 'pbx.example.test' : 'sip.example.test'}:5070`, `${view}: SIP domain and port`);
  check(context.configuration.password === "fixture'phone", `${view}: quoted credential retained`);
  const JsSIP = require(path.join(root, 'modules', 'GOagent', 'js', view === 'agent' ? 'jssip-3.4.4.js' : 'jssip-3.0.13.js'));
  const configuration = { ...context.configuration, sockets: [new JsSIP.WebSocketInterface(context.configuration.sockets[0].url)] };
  const realUA = new JsSIP.UA(configuration); // No .start(): no connection or registration occurs.
  check(realUA.configuration.uri.toString() === configuration.uri, `${view}: bundled JsSIP accepts configuration`);
  if (!microphoneAllowed) {
    check(alerts.some(alert => alert.title === 'Microphone NOT Detected'), `${view}: microphone denial visible`);
    return;
  }
  context.phone.handlers.registered({});
  check(context.phoneRegistered, `${view}: registered state`);
  context.phone.handlers.unregistered({});
  check(!context.phoneRegistered, `${view}: unregistered state`);
  context.phone.handlers.registered({});
  context.phone.handlers.registrationFailed({ cause: 'Rejected' });
  check(!context.phoneRegistered, `${view}: failed registration clears state`);
  const mediaHandlers = {};
  const session = { on() {}, answer() { this.answered = true; }, connection: { addEventListener(event, callback) { mediaHandlers[event] = callback; } } };
  context.phone.handlers.newRTCSession({ originator: 'remote', session });
  check(session.answered, `${view}: incoming session answered`);
  check(typeof mediaHandlers.track === 'function', `${view}: modern remote audio track handler`);
  const stream = { fixture: 'remote-media' };
  mediaHandlers.track({ streams: [stream], track: {} });
  check(audio.srcObject === stream, `${view}: incoming audio attached`);
  check(context.globalSession === session, `${view}: active session available for call controls`);
  const outgoing = { ...session, answered: false };
  context.phone.handlers.newRTCSession({ originator: 'local', session: outgoing });
  check(!outgoing.answered, `${view}: outgoing session not answered as inbound`);
}
(async () => {
  for (const view of ['agent', 'admin']) for (const mode of ['configured', 'fallback']) await testView(view, mode);
  for (const view of ['agent', 'admin']) await testView(view, 'configured', false);
  const standalone = fs.readFileSync(path.join(root, 'tmp', 'calling-sip.html'), 'utf8');
  const context = vm.createContext({ window: {} });
  for (const script of standalone.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) vm.runInContext(script[1], context);
  check(context.window.SETTINGS.registrar_server === 'sip.example.test', 'Standalone registrar uses SIP domain');
  for (const file of standalone.matchAll(/(?:src|href)='([^']+)'/g)) check(fs.existsSync(path.join(root, file[1])), `Standalone asset exists: ${file[1]}`);
  const main = fs.readFileSync(path.join(root, 'tmp', 'calling-main.js'), 'utf8');
  new vm.Script(main);
  check(main.includes("var pass = '';\nvar uPass = '';"), 'Main renderer keeps account API password server-side');
  check(!main.includes('fixture-account-password'), 'Main renderer omits account password');
  console.log(`${checks} rendered calling JavaScript checks passed. Media and events simulated; no live calls.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
